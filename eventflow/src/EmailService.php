<?php

declare(strict_types=1);

final class EmailService
{
    public function sendConfirmation(string $email, int $bookingId): void
    {
        echo "EMAIL {$email}: booking {$bookingId} confirmed" . PHP_EOL;
    }
}
