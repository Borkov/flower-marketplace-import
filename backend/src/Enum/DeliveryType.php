<?php

declare(strict_types=1);

namespace App\Enum;

enum DeliveryType: string
{
    case Pickup = 'pickup';
    case Delivery = 'delivery';
}
