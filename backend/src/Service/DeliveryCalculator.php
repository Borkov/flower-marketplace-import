<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Money;
use App\Enum\DeliveryType;
use DateTimeImmutable;
use DateTimeZone;

final class DeliveryCalculator
{
    public const URGENCY_FEE_RUBLES = 200;
    public const MOSCOW_TZ = 'Europe/Moscow';

    /**
     * @param array<string, array{base: Money, freeFrom: ?Money}> $tariffs
     */
    public function __construct(
        private readonly array $tariffs = [],
    ) {
    }

    /**
     * @return array{fee: Money, urgent: bool}|array{error: string}
     */
    public function calculate(
        DeliveryType $type,
        ?string $district,
        Money $itemsSum,
        DateTimeImmutable $createdAt,
        ?string $deliveryDate,
        ?string $timeFrom,
    ): array {
        if ($type === DeliveryType::Pickup) {
            return ['fee' => Money::fromKopecks(0), 'urgent' => false];
        }

        if ($district === null || $district === '' || !isset($this->tariffs[$district])) {
            return ['error' => 'unknown_district'];
        }

        $tariff = $this->tariffs[$district];
        $base = $tariff['base'];
        $freeFrom = $tariff['freeFrom'];
        if ($freeFrom !== null && $itemsSum->kopecks >= $freeFrom->kopecks) {
            $base = Money::fromKopecks(0);
        }

        $urgent = $this->isUrgent($createdAt, $deliveryDate, $timeFrom);
        $fee = $urgent ? $base->add(Money::fromRubles(self::URGENCY_FEE_RUBLES)) : $base;

        return ['fee' => $fee, 'urgent' => $urgent];
    }

    public function isUrgent(DateTimeImmutable $createdAt, ?string $deliveryDate, ?string $timeFrom): bool
    {
        if ($deliveryDate === null || $timeFrom === null) {
            return false;
        }

        $moscow = new DateTimeZone(self::MOSCOW_TZ);
        $slot = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i',
            $deliveryDate . ' ' . $timeFrom,
            $moscow,
        );
        if ($slot === false) {
            return false;
        }

        $deadline = $createdAt->setTimezone($moscow)->modify('+2 hours');

        return $slot < $deadline;
    }
}
