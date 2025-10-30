<?php
namespace App\Mig\Controller;

use App\Mig\Service\MailService;
use App\Mig\Entity\MailTemplate;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/mig/mail')]
class MailController extends AbstractController
{
    public function __construct(private MailService $mailService, private EntityManagerInterface $em) {}

    #[Route('/', name: 'mig_mail_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $repo = $this->em->getRepository(\App\Mig\Entity\Mail::class);
        $items = $repo->findBy([], ['createdAt' => 'DESC'], 200);
        $payload = array_map(fn($m) => [
            'id' => (int)$m->getId(),
            'subject' => $m->getSubject(),
            'from' => $m->getFromAddress(),
            'to' => $m->getToAddresses(),
            'createdAt' => $m->getCreatedAt()->format(DATE_ATOM),
            'status' => $m->getStatus(),
        ], $items);
        return $this->json(['items' => $payload]);
    }

    #[Route('/{id}', name: 'mig_mail_get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $repo = $this->em->getRepository(\App\Mig\Entity\Mail::class);
        $m = $repo->find($id);
        if (!$m) return $this->json(['error' => 'Not found'], 404);
        return $this->json([
            'id' => (int)$m->getId(),
            'subject' => $m->getSubject(),
            'from' => $m->getFromAddress(),
            'to' => $m->getToAddresses(),
            'cc' => $m->getCcAddresses(),
            'bcc' => $m->getBccAddresses(),
            'bodyHtml' => $m->getBodyHtml(),
            'bodyText' => $m->getBodyText(),
            'tags' => $m->getTags(),
            'status' => $m->getStatus(),
            'createdAt' => $m->getCreatedAt()->format(DATE_ATOM),
            'sentAt' => $m->getSentAt()?->format(DATE_ATOM),
        ]);
    }

    #[Route('/send', name: 'mig_mail_send', methods: ['POST'])]
    public function send(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!isset($data['to']) || !isset($data['subject'])) {
            return $this->json(['error' => 'Invalid payload'], 400);
        }

        $from = $data['from'] ?? null;
        $to = is_array($data['to']) ? $data['to'] : [$data['to']];
        $subject = $data['subject'];
        $html = $data['html'] ?? null;
        $text = $data['text'] ?? null;
        $tags = $data['tags'] ?? null;

        $attachments = [];
        if (!empty($data['attachments']) && is_array($data['attachments'])) {
            foreach ($data['attachments'] as $att) {
                // expect {filename, contentType, base64}
                if (isset($att['filename']) && isset($att['contentType']) && isset($att['base64'])) {
                    $binary = base64_decode($att['base64']);
                    $attachments[] = [$att['filename'], $att['contentType'], $binary];
                }
            }
        }

        try {
            $mail = $this->mailService->sendMail($to, $subject, $html, $text, $attachments, $from, $tags, $data['template_id'] ?? null);
            return $this->json(['id' => (int)$mail->getId(), 'status' => $mail->getStatus()]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/templates', name: 'mig_mail_templates', methods: ['GET','POST'])]
    public function templates(Request $request): JsonResponse
    {
        if ($request->isMethod('GET')) {
            $repo = $this->em->getRepository(MailTemplate::class);
            $items = $repo->findBy([], ['createdAt' => 'DESC']);
            $payload = array_map(fn($t) => [
                'id' => (int)$t->getId(),
                'name' => $t->getName(),
                'subject' => $t->getSubject(),
            ], $items);
            return $this->json(['items' => $payload]);
        }

        $data = json_decode($request->getContent(), true);
        if (!$data['name'] || !$data['subject']) {
            return $this->json(['error' => 'Missing name/subject'], 400);
        }
        $t = new MailTemplate();
        $t->setName($data['name']);
        $t->setSubject($data['subject']);
        $t->setBodyHtml($data['html'] ?? null);
        $t->setBodyText($data['text'] ?? null);
        $t->setDefaultFrom($data['defaultFrom'] ?? null);
        $this->em->persist($t);
        $this->em->flush();
        return $this->json(['id' => (int)$t->getId()]);
    }

    #[Route('/templates/{id}/send', name: 'mig_mail_template_send', methods: ['POST'])]
    public function sendTemplate(int $id, Request $request): JsonResponse
    {
        $repo = $this->em->getRepository(MailTemplate::class);
        $t = $repo->find($id);
        if (!$t) return $this->json(['error' => 'Template not found'], 404);

        $data = json_decode($request->getContent(), true);
        $to = $data['to'] ?? null;
        if (!$to) return $this->json(['error' => 'Missing recipients'], 400);
        $toArr = is_array($to) ? $to : [$to];

        $merge = $data['merge'] ?? [];
        $rendered = $this->mailService->renderTemplate($t, $merge);

        try {
            $mail = $this->mailService->sendMail($toArr, $rendered['subject'], $rendered['html'], $rendered['text'], [], $t->getDefaultFrom(), $data['tags'] ?? null, (int)$t->getId());
            return $this->json(['id' => (int)$mail->getId(), 'status' => $mail->getStatus()]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }
}
