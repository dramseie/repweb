<?php

namespace App\Command;

use App\Entity\Admin;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class CreateAdminCommand extends Command
{
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $passwordHasher;

    public static function getDefaultName(): ?string
    {
        return 'app:create-admin';
    }

    public function __construct(EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher)
    {
        parent::__construct();
        $this->em = $em;
        $this->passwordHasher = $passwordHasher;
    }

    protected function configure(): void
    {
        $this->setDescription('Creates an admin user');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $helper = $this->getHelper('question');

        $emailQuestion = new Question('Enter admin email: ');
        $email = $helper->ask($input, $output, $emailQuestion);

        $passwordQuestion = new Question('Enter admin password: ');
        $passwordQuestion->setHidden(true);
        $passwordQuestion->setHiddenFallback(false);
        $password = $helper->ask($input, $output, $passwordQuestion);

        $confirmQuestion = new Question('Confirm admin password: ');
        $confirmQuestion->setHidden(true);
        $confirmQuestion->setHiddenFallback(false);
        $confirmPassword = $helper->ask($input, $output, $confirmQuestion);

        if ($password !== $confirmPassword) {
            $io->error('Passwords do not match.');
            return Command::FAILURE;
        }

        $user = new Admin();
        $user->setEmail($email);
        $user->setRoles(['ROLE_ADMIN']);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->em->persist($user);
        $this->em->flush();

        $io->success('Admin user created successfully.');
        return Command::SUCCESS;
    }
}
