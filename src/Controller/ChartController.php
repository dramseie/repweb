<?php
        namespace App\Controller;
        use App\Service\ChartService; use Symfony\Bundle\FrameworkBundle\Controller\AbstractController; use Symfony\Component\HttpFoundation\JsonResponse; use Symfony\Component\Routing\Annotation\Route;
        class ChartController extends AbstractController {
          #[Route('/charts', name:'charts_index')] public function index(): \Symfony\Component\HttpFoundation\Response { return $this->render('charts/index.html.twig'); }
          #[Route('/charts/data', name:'charts_data')] public function data(ChartService $svc): JsonResponse { $u=$this->getUser(); $role=$u?->getRoles()[0]??'ROLE_USER'; return $this->json($svc->getChartData($role)); }
        }
        