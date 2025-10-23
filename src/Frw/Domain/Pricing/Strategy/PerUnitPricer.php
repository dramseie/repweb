<?php
// src/Frw/Domain/Pricing/Strategy/PerUnitPricer.php
namespace App\Frw\Domain\Pricing\Strategy;

use App\Frw\Domain\Pricing\PricerInterface;

final class PerUnitPricer implements PricerInterface
{
    public function supports(string $type): bool { return $type === 'per_unit'; }
    public function price(array $in): array
    {
        $u = (int)($in['catalog']['formula_json']['unit_price_cents'] ?? $in['catalog']['base_price_cents'] ?? 0);
        $qty = (float)($in['qty'] ?? 0);
        $label = $in['catalog']['name'] ?? 'Item';
        return [
            'unit_price_cents' => $u,
            'extended_cents'   => (int)round($u * $qty),
            'label'            => $label,
            'meta'             => ['qty'=>$qty]
        ];
    }
}