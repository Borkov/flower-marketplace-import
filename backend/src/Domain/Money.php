<?php

declare(strict_types=1);

namespace App\Domain;

final class Money
{
    private function __construct(public readonly int $kopecks)
    {
    }

    public static function fromRubles(int|float|string $rubles): self
    {
        if (!is_numeric($rubles)) {
            throw new \InvalidArgumentException('Amount is not numeric');
        }

        return new self((int) round(((float) $rubles) * 100));
    }

    public static function fromKopecks(int $kopecks): self
    {
        return new self($kopecks);
    }

    public function add(self $other): self
    {
        return new self($this->kopecks + $other->kopecks);
    }

    public function toDecimalString(): string
    {
        $sign = $this->kopecks < 0 ? '-' : '';
        $abs = abs($this->kopecks);
        $rubles = intdiv($abs, 100);
        $cents = $abs % 100;

        return sprintf('%s%d.%02d', $sign, $rubles, $cents);
    }
}
