<?php
namespace App\Controller\Community;

use App\Service\MembershipApplicationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class MembershipController extends AbstractController
{
    public function __construct(private MembershipApplicationRepository $repo) {}

    public function apply(Request $req, MailerInterface $mailer): Response
    {
        if ($req->isMethod('GET')) {
            return $this->render('community/apply.html.twig');
        }

        // POST
        $email = (string)$req->request->get('email', '');
        $hp    = (string)$req->request->get('company', ''); // honeypot

        // Basic validation
        if ($hp !== '') {
            // bot – pretend success
            return $this->render('community/apply_submitted.html.twig');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->render('community/apply.html.twig', [
                'error' => 'Please enter a valid email address.',
                'email' => $email,
            ]);
        }

        // Reuse last request if any (avoid spamming)
        $existing = $this->repo->findOrNullByEmail($email);
        $token = bin2hex(random_bytes(32));
        $ip    = $req->getClientIp();
        $ua    = $req->headers->get('User-Agent');

        $this->repo->create($email, $token, $ip, $ua);

        // Send verification link
        $verifyUrl = rtrim((string)$_ENV['APP_URL'] ?? $req->getSchemeAndHttpHost(), '/') .
                     '/community/verify?token=' . $token;

        $from = (string)($_ENV['COMMUNITY_SENDER'] ?? 'Repweb <noreply@localhost>');
        $emailMsg = (new Email())
            ->from(Address::create($from))
            ->to($email)
            ->subject('Confirm your Repweb community request')
            ->html($this->renderView('email/community_verify.html.twig', [
                'verifyUrl' => $verifyUrl,
            ]));

        $mailer->send($emailMsg);

        return $this->render('community/apply_submitted.html.twig');
    }

    public function verify(Request $req, MailerInterface $mailer): Response
    {
        $token = (string)$req->query->get('token', '');
        if ($token === '') {
            return $this->render('community/verify_result.html.twig', [
                'ok' => false,
                'message' => 'Invalid or missing token.',
            ]);
        }

        $row = $this->repo->byToken($token);
        if (!$row) {
            return $this->render('community/verify_result.html.twig', [
                'ok' => false,
                'message' => 'Token not found.',
            ]);
        }

        $this->repo->markVerified($token);

        $auto = filter_var($_ENV['COMMUNITY_AUTO_APPROVE'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
        if ($auto) {
            $this->repo->approve($token);
            // (Optional) send welcome email here
            // $mailer->send(...);
            return $this->render('community/verify_result.html.twig', [
                'ok' => true,
                'message' => 'Email verified ✅. Your membership is approved — welcome!',
            ]);
        }

        return $this->render('community/verify_result.html.twig', [
            'ok' => true,
            'message' => 'Email verified ✅. An admin will approve your request shortly.',
        ]);
    }
}
