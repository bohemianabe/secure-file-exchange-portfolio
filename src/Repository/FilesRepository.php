<?php

namespace App\Repository;

use App\Entity\Clients;
use App\Entity\Files;
use App\Entity\Firms;
use App\Entity\FirmUserProfiles;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Files>
 */
class FilesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Files::class);
    }

    /**
     * @param Clients $client
     * 
     * @return array
     */
    public function getClientFileTypeBreakdown(Clients $client): array
    {
        return $this->createQueryBuilder('f')
            ->select("
            CASE
                WHEN f.mimeType LIKE 'image/%' THEN 'images'
                WHEN f.mimeType LIKE 'video/%' THEN 'video'
                WHEN f.mimeType LIKE 'audio/%' THEN 'music'
                WHEN f.mimeType IN (
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'text/plain'
                ) THEN 'documents'
                ELSE 'others'
            END AS fileType
        ")
            ->addSelect('COUNT(f.idFile) AS fileCount')
            ->addSelect('SUM(f.sizeBytes) AS totalBytes')
            ->andWhere('f.client = :client')
            ->andWhere('f.deletedAt IS NULL')
            ->setParameter('client', $client)
            ->groupBy('fileType')
            ->orderBy('totalBytes', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * @param Firms $firm
     * @param ?FirmUserProfiles $fup
     * @param ?array $clients
     * 
     * @return array
     */
    public function getFirmFileTypeBreakdown(Firms $firm, ?FirmUserProfiles $fup, ?array $clients = []): array
    {
        $qb = $this->createQueryBuilder('f')
            ->select("
            CASE
                WHEN f.mimeType LIKE 'image/%' THEN 'images'
                WHEN f.mimeType LIKE 'video/%' THEN 'video'
                WHEN f.mimeType LIKE 'audio/%' THEN 'music'
                WHEN f.mimeType IN (
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'text/plain'
                ) THEN 'documents'
                ELSE 'others'
            END AS fileType
        ")
            ->addSelect('COUNT(f.idFile) AS fileCount')
            ->addSelect('SUM(f.sizeBytes) AS totalBytes')
            ->andWhere('f.firm = :firm')
            ->andWhere('f.deletedAt IS NULL')
            ->setParameter('firm', $firm)
            ->groupBy('fileType')
            ->orderBy('totalBytes', 'DESC');

        if ($fup) {
            $qb->andWhere('f.uploadedByFirmUser = :fup')->setParameter('fup', $fup);
        }

        if (!empty($clients)) {
            $qb->andWhere('f.client IN (:clients)')->setParameter('clients', $clients);
        }

        // ->getQuery()
        // ->getArrayResult();

        return $qb->getQuery()->getResult();
    }

    /**
     * @param Firms $firm
     * @param ?array $client
     * 
     * @return array
     */
    public function findSoftDeleted(Firms $firm, ?array $client): array
    {

        $em = $this->getEntityManager();

        // Disable the soft delete filter
        $em->getFilters()->disable('soft_delete');


        $qb = $this->createQueryBuilder('f')
            ->where('f.deletedAt is NOT NULL');

        if ($firm) {
            $qb->andWhere('f.firm = :firm')->setParameter('firm', $firm);
        }

        if ($client) {
            $qb->andWhere('f.client IN (:client)')->setParameter('client', $client);
        }

        $result = $qb->getQuery()->getResult();

        // IMPORTANT: re-enable the filter
        $em->getFilters()->enable('soft_delete');

        return $result;
    }

    /**
     * @param FirmUserProfiles $fup
     * @param Clients $client
     * @param ?\DateTimeInterface $startDate
     * @param ?\DateTimeInterface $endDate
     * 
     * example: 
     * $lastWeekStart = (new \DateTime('first day of last week'))->setTime(0, 0, 0);
     * $lastWeekEnd   = (new \DateTime('last day of last week'))->setTime(23, 59, 59);
     * $lastWeekActivity = count($this->em->getRepository(Files::class)->findForFirmUserProfile($userProfile, $lastWeekStart, 
     * $lastWeekEnd));
     * 
     * @return array
     */
    public function findForFirmUserProfile(FirmUserProfiles $fup, ?Clients $client = null, ?\DateTimeInterface $startDate = null, ?\DateTimeInterface $endDate = null): array
    {
        $qb = $this->createQueryBuilder('f')
            ->where('f.uploadedByFirmUser = :uploadedByFirmUser')
            ->setParameter('uploadedByFirmUser', $fup);

        if ($client) {
            $qb->andWhere('f.client = :client')->setParameter('client', $client);
        }

        if ($startDate && $endDate) {
            $qb->andWhere('f.createdAt BETWEEN :start AND :end')
                ->setParameter('start', $startDate)
                ->setParameter('end', $endDate->setTime(23, 59, 59));
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @param Firms $firm
     * @param ?Clients $client
     * @param ?\DateTimeInterface $startDate
     * @param ?\DateTimeInterface $endDate
     * 
     * @return array
     */
    public function findForFirm(Firms $firm, ?Clients $client = null, ?\DateTimeInterface $startDate = null, ?\DateTimeInterface $endDate = null): array
    {
        $qb = $this->createQueryBuilder('f')
            ->where('f.firm = :firm')
            ->setParameter('firm', $firm)
            ->orderBy('f.createdAt', 'DESC');

        if ($client) {
            $qb->andWhere('f.client = :client')->setParameter('client', $client);
        }

        if ($startDate && $endDate) {
            $qb->andWhere('f.createdAt BETWEEN :start AND :end')
                ->setParameter('start', $startDate)
                ->setParameter('end', $endDate->setTime(23, 59, 59));
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @param Clients $client
     * @param ?Firms $firm
     * @param ?\DateTimeInterface $startDate
     * @param ?\DateTimeInterface $endDate
     * 
     * @return array
     */
    public function findForClient(Clients $client, ?Firms $firm = null, ?\DateTimeInterface $startDate = null, ?\DateTimeInterface $endDate = null): array
    {
        $qb = $this->createQueryBuilder('f')
            ->where('f.client = :client')
            // ->andWhere('f.deletedAt is NULL')
            ->setParameter('client', $client)
            ->orderBy('f.createdAt', 'DESC');

        if ($firm) {
            $qb->andWhere('f.firm = :firm')->setParameter('firm', $firm);
        }

        if ($startDate && $endDate) {
            $qb->andWhere('f.createdAt BETWEEN :start AND :end')
                ->setParameter('start', $startDate)
                ->setParameter('end', $endDate->setTime(23, 59, 59));
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @param Firms $firm
     * 
     * @return int
     */
    public function getTotalStorageUsedByFirm(Firms $firm): int
    {
        $qb = $this->createQueryBuilder('f')
            ->select('SUM(f.sizeBytes)')
            ->where('f.firm = :firm')
            ->setParameter('firm', $firm);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param Firms $firm
     * @param Clients $client
     * 
     * @return int
     */
    public function getTotalStorageUsedByClient(Firms $firm, Clients $client): int
    {
        $qb = $this->createQueryBuilder('f')
            ->select('SUM(f.sizeBytes)')
            ->where('f.firm = :firm')
            ->andWhere('f.client = :client')
            ->setParameter('firm', $firm)
            ->setParameter('client', $client);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param Firms $firm
     * @param string $checksum
     * 
     * @return ?Files
     */
    public function findDuplicateByChecksum(Firms $firm, string $checksum): ?Files
    {
        return $this->createQueryBuilder('f')
            ->where('f.firm = :firm')
            ->andWhere('f.checksumSha256 = :checksum')
            ->setParameter('firm', $firm)
            ->setParameter('checksum', $checksum)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param Firms $firm
     * @param string $name
     * 
     * @return ?Files
     */
    public function findLatestVersion(Firms $firm, string $name): ?Files
    {
        return $this->createQueryBuilder('f')
            ->where('f.firm = :firm')
            ->andWhere('f.name = :name')
            ->orderBy('f.version', 'DESC')
            ->setParameter('firm', $firm)
            ->setParameter('name', $name)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param Firms $firm
     * @param ?Clients $client,
     * @param int $page
     * @param int $limit
     * 
     * eg: $paginator = $filesRepo->findForFirmPaginated($firm, null, $page, 25);
     * 
     * @return Paginator
     */
    public function findForFirmPaginated(Firms $firm, ?Clients $client = null, int $page = 1, int $limit = 25): Paginator
    {
        $qb = $this->createQueryBuilder('f')
            ->andWhere('f.firm = :firm')
            ->setParameter('firm', $firm)
            ->andWhere('f.deletedAt IS NULL')          // ignore soft-deleted files
            ->orderBy('f.uploadedAt', 'DESC');

        if ($client) {
            $qb->andWhere('f.client = :client')->setParameter('client', $client);
        }

        $qb->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        return new Paginator($qb, true);
    }

    /**
     * @param Clients $client
     * 
     * @return array
     */
    public function findUnviewedFilesByClient(Clients $client): array
    {
        $qb = $this->createQueryBuilder('f')
            ->where('f.client = :client')
            ->andWhere('f.uploadedByFirmUser IS NOT NULL AND f.lastAccessedAt is NULL')
            ->setParameter('client', $client);

        return $qb->getQuery()->getResult();
    }

    /**
     * @param FirmUserProfiles $fup
     * @param ?string $filter
     * 
     * @return QueryBuilder
     */
    public function qbVisibleToUser(FirmUserProfiles $fup, ?string $filter = 'all'): QueryBuilder
    {
        $qb = $this->createQueryBuilder('f')
            ->andWhere('f.firm = :firm')
            ->setParameter('firm', $fup->getFirm());

        if ($filter == 'received') {
            $cutoffDate = new \DateTimeImmutable('-30 days');

            $qb->andWhere('f.createdAt >= :cutoffDate')
                ->setParameter('cutoffDate', $cutoffDate)
                ->andWhere('f.uploadedByClientUser IS NOT NULL and f.client IS NOT NULL');
        }

        if ($filter == 'sent') {
            $cutoffDate = new \DateTimeImmutable('-30 days');

            $qb->andWhere('f.createdAt >= :cutoffDate')
                ->setParameter('cutoffDate', $cutoffDate)
                ->andWhere('f.uploadedByFirmUser IS NOT NULL AND f.client IS NOT NULL')
            ;
        }

        // Admins / primary users see everything
        if ($fup->getUserType() == 'admin' || $fup->getUserType() == 'primary') {
            return $qb;
        }

        // Firm employees
        if ($fup->getUserType() == 'employee') {
            $assignedClients = $fup->getAssignedClients();

            $or = $qb->expr()->orX();

            // 1. Files the employee uploaded
            $or->add('f.uploadedByFirmUser = :fup');

            // 2. Client files where client is assigned
            if (!$assignedClients->isEmpty()) {
                $or->add('f.client IN (:clients)');
                $qb->setParameter('clients', $assignedClients);
            }

            // 3. Outside uploads via file requests created by this employee
            $qb
                ->leftJoin('f.fileRequest', 'fr')
                ->leftJoin('fr.firmUserProfile', 'frFup');

            $or->add('frFup = :fup');
            $qb->andWhere($or)
                ->setParameter('fup', $fup);
        }

        return $qb;
    }

    public function findVisibleDuplicateGroups(FirmUserProfiles $fup): array
    {
        $qb = $this->qbVisibleToUser($fup);

        $qb->select('f.checksumSha256 AS checksum')
            ->addSelect('COUNT(f.idFile) AS fileCount')
            ->addSelect('SUM(f.sizeBytes) AS totalBytes')
            ->andWhere('f.checksumSha256 IS NOT NULL')
            ->groupBy('f.checksumSha256')
            ->having('COUNT(f.idFile) > 1')
            ->orderBy('totalBytes', 'DESC');

        return $qb->getQuery()->getArrayResult();
    }

    public function qbVisibleDuplicateFiles(FirmUserProfiles $fup): QueryBuilder
    {
        $duplicateChecksums = $this->findVisibleDuplicateGroups($fup);
        $checksums = array_column($duplicateChecksums, 'checksum');

        $qb = $this->qbVisibleToUser($fup);

        if ($checksums === []) {
            return $qb->andWhere('1 = 0');
        }

        return $qb
            ->andWhere('f.checksumSha256 IN (:duplicateChecksums)')
            ->setParameter('duplicateChecksums', $checksums)
            ->orderBy('f.checksumSha256', 'ASC')
            ->addOrderBy('f.createdAt', 'ASC');
    }
}
