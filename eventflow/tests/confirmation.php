<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

$failures = 0;

function check(string $label, bool $condition): void
{
    global $failures;
    echo ($condition ? '[OK] ' : '[KO] ') . $label . PHP_EOL;
    if (!$condition) {
        $failures++;
    }
}

function makeBooking(?string $phone = null): Booking
{
    $booking = new Booking(1001, new Customer(1, 'lea@example.com', $phone), 'day');
    $booking->addItem(new BookingItem(new Ticket('DAY-1', 'Pass Jour', 100.0), 1));

    return $booking;
}

function run(BookingService $service, Booking $booking, string $method = 'stripe'): string
{
    ob_start();
    try {
        $service->confirm($booking, $method);
    } finally {
        $out = ob_get_clean();
    }

    return $out;
}

final class SpyListener implements BookingConfirmedListener
{
    public array $statuses = [];

    public function __construct(private string $name, private array &$log)
    {
    }

    public function onBookingConfirmed(Booking $booking, float $total): void
    {
        $this->statuses[] = $booking->status;
        $this->log[] = $this->name;
    }
}

final class FailingListener implements BookingConfirmedListener
{
    public function onBookingConfirmed(Booking $booking, float $total): void
    {
        throw new RuntimeException('boom');
    }
}

$out = run(new BookingService(), makeBooking('0612345678'));
check('Email envoyé', str_contains($out, 'EMAIL lea@example.com: booking 1001 confirmed'));
check('Points de fidélité ajoutés (1 point / euro)', str_contains($out, 'LOYALTY customer=1 points=100'));
check('Réservation transmise aux statistiques', str_contains($out, 'ANALYTICS booking_confirmed'));
check('SMS envoyé si numéro présent', str_contains($out, 'SMS 0612345678:'));

$out = run(new BookingService(), makeBooking(null));
check('Pas de SMS sans numéro', !str_contains($out, 'SMS '));

$log = [];
$out = run(new BookingService([new FailingListener(), new SpyListener('B', $log)]), makeBooking());
check('Une réaction qui échoue n\'empêche pas les suivantes', $log === ['B']);
check('L\'erreur est signalée', str_contains($out, 'LISTENER ERROR FailingListener: boom'));

$log = [];
try {
    run(new BookingService([new SpyListener('A', $log)]), makeBooking(), 'paypal');
} catch (RuntimeException) {
}
check('Paiement refusé : aucune réaction déclenchée', $log === []);

echo $failures === 0 ? 'Tous les tests passent' . PHP_EOL : "{$failures} test(s) en échec" . PHP_EOL;
exit($failures === 0 ? 0 : 1);