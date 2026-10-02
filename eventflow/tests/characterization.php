<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/TestRunner.php';

$tests = new TestRunner();

function createBooking(
    string $customerType = 'standard',
    string $passType = 'day',
    float $price = 50.0,
    int $quantity = 1,
    ?string $phone = '0600000000'
): Booking {
    $customer = new Customer(1, 'test@example.com', $phone, $customerType);
    $ticket = new Ticket('TEST', 'Ticket test', $price);
    $booking = new Booking(1, $customer, $passType);
    $booking->addItem(new BookingItem($ticket, $quantity));
    return $booking;
}

function errorMessageWhenConfirming(BookingService $service, Booking $booking, string $paymentMethod = 'stripe'): ?string
{
    ob_start();

    try {
        $service->confirm($booking, $paymentMethod);
        return null;
    } catch (RuntimeException $exception) {
        return $exception->getMessage();
    } finally {
        ob_end_clean();
    }
}

ob_start();
$service = new BookingService();

$standard = createBooking('standard', 'day', 50.0, 2);
$standardTotal = $service->confirm($standard, 'stripe');
$tests->near(100.0, $standardTotal, 'standard customer keeps initial total');
$tests->same('confirmed', $standard->status, 'booking becomes confirmed');

$vip = createBooking('vip', 'day', 50.0, 2);
$vipTotal = $service->confirm($vip, 'stripe');
$tests->near(90.0, $vipTotal, 'legacy VIP rule gives 10 percent discount');

$threeDays = createBooking('standard', '3days', 60.0, 2);
$threeDaysTotal = $service->confirm($threeDays, 'stripe');
$tests->near(110.0, $threeDaysTotal, 'legacy three day pass discount is 10 euros');

ob_end_clean();

ob_start();
$vipWithThreeDaysPass = createBooking('vip', '3days', 100.0, 1);
$vipWithThreeDaysPassTotal = $service->confirm($vipWithThreeDaysPass, 'stripe');
ob_end_clean();
$tests->near(80.0, $vipWithThreeDaysPassTotal, 'vip percentage is applied before the 3days fixed discount');

$bookingWithoutItems = new Booking(1, new Customer(1, 'test@example.com'), 'day');
$tests->same('Empty booking', errorMessageWhenConfirming($service, $bookingWithoutItems), 'empty booking is rejected');

$bookingWithInvalidEmail = createBooking();
$bookingWithInvalidEmail->customer->email = 'not-an-email';
$tests->same('Invalid email', errorMessageWhenConfirming($service, $bookingWithInvalidEmail), 'invalid email is rejected');

$tests->same(null, errorMessageWhenConfirming($service, createBooking(), 'payfast'), 'payfast payment is accepted');
$bookingWithNegativeTotal = createBooking('standard', '3days', 8.0, 1);
$tests->same('Invalid amount', errorMessageWhenConfirming($service, $bookingWithNegativeTotal), 'total <= 0 is rejected by stripe');

$tests->summary();