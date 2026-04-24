<?php

namespace App\News\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final class NewsRepository
{
	public function __construct(
		private Connection $connection,
		private int $HOURS_DELETE_LABELS,
	) {}

	public function getPaginator(int $page, int $limit): array
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

	public function getCount(?int $region_id = null, ?int $city_id = null, ?string $redactor_url = null): int
	{
		$qb = $this->connection->createQueryBuilder()
			->select('COUNT(DISTINCT article_id) AS total')
			->from('article')
			->where('public = 1')
			->andWhere('public_from <= NOW()')
			->andWhere('public_to >= NOW()')
			->andWhere('region_id IS NOT NULL');

		if ($region_id !== null) {
			$qb->andWhere('region_id = :regionId')
				->setParameter('regionId', $region_id, ParameterType::INTEGER);
		}
		if ($city_id !== null) {
			$qb->andWhere('city_id = :cityId')
				->setParameter('cityId', $city_id, ParameterType::INTEGER);
		}
		if ($redactor_url !== null) {
			$qb->andWhere('author_url = :redactorUrl')
				->setParameter('redactorUrl', $redactor_url);
		}

		$result = $qb->fetchAssociative();

		return (int) ($result['total'] ?? 0);
	}

	public function searchArticles(string $search, ?int $region, ?int $city, int $page, int $limit): array
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
			// ošetření znaku '-', aby ho Google nebral jako mínus (vyloučení slova)
			$item = str_replace('-', ' ', $item);
			$search2 .= '+' . $item;
		}

		$qb = $this->connection->createQueryBuilder()
			->select('id', 'article_id', 'article_url', 'title', 'anotation', 'text', 'public_from', 'updated_date', 'picture', 'duration', 'region_url', 'city_title', 'city_url', 'label', 'author', 'author_url')
			->from('article')
			->where('public = 1')
			->andWhere('public_from <= NOW()')
			->andWhere('public_to >= NOW()')
			->groupBy('article_id')
			->orderBy('public_from', 'DESC')
			->setFirstResult($offset)
			->setMaxResults($limit);

		if ($region) {
			$qb->andWhere('region_id = :region')->setParameter('region', $region);
		}
		if ($city) {
			$qb->andWhere('city_id = :city')->setParameter('city', $city);
		}
		if ($search2) {
			$qb->andWhere('MATCH (title, anotation, text) AGAINST (:search IN BOOLEAN MODE)')
				->setParameter('search', $search2);
		}

		return $qb->fetchAllAssociative();
	}

	public function searchCount(string $search, ?int $region, ?int $city): int
	{
		$search2 = mb_strtolower($search, 'UTF-8');
		$searchAr = explode(' ', $search2);
		$search2 = '';
		foreach ($searchAr as $item) {
			// zakázat hledání krátkých slov
			if (mb_strlen($item, 'UTF-8') < 3) {
				continue;
			}
			// ošetření znaku '-', aby ho Google nebral jako mínus (vyloučení slova)
			$item = str_replace('-', ' ', $item);
			$search2 .= '+' . $item;
		}

		$qb = $this->connection->createQueryBuilder()
			->select('COUNT(DISTINCT article_id) AS total')
			->from('article')
			->where('public = 1')
			->andWhere('public_from <= NOW()')
			->andWhere('public_to >= NOW()');

		if ($region) {
			$qb->andWhere('region_id = :region')->setParameter('region', $region);
		}
		if ($city) {
			$qb->andWhere('city_id = :city')->setParameter('city', $city);
		}
		if ($search2) {
			$qb->andWhere('MATCH (title, anotation, text) AGAINST (:search IN BOOLEAN MODE)')
				->setParameter('search', $search2);
		}

		return (int) $qb->fetchOne();
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

	public function getArticle(int $articleId): ?array
	{
		$result = $this->connection->createQueryBuilder()
			->select('id', 'article_id', 'article_url', 'title', 'anotation', 'text', 'public_from', 'updated_date', 'picture', 'duration', 'region_url', 'city_title', 'city_url', 'label', 'author', 'author_url', 'show_id')
			->from('article')
			->where('article_id = :articleId')
			->andWhere('public = 1')
			->andWhere('public_from <= NOW()')
			->andWhere('public_to >= NOW()')
			->groupBy('article_id')
			->setParameter('articleId', $articleId)
			->fetchAssociative();

		return $result ?: null;
	}

	public function setImpressionsCount(int $articleId): void
	{
		$result = $this->connection->createQueryBuilder()
			->select('count')
			->from('numbers_of_impressions')
			->where('coverage_id = :articleId')
			->setParameter('articleId', $articleId)
			->fetchAssociative();

		if ($result) {
			$this->connection->createQueryBuilder()
				->update('numbers_of_impressions')
				->set('count', 'count + 1')
				->where('coverage_id = :articleId')
				->setParameter('articleId', $articleId)
				->executeStatement();
			return;
		}

		$this->connection->createQueryBuilder()
			->insert('numbers_of_impressions')
			->values([
				'coverage_id' => ':articleId',
				'count' => ':count',
			])
			->setParameter('articleId', $articleId)
			->setParameter('count', 1)
			->executeStatement();
	}

	public function getAllArticles(): ?array
	{
		$result = $this->connection->createQueryBuilder()
			->select('*')
			->from('article_homepage')
			->where('public_from <= NOW()')
			->andWhere('public_to >= NOW()')
			->orderBy('region_order', 'ASC')
			->addOrderBy('public_from', 'DESC')
			->fetchAllAssociative();

		if (!$result) {
			return null;
		}

		$now_minus_x = new \Datetime();
		$now_minus_x = $now_minus_x->modify('-'.$this->HOURS_DELETE_LABELS.' hours')->format('Y-m-d H:i:s');

		foreach ($result as $i => $iValue) {
			if ($iValue['updated_date'] < $now_minus_x) {
				$result[$i]['label'] = '';
			}
		}

		return $result;
	}

	public function getArticlesByRegionId(int $regionId, int $limit, int $withoutArticleId): ?array
	{
		$result = $this->connection->createQueryBuilder()
			->select('id', 'article_id', 'article_url', 'title', 'anotation', 'text', 'public_from', 'updated_date', 'picture', 'duration', 'region_url', 'city_title', 'city_url', 'label', 'author', 'author_url', 'show_id')
			->from('article')
			->where('region_id = :regionId')
			->andWhere('article_id != :withoutArticleId')
			->andWhere('public = 1')
			->andWhere('public_from <= NOW()')
			->andWhere('public_to >= NOW()')
			->groupBy('article_id')
			->orderBy('public_from', 'DESC')
			->setParameter('regionId', $regionId, ParameterType::INTEGER)
			->setParameter('withoutArticleId', $withoutArticleId, ParameterType::INTEGER)
			->setMaxResults($limit)
			->fetchAllAssociative();

		return $result ?: null;
	}

	public function getCountForByArticleId(int $articleId): int
	{
		$result = $this->connection->createQueryBuilder()
			->select('count')
			->from('numbers_of_impressions')
			->where('coverage_id = :articleId')
			->setParameter('articleId', $articleId, ParameterType::INTEGER)
			->fetchAssociative();

		return (int) ($result['count'] ?? 0);
	}

	public function getPaginatorByRedactor(string $redactorUrl, int $page, int $limit): array
	{
		$offset = ($page - 1) * $limit;

		return $this->connection->createQueryBuilder()
			->select('id', 'article_id', 'article_url', 'title', 'anotation', 'text', 'public_from', 'updated_date', 'picture', 'duration', 'region_url', 'city_title', 'city_url', 'label', 'author', 'author_url', 'show_id')
			->from('article')
			->where('public = 1')
			->andWhere('public_from <= NOW()')
			->andWhere('public_to >= NOW()')
			->andWhere('region_id IS NOT NULL')
			->andWhere('author_url = :redactorUrl')
			->groupBy('article_id')
			->orderBy('public_from', 'DESC')
			->setFirstResult($offset)
			->setMaxResults($limit)
			->setParameter('redactorUrl', $redactorUrl)
			->fetchAllAssociative();
	}

	public function getPaginatorByRegion(int $regionId, int $page, int $limit): array
	{
		$offset = ($page - 1) * $limit;

		return $this->connection->createQueryBuilder()
			->select('id', 'article_id', 'article_url', 'title', 'anotation', 'text', 'public_from', 'updated_date', 'picture', 'duration', 'region_url', 'city_title', 'city_url', 'label', 'author', 'author_url', 'show_id')
			->from('article')
			->where('public = 1')
			->andWhere('public_from <= NOW()')
			->andWhere('public_to >= NOW()')
			->andWhere('region_id = :regionId')
			->groupBy('article_id')
			->orderBy('public_from', 'DESC')
			->setFirstResult($offset)
			->setMaxResults($limit)
			->setParameter('regionId', $regionId, ParameterType::INTEGER)
			->fetchAllAssociative();
	}

	public function getPaginatorByCity(int $cityId, int $page, int $limit): array
	{
		$offset = ($page - 1) * $limit;

		return $this->connection->createQueryBuilder()
			->select('id', 'article_id', 'article_url', 'title', 'anotation', 'text', 'public_from', 'updated_date', 'picture', 'duration', 'region_url', 'city_title', 'city_url', 'label', 'author', 'author_url', 'show_id')
			->from('article')
			->where('public = 1')
			->andWhere('public_from <= NOW()')
			->andWhere('public_to >= NOW()')
			->andWhere('city_id = :cityId')
			->groupBy('article_id')
			->orderBy('public_from', 'DESC')
			->setFirstResult($offset)
			->setMaxResults($limit)
			->setParameter('cityId', $cityId, ParameterType::INTEGER)
			->fetchAllAssociative();
	}

	public function getNewArticlesTopic(array $articleIds): ?array
	{
		if ($articleIds === []) {
			return null;
		}

		$rows = $this->connection->createQueryBuilder()
			->select('id', 'article_id', 'article_url', 'title', 'anotation', 'text', 'public_from', 'updated_date', 'picture', 'duration', 'region_url', 'city_title', 'city_url', 'label', 'author', 'author_url', 'show_id')
			->from('article')
			->where('article_id IN (:articleIds)')
			->andWhere('public = 1')
			->andWhere('public_from <= NOW()')
			->andWhere('public_to >= NOW()')
			->groupBy('article_id')
			->setParameter('articleIds', $articleIds, \Doctrine\DBAL\ArrayParameterType::INTEGER)
			->fetchAllAssociative();

		if (!$rows) {
			return null;
		}

		$now_minus_x = new \DateTime();
		$now_minus_x = $now_minus_x->modify('-' . $this->HOURS_DELETE_LABELS . ' hours')->format('Y-m-d H:i:s');

		foreach ($rows as $i => $iValue) {
			if ($iValue['updated_date'] < $now_minus_x) {
				$rows[$i]['label'] = '';
			}
		}

		$indexed = [];
		foreach ($rows as $row) {
			$indexed[(int) $row['article_id']] = $row;
		}

		$result = [];
		foreach ($articleIds as $articleId) {
			if (isset($indexed[$articleId])) {
				$result[] = $indexed[$articleId];
			}
		}

		return $result ?: null;
	}

	public function setImpressionsCountPr(int $articleId): void
	{
		$result = $this->connection->createQueryBuilder()
			->select('count')
			->from('numbers_of_impressions_pr')
			->where('coverage_id = :articleId')
			->setParameter('articleId', $articleId)
			->fetchAssociative();

		if ($result) {
			$this->connection->createQueryBuilder()
				->update('numbers_of_impressions_pr')
				->set('count', 'count + 1')
				->where('coverage_id = :articleId')
				->setParameter('articleId', $articleId)
				->executeStatement();
			return;
		}

		$this->connection->createQueryBuilder()
			->insert('numbers_of_impressions_pr')
			->values([
				'coverage_id' => ':articleId',
				'count' => ':count',
			])
			->setParameter('articleId', $articleId)
			->setParameter('count', 1)
			->executeStatement();
	}

	public function setImpressionsCountTriptips(int $articleId): void
	{
		$result = $this->connection->createQueryBuilder()
			->select('count')
			->from('numbers_of_impressions_triptips')
			->where('coverage_id = :articleId')
			->setParameter('articleId', $articleId)
			->fetchAssociative();

		if ($result) {
			$this->connection->createQueryBuilder()
				->update('numbers_of_impressions_triptips')
				->set('count', 'count + 1')
				->where('coverage_id = :articleId')
				->setParameter('articleId', $articleId)
				->executeStatement();
			return;
		}

		$this->connection->createQueryBuilder()
			->insert('numbers_of_impressions_triptips')
			->values([
				'coverage_id' => ':articleId',
				'count' => ':count',
			])
			->setParameter('articleId', $articleId)
			->setParameter('count', 1)
			->executeStatement();
	}

	/**
	 * Nejsledovanější hosté za poslední 3 dny
	 */
	public function getMostWatchedGuestsForWeb(int $limit): ?array
	{
		$resultSet = $this->connection->createQueryBuilder()
			->select('coverage_id')
			->from('numbers_of_impressions_hoste')
			->where('DATE(numbers_of_impressions_hoste.created_date) >= DATE(DATE_ADD(NOW(), INTERVAL -3 DAY))')
			->andWhere('DATE(numbers_of_impressions_hoste.created_date) <= DATE(NOW())')
			->orderBy('count', 'DESC')
			->setMaxResults($limit)
			->fetchAllAssociative();

		if (!$resultSet) {
			return null;
		}

		$data = [];
		foreach ($resultSet as $value) {
			$data[] = $value['coverage_id'];
		}
		return $data;
	}

	/**
	 * Počítání zobrazení hostů
	 */
	public function setImpressionsGuestsCount(int $guestId): void
	{
		$result = $this->connection->createQueryBuilder()
			->select('count')
			->from('numbers_of_impressions_hoste')
			->where('coverage_id = :guestId')
			->setParameter('guestId', $guestId)
			->fetchAssociative();

		if ($result) {
			$this->connection->createQueryBuilder()
				->update('numbers_of_impressions_hoste')
				->set('count', 'count + 1')
				->where('coverage_id = :guestId')
				->setParameter('guestId', $guestId)
				->executeStatement();
			return;
		}

		$this->connection->createQueryBuilder()
			->insert('numbers_of_impressions_hoste')
			->values([
				'coverage_id' => ':guestId',
				'count' => ':count',
			])
			->setParameter('guestId', $guestId)
			->setParameter('count', 1)
			->executeStatement();
	}

	public function getArticlesForSpecialByIDs(?string $ids, int $limit = 5): ?array
	{
		if (!$ids) {
			return null;
		}

		$qb = $this->connection->createQueryBuilder();
		$qb->select('article_id', 'article_url', 'title', 'region_url', 'city_url')
			->from('article')
			->where('article_id IN (' . $ids . ')')
			->orderBy('public_from', 'DESC')
			->groupBy('article_id')
			->setMaxResults($limit);

		$result = $qb->executeQuery()->fetchAllAssociative();
		return $result ?: null;
	}

	public function getPaginatorByTopic(array $articles_ids, int $page, int $limit): array
	{
		$offset = ($page - 1) * $limit;

		return $this->connection->createQueryBuilder()
			->select('id', 'article_id', 'article_url', 'title', 'anotation', 'text', 'public_from', 'updated_date', 'picture', 'duration', 'region_url', 'city_title', 'city_url', 'label', 'author', 'author_url', 'show_id')
			->from('article')
			->where('public = 1')
			->andWhere('public_from <= NOW()')
			->andWhere('public_to >= NOW()')
			->andWhere('region_id IS NOT NULL')
			->andWhere('article_id IN (:articleIds)')
			->groupBy('article_id')
			->orderBy('public_from', 'DESC')
			->setFirstResult($offset)
			->setMaxResults($limit)
			->setParameter('articleIds', $articles_ids, \Doctrine\DBAL\ArrayParameterType::INTEGER)
			->fetchAllAssociative();
	}

	public function getCountByTopic(array $articles_ids): int
	{
		return (int) $this->connection->createQueryBuilder()
			->select('COUNT(DISTINCT article_id) AS total')
			->from('article')
			->where('public = 1')
			->andWhere('public_from <= NOW()')
			->andWhere('public_to >= NOW()')
			->andWhere('region_id IS NOT NULL')
			->andWhere('article_id IN (:articleIds)')
			->setParameter('articleIds', $articles_ids, \Doctrine\DBAL\ArrayParameterType::INTEGER)
			->fetchOne();
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
