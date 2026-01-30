<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\Writer\PngWriter;
use OTPHP\TOTP;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class TwoFactorController extends AbstractController
{
    private const SESSION_TOTP_SECRET = 'totp_setup_secret';
    private const ISSUER = 'Repweb';

    #[Route('/account/2fa', name: 'account_2fa', methods: ['GET'])]
    public function setup(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException('User not authenticated.');
        }

        $session = $request->getSession();
        $secret = null;
        $provisioningUri = null;
        $qrDataUri = null;

        if (!$user->isTotpEnabled()) {
            $regen = $request->query->getBoolean('regen', false);
            if ($regen || !$session->has(self::SESSION_TOTP_SECRET)) {
                $totp = TOTP::create();
                $totp->setLabel($user->getEmail() ?? $user->getUserIdentifier());
                $totp->setIssuer(self::ISSUER);
                $session->set(self::SESSION_TOTP_SECRET, $totp->getSecret());
            }

            $secret = (string) $session->get(self::SESSION_TOTP_SECRET);
            if (!empty($secret)) {
                $totp = TOTP::create($secret);
                $totp->setLabel($user->getEmail() ?? $user->getUserIdentifier());
                $totp->setIssuer(self::ISSUER);
                $provisioningUri = $totp->getProvisioningUri();

                $result = Builder::create()
                    ->writer(new PngWriter())
                    ->data($provisioningUri)
                    ->encoding(new Encoding('UTF-8'))
                    ->size(220)
                    ->margin(10)
                    ->build();

                $qrDataUri = $result->getDataUri();
            }
        }

        return $this->render('security/totp_setup.html.twig', [
            'enabled' => $user->isTotpEnabled(),
            'secret' => $secret,
            'provisioning_uri' => $provisioningUri,
            'qr_data_uri' => $qrDataUri,
        ]);
    }

    #[Route('/account/2fa/enable', name: 'account_2fa_enable', methods: ['POST'])]
    public function enable(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException('User not authenticated.');
        }

        if ($user->isTotpEnabled()) {
            $this->addFlash('info', 'Two-factor authentication is already enabled.');
            return $this->redirectToRoute('account_2fa');
        }

        if (!$this->isCsrfTokenValid('totp_setup', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid CSRF token. Please try again.');
            return $this->redirectToRoute('account_2fa');
        }

        $secret = (string) $request->getSession()->get(self::SESSION_TOTP_SECRET);
        if (empty($secret)) {
            $this->addFlash('error', 'TOTP setup not initialized. Please start again.');
            return $this->redirectToRoute('account_2fa');
        }

        $code = (string) $request->request->get('code');
        $code = preg_replace('/\s+/', '', $code ?? '');

        $totp = TOTP::create($secret);
        $totp->setLabel($user->getEmail() ?? $user->getUserIdentifier());
        $totp->setIssuer(self::ISSUER);

        if (!$totp->verify($code)) {
            $this->addFlash('error', 'Invalid verification code. Please try again.');
            return $this->redirectToRoute('account_2fa');
        }

        $user->setTotpSecret($secret);
        $user->setTotpEnabled(true);
        $em->flush();
        $request->getSession()->remove(self::SESSION_TOTP_SECRET);

        $this->addFlash('success', 'Two-factor authentication enabled.');
        return $this->redirectToRoute('account_2fa');
    }

    #[Route('/account/2fa/disable', name: 'account_2fa_disable', methods: ['POST'])]
    public function disable(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException('User not authenticated.');
        }

        if (!$this->isCsrfTokenValid('totp_disable', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid CSRF token. Please try again.');
            return $this->redirectToRoute('account_2fa');
        }

        $user->setTotpEnabled(false);
        $user->setTotpSecret(null);
        $em->flush();

        $this->addFlash('success', 'Two-factor authentication disabled.');
        return $this->redirectToRoute('account_2fa');
    }

    public function check(): Response
    {
        return new RedirectResponse('/2fa');
    }
}
