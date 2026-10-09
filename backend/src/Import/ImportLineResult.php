<?php

declare(strict_types=1);

namespace App\Import;

use App\Enum\ImportOutcome;

final class ImportLineResult
{
    public function __construct(
        public readonly string $marketplaceId,
        public readonly ImportOutcome $outcome,
        public readonly string $reason,
        public readonly ?int $orderId,
    ) {
    }

    /** @return array{marketplace_id: string, outcome: string, reason: string, order_id: int|null} */
    public function toArray(): array
    {
        return [
            'marketplace_id' => $this->marketplaceId,
            'outcome' => $this->outcome->value,
            'reason' => $this->reason,
            'order_id' => $this->orderId,
        ];
    }
}
