<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use OTPHP\TOTP;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:2fa:enable',
    description: 'Enable TOTP for a user and print provisioning details.'
)]
class EnableTotpForUserCommand extends Command
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'User email address')
            ->addOption('regen', null, InputOption::VALUE_NONE, 'Regenerate secret if already enabled');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = (string) $input->getArgument('email');
        $regen = (bool) $input->getOption('regen');

        /** @var User|null $user */
        $user = $this->users->findOneBy(['email' => $email]);
        if (!$user) {
            $io->error(sprintf('User not found for email "%s".', $email));
            return Command::FAILURE;
        }

        if ($user->isTotpEnabled() && !$regen) {
            $io->warning('TOTP is already enabled for this user. Use --regen to rotate the secret.');
            return Command::SUCCESS;
        }

        $totp = TOTP::create();
        $totp->setLabel($user->getEmail() ?? $user->getUserIdentifier());
        $totp->setIssuer('Repweb');

        $user->setTotpSecret($totp->getSecret());
        $user->setTotpEnabled(true);
        $this->em->flush();

        $io->success('TOTP enabled for user.');
        $io->writeln('Secret: <info>' . $totp->getSecret() . '</info>');
        $io->writeln('Provisioning URI: <info>' . $totp->getProvisioningUri() . '</info>');

        return Command::SUCCESS;
    }
}
