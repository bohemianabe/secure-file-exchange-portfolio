<?php

namespace App\Command;

use App\Repository\FileRequestsRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:expire-file-requests',
    description: 'Command to change the status of file requests that have expired/never completed.',
)]
class ExpireFileRequestsCommand extends Command
{
    public function __construct(private readonly FileRequestsRepository $fileRequestsRepository)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('arg1', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $arg1 = $input->getArgument('arg1');

        if ($arg1) {
            $io->note(sprintf('You passed an argument: %s', $arg1));
        }
        $now = new \DateTimeImmutable();
        $updatedCount = $this->fileRequestsRepository->expireStaleRequests($now);

        $io->success(sprintf(
            'Expired %d stale file request(s).',
            $updatedCount
        ));

        return Command::SUCCESS;
    }
}
