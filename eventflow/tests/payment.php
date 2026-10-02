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

function makeBooking(float $price, string $type = 'standard'): Booking
{
    $booking = new Booking(1001, new Customer(1, 'lea@example.com', null, $type), 'day');
    $booking->addItem(new BookingItem(new Ticket('DAY-1', 'Pass Jour', $price), 1));

    return $booking;
}

function throwsRuntime(callable $fn): bool
{
    try {
        $fn();
    } catch (RuntimeException) {
        return true;
    }

    return false;
}

check('Stripe : retourne l\'id de transaction', (new StripePaymentGateway())->pay(79.90, 'booking-1') === 'stripe_79.90');
check('Stripe : montant 0 refusé', throwsRuntime(fn() => (new StripePaymentGateway())->pay(0.0, 'booking-1')));

check('PayFast : retourne l\'id de transaction', (new PayFastPaymentGateway())->pay(79.90, 'booking-1') === 'payfast_booking-1');
check('PayFast : montant 0 refusé', throwsRuntime(fn() => (new PayFastPaymentGateway())->pay(0.0, 'booking-1')));

$service = new BookingService();

ob_start();
$total = $service->confirm(makeBooking(100.0), 'stripe');
$out = ob_get_clean();
check('Service + Stripe : total 100.00', $total === 100.0);
check('Service + Stripe : transaction stripe', str_contains($out, 'PAYMENT stripe_100.00'));

$booking = makeBooking(100.0);
ob_start();
$total = $service->confirm($booking, 'payfast');
$out = ob_get_clean();
check('Service + PayFast : total 100.00', $total === 100.0);
check('Service + PayFast : transaction payfast', str_contains($out, 'PAYMENT payfast_booking-1001'));
check('Service + PayFast : réservation confirmée', $booking->status === 'confirmed');

check('Moyen de paiement inconnu refusé', throwsRuntime(fn() => $service->confirm(makeBooking(100.0), 'paypal')));

echo $failures === 0 ? "Tous les tests passent" . PHP_EOL : "{$failures} test(s) en échec" . PHP_EOL;
exit($failures === 0 ? 0 : 1);