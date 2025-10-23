<?php
// src/Frw/Domain/Pricing/Strategy/TieredPricer.php
namespace App\Frw\Domain\Pricing\Strategy;

use App\Frw\Domain\Pricing\PricerInterface;

final class TieredPricer implements PricerInterface
{
    public function supports(string $type): bool { return $type === 'tiered'; }
    public function price(array $in): array
    {
        $qty = (float)($in['qty'] ?? 0);
        $tiers = $in['catalog']['formula_json']['tiers'] ?? [];
        $per = 0; foreach ($tiers as $t) { if ($t['upTo'] === null || $qty <= $t['upTo']) { $per = (int)$t['cents']; break; } }
        $label = $in['catalog']['name'] ?? 'Tiered';
        return [
            'unit_price_cents' => $per,
            'extended_cents'   => (int)round($per * $qty),
            'label'            => $label,
            'meta'             => ['qty'=>$qty, 'tiers'=>$tiers]
        ];
    }
}