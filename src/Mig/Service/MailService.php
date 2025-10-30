<?php
namespace App\Mig\Service;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use App\Mig\Entity\Mail;
use App\Mig\Entity\MailAttachment;
use App\Mig\Entity\MailTemplate;
use App\Mig\Entity\MailSendLog;

class MailService
{
    private EntityManagerInterface $em;
    private MailerInterface $mailer;
    private LoggerInterface $logger;

    public function __construct(EntityManagerInterface $em, MailerInterface $mailer, LoggerInterface $logger)
    {
        $this->em = $em;
        $this->mailer = $mailer;
        $this->logger = $logger;
    }

    /**
     * Basic email validation using filter_var
     */
    public function isValidAddress(string $email): bool
    {
        return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    /**
     * Send an email via Symfony Mailer and persist mail + attachments + send log.
     * $to can be string or array of strings. $attachments is array of [filename, contentType, binaryData]
     */
    public function sendMail(array|string $to, string $subject, string $bodyHtml = null, string $bodyText = null, array $attachments = [], ?string $from = null, ?array $tags = null, ?int $templateId = null): Mail
    {
        $mailEntity = new Mail();
        $mailEntity->setMailbox('outbound');
        $mailEntity->setSubject($subject);
        $mailEntity->setFromAddress($from ?? '');
        $toArr = is_array($to) ? $to : [$to];
        $mailEntity->setToAddresses($toArr);
        $mailEntity->setBodyHtml($bodyHtml);
        $mailEntity->setBodyText($bodyText);
        $mailEntity->setTags($tags);
        if ($templateId) $mailEntity->setTemplateId((string)$templateId);
        $mailEntity->setStatus('pending');

        $this->em->persist($mailEntity);
        $this->em->flush();

        $email = (new Email())
            ->subject($subject)
            ->from($from ?? 'no-reply@example.com');

        foreach ($toArr as $addr) {
            if ($this->isValidAddress($addr)) {
                $email->addTo($addr);
            } else {
                $this->logger->warning('Invalid recipient skipped', ['address' => $addr]);
            }
        }

        if ($bodyHtml) {
            $email->html($bodyHtml);
        }
        if ($bodyText) {
            $email->text($bodyText);
        }

        try {
            // Attach files
            foreach ($attachments as [$filename, $contentType, $binary]) {
                $email->attach($binary, $filename, $contentType);
            }

            $this->mailer->send($email);

            $mailEntity->setStatus('sent');
            $mailEntity->setSentAt(new \DateTimeImmutable());
            $this->em->persist($mailEntity);

            // persist attachments to DB
            foreach ($attachments as [$filename, $contentType, $binary]) {
                $att = new MailAttachment();
                $att->setMail($mailEntity);
                $att->setFilename($filename);
                $att->setContentType($contentType);
                $att->setSize(strlen($binary));
                $att->setData($binary);
                $this->em->persist($att);
            }

            $log = new MailSendLog();
            $log->setMail($mailEntity);
            $log->setAction('send');
            $log->setStatus('ok');
            $log->setMessage('Message queued/sent by Mailer');
            $this->em->persist($log);

            $this->em->flush();

            return $mailEntity;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send email', ['exception' => $e]);
            $mailEntity->setStatus('error');
            $mailEntity->setError($e->getMessage());
            $this->em->persist($mailEntity);

            $log = new MailSendLog();
            $log->setMail($mailEntity);
            $log->setAction('send');
            $log->setStatus('error');
            $log->setMessage($e->getMessage());
            $this->em->persist($log);

            $this->em->flush();

            throw $e;
        }
    }

    /**
     * Persist an incoming message (from IMAP). $envelope is associative array with keys:
     * message_id, subject, from, to (array), cc (array), bcc (array), html, text, tags (assoc)
     */
    public function saveIncomingMail(array $envelope): Mail
    {
        $mailEntity = new Mail();
        $mailEntity->setMailbox('inbound');
        $mailEntity->setMessageId($envelope['message_id'] ?? null);
        $mailEntity->setSubject($envelope['subject'] ?? null);
        $mailEntity->setFromAddress($envelope['from'] ?? null);
        $mailEntity->setToAddresses($envelope['to'] ?? []);
        $mailEntity->setCcAddresses($envelope['cc'] ?? null);
        $mailEntity->setBccAddresses($envelope['bcc'] ?? null);
        $mailEntity->setBodyHtml($envelope['html'] ?? null);
        $mailEntity->setBodyText($envelope['text'] ?? null);
        $mailEntity->setTags($envelope['tags'] ?? null);
        $mailEntity->setStatus('received');

        $this->em->persist($mailEntity);
        $this->em->flush();

        // attachments may be provided as array of [filename, contentType, binary]
        if (!empty($envelope['attachments']) && is_array($envelope['attachments'])) {
            foreach ($envelope['attachments'] as [$filename, $contentType, $binary]) {
                $att = new MailAttachment();
                $att->setMail($mailEntity);
                $att->setFilename($filename);
                $att->setContentType($contentType);
                $att->setSize(strlen($binary));
                $att->setData($binary);
                $this->em->persist($att);
            }
            $this->em->flush();
        }

        $log = new MailSendLog();
        $log->setMail($mailEntity);
        $log->setAction('received');
        $log->setStatus('ok');
        $log->setMessage('Message persisted from IMAP');
        $this->em->persist($log);
        $this->em->flush();

        return $mailEntity;
    }

    /**
     * Render a template with a simple merge map: replace {{key}} with value
     */
    public function renderTemplate(MailTemplate $template, array $merge = []): array
    {
        $html = $template->getBodyHtml() ?? '';
        $text = $template->getBodyText() ?? '';
        $subject = $template->getSubject() ?? '';

        foreach ($merge as $k => $v) {
            $placeholder = '{{' . $k . '}}';
            $html = str_replace($placeholder, (string)$v, $html);
            $text = str_replace($placeholder, (string)$v, $text);
            $subject = str_replace($placeholder, (string)$v, $subject);
        }

        return ['subject' => $subject, 'html' => $html, 'text' => $text];
    }
}
