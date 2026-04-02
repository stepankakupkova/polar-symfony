<?php

namespace App\News\Repository;

use Doctrine\DBAL\Connection;

final class VideoRepository
{
	public function __construct(private Connection $connection) {}

	public function getNewVideosForWeb(int $limit): ?array
	{
		$rows = $this->connection->createQueryBuilder()
			->select('pv.name', 'pv.duration', 'p.time', 'p.title', 'p.short_description', 'p.url', 'ps.url AS show_url')
			->from('program_videos', 'pv')
			->innerJoin('pv', 'program', 'p', 'p.video_id = pv.id')
			->innerJoin('p', 'program2shows', 'p2s', 'p2s.program_id = p.id')
			->innerJoin('p2s', 'program_shows', 'ps', 'ps.id = p2s.show_id')
			->where('p.premiere = 1')
			->orderBy('p.time', 'DESC')
			->setMaxResults($limit)
			->fetchAllAssociative();

		if (!$rows) {
			return null;
		}

		foreach ($rows as $i => $row) {
			$rows[$i]['url'] = '/porady/' . $row['show_url'] . '/' . $row['url'];
			$short = (string) ($row['short_description'] ?? '');
			$rows[$i]['anotation'] = mb_substr($short, 0, 160, 'UTF-8') . (mb_strlen($short, 'UTF-8') > 160 ? '...' : '');
			$rows[$i]['image'] = '/data/program/thumbs/' . $row['name'] . '.jpg';
			unset($rows[$i]['name'], $rows[$i]['show_url'], $rows[$i]['short_description']);
		}

		return $rows;
	}
}
