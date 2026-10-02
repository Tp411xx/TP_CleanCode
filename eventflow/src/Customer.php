<?php

declare(strict_types=1);

final class Customer
{
    public function __construct(
        public int $id,
        public string $email,
        public ?string $phone = null,
        public string $type = 'standard'
    ) {
    }
}
