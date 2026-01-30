<?php

namespace App\Controller\Api;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/smartsheet/presentation', name: 'api_smartsheet_presentation_')]
class SmartsheetPresentationController extends AbstractController
{
    private const MASTER_TABLE = 'nifi.smartsheet_master_data';
    private const DEFAULT_TASK_NAME = 'Assessment Execution';
    private const POST_DEPLOYMENT_TASK = 'Post-Deployment Survey and Correction Process';
    private const SIGN_OFF_TASK = 'Store Sign off Completed';
    private const STATUS_TABLE = 'smartsheet_status_log';
    private const CONTENT_TABLE = 'smartsheet_content';
    private const STATUS_CATEGORIES = [
        'assessment' => 'Planned Assessments',
        'installation' => 'Planned Installations',
        'post_deployment' => 'Post-Deployment & Sign-off',
    ];
    private const COUNTRY_CANDIDATES = [
        'country',
        'Country',
        'Country_Name',
        'Country Name',
        'CountryCode',
        'Country_Code',
        'Country/Region',
        'Country Region',
        'Region',
        'Region_Name',
        'Region Name',
        'Market',
        'Market_Name',
        'Market Name',
    ];
    private const SITE_ID_CANDIDATES = ['Site_ID', 'Site Id', 'SiteID', 'Site Id', 'Site'];
    private const SITE_NAME_CANDIDATES = ['Site_Name', 'Site Name', 'SiteName', 'Site'];
    private const START_DATE_CANDIDATES = ['Start_Date', 'Start Date', 'Start'];
    private const END_DATE_CANDIDATES = ['End_Date', 'End Date', 'End'];
    private const CONFIDENCE_CANDIDATES = ['Confidence', 'Confidence_Level', 'Confidence Level'];
    private const STATUS_CANDIDATES = ['Status', 'Task_Status', 'Task Status'];
    private const TASK_NAME_CANDIDATES = ['Task_Name', 'Task Name', 'task_name', 'task name'];
    private const COMMENT_CANDIDATES = ['Comment', 'Comments', 'Notes', 'Note'];
    private const RAG_CANDIDATES = ['Status (RAG)', 'Status_RAG', 'RAG', 'Rag', 'rag'];
    private const SHEET_NAME_CANDIDATES = ['sheet_name', 'Sheet_Name', 'Sheet Name', 'Sheet'];

    public function __construct(
        private readonly Connection $connection,
        private readonly RequestStack $requestStack
    ) {}

    #[Route('/planned-assessments', name: 'planned_assessments', methods: ['GET'])]
    public function plannedAssessments(): JsonResponse
    {
        return $this->json($this->plannedDataForTask(self::DEFAULT_TASK_NAME));
    }

    #[Route('/planned-installations', name: 'planned_installations', methods: ['GET'])]
    public function plannedInstallations(): JsonResponse
    {
        return $this->json($this->plannedDataForTask('Installation Execution'));
    }

    #[Route('/planned', name: 'planned', methods: ['GET'])]
    public function planned(): JsonResponse
    {
        $task = trim((string) $this->getRequestParameter('task', self::DEFAULT_TASK_NAME));
        if ($task === '') {
            $task = self::DEFAULT_TASK_NAME;
        }

        return $this->json($this->plannedDataForTask($task));
    }

    #[Route('/post-deployment-signoff', name: 'post_deployment_signoff', methods: ['GET'])]
    public function postDeploymentSignoff(): JsonResponse
    {
        return $this->json($this->postDeploymentData());
    }

    #[Route('/timeline', name: 'presentation_timeline', methods: ['GET'])]
    public function timeline(): JsonResponse
    {
        return $this->json($this->timelineData());
    }

    #[Route('/planned-week', name: 'planned_week', methods: ['GET'])]
    public function plannedWeek(RequestStack $requestStack): JsonResponse
    {
        $req = $this->requestStack->getCurrentRequest();
        $country = $req ? trim((string) $req->query->get('country', '')) : '';

        // Use direct date containment so ranges spanning year boundaries are matched correctly
        $sql = sprintf(
            "SELECT * FROM %s WHERE (Task_Name = :assessment OR LOWER(Task_Name) LIKE :installPattern) AND CURDATE() BETWEEN DATE(Start_Date) AND DATE(End_Date)",
            self::MASTER_TABLE
        );

        $params = [
            'assessment' => 'Assessment',
            'installPattern' => '%installation execution%',
        ];

        if ($country !== '') {
            $sql .= ' AND (country = :country OR Country = :country)';
            $params['country'] = $country;
        }

        $rows = $this->connection->fetchAllAssociative($sql, $params);

        return $this->json(['items' => $rows]);
    }

    #[Route('/status', name: 'status_index', methods: ['GET'])]
    public function statusIndex(): JsonResponse
    {
        $sites = $this->fetchStatusSites();
        $logs = $this->fetchStatusLogs();

        $logMap = $this->buildStatusLogMap($logs);

        $items = [];
        foreach ($sites as $siteKey => $site) {
            $categories = [];
            foreach (self::STATUS_CATEGORIES as $categoryId => $label) {
                $categoryLogs = $logMap[$siteKey][$categoryId] ?? ['ragConfidence' => null, 'logs' => []];
                $categories[$categoryId] = $categoryLogs;
            }

            $items[] = [
                'country' => $site['country'],
                'siteId' => $site['siteId'],
                'siteName' => $site['siteName'],
                'categories' => $categories,
            ];
        }

        usort($items, static function (array $a, array $b): int {
            $countryCompare = strcasecmp($a['country'], $b['country']);
            if ($countryCompare !== 0) {
                return $countryCompare;
            }
            return strcasecmp((string) ($a['siteName'] ?? ''), (string) ($b['siteName'] ?? ''));
        });

        return $this->json([
            'categories' => array_map(
                static fn (string $id, string $label): array => ['id' => $id, 'label' => $label],
                array_keys(self::STATUS_CATEGORIES),
                self::STATUS_CATEGORIES
            ),
            'items' => $items,
        ]);
    }

