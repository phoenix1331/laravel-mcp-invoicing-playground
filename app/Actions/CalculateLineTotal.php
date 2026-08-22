<?php

declare(strict_types=1);

namespace App\Actions;

class CalculateLineTotal
{
    public function __invoke(float $quantity, float $unitPrice): float
    {
        return round($quantity * $unitPrice, 2);
    }
}
