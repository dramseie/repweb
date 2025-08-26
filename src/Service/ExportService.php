<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use OpenSpout\Writer\XLSX\Options as XlsxOptions;

class ExportService
{
    public function __construct(private Connection $db) {}

    /** Replace with your logic to fetch report SQL by ID */
    private function getReportSqlById(int $reportId): ?string
    {
        // Example: table "reports" with columns (id, name, repsql)
        $row = $this->db->fetchAssociative('SELECT repsql, name FROM reports WHERE id = ?', [$reportId]);
        if (!$row) return null;
        return $row['repsql'];
    }

    /** @return array{path:string, filename:string, mime:string} */
    public function generateForReportId(int $reportId, string $format = 'csv'): array
    {
        $sql = $this->getReportSqlById($reportId);
        if (!$sql) throw new \RuntimeException('Report not found');

        $rows = $this->db->fetchAllAssociative($sql);
        $basename = 'report_'.$reportId;
        $tmp = tempnam(sys_get_temp_dir(), 'rep_');

        switch (strtolower($format)) {
            case 'excel':
            case 'xlsx':
                @unlink($tmp);
                $tmp = $tmp . '.xlsx';
                $this->writeXlsx($tmp, $rows);
                return ['path' => $tmp, 'filename' => $basename.'.xlsx', 'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];

            case 'json':
                file_put_contents($tmp, json_encode($rows, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
                return ['path' => $tmp, 'filename' => $basename.'.json', 'mime' => 'application/json'];

            case 'csv':
            default:
                $csv = fopen($tmp, 'w');
                if (!empty($rows)) {
                    fputcsv($csv, array_keys($rows[0]));
                    foreach ($rows as $r) fputcsv($csv, array_values($r));
                }
                fclose($csv);
                return ['path' => $tmp, 'filename' => $basename.'.csv', 'mime' => 'text/csv'];
        }
    }

    private function writeXlsx(string $path, array $rows): void
    {
        $options = new XlsxOptions();
        $writer = new XlsxWriter($options);
        $writer->openToFile($path);
        if (!empty($rows)) {
            $writer->addRow(array_keys($rows[0]));
            foreach ($rows as $r) $writer->addRow(array_values($r));
        }
        $writer->close();
    }
}
