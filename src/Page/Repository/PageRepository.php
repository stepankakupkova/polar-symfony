<?php

namespace App\Page\Repository;

use Doctrine\DBAL\Connection;

final class PageRepository
{
	public function __construct(
		private Connection $connection,
	) {}

	/**
	 * @param $params
	 * @return array|null
	 */
	public function fetchForBootstrapTable($params): ?array
	{
		$qb = $this->connection->createQueryBuilder()
			->select(
				'id', 'lang', 'active', 'header', 'title', 'url', 'content', 'image',
				'parent', 'depth', 'rank', 'rank_total',
				'seo_keywords', 'seo_description',
				'created_date', 'updated_date', 'created_user', 'updated_user'
			)
			->from('page')
			->where('lang = :lang')
			->setParameter('lang', $params['lang'])
			->orderBy('header', 'ASC')
			->addOrderBy($params['sort'] ?? 'rank_total', ($params['order'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC');

		if (isset($params['limit'])) {
			$qb->setMaxResults((int) $params['limit']);
		}

		if (isset($params['offset'])) {
			$qb->setFirstResult((int) $params['offset']);
		}

		if (isset($params['search']) && $params['search'] !== '') {
			$qb->andWhere('MATCH (title, content) AGAINST (:search IN BOOLEAN MODE)')
				->setParameter('search', $params['search'] . '*');
		}

		$rows = $qb->fetchAllAssociative();

		foreach ($rows as $i => $row) {
			if ($row['parent'] !== 0) {
				foreach ($rows as $row2) {
					if ($rows[$i]['parent'] === $row2['id']) {
						$rows[$i]['url'] = $row2['url'] . '/' . $rows[$i]['url'];
					}
				}
			}
		}

		return $rows;
	}

	/**
	 * @param string $lang
	 * @param bool $header
	 * @param int|null $parent
	 * @return string
	 */
	public function fetchForNestable(string $lang, bool $header, ?int $parent = null): string
	{
		$qb = $this->connection->createQueryBuilder()
			->select(
				'id', 'lang', 'active', 'header', 'title',
				'parent', 'depth', 'rank', 'rank_total',
				'created_date', 'updated_date', 'created_user', 'updated_user'
			)
			->from('page')
			->where('lang = :lang')
			->andWhere('header = :header')
			->setParameter('lang', $lang)
			->setParameter('header', $header ? 1 : 0)
			->orderBy('rank', 'ASC');

		if ($parent === null) {
			$qb->andWhere('parent IS NULL');
		} else {
			$qb->andWhere('parent = :parent')
				->setParameter('parent', $parent);
		}

		$rows = $qb->fetchAllAssociative();

		if (empty($rows)) {
			return '';
		}

		$data = '<ol class="dd-list">';
		foreach ($rows as $row) {
			$data .=
				'<li class="dd-item" data-id="' . $row['id'] . '">' .
				'<div class="dd-handle cur-move">' .
				'<i class="fa fa-fw ' . ($row['active'] ? 'fa-check-circle text-success' : 'fa-times-circle text-danger') . '"></i> ' .
				$row['title'] .
				'</div>';

			$data .= $this->fetchForNestable($lang, $header, (int) $row['id']);

			$data .=
				'</li>';
		}
		$data .= '</ol>';

		return $data;
	}

	/**
	 * Načte stránky pro navigační config
	 * @return array
	 */
	public function fetchForConfig(string $lang, bool $header, ?int $parent = null): array
	{
		$qb = $this->connection->createQueryBuilder()
			->select(
				'id', 'lang', 'active', 'header', 'title', 'url',
				'parent', 'depth', 'rank', 'rank_total',
				'created_date', 'updated_date', 'created_user', 'updated_user'
			)
			->from('page')
			->where('active = 1')
			->andWhere('lang = :lang')
			->andWhere('header = :header')
			->setParameter('lang', $lang)
			->setParameter('header', $header ? 1 : 0)
			->orderBy('rank', 'ASC');

		if ($parent === null) {
			$qb->andWhere('parent IS NULL OR parent = 0');
		} else {
			$qb->andWhere('parent = :parent')
				->setParameter('parent', $parent);
		}

		return $qb->fetchAllAssociative();
	}

	/**
	 * @return array
	 */
	public function fetchRoutesForConfig(): array
	{
		$qb = $this->connection->createQueryBuilder()
			->select(
				'id', 'lang', 'active', 'header', 'title', 'url',
				'parent', 'depth', 'rank', 'rank_total',
				'created_date', 'updated_date', 'created_user', 'updated_user'
			)
			->from('page')
			->where('active = 1')
			->orderBy('id', 'ASC');

		$rows = $qb->fetchAllAssociative();

		// Zakomentováno, chceme URL "/reklama", ne "/o-tv-polar/reklama"
		//foreach ($rows as $i => $row) {
		//	if ($row['parent'] !== 0) {
		//		foreach ($rows as $row2) {
		//			if ($rows[$i]['parent'] === $row2['id']) {
		//				$rows[$i]['url'] = $row2['url'] . '/' . $rows[$i]['url'];
		//			}
		//		}
		//	}
		//}

		return $rows;
	}

	/**
	 * @param string $column
	 * @param int|string $value
	 * @param string|null $lang
	 * @return array|null
	 */
	public function findPostBy(string $column, int|string $value, ?string $lang = null): ?array
	{
		$qb = $this->connection->createQueryBuilder()
			->select('*')
			->from('page')
			->where("$column = :value")
			->setParameter('value', $value);

		if ($lang) {
			$qb->andWhere('lang = :lang')
				->setParameter('lang', $lang);
		}

		return $qb->fetchAssociative() ?: null;
	}

	/**
	 * @param array $data
	 * @return int
	 */
	public function insertPost(array $data): int
	{
		$this->connection->insert('page', $data);
		return (int) $this->connection->lastInsertId();
	}

	/**
	 * @param int $id
	 * @param array $data
	 * @return void
	 */
	public function updatePost(int $id, array $data): void
	{
		$this->connection->update('page', $data, ['id' => $id]);
	}

	/**
	 * @param int $id
	 * @return void
	 */
	public function deletePost(int $id): void
	{
		$this->connection->delete('page', ['id' => $id]);
	}

	/**
	 * @param bool|null $active
	 * @return int
	 */
	public function getCount(?bool $active = null): int
	{
		$qb = $this->connection->createQueryBuilder()
			->select('COUNT(*)')
			->from('page');

		if ($active !== null) {
			$qb->andWhere('active = 1');
		}

		return (int) $qb->fetchOne();
	}

	/**
	 * @param string $lang
	 * @return int
	 */
	public function getCountByLang(string $lang): int
	{
		return (int) $this->connection->createQueryBuilder()
			->select('COUNT(*)')
			->from('page')
			->where('lang = :lang')
			->setParameter('lang', $lang)
			->fetchOne();
	}

	/**
	 * @param string $lang
	 * @param int|null $parent
	 * @return int
	 */
	public function getCountByLangAndParent(string $lang, ?int $parent = null): int
	{
		return (int) $this->connection->createQueryBuilder()
			->select('COUNT(*)')
			->from('page')
			->where('lang = :lang')
			->andWhere('parent = :parent')
			->setParameter('lang', $lang)
			->setParameter('parent', $parent)
			->fetchOne();
	}

	/**
	 * @param $params
	 * @return int|null
	 */
	public function getCountForBootstrapTable($params): ?int
	{
		$qb = $this->connection->createQueryBuilder()
			->select('COUNT(*)')
			->from('page')
			->where('lang = :lang')
			->setParameter('lang', $params['lang']);

		if (isset($params['search']) && $params['search'] !== '') {
			$qb->andWhere('MATCH (title, content) AGAINST (:search IN BOOLEAN MODE)')
				->setParameter('search', $params['search'] . '*');
		}

		return (int) $qb->fetchOne();
	}
}
