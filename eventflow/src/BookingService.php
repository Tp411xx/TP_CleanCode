<?php

declare(strict_types=1);

final class BookingService
{
    private const VIP_CUSTOMER = 'vip';
    private const THREE_DAYS_PASS = '3days';
    private const VIP_DISCOUNT_MULTIPLIER = 0.90;
    private const THREE_DAYS_DISCOUNT = 10.0;

    public function confirm(Booking $booking, string $paymentMethod = 'stripe'): float
    {
        $this->validate($booking);

        $total = $this->calculateTotal($booking);

        $gateway = $this->createGateway($paymentMethod);

        $transactionId = $gateway->pay($total, 'booking-' . $booking->id);
        echo "PAYMENT {$transactionId}" . PHP_EOL;

        $booking->status = 'confirmed';

        echo "SQL INSERT booking={$booking->id} total={$total} status={$booking->status}" . PHP_EOL;

        $emailService = new EmailService();
        $emailService->sendConfirmation($booking->customer->email, $booking->id);

        return $total;
    }

    private function validate(Booking $booking): void
    {
        if (count($booking->items) === 0) {
            throw new RuntimeException('Empty booking');
        }

        if (!filter_var($booking->customer->email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Invalid email');
        }

        foreach ($booking->items as $item) {
            if ($item->quantity <= 0) {
                throw new RuntimeException('Invalid quantity');
            }
        }
    }

    private function calculateTotal(Booking $booking): float
    {
        $total = 0.0;

        foreach ($booking->items as $item) {
            $total += $item->ticket->price * $item->quantity;
        }

        if ($booking->customer->type === self::VIP_CUSTOMER) {
            $total *= self::VIP_DISCOUNT_MULTIPLIER;
        }

        if ($booking->passType === self::THREE_DAYS_PASS) {
            $total -= self::THREE_DAYS_DISCOUNT;
        }

        return $total;
    }

    private function createGateway(string $paymentMethod): PaymentGateway
    {
        return match ($paymentMethod) {
            'stripe'  => new SupervisedPaymentGateway(new StripePaymentGateway(), 'stripe'),
            'payfast' => new SupervisedPaymentGateway(new PayFastPaymentGateway(), 'payfast'),
            default   => throw new RuntimeException('Unknown payment method'),
        };
    }
}