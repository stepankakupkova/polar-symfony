<?php

namespace App\Program\Repository;

use Doctrine\DBAL\Connection;

final class ShowexRepository
{
	private string $table = 'special_shows';
	private string $tableShowsCategories = 'program_shows_categories';

	public function __construct(private Connection $connection) {}

	/**
	 * @param $params
	 * @return array|null
	 */
	public function fetchForBootstrapTable($params): ?array
	{
		$qb = $this->connection->createQueryBuilder()
			->select(
				'special_shows.id', 'special_shows.title', 'special_shows.url', 'special_shows.short_description',
				'special_shows.image', 'special_shows.thumb',
				'special_shows.status', 'special_shows.content', 'special_shows.seo_keywords',
				'special_shows.seo_description', 'special_shows.`order`',
				'program_shows_categories.title AS category_name'
			)
			->from($this->table)
			->leftJoin('special_shows', 'program_shows_categories', 'program_shows_categories', 'program_shows_categories.id = special_shows.category_id')
			->orderBy($params['sort'] === 'order' ? '`order`' : $params['sort'], $params['order']);

		if (isset($params['limit'])) {
			$qb->setMaxResults((int) $params['limit']);
		}

		if (isset($params['offset'])) {
			$qb->setFirstResult((int) $params['offset']);
		}

		if (isset($params['search']) && $params['search'] !== '') {
			$qb->andWhere('MATCH (special_shows.title, special_shows.short_description, special_shows.content) AGAINST (:search IN BOOLEAN MODE)')
				->setParameter('search', $params['search'] . '*');
		}

		$resultSet = $qb->fetchAllAssociative();

		foreach ($resultSet as $i => $row) {
			$resultSet[$i]['id'] = (int) $row['id'];
			$resultSet[$i]['status'] = (bool) $row['status'];
			$resultSet[$i]['order'] = (int) $row['order'];
		}

		return $resultSet ?: null;
	}

	/**
	 * @param int $limit
	 * @return array
	 */
	public function fetchForBootstrapSelect(int $limit): array
	{
		$rows = $this->connection->createQueryBuilder()
			->select('id', 'title')
			->from($this->table)
			->orderBy('title', 'ASC')
			->setMaxResults($limit)
			->fetchAllAssociative();

		$data = [['value' => null, 'label' => null]];
		foreach ($rows as $item) {
			$data[] = ['value' => $item['id'], 'label' => $item['title']];
		}
		return $data;
	}

	/**
	 * @param string $column
	 * @param int|string $value
	 * @return array|null
	 */
	public function findPostBy(string $column, int|string $value): ?array
	{
		$result = $this->connection->createQueryBuilder()
			->select('*')
			->from($this->table)
			->where($column . ' = :value')
			->setParameter('value', $value)
			->fetchAssociative();

		return $result ?: null;
	}

	/**
	 * @param bool|null $active
	 * @return int
	 */
	public function getCount(?bool $active = null): int
	{
		$qb = $this->connection->createQueryBuilder()
			->select('COUNT(*) AS count')
			->from($this->table);

		if ($active !== null) {
			$qb->andWhere('status = :status')
				->setParameter('status', true);
		}

		return (int) $qb->fetchOne();
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
			$qb->andWhere('MATCH (special_shows.title, special_shows.short_description, special_shows.content) AGAINST (:search IN BOOLEAN MODE)')
				->setParameter('search', $params['search'] . '*');
		}

		return (int) $qb->fetchOne();
	}

	/**
	 * @return array
	 */
	public function fetchCategoryForBootstrapSelect(): array
	{
		$rows = $this->connection->createQueryBuilder()
			->select('id', 'title')
			->from($this->tableShowsCategories)
			->orderBy('id', 'ASC')
			->fetchAllAssociative();

		$data = [['value' => null, 'label' => null]];
		foreach ($rows as $item) {
			$data[] = ['value' => $item['id'], 'label' => $item['title']];
		}
		return $data;
	}

	/**
	 * @param array $data
	 * @return int
	 */
	public function insertPost(array $data): int
	{
		$this->connection->insert($this->table, $data);

		return (int) $this->connection->lastInsertId();
	}

	/**
	 * @param int $id
	 * @param array $data
	 */
	public function updatePost(int $id, array $data): void
	{
		$this->connection->update($this->table, $data, ['id' => $id]);
	}

	/**
	 * @return array
	 */
	public function fetchAll(): array
	{
		return $this->connection->createQueryBuilder()
			->select('id', 'title', '`order`')
			->from($this->table)
			->orderBy('`order`', 'ASC')
			->fetchAllAssociative();
	}

	/**
	 * @param int $id
	 */
	public function deletePost(int $id): void
	{
		$this->connection->delete($this->table, ['id' => $id]);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function fetchForConfig(): array
	{
		$rows = $this->connection->createQueryBuilder()
			->select('id', 'title', 'url', '`order`')
			->from($this->table)
			->where('status = 1')
			->orderBy('`order`', 'ASC')
			->fetchAllAssociative();

		$data = [];
		foreach ($rows as $show) {
			$data[] = [
				'id' => (int) $show['id'],
				'title' => $show['title'],
				'url' => $show['url'],
				'order' => 1 + (int) $show['order'],
			];
		}

		return $data;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function fetchRoutesForConfig(): array
	{
		$rows = $this->connection->createQueryBuilder()
			->select('id', 'url')
			->from($this->table)
			->where('status = 1')
			->orderBy('`order`', 'ASC')
			->fetchAllAssociative();

		$data = [];
		foreach ($rows as $show) {
			$data[] = [
				'id' => (int) $show['id'],
				'url' => $show['url'],
			];
		}

		return $data;
	}
}
