<?php

namespace App\News\Repository;

use Doctrine\DBAL\Connection;

final class CrawlRepository
{
	public function __construct(private Connection $connection) {}

	public function getCrawl(int $id = 1): ?array
	{
		return $this->connection->createQueryBuilder()
			->select('`separator`', 'text_before', 'text_after', 'auto_delete_start', 'auto_delete_stop')
			->from('polar_crawl_settings_crawls')
			->where('id = :id')
			->setParameter('id', $id)
			->executeQuery()
			->fetchAssociative() ?: null;
	}

	public function getItems(int $crawlId = 1): array
	{
		$rows = $this->connection->createQueryBuilder()
			->select('content')
			->from('polar_crawl_content')
			->where('crawl_id = :crawl_id')
			->setParameter('crawl_id', $crawlId)
			->orderBy('`order`', 'ASC')
			->executeQuery()
			->fetchFirstColumn();

		return array_map(fn($item) => mb_strtoupper($item, 'UTF-8'), $rows);
	}
}
