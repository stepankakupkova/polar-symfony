<?php

namespace App\Program\Repository;

use Doctrine\DBAL\Connection;

final class ProgramRepository
{
	public function __construct(private Connection $connection) {}

	public function searchPaginator(string $search, int $page, int $limit): array
	{
		$offset = ($page - 1) * $limit;

		$search2 = mb_strtolower($search, 'UTF-8');
		$searchAr = explode(' ', $search2);
		$search2 = '';
		foreach ($searchAr as $item) {
			// zakázat hledání krátkých slov
			if (mb_strlen($item, 'UTF-8') < 3) {
				continue;
			}
			$search2 .= '+' . $item;
		}

		$qb = $this->connection->createQueryBuilder()
			->select(
				'pv.id', 'pv.name', 'pv.path', 'pv.duration',
				'p.title', 'p.short_description', 'p.description', 'p.url', 'p.time AS date', 'p.premiere', 'p.overwrite',
				'ps.id AS show_id', 'ps.url AS show_url'
			)
			->from('program_videos', 'pv')
			->innerJoin('pv', 'program', 'p', 'p.video_id = pv.id')
			->innerJoin('p', 'program2shows', 'p2s', 'p2s.program_id = p.id')
			->innerJoin('p2s', 'program_shows', 'ps', 'ps.id = p2s.show_id')
			->where('ps.status = 1')
			->andWhere('ps.show_in_archive = 1')
			->andWhere('p.premiere = 1')
			->andWhere('p.time < NOW()')
			->orderBy('p.time', 'DESC')
			->setFirstResult($offset)
			->setMaxResults($limit);

		if ($search2) {
			$qb->andWhere('MATCH (p.title, p.short_description, p.description, p.overwrite) AGAINST (:search IN BOOLEAN MODE)')
				->setParameter('search', $search2);
		}

		return $qb->fetchAllAssociative();
	}

	public function searchPaginatorCount(string $search): int
	{
		$search2 = mb_strtolower($search, 'UTF-8');
		$searchAr = explode(' ', $search2);
		$search2 = '';
		foreach ($searchAr as $item) {
			// zakázat hledání krátkých slov
			if (mb_strlen($item, 'UTF-8') < 3) {
				continue;
			}
			$search2 .= '+' . $item;
		}

		$qb = $this->connection->createQueryBuilder()
			->select('COUNT(*) AS total')
			->from('program_videos', 'pv')
			->innerJoin('pv', 'program', 'p', 'p.video_id = pv.id')
			->innerJoin('p', 'program2shows', 'p2s', 'p2s.program_id = p.id')
			->innerJoin('p2s', 'program_shows', 'ps', 'ps.id = p2s.show_id')
			->where('ps.status = 1')
			->andWhere('ps.show_in_archive = 1')
			->andWhere('p.premiere = 1')
			->andWhere('p.time < NOW()');

		if ($search2) {
			$qb->andWhere('MATCH (p.title, p.short_description, p.description, p.overwrite) AGAINST (:search IN BOOLEAN MODE)')
				->setParameter('search', $search2);
		}

		return (int) $qb->fetchOne();
	}

	public function searchPaginator2(string $search, int $page, int $limit): int
	{
		$search2 = mb_strtolower($search, 'UTF-8');
		$searchAr = explode(' ', $search2);
		$search2 = '';
		foreach ($searchAr as $item) {
			// zakázat hledání krátkých slov
			if (mb_strlen($item, 'UTF-8') < 3) {
				continue;
			}
			$search2 .= '+' . $item;
		}

		$qb = $this->connection->createQueryBuilder()
			->select('COUNT(*) AS total')
			->from('program', 'p')
			->where('p.premiere = 0')
			->andWhere('p.time < NOW()');

		if ($search2) {
			$qb->andWhere('MATCH (p.title, p.short_description, p.description, p.overwrite) AGAINST (:search IN BOOLEAN MODE)')
				->setParameter('search', $search2);
		}

		return (int) $qb->fetchOne();
	}

	// ---- Admin metody ----

	/**
	 * @param $params
	 * @return array|null
	 */
	public function fetchForBootstrapTable($params): ?array
	{
		$qb = $this->connection->createQueryBuilder()
			->select(
				'program.id', 'program.video_id', 'program.premiere', 'program.time', 'program.file', 'program.url',
				'program.title', 'program.short_description', 'program.description', 'program.overwrite', 'program.video_id_for_newton',
				'program_videos.name AS video_name', 'program_videos.path AS video_path', 'program_videos.showed AS video_showed'
			)
			->from('program')
			->leftJoin('program', 'program_videos', 'program_videos', 'program_videos.id = program.video_id')
			->orderBy($params['sort'], $params['order']);

		if (isset($params['limit'])) {
			$qb->setMaxResults((int) $params['limit']);
		}

		if (isset($params['offset'])) {
			$qb->setFirstResult((int) $params['offset']);
		}

		if (isset($params['search']) && $params['search'] !== '') {
			$qb->andWhere('MATCH (title, short_description, description, overwrite) AGAINST (:search IN BOOLEAN MODE)')
				->setParameter('search', $params['search'] . '*');
		}

		$resultSet = $qb->fetchAllAssociative();

		foreach ($resultSet as $i => $row) {
			$resultSet[$i]['id'] = (int) $row['id'];
			$resultSet[$i]['premiere'] = (bool) $row['premiere'];
		}

		return $resultSet ?: null;
	}

