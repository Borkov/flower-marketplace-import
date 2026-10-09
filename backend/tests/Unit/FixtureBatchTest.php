<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\Money;
use App\Enum\ImportOutcome;
use App\Enum\OrderStatus;
use App\Import\ImportDecisionMaker;
use App\Import\MarketplaceOrderParser;
use App\Service\DeliveryCalculator;
use App\Service\PhoneNormalizer;
use App\Service\StatusMapper;
use PHPUnit\Framework\TestCase;

final class FixtureBatchTest extends TestCase
{
    public function testFirstPassMatchesAssignmentRules(): void
    {
        $path = dirname(__DIR__) . '/fixtures/marketplace-orders.json';
        $fixture = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        $parser = new MarketplaceOrderParser(new PhoneNormalizer(), new StatusMapper());
        $calculator = new DeliveryCalculator([
            'centre' => ['base' => Money::fromRubles(300), 'freeFrom' => Money::fromRubles(5000)],
            'north' => ['base' => Money::fromRubles(400), 'freeFrom' => Money::fromRubles(7000)],
            'south' => ['base' => Money::fromRubles(400), 'freeFrom' => Money::fromRubles(7000)],
            'suburb' => ['base' => Money::fromRubles(700), 'freeFrom' => null],
        ]);
        $decisions = new ImportDecisionMaker();

        $existing = [];
        $seen = [];
        $lines = [];

        foreach ($fixture['orders'] as $raw) {
            $parsed = $parser->parse($raw);
            $id = $parsed->marketplaceId;
            $decision = $decisions->decide(
                isset($seen[$id]) && $id !== '',
                $existing[$id] ?? null,
                $parsed,
            );
            if ($id !== '') {
                $seen[$id] = true;
            }

            if ($decision->outcome === ImportOutcome::Created && $parsed->order !== null) {
                $delivery = $calculator->calculate(
                    $parsed->order->deliveryType,
                    $parsed->order->district,
                    $parsed->order->itemsSum,
                    $parsed->order->createdAt,
                    $parsed->order->deliveryDate,
                    $parsed->order->timeFrom,
                );
                if (isset($delivery['error'])) {
                    $lines[] = [
                        'id' => $id,
                        'outcome' => 'rejected',
                        'reason' => $delivery['error'],
                    ];
                    continue;
                }
                $existing[$id] = $parsed->order->status;
                $lines[] = [
                    'id' => $id,
                    'outcome' => 'created',
                    'fee' => $delivery['fee']->toDecimalString(),
                    'needs_review' => $parsed->order->needsReview,
                    'grand' => $parsed->order->itemsSum->add($delivery['fee'])->toDecimalString(),
                ];
                continue;
            }

            $lines[] = [
                'id' => $id,
                'outcome' => $decision->outcome->value,
                'reason' => $decision->reason,
            ];
        }

        self::assertSame('created', $lines[0]['outcome']);
        self::assertSame('300.00', $lines[0]['fee']);
        self::assertSame('3500.00', $lines[0]['grand']);

        self::assertSame('created', $lines[1]['outcome']);
        self::assertSame('600.00', $lines[1]['fee']);

        self::assertSame('created', $lines[2]['outcome']);
        self::assertSame('0.00', $lines[2]['fee']);

        self::assertSame('created', $lines[3]['outcome']);
        self::assertSame('0.00', $lines[3]['fee']);

        self::assertSame('created', $lines[4]['outcome']);

        self::assertSame('rejected', $lines[5]['outcome']);
        self::assertSame('unknown_district', $lines[5]['reason']);

        self::assertSame('rejected', $lines[6]['outcome']);
        self::assertSame('invalid_phone', $lines[6]['reason']);

        self::assertSame('rejected', $lines[7]['outcome']);
        self::assertSame('unknown_status', $lines[7]['reason']);

        self::assertSame('created', $lines[8]['outcome']);
        self::assertTrue($lines[8]['needs_review']);
        self::assertSame('700.00', $lines[8]['fee']);
        self::assertSame('4300.00', $lines[8]['grand']);

        self::assertSame('duplicate', $lines[9]['outcome']);
        self::assertSame('MP-1001', $lines[9]['id']);

        self::assertSame('created', $lines[10]['outcome']);
        self::assertSame('300.00', $lines[10]['fee']);

        $created = array_values(array_filter($lines, static fn (array $l): bool => $l['outcome'] === 'created'));
        self::assertCount(7, $created);
        self::assertSame(OrderStatus::Delivered, $existing['MP-1005']);
        self::assertSame(OrderStatus::Cancelled, $existing['MP-1010']);
    }
}
