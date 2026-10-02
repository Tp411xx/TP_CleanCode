<?php

declare(strict_types=1);

/**
 * SDK externe fourni par PayFast.
 * CONSIGNE : ne pas modifier cette classe.
 */
final class PayFastSdk
{
    /**
     * @param array{reference:string,amount_cents:int,currency:string} $payload
     * @return array{success:bool,transaction_id:string}
     */
    public function executePayment(array $payload): array
    {
        if (($payload['amount_cents'] ?? 0) <= 0) {
            return ['success' => false, 'transaction_id' => ''];
        }

        return [
            'success' => true,
            'transaction_id' => 'payfast_' . $payload['reference'],
        ];
    }
}
