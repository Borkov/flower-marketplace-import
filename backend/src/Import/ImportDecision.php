<?php

declare(strict_types=1);

namespace App\Import;

use App\Enum\ImportOutcome;
use App\Enum\OrderStatus;

final class ImportDecision
{
    public function __construct(
        public readonly ImportOutcome $outcome,
        public readonly string $reason,
        public readonly ?OrderStatus $newStatus = null,
    ) {
    }
}
