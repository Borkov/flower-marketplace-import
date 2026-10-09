<?php

declare(strict_types=1);

namespace App\Import;

use App\Domain\Money;
use App\Enum\DeliveryType;
use App\Enum\OrderStatus;
use DateTimeImmutable;

final class ParsedMarketplaceOrder
{
    /**
     * @param list<array{sku: string, name: string, qty: int, price: Money}> $items
     */
    public function __construct(
        public readonly string $marketplaceId,
        public readonly OrderStatus $status,
        public readonly DateTimeImmutable $createdAt,
        public readonly string $customerName,
        public readonly string $phone,
        public readonly DeliveryType $deliveryType,
        public readonly ?string $district,
        public readonly ?string $address,
        public readonly ?string $deliveryDate,
        public readonly ?string $timeFrom,
        public readonly ?string $timeTo,
        public readonly array $items,
        public readonly Money $marketplaceTotal,
        public readonly Money $itemsSum,
        public readonly bool $needsReview,
    ) {
    }
}
