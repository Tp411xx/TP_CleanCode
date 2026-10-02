<?php

declare(strict_types=1);

final class AnalyticsClient
{
    public function track(string $event, array $data): void
    {
        echo "ANALYTICS {$event} " . json_encode($data) . PHP_EOL;
    }
}
