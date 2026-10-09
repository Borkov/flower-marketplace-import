<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'delivery_district_tariff')]
class DeliveryDistrictTariff
{
    #[ORM\Id]
    #[ORM\Column(length: 32)]
    private string $districtCode;

    #[ORM\Column(name: 'base_price_rub', type: 'decimal', precision: 10, scale: 2)]
    private string $basePriceRub;

    #[ORM\Column(name: 'free_from_items_sum_rub', type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $freeFromItemsSumRub = null;

    public function getDistrictCode(): string
    {
        return $this->districtCode;
    }

    public function getBasePriceRub(): string
    {
        return $this->basePriceRub;
    }

    public function getFreeFromItemsSumRub(): ?string
    {
        return $this->freeFromItemsSumRub;
    }
}
