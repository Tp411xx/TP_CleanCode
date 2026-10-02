<?php

declare(strict_types=1);

interface PaymentGateway
{
    public function pay(float $amount, string $reference): string;
}

final class StripePaymentGateway implements PaymentGateway
{
    public function pay(float $amount, string $reference): string
    {
        return (new StripeClient())->charge($amount);
    }
}

final class PayFastPaymentGateway implements PaymentGateway
{
    public function pay(float $amount, string $reference): string
    {
        $result = (new PayFastSdk())->executePayment([
            'reference'    => $reference,
            'amount_cents' => (int) round($amount * 100),
            'currency'     => 'EUR',
        ]);

        if (!$result['success']) {
            throw new RuntimeException('PayFast payment failed');
        }

        return $result['transaction_id'];
    }
}