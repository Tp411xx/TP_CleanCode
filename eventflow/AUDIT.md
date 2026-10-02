# Audit initial

## 1. Comportement observable

Entrées codées en dur dans index.php :

Client : id 42, lea@example.com, type vip
Billet : DAY-1 « Pass Jour 1 », 79,90 € × 2
Type de pass : day
Moyen de paiement : stripe

Sortie console :

PAYMENT stripe_143.82
SQL INSERT booking=1001 total=143.82 status=confirmed
EMAIL lea@example.com: booking 1001 confirmed
TOTAL FINAL: 143.82

## 2. Problèmes identifiés

# Problème Catégorie Impact

1 BookingService::confirm() fait tout dans une seule méthode : validation, calcul du total, remises, paiement, changement de statut, persistance et email. Les echo de log sont mélangés à la logique. /Lisibilité / Responsabilité/ Méthode longue, avec 5 raisons de changer. Toute modification risque de casser une autre partie.

2 Les throw new RuntimeException('...') sont répétés 6 fois avec des messages en dur, et la chaîne 'stripe' revient à plusieurs endroits. Les validations sont écrites en ligne. /Duplication/ Les erreurs ne sont pas distinguables par type. Duplication limitée aujourd'hui, mais les mêmes contrôles seront recopiés dans tout nouveau service.

3 new StripeClient() et new EmailService() sont créés dans la méthode, et les effets (paiement, SQL, email) passent par echo. Rien n'est injecté. /Testabilité / Couplage/ On ne peut pas tester le calcul sans déclencher Stripe et l'email. Impossible de vérifier les effets avec de faux services. Ajouter SMS, fidélité ou analytics obligera à modifier confirm().

# Problème Catégorie Impact

1 BookingService::confirm() fait tout dans une seule méthode : validation, calcul du total, remises, paiement, changement de statut, persistance et email. Les echo de log sont mélangés à la logique. Lisibilité / Responsabilité Moyen

2 Les throw new RuntimeException('...') sont répétés 6 fois avec des messages en dur, et la chaîne 'stripe' revient à plusieurs endroits. Les validations sont écrites en ligne. Duplication faible

3 new StripeClient() et new EmailService() sont créés dans la méthode, et les effets (paiement, SQL, email) passent par echo. Rien n'est injecté. Testabilité / Couplage Haute

4 StripeClient::charge(float $amount) reçoit un float, sans devise ni centimes entiers. PayFastSdk attend au contraire amount_cents (entier) et currency. /Règle métier/ Moyen

5 Les règles de remise sont en dur : 'vip', 0.90, '3days', 10.0. Rien n'empêche un total ≤ 0 (par exemple un billet à moins de 10 € avec le Pass 3 jours). L'ordre des remises (pourcentage puis montant fixe) n'est ni documenté ni testé. /Règle métier/ haute

6 Le moyen de paiement est choisi par un if / elseif dans confirm(). payfast lève « not implemented », alors que PayFastSdk existe avec une interface différente de StripeClient (tableau en entrée, tableau en sortie, classe non modifiable). /Couplage / Extensibilité/ critique

## 3. Nos trois priorités

1.Le moyen de paiement (#6, avec #4) : c'est ce qui bloque directement l'ajout de PayFast. Il faut un point d'extension et un adaptateur pour PayFastSdk, qui ne doit pas être modifié.

2.Les règles de remise (#5) : elles sont déjà qualifiées d'« anciennes » dans le code et vont évoluer. Il faut les sortir de confirm() et gérer le cas d'un total ≤ 0.

3.Les dépendances et les effets de bord (#3, avec et #1) : sans injection des services, on ne peut ni tester ni ajouter SMS, fidélité et analytics proprement.

## 4. Risques avant refactoring

Tests insuffisants. Les 3 tests ne couvrent ni les erreurs, ni PayFast, ni VIP + Pass 3 jours, et ne vérifient pas les sorties console. Il faut les compléter avant de toucher à confirm() (#1, #3, #5).
Comportement à conserver. L'ordre paiement, statut, SQL, email et l'ordre des remises (VIP puis montant fixe) doivent rester identiques (#1, #5).
Argent en centimes (#4). Le changement peut modifier des arrondis, que la tolérance de 0,001 des tests pourrait masquer.
PayFastSdk non modifiable (#6). Tout doit passer par un adaptateur.