    #[Route('/status/log', name: 'status_log_add', methods: ['POST'])]
    public function addStatusLog(\Symfony\Component\HttpFoundation\Request $request): JsonResponse
    {
        $payload = json_decode((string) $request->getContent(), true) ?? [];
        $country = trim((string) ($payload['country'] ?? ''));
        $siteId = trim((string) ($payload['siteId'] ?? ''));
        $siteName = trim((string) ($payload['siteName'] ?? '')) ?: null;
        $category = trim((string) ($payload['category'] ?? ''));
        $ragConfidence = trim((string) ($payload['ragConfidence'] ?? '')) ?: null;
        $statusText = trim((string) ($payload['statusText'] ?? ''));

        if ($country === '' || $siteId === '' || $category === '') {
            return $this->json(['message' => 'country, siteId, and category are required.'], 400);
        }
        if (!array_key_exists($category, self::STATUS_CATEGORIES)) {
            return $this->json(['message' => 'Invalid category.'], 400);
        }
        if ($statusText === '') {
            return $this->json(['message' => 'statusText is required.'], 400);
        }

        $createdAt = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

        $this->connection->insert(self::STATUS_TABLE, [
            'country' => $country,
            'site_id' => $siteId,
            'site_name' => $siteName,
            'category' => $category,
            'rag_confidence' => $ragConfidence,
            'status_text' => $statusText,
            'created_at' => $createdAt,
        ]);

        return $this->json([
            'ok' => true,
            'createdAt' => $createdAt,
        ]);
    }

    #[Route('/status/save', name: 'status_save', methods: ['POST'])]
    public function saveStatus(\Symfony\Component\HttpFoundation\Request $request): JsonResponse
    {
        $payload = json_decode((string) $request->getContent(), true) ?? [];
        $country = trim((string) ($payload['country'] ?? ''));
        $siteId = trim((string) ($payload['siteId'] ?? ''));
        $siteName = trim((string) ($payload['siteName'] ?? '')) ?: null;
        $category = trim((string) ($payload['category'] ?? ''));
        $ragConfidence = trim((string) ($payload['ragConfidence'] ?? '')) ?: null;
        $statusText = trim((string) ($payload['statusText'] ?? ''));

        if ($country === '' || $siteId === '' || $category === '') {
            return $this->json(['message' => 'country, siteId, and category are required.'], 400);
        }
        if (!array_key_exists($category, self::STATUS_CATEGORIES)) {
            return $this->json(['message' => 'Invalid category.'], 400);
        }
        if ($statusText === '' && $ragConfidence === null) {
            return $this->json(['message' => 'statusText or ragConfidence is required.'], 400);
        }

        $createdAt = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

        $this->connection->insert(self::STATUS_TABLE, [
            'country' => $country,
            'site_id' => $siteId,
            'site_name' => $siteName,
            'category' => $category,
            'rag_confidence' => $ragConfidence,
            'status_text' => $statusText !== '' ? $statusText : '',
            'created_at' => $createdAt,
        ]);

        return $this->json([
            'ok' => true,
            'createdAt' => $createdAt,
        ]);
    }

