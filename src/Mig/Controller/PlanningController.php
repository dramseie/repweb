<?php
namespace App\Mig\Controller;

use App\Mig\Http\ApiResponse;
use App\Mig\Service\FeatureToggle;
use App\Mig\Service\PlanningService;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/mig/planning')]
class PlanningController extends AbstractController
{
    public function __construct(
        private readonly FeatureToggle $toggle,
        private readonly PlanningService $planning,
    ) {
    }

    #[Route('/projects', name: 'mig_planning_projects', methods: ['GET'])]
    public function projects(): JsonResponse
    {
        if (!$this->toggle->mig()) {
            return ApiResponse::err('Migration Planning disabled', 503);
        }

        $data = $this->planning->getPlanningTree();
        return ApiResponse::ok($data);
    }

    #[Route('/overview/{projectId}', name: 'mig_planning_overview', methods: ['GET'])]
    public function overview(int $projectId): JsonResponse
    {
        if (!$this->toggle->mig()) {
            return ApiResponse::err('Migration Planning disabled', 503);
        }

        $data = $this->planning->getManagementOverview($projectId);
        return ApiResponse::ok($data);
    }

    #[Route('/calendars', name: 'mig_planning_calendars_index', methods: ['GET'])]
    public function calendars(): JsonResponse
    {
        if (!$this->toggle->mig()) {
            return ApiResponse::err('Migration Planning disabled', 503);
        }

        return ApiResponse::ok([
            'methods' => $this->planning->methods(),
            'items' => $this->planning->listCalendars(),
        ]);
    }

    #[Route('/calendars', name: 'mig_planning_calendars_create', methods: ['POST'])]
    public function createCalendar(Request $request): JsonResponse
    {
        if (!$this->toggle->mig()) {
            return ApiResponse::err('Migration Planning disabled', 503);
        }

        $payload = $this->decodeJson($request);
        $calendar = $this->planning->createCalendar($payload);
        return ApiResponse::ok(['calendar' => $calendar]);
    }

    #[Route('/calendars/{id}', name: 'mig_planning_calendars_update', methods: ['PATCH'])]
    public function updateCalendar(int $id, Request $request): JsonResponse
    {
        if (!$this->toggle->mig()) {
            return ApiResponse::err('Migration Planning disabled', 503);
        }

        $payload = $this->decodeJson($request);
        $calendar = $this->planning->updateCalendar($id, $payload);
        return ApiResponse::ok(['calendar' => $calendar]);
    }

    #[Route('/calendars/{id}/slots', name: 'mig_planning_calendars_slot_create', methods: ['POST'])]
    public function createSlot(int $id, Request $request): JsonResponse
    {
        if (!$this->toggle->mig()) {
            return ApiResponse::err('Migration Planning disabled', 503);
        }

        $payload = $this->decodeJson($request);
        $calendar = $this->planning->createSlot($id, $payload);
        return ApiResponse::ok(['calendar' => $calendar]);
    }

    #[Route('/slots/{id}', name: 'mig_planning_slots_update', methods: ['PATCH'])]
    public function updateSlot(int $id, Request $request): JsonResponse
    {
        if (!$this->toggle->mig()) {
            return ApiResponse::err('Migration Planning disabled', 503);
        }

        $payload = $this->decodeJson($request);
        $calendar = $this->planning->updateSlot($id, $payload);
        return ApiResponse::ok(['calendar' => $calendar]);
    }

    #[Route('/slots/{id}', name: 'mig_planning_slots_delete', methods: ['DELETE'])]
    public function deleteSlot(int $id): JsonResponse
    {
        if (!$this->toggle->mig()) {
            return ApiResponse::err('Migration Planning disabled', 503);
        }

        $calendar = $this->planning->deleteSlot($id);
        return ApiResponse::ok(['calendar' => $calendar]);
    }

    #[Route('/servers/{id}', name: 'mig_planning_server_update', methods: ['PATCH'])]
    public function updateServer(int $id, Request $request): JsonResponse
    {
        if (!$this->toggle->mig()) {
            return ApiResponse::err('Migration Planning disabled', 503);
        }

        $payload = $this->decodeJson($request);
        $server = $this->planning->updateServer($id, $payload);
        return ApiResponse::ok(['server' => $server]);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(Request $request): array
    {
        $content = trim((string) $request->getContent());
        if ($content === '') {
            return [];
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new BadRequestHttpException('Invalid JSON payload', $e);
        }

        if (!is_array($data)) {
            throw new BadRequestHttpException('JSON payload must be an object or array');
        }

        return $data;
    }
}
