<?php declare(strict_types=1);

namespace ContributeEnhancements\Service;

use Doctrine\DBAL\Connection;

class ArchiveService
{
    protected Connection $connection;

    /** @var array<int, bool> */
    protected array $cache = [];

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function isArchived(int $contributionId): bool
    {
        if (array_key_exists($contributionId, $this->cache)) {
            return $this->cache[$contributionId];
        }

        $archived = (bool) $this->connection->fetchOne(
            'SELECT 1 FROM contribute_enhancements_archive WHERE contribution_id = ?',
            [$contributionId]
        );

        $this->cache[$contributionId] = $archived;

        return $archived;
    }

    /**
     * @return int[]
     */
    public function archivedIds(): array
    {
        $ids = $this->connection->fetchFirstColumn(
            'SELECT contribution_id FROM contribute_enhancements_archive'
        );

        return array_map('intval', $ids ?: []);
    }

    public function archive(int $contributionId, ?int $userId = null): void
    {
        $now = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $this->connection->executeStatement(
            'INSERT INTO contribute_enhancements_archive (contribution_id, archived_at, archived_by_id)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE archived_at = VALUES(archived_at), archived_by_id = VALUES(archived_by_id)',
            [$contributionId, $now, $userId]
        );
        $this->cache[$contributionId] = true;
    }

    public function unarchive(int $contributionId): void
    {
        $this->connection->delete('contribute_enhancements_archive', [
            'contribution_id' => $contributionId,
        ]);
        $this->cache[$contributionId] = false;
    }
}
