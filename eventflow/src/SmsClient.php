<?php

declare(strict_types=1);

final class SmsClient
{
    public function send(string $phone, string $message): void
    {
        echo "SMS {$phone}: {$message}" . PHP_EOL;
    }
}
