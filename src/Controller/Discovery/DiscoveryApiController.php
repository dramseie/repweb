<?php

namespace App\Controller\Discovery;

use App\Entity\Discovery\DiscoveryApplication;
use App\Entity\Discovery\DiscoveryApplicationResponse;
use App\Entity\Discovery\DiscoveryProject;
use App\Entity\Discovery\DiscoveryWave;
use App\Entity\Discovery\DiscoverySession;
use App\Entity\Discovery\DiscoveryStakeholder;
use App\Service\Discovery\DiscoveryAnswerCloner;
use App\Service\Discovery\DiscoveryMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/discovery')]
class DiscoveryApiController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DiscoveryAnswerCloner $answerCloner,
        private readonly DiscoveryMailer $mailer
    ) {
    }

    #[Route('/projects', methods: ['GET'])]
    public function listProjects(): JsonResponse
    {
        $projects = $this->em->getRepository(DiscoveryProject::class)
            ->createQueryBuilder('p')
            ->addSelect('a', 's', 'm', 'w')
            ->leftJoin('p.applications', 'a')
            ->leftJoin('p.stakeholders', 's')
            ->leftJoin('p.sessions', 'm')
            ->leftJoin('p.waves', 'w')
            ->orderBy('p.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();

        $payload = array_map(fn (DiscoveryProject $p) => $this->normalizeProject($p, true), $projects);
        return $this->json($payload);
    }

    #[Route('/projects', methods: ['POST'])]
    public function createProject(Request $request): JsonResponse
    {
        $data = $this->decodeJson($request);
        if (!isset($data['tenantId'], $data['code'], $data['name'])) {
            return $this->json(['error' => 'tenantId, code and name are required'], 400);
        }

        $project = (new DiscoveryProject())
            ->setTenantId((int) $data['tenantId'])
            ->setCode((string) $data['code'])
            ->setName((string) $data['name']);

        if (isset($data['description'])) {
            $project->setDescription($data['description']);
        }
        if (isset($data['legalEntityCi'])) {
            $project->setLegalEntityCi($data['legalEntityCi']);
        }
        if (isset($data['status'])) {
            $project->setStatus($data['status']);
        }
        if (isset($data['ownerEmail'])) {
            $project->setOwnerEmail($data['ownerEmail']);
        }
        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $project->setMetadata($data['metadata']);
        }

        $this->em->persist($project);
        $this->em->flush();

        return $this->json($this->normalizeProject($project));
    }

    #[Route('/projects/{id<\d+>}', methods: ['GET'])]
    public function getProject(int $id): JsonResponse
    {
        $project = $this->projectOr404($id);
        return $this->json($this->normalizeProject($project));
    }

    #[Route('/projects/{id<\d+>}', methods: ['PATCH'])]
    public function updateProject(int $id, Request $request): JsonResponse
    {
        $project = $this->projectOr404($id);
        $data = $this->decodeJson($request);

        $map = [
            'tenantId' => fn (DiscoveryProject $p, $v) => $p->setTenantId((int) $v),
            'code' => fn (DiscoveryProject $p, $v) => $p->setCode((string) $v),
            'name' => fn (DiscoveryProject $p, $v) => $p->setName((string) $v),
            'description' => fn (DiscoveryProject $p, $v) => $p->setDescription($v),
            'legalEntityCi' => fn (DiscoveryProject $p, $v) => $p->setLegalEntityCi($v),
            'status' => fn (DiscoveryProject $p, $v) => $p->setStatus((string) $v),
            'ownerEmail' => fn (DiscoveryProject $p, $v) => $p->setOwnerEmail($v),
            'metadata' => function (DiscoveryProject $p, $v) {
                if ($v !== null && !is_array($v)) {
                    throw new \InvalidArgumentException('metadata must be an object');
                }
                $p->setMetadata($v);
            },
        ];

        foreach ($map as $key => $setter) {
            if (array_key_exists($key, $data)) {
                $setter($project, $data[$key]);
            }
        }

        $this->em->flush();
        return $this->json($this->normalizeProject($project));
    }

    #[Route('/projects/{id<\d+>}', methods: ['DELETE'])]
    public function deleteProject(int $id): JsonResponse
    {
        $project = $this->projectOr404($id);
        $this->em->remove($project);
        $this->em->flush();
        return $this->json(['ok' => true]);
    }

    #[Route('/projects/{id<\d+>}/applications', methods: ['POST'])]
    public function createApplication(int $id, Request $request): JsonResponse
    {
        $project = $this->projectOr404($id);
        $data = $this->decodeJson($request);

        foreach (['tenantId', 'appCi', 'appName'] as $required) {
            if (!isset($data[$required])) {
                return $this->json(['error' => sprintf('%s is required', $required)], 400);
            }
        }

        $application = (new DiscoveryApplication())
            ->setProject($project)
            ->setTenantId((int) $data['tenantId'])
            ->setAppCi((string) $data['appCi'])
            ->setAppName((string) $data['appName']);

        if (isset($data['environment'])) {
            $application->setEnvironment($data['environment']);
        }
        if (isset($data['status'])) {
            $application->setStatus($data['status']);
        }
        if (isset($data['questionnaireId'])) {
            $application->setQuestionnaireId((int) $data['questionnaireId']);
        }
        if (isset($data['raci']) && is_array($data['raci'])) {
            $application->setRaci($data['raci']);
        }
        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $application->setMetadata($data['metadata']);
        }
        if (array_key_exists('waveId', $data)) {
            $wave = $this->resolveWaveIdForProject($data['waveId'], $project);
            $application->setWave($wave);
        }

        $this->em->persist($application);
        $this->em->flush();

        if ($application->getQuestionnaireId()) {
            $this->answerCloner->ensureResponseExists($application);
            $this->em->flush();
            $this->em->refresh($application);
        }

        return $this->json($this->normalizeApplication($application));
    }

    #[Route('/applications/{id<\d+>}', methods: ['PATCH'])]
    public function updateApplication(int $id, Request $request): JsonResponse
    {
        $application = $this->applicationOr404($id);
        $data = $this->decodeJson($request);

        $map = [
            'tenantId' => fn (DiscoveryApplication $a, $v) => $a->setTenantId((int) $v),
            'appCi' => fn (DiscoveryApplication $a, $v) => $a->setAppCi((string) $v),
            'appName' => fn (DiscoveryApplication $a, $v) => $a->setAppName((string) $v),
            'environment' => fn (DiscoveryApplication $a, $v) => $a->setEnvironment($v),
            'status' => fn (DiscoveryApplication $a, $v) => $a->setStatus((string) $v),
            'questionnaireId' => function (DiscoveryApplication $a, $v) {
                $qid = $v !== null ? (int) $v : null;
                $a->setQuestionnaireId($qid);
            },
            'waveId' => function (DiscoveryApplication $a, $v) {
                if ($v === null || $v === '') {
                    $a->setWave(null);
                    return;
                }
                $wave = $this->waveOr404((int) $v);
                if ($wave->getProject()?->getId() !== $a->getProject()?->getId()) {
                    throw new BadRequestHttpException('Wave does not belong to the application project');
                }
                $a->setWave($wave);
            },
            'raci' => function (DiscoveryApplication $a, $v) {
                if ($v !== null && !is_array($v)) {
                    throw new \InvalidArgumentException('raci must be an object');
                }
                $a->setRaci($v);
            },
            'metadata' => function (DiscoveryApplication $a, $v) {
                if ($v !== null && !is_array($v)) {
                    throw new \InvalidArgumentException('metadata must be an object');
                }
                $a->setMetadata($v);
            },
        ];

        foreach ($map as $key => $setter) {
            if (array_key_exists($key, $data)) {
                $setter($application, $data[$key]);
            }
        }

        $this->em->flush();
        return $this->json($this->normalizeApplication($application));
    }

    #[Route('/applications/{id<\d+>}', methods: ['DELETE'])]
    public function deleteApplication(int $id): JsonResponse
    {
        $application = $this->applicationOr404($id);
        $this->em->remove($application);
        $this->em->flush();
        return $this->json(['ok' => true]);
    }

    #[Route('/projects/{id<\d+>}/waves', methods: ['GET'])]
    public function listWaves(int $id): JsonResponse
    {
        $project = $this->projectOr404($id);
        $waves = $project->getWaves();
        return $this->json(array_map(fn (DiscoveryWave $w) => $this->normalizeWave($w), $waves->toArray()));
    }

    #[Route('/projects/{id<\d+>}/waves', methods: ['POST'])]
    public function createWave(int $id, Request $request): JsonResponse
    {
        $project = $this->projectOr404($id);
        $data = $this->decodeJson($request);
        if (empty($data['name'])) {
            return $this->json(['error' => 'name is required'], 400);
        }

        $wave = (new DiscoveryWave())
            ->setProject($project)
            ->setName((string) $data['name']);

        if (isset($data['code'])) {
            $wave->setCode($data['code']);
        }
        if (isset($data['status'])) {
            $wave->setStatus($data['status']);
        }
        if (isset($data['position'])) {
            $wave->setPosition((int) $data['position']);
        } else {
            $wave->setPosition($project->getWaves()->count());
        }
        if (isset($data['startAt'])) {
            $wave->setStartAt($data['startAt'] ? new \DateTimeImmutable($data['startAt']) : null);
        }
        if (isset($data['endAt'])) {
            $wave->setEndAt($data['endAt'] ? new \DateTimeImmutable($data['endAt']) : null);
        }
        if (isset($data['metadata'])) {
            if ($data['metadata'] !== null && !is_array($data['metadata'])) {
                throw new BadRequestHttpException('metadata must be an object');
            }
            $wave->setMetadata($data['metadata']);
        }

        $this->em->persist($wave);
        $this->em->flush();

        return $this->json($this->normalizeWave($wave));
    }

    #[Route('/waves/{id<\d+>}', methods: ['PATCH'])]
    public function updateWave(int $id, Request $request): JsonResponse
    {
        $wave = $this->waveOr404($id);
        $data = $this->decodeJson($request);

        $map = [
            'name' => fn (DiscoveryWave $w, $v) => $w->setName((string) $v),
            'code' => fn (DiscoveryWave $w, $v) => $w->setCode($v),
            'status' => fn (DiscoveryWave $w, $v) => $w->setStatus((string) $v),
            'position' => fn (DiscoveryWave $w, $v) => $w->setPosition((int) $v),
            'startAt' => function (DiscoveryWave $w, $v) {
                $w->setStartAt($v ? new \DateTimeImmutable($v) : null);
            },
            'endAt' => function (DiscoveryWave $w, $v) {
                $w->setEndAt($v ? new \DateTimeImmutable($v) : null);
            },
            'metadata' => function (DiscoveryWave $w, $v) {
                if ($v !== null && !is_array($v)) {
                    throw new BadRequestHttpException('metadata must be an object');
                }
                $w->setMetadata($v);
            },
        ];

        foreach ($map as $key => $setter) {
            if (array_key_exists($key, $data)) {
                $setter($wave, $data[$key]);
            }
        }

        $this->em->flush();
        return $this->json($this->normalizeWave($wave));
    }

    #[Route('/waves/{id<\d+>}', methods: ['DELETE'])]
    public function deleteWave(int $id): JsonResponse
    {
        $wave = $this->waveOr404($id);
        foreach ($wave->getApplications() as $application) {
            $application->setWave(null);
        }
        $this->em->remove($wave);
        $this->em->flush();
        return $this->json(['ok' => true]);
    }

    #[Route('/projects/{id<\d+>}/stakeholders', methods: ['POST'])]
    public function createStakeholder(int $id, Request $request): JsonResponse
    {
        $project = $this->projectOr404($id);
        $data = $this->decodeJson($request);
        if (empty($data['name'])) {
            return $this->json(['error' => 'name is required'], 400);
        }

        $stakeholder = (new DiscoveryStakeholder())
            ->setProject($project)
            ->setName((string) $data['name']);
        $stakeholder->setEmail($data['email'] ?? null);
        $stakeholder->setRole($data['role'] ?? null);
        $stakeholder->setRaciRole($data['raciRole'] ?? null);
        $stakeholder->setNotes($data['notes'] ?? null);
        if (isset($data['meta']) && is_array($data['meta'])) {
            $stakeholder->setMeta($data['meta']);
        }

        $this->em->persist($stakeholder);
        $this->em->flush();

        return $this->json($this->normalizeStakeholder($stakeholder));
    }

    #[Route('/stakeholders/{id<\d+>}', methods: ['PATCH'])]
    public function updateStakeholder(int $id, Request $request): JsonResponse
    {
        $stakeholder = $this->stakeholderOr404($id);
        $data = $this->decodeJson($request);

        if (array_key_exists('name', $data)) {
            $stakeholder->setName($data['name']);
        }
        if (array_key_exists('email', $data)) {
            $stakeholder->setEmail($data['email']);
        }
        if (array_key_exists('role', $data)) {
            $stakeholder->setRole($data['role']);
        }
        if (array_key_exists('raciRole', $data)) {
            $stakeholder->setRaciRole($data['raciRole']);
        }
        if (array_key_exists('notes', $data)) {
            $stakeholder->setNotes($data['notes']);
        }
        if (array_key_exists('meta', $data)) {
            if ($data['meta'] !== null && !is_array($data['meta'])) {
                throw new \InvalidArgumentException('meta must be an object');
            }
            $stakeholder->setMeta($data['meta']);
        }

        $this->em->flush();
        return $this->json($this->normalizeStakeholder($stakeholder));
    }

    #[Route('/stakeholders/{id<\d+>}', methods: ['DELETE'])]
    public function deleteStakeholder(int $id): JsonResponse
    {
        $stakeholder = $this->stakeholderOr404($id);
        $this->em->remove($stakeholder);
        $this->em->flush();
        return $this->json(['ok' => true]);
    }

    #[Route('/projects/{id<\d+>}/sessions', methods: ['POST'])]
    public function createSession(int $id, Request $request): JsonResponse
    {
        $project = $this->projectOr404($id);
        $data = $this->decodeJson($request);

        if (empty($data['title'])) {
            return $this->json(['error' => 'title is required'], 400);
        }

        $session = (new DiscoverySession())
            ->setProject($project)
            ->setTitle((string) $data['title']);

        $this->hydrateSession($session, $data);
        $this->em->persist($session);
        $this->em->flush();

        if (($data['sendMail'] ?? false) === true) {
            $this->mailer->sendSessionSummary($session);
        }

        return $this->json($this->normalizeSession($session));
    }

    #[Route('/sessions/{id<\d+>}', methods: ['PATCH'])]
    public function updateSession(int $id, Request $request): JsonResponse
    {
        $session = $this->sessionOr404($id);
        $data = $this->decodeJson($request);

        if (array_key_exists('title', $data) && $data['title']) {
            $session->setTitle($data['title']);
        }
        $this->hydrateSession($session, $data);
        $this->em->flush();

        if (($data['sendMail'] ?? false) === true) {
            $this->mailer->sendSessionSummary($session);
        }

        return $this->json($this->normalizeSession($session));
    }

    #[Route('/sessions/{id<\d+>}/send-mail', methods: ['POST'])]
    public function sendSessionMail(int $id): JsonResponse
    {
        $session = $this->sessionOr404($id);
        $this->mailer->sendSessionSummary($session);
        $this->em->flush();
        return $this->json(['ok' => true]);
    }

    #[Route('/applications/{id<\d+>}/clone', methods: ['POST'])]
    public function cloneAnswers(int $id, Request $request): JsonResponse
    {
        $target = $this->applicationOr404($id);
        $data = $this->decodeJson($request);
        if (empty($data['sourceApplicationId'])) {
            return $this->json(['error' => 'sourceApplicationId is required'], 400);
        }
        $source = $this->applicationOr404((int) $data['sourceApplicationId']);
        $report = $this->answerCloner->cloneAnswers($source, $target);
        $this->em->flush();
        return $this->json($report);
    }

    private function hydrateSession(DiscoverySession $session, array $data): void
    {
        if (array_key_exists('heldAt', $data)) {
            $session->setHeldAt(isset($data['heldAt']) ? new \DateTimeImmutable($data['heldAt']) : null);
        }
        if (array_key_exists('summary', $data)) {
            $session->setSummary($data['summary']);
        }
        if (array_key_exists('minutesHtml', $data)) {
            $session->setMinutesHtml($data['minutesHtml']);
        }
        if (array_key_exists('participants', $data)) {
            if ($data['participants'] !== null && !is_array($data['participants'])) {
                throw new \InvalidArgumentException('participants must be an array');
            }
            $session->setParticipants($data['participants']);
        }
        if (array_key_exists('actionItems', $data)) {
            if ($data['actionItems'] !== null && !is_array($data['actionItems'])) {
                throw new \InvalidArgumentException('actionItems must be an array');
            }
            $session->setActionItems($data['actionItems']);
        }
        if (array_key_exists('createdBy', $data)) {
            $session->setCreatedBy($data['createdBy']);
        }
    }

    private function normalizeProject(DiscoveryProject $project, bool $summaryOnly = false): array
    {
        $payload = [
            'id' => $project->getId(),
            'tenantId' => $project->getTenantId(),
            'code' => $project->getCode(),
            'name' => $project->getName(),
            'description' => $project->getDescription(),
            'legalEntityCi' => $project->getLegalEntityCi(),
            'status' => $project->getStatus(),
            'ownerEmail' => $project->getOwnerEmail(),
            'metadata' => $project->getMetadata(),
            'createdAt' => $project->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $project->getUpdatedAt()->format(DATE_ATOM),
        ];

        if (!$summaryOnly) {
            $payload['applications'] = array_map(fn (DiscoveryApplication $app) => $this->normalizeApplication($app), $project->getApplications()->toArray());
            $payload['stakeholders'] = array_map(fn (DiscoveryStakeholder $s) => $this->normalizeStakeholder($s), $project->getStakeholders()->toArray());
            $payload['sessions'] = array_map(fn (DiscoverySession $m) => $this->normalizeSession($m), $project->getSessions()->toArray());
            $payload['waves'] = array_map(fn (DiscoveryWave $w) => $this->normalizeWave($w), $project->getWaves()->toArray());
        } else {
            $payload['applicationCount'] = $project->getApplications()->count();
            $payload['stakeholderCount'] = $project->getStakeholders()->count();
            $payload['sessionCount'] = $project->getSessions()->count();
            $payload['waveCount'] = $project->getWaves()->count();
        }

        return $payload;
    }

    private function normalizeApplication(DiscoveryApplication $application): array
    {
        return [
            'id' => $application->getId(),
            'projectId' => $application->getProject()?->getId(),
            'waveId' => $application->getWave()?->getId(),
            'tenantId' => $application->getTenantId(),
            'appCi' => $application->getAppCi(),
            'appName' => $application->getAppName(),
            'environment' => $application->getEnvironment(),
            'status' => $application->getStatus(),
            'questionnaireId' => $application->getQuestionnaireId(),
            'raci' => $application->getRaci(),
            'metadata' => $application->getMetadata(),
            'createdAt' => $application->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $application->getUpdatedAt()->format(DATE_ATOM),
            'responses' => array_map(fn (DiscoveryApplicationResponse $r) => $this->normalizeResponse($r), $application->getResponses()->toArray()),
        ];
    }

    private function normalizeStakeholder(DiscoveryStakeholder $stakeholder): array
    {
        return [
            'id' => $stakeholder->getId(),
            'projectId' => $stakeholder->getProject()?->getId(),
            'name' => $stakeholder->getName(),
            'email' => $stakeholder->getEmail(),
            'role' => $stakeholder->getRole(),
            'raciRole' => $stakeholder->getRaciRole(),
            'notes' => $stakeholder->getNotes(),
            'meta' => $stakeholder->getMeta(),
            'createdAt' => $stakeholder->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $stakeholder->getUpdatedAt()->format(DATE_ATOM),
        ];
    }

    private function normalizeSession(DiscoverySession $session): array
    {
        return [
            'id' => $session->getId(),
            'projectId' => $session->getProject()?->getId(),
            'title' => $session->getTitle(),
            'heldAt' => $session->getHeldAt()?->format(DATE_ATOM),
            'summary' => $session->getSummary(),
            'minutesHtml' => $session->getMinutesHtml(),
            'participants' => $session->getParticipants(),
            'actionItems' => $session->getActionItems(),
            'mailStatus' => $session->getMailStatus(),
            'mailedAt' => $session->getMailedAt()?->format(DATE_ATOM),
            'mailError' => $session->getMailError(),
            'createdBy' => $session->getCreatedBy(),
            'createdAt' => $session->getCreatedAt()->format(DATE_ATOM),
        ];
    }

    private function normalizeResponse(DiscoveryApplicationResponse $response): array
    {
        return [
            'id' => $response->getId(),
            'applicationId' => $response->getApplication()?->getId(),
            'responseId' => $response->getResponseId(),
            'status' => $response->getStatus(),
            'clonedFromApplicationId' => $response->getClonedFromApplicationId(),
            'snapshot' => $response->getSnapshot(),
            'createdAt' => $response->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $response->getUpdatedAt()->format(DATE_ATOM),
        ];
    }

    private function normalizeWave(DiscoveryWave $wave): array
    {
        return [
            'id' => $wave->getId(),
            'projectId' => $wave->getProject()?->getId(),
            'name' => $wave->getName(),
            'code' => $wave->getCode(),
            'status' => $wave->getStatus(),
            'position' => $wave->getPosition(),
            'startAt' => $wave->getStartAt()?->format(DATE_ATOM),
            'endAt' => $wave->getEndAt()?->format(DATE_ATOM),
            'metadata' => $wave->getMetadata(),
            'createdAt' => $wave->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $wave->getUpdatedAt()->format(DATE_ATOM),
            'applicationCount' => $wave->getApplications()->count(),
        ];
    }

    private function projectOr404(int $id): DiscoveryProject
    {
        $project = $this->em->find(DiscoveryProject::class, $id);
        if (!$project) {
            throw $this->createNotFoundException('Discovery project not found');
        }
        return $project;
    }

    private function applicationOr404(int $id): DiscoveryApplication
    {
        $application = $this->em->find(DiscoveryApplication::class, $id);
        if (!$application) {
            throw $this->createNotFoundException('Discovery application not found');
        }
        return $application;
    }

    private function stakeholderOr404(int $id): DiscoveryStakeholder
    {
        $stakeholder = $this->em->find(DiscoveryStakeholder::class, $id);
        if (!$stakeholder) {
            throw $this->createNotFoundException('Discovery stakeholder not found');
        }
        return $stakeholder;
    }

    private function sessionOr404(int $id): DiscoverySession
    {
        $session = $this->em->find(DiscoverySession::class, $id);
        if (!$session) {
            throw $this->createNotFoundException('Discovery session not found');
        }
        return $session;
    }

    private function decodeJson(Request $request): array
    {
        $data = json_decode($request->getContent() ?: '[]', true);
        if (!is_array($data)) {
            throw new BadRequestHttpException('Invalid JSON payload');
        }
        return $data;
    }

    private function waveOr404(int $id): DiscoveryWave
    {
        $wave = $this->em->find(DiscoveryWave::class, $id);
        if (!$wave) {
            throw $this->createNotFoundException('Discovery wave not found');
        }
        return $wave;
    }

    private function resolveWaveIdForProject($waveId, DiscoveryProject $project): ?DiscoveryWave
    {
        if ($waveId === null || $waveId === '') {
            return null;
        }
        $wave = $this->waveOr404((int) $waveId);
        if ($wave->getProject()?->getId() !== $project->getId()) {
            throw new BadRequestHttpException('Wave does not belong to this project');
        }
        return $wave;
    }
}
