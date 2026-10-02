<?php

declare(strict_types=1);

final class BookingService
{
    public function confirm(Booking $booking, string $paymentMethod = 'stripe'): float
    {
        if (count($booking->items) === 0) {
            throw new RuntimeException('Empty booking');
        }

        if (!filter_var($booking->customer->email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Invalid email');
        }

        $total = 0.0;

        foreach ($booking->items as $item) {
            if ($item->quantity <= 0) {
                throw new RuntimeException('Invalid quantity');
            }

            $total += $item->ticket->price * $item->quantity;
        }

        if ($booking->customer->type === 'vip') {
            $total *= 0.90;
        }

        if ($booking->passType === '3days') {
            $total -= 10.0;
        }

        $gateway = match ($paymentMethod) {
            'stripe'  => new SupervisedPaymentGateway(new StripePaymentGateway(), 'stripe'),
            'payfast' => new SupervisedPaymentGateway(new PayFastPaymentGateway(), 'payfast'),
            default   => throw new RuntimeException('Unknown payment method'),
        };

        $transactionId = $gateway->pay($total, 'booking-' . $booking->id);
        echo "PAYMENT {$transactionId}" . PHP_EOL;

        $booking->status = 'confirmed';

        echo "SQL INSERT booking={$booking->id} total={$total} status={$booking->status}" . PHP_EOL;

        $emailService = new EmailService();
        $emailService->sendConfirmation($booking->customer->email, $booking->id);

        return $total;
    }
}