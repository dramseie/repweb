<?php

namespace App\Controller;

use App\Entity\ShareLink;
use App\Service\ExportService; // see note below
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ShareLinkController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em, private ExportService $export) {}

    #[Route('/s/{token}', name: 'share_link', methods: ['GET'])]
    public function resolve(string $token): Response
    {
        /** @var ShareLink|null $link */
        $link = $this->em->getRepository(ShareLink::class)->findOneBy(['token' => $token]);
        if (!$link) return new Response('Invalid token', 404);

        if ($link->getExpiresAt() && $link->getExpiresAt() < new \DateTimeImmutable()) {
            return new Response('Link expired', 410);
        }

        // Private → require login and redirect to your DataTables page (adjust path if needed)
        if (!$link->isPublic()) {
            $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');
            if ($link->getResourceType() === 'report_file') {
                // 👇 update this to your actual DataTables route
                return new RedirectResponse('/datatables/' . $link->getResourceId());
            }
            return new Response('Unsupported private resource', 400);
        }

        // Public
        if ($link->getResourceType() === 'report_file') {
            // Generate a file from the report_id in the requested format
            try {
                $file = $this->export->generateForReportId($link->getResourceId(), $link->getFormat());
            } catch (\Throwable $e) {
                return new Response('Failed to export report: '.$e->getMessage(), 500);
            }

            $resp = new BinaryFileResponse($file['path']);
            $resp->setContentDisposition('attachment', $link->getFilename() ?: $file['filename']);
            $resp->headers->set('Content-Type', $file['mime']);
            return $resp;
        }

        return new Response('Unsupported resource', 400);
    }
}
