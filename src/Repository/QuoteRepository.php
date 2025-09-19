<?php
// src/Repository/QuoteRepository.php
namespace App\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

final class QuoteRepository
{
    public function __construct(private Connection $db) {}

    /**
     * Wraps CALL repweb.sp_quote_service(...) and returns the decoded JSON.
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
            $stmt = $this->db->executeQuery($sql, $params);
            $row  = $stmt->fetchAssociative();          // { quote_json: '...' }
            // Drain any extra result sets from CALL to avoid "commands out of sync"
            while ($stmt->nextResult()) {}
            if (!$row || !isset($row['quote_json'])) {
                throw new \RuntimeException('Quote procedure returned no data.');
            }
            return json_decode((string)$row['quote_json'], true, 512, JSON_THROW_ON_ERROR);
        } catch (Exception $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'SQLSTATE[45000]')) {
                throw new \RuntimeException(preg_replace('/^.*SQLSTATE\[45000\]:\s*/', '', $msg), 0, $e);
            }
            throw $e;
        }
    }
}
