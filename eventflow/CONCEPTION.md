# Note de conception

## 1. Choix principaux

- Une interface PaymentGateway pour que BookingService ne dépende pas d'un moyen de paiement précis. Stripe et PayFast ont des interfaces différentes, et PayFastSdk ne doit pas être modifié : chaque moyen de paiement est donc enveloppé dans sa propre classe.
- Une supervision séparée de la logique de paiement : un décorateur SupervisedPaymentGateway mesure la durée, journalise le montant et le résultat, sans modifier StripeClient , PayFastSdk ni les gateways.
- Un découpage de BookingService::confirm() en petites méthodes ( validate() , calculateTotal() , createGateway() ) et des constantes à la place des valeurs écrites en dur, sans changer le comportement.
- Peu de nouvelles classes : nous avons gardé le changement le plus petit possible pour limiter le risque de régression.

## 2. Principes SOLID mobilisés

Responsabilité unique (SRP)

- Problème initial : confirm() validait, calculait, choisissait le paiement, payait, enregistrait et envoyait l'email.
- Classes concernées : BookingService , SupervisedPaymentGateway .
- Bénéfice : chaque méthode a un rôle clair, et la supervision est dans sa propre classe au lieu d'être mélangée à la logique de réservation.

Ouvert/fermé (OCP)

- Problème initial : ajouter un comportement autour du paiement obligeait à modifier le code existant.
- Classes concernées : PaymentGateway , SupervisedPaymentGateway .
- Bénéfice : nous avons ajouté la supervision sans modifier les gateways ni les SDK. Un nouveau moyen de paiement s'ajoute avec une nouvelle classe. Il reste une ligne à ajouter dans createGateway() .

Inversion des dépendances (DIP), de façon partielle

- Problème initial : BookingService dépendait directement de Stripe.
- Classes concernées : BookingService , PaymentGateway .
- Bénéfice : confirm() ne manipule plus que l'interface PaymentGateway . C'est partiel, car createGateway() et l'email utilisent encore new dans la classe.

Substitution de Liskov (LSP)

- Bénéfice : SupervisedPaymentGateway peut remplacer n'importe quelle PaymentGateway . Il renvoie le même résultat et relance les mêmes exceptions.

Nous n'avons pas appliqué l'inversion des interfaces (ISP), car l'interface PaymentGateway n'a qu'une méthode.

## 3. Design Patterns éventuellement utilisés

Adapter ( StripePaymentGateway , PayFastPaymentGateway )

- Problème : StripeClient reçoit un float et PayFastSdk attend un tableau avec des centimes et une devise, et cette classe est interdite de modification.
- Solution : chaque gateway convertit l'appel commun pay($amount, $reference) vers l'API de son fournisseur.
- Pourquoi pas plus simple : un if dans confirm() aurait mélangé les détails de chaque fournisseur avec la logique de réservation.

Decorator ( SupervisedPaymentGateway )

- Problème : mesurer la durée et journaliser chaque paiement sans toucher aux classes existantes.
- Solution : une classe qui implémente PaymentGateway , enveloppe une autre gateway, chronomètre l'appel puis journalise le résultat.
- Pourquoi pas plus simple : mettre les logs dans confirm() aurait alourdi une méthode déjà trop chargée et il aurait fallu les répéter pour chaque moyen de paiement.

Aucun pattern pour les remises. Il n'y a que deux règles (VIP et Pass 3 jours), donc des constantes nommées dans calculateTotal() suffisent. Un pattern Strategy aurait ajouté des classes sans bénéfice pour le moment. Nous l'envisagerions si les règles devenaient plus nombreuses.

## 4. Solutions envisagées puis écartées

Supervision des paiements (ticket #105).

- Ajouter le chronomètre et les logs directement dans StripeClient et PayFastSdk : écarté, car le ticket interdit de les modifier et PayFastSdk est un SDK externe.
- Mettre les logs dans BookingService::confirm() : écarté, car cette méthode faisait déjà trop de choses et il aurait fallu recopier le code pour chaque moyen de paiement.
- Solution retenue : un décorateur SupervisedPaymentGateway placé autour de PaymentGateway , sans toucher aux classes existantes.

Notification Slack.

- Appeler Slack directement dans confirm() , comme l'email : écarté, car chaque nouvelle notification (SMS, fidélité, analytics) obligerait à modifier la méthode.
- Système d'événements complet : écarté, car il serait trop lourd pour 2 ou 3 notifications. Une simple liste de Notifier suffit.

Refactoring de BookingService .

- Créer une classe par règle de remise : écarté pour l'instant, car il n'y a que deux règles. Nous avons seulement nommé les valeurs en constantes et extrait des méthodes privées.

## 5. Ce que nous améliorerions avec plus de temps

- Injecter les dépendances dans BookingService (passerelles, EmailService ) au lieu de les créer avec new dans la classe, pour tester avec de faux services.
- Ajouter une interface Logger à la place de l'écriture sur STDERR dans SupervisedPaymentGateway , afin de pouvoir vérifier les messages dans un test.
- Sortir les règles de remise dans des classes séparées si leur nombre augmente, avec un test sur leur ordre et un cas pour un total inférieur ou égal à 0.
- Compléter les tests : cas d'erreur de PayFast, VIP avec Pass 3 jours, et vérification de la sortie console.
- Utiliser des centimes entiers pour les montants, plutôt que des float , afin d'éviter les erreurs d'arrondi.
