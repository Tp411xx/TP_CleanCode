<?php

declare(strict_types=1);

/**
 * Décorateur : ajoute la supervision (durée, montant, succès/échec)
 * autour de n'importe quel PaymentGateway, sans le modifier.
 * Les logs vont sur STDERR pour ne pas polluer la sortie standard.
 */
final class SupervisedPaymentGateway implements PaymentGateway
{
    public function __construct(
        private PaymentGateway $inner,
        private string $name
    ) {
    }

    public function pay(float $amount, string $reference): string
    {
        $start = microtime(true);
        $this->log(sprintf('PAYMENT_REQUESTED gateway=%s ref=%s amount=%.2f', $this->name, $reference, $amount));

        try {
            $transactionId = $this->inner->pay($amount, $reference);
            $this->log(sprintf('PAYMENT_SUCCESS gateway=%s ref=%s duration_ms=%.2f', $this->name, $reference, $this->elapsedMs($start)));

            return $transactionId;
        } catch (Throwable $e) {
            $this->log(sprintf('PAYMENT_FAILURE gateway=%s ref=%s duration_ms=%.2f error="%s"', $this->name, $reference, $this->elapsedMs($start), $e->getMessage()));

            throw $e;
        }
    }

    private function elapsedMs(float $start): float
    {
        return (microtime(true) - $start) * 1000;
    }

    private function log(string $message): void
    {
        file_put_contents('php://stderr', $message . PHP_EOL);
    }
}