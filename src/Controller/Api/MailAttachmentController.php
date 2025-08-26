<?php

namespace App\Controller\Api;

use App\Entity\MailAttachment;
use App\Entity\MailTemplate;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/mail')]
class MailAttachmentController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    /** List attachments for a template */
    #[Route(
        '/templates/{id}/attachments',
        name: 'api_mail_attachments_list',
        methods: ['GET'],
        requirements: ['id' => '\d+']
    )]
    public function list(int $id): JsonResponse
    {
        $tpl = $this->em->find(MailTemplate::class, $id);
        if (!$tpl) return $this->json(['error' => 'template not found'], 404);

        $atts = $this->em->getRepository(MailAttachment::class)
            ->findBy(['template' => $tpl], ['position' => 'ASC', 'id' => 'ASC']);

        return $this->json(array_map([$this, 'serializeAtt'], $atts));
    }

    /** Get a single attachment */
    #[Route(
        '/templates/{id}/attachments/{attId}',
        name: 'api_mail_attachments_get_one',
        methods: ['GET'],
        requirements: ['id' => '\d+', 'attId' => '\d+']
    )]
    public function getOne(int $id, int $attId): JsonResponse
    {
        $tpl = $this->em->find(MailTemplate::class, $id);
        if (!$tpl) return $this->json(['error' => 'template not found'], 404);

        $att = $this->em->find(MailAttachment::class, $attId);
        if (!$att || $att->getTemplate()->getId() !== $tpl->getId()) {
            return $this->json(['error' => 'attachment not found for this template'], 404);
        }

        return $this->json($this->serializeAtt($att));
    }

    /**
     * Add or update a single attachment.
     * Accepts JSON (for report/file path) or multipart/form-data when uploading a file.
     *
     * Fields:
     * - id? (int) update existing
     * - TYPE: "report"|"file"
     * - report_id: int (if TYPE=report)
     * - file: uploaded file (if TYPE=file, form-data)
     * - file_path: string (optional when TYPE=file and using an existing server path)
     * - FORMAT: "csv"|"excel"|"json"  (for report)
     * - is_link: 0|1
     * - is_public_link: 0|1 (only relevant if is_link=1)
     * - filename_override: string?
     * - POSITION: int (defaults to max+1)
     */
    #[Route(
        '/templates/{id}/attachments',
        name: 'api_mail_attachments_save',
        methods: ['POST'],
        requirements: ['id' => '\d+']
    )]
    public function save(int $id, Request $req): JsonResponse
    {
        $tpl = $this->em->find(MailTemplate::class, $id);
        if (!$tpl) return $this->json(['error' => 'template not found'], 404);

        $contentType = (string) $req->headers->get('Content-Type');
        $isMultipart = str_starts_with($contentType, 'multipart/form-data');

        $d = $isMultipart ? $req->request->all() : (json_decode($req->getContent() ?: '{}', true) ?: []);

        $att = null;
        $isUpdate = !empty($d['id']);
        if ($isUpdate) {
            $att = $this->em->find(MailAttachment::class, (int)$d['id']);
            if (!$att || $att->getTemplate()->getId() !== $tpl->getId()) {
                return $this->json(['error' => 'attachment not found for this template'], 404);
            }
        } else {
            $att = new MailAttachment();
            $att->setTemplate($tpl);
        }

        $type = strtolower((string)($d['TYPE'] ?? 'report'));
        if (!in_array($type, ['report', 'file'], true)) {
            return $this->json(['error' => 'TYPE must be "report" or "file"'], 400);
        }
        $att->setType($type);

        // POSITION default to next position if not provided on create
        if (isset($d['POSITION'])) {
            $att->setPosition((int)$d['POSITION']);
        } elseif (!$isUpdate) {
            $maxPos = (int) $this->em->createQueryBuilder()
                ->select('COALESCE(MAX(a.position), -1)')
                ->from(MailAttachment::class, 'a')
                ->where('a.template = :tpl')
                ->setParameter('tpl', $tpl)
                ->getQuery()->getSingleScalarResult();
            $att->setPosition($maxPos + 1);
        }

        $att->setFilenameOverride($d['filename_override'] ?? null);
        $att->setIsLink(!empty($d['is_link']));
        $att->setIsPublicLink(!empty($d['is_public_link']));

        if ($type === 'report') {
            $rid = isset($d['report_id']) ? (int)$d['report_id'] : null;
            if (!$rid) return $this->json(['error' => 'report_id required for TYPE=report'], 400);
            $att->setReportId($rid);

            $format = strtolower((string)($d['FORMAT'] ?? 'csv'));
            if (!in_array($format, ['csv','excel','json'], true)) {
                return $this->json(['error' => 'FORMAT must be csv|excel|json for TYPE=report'], 400);
            }
            $att->setFormat($format);

            $att->setFilePath(null);
        } else {
            // TYPE=file
            /** @var UploadedFile|null $file */
            $file = $req->files->get('file');
            $filePath = $d['file_path'] ?? null;

            if ($file instanceof UploadedFile) {
                // OPTIONAL: add limits/guards (uncomment and tune if you want)
                // if ($file->getSize() > 50 * 1024 * 1024) return $this->json(['error' => 'file too large'], 400);
                // $allowed = ['text/plain','text/csv','application/pdf','application/zip'];
                // if (!in_array($file->getClientMimeType() ?: '', $allowed, true)) return $this->json(['error'=>'mime not allowed'],400);

                $destDir = $this->getParameter('kernel.project_dir') . '/public/uploads/mail-files';
                if (!is_dir($destDir)) @mkdir($destDir, 0775, true);
                $ext = $file->guessExtension() ?: pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION) ?: 'bin';
                $safeName = sprintf('tpl_%d_%s.%s', $tpl->getId(), bin2hex(random_bytes(6)), $ext);
                $file->move($destDir, $safeName);

                $att->setFilePath($destDir . '/' . $safeName);
            } elseif ($filePath) {
                $att->setFilePath($filePath);
            } else {
                return $this->json(['error' => 'Provide file upload (field "file") or "file_path" for TYPE=file'], 400);
            }

            // FORMAT not used for raw files; keep whatever is given or default
            $att->setFormat(strtolower((string)($d['FORMAT'] ?? 'csv')));
        }

        $this->em->persist($att);
        $this->em->flush();

        $payload = $this->serializeAtt($att);
        return $this->json($payload, $isUpdate ? 200 : 201);
    }

    /** Delete one attachment from a template */
    #[Route(
        '/templates/{id}/attachments/{attId}',
        name: 'api_mail_attachments_delete',
        methods: ['DELETE'],
        requirements: ['id' => '\d+', 'attId' => '\d+']
    )]
    public function delete(int $id, int $attId): JsonResponse
    {
        $tpl = $this->em->find(MailTemplate::class, $id);
        if (!$tpl) return $this->json(['error' => 'template not found'], 404);

        $att = $this->em->find(MailAttachment::class, $attId);
        if (!$att || $att->getTemplate()->getId() !== $tpl->getId()) {
            return $this->json(['error' => 'attachment not found for this template'], 404);
        }

        $this->em->remove($att);
        $this->em->flush();

        return $this->json(['ok' => true], 200);
    }

    /** Helper to standardize attachment JSON (includes public URL if stored under /public) */
    private function serializeAtt(MailAttachment $a): array
    {
        $publicUrl = null;
        $abs = $a->getFilePath();
        if ($abs && str_contains($abs, DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR)) {
            $projectDir = $this->getParameter('kernel.project_dir');
            $publicDir  = $projectDir . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR;
            if (str_starts_with($abs, $publicDir)) {
                $rel = substr($abs, strlen($publicDir));
                $publicUrl = '/' . str_replace(DIRECTORY_SEPARATOR, '/', $rel);
            }
        }

        return [
            'id'                => $a->getId(),
            'TYPE'              => $a->getType(),
            'report_id'         => $a->getReportId(),
            'file_path'         => $a->getFilePath(),
            'file_url'          => $publicUrl,
            'FORMAT'            => $a->getFormat(),
            'is_link'           => $a->isLink(),
            'is_public_link'    => $a->isPublicLink(),
            'filename_override' => $a->getFilenameOverride(),
            'POSITION'          => $a->getPosition(),
        ];
    }
}
