<?php

namespace App\Controller\Api;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Annotation\Route;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;

#[Route('/api/smartsheet/presentation', name: 'api_smartsheet_presentation_')]
class SmartsheetPresentationController extends AbstractController
{
    private const MASTER_TABLE = 'nifi.smartsheet_master_data';
    private const COUNTRY_GANTT_VIEW = 'nifi.smartsheet_country_gantt_view';
    private const PLANNED_WEEK_VIEW = 'nifi.smartsheet_planned_week_view';
    private const DEFAULT_TASK_NAME = 'Assessment Execution';
    private const POST_DEPLOYMENT_TASK = 'Post-Deployment Survey and Correction Process';
    private const SIGN_OFF_TASK = 'Store Sign off Completed';
    private const STATUS_TABLE = 'smartsheet_status_log';
    private const CONTENT_TABLE = 'smartsheet_content';
    private const TASK_TRACKER_TABLE = 'smartsheet_task_tracker';
    private const TASK_TRACKER_FILES_TABLE = 'smartsheet_files.task_tracker_files';
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
    private const TASK_ID_CANDIDATES = ['task_id', 'Task_ID', 'Task Id', 'TaskID'];
    private const PARENT_ID_CANDIDATES = ['parent_id', 'Parent_ID', 'Parent Id', 'ParentID', 'Parent_Task_ID', 'Parent Task Id'];
    private const PHASE_CANDIDATES = ['Phase', 'phase'];
    private const HISTORY_TABLE_NAME = 'smartsheet_master_data_history_changes';
    private const HISTORY_TASK_ID_CANDIDATES = ['task_id', 'Task_ID', 'Task Id', 'TaskID'];
    private const HISTORY_COLUMN_NAME_CANDIDATES = ['column_name', 'Column_Name', 'Column Name', 'field_name', 'Field_Name', 'Field Name', 'field', 'column'];
    private const HISTORY_CHANGE_TYPE_CANDIDATES = ['change_type', 'Change_Type', 'Change Type', 'type'];
    private const HISTORY_OLD_START_CANDIDATES = ['old_start_date', 'Old_Start_Date', 'Old Start Date'];
    private const HISTORY_NEW_START_CANDIDATES = ['new_start_date', 'New_Start_Date', 'New Start Date'];
    private const HISTORY_OLD_END_CANDIDATES = ['old_end_date', 'Old_End_Date', 'Old End Date'];
    private const HISTORY_NEW_END_CANDIDATES = ['new_end_date', 'New_End_Date', 'New End Date'];
    private const HISTORY_PREVIOUS_RUN_CANDIDATES = ['previous_run', 'Previous_Run', 'Previous Run', 'previous_run_at', 'previous_run_date'];
    private const HISTORY_CURRENT_RUN_CANDIDATES = ['current_run', 'Current_Run', 'Current Run', 'current_run_at', 'current_run_date'];
    private const START_DATE_CANDIDATES = ['Start_Date', 'Start Date', 'Start'];
    private const END_DATE_CANDIDATES = ['End_Date', 'End Date', 'End'];
    private const CONFIDENCE_CANDIDATES = ['Confidence', 'Confidence_Level', 'Confidence Level'];
    private const STATUS_CANDIDATES = ['Status', 'Task_Status', 'Task Status'];
    private const TASK_NAME_CANDIDATES = ['Task_Name', 'Task Name', 'task_name', 'task name'];
    private const ROW_NUM_CANDIDATES = ['row_num', 'Row_Num', 'Row Num', 'Row_Number', 'Row Number', 'Row'];
    private const COMMENT_CANDIDATES = ['Comment', 'Comments', 'Notes', 'Note'];
    private const RAG_CANDIDATES = ['Status (RAG)', 'Status_RAG', 'RAG', 'Rag', 'rag'];
    private const SHEET_NAME_CANDIDATES = ['sheet_name', 'Sheet_Name', 'Sheet Name', 'Sheet'];
    private const COUNTRY_FLAG_MAP = [
        'Australia' => 'AU',
        'Austria' => 'AT',
        'Belgium' => 'BE',
        'Bulgaria' => 'BG',
        'Canada' => 'CA',
        'Croatia' => 'HR',
        'Cyprus' => 'CY',
        'Czechia' => 'CZ',
        'Czech Republic' => 'CZ',
        'Denmark' => 'DK',
        'Estonia' => 'EE',
        'Finland' => 'FI',
        'France' => 'FR',
        'Germany' => 'DE',
        'Greece' => 'GR',
        'Hungary' => 'HU',
        'Iceland' => 'IS',
        'India' => 'IN',
        'Ireland' => 'IE',
        'Italy' => 'IT',
        'Latvia' => 'LV',
        'Lithuania' => 'LT',
        'Luxembourg' => 'LU',
        'Malta' => 'MT',
        'Netherlands' => 'NL',
        'Norway' => 'NO',
        'Poland' => 'PL',
        'Portugal' => 'PT',
        'Romania' => 'RO',
        'Slovakia' => 'SK',
        'Slovenia' => 'SI',
        'Spain' => 'ES',
        'Sweden' => 'SE',
        'Switzerland' => 'CH',
        'United Kingdom' => 'GB',
        'UK' => 'GB',
        'United States' => 'US',
        'USA' => 'US',
    ];

    /** @var array<string, string|null> */
    private array $flagCache = [];

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

        $columns = $this->resolveMasterColumns();
        $countryColumn = $columns['country'] ?? null;
        $siteIdColumn = $columns['siteId'] ?? null;
        $siteNameColumn = $columns['siteName'] ?? null;
        $taskNameColumn = $columns['taskName'] ?? null;
        $taskIdColumn = $columns['taskId'] ?? null;
        $parentIdColumn = $columns['parentId'] ?? null;
        $phaseColumn = $columns['phase'] ?? null;
        $startColumn = $columns['startDate'] ?? null;
        $endColumn = $columns['endDate'] ?? null;
        $phaseColumn = $columns['phase'] ?? null;

        $commentOverridesRow = $this->connection->fetchAssociative(
            sprintf('SELECT content FROM %s WHERE section = :section ORDER BY created_at DESC LIMIT 1', self::CONTENT_TABLE),
            ['section' => 'planned_week_comments']
        );
        $commentOverrides = json_decode((string) ($commentOverridesRow['content'] ?? ''), true);
        $commentOverrides = is_array($commentOverrides) ? $commentOverrides : [];

        $formatDateKey = static function ($value): string {
            if ($value === null || $value === '') {
                return '';
            }
            $timestamp = strtotime((string) $value);
            if ($timestamp === false) {
                return trim((string) $value);
            }
            return date('Y-m-d', $timestamp);
        };

        $buildCommentKey = static function (
            string $countryValue,
            string $siteIdValue,
            string $siteNameValue,
            string $taskValue,
            string $startValue,
            string $endValue
        ): string {
            $siteKey = $siteIdValue !== '' ? $siteIdValue : $siteNameValue;
            return mb_strtolower(implode('||', [
                trim($countryValue),
                trim($siteKey),
                trim($taskValue),
                $startValue,
                $endValue,
            ]));
        };

        $sql = 'SELECT * FROM nifi.smartsheet_planned_week_view';
        $params = [];
        if ($country !== '') {
            $sql .= ' WHERE country = :country';
            $params['country'] = $country;
        }

        $rows = $this->connection->fetchAllAssociative($sql, $params);
        foreach ($rows as &$row) {
            $rowCountry = $countryColumn && array_key_exists($countryColumn, $row)
                ? (string) ($row[$countryColumn] ?? '')
                : (string) ($row['country'] ?? ($row['Country'] ?? ''));
            $rowSiteId = $siteIdColumn && array_key_exists($siteIdColumn, $row)
                ? (string) ($row[$siteIdColumn] ?? '')
                : (string) ($row['site_id'] ?? ($row['Site_ID'] ?? ($row['SiteID'] ?? '')));
            $rowSiteName = $siteNameColumn && array_key_exists($siteNameColumn, $row)
                ? (string) ($row[$siteNameColumn] ?? '')
                : (string) ($row['site_name'] ?? ($row['Site_Name'] ?? ($row['SiteName'] ?? '')));
            $rowTask = $taskNameColumn && array_key_exists($taskNameColumn, $row)
                ? (string) ($row[$taskNameColumn] ?? '')
                : (string) ($row['task_name'] ?? ($row['Task_Name'] ?? ''));
            $rowStart = $startColumn && array_key_exists($startColumn, $row)
                ? $row[$startColumn]
                : ($row['start_date'] ?? ($row['Start_Date'] ?? null));
            $rowEnd = $endColumn && array_key_exists($endColumn, $row)
                ? $row[$endColumn]
                : ($row['end_date'] ?? ($row['End_Date'] ?? null));

            $key = $buildCommentKey(
                $rowCountry,
                $rowSiteId,
                $rowSiteName,
                $rowTask,
                $formatDateKey($rowStart),
                $formatDateKey($rowEnd)
            );

            if ($key !== '' && array_key_exists($key, $commentOverrides)) {
                $row['comment'] = (string) ($commentOverrides[$key] ?? '');
            } else {
                $row['comment'] = '';
            }
        }
        unset($row);

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

    #[Route('/export-html', name: 'presentation_export_html', methods: ['POST'])]
    public function exportPresentationHtml(\Symfony\Component\HttpFoundation\Request $request): BinaryFileResponse
    {
        $payload = json_decode((string) $request->getContent(), true) ?? [];
        $countries = $this->normalizeCountryFilter($payload['countries'] ?? null);

        $assessments = $this->plannedDataForTask(self::DEFAULT_TASK_NAME, $countries);
        $installations = $this->plannedDataForTask('Installation Execution', $countries);
        $postDeployment = $this->postDeploymentData($countries);
        $issueLog = $this->issueLogData($countries);
        $overview = $this->programmeOverviewData();
        $timeline = $this->timelineData($countries);
        $progress = $this->progressData($countries);
        $plannedWeekRows = $this->plannedWeekData($countries);

        $highlightsContent = $this->getLatestContent('highlights');
        $trendOverrides = $this->decodeOverrides($this->getLatestContent('trend_overrides'));
        $overviewOverrides = $this->decodeOverrides($this->getLatestContent('overview_overrides'));

        $overviewItems = $this->filterItemsByCountries($overview['items'] ?? [], $countries, 'country');

        $assets = [
            'logo' => $this->imageToDataUri(dirname(__DIR__, 3) . '/public/images/logo.png'),
            'traffic_green' => $this->imageToDataUri(dirname(__DIR__, 3) . '/public/images/green.png'),
            'traffic_amber' => $this->imageToDataUri(dirname(__DIR__, 3) . '/public/images/yellow.png'),
            'traffic_red' => $this->imageToDataUri(dirname(__DIR__, 3) . '/public/images/red.png'),
        ];

        $html = $this->buildOfflinePresentationHtml([
            'generatedAt' => (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
            'countries' => $countries,
            'plannedAssessments' => $assessments,
            'plannedInstallations' => $installations,
            'postDeployment' => $postDeployment,
            'issueLog' => $issueLog,
            'overviewItems' => $overviewItems,
            'timeline' => $timeline,
            'progress' => $progress,
            'plannedWeekRows' => $plannedWeekRows,
            'highlights' => $highlightsContent,
            'trendOverrides' => $trendOverrides,
            'overviewOverrides' => $overviewOverrides,
        ], $assets);

        $tmpHtml = tempnam(sys_get_temp_dir(), 'rep_html_') . '.html';
        file_put_contents($tmpHtml, $html);

        $filename = sprintf('presentation-%s.html', (new DateTimeImmutable('now'))->format('Ymd_His'));
        $response = new BinaryFileResponse($tmpHtml);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);
        $response->headers->set('Content-Type', 'text/html; charset=utf-8');
        $response->headers->set('Cache-Control', 'no-store');
        $response->deleteFileAfterSend(true);

        return $response;
    }

