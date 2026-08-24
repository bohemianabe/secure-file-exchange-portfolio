<?php

namespace App\Repository;

use App\Entity\Clients;
use App\Entity\Conversations;
use App\Entity\Firms;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Conversations>
 */
class ConversationsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Conversations::class);
    }

    /**
     * @param User $user
     * @param \DateTimeInterface $sinceLoginAt
     * 
     * @return array
     */
    public function findUnreadConversationNotificationsForUser(User $user, \DateTimeInterface $sinceLoginAt): array
    {
        $qb = $this->createQueryBuilder('convo');

        // ag: SELECT MAX(msg2.createdAt) will return the latest msg within each conversation
        return $qb
            ->select('
            convo.idConversation AS conversationId,
            client.idClient AS clientId,
            client.name AS clientName,
            convo.subject AS subject,
            msg.idMessage AS latestMessageId,
            msg.body AS latestMessage,
            msg.createdAt AS createdAt,
            p.isMuted
        ')
            ->join('convo.participants', 'p')
            ->join('convo.messages', 'msg')
            ->join('convo.client', 'client')
            ->where('p.user = :user')
            ->andWhere('convo.isArchived = false')
            ->andWhere('msg.sender != :user')
            ->andWhere('msg.createdAt > :sinceLoginAt')
            ->andWhere('p.lastReadAt IS NULL OR msg.createdAt > p.lastReadAt')
            ->andWhere('msg.createdAt = (
            SELECT MAX(msg2.createdAt)
            FROM App\Entity\Messages msg2
            WHERE msg2.conversation = convo
              AND msg2.sender != :user
              AND msg2.createdAt > :sinceLoginAt
        )')
            ->setParameter('user', $user)
            ->setParameter('sinceLoginAt', $sinceLoginAt)
            ->orderBy('msg.createdAt', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * @param Clients $client
     * @param User $user
     * 
     * @return array
     */
    public function findActiveConversationsForClientAndUser(Clients $client, User $user): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.participants', 'cp')
            ->andWhere('c.client = :client')
            ->andWhere('c.isArchived = false')
            ->andWhere('cp.user = :user')
            ->setParameter('client', $client)
            ->setParameter('user', $user)
            // ->orderBy('c.lastMessageAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param Firms $firm
     * @param Clients $client
     * @param User $firmUser
     * 
     * @return array
     */
    public function findActiveConversationsWithFirmUserLastReadAt(Firms $firm, Clients $client, User $firmUser): array
    {
        return $this->createQueryBuilder('c')
            ->select('c AS conversation')
            ->addSelect('firmParticipant.lastReadAt AS firmUserLastReadAt')
            ->innerJoin('c.participants', 'firmParticipant')
            ->where('c.firm = :firm')
            ->andWhere('c.client = :client')
            ->andWhere('c.isArchived = false')
            ->andWhere('firmParticipant.user = :firmUser')
            ->setParameter('firm', $firm)
            ->setParameter('client', $client)
            ->setParameter('firmUser', $firmUser)
            ->getQuery()
            ->getResult();
    }

    /**
     *  Returns an array of active conversations.
     * 
     * @param Firms $firm Firms entity
     * @param Clients $client Clients entity
     * @param User $firmUser User entity optional filter
     * @param User $clientUser User entity optional filter
     * @param array $excludedRoles array optional filter
     * 
     * @return array Returns an array of Conversations objects
     */
    public function findActiveConversationsBetweenFirmUserAndClient(Firms $firm, Clients $client, ?User $firmUser = null, ?User $clientUser = null, ?array $excludedRoles = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->innerJoin('c.participants', 'cp')
            ->andWhere('c.firm = :firm')
            ->andWhere('c.client = :client')
            ->andWhere('c.isArchived = false')
            ->setParameter('firm', $firm)
            ->setParameter('client', $client);

        // Filter by firm user
        if ($firmUser) {
            $qb->andWhere('cp.user = :firmUser')
                ->setParameter('firmUser', $firmUser);
        }

        // Filter by client user (separate alias!)
        if ($clientUser) {
            $qb->innerJoin('c.participants', 'cpClient')
                ->andWhere('cpClient.user = :clientUser')
                ->setParameter('clientUser', $clientUser);
        }

        // Exclude roles
        if (!empty($excludedRoles)) {
            $qb->andWhere('cp.role NOT IN (:excludedRoles)')
                ->setParameter('excludedRoles', $excludedRoles);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @param Firms $firm
     * @param Clients $client
     * @param User $firmUser
     * @param User $clientUser
     * 
     * @return ?Conversations
     */
    public function findOneConversationBetweenFirmUserAndClientUser(Firms $firm, Clients $client, User $firmUser, User $clientUser): ?Conversations
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.participants', 'pFirm')
            ->innerJoin('c.participants', 'pClient')
            ->andWhere('c.firm = :firm')
            ->andWhere('c.client = :client')
            ->andWhere('c.isArchived = false')
            ->andWhere('pFirm.user = :firmUser')
            ->andWhere('pClient.user = :clientUser')
            ->setParameter('firm', $firm)
            ->setParameter('client', $client)
            ->setParameter('firmUser', $firmUser)
            ->setParameter('clientUser', $clientUser)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
