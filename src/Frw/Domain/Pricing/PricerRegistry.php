<?php
// src/Frw/Domain/Pricing/PricerRegistry.php
namespace App\Frw\Domain\Pricing;

final class PricerRegistry
{
    /** @param iterable<PricerInterface> $strategies */
    public function __construct(private iterable $strategies) {}

    public function get(string $type): PricerInterface
    {
        foreach ($this->strategies as $s) if ($s->supports($type)) return $s;
        throw new \RuntimeException("No pricer for type $type");
    }
}