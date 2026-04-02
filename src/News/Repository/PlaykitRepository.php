<?php

namespace App\News\Repository;

use Doctrine\DBAL\Connection;

final class PlaykitRepository
{
	public function __construct(private Connection $connection) {}

	public function getWeatherForNews(?string $region): ?array
	{
		$qb = $this->connection->createQueryBuilder()
			->select('pwd.date', 'pwd.day', 'pwd.status', 'pwd.temperature_day')
			->from('polar_weather_days', 'pwd')
			->leftJoin('pwd', 'polar_weather_cities', 'pwc', 'pwc.id = pwd.city_id')
			->orderBy('pwd.date', 'ASC')
			->setMaxResults(3);

		if ($region) {
			$qb->where('pwc.title = :region')
				->setParameter('region', $region);
		}

		$result = $qb->fetchAllAssociative();
		return $result ?: null;
	}

	public function getAllHomepage(?array $withoutIds = null): ?array
	{
		$qb = $this->connection->createQueryBuilder()
			->select('pnh.*', 'pna.show_id', 'pna.updated_date')
			->from('polar_news_homepage', 'pnh')
			->leftJoin('pnh', 'polar_news_articles', 'pna', 'pna.id = pnh.article_id')
			->orderBy('pnh.id', 'ASC');

		if ($withoutIds) {
			$qb->where('pnh.article_id NOT IN (:ids)')
				->setParameter('ids', $withoutIds, \Doctrine\DBAL\ArrayParameterType::INTEGER);
		}

		$result = $qb->fetchAllAssociative();
		return $result ?: null;
	}

	public function getSpecial(): array
	{
		$rows = $this->connection->createQueryBuilder()
			->select('label', 'value')
			->from('polar_news_settings')
			->where('flag = :flag')
			->setParameter('flag', 'special')
			->fetchAllAssociative();

		$result = [];
		foreach ($rows as $row) {
			$result[$row['label']] = $row['value'];
		}
		return $result;
	}
}
