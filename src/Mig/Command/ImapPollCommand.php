<?php
namespace App\Mig\Command;

use App\Mig\Service\MailService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Psr\Log\LoggerInterface;

class ImapPollCommand extends Command
{
    protected static $defaultName = 'mig:mail:poll-imap';

    private MailService $mailService;
    private LoggerInterface $logger;

    public function __construct(MailService $mailService, LoggerInterface $logger)
    {
        parent::__construct(self::$defaultName);
        $this->mailService = $mailService;
        $this->logger = $logger;
    }

    protected function configure()
    {
        $this->setDescription('Poll IMAP server for new messages and persist them.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $host = getenv('MAIL_IMAP_HOST') ?: null;
        $user = getenv('MAIL_IMAP_USER') ?: null;
        $pass = getenv('MAIL_IMAP_PASS') ?: null;
        $mailbox = getenv('MAIL_IMAP_MAILBOX') ?: 'INBOX';

        if (!$host || !$user || !$pass) {
            $output->writeln('<error>Missing MAIL_IMAP_HOST / MAIL_IMAP_USER / MAIL_IMAP_PASS env vars</error>');
            return Command::FAILURE;
        }

        $connectionString = sprintf('{%s}%s', $host, $mailbox);

        if (!function_exists('imap_open')) {
            $output->writeln('<error>PHP IMAP extension is not available (php-imap)</error>');
            return Command::FAILURE;
        }

        $stream = @imap_open($connectionString, $user, $pass);
        if (!$stream) {
            $err = imap_last_error();
            $output->writeln('<error>Failed to open IMAP connection: ' . $err . '</error>');
            $this->logger->error('IMAP connection failed', ['error' => $err]);
            return Command::FAILURE;
        }

        $output->writeln('Connected, searching UNSEEN messages...');
        $uids = imap_search($stream, 'UNSEEN', SE_UID);
        if (empty($uids)) {
            $output->writeln('No new messages.');
            imap_close($stream);
            return Command::SUCCESS;
        }

        foreach ($uids as $uid) {
            try {
                $header = imap_fetchheader($stream, $uid, FT_UID);
                $overview = imap_fetch_overview($stream, $uid, FT_UID)[0] ?? null;
                $body = imap_fetchbody($stream, $uid, "1", FT_UID);

                // naive extraction: prefer HTML part when present
                $structure = imap_fetchstructure($stream, $uid, FT_UID);
                $html = null;
                $text = null;
                $attachments = [];

                if ($structure && property_exists($structure, 'parts') && count($structure->parts)) {
                    foreach ($structure->parts as $partNum => $part) {
                        $partIndex = $partNum + 1;
                        $partData = imap_fetchbody($stream, $uid, $partIndex, FT_UID);
                        // decode
                        if ($part->encoding == 3) $partData = base64_decode($partData);
                        if ($part->encoding == 4) $partData = quoted_printable_decode($partData);

                        // html vs text
                        $ct = strtolower($part->subtype ?? '');
                        if ($ct === 'html' || stripos($part->type ?? '', 'text') !== false && $ct === 'html') {
                            $html = $partData;
                        }
                        if ($part->type === 0) {
                            // text
                            if (!$text) $text = $partData;
                        }

                        // attachments
                        if (isset($part->disposition) && strtolower($part->disposition) === 'attachment') {
                            $filename = $part->dparameters[0]->value ?? ('attachment-' . $partIndex);
                            $contentType = ($part->type ?? 'application') . '/' . ($part->subtype ?? 'octet-stream');
                            $attachments[] = [$filename, $contentType, $partData];
                        }
                    }
                } else {
                    // fallback: use raw body
                    $bodyRaw = imap_body($stream, $uid, FT_UID);
                    $text = $bodyRaw;
                }

                $messageId = null;
                if ($overview && isset($overview->message_id)) {
                    $messageId = $overview->message_id;
                }

                $from = null;
                if ($overview && isset($overview->from)) {
                    $from = $overview->from;
                }

                $to = [];
                if ($overview && isset($overview->to)) {
                    $to = array_map('trim', explode(',', $overview->to));
                }

                $envelope = [
                    'message_id' => $messageId,
                    'subject' => $overview->subject ?? null,
                    'from' => $from,
                    'to' => $to,
                    'cc' => null,
                    'bcc' => null,
                    'html' => $html,
                    'text' => $text,
                    'attachments' => $attachments,
                    'tags' => null,
                ];

                $this->mailService->saveIncomingMail($envelope);
                // mark as seen
                imap_setflag_full($stream, $uid, '\\Seen', ST_UID);

                $output->writeln('Saved message: ' . ($overview->subject ?? '(no subject)'));
            } catch (\Throwable $e) {
                $this->logger->error('Failed to process IMAP message', ['exception' => $e]);
                $output->writeln('<error>Failed to process message UID ' . $uid . '</error>');
            }
        }

        imap_close($stream);
        $output->writeln('Done.');
        return Command::SUCCESS;
    }
}
