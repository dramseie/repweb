<?php
// src/Repository/RepwebQuoteRepository.php
namespace App\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

final class RepwebQuoteRepository
{
    public function __construct(private Connection $repweb) {}

    /**
     * Calls repweb.sp_quote_service and returns the decoded quote as array.
     *
     * @return array{service:array,inputs:array,calc:array,totals:array}
     */
    public function quoteService(
        string $tenantCode,
        string $serviceCi,
        string $countryIso2,
        string $level,
        float  $usage,
        int    $years,
        ?string $billCcy = null,
        ?\DateTimeInterface $asOf = null,
        bool   $vatIncluded = false,
        ?string $customerRef = null,
        ?string $who = null
    ): array {
        $sql = 'CALL repweb.sp_quote_service(?,?,?,?,?,?,?,?,?,?,?)';
        $params = [
            $tenantCode,
            $serviceCi,
            strtoupper($countryIso2),
            $level,
            $usage,
            $years,
            $billCcy,
            $asOf?->format('Y-m-d'),
            $vatIncluded ? 1 : 0,
            $customerRef,
            $who,
        ];

        try {
            $result = $this->repweb->executeQuery($sql, $params);
            $row = $result->fetchAssociative();   // first (and only) result set: { quote_json: '<json>' }
            if (!$row || !isset($row['quote_json'])) {
                throw new \RuntimeException('Quote procedure returned no data.');
            }
            /** @var array $decoded */
            $decoded = json_decode((string)$row['quote_json'], true, 512, JSON_THROW_ON_ERROR);
            return $decoded;
        } catch (Exception $e) {
            // Bubble up SQLSTATE 45000 messages (SIGNAL) as a nice RuntimeException
            $msg = $e->getMessage();
            if (str_contains($msg, 'SQLSTATE[45000]')) {
                throw new \RuntimeException(preg_replace('/^.*SQLSTATE\[45000\]: /', '', $msg), 0, $e);
            }
            throw $e;
        }
    }
}
