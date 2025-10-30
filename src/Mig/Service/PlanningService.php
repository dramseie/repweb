<?php
namespace App\Mig\Service;

use App\Mig\Entity\Calendar;
use App\Mig\Entity\CalendarSlot;
use App\Mig\Entity\Server;
use App\Mig\Repository\CalendarRepository;
use App\Mig\Repository\CalendarSlotRepository;
use App\Mig\Repository\ContainerRepository;
use App\Mig\Repository\ServerRepository;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\TableIdentifier;
use Doctrine\ORM\EntityManagerInterface;
use Throwable;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PlanningService
{
    public const METHODS = ['LiftShift', 'Reinstall', 'P2V', 'V2V', 'vMotion', 'Decomm'];

    public function __construct(
        private readonly Connection $connection,
        private readonly EntityManagerInterface $em,
        private readonly CalendarRepository $calendarRepo,
        private readonly CalendarSlotRepository $slotRepo,
        private readonly ServerRepository $serverRepo,
        private readonly ContainerRepository $containerRepo,
    ) {
        $this->containerHasApplicationColumn = $this->detectColumn('repweb_mig', 'mig_container', 'application_id');
        $this->calendarTablesPresent = $this->detectTable('repweb_mig', 'mig_calendar')
            && $this->detectTable('repweb_mig', 'mig_calendar_slot');
        $this->serverHasStatusColumn = $this->detectColumn('repweb_mig', 'mig_server', 'status');
    }

    private bool $containerHasApplicationColumn;
    private bool $calendarTablesPresent;
    private bool $serverHasStatusColumn;

    private function containerSql(): string
    {
        if ($this->containerHasApplicationColumn) {
            return 'SELECT c.id, c.wave_id, c.application_id, c.name, c.notes,
                    da.app_name AS application_name,
                    da.app_ci AS application_ci,
                    da.environment AS application_environment
             FROM repweb_mig.mig_container c
             LEFT JOIN discovery_application da ON da.id = c.application_id
             ORDER BY c.name';
        }

        return 'SELECT c.id, c.wave_id, c.name, c.notes
             FROM repweb_mig.mig_container c
             ORDER BY c.name';
    }

    private function serverSql(): string
    {
        $baseSelect = 'SELECT s.id, s.container_id, s.hostname, s.application, s.method,
        s.calendar_id, s.slot_id, s.scheduled_start, s.scheduled_end';

        if ($this->serverHasStatusColumn) {
            $baseSelect .= ', s.status';
        }

        if ($this->calendarTablesPresent) {
            return $baseSelect . ',
            cal.name AS calendar_name, cal.method AS calendar_method,
            slot.label AS slot_label, slot.starts_at AS slot_start, slot.ends_at AS slot_end
         FROM repweb_mig.mig_server s
         LEFT JOIN repweb_mig.mig_calendar cal ON cal.id = s.calendar_id
         LEFT JOIN repweb_mig.mig_calendar_slot slot ON slot.id = s.slot_id
         ORDER BY s.hostname';
        }

        return $baseSelect . '
     FROM repweb_mig.mig_server s
     ORDER BY s.hostname';
    }

    public function getPlanningTree(): array
    {
        $projects = $this->connection->fetchAllAssociative(
            'SELECT p.id, p.tenant_id, p.name, p.status, p.created_at
             FROM repweb_mig.mig_project p
             ORDER BY p.name'
        );

        $waves = $this->connection->fetchAllAssociative(
            'SELECT w.id, w.project_id, w.name, w.status, w.start_at, w.end_at
             FROM repweb_mig.mig_wave2 w
             ORDER BY w.start_at IS NULL, w.start_at, w.id'
        );

        $containers = $this->connection->fetchAllAssociative(
            $this->containerSql()
        );

        $servers = $this->connection->fetchAllAssociative(
            $this->serverSql()
        );

        $projectMap = [];
        foreach ($projects as $row) {
            $id = (int) $row['id'];
            $projectMap[$id] = [
                'id' => $id,
                'tenantId' => (int) $row['tenant_id'],
                'name' => $row['name'],
                'status' => $row['status'],
                'createdAt' => $this->formatDate($row['created_at']),
                'waves' => [],
            ];
        }

        $wavesByProject = [];
        foreach ($waves as $row) {
            $wave = [
                'id' => (int) $row['id'],
                'projectId' => (int) $row['project_id'],
                'name' => $row['name'],
                'status' => $row['status'],
                'startAt' => $this->formatDate($row['start_at']),
                'endAt' => $this->formatDate($row['end_at']),
                'containers' => [],
            ];
            $wavesByProject[$wave['projectId']][] = $wave;
        }

        $containersByWave = [];
        foreach ($containers as $row) {
            $applicationId = null;
            if ($this->containerHasApplicationColumn && array_key_exists('application_id', $row)) {
                $applicationId = $row['application_id'] !== null ? (int) $row['application_id'] : null;
            }

            $container = [
                'id' => (int) $row['id'],
                'waveId' => (int) $row['wave_id'],
                'applicationId' => $applicationId,
                'name' => $row['name'],
                'notes' => $row['notes'],
                'application' => ($this->containerHasApplicationColumn && $applicationId !== null) ? [
                    'id' => $applicationId,
                    'name' => $row['application_name'] ?? null,
                    'ci' => $row['application_ci'] ?? null,
                    'environment' => $row['application_environment'] ?? null,
                ] : null,
                'servers' => [],
            ];
            $containersByWave[$container['waveId']][] = $container;
        }

        $serversByContainer = [];
        $methodTotals = [];
        foreach (self::METHODS as $methodKey) {
            $methodTotals[$methodKey] = 0;
        }
        $totalServers = 0;

        foreach ($servers as $row) {
            $server = $this->normalizeServerRow($row);
            $serversByContainer[$server['containerId']][] = $server;
            $totalServers++;
            if (isset($methodTotals[$server['method']])) {
                $methodTotals[$server['method']]++;
            }
        }

        foreach ($projectMap as &$project) {
            $waveList = $wavesByProject[$project['id']] ?? [];
            foreach ($waveList as &$wave) {
                $containerList = $containersByWave[$wave['id']] ?? [];
                foreach ($containerList as &$container) {
                    $container['servers'] = $serversByContainer[$container['id']] ?? [];
                }
                $wave['containers'] = array_values($containerList);
            }
            $project['waves'] = array_values($waveList);
        }
        unset($project, $wave, $container);

        return [
            'projects' => array_values($projectMap),
            'methods' => self::METHODS,
            'summary' => [
                'totalServers' => $totalServers,
                'byMethod' => $methodTotals,
            ],
        ];
    }

    public function listCalendars(): array
    {
        return $this->gatherCalendars();
    }

    public function createCalendar(array $payload): array
    {
        if (!$this->calendarTablesPresent) {
            throw new BadRequestHttpException('Calendar scheduling is not available until the database migration runs.');
        }

        $method = (string) ($payload['method'] ?? '');
        if (!in_array($method, self::METHODS, true)) {
            throw new BadRequestHttpException('Invalid migration method');
        }
        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            throw new BadRequestHttpException('Calendar name is required');
        }

        $calendar = new Calendar();
        $calendar->setMethod($method);
        $calendar->setName($name);
        $calendar->setDescription($payload['description'] ?? null);
        $calendar->setTimezone($payload['timezone'] ?? 'UTC');
        $calendar->setActiveFrom($this->parseDateTime($payload['activeFrom'] ?? null));
        $calendar->setActiveTo($this->parseDateTime($payload['activeTo'] ?? null));
        $calendar->setCreatedAt(new DateTimeImmutable());
        $calendar->setUpdatedAt(new DateTimeImmutable());

        $this->calendarRepo->save($calendar);

        $data = $this->gatherCalendars((int) $calendar->getId());
        return $data[0] ?? [];
    }

    public function updateCalendar(int $calendarId, array $payload): array
    {
        if (!$this->calendarTablesPresent) {
            throw new BadRequestHttpException('Calendar scheduling is not available until the database migration runs.');
        }

        /** @var Calendar|null $calendar */
        $calendar = $this->calendarRepo->find((string) $calendarId);
        if (!$calendar) {
            throw new NotFoundHttpException('Calendar not found');
        }

        if (array_key_exists('name', $payload)) {
            $name = trim((string) $payload['name']);
            if ($name === '') {
                throw new BadRequestHttpException('Calendar name cannot be empty');
            }
            $calendar->setName($name);
        }
        if (array_key_exists('description', $payload)) {
            $calendar->setDescription($payload['description'] !== null ? (string) $payload['description'] : null);
        }
        if (array_key_exists('timezone', $payload)) {
            $calendar->setTimezone((string) $payload['timezone']);
        }
        if (array_key_exists('activeFrom', $payload)) {
            $calendar->setActiveFrom($this->parseDateTime($payload['activeFrom']));
        }
        if (array_key_exists('activeTo', $payload)) {
            $calendar->setActiveTo($this->parseDateTime($payload['activeTo']));
        }
        if (array_key_exists('method', $payload)) {
            $method = (string) $payload['method'];
            if (!in_array($method, self::METHODS, true)) {
                throw new BadRequestHttpException('Invalid migration method');
            }
            $calendar->setMethod($method);
        }

        $calendar->setUpdatedAt(new DateTimeImmutable());
        $this->calendarRepo->save($calendar);

        $data = $this->gatherCalendars((int) $calendar->getId());
        return $data[0] ?? [];
    }

    public function createSlot(int $calendarId, array $payload): array
    {
        if (!$this->calendarTablesPresent) {
            throw new BadRequestHttpException('Calendar scheduling is not available until the database migration runs.');
        }

        /** @var Calendar|null $calendar */
        $calendar = $this->calendarRepo->find((string) $calendarId);
        if (!$calendar) {
            throw new NotFoundHttpException('Calendar not found');
        }

        $label = trim((string) ($payload['label'] ?? ''));
        if ($label === '') {
            throw new BadRequestHttpException('Slot label is required');
        }

        $slot = new CalendarSlot();
        $slot->setCalendar($calendar);
        $slot->setLabel($label);
        $slot->setStartsAt($this->requireDateTime($payload['startsAt'] ?? null, 'startsAt'));
        $slot->setEndsAt($this->requireDateTime($payload['endsAt'] ?? null, 'endsAt'));
        $slot->setCapacity((int) ($payload['capacity'] ?? 1));
        $slot->setNotes(array_key_exists('notes', $payload) && $payload['notes'] !== null ? (string) $payload['notes'] : null);

        $this->slotRepo->save($slot);
        $calendar->setUpdatedAt(new DateTimeImmutable());
        $this->calendarRepo->save($calendar);

        $data = $this->gatherCalendars((int) $calendar->getId());
        return $data[0] ?? [];
    }

    public function updateSlot(int $slotId, array $payload): array
    {
        if (!$this->calendarTablesPresent) {
            throw new BadRequestHttpException('Calendar scheduling is not available until the database migration runs.');
        }

        /** @var CalendarSlot|null $slot */
        $slot = $this->slotRepo->find((string) $slotId);
        if (!$slot) {
            throw new NotFoundHttpException('Slot not found');
        }

        if (array_key_exists('label', $payload)) {
            $label = trim((string) $payload['label']);
            if ($label === '') {
                throw new BadRequestHttpException('Slot label cannot be empty');
            }
            $slot->setLabel($label);
        }
        if (array_key_exists('startsAt', $payload)) {
            $slot->setStartsAt($this->requireDateTime($payload['startsAt'], 'startsAt'));
        }
        if (array_key_exists('endsAt', $payload)) {
            $slot->setEndsAt($this->requireDateTime($payload['endsAt'], 'endsAt'));
        }
        if (array_key_exists('capacity', $payload)) {
            $slot->setCapacity((int) $payload['capacity']);
        }
        if (array_key_exists('notes', $payload)) {
            $slot->setNotes($payload['notes'] !== null ? (string) $payload['notes'] : null);
        }

        $calendar = $slot->getCalendar();
        $calendar->setUpdatedAt(new DateTimeImmutable());

        $this->slotRepo->save($slot);
        $this->calendarRepo->save($calendar);

        $data = $this->gatherCalendars((int) $calendar->getId());
        return $data[0] ?? [];
    }

    public function deleteSlot(int $slotId): array
    {
        if (!$this->calendarTablesPresent) {
            throw new BadRequestHttpException('Calendar scheduling is not available until the database migration runs.');
        }

        /** @var CalendarSlot|null $slot */
        $slot = $this->slotRepo->find((string) $slotId);
        if (!$slot) {
            throw new NotFoundHttpException('Slot not found');
        }
        $calendar = $slot->getCalendar();
        $calendarId = (int) $calendar->getId();

        $this->slotRepo->remove($slot);
        $calendar->setUpdatedAt(new DateTimeImmutable());
        $this->calendarRepo->save($calendar);

        $data = $this->gatherCalendars($calendarId);
        return $data[0] ?? [];
    }

    public function updateServer(int $serverId, array $payload): array
    {
        /** @var Server|null $server */
        $server = $this->serverRepo->find((string) $serverId);
        if (!$server) {
            throw new NotFoundHttpException('Server not found');
        }

        $dirty = false;

        if (array_key_exists('containerId', $payload)) {
            $containerId = $payload['containerId'];
            if ($containerId === null || $containerId === '') {
                throw new BadRequestHttpException('containerId cannot be null');
            }
            $container = $this->containerRepo->find((string) $containerId);
            if (!$container) {
                throw new NotFoundHttpException('Container not found');
            }
            $server->setContainerId((string) $containerId);
            $dirty = true;
        }

        if (array_key_exists('method', $payload)) {
            $method = (string) $payload['method'];
            if (!in_array($method, self::METHODS, true)) {
                throw new BadRequestHttpException('Invalid migration method');
            }
            $server->setMethod($method);
            $dirty = true;
        }

        $calendarUpdated = false;

        if (array_key_exists('slotId', $payload)) {
            if (!$this->calendarTablesPresent) {
                throw new BadRequestHttpException('Calendar scheduling is not available until the database migration runs.');
            }

            $slotId = $payload['slotId'];
            if ($slotId === null || $slotId === '') {
                $server->setSlotId(null);
                $server->setScheduledStart(null);
                $server->setScheduledEnd(null);
                $calendarUpdated = true;
                $dirty = true;
            } else {
                /** @var CalendarSlot|null $slot */
                $slot = $this->slotRepo->find((string) $slotId);
                if (!$slot) {
                    throw new NotFoundHttpException('Slot not found');
                }
                $calendar = $slot->getCalendar();
                if ($calendar->getMethod() !== $server->getMethod()) {
                    throw new BadRequestHttpException('Slot method does not match server method');
                }
                $server->setCalendarId((string) $calendar->getId());
                $server->setSlotId((string) $slot->getId());
                $server->setScheduledStart($slot->getStartsAt());
                $server->setScheduledEnd($slot->getEndsAt());
                $calendarUpdated = true;
                $dirty = true;
            }
        }

        if (!$calendarUpdated && array_key_exists('calendarId', $payload)) {
            if (!$this->calendarTablesPresent) {
                throw new BadRequestHttpException('Calendar scheduling is not available until the database migration runs.');
            }

            $calendarId = $payload['calendarId'];
            if ($calendarId === null || $calendarId === '') {
                $server->setCalendarId(null);
                $server->setSlotId(null);
                $server->setScheduledStart(null);
                $server->setScheduledEnd(null);
                $dirty = true;
            } else {
                /** @var Calendar|null $calendar */
                $calendar = $this->calendarRepo->find((string) $calendarId);
                if (!$calendar) {
                    throw new NotFoundHttpException('Calendar not found');
                }
                if ($calendar->getMethod() !== $server->getMethod()) {
                    throw new BadRequestHttpException('Calendar method does not match server method');
                }
                $server->setCalendarId((string) $calendar->getId());
                $server->setSlotId(null);
                $server->setScheduledStart(null);
                $server->setScheduledEnd(null);
                $dirty = true;
            }
        }

        if (array_key_exists('method', $payload) && $server->getCalendarId() !== null) {
            /** @var Calendar|null $calendar */
            $calendar = $this->calendarRepo->find($server->getCalendarId());
            if ($calendar && $calendar->getMethod() !== $server->getMethod()) {
                $server->setCalendarId(null);
                $server->setSlotId(null);
                $server->setScheduledStart(null);
                $server->setScheduledEnd(null);
                $dirty = true;
            }
        }

        if ($dirty) {
            $this->em->persist($server);
            $this->em->flush();
        }

        return $this->fetchServerData((int) $server->getId());
    }

    public function methods(): array
    {
        return self::METHODS;
    }

    public function getManagementOverview(int $projectId): array
    {
        $tree = $this->getPlanningTree();

        $project = null;
        foreach ($tree['projects'] as $item) {
            if ($item['id'] === $projectId) {
                $project = $item;
                break;
            }
        }

        if ($project === null) {
            throw new NotFoundHttpException('Project not found');
        }

        $now = new DateTimeImmutable('now');
        $timelineStart = null;
        $timelineEnd = null;
        $projectCreated = $this->tryParseDate($project['createdAt'] ?? null);

        $summary = [
            'totalWaves' => count($project['waves']),
            'totalContainers' => 0,
            'totalServers' => 0,
            'totalApplications' => 0,
            'progress' => [
                'open' => 0,
                'migrated' => 0,
                'failed' => 0,
                'total' => 0,
                'completionPercent' => 0.0,
            ],
            'methodBreakdown' => [],
        ];

        $timelineWaves = [];
        $applicationStats = [];

        foreach ($project['waves'] as $wave) {
            $waveStart = $this->tryParseDate($wave['startAt'] ?? null);
            $waveEnd = $this->tryParseDate($wave['endAt'] ?? null);

            if ($waveStart && ($timelineStart === null || $waveStart < $timelineStart)) {
                $timelineStart = $waveStart;
            }
            if ($waveEnd && ($timelineEnd === null || $waveEnd > $timelineEnd)) {
                $timelineEnd = $waveEnd;
            }

            $waveProgress = ['open' => 0, 'migrated' => 0, 'failed' => 0];
            $waveMethodBreakdown = [];

            foreach ($wave['containers'] as $container) {
                $summary['totalContainers']++;

                $applicationData = is_array($container['application'] ?? null) ? $container['application'] : null;
                $applicationId = $applicationData['id'] ?? $container['applicationId'] ?? null;
                $applicationKey = $applicationId !== null ? 'app:' . $applicationId : 'container:' . $container['id'];
                if (!isset($applicationStats[$applicationKey])) {
                    $applicationStats[$applicationKey] = [
                        'key' => $applicationKey,
                        'applicationId' => $applicationId !== null ? (int) $applicationId : null,
                        'name' => $applicationData['name'] ?? $container['name'],
                        'ci' => $applicationData['ci'] ?? null,
                        'environment' => $applicationData['environment'] ?? null,
                        'containers' => [],
                        'serverCount' => 0,
                        'open' => 0,
                        'migrated' => 0,
                        'failed' => 0,
                    ];
                }
                $applicationStats[$applicationKey]['containers'][(int) $container['id']] = $container['name'];

                foreach ($container['servers'] as $server) {
                    $summary['totalServers']++;
                    $applicationStats[$applicationKey]['serverCount']++;

                    $method = $server['method'] ?? 'Unknown';
                    if ($method === '') {
                        $method = 'Unknown';
                    }
                    $waveMethodBreakdown[$method] = ($waveMethodBreakdown[$method] ?? 0) + 1;
                    $summary['methodBreakdown'][$method] = ($summary['methodBreakdown'][$method] ?? 0) + 1;

                    $status = $this->classifyServerStatus($server, $now);
                    if (!isset($waveProgress[$status])) {
                        $waveProgress[$status] = 0;
                    }
                    $waveProgress[$status]++;
                    if (!isset($summary['progress'][$status])) {
                        $summary['progress'][$status] = 0;
                    }
                    $summary['progress'][$status]++;
                    if (!isset($applicationStats[$applicationKey][$status])) {
                        $applicationStats[$applicationKey][$status] = 0;
                    }
                    $applicationStats[$applicationKey][$status]++;
                }
            }

            arsort($waveMethodBreakdown);
            $waveTotal = $waveProgress['open'] + $waveProgress['migrated'] + $waveProgress['failed'];
            $waveProgress['total'] = $waveTotal;
            $waveProgress['completionPercent'] = $waveTotal > 0 ? $this->percent($waveProgress['migrated'], $waveTotal) : 0.0;

            $timelineWaves[] = [
                'id' => (int) $wave['id'],
                'name' => $wave['name'],
                'status' => $wave['status'],
                'startAt' => $wave['startAt'],
                'endAt' => $wave['endAt'],
                'durationDays' => $this->computeDurationDays($waveStart, $waveEnd),
                'progress' => $waveProgress,
                'methodBreakdown' => $waveMethodBreakdown,
            ];
        }

        if ($timelineStart === null) {
            $timelineStart = $projectCreated;
        }
        if ($timelineEnd === null) {
            $timelineEnd = $timelineStart;
        }

        $summary['progress']['total'] = $summary['progress']['open'] + $summary['progress']['migrated'] + $summary['progress']['failed'];
        $summary['progress']['completionPercent'] = $summary['progress']['total'] > 0
            ? $this->percent($summary['progress']['migrated'], $summary['progress']['total'])
            : 0.0;

        arsort($summary['methodBreakdown']);

        $applications = [];
        foreach ($applicationStats as $stat) {
            $total = $stat['serverCount'] ?? 0;
            $open = $stat['open'] ?? 0;
            $migrated = $stat['migrated'] ?? 0;
            $failed = $stat['failed'] ?? 0;
            $containers = array_map(
                static fn ($id, string $name): array => ['id' => (int) $id, 'name' => $name],
                array_keys($stat['containers']),
                array_values($stat['containers'])
            );

            $applications[] = [
                'key' => $stat['key'],
                'applicationId' => $stat['applicationId'],
                'name' => $stat['name'],
                'ci' => $stat['ci'],
                'environment' => $stat['environment'],
                'containerCount' => count($stat['containers']),
                'containers' => $containers,
                'serverCount' => $total,
                'open' => $open,
                'migrated' => $migrated,
                'failed' => $failed,
                'completionPercent' => $total > 0 ? $this->percent($migrated, $total) : 0.0,
            ];
        }

        usort($applications, static function (array $a, array $b): int {
            if ($a['completionPercent'] === $b['completionPercent']) {
                if ($a['serverCount'] === $b['serverCount']) {
                    return strcmp((string) $a['name'], (string) $b['name']);
                }
                return $b['serverCount'] <=> $a['serverCount'];
            }

            return $b['completionPercent'] <=> $a['completionPercent'];
        });

        $summary['totalApplications'] = count($applications);

        return [
            'project' => [
                'id' => (int) $project['id'],
                'name' => $project['name'],
                'status' => $project['status'],
                'createdAt' => $project['createdAt'],
            ],
            'timeline' => [
                'start' => $timelineStart ? $timelineStart->format(DateTimeInterface::ATOM) : null,
                'end' => $timelineEnd ? $timelineEnd->format(DateTimeInterface::ATOM) : null,
                'durationDays' => $this->computeDurationDays($timelineStart, $timelineEnd),
                'waves' => $timelineWaves,
            ],
            'summary' => $summary,
            'applications' => $applications,
        ];
    }

    /**
     * @param array<string, mixed> $server
     */
    private function classifyServerStatus(array $server, DateTimeImmutable $reference): string
    {
        $rawStatus = $server['status'] ?? null;
        if (is_string($rawStatus) && $rawStatus !== '') {
            $normalized = strtolower(trim($rawStatus));
            if (in_array($normalized, ['failed', 'error', 'rollback', 'rolledback', 'rolled_back', 'cancelled'], true)) {
                return 'failed';
            }
            if (in_array($normalized, ['migrated', 'completed', 'done', 'validated', 'success', 'successful'], true)) {
                return 'migrated';
            }
        }

        $scheduledEnd = $server['scheduledEnd'] ?? null;
        if (is_string($scheduledEnd) && $scheduledEnd !== '') {
            $end = $this->tryParseDate($scheduledEnd);
            if ($end && $end <= $reference) {
                return 'migrated';
            }
        }

        return 'open';
    }

    private function tryParseDate(mixed $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }
        try {
            return new DateTimeImmutable((string) $value);
        } catch (Throwable) {
            return null;
        }
    }

    private function computeDurationDays(?DateTimeImmutable $start, ?DateTimeImmutable $end): ?int
    {
        if (!$start || !$end) {
            return null;
        }
        if ($end < $start) {
            return null;
        }
        $seconds = $end->getTimestamp() - $start->getTimestamp();
        $days = (int) ceil($seconds / 86400);
        return $days > 0 ? $days : 1;
    }

    private function percent(int $part, int $total): float
    {
        if ($total <= 0) {
            return 0.0;
        }

        return round(($part / $total) * 100, 1);
    }

    private function gatherCalendars(?int $calendarId = null): array
    {
        if (!$this->calendarTablesPresent) {
            return [];
        }

        $params = [];
        $where = '';
        if ($calendarId !== null) {
            $where = 'WHERE c.id = :id';
            $params['id'] = $calendarId;
        }

        $calendars = $this->connection->fetchAllAssociative(
            "SELECT c.id, c.method, c.name, c.description, c.timezone, c.active_from, c.active_to, c.created_at, c.updated_at
             FROM repweb_mig.mig_calendar c
             $where
             ORDER BY c.method, c.name",
            $params
        );

        if (!$calendars) {
            return [];
        }

        $ids = array_map('intval', array_column($calendars, 'id'));
        $slots = [];
        if ($ids) {
            $slotRows = $this->connection->fetchAllAssociative(
                'SELECT s.id, s.calendar_id, s.label, s.starts_at, s.ends_at, s.capacity, s.notes
                 FROM repweb_mig.mig_calendar_slot s
                 WHERE s.calendar_id IN (?)
                 ORDER BY s.starts_at, s.id',
                [$ids],
                [ArrayParameterType::INTEGER]
            );
            foreach ($slotRows as $slotRow) {
                $calendarKey = (int) $slotRow['calendar_id'];
                $slots[$calendarKey][] = [
                    'id' => (int) $slotRow['id'],
                    'calendarId' => $calendarKey,
                    'label' => $slotRow['label'],
                    'startsAt' => $this->formatDate($slotRow['starts_at']),
                    'endsAt' => $this->formatDate($slotRow['ends_at']),
                    'capacity' => (int) $slotRow['capacity'],
                    'notes' => $slotRow['notes'],
                ];
            }
        }

        $result = [];
        foreach ($calendars as $row) {
            $id = (int) $row['id'];
            $result[] = [
                'id' => $id,
                'method' => $row['method'],
                'name' => $row['name'],
                'description' => $row['description'],
                'timezone' => $row['timezone'],
                'activeFrom' => $this->formatDate($row['active_from']),
                'activeTo' => $this->formatDate($row['active_to']),
                'createdAt' => $this->formatDate($row['created_at']),
                'updatedAt' => $this->formatDate($row['updated_at']),
                'slots' => $slots[$id] ?? [],
            ];
        }

        return $result;
    }

    private function fetchServerData(int $serverId): array
    {
        $sql = $this->calendarTablesPresent
            ? 'SELECT s.id, s.container_id, s.hostname, s.application, s.method,
                    s.calendar_id, s.slot_id, s.scheduled_start, s.scheduled_end,
                    cal.name AS calendar_name, cal.method AS calendar_method,
                    slot.label AS slot_label, slot.starts_at AS slot_start, slot.ends_at AS slot_end
             FROM repweb_mig.mig_server s
             LEFT JOIN repweb_mig.mig_calendar cal ON cal.id = s.calendar_id
             LEFT JOIN repweb_mig.mig_calendar_slot slot ON slot.id = s.slot_id
             WHERE s.id = :id'
            : 'SELECT s.id, s.container_id, s.hostname, s.application, s.method,
                    s.calendar_id, s.slot_id, s.scheduled_start, s.scheduled_end
             FROM repweb_mig.mig_server s
             WHERE s.id = :id';

        $row = $this->connection->fetchAssociative(
            $sql,
            ['id' => $serverId]
        );
        if (!$row) {
            throw new NotFoundHttpException('Server not found');
        }
        return $this->normalizeServerRow($row);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeServerRow(array $row): array
    {
        $calendarId = $row['calendar_id'] !== null ? (int) $row['calendar_id'] : null;
        $slotId = $row['slot_id'] !== null ? (int) $row['slot_id'] : null;

        return [
            'id' => (int) $row['id'],
            'containerId' => (int) $row['container_id'],
            'hostname' => $row['hostname'],
            'application' => $row['application'],
            'method' => $row['method'],
            'calendarId' => $calendarId,
            'slotId' => $slotId,
            'status' => array_key_exists('status', $row) ? $row['status'] : null,
            'calendar' => ($calendarId !== null && array_key_exists('calendar_name', $row)) ? [
                'id' => $calendarId,
                'name' => $row['calendar_name'],
                'method' => $row['calendar_method'] ?? null,
            ] : null,
            'slot' => ($slotId !== null && array_key_exists('slot_label', $row)) ? [
                'id' => $slotId,
                'label' => $row['slot_label'],
                'startsAt' => $this->formatDate($row['slot_start'] ?? null),
                'endsAt' => $this->formatDate($row['slot_end'] ?? null),
            ] : null,
            'scheduledStart' => $this->formatDate($row['scheduled_start']),
            'scheduledEnd' => $this->formatDate($row['scheduled_end']),
        ];
    }

    private function formatDate(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }
        try {
            return (new DateTimeImmutable((string) $value))->format(DateTimeInterface::ATOM);
        } catch (\Exception) {
            return null;
        }
    }

    private function parseDateTime(mixed $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return new DateTimeImmutable((string) $value);
        } catch (\Exception) {
            throw new BadRequestHttpException('Invalid date time value');
        }
    }

    private function requireDateTime(mixed $value, string $field): DateTimeImmutable
    {
        if ($value === null || $value === '') {
            throw new BadRequestHttpException(sprintf('%s is required', $field));
        }
        try {
            return new DateTimeImmutable((string) $value);
        } catch (\Exception) {
            throw new BadRequestHttpException(sprintf('Invalid %s value', $field));
        }
    }

    private function detectColumn(string $schema, string $table, string $column): bool
    {
        try {
            $schemaManager = method_exists($this->connection, 'createSchemaManager')
                ? $this->connection->createSchemaManager()
                : $this->connection->getSchemaManager();

            $tableIdentifier = new TableIdentifier($table, $schema);
            $tableDetails = $schemaManager->introspectTable($tableIdentifier);
            return $tableDetails->hasColumn($column);
        } catch (Throwable) {
            return false;
        }
    }

    private function detectTable(string $schema, string $table): bool
    {
        try {
            $count = $this->connection->fetchOne(
                'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = :schema AND table_name = :table',
                ['schema' => $schema, 'table' => $table]
            );
            return (int) $count > 0;
        } catch (Throwable) {
            return false;
        }
    }
}
