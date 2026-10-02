<?php

declare(strict_types=1);

final class LoyaltyService
{
    public function addPoints(int $customerId, int $points): void
    {
        echo "LOYALTY customer={$customerId} points={$points}" . PHP_EOL;
    }
}
