<?php
// src/Frw/Domain/Pricing/PricerInterface.php
namespace App\Frw\Domain\Pricing;

interface PricerInterface
{
    public function supports(string $type): bool;
    /** @return array{unit_price_cents:int,extended_cents:int,label:string,meta:array} */
    public function price(array $input): array;
}