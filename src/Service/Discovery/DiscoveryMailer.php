<?php

namespace App\Service\Discovery;

use App\Entity\Discovery\DiscoverySession;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class DiscoveryMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly string $defaultSender = 'Repweb Discovery <noreply@example.com>'
    ) {
    }

    public function sendSessionSummary(DiscoverySession $session): void
    {
        $project = $session->getProject();
        if (!$project) {
            return;
        }

        $recipients = [];
        if ($project->getOwnerEmail() && filter_var($project->getOwnerEmail(), FILTER_VALIDATE_EMAIL)) {
            $recipients[] = $project->getOwnerEmail();
        }
        foreach ($project->getStakeholders() as $stakeholder) {
            $email = $stakeholder->getEmail();
            if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $recipients[] = $email;
            }
        }
        $recipients = array_values(array_unique($recipients));

        if (empty($recipients)) {
            $session->setMailStatus('pending');
            $session->setMailError('No recipients defined');
            return;
        }

        $from = Address::create($_ENV['DISCOVERY_SENDER'] ?? $this->defaultSender);

        $html = $this->twig->render('email/discovery/session_summary.html.twig', [
            'session' => $session,
            'project' => $project,
        ]);

        $email = (new Email())
            ->from($from)
            ->subject(sprintf('Discovery session recap: %s', $session->getTitle()))
            ->html($html);

        foreach ($recipients as $address) {
            $email->addTo($address);
        }

        try {
            $this->mailer->send($email);
            $session->setMailStatus('sent');
            $session->setMailError(null);
            $session->setMailedAt(new \DateTimeImmutable());
        } catch (\Throwable $e) {
            $session->setMailStatus('error');
            $session->setMailError($e->getMessage());
        }
    }
}
