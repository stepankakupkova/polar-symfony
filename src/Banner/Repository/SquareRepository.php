<?php
/*
 * @project ferritenergy
 * @author Rostislav Greipel
 * @link https://rogr.cz
 * @copyright (c) 2011 - 2021 ROGR All Rights Reserved
 * @license https://rogr.cz/license/eula EULA License
 */

declare(strict_types=1);

namespace App\Banner\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

final class SquareRepository
{
    private string $table = 'banner_square';

    public function __construct(private Connection $connection) {}

    /**
     * @throws Exception
     */
    public function fetchForBootstrapTable(array $params): ?array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->table)
            ->where('lang = :lang')
            ->setParameter('lang', $params['lang'])
            ->orderBy($params['sort'] ?? 'rank', $params['order'] ?? 'asc');

        if (isset($params['limit'])) {
            $qb->setMaxResults((int) $params['limit']);
        }
        if (isset($params['offset'])) {
            $qb->setFirstResult((int) $params['offset']);
        }
        if (isset($params['search']) && $params['search'] !== '') {
            $qb->andWhere('MATCH (title, link) AGAINST (:search IN BOOLEAN MODE)')
               ->setParameter('search', $params['search'] . '*');
        }

        return $qb->executeQuery()->fetchAllAssociative() ?: null;
    }

    /**
     * @throws Exception
     */
    public function getCountForBootstrapTable(array $params): int
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from($this->table)
            ->where('lang = :lang')
            ->setParameter('lang', $params['lang']);

        if (isset($params['search']) && $params['search'] !== '') {
            $qb->andWhere('MATCH (title, link) AGAINST (:search IN BOOLEAN MODE)')
               ->setParameter('search', $params['search'] . '*');
        }

        return (int) $qb->executeQuery()->fetchOne();
    }

    /**
     * @throws Exception
     */
    public function getCount(?bool $active = null): int
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from($this->table);

        if ($active !== null) {
            $qb->where('active = :active')
               ->setParameter('active', (int) $active);
        }

        return (int) $qb->executeQuery()->fetchOne();
    }

    /**
     * @throws Exception|\RuntimeException|\InvalidArgumentException
     */
    public function findPostBy(string $column, int|string $value): array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select(
                '*',
                'SUBSTRING_INDEX(`public_from`, \' \', 1) AS public_from',
                'SUBSTRING_INDEX(`public_from`, \' \', -1) AS public_from_time',
                'SUBSTRING_INDEX(`public_to`, \' \', 1) AS public_to',
                'SUBSTRING_INDEX(`public_to`, \' \', -1) AS public_to_time'
            )
            ->from($this->table)
            ->where($column . ' = :value')
            ->setParameter('value', $value);

        $row = $qb->executeQuery()->fetchAssociative();

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
    public function insertPost(array $data): int
    {
        $this->connection->insert($this->table, $data);
        return (int) $this->connection->lastInsertId();
    }

    /**
     * @throws Exception
     */
    public function updatePost(int $id, array $data): void
    {
        $this->connection->update($this->table, $data, ['id' => $id]);
    }

    /**
     * @throws Exception
     */
    public function deletePost(int $id): void
    {
        $this->connection->delete($this->table, ['id' => $id]);
    }

    /**
     * @throws Exception
     */
    public function getBannerForWeb(): ?array
    {
        return $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->table)
            ->where('active = 1')
            ->andWhere('public_from <= NOW()')
            ->andWhere('public_to >= NOW()')
            ->orderBy('RAND()')
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative() ?: null;
    }
}