	/**
	 * @param $params
	 * @return int|null
	 */
	public function getCountForBootstrapTable($params): ?int
	{
		$qb = $this->connection->createQueryBuilder()
			->select('COUNT(*) AS count')
			->from('program');

		if (isset($params['search']) && $params['search'] !== '') {
			$qb->andWhere('MATCH (title, short_description, description, overwrite) AGAINST (:search IN BOOLEAN MODE)')
				->setParameter('search', $params['search'] . '*');
		}

		return (int) $qb->fetchOne();
	}

	/**
	 * @param string $column
	 * @param int|string $value
	 * @return array|null
	 */
	public function findPostBy(string $column, int|string $value): ?array
	{
		$qb = $this->connection->createQueryBuilder()
			->select('*')
			->from('program')
			->where($column . ' = :value')
			->setParameter('value', $value);

		$result = $qb->fetchAssociative();

		return $result ?: null;
	}

	/**
	 * @param string $column
	 * @param int|string $value
	 * @return array|null
	 */
	public function findPostsBy(string $column, int|string $value): ?array
	{
		$qb = $this->connection->createQueryBuilder()
			->select('*')
			->from('program')
			->where($column . ' = :value')
			->setParameter('value', $value)
			->orderBy('time', 'ASC')
			->addOrderBy('premiere', 'ASC');

		return $qb->fetchAllAssociative() ?: null;
	}

	/**
	 * @param bool|null $premiere
	 * @return int
	 */
	public function getCount(bool $premiere = null): int
	{
		$qb = $this->connection->createQueryBuilder()
			->select('COUNT(*) AS count')
			->from('program');

		if ($premiere !== null) {
			$qb->andWhere('premiere = :premiere')
				->setParameter('premiere', true);
		}

		return (int) $qb->fetchOne();
	}

	/**
	 * @param int $videoId
	 * @return array|null
	 */
	public function findPremiereByVideoId(int $videoId): ?array
	{
		$qb = $this->connection->createQueryBuilder()
			->select('*')
			->from('program')
			->where('video_id = :videoId')
			->andWhere('premiere = 1')
			->setParameter('videoId', $videoId);

		$result = $qb->fetchAssociative();

		return $result ?: null;
	}

	/**
	 * @param array $data
	 * @return int
	 */
	public function insertPost(array $data): int
	{
		$this->connection->insert('program', $data);

		return (int) $this->connection->lastInsertId();
	}

	/**
	 * @param int $id
	 * @param array $data
	 */
	public function updatePost(int $id, array $data): void
	{
		$this->connection->update('program', $data, ['id' => $id]);
	}

	/**
	 * @param int $id
	 */
	public function deletePost(int $id): void
	{
		$this->connection->delete('program', ['id' => $id]);
	}

	/**
	 * @param int $program_id
	 * @param int $show_id
	 */
	public function insertProgram2Shows(int $program_id, int $show_id): void
	{
		$this->connection->insert('program2shows', [
			'program_id' => $program_id,
			'show_id' => $show_id,
		]);
	}

	/**
	 * @param int $programId
	 */
	public function deleteProgram2ShowsByProgram(int $programId): void
	{
		$this->connection->delete('program2shows', ['program_id' => $programId]);
	}

	/**
	 * @param int $showId
	 */
	public function deleteProgram2Shows(int $showId): void
	{
		$this->connection->delete('program2shows', ['show_id' => $showId]);
	}

	/**
	 * @return array
	 */
	public function getPremieresForExportNewton(): array
	{
		$qb = $this->connection->createQueryBuilder()
			->select(
				'program.id', 'program.time', 'program.title', 'program.url',
				'program.short_description', 'program.description', 'program.overwrite',
				'program_shows.url AS show_url',
				'program_videos.path AS video_path', 'program_videos.name AS video_name',
				'v2.path AS video_path2', 'v2.name AS video_name2'
			)
			->from('program')
			->leftJoin('program', 'program2shows', 'program2shows', 'program2shows.program_id = program.id')
			->leftJoin('program', 'program_shows', 'program_shows', 'program2shows.show_id = program_shows.id')
			->leftJoin('program', 'program_videos', 'program_videos', 'program_videos.id = program.video_id')
			->leftJoin('program', 'program_videos', 'v2', 'v2.id = program.video_id_for_newton')
			->where('program_shows.newton = 1')
			->andWhere('DATE(program.time) >= DATE_SUB(DATE(NOW()), INTERVAL 3 DAY)')
			->andWhere('program.time < NOW()')
			->andWhere('CHAR_LENGTH(program.overwrite) > :minLength')
			->andWhere('(program.video_id IS NOT NULL OR program.video_id_for_newton IS NOT NULL)')
			->setParameter('minLength', 100)
			->orderBy('program.time', 'DESC');

		return $qb->fetchAllAssociative();
	}
}
