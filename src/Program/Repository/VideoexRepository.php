<?php

namespace App\Program\Repository;

use Doctrine\DBAL\Connection;

final class VideoexRepository
{
	private string $table = 'special_videos';
	private string $tableParts = 'special_videos_parts';

	public function __construct(private Connection $connection) {}

	/**
	 * @param $params
	 * @return array|null
	 */
	public function fetchForBootstrapTable($params): ?array
	{
		$qb = $this->connection->createQueryBuilder()
			->select(
				'special_videos.id', 'special_videos.name', 'special_videos.title', 'special_videos.path',
				'special_videos.lenght', 'special_videos.showed',
				'special_shows.title AS show_title'
			)
			->from($this->table)
			->leftJoin('special_videos', 'special_shows', 'special_shows', 'special_shows.id = special_videos.show_id')
			->orderBy($params['sort'], $params['order']);

		if (isset($params['limit'])) {
			$qb->setMaxResults((int) $params['limit']);
		}

		if (isset($params['offset'])) {
			$qb->setFirstResult((int) $params['offset']);
		}

		if (isset($params['search']) && $params['search'] !== '') {
			$qb->andWhere('MATCH (name, path) AGAINST (:search IN BOOLEAN MODE)')
				->setParameter('search', $params['search'] . '*');
		}

		$resultSet = $qb->fetchAllAssociative();

		foreach ($resultSet as $i => $row) {
			$resultSet[$i]['id'] = (int) $row['id'];
		}

		return $resultSet ?: null;
	}

	/**
	 * @param $params
	 * @return array|null
	 */
	public function fetchForBootstrapTableParts($params): ?array
	{
		$qb = $this->connection->createQueryBuilder()
			->select('id', 'video_id', 'title', 'sec_from', 'sec_to')
			->from($this->tableParts)
			->where('video_id = :video_id')
			->setParameter('video_id', $params['video_id'])
			->orderBy($params['sort'], $params['order']);

		if (isset($params['limit'])) {
			$qb->setMaxResults((int) $params['limit']);
		}

		if (isset($params['offset'])) {
			$qb->setFirstResult((int) $params['offset']);
		}

		if (isset($params['search']) && $params['search'] !== '') {
			$qb->andWhere('MATCH (title) AGAINST (:search IN BOOLEAN MODE)')
				->setParameter('search', $params['search'] . '*');
		}

		$resultSet = $qb->fetchAllAssociative();

		foreach ($resultSet as $i => $row) {
			$resultSet[$i]['id'] = (int) $row['id'];
			$resultSet[$i]['video_id'] = (int) $row['video_id'];
			$resultSet[$i]['sec_from'] = (int) $row['sec_from'];
			$resultSet[$i]['sec_to'] = (int) $row['sec_to'];
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
			->select('id', 'name')
			->from($this->table)
			->orderBy('id', 'DESC')
			->setMaxResults($limit)
			->fetchAllAssociative();

		$data = [['value' => null, 'label' => null]];
		foreach ($rows as $item) {
			$data[] = ['value' => $item['id'], 'label' => $item['name']];
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
	 * @param int $part_id
	 * @return array|null
	 */
	public function findPartBy(int $part_id): ?array
	{
		$result = $this->connection->createQueryBuilder()
			->select('*')
			->from($this->tableParts)
			->where('id = :id')
			->setParameter('id', $part_id)
			->fetchAssociative();

		return $result ?: null;
	}

	/**
	 * @param string $column
	 * @param int|string $value
	 * @return array
	 */
	public function findPartsBy(string $column, int|string $value): array
	{
		$resultSet = $this->connection->createQueryBuilder()
			->select('*')
			->from($this->tableParts)
			->where($column . ' = :value')
			->setParameter('value', $value)
			->fetchAllAssociative();

		foreach ($resultSet as $i => $row) {
			$resultSet[$i]['id'] = (int) $row['id'];
		}

		return $resultSet;
	}

	/**
	 * @return int
	 */
	public function getCount(): int
	{
		return (int) $this->connection->createQueryBuilder()
			->select('COUNT(*) AS count')
			->from($this->table)
			->fetchOne();
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
			$qb->andWhere('MATCH (name, path) AGAINST (:search IN BOOLEAN MODE)')
				->setParameter('search', $params['search'] . '*');
		}

		return (int) $qb->fetchOne();
	}

	/**
	 * @param $params
	 * @return int|null
	 */
	public function getCountForBootstrapTableParts($params): ?int
	{
		$qb = $this->connection->createQueryBuilder()
			->select('COUNT(*) AS count')
			->from($this->tableParts)
			->where('video_id = :video_id')
			->setParameter('video_id', $params['video_id']);

		if (isset($params['search']) && $params['search'] !== '') {
			$qb->andWhere('MATCH (title) AGAINST (:search IN BOOLEAN MODE)')
				->setParameter('search', $params['search'] . '*');
		}

		return (int) $qb->fetchOne();
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
	 * @param int $id
	 */
	public function deletePost(int $id): void
	{
		$this->connection->delete($this->table, ['id' => $id]);
	}

	/**
	 * @param int $video_id
	 * @param int $sec_from
	 * @param int $sec_to
	 * @param string $title
	 */
	public function insertPostPart(int $video_id, int $sec_from, int $sec_to, string $title): void
	{
		$this->connection->insert($this->tableParts, [
			'video_id' => $video_id,
			'sec_from' => $sec_from,
			'sec_to' => $sec_to,
			'title' => $title,
		]);
	}

	/**
	 * @param int $part_id
	 * @param int $video_id
	 * @param int $sec_from
	 * @param int $sec_to
	 * @param string $title
	 */
	public function updatePostPart(int $part_id, int $video_id, int $sec_from, int $sec_to, string $title): void
	{
		$this->connection->update($this->tableParts, [
			'video_id' => $video_id,
			'sec_from' => $sec_from,
			'sec_to' => $sec_to,
			'title' => $title,
		], ['id' => $part_id]);
	}

	/**
	 * @param int $part_id
	 */
	public function deletePostPart(int $part_id): void
	{
		$this->connection->delete($this->tableParts, ['id' => $part_id]);
	}

	public function getPaginatorByShow(int $show_id, int $page, int $limit): array
	{
		$offset = ($page - 1) * $limit;

		return $this->connection->createQueryBuilder()
			->select('special_videos.id', 'special_videos.show_id', 'special_videos.name', 'special_videos.title',
				'special_videos.url', 'special_videos.path', 'special_videos.time', 'special_videos.duration_sec'
			)
			->from('special_videos')
			->leftJoin('special_videos', 'special_shows', 'special_shows', 'special_shows.id = special_videos.show_id')
			->where('special_shows.id = :show_id')
			->setParameter('show_id', $show_id)
			->orderBy('special_videos.time', 'DESC')
			->setFirstResult($offset)
			->setMaxResults($limit)
			->fetchAllAssociative();
	}

	public function getCountByShow(int $show_id): int
	{
		return (int) $this->connection->createQueryBuilder()
			->select('COUNT(*)')
			->from('special_videos')
			->leftJoin('special_videos', 'special_shows', 'special_shows', 'special_shows.id = special_videos.show_id')
			->where('special_shows.id = :show_id')
			->setParameter('show_id', $show_id)
			->fetchOne();
	}
}
