<?php

declare(strict_types=1);

namespace App\Application\Repository;

use DateTime;
use Doctrine\DBAL\Connection;

class LogRepository
{
	private string $table = 'application_log';

	public function __construct(private Connection $connection) {}

	/**
	 * @param array $data
	 */
	public function insert(array $data): void
	{
		$this->connection->insert($this->table, $data);
	}

	public function fetchForBootstrapTable(array $params): array
	{
		$qb = $this->connection->createQueryBuilder();
		$qb->select('id', 'datetime', 'priority', 'message', 'description', 'user', 'reference', 'file', 'line', 'trace', 'xdebug', 'uri', 'ip', 'session_id')
			->from($this->table);

		$allowedSort = ['id', 'datetime', 'priority', 'message', 'description', 'user'];
		$sort = in_array($params['sort'] ?? '', $allowedSort, true) ? $params['sort'] : 'datetime';
		$order = strtoupper($params['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
		$qb->orderBy($sort, $order);

		if (!empty($params['priority'])) {
			$placeholders = implode(', ', array_fill(0, count($params['priority']), '?'));
			$qb->andWhere('LOWER(priority) IN (' . $placeholders . ')');
			foreach (array_values($params['priority']) as $i => $val) {
				$qb->setParameter($i, $val);
			}
		}

		if (!empty($params['search'])) {
			$search = '+' . implode('+', explode(' ', $params['search']));
			$qb->andWhere('MATCH (message, description, user) AGAINST (:search IN BOOLEAN MODE)')
				->setParameter('search', '"' . $search . '"');
		}

		if (isset($params['limit'])) {
			$qb->setMaxResults((int) $params['limit']);
		}

		if (isset($params['offset'])) {
			$qb->setFirstResult((int) $params['offset']);
		}

		return $qb->fetchAllAssociative();
	}

	public function getCountForBootstrapTable(array $params): int
	{
		$qb = $this->connection->createQueryBuilder();
		$qb->select('COUNT(*) AS cnt')
			->from($this->table);

		if (!empty($params['priority'])) {
			$placeholders = implode(', ', array_fill(0, count($params['priority']), '?'));
			$qb->andWhere('LOWER(priority) IN (' . $placeholders . ')');
			foreach (array_values($params['priority']) as $i => $val) {
				$qb->setParameter($i, $val);
			}
		}

		if (!empty($params['search'])) {
			$search = '+' . implode('+', explode(' ', $params['search']));
			$qb->andWhere('MATCH (message, description, user) AGAINST (:search IN BOOLEAN MODE)')
				->setParameter('search', '"' . $search . '"');
		}

		return (int) $qb->fetchOne();
	}

	public function fetchForDashboard(DateTime $datetime): array
	{
		$qb = $this->connection->createQueryBuilder();
		$qb->select('id', 'datetime', 'priority', 'message', 'description', 'user')
			->from($this->table)
			->where('datetime >= :datetime')
			->setParameter('datetime', $datetime->format('Y-m-d H:i:s'))
			->orderBy('datetime', 'DESC');

		return $qb->fetchAllAssociative();
	}

	public function findById(int $id): ?array
	{
		$qb = $this->connection->createQueryBuilder();
		$qb->select('*')
			->from($this->table)
			->where('id = :id')
			->setParameter('id', $id);

		return $qb->fetchAssociative() ?: null;
	}

	public function deleteById(int $id): void
	{
		$this->connection->delete($this->table, ['id' => $id]);
	}

	public function deleteByPriority(?array $types): void
	{
		if (empty($types)) {
			$this->connection->executeStatement('DELETE FROM ' . $this->table);
			return;
		}

		$placeholders = implode(', ', array_fill(0, count($types), '?'));
		$this->connection->executeStatement(
			'DELETE FROM ' . $this->table . ' WHERE LOWER(priority) IN (' . $placeholders . ')',
			array_values($types)
		);
	}
}
