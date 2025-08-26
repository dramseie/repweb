<?php
// src/Controller/ExportController.php
namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Service\DataTablesQueryBuilder;

use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\CSV\Options as CsvOptions;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;

class ExportController extends AbstractController
{
    // If you’re using the YAML route, you can omit this attribute.
    // #[Route('/api/dt/{reportId}/export.{format}', name: 'dt_export', requirements: ['format' => 'xlsx|csv'], methods: ['POST'])]
    public function export(
        string $reportId,
        string $format,
        Request $request,
        DataTablesQueryBuilder $dtqb
    ): BinaryFileResponse {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $state = json_decode($request->getContent(), true) ?? [];
        $query = $dtqb->buildQuery($reportId, $state, $forExport = true);
        $cols  = $dtqb->getExportColumns($state);

        $now = (new \DateTime())->format('Ymd_His');
        $filename = sprintf('report-%s-%s.%s', $reportId, $now, $format);

        // write to a temp file to avoid stream corruption
        $tmp = tempnam(sys_get_temp_dir(), 'rep_export_');
        $tmpWithExt = $tmp . '.' . $format;
        @rename($tmp, $tmpWithExt);

        $ensureColumns = function(array $cols, array $firstRow): array {
            if (!empty($cols)) return $cols;
            if (empty($firstRow)) return [];
            return array_map(fn($k) => ['key' => $k, 'title' => $k], array_keys($firstRow));
        };

        if ($format === 'xlsx') {
            $writer = new XlsxWriter();
            $writer->openToFile($tmpWithExt);

            try {
                $prime = $dtqb->fetchChunk($query, 1, 0);
                $colsLocal = $ensureColumns($cols, $prime[0] ?? []);

                $headerStyle = (new Style())->setFontBold();
                $writer->addRow(Row::fromValues(array_column($colsLocal, 'title'), $headerStyle));

                $offset = 0;
                if (!empty($prime)) {
                    $writer->addRow(Row::fromValues(array_map(fn($c) => $prime[0][$c['key']] ?? '', $colsLocal)));
                    $offset = 1;
                }

                $chunk = 5000;
                while ($rows = $dtqb->fetchChunk($query, $chunk, $offset)) {
                    foreach ($rows as $r) {
                        $writer->addRow(Row::fromValues(array_map(fn($c) => $r[$c['key']] ?? '', $colsLocal)));
                    }
                    $offset += $chunk;
                }
            } finally {
                $writer->close();
            }

            $mime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        } else { // csv
            $delimiter = $request->query->get('delimiter', ';');

            $opt = new CsvOptions();
            $opt->FIELD_DELIMITER = $delimiter;
            $opt->FIELD_ENCLOSURE = '"';
            $opt->ADD_BOM = true;
            $opt->END_OF_LINE = "\r\n";

            $writer = new CsvWriter($opt);
            $writer->openToFile($tmpWithExt);

            try {
                $prime = $dtqb->fetchChunk($query, 1, 0);
                $colsLocal = $ensureColumns($cols, $prime[0] ?? []);

                $headerStyle = (new Style())->setFontBold();
                $writer->addRow(Row::fromValues(array_column($colsLocal, 'title'), $headerStyle));

                $offset = 0;
                if (!empty($prime)) {
                    $writer->addRow(Row::fromValues(array_map(fn($c) => $prime[0][$c['key']] ?? '', $colsLocal)));
                    $offset = 1;
                }

                $chunk = 10000;
                while ($rows = $dtqb->fetchChunk($query, $chunk, $offset)) {
                    foreach ($rows as $r) {
                        $writer->addRow(Row::fromValues(array_map(fn($c) => $r[$c['key']] ?? '', $colsLocal)));
                    }
                    $offset += $chunk;
                }
            } finally {
                $writer->close();
            }

            $mime = 'text/csv; charset=UTF-8';
        }

        $response = new BinaryFileResponse($tmpWithExt);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);
        $response->headers->set('Content-Type', $mime);
        $response->headers->set('Cache-Control', 'no-store');
        $response->deleteFileAfterSend(true);

        return $response;
    }
}
