<?php
namespace App\Command;

use App\Service\MembershipApplicationRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'community:approve', description: 'Approve a membership by token')]
final class CommunityApproveCommand extends Command
{
    public function __construct(private MembershipApplicationRepository $repo){ parent::__construct(); }

    protected function configure(): void
    {
        $this->addArgument('token', InputArgument::REQUIRED, 'Verification token');
    }

    protected function execute(InputInterface $in, OutputInterface $out): int
    {
        $token = (string)$in->getArgument('token');
        $this->repo->approve($token);
        $out->writeln('<info>Approved.</info>');
        return Command::SUCCESS;
    }
}
