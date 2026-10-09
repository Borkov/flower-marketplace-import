<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Enum\ImportOutcome;
use App\Enum\OrderStatus;
use App\Import\ImportDecisionMaker;
use App\Import\ParseResult;
use App\Import\ParsedMarketplaceOrder;
use App\Domain\Money;
use App\Enum\DeliveryType;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ImportDecisionMakerTest extends TestCase
{
    private ImportDecisionMaker $maker;

    protected function setUp(): void
    {
        $this->maker = new ImportDecisionMaker();
    }

    public function testDuplicateInBatchWins(): void
    {
        $decision = $this->maker->decide(true, null, ParseResult::reject('MP-1001', 'invalid_phone', 'x'));
        self::assertSame(ImportOutcome::Duplicate, $decision->outcome);
        self::assertSame('duplicate_in_batch', $decision->reason);
    }

    public function testRejectWhenParseFailed(): void
    {
        $decision = $this->maker->decide(false, null, ParseResult::reject('MP-1007', 'invalid_phone', 'bad'));
        self::assertSame(ImportOutcome::Rejected, $decision->outcome);
        self::assertSame('invalid_phone', $decision->reason);
    }

    public function testCreateWhenNew(): void
    {
        $decision = $this->maker->decide(false, null, ParseResult::ok($this->order(OrderStatus::New)));
        self::assertSame(ImportOutcome::Created, $decision->outcome);
    }

    public function testFinalStatusDoesNotRollBack(): void
    {
        $decision = $this->maker->decide(
            false,
            OrderStatus::Delivered,
            ParseResult::ok($this->order(OrderStatus::New)),
        );
        self::assertSame(ImportOutcome::Unchanged, $decision->outcome);
        self::assertSame('final_status_locked', $decision->reason);
    }

    public function testStatusUpdate(): void
    {
        $decision = $this->maker->decide(
            false,
            OrderStatus::New,
            ParseResult::ok($this->order(OrderStatus::Accepted)),
        );
        self::assertSame(ImportOutcome::Updated, $decision->outcome);
        self::assertSame(OrderStatus::Accepted, $decision->newStatus);
    }

    public function testUnchangedWhenSameStatus(): void
    {
        $decision = $this->maker->decide(
            false,
            OrderStatus::New,
            ParseResult::ok($this->order(OrderStatus::New)),
        );
        self::assertSame(ImportOutcome::Unchanged, $decision->outcome);
    }

    private function order(OrderStatus $status): ParsedMarketplaceOrder
    {
        return new ParsedMarketplaceOrder(
            'MP-1',
            $status,
            new DateTimeImmutable('2026-10-08T09:00:00+03:00'),
            'A',
            '+79001234567',
            DeliveryType::Pickup,
            null,
            null,
            null,
            null,
            null,
            [['sku' => 'x', 'name' => 'n', 'qty' => 1, 'price' => Money::fromRubles(100)]],
            Money::fromRubles(100),
            Money::fromRubles(100),
            false,
        );
    }
}
