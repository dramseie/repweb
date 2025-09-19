<?php
// src/Service/QuoteService.php
namespace App\Service;

use App\Repository\QuoteRepository;

final class QuoteService
{
    public function __construct(private QuoteRepository $quotes) {}

    public function quote(array $req): array
    {
        return $this->quotes->quoteService(
            tenantCode:  $req['tenant_code'] ?? 'cmdb',
            serviceCi:   $req['service_code'],
            countryIso2: $req['country'],
            level:       $req['level'],
            usage:       (float)$req['usage'],
            years:       (int)$req['years'],
            billCcy:     $req['bill_ccy'] ?? null,
            asOf:        isset($req['as_of']) ? new \DateTimeImmutable($req['as_of']) : null,
            vatIncluded: (bool)($req['vat_included'] ?? false),
            customerRef: $req['customer_ref'] ?? null,
            who:         $req['who'] ?? null
        );
    }
}
