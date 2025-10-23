<?php
// src/Frw/Domain/Pricing/Strategy/AgingPricer.php
namespace App\Frw\Domain\Pricing\Strategy;

use App\Frw\Domain\Pricing\PricerInterface;

final class AgingPricer implements PricerInterface
{
    public function supports(string $type): bool { return $type === 'aging'; }
    public function price(array $in): array
    {
        $base = (int)($in['ctx']['baseMonthly'] ?? $in['catalog']['base_price_cents'] ?? 0);
        $table = $in['args']['ageTable'] ?? [];
        // Compute weighted total for first N years (simple example)
        $months = [];
        foreach ($table as $row) { $months[] = 12 * ($row['factor'] ?? 1); }
        $sum = array_sum($months) * $base; // simplistic illustration
        return [
            'unit_price_cents' => $base,
            'extended_cents'   => (int)$sum,
            'label'            => $in['catalog']['name'] ?? 'Aging',
            'meta'             => ['table'=>$table]
        ];
    }
}