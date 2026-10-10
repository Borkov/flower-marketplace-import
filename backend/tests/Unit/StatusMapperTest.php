<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Enum\OrderStatus;
use App\Service\StatusMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StatusMapperTest extends TestCase
{
    #[DataProvider('known')]
    public function testMapsKnownStatuses(string $raw, OrderStatus $expected): void
    {
        self::assertSame($expected, (new StatusMapper())->map($raw));
    }

    /** @return iterable<string, array{string, OrderStatus}> */
    public static function known(): iterable
    {
        yield 'NEW' => ['NEW', OrderStatus::New];
        yield 'ACCEPTED' => ['ACCEPTED', OrderStatus::Accepted];
        yield 'IN_DELIVERY' => ['IN_DELIVERY', OrderStatus::Delivering];
        yield 'DONE' => ['DONE', OrderStatus::Delivered];
        yield 'CANCELED' => ['CANCELED', OrderStatus::Cancelled];
    }

    public function testUnknownStatus(): void
    {
        self::assertNull((new StatusMapper())->map('PACKING'));
    }
}
