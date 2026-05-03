<?php

namespace App\Repository;

use App\Database\PdoConnectionFactory;
use App\Entity\Message;

class MessageRepository
{
    public function __construct(private PdoConnectionFactory $pdoFactory) {}

    private function pdo(): \PDO
    {
        return $this->pdoFactory->getConnection();
    }

    private function hydrate(array $row): Message
    {
        $m = (new Message())
            ->setId((int) $row['id'])
            ->setSenderUsername((string) $row['sender_username'])
            ->setReceiverUsername((string) $row['receiver_username'])
            ->setContent((string) ($row['content'] ?? ''))
            ->setIsRead(!empty($row['is_read']));
        if (!empty($row['story_id'])) {
            $m->setStoryId((int) $row['story_id']);
        }
        if (!empty($row['created_at'])) {
            try { $m->setCreatedAt(new \DateTimeImmutable((string) $row['created_at'])); } catch (\Exception) {}
        }
        return $m;
    }

    public function find(int $id): ?Message
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM messages WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    /** @return Message[] */
    public function getConversation(string $u1, string $u2): array
    {
        // Distinct placeholders per occurrence — MySQL native prepares forbid reuse
        $stmt = $this->pdo()->prepare(
            'SELECT * FROM messages
             WHERE (sender_username = :a1 AND receiver_username = :b1)
                OR (sender_username = :b2 AND receiver_username = :a2)
             ORDER BY created_at ASC'
        );
        $stmt->execute(['a1' => $u1, 'b1' => $u2, 'b2' => $u2, 'a2' => $u1]);
        return array_map(fn($r) => $this->hydrate($r), $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function getRecentConversations(string $me): array
    {
        // Distinct placeholders per occurrence — MySQL native prepares forbid reuse
        $sql = <<<SQL
            SELECT peer,
                   MAX(created_at) AS last_at,
                   SUBSTRING_INDEX(GROUP_CONCAT(content ORDER BY created_at DESC SEPARATOR '\\n---\\n'), '\\n---\\n', 1) AS last_message,
                   SUM(CASE WHEN receiver_username = :me1 AND is_read = 0 THEN 1 ELSE 0 END) AS unread
            FROM (
                SELECT CASE WHEN sender_username = :me2 THEN receiver_username ELSE sender_username END AS peer,
                       content, created_at, sender_username, receiver_username, is_read
                FROM messages
                WHERE sender_username = :me3 OR receiver_username = :me4
            ) t
            GROUP BY peer
            ORDER BY last_at DESC
        SQL;
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute(['me1' => $me, 'me2' => $me, 'me3' => $me, 'me4' => $me]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function markThreadRead(string $me, string $peer): int
    {
        $stmt = $this->pdo()->prepare(
            'UPDATE messages SET is_read = 1
             WHERE receiver_username = :me AND sender_username = :peer AND is_read = 0'
        );
        $stmt->execute(['me' => $me, 'peer' => $peer]);
        return $stmt->rowCount();
    }

    public function countUnreadFor(string $me): int
    {
        $stmt = $this->pdo()->prepare(
            'SELECT COUNT(*) FROM messages WHERE receiver_username = :me AND is_read = 0'
        );
        $stmt->execute(['me' => $me]);
        return (int) $stmt->fetchColumn();
    }

    public function save(Message $m): Message
    {
        $pdo = $this->pdo();
        if ($m->getId() === null) {
            $stmt = $pdo->prepare(
                'INSERT INTO messages (sender_username, receiver_username, content, story_id, is_read, created_at)
                 VALUES (:su, :ru, :c, :sid, :ir, :ca)'
            );
            $stmt->execute([
                'su' => $m->getSenderUsername(),
                'ru' => $m->getReceiverUsername(),
                'c' => $m->getContent(),
                'sid' => $m->getStoryId(),
                'ir' => $m->isRead() ? 1 : 0,
                'ca' => $m->getCreatedAt()->format('Y-m-d H:i:s'),
            ]);
            $m->setId((int) $pdo->lastInsertId());
        } else {
            $stmt = $pdo->prepare('UPDATE messages SET is_read = :ir WHERE id = :id');
            $stmt->execute(['id' => $m->getId(), 'ir' => $m->isRead() ? 1 : 0]);
        }
        return $m;
    }

    public function remove(Message $m): void
    {
        if ($m->getId() === null) return;
        $stmt = $this->pdo()->prepare('DELETE FROM messages WHERE id = :id');
        $stmt->execute(['id' => $m->getId()]);
    }
}
