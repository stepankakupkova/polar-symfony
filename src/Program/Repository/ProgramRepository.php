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
}
