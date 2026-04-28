<?php

declare(strict_types=1);

namespace App\Camera\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

final class CameraRepository
{
    private string $table = 'cameras';

    public function __construct(private Connection $connection) {}

    /**
     * @throws Exception
     */
    public function fetchAll(): ?array
    {
        $result = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->table)
            ->orderBy('rank', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();

        return $result ?: null;
    }

    /**
     * @throws Exception
     */
    public function fetchAllLimit(int $limit): ?array
    {
        $result = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->table)
            ->orderBy('rank', 'ASC')
            ->setMaxResults($limit)
            ->executeQuery()
            ->fetchAllAssociative();

        return $result ?: null;
    }

    /**
     * @throws Exception
     */
    public function fetchForBootstrapTable(array $params): ?array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('id', 'title', 'description', 'url_m3u8', 'url_mpd', 'rank')
            ->from($this->table)
            ->orderBy($params['sort'] ?? 'rank', $params['order'] ?? 'asc');

        if (isset($params['limit'])) {
            $qb->setMaxResults((int) $params['limit']);
        }
        if (isset($params['offset'])) {
            $qb->setFirstResult((int) $params['offset']);
        }
        if (isset($params['search']) && $params['search'] !== '') {
            $qb->andWhere('MATCH (title, description) AGAINST (:search IN BOOLEAN MODE)')
               ->setParameter('search', $params['search'] . '*');
        }

        $rows = $qb->executeQuery()->fetchAllAssociative();
        if (!$rows) {
            return null;
        }

        foreach ($rows as $i => $row) {
            $rows[$i]['id']   = (int) $row['id'];
            $rows[$i]['rank'] = (int) $row['rank'];
        }

        return $rows;
    }

    /**
     * @throws Exception
     */
    public function getCountForBootstrapTable(array $params): int
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from($this->table);

        if (isset($params['search']) && $params['search'] !== '') {
            $qb->andWhere('MATCH (title, description) AGAINST (:search IN BOOLEAN MODE)')
               ->setParameter('search', $params['search'] . '*');
        }

        return (int) $qb->executeQuery()->fetchOne();
    }

    /**
     * @throws Exception
     * @throws \InvalidArgumentException
     */
    public function findPostBy(string $column, int|string $value): array
    {
        $row = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->table)
            ->where($column . ' = :value')
            ->setParameter('value', $value)
            ->executeQuery()
            ->fetchAssociative();

        if (!$row) {
            throw new \InvalidArgumentException(sprintf(
                'Záznam s identifikátorem "%s" nenalezen. Tabulka "%s".',
                $column . ' => ' . $value,
                $this->table
            ));
        }

        return $row;
    }

    /**
     * @throws Exception
     */
    public function getCount(): int
    {
        return (int) $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from($this->table)
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * Vrátí pole všech kamer pro stránkování (bez Laminas Paginatoru).
     * @throws Exception
     */
    public function getPaginator(): ?array
    {
        return $this->fetchAll();
    }

    /**
     * @throws Exception
     */
    public function insert(array $data): int
    {
        $this->connection->insert($this->table, $data);
        return (int) $this->connection->lastInsertId();
    }

    /**
     * @throws Exception
     */
    public function update(array $data, int $id): void
    {
        $this->connection->update($this->table, $data, ['id' => $id]);
    }

    /**
     * @throws Exception
     */
    public function delete(int $id): void
    {
        $this->connection->delete($this->table, ['id' => $id]);
    }
}
