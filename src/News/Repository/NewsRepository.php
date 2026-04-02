<?php

namespace App\News\Repository;

use Doctrine\DBAL\Connection;

final class NewsRepository
{
	public function __construct(private Connection $connection) {}

	public function getPage(int $page, int $limit): array
	{
		$offset = ($page - 1) * $limit;

		return $this->connection->createQueryBuilder()
			->select('id', 'article_id', 'article_url', 'title', 'anotation', 'text', 'public_from', 'updated_date', 'picture', 'duration', 'region_url', 'city_title', 'city_url', 'label', 'author', 'author_url', 'show_id')
			->from('article')
			->where('public = 1')
			->andWhere('public_from <= NOW()')
			->andWhere('public_to >= NOW()')
			->andWhere('region_id IS NOT NULL')
			->groupBy('article_id')
			->orderBy('public_from', 'DESC')
			->setFirstResult($offset)
			->setMaxResults($limit)
			->fetchAllAssociative();
	}

	public function getTotal(): int
	{
		$result = $this->connection->createQueryBuilder()
			->select('COUNT(DISTINCT article_id) AS total')
			->from('article')
			->where('public = 1')
			->andWhere('public_from <= NOW()')
			->andWhere('public_to >= NOW()')
			->andWhere('region_id IS NOT NULL')
			->fetchAssociative();

		return (int) ($result['total'] ?? 0);
	}

	public function getCountFromSettings(): int
	{
		$result = $this->connection->createQueryBuilder()
			->select('value')
			->from('settings')
			->where('flag = :flag')
			->andWhere('label = :label')
			->setParameter('flag', 'news')
			->setParameter('label', 'count')
			->fetchAssociative();

		return (int) ($result['value'] ?? 0);
	}

	public function getPrArticles(int $limit): ?array
	{
		$result = $this->connection->createQueryBuilder()
			->select('*')
			->from('pr_homepage')
			->where('public_from <= NOW()')
			->andWhere('public_to >= NOW()')
			->orderBy('public_from', 'DESC')
			->setMaxResults($limit)
			->fetchAllAssociative();

		return $result ?: null;
	}

	public function getTriptipArticles(int $limit, ?int $region_id = null, bool $rand = false): ?array
	{
		$qb = $this->connection->createQueryBuilder()
			->select('*')
			->from('triptip_homepage')
			->where('term_from >= :now')
			->setParameter('now', date('Y-m-d H:i:s'))
			->groupBy('article_id')
			->setMaxResults($limit);

		if ($rand) {
			$qb->orderBy('RAND()')->addOrderBy('term_from', 'ASC');
		} else {
			$qb->orderBy('term_from', 'ASC');
		}

		if ($region_id) {
			$qb->andWhere('region_id IN (' . $region_id . ', 7)');
		}

		$result = $qb->fetchAllAssociative();
		return $result ?: null;
	}

	public function getAllArticlesForRegions(): array
	{
		$result = $this->connection->createQueryBuilder()
			->select('*')
			->from('article_homepage')
			->where('public_from <= NOW()')
			->andWhere('public_to >= NOW()')
			->orderBy('region_order', 'ASC')
			->addOrderBy('public_from', 'DESC')
			->fetchAllAssociative();

		// Grouping by region
		$regions = [];
		foreach ($result as $row) {
			$key = $row['region_url'] ?? $row['region_id'];
			if (!isset($regions[$key])) {
				$regions[$key] = [
					'url'      => $row['region_url'],
					'title'    => $row['region_title'] ?? '',
					'articles' => [],
				];
			}
			$regions[$key]['articles'][] = $row;
		}

		return array_values($regions);
	}

	private function removeAccent(string $text, string $replace = ''): string
	{
		$transliterator = \Transliterator::createFromRules(':: Any-Latin; :: NFD; :: [:Nonspacing Mark:] Remove; :: NFC; :: [:Punctuation:] Remove; :: Lower();', \Transliterator::FORWARD);
		$text = $transliterator->transliterate($text);
		$text = preg_replace('/\p{C}+/u', '', $text);
		if ($replace) {
			$text = str_replace(' ', $replace, $text);
		}
		return $text;
	}
}
