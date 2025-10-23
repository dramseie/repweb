<?php
// src/Frw/Domain/Pricing/Strategy/SwitchPricer.php
namespace App\Frw\Domain\Pricing\Strategy;

use App\Frw\Domain\Pricing\PricerInterface;

final class SwitchPricer implements PricerInterface
{
    public function supports(string $type): bool { return $type === 'switch'; }
    public function price(array $in): array
    {
        $on = $in['catalog']['formula_json']['on'] ?? null; // e.g. 'args.os'
        $cases = $in['catalog']['formula_json']['cases'] ?? [];
        $key = $in['args']['os'] ?? 'default';
        $per = (int)($cases[$key]['cents'] ?? ($cases['default']['cents'] ?? 0));
        $label = $in['catalog']['name'] ?? 'Switch';
        $qty = (float)($in['qty'] ?? 1);
        return [
            'unit_price_cents' => $per,
            'extended_cents'   => (int)round($per * $qty),
            'label'            => $label,
            'meta'             => ['key'=>$key, 'cases'=>$cases]
        ];
    }
}