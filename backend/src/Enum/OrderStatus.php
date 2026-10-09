<?php

declare(strict_types=1);

namespace App\Enum;

enum OrderStatus: string
{
    case New = 'new';
    case Accepted = 'accepted';
    case Delivering = 'delivering';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function isFinal(): bool
    {
        return $this === self::Delivered || $this === self::Cancelled;
    }
}
