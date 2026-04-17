<?php

namespace App\User\Repository;

use Doctrine\DBAL\Connection;

final class UserRepository
{
	public function __construct(
		private Connection $connection,
	) {}

	public function getCount(bool $activeOnly = false): int
	{
		$qb = $this->connection->createQueryBuilder()
			->select('COUNT(*)')
			->from('user');

		if ($activeOnly) {
			$qb->innerJoin('user', 'authorization', 'a', 'user.authorization_id = a.id')
				->andWhere('a.active = 1');
		}

		return (int) $qb->fetchOne();
	}

	public function findPostBy(string $column, int|string $value): ?array
	{
		return $this->connection->createQueryBuilder()
			->select('*')
			->from('user')
			->where("$column = :value")
			->setParameter('value', $value)
			->fetchAssociative() ?: null;
	}

	public function updatePost(int $id, array $data): void
	{
		$this->connection->update('user', $data, ['id' => $id]);
	}

	/**
	 * Pro bootstrap-table AJAX endpoint.
	 * @param array $params Query parametry z bootstrap-table (sort, order, offset, limit, search)
	 * @return array
	 */
	public function fetchForBootstrapTable(array $params): array
	{
		$qb = $this->connection->createQueryBuilder()
			->select(
				'u.id',
				'u.authorization_id',
				'u.first_name',
				'u.last_name',
				'u.image',
				'u.created_date',
				'u.updated_date',
				'u.created_user',
				'u.updated_user',
				'a.username',
				'a.active'
			)
			->from('user', 'u')
			->innerJoin('u', 'authorization', 'a', 'u.authorization_id = a.id');

		// Search
		$search = $params['search'] ?? '';
		if ($search !== '') {
			$qb->andWhere(
				$qb->expr()->or(
					$qb->expr()->like('a.username', ':search'),
					$qb->expr()->like('u.first_name', ':search'),
					$qb->expr()->like('u.last_name', ':search'),
				)
			)->setParameter('search', "%$search%");
		}

		// Sort
		$sort = $params['sort'] ?? 'username';
		$order = $params['order'] ?? 'asc';
		$allowedSort = ['id', 'username', 'active', 'first_name', 'last_name', 'created_date', 'updated_date', 'created_user', 'updated_user'];
		if (in_array($sort, $allowedSort, true)) {
			$sortColumn = match($sort) {
				'username' => 'a.username',
				'active' => 'a.active',
				default => 'u.' . $sort,
			};
			$qb->orderBy($sortColumn, $order === 'desc' ? 'DESC' : 'ASC');
		}

		// Pagination
		$offset = (int) ($params['offset'] ?? 0);
		$limit = (int) ($params['limit'] ?? 25);
		$qb->setFirstResult($offset)->setMaxResults($limit);

		$rows = $qb->fetchAllAssociative();

		// Přidat role ke každému řádku
		foreach ($rows as &$row) {
			$row['role'] = $this->getRolesForAuthorization((int) $row['authorization_id']);
		}

		return $rows;
	}

	public function getCountForBootstrapTable(array $params): int
	{
		$qb = $this->connection->createQueryBuilder()
			->select('COUNT(*)')
			->from('user', 'u')
			->innerJoin('u', 'authorization', 'a', 'u.authorization_id = a.id');

		$search = $params['search'] ?? '';
		if ($search !== '') {
			$qb->andWhere(
				$qb->expr()->or(
					$qb->expr()->like('a.username', ':search'),
					$qb->expr()->like('u.first_name', ':search'),
					$qb->expr()->like('u.last_name', ':search'),
				)
			)->setParameter('search', "%$search%");
		}

		return (int) $qb->fetchOne();
	}

	public function getRolesForUser(int $authorizationId): array
	{
		return $this->getRolesForAuthorization($authorizationId);
	}

	private function getRolesForAuthorization(int $authorizationId): array
	{
		return $this->connection->createQueryBuilder()
			->select('r.role')
			->from('authorization2role', 'ar')
			->innerJoin('ar', 'authorization_role', 'r', 'ar.role_id = r.id')
			->where('ar.authorization_id = :id')
			->setParameter('id', $authorizationId)
			->fetchFirstColumn();
	}
}
