<?php

namespace App\Command;

use App\Logging\AuditLogger;
use App\Repository\FirmsRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Connection;


#[AsCommand(
    name: 'app:apply-pending-storage-plan-changes',
    description: 'This command looks to see if any storage plan changes have been requested, and makes all the neccessary changes to go with it.',
)]
class ApplyPendingStoragePlanChangesCommand extends Command
{
    private array $errorArray = [];
    public function __construct(
        private FirmsRepository $firmRepo,
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
        $io = new SymfonyStyle($input, $output);

        $firmPendingStorageChange = $this->firmRepo->createQueryBuilder('f')
            ->where('f.pendingStoragePlan IS NOT NULL')
            ->getQuery()
            ->getResult();


        if (!empty($firmPendingStorageChange)) {
            foreach ($firmPendingStorageChange as $firm) {
                try {
                    $pendingStoragePlan = $firm->getPendingStoragePlan();
                    $firmId = $firm->getId();

                    $this->firmRepo->createQueryBuilder('f')
                        ->update()
                        ->set('f.storagePlan', ':storagePlan')
                        ->set('f.pendingStoragePlan', ':pendingStoragePlan')
                        ->where('f.idFirm = :idFirm')
                        ->setParameter('storagePlan', $pendingStoragePlan)
                        ->setParameter('pendingStoragePlan', null)
                        ->setParameter('idFirm', $firmId)
                        ->getQuery()
                        ->execute();

                    echo $firm->getName() . " storage plan updated to: " . $pendingStoragePlan->getName() . PHP_EOL;
                } catch (\Throwable $e) {
                    $this->errorArray[] = [$firm->getName() => $e->getMessage()];
                }
            }
        } else {
            $io->success('No pending storage plan changes found.');
        }

        if (!empty($this->errorArray)) {
            $this->audit->log(
                action: 'storagePlan.applyPendingCommand',
                resourceType: 'storagePlan',
                // resourceId: $export->getId(),
                success: false,
                metadata: $this->errorArray,
                severity: 'error',
            );

            $io->warning($this->errorArray[0]);
        } else {
            $this->audit->log(
                action: 'storagePlan.applyPendingCommand',
                resourceType: 'storagePlan',
                // resourceId: $export->getId(),
                success: true,
                metadata: $this->errorArray,
                severity: 'info',
            );


            $io->success('Pending storage changes applied without issue.');
        }
        return Command::SUCCESS;
    }

    // ag: function ended up being unused, but leaving here for future reference.
    /**
     * @param string $sql SQL statement to execute
     * @param Connection $connection Doctrine database connection to use (optional)
     * @return mixed
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    protected function query(string $sql, ?Connection $connection = null, array $params = [])
    {
        try {
            // $conn = isset($connection) && $connection instanceof Connection ? $connection : $this->em->getConnection();
            $stmt = $connection->prepare($sql);

            // Execute with parameters
            if (!empty($params)) {
                return $stmt->executeQuery($params)->fetchAllAssociative();
            } else {
                return $stmt->executeQuery()->fetchAllAssociative();
            }
        } catch (\Throwable $e) {
            // ag: build the error array
            dd($e->getMessage());
            $errorArr = [
                'error_message' => $e->getMessage(),
                'sql' => $sql,
                'params' => $params,
            ];
            array_push($this->errorArray, $errorArr);

            return null;
        }
    }
}
