<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\Money;
use App\Enum\DeliveryType;
use App\Service\DeliveryCalculator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class DeliveryCalculatorTest extends TestCase
{
    private DeliveryCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new DeliveryCalculator([
            'centre' => ['base' => Money::fromRubles(300), 'freeFrom' => Money::fromRubles(5000)],
            'north' => ['base' => Money::fromRubles(400), 'freeFrom' => Money::fromRubles(7000)],
            'south' => ['base' => Money::fromRubles(400), 'freeFrom' => Money::fromRubles(7000)],
            'suburb' => ['base' => Money::fromRubles(700), 'freeFrom' => null],
        ]);
    }

    public function testPickupIsFree(): void
    {
        $result = $this->calculator->calculate(
            DeliveryType::Pickup,
            null,
            Money::fromRubles(1500),
            new DateTimeImmutable('2026-10-09T12:30:00+03:00'),
            null,
            null,
        );

        self::assertSame('0.00', $result['fee']->toDecimalString());
        self::assertFalse($result['urgent']);
    }

    public function testCentreBelowThreshold(): void
    {
        $result = $this->calculator->calculate(
            DeliveryType::Delivery,
            'centre',
            Money::fromRubles(3200),
            new DateTimeImmutable('2026-10-08T09:00:00+03:00'),
            '2026-10-10',
            '14:00',
        );

        self::assertSame('300.00', $result['fee']->toDecimalString());
        self::assertFalse($result['urgent']);
    }

    public function testCentreFreeFromThreshold(): void
    {
        $result = $this->calculator->calculate(
            DeliveryType::Delivery,
            'centre',
            Money::fromRubles(6400),
            new DateTimeImmutable('2026-10-09T10:00:00+03:00'),
            '2026-10-11',
            '10:00',
        );

        self::assertSame('0.00', $result['fee']->toDecimalString());
    }

    public function testNorthUrgentAdds200OnTopOfBase(): void
    {
        $result = $this->calculator->calculate(
            DeliveryType::Delivery,
            'north',
            Money::fromRubles(5000),
            new DateTimeImmutable('2026-10-10T11:00:00+03:00'),
            '2026-10-10',
            '12:00',
        );

        self::assertTrue($result['urgent']);
        self::assertSame('600.00', $result['fee']->toDecimalString());
    }

    public function testSuburbHasNoFreeThreshold(): void
    {
        $result = $this->calculator->calculate(
            DeliveryType::Delivery,
            'suburb',
            Money::fromRubles(3600),
            new DateTimeImmutable('2026-10-09T16:00:00+03:00'),
            '2026-10-13',
            '10:00',
        );

        self::assertSame('700.00', $result['fee']->toDecimalString());
    }

    public function testUnknownDistrict(): void
    {
        $result = $this->calculator->calculate(
            DeliveryType::Delivery,
            'zarechye',
            Money::fromRubles(2900),
            new DateTimeImmutable('2026-10-09T13:00:00+03:00'),
            '2026-10-12',
            '11:00',
        );

        self::assertSame('unknown_district', $result['error']);
    }

    public function testExactlyTwoHoursIsNotUrgent(): void
    {
        $result = $this->calculator->calculate(
            DeliveryType::Delivery,
            'centre',
            Money::fromRubles(1000),
            new DateTimeImmutable('2026-10-10T10:00:00+03:00'),
            '2026-10-10',
            '12:00',
        );

        self::assertFalse($result['urgent']);
        self::assertSame('300.00', $result['fee']->toDecimalString());
    }
}
