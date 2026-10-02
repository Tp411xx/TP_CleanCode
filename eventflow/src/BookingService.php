<?php

declare(strict_types=1);

final class BookingService
{
    private array $listeners;
    
    public function __construct(?array $listeners = null)
    {
        $this->listeners = $listeners ?? [
            new EmailConfirmationListener(),
            new LoyaltyPointsListener(),
            new AnalyticsListener(),
            new SmsConfirmationListener(),
        ];
    }

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
            'stripe'  => new StripePaymentGateway(),
            'payfast' => new PayFastPaymentGateway(),
            default   => throw new RuntimeException('Unknown payment method'),
        };

        $transactionId = $gateway->pay($total, 'booking-' . $booking->id);
        echo "PAYMENT {$transactionId}" . PHP_EOL;

        $booking->status = 'confirmed';

        echo "SQL INSERT booking={$booking->id} total={$total} status={$booking->status}" . PHP_EOL;

        foreach ($this->listeners as $listener) {
            try {
                $listener->onBookingConfirmed($booking, $total);
            } catch (Throwable $e) {
                echo 'LISTENER ERROR ' . $listener::class . ': ' . $e->getMessage() . PHP_EOL;
            }
        }

        return $total;
    }
}