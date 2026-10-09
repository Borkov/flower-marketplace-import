<?php

declare(strict_types=1);

namespace App\Import;

final class ParseResult
{
    public function __construct(
        public readonly string $marketplaceId,
        public readonly ?ParsedMarketplaceOrder $order,
        public readonly ?string $rejectCode,
        public readonly ?string $reason,
    ) {
    }

    public static function ok(ParsedMarketplaceOrder $order): self
    {
        return new self($order->marketplaceId, $order, null, null);
    }

    public static function reject(string $marketplaceId, string $code, string $reason): self
    {
        return new self($marketplaceId, null, $code, $reason);
    }
}
