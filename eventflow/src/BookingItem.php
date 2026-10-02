<?php

declare(strict_types=1);

final class BookingItem
{
    public function __construct(
        public Ticket $ticket,
        public int $quantity
    ) {
    }
}
