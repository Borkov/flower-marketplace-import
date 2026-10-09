<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\OrderStatus;

final class StatusMapper
{
    private const MAP = [
        'NEW' => OrderStatus::New,
        'ACCEPTED' => OrderStatus::Accepted,
        'IN_DELIVERY' => OrderStatus::Delivering,
        'DONE' => OrderStatus::Delivered,
        'CANCELED' => OrderStatus::Cancelled,
    ];

    public function map(string $marketplaceStatus): ?OrderStatus
    {
        return self::MAP[strtoupper(trim($marketplaceStatus))] ?? null;
    }
}
