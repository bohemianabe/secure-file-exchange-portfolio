<?php

namespace App\Command;

use App\Entity\Firms;
use App\Repository\FirmsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:firm-purge-soft-deleted',
    description: '
            Purge anything old than 7 days and dry run:
            php bin/console app:firm-purge-soft-deleted --older-than="7 days" --batch=100 --dry-run


            Purge older than 0 minutes and dry run:
            php bin/console app:firm-purge-soft-deleted --older-than="0 minutes" --batch=100 --dry-run


            To delete no dry run:
            php bin/console app:firm-purge-soft-deleted --older-than="7 days" --batch=100    ',
)]



class PurgeSoftDeletedFirmsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FirmsRepository $firms
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('older-than', null, InputOption::VALUE_REQUIRED, 'Purge items soft-deleted before this duration (e.g. "7 days", "1 month")', '7 days')
            ->addOption('batch', null, InputOption::VALUE_REQUIRED, 'Batch size', '100')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be deleted but don\'t delete');
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io       = new SymfonyStyle($input, $output);
        $batch    = (int) $input->getOption('batch');
        $dryRun   = (bool) $input->getOption('dry-run');

        $em = $this->em;
        $filters = $em->getFilters();

        // ag: turn off the soft-delete filter so we can see soft-deleted rows
        $wasEnabled = $filters->isEnabled('soft_delete');
        if ($wasEnabled) {
            $filters->disable('soft_delete');
        }


        $cutoff = new \DateTimeImmutable('-' . $input->getOption('older-than'));
        $io->title(sprintf('Purging soft-deleted items older than %s (<= %s)', $input->getOption('older-than'), $cutoff->format('c')));

        $total = 0;
        $this->em->beginTransaction();


        try {
            $total = 0;
            $cutoff = new \DateTimeImmutable('-' . $input->getOption('older-than')); // e.g. "7 days"

            foreach ($this->firms->iterateForPurge($cutoff, $batch) as $chunk) {
                foreach ($chunk as $firm) {
                    if ($dryRun) {
                        $io->writeln(sprintf('Would delete firm #%d "%s"', $firm->getId(), $firm->getName()));
                        continue;
                    }
                    $this->em->remove($firm);
                }

                if (!$dryRun) {
                    $this->em->flush();
                    $this->em->clear(); // keep memory stable on big runs
                }

                $total += \count($chunk);
            }

            if (!$dryRun) {
                $this->em->commit();
            } else {
                $this->em->rollback();
            }

            $io->success($dryRun ? "Dry-run complete. Would delete " . $total . " firm(s)." : "Purged " . $total . " firm(s).");
            return Command::SUCCESS;
        } finally {
            // Re-enable the filter so the rest of the app behaves normally
            if ($wasEnabled) {
                $filters->enable('soft_delete');
            }
        }
        return Command::SUCCESS;
    }
}
