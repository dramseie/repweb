<?php

namespace App\Controller\Api;

use App\Entity\MailTemplate;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/mail')]
class MailTemplateController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    #[Route('/templates', name: 'api_mail_templates_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $all = $this->em->getRepository(MailTemplate::class)->findBy([], ['id' => 'DESC']);
        $out = [];
        foreach ($all as $t) {
            $out[] = [
                'id'         => $t->getId(),
                'NAME'       => $t->getName(),
                'SUBJECT'    => $t->getSubject(),
                'from_email' => $t->getFromEmail(),
                'is_active'  => $t->isActive(),
                'updated_at' => $t->getUpdatedAt()?->format(DATE_ATOM),
            ];
        }
        return $this->json($out);
    }

    #[Route('/templates', name: 'api_mail_templates_save', methods: ['POST'])]
    public function save(Request $req): JsonResponse
    {
        $d = json_decode($req->getContent() ?: '{}', true);

        $tpl = null;
        if (!empty($d['id'])) {
            $tpl = $this->em->find(MailTemplate::class, (int)$d['id']);
            if (!$tpl) return $this->json(['error' => 'template not found'], 404);
        } else {
            $tpl = new MailTemplate();
        }

        $tpl->setName((string)($d['NAME'] ?? $d['name'] ?? 'Untitled'));
        $tpl->setSubject((string)($d['SUBJECT'] ?? $d['subject'] ?? '(no subject)'));
        $tpl->setBodyHtml((string)($d['body_html'] ?? '<p>Hello</p>'));
        $tpl->setBodyText($d['body_text'] ?? null);
        $tpl->setFromEmail((string)($d['from_email'] ?? 'noreply@example.com'));
        $tpl->setReplyTo($d['reply_to'] ?? null);
        $tpl->setIsActive(isset($d['is_active']) ? (bool)$d['is_active'] : true);

        // to & cc validation (array of strings)
        $to = self::normalizeEmails($d['to_addresses'] ?? $d['to'] ?? []);
        $cc = self::normalizeEmails($d['cc_addresses'] ?? $d['cc'] ?? []);
        $tpl->setToAddresses($to);
        $tpl->setCcAddresses($cc);

        $this->em->persist($tpl);
        $this->em->flush();

        return $this->json(['id' => $tpl->getId()]);
    }

    #[Route('/templates/{id}/logo', name: 'api_mail_templates_logo', methods: ['POST'])]
    public function uploadLogo(int $id, Request $req): JsonResponse
    {
        /** @var MailTemplate|null $tpl */
        $tpl = $this->em->find(MailTemplate::class, $id);
        if (!$tpl) return $this->json(['error' => 'template not found'], 404);

        /** @var UploadedFile|null $file */
        $file = $req->files->get('logo');
        if (!$file instanceof UploadedFile) {
            return $this->json(['error' => 'no file "logo" provided'], 400);
        }

        $destDir = $this->getParameter('kernel.project_dir') . '/public/uploads/logos';
        if (!is_dir($destDir)) @mkdir($destDir, 0775, true);

        $safeName = sprintf('logo_%d_%s.%s', $tpl->getId(), bin2hex(random_bytes(4)), $file->guessExtension() ?: 'png');
        $file->move($destDir, $safeName);
        $absolute = $destDir . '/' . $safeName;

        $tpl->setLogoPath($absolute);
        $this->em->flush();

        // public URL (for admin preview)
        $publicUrl = '/uploads/logos/' . $safeName;

        return $this->json(['ok' => true, 'path' => $absolute, 'url' => $publicUrl]);
    }

    /** @return string[] */
    private static function normalizeEmails($val): array
    {
        // accepts array or comma-separated string
        $arr = is_array($val) ? $val : explode(',', (string)$val);
        $uniq = [];
        foreach ($arr as $raw) {
            $e = trim((string)$raw);
            if ($e === '') continue;
            if (!filter_var($e, FILTER_VALIDATE_EMAIL)) continue;
            $uniq[$e] = true;
        }
        return array_keys($uniq);
    }
}
