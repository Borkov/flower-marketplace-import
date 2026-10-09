<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Money;
use App\Entity\DeliveryDistrictTariff;
use Doctrine\ORM\EntityManagerInterface;

final class TariffCatalog
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function calculator(): DeliveryCalculator
    {
        $rows = $this->em->getRepository(DeliveryDistrictTariff::class)->findAll();
        $tariffs = [];
        foreach ($rows as $row) {
            $tariffs[$row->getDistrictCode()] = [
                'base' => Money::fromRubles($row->getBasePriceRub()),
                'freeFrom' => $row->getFreeFromItemsSumRub() === null
                    ? null
                    : Money::fromRubles($row->getFreeFromItemsSumRub()),
            ];
        }

        return new DeliveryCalculator($tariffs);
    }
}
