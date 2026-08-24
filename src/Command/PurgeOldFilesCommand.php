<?php

namespace App\Command;

use App\Logging\AuditLogger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use App\Repository\FilesRepository;
use Doctrine\ORM\EntityManagerInterface;

#[AsCommand(
    name: 'app:purge-old-files',
    description: 'Soft-delete or remove files older than 1 year'
)]
class PurgeOldFilesCommand extends Command
{
    private array $errorArray = [];

    public function __construct(
        private FilesRepository $filesRepo,
        private EntityManagerInterface $em,
        private AuditLogger $audit,
    ) {
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
        // ag: TODO review this command. May want to hard delete instead of just soft delete
        $io = new SymfonyStyle($input, $output);

        $threshold = (new \DateTimeImmutable())->modify('-1 year');
        // ag: if the file passed its expiration_date, and not delete then delete file. Assume it is a straggler.  
        $files = $this->filesRepo->createQueryBuilder('f')
            ->andWhere('f.deletedAt IS NULL')
            ->andWhere('f.createdAt < :threshold')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getResult();

        foreach ($files as $file) {
            try {
                $file->setDeletedAt(new \DateTimeImmutable());
                // ag: unlink file here
            } catch (\Throwable $e) {
                $this->errorArray[] = ['fileId' => $file->getId(), 'error' => $e->getMessage()];
            }
        }

        $this->em->flush();

        if (!empty($this->errorArray)) {
            $this->audit->log(
                action: 'file.oldFilesPurgeCommand',
                resourceType: 'Files',
                // resourceId: $export->getId(),
                success: false,
                metadata: $this->errorArray,
                severity: 'error',
            );

            $io->warning($this->errorArray[0]);
        } else {
            $this->audit->log(
                action: 'file.oldFilesPurgeCommand',
                resourceType: 'Files',
                // resourceId: $export->getId(),
                success: true,
                metadata: $this->errorArray,
                severity: 'info',
            );

            $io->success(sprintf('Marked %d files older than 1 year as deleted.', count($files)));
        }


        return Command::SUCCESS;
    }
}
