<?php

namespace App\Repository;

use App\Entity\Message;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    /** Full chronological thread between two users. */
    public function getConversation(string $u1, string $u2): array
    {
        return $this->createQueryBuilder('m')
            ->where('(m.senderUsername = :a AND m.receiverUsername = :b)')
            ->orWhere('(m.senderUsername = :b AND m.receiverUsername = :a)')
            ->setParameter('a', $u1)
            ->setParameter('b', $u2)
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()->getResult();
    }

    /**
     * Most-recent message per peer for $me — the "inbox" view.
     * Returns rows: ['peer' => string, 'last_message' => string, 'last_at' => DateTimeImmutable, 'unread' => int].
     */
    public function getRecentConversations(string $me): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = <<<SQL
            SELECT peer,
                   MAX(created_at) AS last_at,
                   SUBSTRING_INDEX(GROUP_CONCAT(content ORDER BY created_at DESC SEPARATOR '\\n---\\n'), '\\n---\\n', 1) AS last_message,
                   SUM(CASE WHEN receiver_username = :me AND is_read = 0 THEN 1 ELSE 0 END) AS unread
            FROM (
                SELECT CASE WHEN sender_username = :me THEN receiver_username ELSE sender_username END AS peer,
                       content, created_at, sender_username, receiver_username, is_read
                FROM sf_messages
                WHERE sender_username = :me OR receiver_username = :me
            ) t
            GROUP BY peer
            ORDER BY last_at DESC
        SQL;

        return $conn->executeQuery($sql, ['me' => $me])->fetchAllAssociative();
    }

    /** Mark all incoming messages from $peer to $me as read. */
    public function markThreadRead(string $me, string $peer): int
    {
        return $this->createQueryBuilder('m')
            ->update()
            ->set('m.isRead', ':r')
            ->where('m.receiverUsername = :me')
            ->andWhere('m.senderUsername = :peer')
            ->andWhere('m.isRead = :u')
            ->setParameter('r', true)
            ->setParameter('u', false)
            ->setParameter('me', $me)
            ->setParameter('peer', $peer)
            ->getQuery()->execute();
    }

    public function countUnreadFor(string $me): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.receiverUsername = :me')
            ->andWhere('m.isRead = :u')
            ->setParameter('me', $me)
            ->setParameter('u', false)
            ->getQuery()->getSingleScalarResult();
    }
}
