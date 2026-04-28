<?php

declare(strict_types=1);

namespace App\Election\Repository;

use Doctrine\DBAL\Connection;
use InvalidArgumentException;
use RuntimeException;

class ElectionRepository2024
{
    /**
     * @var string
     */
    private string $table;

    /**
     * @param Connection $connection
     */
    public function __construct(
        private Connection $connection,
    ) {
        $this->table = 'elections_2024';
    }

    /**
     * @return array|null
     */
    public function fetchAll(): ?array
    {
        $result = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->table)
            ->orderBy('rank', 'ASC')
            ->fetchAllAssociative();

        return $result ?: null;
    }

    /**
     * @param $params
     * @return array|null
     */
    public function fetchForBootstrapTable($params): ?array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('e.id', 'e.title', 'e.description', 'e.video_id', 'e.rank', 'pv.name')
            ->from($this->table, 'e')
            ->leftJoin('e', 'program_videos', 'pv', 'pv.id = e.video_id')
            ->orderBy($params['sort'], $params['order']);

        if (isset($params['limit'])) {
            $qb->setMaxResults((int)$params['limit']);
        }

        if (isset($params['offset'])) {
            $qb->setFirstResult((int)$params['offset']);
        }

        if (isset($params['search']) && $params['search'] !== '') {
            $qb->andWhere('MATCH (e.title) AGAINST (:search IN BOOLEAN MODE)')
               ->setParameter('search', $params['search'] . '*');
        }

        $result = $qb->fetchAllAssociative();

        foreach ($result as $i => $row) {
            $result[$i]['id']   = (int)$row['id'];
            $result[$i]['rank'] = (int)$row['rank'];
        }

        return $result ?: null;
    }

    /**
     * @param string $column
     * @param int|string $value
     * @return array
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function findPostBy(string $column, int|string $value): array
    {
        $row = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->table)
            ->where($column . ' = :value')
            ->setParameter('value', $value)
            ->fetchAssociative();

        if (!$row) {
            throw new InvalidArgumentException(sprintf(
                'Záznam s identifikátorem "%s" nenalezen. Tabulka "' . $this->table . '".',
                $column . ' => ' . $value
            ));
        }

        return $row;
    }

    /**
     * @return int
     */
    public function getCount(): int
    {
        $result = $this->connection->createQueryBuilder()
            ->select('COUNT(*) AS count')
            ->from($this->table)
            ->fetchAssociative();

        return (int)($result['count'] ?? 0);
    }

    /**
     * @param $params
     * @return int|null
     */
    public function getCountForBootstrapTable($params): ?int
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('COUNT(*) AS count')
            ->from($this->table);

        if (isset($params['search']) && $params['search'] !== '') {
            $qb->andWhere('MATCH (title, description) AGAINST (:search IN BOOLEAN MODE)')
               ->setParameter('search', $params['search'] . '*');
        }

        $result = $qb->fetchAssociative();

        return isset($result['count']) ? (int)$result['count'] : null;
    }

    /**
     * @param int $limit
     * @return array|null
     */
    public function fetchAllLimit($limit): ?array
    {
        $result = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->table)
            ->orderBy('rank', 'ASC')
            ->setMaxResults($limit)
            ->fetchAllAssociative();

        return $result ?: null;
    }

    /**
     * @return array|null
     */
    public function fetchAllForWeb(): ?array
    {
        $result = $this->connection->createQueryBuilder()
            ->select('e.*', 'pv.name', 'pv.duration', 'p.url AS program_url', 'ps.url')
            ->from($this->table, 'e')
            ->innerJoin('e', 'program_videos', 'pv', 'pv.id = e.video_id')
            ->innerJoin('pv', 'program', 'p', 'p.video_id = pv.id')
            ->innerJoin('p', 'program2shows', 'p2s', 'p2s.program_id = p.id')
            ->innerJoin('p2s', 'program_shows', 'ps', 'ps.id = p2s.show_id')
            ->where('p.premiere = 1')
            ->andWhere('p.time < NOW()')
            ->orderBy('e.rank', 'ASC')
            ->groupBy('e.id')
            ->fetchAllAssociative();

        return $result ?: null;
    }

    /**
     * @param int $video_id
     * @return array|null
     */
    public function fetchStudioForWeb($video_id): ?array
    {
        $result = $this->connection->createQueryBuilder()
            ->select('e.*', 'pv.id', 'pv.name', 'pv.path')
            ->from($this->table, 'e')
            ->innerJoin('e', 'program_videos', 'pv', 'pv.id = e.video_id')
            ->where('e.video_id = :video_id')
            ->setParameter('video_id', $video_id)
            ->fetchAssociative();

        return $result ?: null;
    }
}