    #[Route('/export-xlsx', name: 'presentation_export_xlsx', methods: ['POST'])]
    public function exportPresentationXlsx(\Symfony\Component\HttpFoundation\Request $request): BinaryFileResponse
    {
        $payload = json_decode((string) $request->getContent(), true) ?? [];
        $countries = $this->normalizeCountryFilter($payload['countries'] ?? null);

        $assessments = $this->plannedDataForTask(self::DEFAULT_TASK_NAME, $countries);
        $installations = $this->plannedDataForTask('Installation Execution', $countries);
        $postDeployment = $this->postDeploymentData($countries);
        $issueLog = $this->issueLogData($countries);
        $overview = $this->programmeOverviewData();
        $timeline = $this->timelineData($countries);
        $plannedWeekRows = $this->plannedWeekData($countries);

        $trendOverrides = $this->decodeOverrides($this->getLatestContent('trend_overrides'));
        $overviewOverrides = $this->decodeOverrides($this->getLatestContent('overview_overrides'));

        $overviewItems = $this->filterItemsByCountries($overview['items'] ?? [], $countries, 'country');

        $overviewRows = [];
        foreach ($overviewItems as $row) {
            $country = (string) ($row['country'] ?? '');
            $override = $overviewOverrides[$country] ?? [];
            $ragValue = ($override['rag'] ?? null) !== null && $override['rag'] !== '' ? $override['rag'] : ($row['rag'] ?? '');
            $commentValue = ($override['comment'] ?? null) !== null && $override['comment'] !== '' ? $override['comment'] : ($row['comment'] ?? '');
            $overviewRows[] = [
                $country,
                $row['stores'] ?? '',
                $row['assessed'] ?? '',
                $row['ongoingInstallations'] ?? '',
                $row['storesInstalled'] ?? '',
                $row['storeSignoff'] ?? '',
                $ragValue,
                $commentValue,
            ];
        }

        $plannedWeekExportRows = [];
        foreach ($plannedWeekRows as $row) {
            $country = $this->resolveRowField($row, ['country', 'Country']) ?? '—';
            $siteName = $this->cleanSiteName($this->resolveRowField($row, ['site_name', 'siteName', 'Site_Name', 'SiteName']) ?? '');
            $siteId = $this->resolveRowField($row, ['site_id', 'siteId', 'Site_ID', 'SiteID']) ?? '';
            $taskName = $this->resolveRowField($row, ['task_name', 'taskName', 'Task_Name', 'TaskName']) ?? '—';
            $startDate = $this->formatDate($this->resolveRowField($row, ['start_date', 'startDate', 'Start_Date', 'StartDate']));
            $endDate = $this->formatDate($this->resolveRowField($row, ['end_date', 'endDate', 'End_Date', 'EndDate']));
            $status = $this->resolveRowField($row, ['status', 'Status']) ?? '';
            $comment = $this->resolveRowField($row, ['comment', 'Comment']) ?? '';

            $plannedWeekExportRows[] = [
                $country,
                $siteName !== '' ? $siteName : '—',
                $siteId,
                $taskName,
                $startDate ?? '—',
                $endDate ?? '—',
                $status !== '' ? $status : '—',
                $comment !== '' ? $comment : '—',
            ];
        }

        $timelineRows = [];
        foreach (($timeline['items'] ?? []) as $row) {
            $timelineRows[] = [
                $row['country'] ?? '',
                $row['startDate'] ?? '',
                $row['installEndDate'] ?? '',
                $row['endDate'] ?? '',
            ];
        }

        $trendGroups = [
            'green' => [],
            'amber' => [],
            'red' => [],
        ];
        foreach ($overviewItems as $row) {
            $country = (string) ($row['country'] ?? '');
            $override = $trendOverrides[$country] ?? [];
            $ragValue = ($override['rag'] ?? null) !== null && $override['rag'] !== '' ? $override['rag'] : ($row['rag'] ?? '');
            $commentValue = ($override['comment'] ?? null) !== null && $override['comment'] !== '' ? $override['comment'] : ($row['comment'] ?? '');
            $rag = $this->normalizeRagValue($ragValue);
            if ($rag !== '' && isset($trendGroups[$rag])) {
                $trendGroups[$rag][] = [
                    $country,
                    $row['stores'] ?? '',
                    $row['assessed'] ?? '',
                    $row['ongoingInstallations'] ?? '',
                    $row['storesInstalled'] ?? '',
                    $commentValue,
                ];
            }
        }

        $generalIssuesRows = [];
        foreach (($issueLog['items'] ?? []) as $block) {
            $country = $block['country'] ?? '';
            foreach (($block['issues'] ?? []) as $issue) {
                $generalIssuesRows[] = [
                    $country,
                    $issue['storeName'] ?? '',
                    $issue['storeId'] ?? '',
                    $issue['description'] ?? '',
                    $issue['priority'] ?? '',
                    $issue['responsibleParty'] ?? '',
                    $issue['actionRequired'] ?? '',
                    $issue['resolveDate'] ?? '',
                ];
            }
        }

        $allPlannedRows = [];
        $appendPlannedRows = static function (string $section, array $meta, array $items) use (&$allPlannedRows): void {
            $currentLabel = $meta['currentMonth'] ?? 'Current month';
            $nextLabel = $meta['nextMonth'] ?? 'Next month';
            foreach ($items as $block) {
                $country = $block['country'] ?? '';
                foreach ([
                    'current' => $currentLabel,
                    'next' => $nextLabel,
                ] as $bucket => $label) {
                    foreach (($block[$bucket] ?? []) as $entry) {
                        $allPlannedRows[] = [
                            $country,
                            $section,
                            $label,
                            $entry['siteName'] ?? '',
                            $entry['siteId'] ?? '',
                            $entry['startDate'] ?? '',
                            $entry['endDate'] ?? '',
                            $entry['confidence'] ?? '',
                            $entry['status'] ?? '',
                        ];
                    }
                }
            }
        };
        $appendPlannedRows('Planned Assessments', $assessments['meta'] ?? [], $assessments['items'] ?? []);
        $appendPlannedRows('Planned Installations', $installations['meta'] ?? [], $installations['items'] ?? []);
        $appendPlannedRows('Post-Deployment & Sign-off', $postDeployment['meta'] ?? [], $postDeployment['items'] ?? []);

        $allIssueRows = [];
        foreach (($issueLog['items'] ?? []) as $block) {
            $country = $block['country'] ?? '';
            foreach (($block['issues'] ?? []) as $issue) {
                $allIssueRows[] = [
                    $country,
                    $issue['storeName'] ?? '',
                    $issue['storeId'] ?? '',
                    $issue['description'] ?? '',
                    $issue['priority'] ?? '',
                    $issue['responsibleParty'] ?? '',
                    $issue['actionRequired'] ?? '',
                    $issue['resolveDate'] ?? '',
                ];
            }
        }

        $tmp = tempnam(sys_get_temp_dir(), 'rep_xlsx_');
        $tmpWithExt = $tmp . '.xlsx';
        @rename($tmp, $tmpWithExt);

        $writer = new XlsxWriter();
        $writer->openToFile($tmpWithExt);
        $headerStyle = (new Style())->setFontBold();
        $sheetIndex = 0;

        $addSheet = static function (XlsxWriter $writer, Style $headerStyle, int &$sheetIndex, string $name, array $headers, array $rows): void {
            if ($sheetIndex === 0) {
                $writer->getCurrentSheet()->setName($name);
            } else {
                $writer->addNewSheetAndMakeItCurrent();
                $writer->getCurrentSheet()->setName($name);
            }
            $sheetIndex += 1;
            $writer->addRow(Row::fromValues($headers, $headerStyle));
            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues($row));
            }
        };

        try {
            $addSheet($writer, $headerStyle, $sheetIndex, 'Programme Overview',
                ['Country', 'Stores', 'Assessed', 'Ongoing Installations', 'Installed', 'Sign-off', 'RAG', 'Comment'],
                $overviewRows
            );
            $addSheet($writer, $headerStyle, $sheetIndex, 'Status Planned',
                ['Country', 'Site Name', 'Site ID', 'Activity', 'Start', 'End', 'Status', 'Comment'],
                $plannedWeekExportRows
            );
            $addSheet($writer, $headerStyle, $sheetIndex, 'Timeline',
                ['Country', 'Start Date', 'Install End Date', 'End Date'],
                $timelineRows
            );
            $addSheet($writer, $headerStyle, $sheetIndex, 'Trend Green',
                ['Country', 'Total', 'Assessments', 'Ongoing Installation', 'Stores Installed', 'Comment'],
                $trendGroups['green']
            );
            $addSheet($writer, $headerStyle, $sheetIndex, 'Trend Amber',
                ['Country', 'Total', 'Assessments', 'Ongoing Installation', 'Stores Installed', 'Comment'],
                $trendGroups['amber']
            );
            $addSheet($writer, $headerStyle, $sheetIndex, 'Trend Red',
                ['Country', 'Total', 'Assessments', 'Ongoing Installation', 'Stores Installed', 'Comment'],
                $trendGroups['red']
            );
            $addSheet($writer, $headerStyle, $sheetIndex, 'General Issues',
                ['Country', 'Site Name', 'Site ID', 'Description', 'Priority', 'Responsible Party', 'Action', 'Resolve Date'],
                $generalIssuesRows
            );
            $addSheet($writer, $headerStyle, $sheetIndex, 'All Planned',
                ['Country', 'Section', 'Month', 'Site Name', 'Site ID', 'Start', 'End', 'Confidence', 'Status'],
                $allPlannedRows
            );
            $addSheet($writer, $headerStyle, $sheetIndex, 'All Issues',
                ['Country', 'Site Name', 'Site ID', 'Description', 'Priority', 'Responsible Party', 'Action', 'Resolve Date'],
                $allIssueRows
            );
        } finally {
            $writer->close();
        }

        $filename = sprintf('presentation-%s.xlsx', (new DateTimeImmutable('now'))->format('Ymd_His'));
        $response = new BinaryFileResponse($tmpWithExt);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Cache-Control', 'no-store');
        $response->deleteFileAfterSend(true);

        return $response;
    }

    #[Route('/issues', name: 'issue_log', methods: ['GET'])]
    public function issues(): JsonResponse
    {
        $payload = $this->issueLogData();
        $payload['generalIssues'] = $this->generalIssueLogData();
        return $this->json($payload);
    }

    #[Route('/task-tracker', name: 'task_tracker_index', methods: ['GET'])]
    public function taskTrackerIndex(): JsonResponse
    {
        $rows = $this->connection->fetchAllAssociative(
            sprintf(
                'SELECT id, log_date, category, description, responsible, tasks_json, created_at, updated_at FROM %s ORDER BY log_date DESC, id DESC',
                self::TASK_TRACKER_TABLE
            )
        );

        $items = array_map(static function (array $row): array {
            $tasks = json_decode((string) ($row['tasks_json'] ?? ''), true);
            $id = (int) ($row['id'] ?? 0);
            return [
                'id' => $id,
                'taskId' => $id,
                'date' => $row['log_date'] ?? null,
                'category' => $row['category'] ?? null,
                'description' => $row['description'] ?? null,
                'responsible' => $row['responsible'] ?? null,
                'tasks' => is_array($tasks) ? $tasks : [],
                'createdAt' => $row['created_at'] ?? null,
                'updatedAt' => $row['updated_at'] ?? null,
            ];
        }, $rows);

        return $this->json(['items' => $items]);
    }

    #[Route('/task-tracker/options', name: 'task_tracker_options', methods: ['GET'])]
    public function taskTrackerOptions(Request $request): JsonResponse
    {
        $offset = max(0, (int) $request->query->get('offset', 0));
        $limit = (int) $request->query->get('limit', 200);
        if ($limit < 50) {
            $limit = 50;
        }
        if ($limit > 500) {
            $limit = 500;
        }

        $countryFilter = trim((string) $request->query->get('country', ''));
        $siteFilter = trim((string) $request->query->get('site', ''));
        $taskFilter = trim((string) $request->query->get('task', ''));

        $columns = $this->resolveMasterColumns();
        $countryColumn = $columns['country'] ?? null;
        $siteNameColumn = $columns['siteName'] ?? null;
        $siteIdColumn = $columns['siteId'] ?? null;
        $taskIdColumn = $columns['taskId'] ?? null;
        $taskNameColumn = $columns['taskName'] ?? null;

        if (!$countryColumn && !$siteNameColumn && !$siteIdColumn && !$taskNameColumn) {
            return $this->json(['items' => [], 'hasMore' => false, 'nextOffset' => $offset]);
        }

        $selectParts = [];
        if ($countryColumn) {
            $selectParts[] = sprintf('`%s` AS country', $countryColumn);
        }
        if ($siteNameColumn) {
            $selectParts[] = sprintf('`%s` AS site_name', $siteNameColumn);
        }
        if ($siteIdColumn) {
            $selectParts[] = sprintf('`%s` AS site_id', $siteIdColumn);
        }
        if ($taskIdColumn) {
            $selectParts[] = sprintf('`%s` AS task_id', $taskIdColumn);
        }
        if ($taskNameColumn) {
            $selectParts[] = sprintf('`%s` AS task_name', $taskNameColumn);
        }

        $sql = sprintf('SELECT DISTINCT %s FROM %s WHERE 1=1', implode(', ', $selectParts), self::MASTER_TABLE);
        $params = [];

        if ($countryFilter !== '' && $countryColumn) {
            $sql .= sprintf(' AND `%s` LIKE :country', $countryColumn);
            $params['country'] = '%' . $countryFilter . '%';
        }

        if ($siteFilter !== '' && ($siteNameColumn || $siteIdColumn)) {
            if ($siteNameColumn && $siteIdColumn) {
                $sql .= sprintf(' AND (`%s` LIKE :site OR `%s` LIKE :site)', $siteNameColumn, $siteIdColumn);
            } elseif ($siteNameColumn) {
                $sql .= sprintf(' AND `%s` LIKE :site', $siteNameColumn);
            } else {
                $sql .= sprintf(' AND `%s` LIKE :site', $siteIdColumn);
            }
            $params['site'] = '%' . $siteFilter . '%';
        }

        if ($taskFilter !== '' && $taskNameColumn) {
            $sql .= sprintf(' AND `%s` LIKE :task', $taskNameColumn);
            $params['task'] = '%' . $taskFilter . '%';
        }

        $orderParts = array_values(array_filter([
            $countryColumn ? sprintf('`%s`', $countryColumn) : null,
            $siteNameColumn ? sprintf('`%s`', $siteNameColumn) : null,
            $siteIdColumn ? sprintf('`%s`', $siteIdColumn) : null,
            $taskIdColumn ? sprintf('`%s`', $taskIdColumn) : null,
            $taskNameColumn ? sprintf('`%s`', $taskNameColumn) : null,
        ]));
        if ($orderParts) {
            $sql .= ' ORDER BY ' . implode(', ', $orderParts);
        }

        $sql .= sprintf(' LIMIT %d OFFSET %d', $limit, $offset);

        $rows = $this->connection->fetchAllAssociative($sql, $params);
        $items = array_map(static function (array $row): array {
            return [
                'country' => $row['country'] ?? null,
                'siteName' => $row['site_name'] ?? null,
                'siteId' => $row['site_id'] ?? null,
                'taskId' => isset($row['task_id']) ? (string) $row['task_id'] : null,
                'taskName' => $row['task_name'] ?? null,
            ];
        }, $rows);

        $nextOffset = $offset + count($items);

        return $this->json([
            'items' => $items,
            'hasMore' => count($items) >= $limit,
            'nextOffset' => $nextOffset,
        ]);
    }

    #[Route('/task-tracker', name: 'task_tracker_create', methods: ['POST'])]
    public function taskTrackerCreate(Request $request): JsonResponse
    {
        $payload = json_decode((string) $request->getContent(), true) ?? [];
        $date = trim((string) ($payload['date'] ?? ''));
        $category = trim((string) ($payload['category'] ?? ''));
        $description = trim((string) ($payload['description'] ?? ''));
        $responsible = trim((string) ($payload['responsible'] ?? '')) ?: null;
        $tasks = $payload['tasks'] ?? [];

        $normalizedDate = $this->normalizeTaskTrackerDate($date);
        if ($normalizedDate === null) {
            return $this->json(['message' => 'date must be in YYYY-MM-DD format.'], 400);
        }

        if ($category === '' || $description === '') {
            return $this->json(['message' => 'date, category, and description are required.'], 400);
        }
        if (!is_array($tasks)) {
            $tasks = [];
        }

        $createdAt = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $this->connection->insert(self::TASK_TRACKER_TABLE, [
            'log_date' => $normalizedDate,
            'category' => $category,
            'description' => $description,
            'responsible' => $responsible,
            'tasks_json' => json_encode(array_values($tasks), JSON_UNESCAPED_UNICODE),
            'created_at' => $createdAt,
        ]);

        $id = (int) $this->connection->lastInsertId();

        return $this->json(['ok' => true, 'id' => $id, 'taskId' => $id, 'createdAt' => $createdAt]);
    }

    #[Route('/task-tracker/draft', name: 'task_tracker_draft', methods: ['POST'])]
    public function taskTrackerDraft(Request $request): JsonResponse
    {
        $payload = json_decode((string) $request->getContent(), true) ?? [];
        $date = trim((string) ($payload['date'] ?? ''));
        $category = trim((string) ($payload['category'] ?? ''));
        $description = trim((string) ($payload['description'] ?? ''));
        $responsible = trim((string) ($payload['responsible'] ?? '')) ?: null;
        $tasks = $payload['tasks'] ?? [];

        $normalizedDate = $this->normalizeTaskTrackerDate($date) ?? (new DateTimeImmutable('now'))->format('Y-m-d');
        if (!is_array($tasks)) {
            $tasks = [];
        }

        if ($category === '') {
            $category = 'Draft';
        }
        if ($description === '') {
            $description = 'Draft entry';
        }

        $createdAt = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $this->connection->insert(self::TASK_TRACKER_TABLE, [
            'log_date' => $normalizedDate,
            'category' => $category,
            'description' => $description,
            'responsible' => $responsible,
            'tasks_json' => json_encode(array_values($tasks), JSON_UNESCAPED_UNICODE),
            'created_at' => $createdAt,
        ]);

        $id = (int) $this->connection->lastInsertId();

        return $this->json([
            'ok' => true,
            'id' => $id,
            'taskId' => $id,
            'createdAt' => $createdAt,
            'category' => $category,
            'description' => $description,
            'date' => $normalizedDate,
        ]);
    }

    #[Route('/task-tracker/{id}', name: 'task_tracker_update', methods: ['PUT'])]
    public function taskTrackerUpdate(int $id, Request $request): JsonResponse
    {
        $payload = json_decode((string) $request->getContent(), true) ?? [];
        $date = trim((string) ($payload['date'] ?? ''));
        $category = trim((string) ($payload['category'] ?? ''));
        $description = trim((string) ($payload['description'] ?? ''));
        $responsible = trim((string) ($payload['responsible'] ?? '')) ?: null;
        $tasks = $payload['tasks'] ?? [];

        $normalizedDate = $this->normalizeTaskTrackerDate($date);
        if ($normalizedDate === null) {
            return $this->json(['message' => 'date must be in YYYY-MM-DD format.'], 400);
        }

        if ($category === '' || $description === '') {
            return $this->json(['message' => 'date, category, and description are required.'], 400);
        }
        if (!is_array($tasks)) {
            $tasks = [];
        }

        $updatedAt = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $this->connection->update(self::TASK_TRACKER_TABLE, [
            'log_date' => $normalizedDate,
            'category' => $category,
            'description' => $description,
            'responsible' => $responsible,
            'tasks_json' => json_encode(array_values($tasks), JSON_UNESCAPED_UNICODE),
            'updated_at' => $updatedAt,
        ], ['id' => $id]);

        return $this->json(['ok' => true, 'id' => $id, 'taskId' => $id, 'updatedAt' => $updatedAt]);
    }

    #[Route('/task-tracker/{id}/files', name: 'task_tracker_files_index', methods: ['GET'])]
    public function taskTrackerFilesIndex(int $id): JsonResponse
    {
        $rows = $this->connection->fetchAllAssociative(
            sprintf(
                'SELECT id, filename, mime_type, size_bytes, created_at FROM %s WHERE task_tracker_id = :id ORDER BY id DESC',
                self::TASK_TRACKER_FILES_TABLE
            ),
            ['id' => $id]
        );

        $items = array_map(static function (array $row): array {
            return [
                'id' => (int) ($row['id'] ?? 0),
                'filename' => $row['filename'] ?? null,
                'mimeType' => $row['mime_type'] ?? null,
                'size' => (int) ($row['size_bytes'] ?? 0),
                'createdAt' => $row['created_at'] ?? null,
                'downloadUrl' => sprintf('/api/smartsheet/presentation/task-tracker/files/%d', (int) ($row['id'] ?? 0)),
            ];
        }, $rows);

        return $this->json(['items' => $items]);
    }

    #[Route('/task-tracker/{id}/files', name: 'task_tracker_files_upload', methods: ['POST'])]
    public function taskTrackerFilesUpload(int $id, Request $request): JsonResponse
    {
        $files = $request->files->all();
        $uploads = [];

        if (isset($files['files']) && is_array($files['files'])) {
            $uploads = array_merge($uploads, $files['files']);
        }
        if (isset($files['file'])) {
            $uploads[] = $files['file'];
        }

        if (!$uploads) {
            return $this->json(['message' => 'No files uploaded.'], 400);
        }

        $createdAt = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $saved = [];

        foreach ($uploads as $upload) {
            if (!$upload || !$upload->isValid()) {
                continue;
            }
            $content = file_get_contents($upload->getPathname());
            if ($content === false) {
                continue;
            }

            $this->connection->insert(
                self::TASK_TRACKER_FILES_TABLE,
                [
                    'task_tracker_id' => $id,
                    'filename' => $upload->getClientOriginalName(),
                    'mime_type' => $upload->getClientMimeType() ?: 'application/octet-stream',
                    'size_bytes' => (int) $upload->getSize(),
                    'content' => $content,
                    'created_at' => $createdAt,
                ],
                [
                    'task_tracker_id' => ParameterType::INTEGER,
                    'size_bytes' => ParameterType::INTEGER,
                    'content' => ParameterType::LARGE_OBJECT,
                ]
            );

            $fileId = (int) $this->connection->lastInsertId();
            $saved[] = [
                'id' => $fileId,
                'filename' => $upload->getClientOriginalName(),
                'mimeType' => $upload->getClientMimeType() ?: 'application/octet-stream',
                'size' => (int) $upload->getSize(),
                'createdAt' => $createdAt,
                'downloadUrl' => sprintf('/api/smartsheet/presentation/task-tracker/files/%d', $fileId),
            ];
        }

        return $this->json(['items' => $saved]);
    }

    #[Route('/task-tracker/files/{fileId}', name: 'task_tracker_files_download', methods: ['GET'])]
    public function taskTrackerFilesDownload(int $fileId): Response
    {
        $row = $this->connection->fetchAssociative(
            sprintf('SELECT filename, mime_type, content FROM %s WHERE id = :id', self::TASK_TRACKER_FILES_TABLE),
            ['id' => $fileId]
        );

        if (!$row) {
            return new Response('Not found', 404);
        }

        $response = new Response($row['content'] ?? '');
        $response->headers->set('Content-Type', $row['mime_type'] ?? 'application/octet-stream');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            (string) ($row['filename'] ?? 'file')
        ));
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    #[Route('/overview', name: 'presentation_overview', methods: ['GET'])]
    public function overview(): JsonResponse
    {
        return $this->json($this->programmeOverviewData());
    }

    #[Route('/gantt-countries', name: 'presentation_gantt_countries', methods: ['GET'])]
    public function ganttCountries(): JsonResponse
    {
        $columns = $this->resolveMasterColumns();
        $countryColumn = $columns['country'] ?? null;
        if (!$countryColumn) {
            return $this->json(['items' => []]);
        }

        $rows = $this->connection->fetchFirstColumn(
            sprintf('SELECT DISTINCT `%s` AS country FROM %s WHERE `%s` IS NOT NULL AND `%s` <> "" ORDER BY `%s`',
                $countryColumn,
                self::MASTER_TABLE,
                $countryColumn,
                $countryColumn,
                $countryColumn
            )
        );

        $items = array_values(array_filter(array_map(static fn ($value) => trim((string) $value), $rows)));

        return $this->json(['items' => $items]);
    }

    #[Route('/gantt-sites', name: 'presentation_gantt_sites', methods: ['GET'])]
    public function ganttSites(Request $request): JsonResponse
    {
        $country = trim((string) $request->query->get('country', ''));
        if ($country === '') {
            return $this->json(['items' => []]);
        }

        $columns = $this->resolveMasterColumns();
        $countryColumn = $columns['country'] ?? null;
        $siteNameColumn = $columns['siteName'] ?? null;
        $siteIdColumn = $columns['siteId'] ?? null;

        if (!$countryColumn || (!$siteNameColumn && !$siteIdColumn)) {
            return $this->json(['items' => []]);
        }

        $selectParts = [];
        if ($siteNameColumn) {
            $selectParts[] = sprintf('`%s` AS site_name', $siteNameColumn);
        }
        if ($siteIdColumn) {
            $selectParts[] = sprintf('`%s` AS site_id', $siteIdColumn);
        }

        $sql = sprintf(
            'SELECT DISTINCT %s FROM %s WHERE `%s` = :country',
            implode(', ', $selectParts),
            self::MASTER_TABLE,
            $countryColumn
        );

        $rows = $this->connection->fetchAllAssociative($sql, ['country' => $country]);
        $items = [];
        foreach ($rows as $row) {
            $siteName = trim((string) ($row['site_name'] ?? ''));
            $siteId = trim((string) ($row['site_id'] ?? ''));
            $key = $siteId !== '' ? $siteId : $siteName;
            $label = $siteName !== '' ? $siteName : $siteId;
            if ($key === '' || $label === '') {
                continue;
            }
            $items[] = [
                'key' => $key,
                'label' => $label,
                'siteName' => $siteName ?: null,
                'siteId' => $siteId ?: null,
            ];
        }

        usort($items, static fn (array $a, array $b): int => strcasecmp($a['label'], $b['label']));

        return $this->json(['items' => $items]);
    }

    #[Route('/gantt', name: 'presentation_gantt', methods: ['GET'])]
    public function gantt(Request $request): JsonResponse
    {
        $country = trim((string) $request->query->get('country', ''));
        $siteKey = trim((string) $request->query->get('site', ''));
        if ($country === '' || $siteKey === '') {
            return $this->json(['items' => []]);
        }

        $columns = $this->resolveMasterColumns();
        $countryColumn = $columns['country'] ?? null;
        $siteIdColumn = $columns['siteId'] ?? null;
        $siteNameColumn = $columns['siteName'] ?? null;
        $taskNameColumn = $columns['taskName'] ?? null;
        $taskIdColumn = $columns['taskId'] ?? null;
        $parentIdColumn = $columns['parentId'] ?? null;
        $phaseColumn = $columns['phase'] ?? null;
        $startColumn = $columns['startDate'] ?? null;
        $endColumn = $columns['endDate'] ?? null;

        if (!$countryColumn || !$taskNameColumn || !$startColumn || !$endColumn || (!$siteNameColumn && !$siteIdColumn)) {
            return $this->json(['items' => []]);
        }

        $selectParts = [
            sprintf('`%s` AS country', $countryColumn),
            sprintf('`%s` AS task_name', $taskNameColumn),
            sprintf('`%s` AS start_date', $startColumn),
            sprintf('`%s` AS end_date', $endColumn),
        ];
        if ($taskIdColumn) {
            $selectParts[] = sprintf('`%s` AS task_id', $taskIdColumn);
        }
        if ($parentIdColumn) {
            $selectParts[] = sprintf('`%s` AS parent_id', $parentIdColumn);
        }
        if ($phaseColumn) {
            $selectParts[] = sprintf('`%s` AS phase', $phaseColumn);
        }
        if ($siteNameColumn) {
            $selectParts[] = sprintf('`%s` AS site_name', $siteNameColumn);
        }
        if ($siteIdColumn) {
            $selectParts[] = sprintf('`%s` AS site_id', $siteIdColumn);
        }

        $sql = sprintf(
            'SELECT %s FROM %s WHERE `%s` = :country AND `%s` IS NOT NULL AND `%s` IS NOT NULL',
            implode(', ', $selectParts),
            self::MASTER_TABLE,
            $countryColumn,
            $startColumn,
            $endColumn
        );

        if ($siteNameColumn && $siteIdColumn) {
            $sql .= sprintf(' AND (`%s` = :site OR `%s` = :site)', $siteNameColumn, $siteIdColumn);
        } elseif ($siteNameColumn) {
            $sql .= sprintf(' AND `%s` = :site', $siteNameColumn);
        } else {
            $sql .= sprintf(' AND `%s` = :site', $siteIdColumn);
        }

        $sql .= sprintf(' ORDER BY `%s`', $startColumn);

        $rows = $this->connection->fetchAllAssociative($sql, ['country' => $country, 'site' => $siteKey]);

        $changeMap = $this->resolveHistoryChangeMap($rows, $taskIdColumn, $startColumn, $endColumn);

        $items = [];
        foreach ($rows as $row) {
            $taskId = isset($row['task_id']) ? (string) $row['task_id'] : null;
            $changes = $taskId ? ($changeMap[$taskId] ?? []) : [];
            $items[] = [
                'country' => $row['country'] ?? null,
                'siteId' => $row['site_id'] ?? null,
                'siteName' => $row['site_name'] ?? null,
                'taskId' => $taskId,
                'parentId' => isset($row['parent_id']) ? (string) $row['parent_id'] : null,
                'taskName' => $row['task_name'] ?? null,
                'phase' => $row['phase'] ?? null,
                'startDate' => $row['start_date'] ?? null,
                'endDate' => $row['end_date'] ?? null,
                'startChanged' => (bool) ($changes['start'] ?? false),
                'endChanged' => (bool) ($changes['end'] ?? false),
                'startChange' => $changes['startInfo'] ?? null,
                'endChange' => $changes['endInfo'] ?? null,
            ];
        }

        return $this->json(['items' => $items]);
    }

    #[Route('/gantt-country-tasks', name: 'presentation_gantt_country_tasks', methods: ['GET'])]
    public function ganttCountryTasks(): JsonResponse
    {
        $columns = $this->resolveCountryGanttColumns();
        $taskNameColumn = $columns['taskName'] ?? null;
        $phaseColumn = $columns['phase'] ?? null;

        if (!$taskNameColumn || !$phaseColumn) {
            return $this->json(['items' => []]);
        }

        $sql = sprintf(
            'SELECT DISTINCT `%s` AS task_name FROM %s WHERE `%s` IS NOT NULL AND `%s` <> "" AND IFNULL(`%s`, "") NOT IN (\'Store\', \'Country\') ORDER BY `%s`',
            $taskNameColumn,
            self::COUNTRY_GANTT_VIEW,
            $taskNameColumn,
            $taskNameColumn,
            $phaseColumn,
            $taskNameColumn
        );

        $items = $this->connection->executeQuery($sql)->fetchFirstColumn();
        $items = array_values(array_filter(array_map('trim', $items), static fn (?string $value) => $value !== null && $value !== ''));

        return $this->json(['items' => $items]);
    }

    #[Route('/gantt-country', name: 'presentation_gantt_country', methods: ['GET'])]
    public function ganttCountry(
        \Symfony\Component\HttpFoundation\Request $request
    ): JsonResponse
    {
        $columns = $this->resolveCountryGanttColumns();
        $countryColumn = $columns['country'] ?? null;
        $siteIdColumn = $columns['siteId'] ?? null;
        $siteNameColumn = $columns['siteName'] ?? null;
        $taskNameColumn = $columns['taskName'] ?? null;
        $startColumn = $columns['startDate'] ?? null;
        $endColumn = $columns['endDate'] ?? null;
        $rowNumColumn = $columns['rowNum'] ?? null;

        if (!$countryColumn || !$taskNameColumn || !$startColumn || !$endColumn || (!$siteNameColumn && !$siteIdColumn)) {
            return $this->json(['items' => []]);
        }

        $selectParts = [
            sprintf('`%s` AS country', $countryColumn),
            sprintf('`%s` AS task_name', $taskNameColumn),
            sprintf('`%s` AS start_date', $startColumn),
            sprintf('`%s` AS end_date', $endColumn),
        ];
        if ($siteNameColumn) {
            $selectParts[] = sprintf('`%s` AS site_name', $siteNameColumn);
        }
        if ($siteIdColumn) {
            $selectParts[] = sprintf('`%s` AS site_id', $siteIdColumn);
        }

        $tasksParam = $request->query->get('tasks');
        if (is_array($tasksParam)) {
            $taskNames = $tasksParam;
        } elseif (is_string($tasksParam) && $tasksParam !== '') {
            $taskNames = [$tasksParam];
        } else {
            $taskNames = [];
        }
        $taskNames = array_filter(array_map(static fn ($value) => strtolower(trim((string) $value)), $taskNames));
        if ($taskNames === []) {
            $taskNames = ['assessment execution', 'installation execution', 'store sign off completed'];
        }
        $sql = sprintf(
            'SELECT %s FROM %s WHERE `%s` IS NOT NULL AND `%s` IS NOT NULL AND TRIM(`%s`) <> "" AND TRIM(`%s`) <> "" AND TRIM(LOWER(`%s`)) IN (?)',
            implode(', ', $selectParts),
            self::COUNTRY_GANTT_VIEW,
            $startColumn,
            $endColumn,
            $startColumn,
            $endColumn,
            $taskNameColumn
        );

        $orderParts = [
            sprintf('`%s`', $countryColumn),
        ];
        if ($siteNameColumn && $siteIdColumn) {
            $orderParts[] = sprintf('COALESCE(`%s`, `%s`)', $siteNameColumn, $siteIdColumn);
        } elseif ($siteNameColumn) {
            $orderParts[] = sprintf('`%s`', $siteNameColumn);
        } elseif ($siteIdColumn) {
            $orderParts[] = sprintf('`%s`', $siteIdColumn);
        }
        if ($rowNumColumn) {
            $orderParts[] = sprintf('`%s`', $rowNumColumn);
        }
        $orderParts[] = sprintf('`%s`', $taskNameColumn);
        $orderParts[] = sprintf('`%s`', $startColumn);

        $rows = $this->connection->executeQuery(
            $sql . ' ORDER BY ' . implode(', ', $orderParts),
            [$taskNames],
            [ArrayParameterType::STRING]
        )->fetchAllAssociative();

        $items = array_map(static function (array $row): array {
            return [
                'country' => $row['country'] ?? null,
                'siteId' => $row['site_id'] ?? null,
                'siteName' => $row['site_name'] ?? null,
                'taskName' => $row['task_name'] ?? null,
                'startDate' => $row['start_date'] ?? null,
                'endDate' => $row['end_date'] ?? null,
            ];
        }, $rows);

        return $this->json(['items' => $items]);
    }

    #[Route('/wonderful-states', name: 'presentation_wonderful_states', methods: ['GET'])]
    public function wonderfulStates(): JsonResponse
    {
        return $this->json(['items' => ['Done', 'In progress', 'Not started']]);
    }

    #[Route('/wonderful', name: 'presentation_wonderful', methods: ['GET'])]
    public function wonderfulReport(Request $request): JsonResponse
    {
        $columns = $this->resolveCountryGanttColumns();
        $countryColumn = $columns['country'] ?? null;
        $siteIdColumn = $columns['siteId'] ?? null;
        $siteNameColumn = $columns['siteName'] ?? null;
        $taskNameColumn = $columns['taskName'] ?? null;
        $startColumn = $columns['startDate'] ?? null;
        $endColumn = $columns['endDate'] ?? null;
        $statusColumn = $columns['status'] ?? null;
        $rowNumColumn = $columns['rowNum'] ?? null;

        if (!$countryColumn || !$taskNameColumn || !$startColumn || !$endColumn || (!$siteNameColumn && !$siteIdColumn)) {
            return $this->json(['items' => []]);
        }

        $selectParts = [
            sprintf('`%s` AS country', $countryColumn),
            sprintf('`%s` AS task_name', $taskNameColumn),
            sprintf('`%s` AS start_date', $startColumn),
            sprintf('`%s` AS end_date', $endColumn),
        ];
        if ($siteNameColumn) {
            $selectParts[] = sprintf('`%s` AS site_name', $siteNameColumn);
        }
        if ($siteIdColumn) {
            $selectParts[] = sprintf('`%s` AS site_id', $siteIdColumn);
        }
        if ($statusColumn) {
            $selectParts[] = sprintf('`%s` AS status', $statusColumn);
        }

        $whereParts = [
            sprintf('`%s` IS NOT NULL', $startColumn),
            sprintf('`%s` IS NOT NULL', $endColumn),
            sprintf('TRIM(`%s`) <> ""', $startColumn),
            sprintf('TRIM(`%s`) <> ""', $endColumn),
        ];
        $params = [];
        $types = [];

        $taskParam = $request->query->get('task');
        if (is_array($taskParam)) {
            $taskNames = $taskParam;
        } elseif (is_string($taskParam) && $taskParam !== '') {
            $taskNames = [$taskParam];
        } else {
            $taskNames = [];
        }
        $taskNames = array_filter(array_map(static fn ($value) => strtolower(trim((string) $value)), $taskNames));
        if ($taskNames !== []) {
            $whereParts[] = sprintf('TRIM(LOWER(`%s`)) IN (?)', $taskNameColumn);
            $params[] = $taskNames;
            $types[] = ArrayParameterType::STRING;
        }

        $stateParam = $request->query->get('state');
        $stateFilter = null;
        if ($stateParam !== null && $stateParam !== '') {
            $stateFilter = strtolower(trim((string) (is_array($stateParam) ? ($stateParam[0] ?? '') : $stateParam)));
        }

        $from = trim((string) $request->query->get('from', ''));
        $to = trim((string) $request->query->get('to', ''));
        if ($from !== '') {
            $whereParts[] = sprintf('`%s` >= :from_date', $endColumn);
            $params['from_date'] = $from;
            $types['from_date'] = ParameterType::STRING;
        }
        if ($to !== '') {
            $whereParts[] = sprintf('`%s` <= :to_date', $startColumn);
            $params['to_date'] = $to;
            $types['to_date'] = ParameterType::STRING;
        }

        $sql = sprintf(
            'SELECT %s FROM %s WHERE %s',
            implode(', ', $selectParts),
            self::COUNTRY_GANTT_VIEW,
            implode(' AND ', $whereParts)
        );

        $orderParts = [
            sprintf('`%s`', $countryColumn),
        ];
        if ($siteNameColumn && $siteIdColumn) {
            $orderParts[] = sprintf('COALESCE(`%s`, `%s`)', $siteNameColumn, $siteIdColumn);
        } elseif ($siteNameColumn) {
            $orderParts[] = sprintf('`%s`', $siteNameColumn);
        } elseif ($siteIdColumn) {
            $orderParts[] = sprintf('`%s`', $siteIdColumn);
        }
        if ($rowNumColumn) {
            $orderParts[] = sprintf('`%s`', $rowNumColumn);
        }
        $orderParts[] = sprintf('`%s`', $taskNameColumn);
        $orderParts[] = sprintf('`%s`', $startColumn);

        $rows = $this->connection->executeQuery(
            $sql . ' ORDER BY ' . implode(', ', $orderParts),
            $params,
            $types
        )->fetchAllAssociative();

        $items = array_values(array_filter(array_map(static function (array $row) use ($stateFilter): ?array {
            $rawStatus = isset($row['status']) ? (string) $row['status'] : '';
            $statusLower = strtolower(trim($rawStatus));
            $normalizedStatus = 'Not started';
            if ($statusLower !== '') {
                if (str_contains($statusLower, 'done') || str_contains($statusLower, 'complete') || str_contains($statusLower, 'completed')) {
                    $normalizedStatus = 'Done';
                } elseif (str_contains($statusLower, 'progress') || str_contains($statusLower, 'ongoing') || str_contains($statusLower, 'in progress')) {
                    $normalizedStatus = 'In progress';
                }
            }

            if ($stateFilter !== null && strtolower($normalizedStatus) !== $stateFilter) {
                return null;
            }

            return [
                'country' => $row['country'] ?? null,
                'siteId' => $row['site_id'] ?? null,
                'siteName' => $row['site_name'] ?? null,
                'taskName' => $row['task_name'] ?? null,
                'startDate' => $row['start_date'] ?? null,
                'endDate' => $row['end_date'] ?? null,
                'status' => $normalizedStatus,
            ];
        }, $rows)));

        return $this->json(['items' => $items]);
    }

    private function normalizeTaskTrackerDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if ($date instanceof DateTimeImmutable) {
            return $date->format('Y-m-d');
        }

        $date = DateTimeImmutable::createFromFormat('d/m/Y', $value);
        if ($date instanceof DateTimeImmutable) {
            return $date->format('Y-m-d');
        }

        return null;
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
        $rows = $this->connection->fetchAllAssociative(
            'SELECT country, site_id, site_name, install_start, install_end, signoff_end FROM nifi.smartsheet_timeline_view'
        );

        $installStartByCountry = [];
        $installEndByCountry = [];
        $signoffCompletedByCountry = [];
        $sitesByCountry = [];
        $siteAliasesByCountry = [];

        foreach ($rows as $row) {
            $country = trim((string) ($row['country'] ?? ''));
            $country = $country !== '' ? $country : 'Unspecified';

            $siteId = trim((string) ($row['site_id'] ?? ''));
            $siteName = $this->normalizeSiteName($row['site_name'] ?? null);
            $normalizedName = $siteName ? mb_strtolower($siteName) : null;
            if ($siteId === '' && (!$siteName || $siteName === '')) {
                continue;
            }

            if (!isset($sitesByCountry[$country])) {
                $sitesByCountry[$country] = [];
            }
            if (!isset($siteAliasesByCountry[$country])) {
                $siteAliasesByCountry[$country] = ['id' => [], 'name' => []];
            }

            $key = null;
            if ($siteId !== '' && isset($siteAliasesByCountry[$country]['id'][$siteId])) {
                $key = $siteAliasesByCountry[$country]['id'][$siteId];
            } elseif ($normalizedName && isset($siteAliasesByCountry[$country]['name'][$normalizedName])) {
                $key = $siteAliasesByCountry[$country]['name'][$normalizedName];
            }
            if ($key === null) {
                $key = $siteId !== '' ? $siteId : (string) $siteName;
            }
            if (!isset($sitesByCountry[$country][$key])) {
                $sitesByCountry[$country][$key] = [
                    'siteId' => $siteId !== '' ? $siteId : null,
                    'siteName' => $siteName,
                    'startDate' => null,
                    'installEndDate' => null,
                    'endDate' => null,
                ];
            }

            if ($siteId !== '') {
                $siteAliasesByCountry[$country]['id'][$siteId] = $key;
                if (!$sitesByCountry[$country][$key]['siteId']) {
                    $sitesByCountry[$country][$key]['siteId'] = $siteId;
                }
            }
            if ($normalizedName) {
                $siteAliasesByCountry[$country]['name'][$normalizedName] = $key;
                if (!$sitesByCountry[$country][$key]['siteName']) {
                    $sitesByCountry[$country][$key]['siteName'] = $siteName;
                }
            }

            $installStart = $this->parseDate($row['install_start'] ?? null);
            $installEnd = $this->parseDate($row['install_end'] ?? null);
            $signoffEnd = $this->parseDate($row['signoff_end'] ?? null);

            if ($installStart !== null && (!isset($installStartByCountry[$country]) || $installStart < $installStartByCountry[$country])) {
                $installStartByCountry[$country] = $installStart;
            }
            if ($installEnd !== null && (!isset($installEndByCountry[$country]) || $installEnd > $installEndByCountry[$country])) {
                $installEndByCountry[$country] = $installEnd;
            }
            if ($signoffEnd !== null && (!isset($signoffCompletedByCountry[$country]) || $signoffEnd > $signoffCompletedByCountry[$country])) {
                $signoffCompletedByCountry[$country] = $signoffEnd;
            }

            $entry = &$sitesByCountry[$country][$key];
            if ($installStart !== null && ($entry['startDate'] === null || $installStart < $entry['startDate'])) {
                $entry['startDate'] = $installStart;
            }
            if ($installEnd !== null && ($entry['installEndDate'] === null || $installEnd > $entry['installEndDate'])) {
                $entry['installEndDate'] = $installEnd;
            }
            if ($signoffEnd !== null && ($entry['endDate'] === null || $signoffEnd > $entry['endDate'])) {
                $entry['endDate'] = $signoffEnd;
            }
            unset($entry);
        }

        $countryList = array_unique(array_merge(
            array_keys($installStartByCountry),
            array_keys($installEndByCountry),
            array_keys($signoffCompletedByCountry)
        ));
        $countryList = $this->applyCountryFilter($countryList, $countries);

        $items = [];
        foreach ($countryList as $country) {
            $rawSites = $sitesByCountry[$country] ?? [];
            $sites = [];
            foreach ($rawSites as $site) {
                $sites[] = [
                    'siteId' => $site['siteId'] ?? null,
                    'siteName' => $site['siteName'] ?? null,
                    'startDate' => $this->formatDate($site['startDate'] ?? null),
                    'installEndDate' => $this->formatDate($site['installEndDate'] ?? null),
                    'endDate' => $this->formatDate($site['endDate'] ?? null),
                ];
            }
            usort($sites, static function (array $a, array $b): int {
                $labelA = (string) ($a['siteName'] ?? $a['siteId'] ?? '');
                $labelB = (string) ($b['siteName'] ?? $b['siteId'] ?? '');
                return strcasecmp($labelA, $labelB);
            });
            $items[] = [
                'country' => $country,
                'startDate' => $this->formatDate($installStartByCountry[$country] ?? null),
                'installEndDate' => $this->formatDate($installEndByCountry[$country] ?? null),
                'endDate' => $this->formatDate($signoffCompletedByCountry[$country] ?? null),
                'sites' => $sites,
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
     * @return array{ikea: array<int, array<string, mixed>>, hpe: array<int, array<string, mixed>>}
     */
    private function generalIssueLogData(?array $countries = null): array
    {
           $sql = 'SELECT id, country, responsible_party, blocker_title, description, action_to_be_taken, priority '
               . 'FROM nifi.smartsheet_general_issues_view';

        $rows = $this->connection->fetchAllAssociative($sql);

        $ikea = [];
        $hpe = [];

        foreach ($rows as $row) {
            $country = trim((string) ($row['country'] ?? '')) ?: 'Unspecified';
            if ($countries && !$this->countryMatchesFilter($country, $countries)) {
                continue;
            }

            $owner = trim((string) ($row['responsible_party'] ?? ''));
            $description = trim((string) ($row['blocker_title'] ?? ''));
            if ($description === '') {
                $description = trim((string) ($row['description'] ?? ''));
            }

            $entry = [
                'id' => (int) ($row['id'] ?? 0),
                'description' => $description !== '' ? $description : null,
                'country' => $country,
                'owner' => $owner !== '' ? $owner : null,
                'action' => $row['action_to_be_taken'] ?? null,
                'priority' => $row['priority'] ?? null,
            ];

            if (stripos($owner, 'IKEA') !== false) {
                $ikea[] = $entry;
            } else {
                $hpe[] = $entry;
            }
        }

        return ['ikea' => $ikea, 'hpe' => $hpe];
    }

    /**
     * @param array<int, string> $countries
     */
    private function countryMatchesFilter(string $countryValue, array $countries): bool
    {
        foreach ($countries as $country) {
            if ($country === '') {
                continue;
            }
            if (stripos($countryValue, $country) !== false) {
                return true;
            }
        }
        return false;
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
        $source = match ($taskName) {
            self::DEFAULT_TASK_NAME => 'nifi.smartsheet_planned_assessments_view',
            'Installation Execution' => 'nifi.smartsheet_planned_installations_view',
            self::POST_DEPLOYMENT_TASK, self::SIGN_OFF_TASK => 'nifi.smartsheet_post_deployment_signoff_view',
            default => self::MASTER_TABLE,
        };
        $sql = sprintf(
            'SELECT * FROM %s WHERE Task_Name = :taskName AND Start_Date <= :endDate AND End_Date >= :startDate',
            $source
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
        $source = match ($taskName) {
            self::DEFAULT_TASK_NAME => 'nifi.smartsheet_planned_assessments_view',
            'Installation Execution' => 'nifi.smartsheet_planned_installations_view',
            self::POST_DEPLOYMENT_TASK, self::SIGN_OFF_TASK => 'nifi.smartsheet_post_deployment_signoff_view',
            default => self::MASTER_TABLE,
        };
        $sql = sprintf('SELECT * FROM %s WHERE Task_Name = :taskName', $source);

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
        $overviewOverrides = $this->connection->fetchAssociative(
            sprintf('SELECT content FROM %s WHERE section = :section ORDER BY created_at DESC LIMIT 1', self::CONTENT_TABLE),
            ['section' => 'overview_overrides']
        );
        $overviewOverrideData = json_decode((string) ($overviewOverrides['content'] ?? ''), true);
        $overviewOverrideData = is_array($overviewOverrideData) ? $overviewOverrideData : [];

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
            $countryData[$country]['sites'][$siteKey] = true;

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
            $override = $overviewOverrideData[$country] ?? [];
            $items[] = [
                'country' => $country,
                'stores' => count($data['sites'] ?? []),
                'assessed' => count($data['assessed'] ?? []),
                'ongoingInstallations' => count($data['ongoingInstallations'] ?? []),
                'storesInstalled' => count($data['storesInstalled'] ?? []),
                'defectsCompleted' => count($data['defectsCompleted'] ?? []),
                'storeSignoff' => count($data['storeSignoff'] ?? []),
                'comment' => is_array($override) ? ($override['comment'] ?? null) : null,
                'rag' => is_array($override) ? ($override['rag'] ?? null) : null,
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
            'taskId' => $this->findColumnName($columns, self::TASK_ID_CANDIDATES),
            'parentId' => $this->findColumnName($columns, self::PARENT_ID_CANDIDATES),
            'phase' => $this->findColumnName($columns, self::PHASE_CANDIDATES),
            'taskName' => $this->findColumnName($columns, self::TASK_NAME_CANDIDATES),
            'startDate' => $this->findColumnName($columns, self::START_DATE_CANDIDATES),
            'endDate' => $this->findColumnName($columns, self::END_DATE_CANDIDATES),
            'status' => $this->findColumnName($columns, self::STATUS_CANDIDATES),
            'comment' => $this->findColumnName($columns, self::COMMENT_CANDIDATES),
            'rag' => $this->findColumnName($columns, self::RAG_CANDIDATES),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function resolveCountryGanttColumns(): array
    {
        $columns = $this->connection->fetchFirstColumn(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table',
            [
                'schema' => 'nifi',
                'table' => 'smartsheet_country_gantt_view',
            ]
        );

        return [
            'country' => $this->findColumnName($columns, self::COUNTRY_CANDIDATES),
            'siteId' => $this->findColumnName($columns, self::SITE_ID_CANDIDATES),
            'siteName' => $this->findColumnName($columns, self::SITE_NAME_CANDIDATES),
            'phase' => $this->findColumnName($columns, self::PHASE_CANDIDATES),
            'taskName' => $this->findColumnName($columns, self::TASK_NAME_CANDIDATES),
            'startDate' => $this->findColumnName($columns, self::START_DATE_CANDIDATES),
            'endDate' => $this->findColumnName($columns, self::END_DATE_CANDIDATES),
            'rowNum' => $this->findColumnName($columns, self::ROW_NUM_CANDIDATES),
            'status' => $this->findColumnName($columns, self::STATUS_CANDIDATES),
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

    /**
     * @return array<string, array{start?: bool, end?: bool}>
     */
    private function resolveHistoryChangeMap(array $rows, ?string $taskIdColumn, ?string $startColumn, ?string $endColumn): array
    {
        if (!$taskIdColumn) {
            return [];
        }

        $taskIds = [];
        foreach ($rows as $row) {
            $taskId = trim((string) ($row['task_id'] ?? ''));
            if ($taskId !== '') {
                $taskIds[$taskId] = true;
            }
        }

        if (!$taskIds) {
            return [];
        }

        $historyTable = $this->resolveHistoryTable();
        if (!$historyTable) {
            return [];
        }

        $historyColumns = $this->resolveHistoryColumns($historyTable['schema'], $historyTable['table']);
        $historyTaskIdColumn = $historyColumns['taskId'] ?? null;
        $historyColumnColumn = $historyColumns['columnName'] ?? null;
        $historyChangeTypeColumn = $historyColumns['changeType'] ?? null;
        $historyOldStart = $historyColumns['oldStart'] ?? null;
        $historyNewStart = $historyColumns['newStart'] ?? null;
        $historyOldEnd = $historyColumns['oldEnd'] ?? null;
        $historyNewEnd = $historyColumns['newEnd'] ?? null;
        $historyPreviousRun = $historyColumns['previousRun'] ?? null;
        $historyCurrentRun = $historyColumns['currentRun'] ?? null;
        if (!$historyTaskIdColumn || !$historyChangeTypeColumn) {
            return [];
        }

        $map = [];

        if ($historyColumnColumn) {
            $startCandidates = array_filter(array_unique(array_merge(
                $startColumn ? [$startColumn] : [],
                self::START_DATE_CANDIDATES
            )));
            $endCandidates = array_filter(array_unique(array_merge(
                $endColumn ? [$endColumn] : [],
                self::END_DATE_CANDIDATES
            )));
            $startLower = array_values(array_unique(array_map('mb_strtolower', $startCandidates)));
            $endLower = array_values(array_unique(array_map('mb_strtolower', $endCandidates)));
            $allLower = array_values(array_unique(array_merge($startLower, $endLower)));
            if ($allLower) {
                $sql = sprintf(
                    'SELECT `%s` AS task_id, `%s` AS column_name FROM %s WHERE `%s` IN (?) AND LOWER(`%s`) = :changeType AND LOWER(`%s`) IN (?)',
                    $historyTaskIdColumn,
                    $historyColumnColumn,
                    $historyTable['qualified'],
                    $historyTaskIdColumn,
                    $historyChangeTypeColumn,
                    $historyColumnColumn
                );

                $historyRows = $this->connection->executeQuery(
                    $sql,
                    [array_keys($taskIds), 'changed', $allLower],
                    [ArrayParameterType::STRING, ParameterType::STRING, ArrayParameterType::STRING]
                )->fetchAllAssociative();

                foreach ($historyRows as $row) {
                    $taskId = trim((string) ($row['task_id'] ?? ''));
                    $column = mb_strtolower(trim((string) ($row['column_name'] ?? '')));
                    if ($taskId === '' || $column === '') {
                        continue;
                    }
                    if (in_array($column, $startLower, true)) {
                        $map[$taskId]['start'] = true;
                    }
                    if (in_array($column, $endLower, true)) {
                        $map[$taskId]['end'] = true;
                    }
                }
            }
        }

        if ($historyOldStart && $historyNewStart) {
            $selectParts = [
                sprintf('`%s` AS task_id', $historyTaskIdColumn),
                sprintf('`%s` AS old_start', $historyOldStart),
                sprintf('`%s` AS new_start', $historyNewStart),
                sprintf('`%s` AS old_end', $historyOldEnd ?? $historyOldStart),
                sprintf('`%s` AS new_end', $historyNewEnd ?? $historyNewStart),
            ];
            if ($historyPreviousRun) {
                $selectParts[] = sprintf('`%s` AS previous_run', $historyPreviousRun);
            }
            if ($historyCurrentRun) {
                $selectParts[] = sprintf('`%s` AS current_run', $historyCurrentRun);
            }

            $sql = sprintf(
                'SELECT %s FROM %s WHERE `%s` IN (?) AND LOWER(`%s`) = ?',
                implode(', ', $selectParts),
                $historyTable['qualified'],
                $historyTaskIdColumn,
                $historyChangeTypeColumn
            );

            $historyRows = $this->connection->executeQuery(
                $sql,
                [array_keys($taskIds), 'changed'],
                [ArrayParameterType::STRING, ParameterType::STRING]
            )->fetchAllAssociative();

            foreach ($historyRows as $row) {
                $taskId = trim((string) ($row['task_id'] ?? ''));
                if ($taskId === '') {
                    continue;
                }
                $oldStart = trim((string) ($row['old_start'] ?? ''));
                $newStart = trim((string) ($row['new_start'] ?? ''));
                $oldEnd = trim((string) ($row['old_end'] ?? ''));
                $newEnd = trim((string) ($row['new_end'] ?? ''));
                $previousRun = $row['previous_run'] ?? null;
                $currentRun = $row['current_run'] ?? null;
                if ($oldStart !== '' || $newStart !== '') {
                    if ($oldStart !== $newStart) {
                        $map[$taskId]['start'] = true;
                        $map[$taskId]['startInfo'] = [
                            'old' => $oldStart ?: null,
                            'new' => $newStart ?: null,
                            'previousRun' => $previousRun,
                            'currentRun' => $currentRun,
                        ];
                    }
                }
                if ($oldEnd !== '' || $newEnd !== '') {
                    if ($oldEnd !== $newEnd) {
                        $map[$taskId]['end'] = true;
                        $map[$taskId]['endInfo'] = [
                            'old' => $oldEnd ?: null,
                            'new' => $newEnd ?: null,
                            'previousRun' => $previousRun,
                            'currentRun' => $currentRun,
                        ];
                    }
                }
            }
        }

        return $map;
    }

    /**
     * @return array{schema: string, table: string, qualified: string}|null
     */
    private function resolveHistoryTable(): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT TABLE_SCHEMA AS schema_name, TABLE_NAME AS table_name FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = :table ORDER BY (TABLE_SCHEMA = :preferred) DESC LIMIT 1',
            [
                'table' => self::HISTORY_TABLE_NAME,
                'preferred' => 'nifi',
            ]
        );

        $schema = $row['schema_name'] ?? null;
        $table = $row['table_name'] ?? null;
        if (!$schema || !$table) {
            return null;
        }

        return [
            'schema' => $schema,
            'table' => $table,
            'qualified' => sprintf('%s.%s', $schema, $table),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function resolveHistoryColumns(string $schema, string $table): array
    {
        $columns = $this->connection->fetchFirstColumn(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table',
            [
                'schema' => $schema,
                'table' => $table,
            ]
        );

        return [
            'taskId' => $this->findColumnName($columns, self::HISTORY_TASK_ID_CANDIDATES),
            'columnName' => $this->findColumnName($columns, self::HISTORY_COLUMN_NAME_CANDIDATES),
            'changeType' => $this->findColumnName($columns, self::HISTORY_CHANGE_TYPE_CANDIDATES),
            'oldStart' => $this->findColumnName($columns, self::HISTORY_OLD_START_CANDIDATES),
            'newStart' => $this->findColumnName($columns, self::HISTORY_NEW_START_CANDIDATES),
            'oldEnd' => $this->findColumnName($columns, self::HISTORY_OLD_END_CANDIDATES),
            'newEnd' => $this->findColumnName($columns, self::HISTORY_NEW_END_CANDIDATES),
            'previousRun' => $this->findColumnName($columns, self::HISTORY_PREVIOUS_RUN_CANDIDATES),
            'currentRun' => $this->findColumnName($columns, self::HISTORY_CURRENT_RUN_CANDIDATES),
        ];
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
        $source = $taskName === self::SIGN_OFF_TASK
            ? 'nifi.smartsheet_post_deployment_signoff_view'
            : self::MASTER_TABLE;
        $sql = sprintf(
            'SELECT * FROM %s WHERE Task_Name = :taskName AND End_Date >= :startDate AND End_Date <= :endDate',
            $source
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
        $sql = 'SELECT id, country, store_name, store_id, description, priority, responsible_party, action_required, resolve_date '
             . 'FROM repweb.smartsheet_issue_log_view ORDER BY country, resolve_date, store_name';
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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function plannedWeekData(?array $countries = null): array
    {
        $sql = sprintf('SELECT * FROM %s', self::PLANNED_WEEK_VIEW);
        $rows = $this->connection->fetchAllAssociative($sql);
        if ($countries === null || $countries === []) {
            return $rows;
        }

        $countrySet = array_flip(array_map(static fn (string $c): string => mb_strtolower(trim($c)), $countries));

        return array_values(array_filter($rows, static function (array $row) use ($countrySet): bool {
            $country = '';
            foreach (self::COUNTRY_CANDIDATES as $candidate) {
                foreach ($row as $key => $value) {
                    if (strcasecmp($key, $candidate) === 0) {
                        $country = trim((string) $value);
                        break 2;
                    }
                }
            }
            if ($country === '') {
                return false;
            }
            return isset($countrySet[mb_strtolower($country)]);
        }));
    }

    private function getLatestContent(string $section): string
    {
        $row = $this->connection->fetchAssociative(
            sprintf('SELECT content FROM %s WHERE section = :section ORDER BY created_at DESC LIMIT 1', self::CONTENT_TABLE),
            ['section' => $section]
        );

        return (string) ($row['content'] ?? '');
    }

    /**
     * @return array<string, array{rag?: string|null, comment?: string|null}>
     */
    private function decodeOverrides(string $content): array
    {
        if ($content === '') {
            return [];
        }
        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function filterItemsByCountries(array $items, ?array $countries, string $key): array
    {
        if ($countries === null || $countries === []) {
            return $items;
        }
        $set = array_flip(array_map(static fn (string $c): string => mb_strtolower(trim($c)), $countries));

        return array_values(array_filter($items, static function (array $item) use ($set, $key): bool {
            $country = isset($item[$key]) ? trim((string) $item[$key]) : '';
            if ($country === '') {
                return false;
            }
            return isset($set[mb_strtolower($country)]);
        }));
    }

    private function imageToDataUri(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }
        $data = file_get_contents($path);
        if ($data === false) {
            return null;
        }
        $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            default => 'application/octet-stream',
        };

        return sprintf('data:%s;base64,%s', $mime, base64_encode($data));
    }

    private function getFlagDataUri(string $country): ?string
    {
        $overviewOverrides = $this->connection->fetchAssociative(
            sprintf('SELECT content FROM %s WHERE section = :section ORDER BY created_at DESC LIMIT 1', self::CONTENT_TABLE),
            ['section' => 'overview_overrides']
        );
        $overviewOverrideData = json_decode((string) ($overviewOverrides['content'] ?? ''), true);
        $overviewOverrideData = is_array($overviewOverrideData) ? $overviewOverrideData : [];

        $rows = $this->connection->fetchAllAssociative(
            'SELECT country, stores, assessed, ongoing_installations, stores_installed, store_signoff '
            . 'FROM nifi.smartsheet_country_trend_view'
        );

        $items = [];
        foreach ($rows as $row) {
            $country = trim((string) ($row['country'] ?? '')) ?: 'Unspecified';
            $override = $overviewOverrideData[$country] ?? [];
            $items[] = [
                'country' => $country,
                'stores' => (int) ($row['stores'] ?? 0),
                'assessed' => (int) ($row['assessed'] ?? 0),
                'ongoingInstallations' => (int) ($row['ongoing_installations'] ?? 0),
                'storesInstalled' => (int) ($row['stores_installed'] ?? 0),
                'defectsCompleted' => 0,
                'storeSignoff' => (int) ($row['store_signoff'] ?? 0),
                'comment' => is_array($override) ? ($override['comment'] ?? null) : null,
                'rag' => is_array($override) ? ($override['rag'] ?? null) : null,
            ];
        }

        usort($items, static fn (array $a, array $b): int => strcasecmp($a['country'], $b['country']));

        return ['items' => $items];

        $timelineMap = [];
        foreach (($timeline['items'] ?? []) as $row) {
            if (!empty($row['country'])) {
                $timelineMap[$row['country']] = $row;
            }
        }

        $issueMap = [];
        foreach (($issueLog['items'] ?? []) as $row) {
            if (!empty($row['country'])) {
                $issueMap[$row['country']] = $row;
            }
        }

        $assessmentMap = [];
        foreach (($assessments['items'] ?? []) as $row) {
            if (!empty($row['country'])) {
                $assessmentMap[$row['country']] = $row;
            }
        }

        $installationMap = [];
        foreach (($installations['items'] ?? []) as $row) {
            if (!empty($row['country'])) {
                $installationMap[$row['country']] = $row;
            }
        }

        $postDeploymentMap = [];
        foreach (($postDeployment['items'] ?? []) as $row) {
            if (!empty($row['country'])) {
                $postDeploymentMap[$row['country']] = $row;
            }
        }

        $countryList = $data['countries'] ?? [];
        if ($countryList === null || $countryList === []) {
            $countryList = array_unique(array_merge(
                array_keys($assessmentMap),
                array_keys($installationMap),
                array_keys($postDeploymentMap),
                array_keys($issueMap)
            ));
        }
        sort($countryList, SORT_STRING | SORT_FLAG_CASE);

        $html = [];
        $html[] = '<!doctype html>';
        $html[] = '<html lang="en">';
        $html[] = '<head>';
        $html[] = '<meta charset="utf-8" />';
        $html[] = '<meta name="viewport" content="width=device-width, initial-scale=1" />';
        $html[] = '<title>Presentation Export</title>';
        $html[] = '<style>';
        $html[] = 'body{font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;margin:0;background:#f4f6f9;color:#1b1f24;}';
        $html[] = '.page{max-width:1200px;margin:0 auto;padding:32px;}';
        $html[] = '.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;}';
        $html[] = '.card{background:#fff;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,.08);margin-bottom:24px;padding:20px;}';
        $html[] = '.card h2{margin:0 0 12px;font-size:20px;}';
        $html[] = '.muted{color:#6c7a89;font-size:13px;}';
        $html[] = '.table{width:100%;border-collapse:collapse;font-size:13px;}';
        $html[] = '.table th,.table td{border:1px solid #e5e7eb;padding:6px 8px;vertical-align:top;}';
        $html[] = '.table th{background:#f5f7fb;text-align:left;}';
        $html[] = '.pill{display:inline-block;padding:2px 8px;border-radius:999px;font-size:12px;color:#fff;}';
        $html[] = '.pill.green{background:#198754;}';
        $html[] = '.pill.amber{background:#ffc107;color:#111;}';
        $html[] = '.pill.red{background:#dc3545;}';
        $html[] = '.flag{margin-right:6px;vertical-align:text-bottom;}';
        $html[] = '.section-title{font-weight:700;margin:18px 0 10px;font-size:15px;}';
        $html[] = '.grid{display:grid;gap:16px;}';
        $html[] = '.grid-2{grid-template-columns:repeat(auto-fit,minmax(280px,1fr));}';
        $html[] = '.progress-row{margin-bottom:10px;}';
        $html[] = '.progress-bar{height:8px;border-radius:999px;background:#eef1f4;overflow:hidden;}';
        $html[] = '.progress-bar span{display:block;height:100%;float:left;}';
        $html[] = '.bg-success{background:#198754;}';
        $html[] = '.bg-warning{background:#ffc107;}';
        $html[] = '.bg-secondary{background:#6c757d;}';
        $html[] = '.timeline-row{display:flex;align-items:center;gap:12px;margin-bottom:10px;}';
        $html[] = '.timeline-bar{flex:1;height:14px;border-radius:6px;background:#eef1f4;position:relative;}';
        $html[] = '.timeline-bar .segment{position:absolute;top:1px;bottom:1px;border-radius:6px;}';
        $html[] = '.traffic-light{width:110px;align-self:center;}';
        $html[] = '@media print{body{background:#fff;} .card{box-shadow:none;border:1px solid #e5e7eb;}}';
        $html[] = '</style>';
        $html[] = '</head>';
        $html[] = '<body>';
        $html[] = '<div class="page">';
        $html[] = '<div class="header">';
        $html[] = '<div><h1 style="margin:0;font-size:26px;">Presentation</h1><div class="muted">Generated ' . $generatedAt . '</div></div>';
        if (!empty($assets['logo'])) {
            $html[] = '<img src="' . $assets['logo'] . '" alt="" style="height:48px;" />';
        }
        $html[] = '</div>';

        $html[] = '<div class="card">';
        $html[] = '<h2>Highlights</h2>';
        if ($highlights !== '') {
            $html[] = '<div>' . $highlights . '</div>';
        } else {
            $html[] = '<div class="muted">No highlights yet.</div>';
        }
        $html[] = '</div>';

        $html[] = '<div class="card">';
        $html[] = '<h2>Programme Overview Per Country</h2>';
        if ($overviewItems === []) {
            $html[] = '<div class="muted">No overview data available.</div>';
        } else {
            $html[] = '<table class="table">';
            $html[] = '<thead><tr><th>Country</th><th>Stores</th><th>Assessed</th><th>Ongoing Installations</th><th>Installed</th><th>Sign-off</th><th>RAG</th><th>Comment</th></tr></thead><tbody>';
            foreach ($overviewItems as $row) {
                $country = (string) ($row['country'] ?? '');
                $override = $overviewOverrides[$country] ?? [];
                $ragValue = ($override['rag'] ?? null) !== null && $override['rag'] !== '' ? $override['rag'] : ($row['rag'] ?? '');
                $commentValue = ($override['comment'] ?? null) !== null && $override['comment'] !== '' ? $override['comment'] : ($row['comment'] ?? '');
                $ragClass = $this->normalizeRagValue($ragValue);
                $ragLabel = $ragValue !== '' ? htmlspecialchars((string) $ragValue, ENT_QUOTES) : '—';
                $html[] = '<tr>';
                $html[] = '<td>' . $this->buildCountryFlag($country) . htmlspecialchars($country, ENT_QUOTES) . '</td>';
                $html[] = '<td>' . htmlspecialchars((string) ($row['stores'] ?? '—'), ENT_QUOTES) . '</td>';
                $html[] = '<td>' . htmlspecialchars((string) ($row['assessed'] ?? '—'), ENT_QUOTES) . '</td>';
                $html[] = '<td>' . htmlspecialchars((string) ($row['ongoingInstallations'] ?? '—'), ENT_QUOTES) . '</td>';
                $html[] = '<td>' . htmlspecialchars((string) ($row['storesInstalled'] ?? '—'), ENT_QUOTES) . '</td>';
                $html[] = '<td>' . htmlspecialchars((string) ($row['storeSignoff'] ?? '—'), ENT_QUOTES) . '</td>';
                $html[] = '<td>' . ($ragClass ? '<span class="pill ' . $ragClass . '">' . $ragLabel . '</span>' : $ragLabel) . '</td>';
                $html[] = '<td class="muted">' . htmlspecialchars((string) $commentValue, ENT_QUOTES) . '</td>';
                $html[] = '</tr>';
            }
            $html[] = '</tbody></table>';
        }
        $html[] = '</div>';

        $html[] = '<div class="card">';
        $html[] = '<h2>Status planned assessments and installations</h2>';
        if ($plannedWeekRows === []) {
            $html[] = '<div class="muted">No planned assessments or installations found.</div>';
        } else {
            $html[] = '<table class="table">';
            $html[] = '<thead><tr><th>Country</th><th>Site Name (Site ID)</th><th>Activity</th><th>Start</th><th>End</th><th>Status</th><th>Comment</th></tr></thead><tbody>';
            foreach ($plannedWeekRows as $index => $row) {
                $country = $this->resolveRowField($row, ['country', 'Country']) ?? '—';
                $siteName = $this->cleanSiteName($this->resolveRowField($row, ['site_name', 'siteName', 'Site_Name', 'SiteName']) ?? '');
                $siteId = $this->resolveRowField($row, ['site_id', 'siteId', 'Site_ID', 'SiteID']) ?? '';
                $taskName = $this->resolveRowField($row, ['task_name', 'taskName', 'Task_Name', 'TaskName']) ?? '—';
                $startDate = $this->formatDate($this->resolveRowField($row, ['start_date', 'startDate', 'Start_Date', 'StartDate']));
                $endDate = $this->formatDate($this->resolveRowField($row, ['end_date', 'endDate', 'End_Date', 'EndDate']));
                $status = $this->resolveRowField($row, ['status', 'Status']) ?? '';
                $comment = $this->resolveRowField($row, ['comment', 'Comment']) ?? '';

                $html[] = '<tr>';
                $html[] = '<td>' . $this->buildCountryFlag((string) $country) . htmlspecialchars((string) $country, ENT_QUOTES) . '</td>';
                $html[] = '<td>' . htmlspecialchars((string) ($siteName !== '' ? $siteName : '—'), ENT_QUOTES) . ($siteId ? ' (' . htmlspecialchars((string) $siteId, ENT_QUOTES) . ')' : '') . '</td>';
                $html[] = '<td>' . htmlspecialchars((string) $taskName, ENT_QUOTES) . '</td>';
                $html[] = '<td>' . htmlspecialchars((string) ($startDate ?? '—'), ENT_QUOTES) . '</td>';
                $html[] = '<td>' . htmlspecialchars((string) ($endDate ?? '—'), ENT_QUOTES) . '</td>';
                $html[] = '<td>' . htmlspecialchars((string) ($status !== '' ? $status : '—'), ENT_QUOTES) . '</td>';
                $html[] = '<td class="muted">' . htmlspecialchars((string) ($comment !== '' ? $comment : '—'), ENT_QUOTES) . '</td>';
                $html[] = '</tr>';
            }
            $html[] = '</tbody></table>';
        }
        $html[] = '</div>';

        $html[] = '<div class="card">';
        $html[] = '<h2>Timeline</h2>';
        $timelineItems = $timeline['items'] ?? [];
        if ($timelineItems === []) {
            $html[] = '<div class="muted">No timeline data available.</div>';
        } else {
            $domain = $this->timelineDomain($timelineItems);
            if ($domain === null) {
                $html[] = '<div class="muted">Timeline dates are missing.</div>';
            } else {
                foreach ($timelineItems as $item) {
                    $country = (string) ($item['country'] ?? '');
                    $start = $this->parseDate($item['startDate'] ?? null);
                    $installEnd = $this->parseDate($item['installEndDate'] ?? $item['endDate'] ?? null);
                    $end = $this->parseDate($item['endDate'] ?? null);
                    if (!$start || !$end) {
                        continue;
                    }
                    $left = (($start->getTimestamp() * 1000 - $domain['min']) / $domain['span']) * 100;
                    $installWidth = $installEnd ? max(0.5, (($installEnd->getTimestamp() * 1000 - $start->getTimestamp() * 1000) / $domain['span']) * 100) : 0;
                    $totalWidth = max(0.5, (($end->getTimestamp() * 1000 - $start->getTimestamp() * 1000) / $domain['span']) * 100);
                    $restWidth = max(0, $totalWidth - $installWidth);

                    $html[] = '<div class="timeline-row">';
                    $html[] = '<div style="width:180px;" class="muted">' . $this->buildCountryFlag($country) . htmlspecialchars($country, ENT_QUOTES) . '</div>';
                    $html[] = '<div class="timeline-bar">';
                    $html[] = '<span class="segment" style="left:' . $left . '%;width:' . $installWidth . '%;background:#0f9d88;"></span>';
                    if ($restWidth > 0) {
                        $html[] = '<span class="segment" style="left:' . ($left + $installWidth) . '%;width:' . $restWidth . '%;background:#7fd9c9;"></span>';
                    }
                    $html[] = '</div>';
                    $html[] = '<div class="muted" style="min-width:140px;text-align:right;">' . htmlspecialchars($this->formatDate($end) ?? '—', ENT_QUOTES) . '</div>';
                    $html[] = '</div>';
                }
            }
        }
        $html[] = '</div>';

        $html[] = '<div class="card">';
        $html[] = '<h2>Country Trend</h2>';

        $trendGroups = [
            'green' => [],
            'amber' => [],
            'red' => [],
        ];
        foreach ($overviewItems as $row) {
            $country = (string) ($row['country'] ?? '');
            $override = $trendOverrides[$country] ?? [];
            $ragValue = ($override['rag'] ?? null) !== null && $override['rag'] !== '' ? $override['rag'] : ($row['rag'] ?? '');
            $commentValue = ($override['comment'] ?? null) !== null && $override['comment'] !== '' ? $override['comment'] : ($row['comment'] ?? '');
            $rag = $this->normalizeRagValue($ragValue);
            if ($rag !== '' && isset($trendGroups[$rag])) {
                $trendGroups[$rag][] = [
                    'country' => $country,
                    'stores' => $row['stores'] ?? null,
                    'assessed' => $row['assessed'] ?? null,
                    'ongoingInstallations' => $row['ongoingInstallations'] ?? null,
                    'storesInstalled' => $row['storesInstalled'] ?? null,
                    'comment' => $commentValue,
                ];
            }
        }

        foreach (['green' => 'Green', 'amber' => 'Amber', 'red' => 'Red'] as $ragKey => $ragLabel) {
            $rows = $trendGroups[$ragKey];
            $html[] = '<div class="section-title">' . $ragLabel . '</div>';
            if ($rows === []) {
                $html[] = '<div class="muted">No ' . strtolower($ragLabel) . ' countries available.</div>';
                continue;
            }
            $html[] = '<div class="grid grid-2">';
            $html[] = '<div>';
            $html[] = '<table class="table">';
            $html[] = '<thead><tr><th>Country</th><th>Total</th><th>Assessments</th><th>Ongoing Installation</th><th>Stores Installed</th><th>Comment</th></tr></thead><tbody>';
            foreach ($rows as $row) {
                $country = (string) ($row['country'] ?? '');
                $html[] = '<tr>';
                $html[] = '<td>' . $this->buildCountryFlag($country) . htmlspecialchars($country, ENT_QUOTES) . '</td>';
                $html[] = '<td>' . htmlspecialchars((string) ($row['stores'] ?? '—'), ENT_QUOTES) . '</td>';
                $html[] = '<td>' . htmlspecialchars((string) ($row['assessed'] ?? '—'), ENT_QUOTES) . '</td>';
                $html[] = '<td>' . htmlspecialchars((string) ($row['ongoingInstallations'] ?? '—'), ENT_QUOTES) . '</td>';
                $html[] = '<td>' . htmlspecialchars((string) ($row['storesInstalled'] ?? '—'), ENT_QUOTES) . '</td>';
                $html[] = '<td class="muted">' . htmlspecialchars((string) ($row['comment'] ?? '—'), ENT_QUOTES) . '</td>';
                $html[] = '</tr>';
            }
            $html[] = '</tbody></table>';
            $html[] = '</div>';
            $html[] = '<div style="display:flex;justify-content:center;align-items:center;">';
            if (!empty($trafficLights[$ragKey])) {
                $html[] = '<img class="traffic-light" src="' . $trafficLights[$ragKey] . '" alt="" />';
            }
            $html[] = '</div>';
            $html[] = '</div>';
        }
        $html[] = '</div>';

        $html[] = '<div class="card">';
        $html[] = '<h2>General Issues</h2>';
        $issues = [];
        foreach ($issueMap as $country => $block) {
            foreach (($block['issues'] ?? []) as $issue) {
                $issues[] = array_merge($issue, ['country' => $country]);
            }
        }
        if ($issues === []) {
            $html[] = '<div class="muted">No issues logged.</div>';
        } else {
            $html[] = '<table class="table">';
            $html[] = '<thead><tr><th>Country</th><th>Site Name</th><th>Site ID</th><th>Description</th><th>Priority</th><th>Responsible Party</th><th>Action</th><th>Resolve Date</th></tr></thead><tbody>';
            foreach ($issues as $issue) {
                $country = (string) ($issue['country'] ?? '');
                $html[] = '<tr>';
                $html[] = '<td>' . $this->buildCountryFlag($country) . htmlspecialchars($country, ENT_QUOTES) . '</td>';
                $html[] = '<td>' . htmlspecialchars((string) ($issue['storeName'] ?? '—'), ENT_QUOTES) . '</td>';
                $html[] = '<td>' . htmlspecialchars((string) ($issue['storeId'] ?? '—'), ENT_QUOTES) . '</td>';
                $html[] = '<td>' . htmlspecialchars((string) ($issue['description'] ?? '—'), ENT_QUOTES) . '</td>';
                $html[] = '<td>' . htmlspecialchars((string) ($issue['priority'] ?? '—'), ENT_QUOTES) . '</td>';
                $html[] = '<td>' . htmlspecialchars((string) ($issue['responsibleParty'] ?? '—'), ENT_QUOTES) . '</td>';
                $html[] = '<td>' . htmlspecialchars((string) ($issue['actionRequired'] ?? '—'), ENT_QUOTES) . '</td>';
                $html[] = '<td>' . htmlspecialchars((string) ($issue['resolveDate'] ?? '—'), ENT_QUOTES) . '</td>';
                $html[] = '</tr>';
            }
            $html[] = '</tbody></table>';
        }
        $html[] = '</div>';

        foreach ($countryList as $country) {
            $countryLabel = htmlspecialchars((string) $country, ENT_QUOTES);
            $html[] = '<div class="card">';
            $html[] = '<h2>' . $this->buildCountryFlag((string) $country) . $countryLabel . ' <span class="muted" style="font-size:12px;">' . $presentationDate . '</span></h2>';

            $progressRow = $progressMap[$country]['tasks'] ?? [];
            if ($progressRow !== []) {
                $html[] = '<div class="grid grid-2" style="margin-bottom:16px;">';
                foreach ($progressRow as $task) {
                    $label = htmlspecialchars((string) ($task['label'] ?? ''), ENT_QUOTES);
                    $total = (int) ($task['total'] ?? 0);
                    $donePct = (int) ($task['donePct'] ?? 0);
                    $inProgressPct = (int) ($task['inProgressPct'] ?? 0);
                    $notStartedPct = (int) ($task['notStartedPct'] ?? 0);
                    $html[] = '<div class="progress-row">';
                    $html[] = '<div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px;"><span><strong>' . $label . '</strong></span><span class="muted">' . ($total > 0 ? 'Done ' . $donePct . '% · In progress ' . $inProgressPct . '% · Not started ' . $notStartedPct . '%' : 'No stores') . '</span></div>';
                    $html[] = '<div class="progress-bar">';
                    $html[] = '<span class="bg-success" style="width:' . $donePct . '%"></span>';
                    $html[] = '<span class="bg-warning" style="width:' . $inProgressPct . '%"></span>';
                    $html[] = '<span class="bg-secondary" style="width:' . $notStartedPct . '%"></span>';
                    $html[] = '</div>';
                    $html[] = '</div>';
                }
                $html[] = '</div>';
            }

            $html[] = $this->renderPlanSection('Planned Assessments', $assessments['meta'] ?? [], $assessmentMap[$country]['current'] ?? [], $assessmentMap[$country]['next'] ?? []);
            $html[] = $this->renderPlanSection('Planned Installations', $installations['meta'] ?? [], $installationMap[$country]['current'] ?? [], $installationMap[$country]['next'] ?? []);
            $html[] = $this->renderPlanSection('Post-Deployment & Sign-off', $postDeployment['meta'] ?? [], $postDeploymentMap[$country]['current'] ?? [], $postDeploymentMap[$country]['next'] ?? []);

            $issues = $issueMap[$country]['issues'] ?? [];
            $html[] = '<div class="section-title">Issue Log</div>';
            if ($issues === []) {
                $html[] = '<div class="muted">No issues logged.</div>';
            } else {
                $html[] = '<table class="table">';
                $html[] = '<thead><tr><th>Site Name</th><th>Site ID</th><th>Description</th><th>Priority</th><th>Responsible Party</th><th>Action</th><th>Resolve Date</th></tr></thead><tbody>';
                foreach ($issues as $issue) {
                    $html[] = '<tr>';
                    $html[] = '<td>' . htmlspecialchars((string) ($issue['storeName'] ?? '—'), ENT_QUOTES) . '</td>';
                    $html[] = '<td>' . htmlspecialchars((string) ($issue['storeId'] ?? '—'), ENT_QUOTES) . '</td>';
                    $html[] = '<td>' . htmlspecialchars((string) ($issue['description'] ?? '—'), ENT_QUOTES) . '</td>';
                    $html[] = '<td>' . htmlspecialchars((string) ($issue['priority'] ?? '—'), ENT_QUOTES) . '</td>';
                    $html[] = '<td>' . htmlspecialchars((string) ($issue['responsibleParty'] ?? '—'), ENT_QUOTES) . '</td>';
                    $html[] = '<td>' . htmlspecialchars((string) ($issue['actionRequired'] ?? '—'), ENT_QUOTES) . '</td>';
                    $html[] = '<td>' . htmlspecialchars((string) ($issue['resolveDate'] ?? '—'), ENT_QUOTES) . '</td>';
                    $html[] = '</tr>';
                }
                $html[] = '</tbody></table>';
            }

            $html[] = '</div>';
        }

        $html[] = '</div></body></html>';

        return implode("\n", $html);
    }

    private function renderPlanSection(string $title, array $meta, array $currentRows, array $nextRows): string
    {
        $currentLabel = htmlspecialchars((string) ($meta['currentMonth'] ?? 'Current month'), ENT_QUOTES);
        $nextLabel = htmlspecialchars((string) ($meta['nextMonth'] ?? 'Next month'), ENT_QUOTES);

        $html = [];
        $html[] = '<div class="section-title">' . htmlspecialchars($title, ENT_QUOTES) . '</div>';
        $html[] = '<div class="grid grid-2">';
        $html[] = $this->renderPlanTable($currentLabel, $currentRows);
        $html[] = $this->renderPlanTable($nextLabel, $nextRows);
        $html[] = '</div>';

        return implode("\n", $html);
    }

    private function renderPlanTable(string $label, array $rows): string
    {
        $html = [];
        $html[] = '<div>';
        $html[] = '<div class="muted" style="text-transform:uppercase;font-weight:600;margin-bottom:6px;">' . $label . '</div>';
        if ($rows === []) {
            $html[] = '<div class="muted">No entries.</div>';
            $html[] = '</div>';
            return implode("\n", $html);
        }
        $html[] = '<table class="table">';
        $html[] = '<thead><tr><th>Site Name</th><th>Start</th><th>End</th><th>Confidence</th><th>Status</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $siteName = (string) ($row['siteName'] ?? '—');
            $siteId = (string) ($row['siteId'] ?? '');
            $html[] = '<tr>';
            $html[] = '<td>' . htmlspecialchars($siteName, ENT_QUOTES) . ($siteId !== '' ? ' (' . htmlspecialchars($siteId, ENT_QUOTES) . ')' : '') . '</td>';
            $html[] = '<td>' . htmlspecialchars((string) ($row['startDate'] ?? '—'), ENT_QUOTES) . '</td>';
            $html[] = '<td>' . htmlspecialchars((string) ($row['endDate'] ?? '—'), ENT_QUOTES) . '</td>';
            $confidence = (string) ($row['confidence'] ?? '');
            $status = (string) ($row['status'] ?? '');
            $html[] = '<td>' . ($confidence !== '' ? htmlspecialchars($confidence, ENT_QUOTES) : '—') . '</td>';
            $html[] = '<td>' . ($status !== '' ? htmlspecialchars($status, ENT_QUOTES) : '—') . '</td>';
            $html[] = '</tr>';
        }
        $html[] = '</tbody></table>';
        $html[] = '</div>';

        return implode("\n", $html);
    }

    private function resolveRowField(array $row, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                return $row[$key];
            }
            foreach ($row as $rowKey => $value) {
                if (strcasecmp((string) $rowKey, (string) $key) === 0 && $value !== null && $value !== '') {
                    return $value;
                }
            }
        }
        return null;
    }

    private function cleanSiteName(string $value): string
    {
        return trim(preg_replace('/^IKEAStore\s*-\s*/i', '', $value));
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array{min: float, max: float, span: float}|null
     */
    private function timelineDomain(array $items): ?array
    {
        $min = null;
        $max = null;
        foreach ($items as $item) {
            $start = $this->parseDate($item['startDate'] ?? null);
            $end = $this->parseDate($item['endDate'] ?? null);
            if (!$start || !$end) {
                continue;
            }
            $startMs = $start->getTimestamp() * 1000;
            $endMs = $end->getTimestamp() * 1000;
            $min = $min === null ? $startMs : min($min, $startMs);
            $max = $max === null ? $endMs : max($max, $endMs);
        }
        if ($min === null || $max === null) {
            return null;
        }
        if ($min === $max) {
            $max = $min + 86400000;
        }
        return [
            'min' => (float) $min,
            'max' => (float) $max,
            'span' => (float) ($max - $min),
        ];
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
