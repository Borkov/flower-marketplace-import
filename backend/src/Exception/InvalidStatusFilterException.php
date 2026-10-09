<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

final class InvalidStatusFilterException extends RuntimeException
{
    public function __construct(public readonly string $status)
    {
        parent::__construct('Unknown status filter: ' . $status);
    }
}
