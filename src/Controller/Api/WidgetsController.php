<?php
// src/Controller/Api/WidgetsController.php
namespace App\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/widgets', name: 'api_widgets_')]
class WidgetsController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    /**
     * Catalog of available widgets + default props and preferred sizes
     */
    #[Route('/defs', name: 'defs', methods: ['GET'])]
    public function defs(): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $defs = [
            // 🗺️ Leaflet World Map
            [
                'type'     => 'leaflet',
                'title'    => 'World Map',
                'defaults' => [
                    'apiUrl' => '/api/eav/geo/view',
                    'height' => '560px',
                ],
                'minW' => 4, 'minH' => 5, 'w' => 6, 'h' => 12,
            ],

            // 📈 Plotly
            [
                'type'     => 'plotly',
                'title'    => 'Plotly Chart',
                'defaults' => ['reportId' => 1, 'height' => 360, 'chart' => 'line'],
                'minW' => 4, 'minH' => 6, 'w' => 6, 'h' => 8,
            ],

            // 🗃️ DataTable
            [
                'type'     => 'datatable',
                'title'    => 'DataTable',
                'defaults' => ['reportId' => 1, 'pageLength' => 15],
                'minW' => 4, 'minH' => 5, 'w' => 6, 'h' => 9,
            ],

            // 🔀 Pivot
            [
                'type'     => 'pivot',
                'title'    => 'Pivot Table',
                'defaults' => ['reportId' => 1],
                'minW' => 4, 'minH' => 6, 'w' => 6, 'h' => 9,
            ],

            // 📊 Grafana
            [
                'type'     => 'grafana',
                'title'    => 'Grafana Panel',
                'defaults' => [
                    'src' => 'https://your-grafana.example/d/abcdef/demo?orgId=1&kiosk',
                    'height' => 360,
                ],
                'minW' => 4, 'minH' => 5, 'w' => 6, 'h' => 7,
            ],

            // 🔢 KPI
            [
                'type'     => 'kpi',
                'title'    => 'KPI Tile',
                'defaults' => ['label' => 'New Orders', 'value' => 0, 'sub' => 'today'],
                'minW' => 2, 'minH' => 3, 'w' => 3, 'h' => 4,
            ],

            // ✍️ Markdown
            [
                'type'     => 'markdown',
                'title'    => 'Markdown Note',
                'defaults' => ['md' => "# Note\nAdd your notes here."],
                'minW' => 3, 'minH' => 3, 'w' => 4, 'h' => 5,
            ],
        ];

        // 🧩 NiFi widget (only show if NiFi is configured)
        if (!empty($_ENV['NIFI_BASE_URL'])) {
            $defs[] = [
                'type'        => 'nifi',
                'title'       => 'NiFi Process Groups',
                'description' => 'Live status of NiFi process groups (running, stopped, invalid, disabled, threads, queued).',
                'defaults'    => [
                    'title'      => 'NiFi Process Groups',
                    'refreshSec' => 300,
                ],
                'minW' => 4, 'minH' => 5, 'w' => 6, 'h' => 9,
            ];
        }

        return $this->json($defs);
    }

    #[Route('/layout', name: 'get_layout', methods: ['GET'])]
    public function getLayout(): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        /** @var User $user */
        $user = $this->getUser();

        return $this->json($user->getWidgetLayout() ?? [
            'version' => 1,
            'items'   => [],
            'layouts' => [],
        ]);
    }

    #[Route('/layout', name: 'save_layout', methods: ['POST'])]
    public function saveLayout(Request $req): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($req->getContent(), true);
        if (!is_array($data) || !isset($data['items'], $data['layouts'])) {
            return $this->json(['error' => 'Invalid payload'], 400);
        }

        $payload = [
            'version'   => (int)($data['version'] ?? 1),
            'items'     => array_values($data['items']),
            'layouts'   => $data['layouts'],
            'updatedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];

        $user->setWidgetLayout($payload);
        $this->em->persist($user);
        $this->em->flush();

        return $this->json(['ok' => true]);
    }
}
