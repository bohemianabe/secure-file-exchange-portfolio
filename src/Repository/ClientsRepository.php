<?php

namespace App\Repository;

use App\Entity\Clients;
use App\Entity\FirmUserProfiles;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Clients>
 */
class ClientsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Clients::class);
    }


    /**
     * @param Clients $client
     * 
     * @return int
     */
    public function getUsedStorageBytesIncludingDeleted(Clients $client): int
    {
        $em = $this->getEntityManager();

        // Disable the soft delete filter
        $em->getFilters()->disable('soft_delete');

        $qb = $em->createQueryBuilder()
            ->select('SUM(f.sizeBytes)')
            ->from('App\Entity\Files', 'f')
            ->where('f.client = :client')
            ->setParameter('client', $client);

        $result = (int) $qb->getQuery()->getSingleScalarResult();

        // IMPORTANT: re-enable the filter
        $em->getFilters()->enable('soft_delete');

        return $result;
    }

    //    /**
    //     * @return Clients[] Returns an array of Clients objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('c.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Clients
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