    #[Route('/export', name: 'presentation_export', methods: ['POST'])]
    public function exportPresentation(\Symfony\Component\HttpFoundation\Request $request): BinaryFileResponse
    {
        $payload = json_decode((string) $request->getContent(), true) ?? [];
        $countries = $this->normalizeCountryFilter($payload['countries'] ?? null);

        $data = [
            'generatedAt' => (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
            'countries' => $countries,
            'plannedAssessments' => $this->plannedDataForTask(self::DEFAULT_TASK_NAME, $countries),
            'plannedInstallations' => $this->plannedDataForTask('Installation Execution', $countries),
            'postDeployment' => $this->postDeploymentData($countries),
            'issueLog' => $this->issueLogData($countries),
        ];

        $tmpJson = tempnam(sys_get_temp_dir(), 'rep_ppt_');
        $tmpPptx = $tmpJson . '.pptx';
        file_put_contents($tmpJson, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $script = dirname(__DIR__, 3) . '/scripts/presentation_export.py';
        $env = array_merge($_ENV, [
            'PPTX_SCRIPT' => $script,
            'PPTX_INPUT' => $tmpJson,
            'PPTX_OUTPUT' => $tmpPptx,
        ]);
        $process = new Process(['bash', dirname(__DIR__, 3) . '/scripts/presentation_export.sh'], null, $env);
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            @unlink($tmpJson);
            throw new \RuntimeException('PPTX export failed: ' . $process->getErrorOutput());
        }

        @unlink($tmpJson);

        $filename = sprintf('presentation-%s.pptx', (new DateTimeImmutable('now'))->format('Ymd_His'));
        $response = new BinaryFileResponse($tmpPptx);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.presentationml.presentation');
        $response->headers->set('Cache-Control', 'no-store');
        $response->deleteFileAfterSend(true);

        return $response;
    }

    #[Route('/issues', name: 'issue_log', methods: ['GET'])]
    public function issues(): JsonResponse
    {
        return $this->json($this->issueLogData());
    }

    #[Route('/overview', name: 'presentation_overview', methods: ['GET'])]
    public function overview(): JsonResponse
    {
        return $this->json($this->programmeOverviewData());
    }

    #[Route('/content', name: 'presentation_content_get', methods: ['GET'])]
    public function content(\Symfony\Component\HttpFoundation\Request $request): JsonResponse
    {
        $section = trim((string) $request->query->get('section', ''));
        if ($section === '') {
            return $this->json(['message' => 'section is required.'], 400);
        }

        $row = $this->connection->fetchAssociative(
            sprintf('SELECT content, created_at FROM %s WHERE section = :section ORDER BY created_at DESC LIMIT 1', self::CONTENT_TABLE),
            ['section' => $section]
        );

        return $this->json([
            'section' => $section,
            'content' => $row['content'] ?? '',
            'createdAt' => $row['created_at'] ?? null,
        ]);
    }

    #[Route('/content', name: 'presentation_content_save', methods: ['POST'])]
    public function saveContent(\Symfony\Component\HttpFoundation\Request $request): JsonResponse
    {
        $payload = json_decode((string) $request->getContent(), true) ?? [];
        $section = trim((string) ($payload['section'] ?? ''));
        $content = (string) ($payload['content'] ?? '');
        if ($section === '') {
            return $this->json(['message' => 'section is required.'], 400);
        }

        $createdAt = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $this->connection->insert(self::CONTENT_TABLE, [
            'section' => $section,
            'content' => $content,
            'created_at' => $createdAt,
        ]);

        return $this->json(['ok' => true, 'createdAt' => $createdAt]);
    }

    #[Route('/progress', name: 'presentation_progress', methods: ['GET'])]
    public function progress(\Symfony\Component\HttpFoundation\Request $request): JsonResponse
    {
        $countries = $this->normalizeCountryFilter($request->query->all('countries'));

        return $this->json($this->progressData($countries));
    }

    #[Route('/issues', name: 'issue_log_add', methods: ['POST'])]
    public function addIssue(\Symfony\Component\HttpFoundation\Request $request): JsonResponse
    {
        $payload = json_decode((string) $request->getContent(), true) ?? [];
        $country = trim((string) ($payload['country'] ?? ''));
        $storeName = trim((string) ($payload['storeName'] ?? '')) ?: null;
        $storeId = trim((string) ($payload['storeId'] ?? '')) ?: null;
        $description = trim((string) ($payload['description'] ?? '')) ?: null;
        $priority = trim((string) ($payload['priority'] ?? '')) ?: null;
        $responsibleParty = trim((string) ($payload['responsibleParty'] ?? '')) ?: null;
        $actionRequired = trim((string) ($payload['actionRequired'] ?? '')) ?: null;
        $resolveDate = trim((string) ($payload['resolveDate'] ?? '')) ?: null;

        if ($country === '') {
            return $this->json(['message' => 'country is required.'], 400);
        }

        $this->connection->insert('smartsheet_issue_log', [
            'country' => $country,
            'store_name' => $storeName,
            'store_id' => $storeId,
            'description' => $description,
            'priority' => $priority,
            'responsible_party' => $responsibleParty,
            'action_required' => $actionRequired,
            'resolve_date' => $resolveDate ?: null,
            'created_at' => (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
        ]);

        return $this->json(['ok' => true]);
    }

    #[Route('/issues/{id}', name: 'issue_log_update', methods: ['PUT'])]
    public function updateIssue(int $id, \Symfony\Component\HttpFoundation\Request $request): JsonResponse
    {
        $payload = json_decode((string) $request->getContent(), true) ?? [];
        $country = trim((string) ($payload['country'] ?? ''));
        $storeName = trim((string) ($payload['storeName'] ?? '')) ?: null;
        $storeId = trim((string) ($payload['storeId'] ?? '')) ?: null;
        $description = trim((string) ($payload['description'] ?? '')) ?: null;
        $priority = trim((string) ($payload['priority'] ?? '')) ?: null;
        $responsibleParty = trim((string) ($payload['responsibleParty'] ?? '')) ?: null;
        $actionRequired = trim((string) ($payload['actionRequired'] ?? '')) ?: null;
        $resolveDate = trim((string) ($payload['resolveDate'] ?? '')) ?: null;

        if ($country === '') {
            return $this->json(['message' => 'country is required.'], 400);
        }

        $this->connection->update('smartsheet_issue_log', [
            'country' => $country,
            'store_name' => $storeName,
            'store_id' => $storeId,
            'description' => $description,
            'priority' => $priority,
            'responsible_party' => $responsibleParty,
            'action_required' => $actionRequired,
            'resolve_date' => $resolveDate ?: null,
            'updated_at' => (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
        ], ['id' => $id]);

        return $this->json(['ok' => true]);
    }

    /**
     * @return array{meta: array<string, mixed>, items: array<int, array<string, mixed>>}
     */
    private function plannedDataForTask(string $taskName, ?array $countries = null): array
    {
        $today = new DateTimeImmutable('today');
        $currentStart = $today->modify('first day of this month');
        $currentEnd = $today->modify('last day of this month');
        $nextStart = $today->modify('first day of next month');
        $nextEnd = $today->modify('last day of next month');

        $statusMap = $this->latestStatusMap();
        $categoryId = $taskName === 'Installation Execution' ? 'installation' : 'assessment';

        $currentRows = $this->fetchRows($currentStart, $currentEnd, $taskName);
        $nextRows = $this->fetchRows($nextStart, $nextEnd, $taskName);

        $currentByCountry = $this->groupByCountry($currentRows, $statusMap, $categoryId);
        $nextByCountry = $this->groupByCountry($nextRows, $statusMap, $categoryId);

        $countryList = array_unique(array_merge(array_keys($currentByCountry), array_keys($nextByCountry)));
        $countryList = $this->applyCountryFilter($countryList, $countries);
        usort($countryList, static fn (string $a, string $b): int => strcasecmp($a, $b));

        $items = [];
        foreach ($countryList as $country) {
            $items[] = [
                'country' => $country,
                'current' => $currentByCountry[$country] ?? [],
                'next' => $nextByCountry[$country] ?? [],
            ];
        }

        return [
            'meta' => [
                'taskName' => $taskName,
                'currentMonth' => $currentStart->format('F Y'),
                'nextMonth' => $nextStart->format('F Y'),
                'currentStart' => $currentStart->format('Y-m-d'),
                'currentEnd' => $currentEnd->format('Y-m-d'),
                'nextStart' => $nextStart->format('Y-m-d'),
                'nextEnd' => $nextEnd->format('Y-m-d'),
            ],
            'items' => $items,
        ];
    }

    /**
     * @return array{meta: array<string, mixed>, items: array<int, array<string, mixed>>}
     */
    private function postDeploymentData(?array $countries = null): array
    {
        $today = new DateTimeImmutable('today');
        $currentStart = $today->modify('first day of this month');
        $currentEnd = $today->modify('last day of this month');
        $nextStart = $today->modify('first day of next month');
        $nextEnd = $today->modify('last day of next month');

        $statusMap = $this->latestStatusMap();

        $taskARows = $this->fetchRowsForTask(self::POST_DEPLOYMENT_TASK);
        $taskBRows = $this->fetchRowsByEndDateRange(self::SIGN_OFF_TASK, $currentStart, $nextEnd);

        $taskAIndex = $this->indexRowsByKey($taskARows);

        $currentRows = [];
        $nextRows = [];

        if ($taskBRows !== []) {
            $taskBColumns = $this->resolveColumns($taskBRows[0]);
            $taskAColumns = $taskARows !== [] ? $this->resolveColumns($taskARows[0]) : $taskBColumns;

            foreach ($taskBRows as $row) {
                $endDate = $this->parseDate($taskBColumns['endDate'] ? $row[$taskBColumns['endDate']] ?? null : null);
                if ($endDate === null) {
                    continue;
                }

                $bucket = null;
                if ($endDate >= $currentStart && $endDate <= $currentEnd) {
                    $bucket = 'current';
                } elseif ($endDate >= $nextStart && $endDate <= $nextEnd) {
                    $bucket = 'next';
                }

                if ($bucket === null) {
                    continue;
                }

                $key = $this->rowKey($row, $taskBColumns['sheetName'], $taskBColumns['siteId']);
                $taskARow = $key !== null ? ($taskAIndex[$key] ?? null) : null;

                $country = $this->resolveCountry($row, $taskBColumns['country'], $taskARow, $taskAColumns['country']);
                $siteId = $taskBColumns['siteId'] ? $row[$taskBColumns['siteId']] ?? null : null;
                $siteName = $taskBColumns['siteName'] ? $row[$taskBColumns['siteName']] ?? null : null;
                if ($siteName === null && $taskARow !== null && $taskAColumns['siteName']) {
                    $siteName = $taskARow[$taskAColumns['siteName']] ?? null;
                }
                $siteName = $this->normalizeSiteName($siteName);

                $startDateValue = null;
                if ($taskARow !== null && $taskAColumns['startDate']) {
                    $startDateValue = $taskARow[$taskAColumns['startDate']] ?? null;
                }

                $entry = [
                    'Country' => $country,
                    'Site_ID' => $siteId,
                    'Site_Name' => $siteName,
                    'Start_Date' => $this->formatDate($startDateValue),
                    'End_Date' => $this->formatDate($row[$taskBColumns['endDate']] ?? null),
                    'Confidence' => $taskBColumns['confidence'] ? $row[$taskBColumns['confidence']] ?? null : null,
                    'Status' => $taskBColumns['status'] ? $row[$taskBColumns['status']] ?? null : null,
                ];

                if ($bucket === 'current') {
                    $currentRows[] = $entry;
                } else {
                    $nextRows[] = $entry;
                }
            }
        }

        $currentByCountry = $this->groupByCountry($currentRows, $statusMap, 'post_deployment');
        $nextByCountry = $this->groupByCountry($nextRows, $statusMap, 'post_deployment');

        $countryList = array_unique(array_merge(array_keys($currentByCountry), array_keys($nextByCountry)));
        $countryList = $this->applyCountryFilter($countryList, $countries);
        usort($countryList, static fn (string $a, string $b): int => strcasecmp($a, $b));

        $items = [];
        foreach ($countryList as $country) {
            $items[] = [
                'country' => $country,
                'current' => $currentByCountry[$country] ?? [],
                'next' => $nextByCountry[$country] ?? [],
            ];
        }

        return [
            'meta' => [
                'taskName' => 'Post-Deployment & Sign-off',
                'currentMonth' => $currentStart->format('F Y'),
                'nextMonth' => $nextStart->format('F Y'),
                'currentStart' => $currentStart->format('Y-m-d'),
                'currentEnd' => $currentEnd->format('Y-m-d'),
                'nextStart' => $nextStart->format('Y-m-d'),
                'nextEnd' => $nextEnd->format('Y-m-d'),
            ],
            'items' => $items,
        ];
    }

    /**
     * @return array{items: array<int, array<string, mixed>>}
     */
    private function timelineData(?array $countries = null): array
    {
        $installRows = $this->fetchRowsForTask('Installation execution');
        $signoffRows = $this->fetchRowsForTask('Store Sign off Completed');

        $installColumns = $installRows !== [] ? $this->resolveColumns($installRows[0]) : [];
        $signoffColumns = $signoffRows !== [] ? $this->resolveColumns($signoffRows[0]) : [];

        $installStartByCountry = [];
        $installEndByCountry = [];
        foreach ($installRows as $row) {
            $countryColumn = $installColumns['country'] ?? null;
            $startColumn = $installColumns['startDate'] ?? null;
            $endColumn = $installColumns['endDate'] ?? null;

            $country = $countryColumn ? trim((string) ($row[$countryColumn] ?? '')) : '';
            $country = $country !== '' ? $country : 'Unspecified';

            $startDate = $this->parseDate($startColumn ? $row[$startColumn] ?? null : null);
            $endDate = $this->parseDate($endColumn ? $row[$endColumn] ?? null : null);

            if ($startDate !== null && (!isset($installStartByCountry[$country]) || $startDate < $installStartByCountry[$country])) {
                $installStartByCountry[$country] = $startDate;
            }
            if ($endDate !== null && (!isset($installEndByCountry[$country]) || $endDate > $installEndByCountry[$country])) {
                $installEndByCountry[$country] = $endDate;
            }
        }

        $signoffCompletedByCountry = [];
        foreach ($signoffRows as $row) {
            $countryColumn = $signoffColumns['country'] ?? null;
            $endColumn = $signoffColumns['endDate'] ?? null;

            $country = $countryColumn ? trim((string) ($row[$countryColumn] ?? '')) : '';
            $country = $country !== '' ? $country : 'Unspecified';

            $endDate = $this->parseDate($endColumn ? $row[$endColumn] ?? null : null);
            if ($endDate === null) {
                continue;
            }

            if (!isset($signoffCompletedByCountry[$country]) || $endDate > $signoffCompletedByCountry[$country]) {
                $signoffCompletedByCountry[$country] = $endDate;
            }
        }

        $countryList = array_unique(array_merge(
            array_keys($installStartByCountry),
            array_keys($installEndByCountry),
            array_keys($signoffCompletedByCountry)
        ));
        $countryList = $this->applyCountryFilter($countryList, $countries);

        $items = [];
        foreach ($countryList as $country) {
            $items[] = [
                'country' => $country,
                'startDate' => $this->formatDate($installStartByCountry[$country] ?? null),
                'installEndDate' => $this->formatDate($installEndByCountry[$country] ?? null),
                'endDate' => $this->formatDate($signoffCompletedByCountry[$country] ?? null),
            ];
        }

        usort($items, static function (array $a, array $b): int {
            $startA = $a['startDate'] ?? '';
            $startB = $b['startDate'] ?? '';
            if ($startA === $startB) {
                return strcasecmp((string) $a['country'], (string) $b['country']);
            }
            return strcmp((string) $startA, (string) $startB);
        });

        return ['items' => $items];
    }

    /**
     * @return array{items: array<int, array<string, mixed>>}
     */
    private function issueLogData(?array $countries = null): array
    {
        $sql = 'SELECT id, country, store_name, store_id, description, priority, responsible_party, action_required, resolve_date '
             . 'FROM smartsheet_issue_log ORDER BY country, resolve_date, store_name';
        $rows = $this->connection->fetchAllAssociative($sql);

        $grouped = [];
        foreach ($rows as $row) {
            $country = trim((string) ($row['country'] ?? '')) ?: 'Unspecified';
            $grouped[$country][] = [
                'id' => (int) ($row['id'] ?? 0),
                'country' => $country,
                'storeName' => $row['store_name'] ?? null,
                'storeId' => $row['store_id'] ?? null,
                'description' => $row['description'] ?? null,
                'priority' => $row['priority'] ?? null,
                'responsibleParty' => $row['responsible_party'] ?? null,
                'actionRequired' => $row['action_required'] ?? null,
                'resolveDate' => $row['resolve_date'] ? (new DateTimeImmutable($row['resolve_date']))->format('Y-m-d') : null,
            ];
        }

        $countryList = array_keys($grouped);
        $countryList = $this->applyCountryFilter($countryList, $countries);
        usort($countryList, static fn (string $a, string $b): int => strcasecmp($a, $b));

        $items = [];
        foreach ($countryList as $country) {
            $items[] = [
                'country' => $country,
                'issues' => $grouped[$country] ?? [],
            ];
        }

        return ['items' => $items];
    }

    /**
     * @param array<int, string> $countryList
     * @param array<int, string>|null $countries
     * @return array<int, string>
     */
    private function applyCountryFilter(array $countryList, ?array $countries): array
    {
        if ($countries === null || $countries === []) {
            return $countryList;
        }
        $set = array_flip(array_map(static fn (string $c): string => mb_strtolower(trim($c)), $countries));

        return array_values(array_filter($countryList, static function (string $country) use ($set): bool {
            return isset($set[mb_strtolower($country)]);
        }));
    }

    /**
     * @param mixed $value
     * @return array<int, string>|null
     */
    private function normalizeCountryFilter(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        $countries = array_values(array_filter(array_map(static function ($item): ?string {
            if (!is_string($item)) {
                return null;
            }
            $trimmed = trim($item);
            return $trimmed === '' ? null : $trimmed;
        }, $value)));

        return $countries === [] ? null : $countries;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchRows(DateTimeImmutable $start, DateTimeImmutable $end, string $taskName): array
    {
        $sql = sprintf(
            'SELECT * FROM %s WHERE Task_Name = :taskName AND Start_Date <= :endDate AND End_Date >= :startDate',
            self::MASTER_TABLE
        );

        return $this->connection->fetchAllAssociative($sql, [
            'taskName' => $taskName,
            'startDate' => $start->format('Y-m-d'),
            'endDate' => $end->format('Y-m-d'),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchRowsForTask(string $taskName): array
    {
        $sql = sprintf('SELECT * FROM %s WHERE Task_Name = :taskName', self::MASTER_TABLE);

        return $this->connection->fetchAllAssociative($sql, [
            'taskName' => $taskName,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchRowsForTaskPattern(string $pattern): array
    {
        $sql = sprintf('SELECT * FROM %s WHERE LOWER(Task_Name) LIKE :pattern', self::MASTER_TABLE);

        return $this->connection->fetchAllAssociative($sql, [
            'pattern' => '%' . mb_strtolower($pattern) . '%',
        ]);
    }

    /**
     * @param array<int, string> $taskNames
     * @return array<int, array<string, mixed>>
     */
    private function fetchRowsForTasks(array $taskNames): array
    {
        if ($taskNames === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($taskNames), '?'));

        return $this->connection->fetchAllAssociative(
            sprintf('SELECT * FROM %s WHERE Task_Name IN (%s)', self::MASTER_TABLE, $placeholders),
            $taskNames
        );
    }

    /**
     * @return array{items: array<int, array<string, mixed>>}
     */
    private function programmeOverviewData(): array
    {
        $columns = $this->resolveMasterColumns();
        $countryColumn = $columns['country'] ?? null;
        $siteIdColumn = $columns['siteId'] ?? null;
        $siteNameColumn = $columns['siteName'] ?? null;
        $taskNameColumn = $columns['taskName'] ?? null;
        $statusColumn = $columns['status'] ?? null;
        $commentColumn = $columns['comment'] ?? null;
        $ragColumn = $columns['rag'] ?? null;

        if ($countryColumn === null || $siteIdColumn === null || $taskNameColumn === null) {
            return ['items' => []];
        }

        $selectParts = [
            sprintf('`%s` AS country', $countryColumn),
            sprintf('`%s` AS site_id', $siteIdColumn),
            sprintf('`%s` AS task_name', $taskNameColumn),
        ];
        if ($siteNameColumn !== null) {
            $selectParts[] = sprintf('`%s` AS site_name', $siteNameColumn);
        }
        if ($statusColumn !== null) {
            $selectParts[] = sprintf('`%s` AS status', $statusColumn);
        }
        if ($commentColumn !== null) {
            $selectParts[] = sprintf('`%s` AS comment', $commentColumn);
        }
        if ($ragColumn !== null) {
            $selectParts[] = sprintf('`%s` AS rag', $ragColumn);
        }

        $sql = sprintf('SELECT %s FROM %s', implode(', ', $selectParts), self::MASTER_TABLE);
        $rows = $this->connection->fetchAllAssociative($sql);

        $countryData = [];
        foreach ($rows as $row) {
            $country = trim((string) ($row['country'] ?? '')) ?: 'Unspecified';
            $siteId = trim((string) ($row['site_id'] ?? ''));
            $siteName = trim((string) ($row['site_name'] ?? ''));
            $siteKey = $siteId !== '' ? $siteId : ($siteName !== '' ? $siteName : null);
            if ($siteKey === null) {
                continue;
            }

            $taskName = trim((string) ($row['task_name'] ?? ''));
            if ($taskName === '') {
                continue;
            }

            $statusClass = $this->classifyProgressStatus($row['status'] ?? null);
            $comment = trim((string) ($row['comment'] ?? ''));
            $rag = trim((string) ($row['rag'] ?? ''));

            $countryData[$country]['sites'][$siteKey] = true;

            if ($comment !== '' && empty($countryData[$country]['comment'])) {
                $countryData[$country]['comment'] = $comment;
            }

            if ($rag !== '') {
                $current = $countryData[$country]['rag'] ?? null;
                $countryData[$country]['rag'] = $this->pickWorstRag($current, $rag);
            }

            $taskNormalized = mb_strtolower($taskName);
            $assessmentTask = 'assessment completed';
            $installationTask = 'migration & installation completed';
            $signoffTask = 'store sign off completed';

            if ($taskNormalized === $assessmentTask && $statusClass === 'done') {
                $countryData[$country]['assessed'][$siteKey] = true;
            }

            if ($taskNormalized === $installationTask) {
                if ($statusClass === 'done') {
                    $countryData[$country]['storesInstalled'][$siteKey] = true;
                } elseif ($statusClass === 'in_progress') {
                    $countryData[$country]['ongoingInstallations'][$siteKey] = true;
                }
            }

            if ($taskNormalized === $signoffTask && $statusClass === 'done') {
                $countryData[$country]['storeSignoff'][$siteKey] = true;
            }
        }

        $items = [];
        foreach ($countryData as $country => $data) {
            $items[] = [
                'country' => $country,
                'stores' => count($data['sites'] ?? []),
                'assessed' => count($data['assessed'] ?? []),
                'ongoingInstallations' => count($data['ongoingInstallations'] ?? []),
                'storesInstalled' => count($data['storesInstalled'] ?? []),
                'defectsCompleted' => count($data['defectsCompleted'] ?? []),
                'storeSignoff' => count($data['storeSignoff'] ?? []),
                'comment' => $data['comment'] ?? null,
                'rag' => $data['rag'] ?? null,
            ];
        }

        usort($items, static fn (array $a, array $b): int => strcasecmp($a['country'], $b['country']));

        return ['items' => $items];
    }

    /**
     * @return array<string, string|null>
     */
    private function resolveMasterColumns(): array
    {
        $columns = $this->connection->fetchFirstColumn(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table',
            [
                'schema' => 'nifi',
                'table' => 'smartsheet_master_data',
            ]
        );

        return [
            'country' => $this->findColumnName($columns, self::COUNTRY_CANDIDATES),
            'siteId' => $this->findColumnName($columns, self::SITE_ID_CANDIDATES),
            'siteName' => $this->findColumnName($columns, self::SITE_NAME_CANDIDATES),
            'taskName' => $this->findColumnName($columns, self::TASK_NAME_CANDIDATES),
            'startDate' => $this->findColumnName($columns, self::START_DATE_CANDIDATES),
            'endDate' => $this->findColumnName($columns, self::END_DATE_CANDIDATES),
            'status' => $this->findColumnName($columns, self::STATUS_CANDIDATES),
            'comment' => $this->findColumnName($columns, self::COMMENT_CANDIDATES),
            'rag' => $this->findColumnName($columns, self::RAG_CANDIDATES),
        ];
    }

    /**
     * @return array<string, array{minStart?: mixed, maxEnd?: mixed}>
     */
    private function fetchTimelineAggregates(
        string $taskName,
        string $countryColumn,
        string $taskNameColumn,
        ?string $startColumn,
        ?string $endColumn
    ): array {
        $selectParts = [sprintf('`%s` AS country', $countryColumn)];
        if ($startColumn !== null) {
            $selectParts[] = sprintf('MIN(`%s`) AS min_start', $startColumn);
        }
        if ($endColumn !== null) {
            $selectParts[] = sprintf('MAX(`%s`) AS max_end', $endColumn);
        }

        $sql = sprintf(
            'SELECT %s FROM %s WHERE LOWER(`%s`) = :taskName GROUP BY `%s`',
            implode(', ', $selectParts),
            self::MASTER_TABLE,
            $taskNameColumn,
            $countryColumn
        );

        $rows = $this->connection->fetchAllAssociative($sql, [
            'taskName' => mb_strtolower($taskName),
        ]);

        $map = [];
        foreach ($rows as $row) {
            $country = trim((string) ($row['country'] ?? '')) ?: 'Unspecified';
            $map[$country] = [
                'minStart' => $row['min_start'] ?? null,
                'maxEnd' => $row['max_end'] ?? null,
            ];
        }

        return $map;
    }

    /**
     * @param array<int, string> $columns
     * @param array<int, string> $candidates
     */
    private function findColumnName(array $columns, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            foreach ($columns as $column) {
                if (strcasecmp($column, $candidate) === 0) {
                    return $column;
                }
            }
        }

        return null;
    }

    private function pickWorstRag(?string $current, ?string $incoming): ?string
    {
        $rank = [
            'red' => 3,
            'amber' => 2,
            'yellow' => 2,
            'green' => 1,
        ];
        $currentKey = $current ? mb_strtolower($current) : '';
        $incomingKey = $incoming ? mb_strtolower($incoming) : '';

        $currentRank = $rank[$currentKey] ?? 0;
        $incomingRank = $rank[$incomingKey] ?? 0;

        if ($incomingRank === 0 && $currentRank === 0) {
            return $current ?: $incoming;
        }

        return $incomingRank >= $currentRank ? $incoming : $current;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchRowsByEndDateRange(string $taskName, DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $sql = sprintf(
            'SELECT * FROM %s WHERE Task_Name = :taskName AND End_Date >= :startDate AND End_Date <= :endDate',
            self::MASTER_TABLE
        );

        return $this->connection->fetchAllAssociative($sql, [
            'taskName' => $taskName,
            'startDate' => $start->format('Y-m-d'),
            'endDate' => $end->format('Y-m-d'),
        ]);
    }

    /**
     * @return array{items: array<int, array<string, mixed>>}
     */
    private function progressData(?array $countries = null): array
    {
        $taskMap = [
            'assessment' => [
                'label' => 'Assessment',
                'taskName' => 'Assessment Completed',
            ],
            'installation' => [
                'label' => 'Installation',
                'taskName' => 'Migration & Installation Completed',
            ],
            'signoff' => [
                'label' => 'Store Signoff',
                'taskName' => self::SIGN_OFF_TASK,
            ],
        ];

        $taskNames = array_values(array_map(static fn (array $task): string => $task['taskName'], $taskMap));
        $taskByName = [];
        foreach ($taskMap as $key => $task) {
            $taskByName[mb_strtolower($task['taskName'])] = $key;
        }

        $rows = $this->fetchRowsForTasks($taskNames);
        if ($rows === []) {
            return ['items' => []];
        }

        $columns = $this->resolveColumns($rows[0]);
        $countryColumn = $columns['country'];
        $siteIdColumn = $columns['siteId'];
        $siteNameColumn = $columns['siteName'];
        $statusColumn = $columns['status'];
        $taskNameColumn = $this->findColumn($rows[0], self::TASK_NAME_CANDIDATES);

        $rank = [
            'not_started' => 1,
            'in_progress' => 2,
            'done' => 3,
        ];

        $siteStates = [];
        foreach ($rows as $index => $row) {
            $taskName = $taskNameColumn ? ($row[$taskNameColumn] ?? null) : null;
            $taskName = $taskName !== null ? trim((string) $taskName) : '';
            $taskKey = $taskName !== '' ? ($taskByName[mb_strtolower($taskName)] ?? null) : null;
            if ($taskKey === null) {
                continue;
            }
            $country = $countryColumn ? trim((string) ($row[$countryColumn] ?? '')) : '';
            if ($country === '') {
                $country = 'Unspecified';
            }
            $siteId = $siteIdColumn ? trim((string) ($row[$siteIdColumn] ?? '')) : '';
            $siteName = $siteNameColumn ? trim((string) ($row[$siteNameColumn] ?? '')) : '';
            $siteKey = $siteId !== '' ? $siteId : ($siteName !== '' ? $siteName : null);
            if ($siteKey === null) {
                continue;
            }

            $statusRaw = $statusColumn ? (string) ($row[$statusColumn] ?? '') : '';
            $statusClass = $this->classifyProgressStatus($statusRaw);

            $existing = $siteStates[$country][$taskKey][$siteKey] ?? null;
            if ($existing === null || $rank[$statusClass] > $rank[$existing]) {
                $siteStates[$country][$taskKey][$siteKey] = $statusClass;
            }
        }

        $countryList = array_keys($siteStates);
        $countryList = $this->applyCountryFilter($countryList, $countries);
        usort($countryList, static fn (string $a, string $b): int => strcasecmp($a, $b));

        $items = [];
        foreach ($countryList as $country) {
            $tasks = [];
            foreach ($taskMap as $taskKey => $task) {
                $sites = $siteStates[$country][$taskKey] ?? [];
                $total = count($sites);
                $counts = array_count_values($sites);
                $done = (int) ($counts['done'] ?? 0);
                $inProgress = (int) ($counts['in_progress'] ?? 0);
                $notStarted = (int) ($counts['not_started'] ?? 0);

                $donePct = $total > 0 ? (int) round($done * 100 / $total) : 0;
                $inProgressPct = $total > 0 ? (int) round($inProgress * 100 / $total) : 0;
                $notStartedPct = $total > 0 ? max(0, 100 - $donePct - $inProgressPct) : 0;

                $tasks[] = [
                    'key' => $taskKey,
                    'label' => $task['label'],
                    'total' => $total,
                    'done' => $done,
                    'inProgress' => $inProgress,
                    'notStarted' => $notStarted,
                    'donePct' => $donePct,
                    'inProgressPct' => $inProgressPct,
                    'notStartedPct' => $notStartedPct,
                ];
            }

            $items[] = [
                'country' => $country,
                'tasks' => $tasks,
            ];
        }

        return ['items' => $items];
    }

    private function classifyProgressStatus(?string $status): string
    {
        $text = mb_strtolower(trim((string) $status));
        if ($text === '') {
            return 'not_started';
        }
        if (
            str_contains($text, 'not started')
            || str_contains($text, 'not_started')
            || str_contains($text, 'todo')
            || str_contains($text, 'pending')
        ) {
            return 'not_started';
        }
        if (
            str_contains($text, 'done')
            || str_contains($text, 'complete')
            || str_contains($text, 'completed')
            || str_contains($text, 'sign off')
            || str_contains($text, 'signed off')
            || str_contains($text, 'closed')
        ) {
            return 'done';
        }

        return 'in_progress';
    }

    /**
     * @return array<string, array{country: string, siteId: string, siteName: ?string}>
     */
    private function fetchStatusSites(): array
    {
        $taskNames = [
            self::DEFAULT_TASK_NAME,
            'Installation Execution',
            self::POST_DEPLOYMENT_TASK,
            self::SIGN_OFF_TASK,
        ];

        $placeholders = implode(',', array_fill(0, count($taskNames), '?'));
        $rows = $this->connection->fetchAllAssociative(
            sprintf('SELECT * FROM %s WHERE Task_Name IN (%s)', self::MASTER_TABLE, $placeholders),
            $taskNames
        );

        $sites = [];
        if ($rows !== []) {
            $columns = $this->resolveColumns($rows[0]);
            foreach ($rows as $row) {
                $country = $columns['country'] ? trim((string) ($row[$columns['country']] ?? '')) : '';
                $siteId = $columns['siteId'] ? trim((string) ($row[$columns['siteId']] ?? '')) : '';
                $siteName = $columns['siteName'] ? trim((string) ($row[$columns['siteName']] ?? '')) : '';
                if ($country === '' || $siteId === '') {
                    continue;
                }
                $key = $this->statusKey($country, $siteId);
                $sites[$key] = [
                    'country' => $country,
                    'siteId' => $siteId,
                    'siteName' => $siteName !== '' ? $siteName : null,
                ];
            }
        }

        $logs = $this->fetchStatusLogs();
        foreach ($logs as $log) {
            $country = trim((string) ($log['country'] ?? ''));
            $siteId = trim((string) ($log['site_id'] ?? ''));
            if ($country === '' || $siteId === '') {
                continue;
            }
            $key = $this->statusKey($country, $siteId);
            if (!isset($sites[$key])) {
                $sites[$key] = [
                    'country' => $country,
                    'siteId' => $siteId,
                    'siteName' => $log['site_name'] ?? null,
                ];
            }
        }

        return $sites;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchStatusLogs(): array
    {
        return $this->connection->fetchAllAssociative(
            sprintf('SELECT * FROM %s ORDER BY created_at DESC', self::STATUS_TABLE)
        );
    }

    /**
     * @param array<int, array<string, mixed>> $logs
     * @return array<string, array<string, array{ragConfidence: ?string, logs: array<int, array<string, mixed>>}>>
     */
    private function buildStatusLogMap(array $logs): array
    {
        $map = [];
        foreach ($logs as $log) {
            $country = trim((string) ($log['country'] ?? ''));
            $siteId = trim((string) ($log['site_id'] ?? ''));
            $category = trim((string) ($log['category'] ?? ''));
            if ($country === '' || $siteId === '' || $category === '') {
                continue;
            }

            $key = $this->statusKey($country, $siteId);
            if (!isset($map[$key][$category])) {
                $map[$key][$category] = [
                    'ragConfidence' => null,
                    'logs' => [],
                ];
            }

            if ($map[$key][$category]['ragConfidence'] === null && !empty($log['rag_confidence'])) {
                $map[$key][$category]['ragConfidence'] = $log['rag_confidence'];
            }

            $map[$key][$category]['logs'][] = [
                'id' => $log['id'] ?? null,
                'createdAt' => $this->formatDateTime($log['created_at'] ?? null),
                'statusText' => $log['status_text'] ?? null,
                'ragConfidence' => $log['rag_confidence'] ?? null,
            ];
        }

        return $map;
    }

    /**
     * @return array<string, array<string, array{ragConfidence: ?string, statusText: ?string}>>
     */
    private function latestStatusMap(): array
    {
        $sql = sprintf(
            'SELECT l.* FROM %1$s l '
            . 'INNER JOIN (SELECT country, site_id, category, MAX(created_at) AS max_created FROM %1$s GROUP BY country, site_id, category) m '
            . 'ON l.country = m.country AND l.site_id = m.site_id AND l.category = m.category AND l.created_at = m.max_created',
            self::STATUS_TABLE
        );

        $rows = $this->connection->fetchAllAssociative($sql);
        $map = [];
        foreach ($rows as $row) {
            $category = trim((string) ($row['category'] ?? ''));
            $country = trim((string) ($row['country'] ?? ''));
            $siteId = trim((string) ($row['site_id'] ?? ''));
            if ($category === '' || $country === '' || $siteId === '') {
                continue;
            }
            $key = $this->statusKey($country, $siteId);
            $map[$category][$key] = [
                'ragConfidence' => $row['rag_confidence'] ?? null,
                'statusText' => $row['status_text'] ?? null,
            ];
        }

        return $map;
    }

    private function statusKey(string $country, string $siteId): string
    {
        return sprintf('%s|%s', $country, $siteId);
    }

    private function getRequestParameter(string $name, mixed $default = null): mixed
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return $default;
        }

        return $request->query->get($name, $default);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function groupByCountry(array $rows, array $statusMap = [], ?string $categoryId = null): array
    {
        if ($rows === []) {
            return [];
        }

        $countryColumn = $this->findColumn($rows[0], self::COUNTRY_CANDIDATES);
        $siteIdColumn = $this->findColumn($rows[0], self::SITE_ID_CANDIDATES);
        $siteNameColumn = $this->findColumn($rows[0], self::SITE_NAME_CANDIDATES);
        $startDateColumn = $this->findColumn($rows[0], self::START_DATE_CANDIDATES);
        $endDateColumn = $this->findColumn($rows[0], self::END_DATE_CANDIDATES);
        $confidenceColumn = $this->findColumn($rows[0], self::CONFIDENCE_CANDIDATES);
        $statusColumn = $this->findColumn($rows[0], self::STATUS_CANDIDATES);

        $grouped = [];
        foreach ($rows as $row) {
            $country = $countryColumn ? trim((string) ($row[$countryColumn] ?? '')) : '';
            if ($country === '') {
                $country = 'Unspecified';
            }

            $siteId = $siteIdColumn ? trim((string) ($row[$siteIdColumn] ?? '')) : '';
            $override = null;
            if ($categoryId && $siteId !== '') {
                $key = $this->statusKey($country, $siteId);
                $override = $statusMap[$categoryId][$key] ?? null;
            }

            $grouped[$country][] = [
                'siteId' => $siteIdColumn ? $row[$siteIdColumn] ?? null : null,
                'siteName' => $this->normalizeSiteName($siteNameColumn ? $row[$siteNameColumn] ?? null : null),
                'startDate' => $this->formatDate($startDateColumn ? $row[$startDateColumn] ?? null : null),
                'endDate' => $this->formatDate($endDateColumn ? $row[$endDateColumn] ?? null : null),
                'confidence' => $override['ragConfidence'] ?? null,
                'status' => $override['statusText'] ?? null,
            ];
        }

        foreach ($grouped as &$entries) {
            usort($entries, static function (array $a, array $b): int {
                return strcmp((string) ($a['startDate'] ?? ''), (string) ($b['startDate'] ?? ''));
            });
        }
        unset($entries);

        return $grouped;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, array<string, mixed>>
     */
    private function indexRowsByKey(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $columns = $this->resolveColumns($rows[0]);
        $indexed = [];
        foreach ($rows as $row) {
            $key = $this->rowKey($row, $columns['sheetName'], $columns['siteId']);
            if ($key === null) {
                continue;
            }
            $indexed[$key] = $row;
        }

        return $indexed;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function rowKey(array $row, ?string $sheetColumn, ?string $siteIdColumn): ?string
    {
        $sheet = $sheetColumn ? trim((string) ($row[$sheetColumn] ?? '')) : '';
        $siteId = $siteIdColumn ? trim((string) ($row[$siteIdColumn] ?? '')) : '';

        if ($sheet === '' || $siteId === '') {
            return null;
        }

        return sprintf('%s|%s', $sheet, $siteId);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, string|null>
     */
    private function resolveColumns(array $row): array
    {
        return [
            'country' => $this->findColumn($row, self::COUNTRY_CANDIDATES),
            'siteId' => $this->findColumn($row, self::SITE_ID_CANDIDATES),
            'siteName' => $this->findColumn($row, self::SITE_NAME_CANDIDATES),
            'startDate' => $this->findColumn($row, self::START_DATE_CANDIDATES),
            'endDate' => $this->findColumn($row, self::END_DATE_CANDIDATES),
            'confidence' => $this->findColumn($row, self::CONFIDENCE_CANDIDATES),
            'status' => $this->findColumn($row, self::STATUS_CANDIDATES),
            'sheetName' => $this->findColumn($row, self::SHEET_NAME_CANDIDATES),
        ];
    }

    private function resolveCountry(array $primaryRow, ?string $primaryColumn, ?array $fallbackRow, ?string $fallbackColumn): string
    {
        $country = $primaryColumn ? trim((string) ($primaryRow[$primaryColumn] ?? '')) : '';
        if ($country !== '') {
            return $country;
        }
        if ($fallbackRow !== null && $fallbackColumn) {
            $fallback = trim((string) ($fallbackRow[$fallbackColumn] ?? ''));
            if ($fallback !== '') {
                return $fallback;
            }
        }

        return 'Unspecified';
    }

    private function parseDate(mixed $value): ?DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }
        if ($value instanceof \DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);
        if ($string === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($string);
        } catch (\Throwable) {
            return null;
        }
    }

    private function formatDateTime(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if ($value === null) {
            return null;
        }
        $string = trim((string) $value);
        if ($string === '') {
            return null;
        }
        try {
            return (new DateTimeImmutable($string))->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return $string;
        }
    }

    private function formatDate(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if ($value === null) {
            return null;
        }
        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private function normalizeSiteName(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $name = trim((string) $value);
        if ($name === '') {
            return null;
        }
        $name = preg_replace('/^\s*IKEA\s*Store\s*[-–—:]?\s*/i', '', $name);
        $name = preg_replace('/^\s*IKEAStore\s*[-–—:]?\s*/i', '', $name);
        $name = preg_replace('/^\s*IKEA\s*[-–—:]?\s*/i', '', $name);
        $name = trim((string) $name);

        return $name === '' ? null : $name;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, string> $candidates
     */
    private function findColumn(array $row, array $candidates): ?string
    {
        if ($row === []) {
            return null;
        }

        foreach ($candidates as $candidate) {
            foreach ($row as $key => $value) {
                if (strcasecmp($key, $candidate) === 0) {
                    return $key;
                }
            }
        }

        return null;
    }
}
