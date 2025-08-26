<?php
// src/Controller/ReportApiController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, StreamedResponse};
use Symfony\Component\Routing\Annotation\Route;
use App\Service\DataTablesQueryBuilder;

use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\CSV\Options as CsvOptions;
use OpenSpout\Common\Entity\Row;

class ReportApiController extends AbstractController
{
    private function authorize(Request $req): ?JsonResponse
    {
        if ($this->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            return null; // session ok
        }
        $provided = $req->query->get('api_key') ?: $req->headers->get('X-Api-Key');
        $expected = $_ENV['REPORT_API_KEY'] ?? null;
        if ($expected && hash_equals($expected, (string)$provided)) {
            return null; // fixed key ok
        }
        return new JsonResponse(['error' => 'Unauthorized'], 401);
    }

    #[Route(
        '/api/report/{reportId}.{format}',
        name: 'report_api_fetch',
        requirements: ['format' => 'json|csv'],
        methods: ['GET']
    )]
    public function fetch(
        string $reportId,
        string $format,
        Request $request,
        DataTablesQueryBuilder $dtqb
    ) {
        if ($resp = $this->authorize($request)) return $resp;

        // --- state, paging, query ---
        $state = [];
        if ($s = $request->query->get('state')) {
            $json = base64_decode(strtr($s, '-_', '+/'));
            $state = json_decode($json ?: '[]', true) ?: [];
        }
        $limit  = min(max((int)$request->query->get('limit', 100), 1), 1000);
        $offset = max((int)$request->query->get('offset', 0), 0);

        $query = $dtqb->buildQuery($reportId, $state, $forExport = true);
        $cols  = $dtqb->getExportColumns($state);

        if ($format === 'json') {
            $rows = $dtqb->fetchChunk($query, $limit, $offset);
            return new JsonResponse([
                'reportId' => (string)$reportId,
                'count'    => count($rows),
                'limit'    => $limit,
                'offset'   => $offset,
                'columns'  => array_column($cols, 'key'),
                'data'     => $rows,
            ]);
        }

        // CSV stream (OpenSpout v4)
        $delimiter = $request->query->get('delimiter', ';');
        $opt = new CsvOptions();
        $opt->FIELD_DELIMITER = $delimiter;
        $opt->FIELD_ENCLOSURE = '"';
        $opt->ADD_BOM = true;
        $opt->END_OF_LINE = "\r\n";

        $resp = new StreamedResponse(function () use ($dtqb, $query, $cols, $limit, $offset, $opt, $reportId) {
            $writer = new CsvWriter($opt);
            $writer->openToFile('php://output');
            try {
                $writer->addRow(Row::fromValues(array_column($cols, 'title')));
                $rows = $dtqb->fetchChunk($query, $limit, $offset);
                foreach ($rows as $r) {
                    $writer->addRow(Row::fromValues(array_map(fn($c) => $r[$c['key']] ?? '', $cols)));
                }
            } finally {
                $writer->close();
            }
        });
        $resp->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $resp->headers->set('Content-Disposition', 'inline; filename="report-'.$reportId.'.csv"');
        $resp->headers->set('Cache-Control', 'no-store');
        return $resp;
    }
}
