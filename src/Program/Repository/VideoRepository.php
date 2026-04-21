<?php

namespace App\Program\Repository;

use Doctrine\DBAL\Connection;

final class VideoRepository
{
	private string $table = 'program_videos';

	public function __construct(private Connection $connection) {}

	/**
	 * @param $params
	 * @return array|null
	 */
	public function fetchForBootstrapTable($params): ?array
	{
		$qb = $this->connection->createQueryBuilder()
			->select('id', 'name', 'path', 'lenght', 'showed')
			->from($this->table)
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
	 * @param int $show_id
	 * @return array
	 */
	public function fetchForBootstrapSelectByShowId(int $show_id): array
	{
		$rows = $this->connection->createQueryBuilder()
			->select('program_videos.id', 'program_videos.name')
			->from($this->table)
			->leftJoin('program_videos', 'program', 'program', 'program.video_id = program_videos.id')
			->leftJoin('program', 'program2shows', 'program2shows', 'program2shows.program_id = program.id')
			->leftJoin('program2shows', 'program_shows', 'program_shows', 'program_shows.id = program2shows.show_id')
			->where('program.premiere = 1')
			->andWhere('program_shows.id = :show_id')
			->andWhere('time < NOW()')
			->setParameter('show_id', $show_id)
			->orderBy('program.time', 'DESC')
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

	public function getPaginatorByShow(int $show_id, int $page, int $limit): array
	{
		$offset = ($page - 1) * $limit;

		return $this->connection->createQueryBuilder()
			->select('program_videos.id', 'program_videos.name', 'program_videos.path', 'program_videos.duration',
				'program.title', 'program.short_description', 'program.description', 'program.url', 'program.time',
				'program_shows.id AS show_id'
			)
			->from('program_videos')
			->leftJoin('program_videos', 'program', 'program', 'program.video_id = program_videos.id')
			->leftJoin('program', 'program2shows', 'program2shows', 'program2shows.program_id = program.id')
			->leftJoin('program2shows', 'program_shows', 'program_shows', 'program_shows.id = program2shows.show_id')
			->where('program.premiere = 1')
			->andWhere('program_shows.id = :show_id')
			->andWhere('program.time < NOW()')
			->setParameter('show_id', $show_id)
			->orderBy('program.time', 'DESC')
			->setFirstResult($offset)
			->setMaxResults($limit)
			->fetchAllAssociative();
	}

	public function getCountByShow(int $show_id): int
	{
		return (int) $this->connection->createQueryBuilder()
			->select('COUNT(*)')
			->from('program_videos')
			->leftJoin('program_videos', 'program', 'program', 'program.video_id = program_videos.id')
			->leftJoin('program', 'program2shows', 'program2shows', 'program2shows.program_id = program.id')
			->leftJoin('program2shows', 'program_shows', 'program_shows', 'program_shows.id = program2shows.show_id')
			->where('program.premiere = 1')
			->andWhere('program_shows.id = :show_id')
			->andWhere('program.time < NOW()')
			->setParameter('show_id', $show_id)
			->fetchOne();
	}

	public function getNewVideosForWeb(int $limit): array
	{
		$rows = $this->connection->createQueryBuilder()
			->select(
				'program_videos.name', 'program_videos.duration',
				'program.time', 'program.title', 'program.short_description', 'program.url',
				'program_shows.url AS show_url'
			)
			->from('program_videos')
			->innerJoin('program_videos', 'program', 'program', 'program.video_id = program_videos.id')
			->innerJoin('program', 'program2shows', 'program2shows', 'program2shows.program_id = program.id')
			->innerJoin('program2shows', 'program_shows', 'program_shows', 'program_shows.id = program2shows.show_id')
			->where('program.premiere = 1')
			->orderBy('program.time', 'DESC')
			->setMaxResults($limit)
			->fetchAllAssociative();

		foreach ($rows as $i => $row) {
			$rows[$i]['url'] = '/porady/' . $row['show_url'] . '/' . $row['url'];
			$short_desc = (string) ($row['short_description'] ?? '');
			$rows[$i]['anotation'] = mb_substr($short_desc, 0, 160, 'UTF-8') . ((mb_strlen($short_desc, 'UTF-8') > 160) ? '...' : '');
			$rows[$i]['image'] = '/data/program/thumbs/' . $row['name'] . '.jpg';
			unset(
				$rows[$i]['name'],
				$rows[$i]['show_url'],
				$rows[$i]['short_description']
			);
		}
		return $rows;
	}

	public function getMostWatchedShowsForWeb(int $limit): array
	{
		$rows = $this->connection->createQueryBuilder()
			->select(
				'program_videos.name',
				'program.time', 'program.title', 'program.short_description AS anotation', 'program.url', 'program.time AS date',
				'program_shows.url AS show_url'
			)
			->from('program_videos')
			->innerJoin('program_videos', 'program', 'program', 'program.video_id = program_videos.id')
			->innerJoin('program', 'program2shows', 'program2shows', 'program2shows.program_id = program.id')
			->innerJoin('program2shows', 'program_shows', 'program_shows', 'program_shows.id = program2shows.show_id')
			->where('program.premiere = 1')
			->andWhere('DATE(program.time) >= DATE(DATE_ADD(NOW(), INTERVAL -3 DAY))')
			->andWhere('DATE(program.time) <= NOW()')
			->orderBy('program_videos.showed', 'DESC')
			->setMaxResults($limit)
			->fetchAllAssociative();

		foreach ($rows as $i => $iValue) {
			$rows[$i]['url'] = '/porady/' . $iValue['show_url'] . '/' . $iValue['url'];
			$rows[$i]['anotation'] = '';
			if ($iValue['anotation']) {
				$rows[$i]['anotation'] = mb_substr($iValue['anotation'], 0, 160, 'UTF-8') . ((mb_strlen($iValue['anotation'], 'UTF-8') > 160) ? '...' : '');
			}
			$rows[$i]['image'] = '/data/program/thumbs/' . $iValue['name'] . '.jpg';
			unset(
				$iValue['name'],
				$iValue['show_url']
			);
		}
		return $rows;
	}
}
