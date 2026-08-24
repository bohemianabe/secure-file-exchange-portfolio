<?php

namespace App\Repository;

use App\Entity\Conversations;
use App\Entity\Messages;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Messages>
 */
class MessagesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Messages::class);
    }

    /**
     * @param Conversations $conversation
     * @param int $days
     * 
     * @return array of recent msg within a certain number of days
     */
    public function findRecentConversationMessages(Conversations $conversation, int $days = 90): array
    {
        $since = new \DateTimeImmutable("-{$days} days");

        return $this->createQueryBuilder('m')
            ->andWhere('m.conversation = :conversation')
            ->andWhere('m.createdAt >= :since')
            ->setParameter('conversation', $conversation)
            ->setParameter('since', $since)
            ->orderBy('m.createdAt', 'ASC')
            ->addOrderBy('m.idMessage', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param Conversations $conversation 
     * 
     * @return int id of the last message in a conversation
     */
    public function getConversationLastMessageId(Conversations $conversation): int
    {
        return $this->createQueryBuilder('m')
            ->select('m.idMessage')
            ->where('m.conversation = :conversation')
            ->setParameter('conversation', $conversation)
            ->orderBy('m.idMessage', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param Conversations $conversation
     * @param int $messageId
     * 
     * @return Messages[] Returns an array of Messages objects after a certain message id
     */
    public function getConversationMessagesAfterMessageId(Conversations $conversation, int $messageId): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.conversation = :conversation')
            ->andWhere('m.idMessage > :messageId')
            ->setParameter('conversation', $conversation)
            ->setParameter('messageId', $messageId)
            ->orderBy('m.idMessage', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
