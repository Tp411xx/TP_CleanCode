<?php

declare(strict_types=1);

final class Ticket
{
    public function __construct(
        public string $code,
        public string $label,
        public float $price
    ) {
    }
}
