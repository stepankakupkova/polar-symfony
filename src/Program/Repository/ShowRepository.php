<?php

namespace App\Program\Repository;

use Doctrine\DBAL\Connection;

final class ShowRepository
{
	private string $table = 'program_shows';
	private string $table2Shows = 'program2shows';
	private string $tableShowsTimes = 'program_shows_times';
	private string $tableShowsCategories = 'program_shows_categories';

	public function __construct(private Connection $connection) {}

	/**
	 * @param $params
	 * @return array|null
	 */
	public function fetchForBootstrapTable($params): ?array
	{
		$qb = $this->connection->createQueryBuilder()
			->select(
				'program_shows.id', 'program_shows.title', 'program_shows.url', 'program_shows.short_description',
				'program_shows.image', 'program_shows.thumb',
				'program_shows.status', 'program_shows.show_in_archive', 'program_shows.video_parts',
				'program_shows.show_datetime', 'program_shows.download', 'program_shows.newton',
				'program_shows.content', 'program_shows.seo_keywords', 'program_shows.seo_description',
				'program_shows.`order`',
				'program_shows_categories.title AS category_name'
			)
			->from($this->table)
			->leftJoin('program_shows', 'program_shows_categories', 'program_shows_categories', 'program_shows_categories.id = program_shows.category_id')
			->orderBy($params['sort'] === 'order' ? '`order`' : $params['sort'], $params['order']);

		if (isset($params['limit'])) {
			$qb->setMaxResults((int) $params['limit']);
		}

		if (isset($params['offset'])) {
			$qb->setFirstResult((int) $params['offset']);
		}

		if (isset($params['search']) && $params['search'] !== '') {
			$qb->andWhere('MATCH (program_shows.title, program_shows.short_description, program_shows.content) AGAINST (:search IN BOOLEAN MODE)')
				->setParameter('search', $params['search'] . '*');
		}

		$resultSet = $qb->fetchAllAssociative();

		foreach ($resultSet as $i => $row) {
			$resultSet[$i]['id'] = (int) $row['id'];
			$resultSet[$i]['status'] = (bool) $row['status'];
			$resultSet[$i]['show_in_archive'] = (bool) $row['show_in_archive'];
			$resultSet[$i]['video_parts'] = (bool) $row['video_parts'];
			$resultSet[$i]['show_datetime'] = (bool) $row['show_datetime'];
			$resultSet[$i]['download'] = (bool) $row['download'];
			$resultSet[$i]['newton'] = (bool) $row['newton'];
			$resultSet[$i]['order'] = (int) $row['order'];
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
			->select('id', 'title')
			->from($this->table)
			->orderBy('title', 'ASC')
			->setMaxResults($limit)
			->fetchAllAssociative();

		$data = [['value' => null, 'label' => null]];
		foreach ($rows as $item) {
			$data[] = ['value' => $item['id'], 'label' => $item['title']];
		}
		return $data;
	}

	/**
	 * @param $params
	 * @return array
	 */
	public function fetchTimesForBootstrapTable($params): array
	{
		$rows = $this->connection->createQueryBuilder()
			->select('*')
			->from($this->tableShowsTimes)
			->where('show_id = :show_id')
			->setParameter('show_id', $params['show_id'])
			->fetchAllAssociative();

		$dataTmp = [];
		foreach ($rows as $time) {
			$dataTmp[$time['day']][] = [
				'id' => $time['id'],
				'time' => $time['time'],
				'premiere' => $time['premiere'],
			];
		}

		$days = [
			1 => 'PO',
			2 => 'UT',
			3 => 'ST',
			4 => 'CT',
			5 => 'PA',
			6 => 'SO',
			7 => 'NE',
			8 => 'PREM',
			9 => 'REPR',
		];

		$data = [];
		foreach ($days as $id => $day) {
			$data[] = [
				'id' => $id,
				'day' => $day,
				'data' => $dataTmp[$day] ?? [],
			];
		}

		return $data;
	}

	/**
	 * @param $params
	 * @return int
	 */
	public function getTimesCountForBootstrapTable($params): int
	{
		return (int) $this->connection->createQueryBuilder()
			->select('COUNT(*) AS count')
			->from($this->tableShowsTimes)
			->where('show_id = :show_id')
			->setParameter('show_id', $params['show_id'])
			->fetchOne();
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
	 * @param int $programId
	 * @return array|null
	 */
	public function findPostByProgram(int $programId): ?array
	{
		$result = $this->connection->createQueryBuilder()
			->select('program_shows.*')
			->from($this->table)
			->leftJoin('program_shows', $this->table2Shows, 'p2s', 'p2s.show_id = program_shows.id')
			->where('p2s.program_id = :programId')
			->setParameter('programId', $programId)
			->fetchAssociative();

		return $result ?: null;
	}

	/**
	 * @param bool|null $active
	 * @return int
	 */
	public function getCount(?bool $active = null): int
	{
		$qb = $this->connection->createQueryBuilder()
			->select('COUNT(*) AS count')
			->from($this->table);

		if ($active !== null) {
			$qb->andWhere('status = :status')
				->setParameter('status', true);
		}

		return (int) $qb->fetchOne();
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
			$qb->andWhere('MATCH (title, short_description, content) AGAINST (:search IN BOOLEAN MODE)')
				->setParameter('search', $params['search'] . '*');
		}

		return (int) $qb->fetchOne();
	}

	/**
	 * @return array
	 */
	public function fetchCategoryForBootstrapSelect(): array
	{
		$rows = $this->connection->createQueryBuilder()
			->select('id', 'title')
			->from($this->tableShowsCategories)
			->orderBy('id', 'ASC')
			->fetchAllAssociative();

		$data = [['value' => null, 'label' => null]];
		foreach ($rows as $item) {
			$data[] = ['value' => $item['id'], 'label' => $item['title']];
		}
		return $data;
	}

	/**
	 * @param array $data
	 * @return int
	 */
	public function insertPost(array $data): int
	{
		// 'order' je rezervované slovo v MySQL/MariaDB, nutno escapovat
		if (array_key_exists('order', $data)) {
			$data['`order`'] = $data['order'];
			unset($data['order']);
		}
		$this->connection->insert($this->table, $data);

		return (int) $this->connection->lastInsertId();
	}

	/**
	 * @param int $id
	 * @param array $data
	 */
	public function updatePost(int $id, array $data): void
	{
		// 'order' je rezervované slovo v MySQL/MariaDB, nutno escapovat
		if (array_key_exists('order', $data)) {
			$data['`order`'] = $data['order'];
			unset($data['order']);
		}
		$this->connection->update($this->table, $data, ['id' => $id]);
	}

	/**
	 * @param int $id
	 */
	public function deletePost(int $id): void
	{
		$this->connection->delete($this->table, ['id' => $id]);
	}

	public function fetchAll(): array
	{
		return $this->connection->createQueryBuilder()
			->select('id', 'title', '`order`')
			->from($this->table)
			->orderBy('`order`', 'ASC')
			->fetchAllAssociative();
	}

	public function fetchAllForNews(): ?array
	{
		$rows = $this->connection->createQueryBuilder()
			->select('id', 'title')
			->from($this->table)
			->where('show_in_archive = 1')
			->andWhere('status = 1')
			->orderBy('`order`', 'ASC')
			->fetchAllAssociative();

		if (!$rows) {
			return null;
		}
		$data = [];
		foreach ($rows as $item) {
			$data[$item['id']] = $item['title'];
		}
		return $data;
	}

	/**
	 * @param int $show_id
	 */
	public function deleteShowsTimes(int $show_id): void
	{
		$this->connection->delete($this->tableShowsTimes, ['show_id' => $show_id]);
	}

	/**
	 * @param int $show_id
	 * @param string $day
	 * @param string $value
	 * @param bool $premiere
	 */
	public function insertTime(int $show_id, string $day, string $value, bool $premiere): void
	{
		$this->connection->insert($this->tableShowsTimes, [
			'show_id' => $show_id,
			'day' => $day,
			'time' => $value,
			'premiere' => (int) $premiere,
		]);
	}

	/**
	 * @param int $id
	 * @param int $show_id
	 * @param string $day
	 * @param string $value
	 * @param bool $premiere
	 */
	public function updateTime(int $id, int $show_id, string $day, string $value, bool $premiere): void
	{
		$this->connection->update($this->tableShowsTimes, [
			'show_id' => $show_id,
			'day' => $day,
			'time' => $value,
			'premiere' => (int) $premiere,
		], ['id' => $id]);
	}

	/**
	 * @param int $id
	 */
	public function deleteTime(int $id): void
	{
		$this->connection->delete($this->tableShowsTimes, ['id' => $id]);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function fetchForConfig(): array
	{
		$rows = $this->connection->createQueryBuilder()
			->select('id', 'title', 'url', '`order`')
			->from($this->table)
			->where('show_in_archive = 1')
			->andWhere('status = 1')
			->orderBy('`order`', 'ASC')
			->fetchAllAssociative();

		$data = [];
		foreach ($rows as $show) {
			$data[] = [
				'id' => (int) $show['id'],
				'title' => $show['title'],
				'url' => $show['url'],
				'order' => 1 + (int) $show['order'],
			];
		}

		return $data;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function fetchRoutesForConfig(): array
	{
		$rows = $this->connection->createQueryBuilder()
			->select('id', 'url')
			->from($this->table)
			->where('show_in_archive = 1')
			->andWhere('status = 1')
			->orderBy('`order`', 'ASC')
			->fetchAllAssociative();

		$data = [];
		foreach ($rows as $show) {
			$data[] = [
				'id' => (int) $show['id'],
				'url' => $show['url'],
			];
		}

		return $data;
	}

	public function fetchTimesForWeb(int $show_id): string
	{
		$data = "";

		$rows = $this->connection->createQueryBuilder()
			->select('*')
			->from($this->tableShowsTimes)
			->where('show_id = :show_id')
			->setParameter('show_id', $show_id)
			->orderBy('FIELD(day, "PO", "UT", "ST", "CT", "PA", "SO", "NE", "PREM", "REPR")')
			->addOrderBy('time', 'ASC')
			->fetchAllAssociative();

		$dataTmp = [];
		$days = [
			'PO' => 'Pondělí',
			'UT' => 'Úterý',
			'ST' => 'Středa',
			'CT' => 'Čtvrtek',
			'PA' => 'Pátek',
			'SO' => 'Sobota',
			'NE' => 'Neděle',
			'PREM' => 'Premiéra',
			'REPR' => 'Repríza',
		];

		foreach ($rows as $time) {
			$dataTmp[$time['day']][] = [
				'id' => $time['id'],
				'time' => $time['time'],
				'premiere' => $time['premiere'],
			];
			$data = '<div class="row pt-2">';
			if ($dataTmp) {
				foreach ($dataTmp as $day => $items) {
					$data .= '<div class="col-sm-2 col-xs-3"><strong>' . $days[$day] . '</strong></div>';
					$data .= '<div class="col-sm-10 col-xs-9">';
					foreach ($items as $item) {
						if ($item['premiere']) {
							$data .= '<strong class="text-color-primary">' . $item['time'] . '</strong>, ';
						} else {
							$data .= $item['time'] . ', ';
						}
					}
					$data = mb_substr($data, 0, -2, 'UTF-8');
					$data .= '</div>';
				}
			} else {
				$data .= '<div class="col-md-12"><strong>Tento pořad momentálně není v TV POLAR vysílán.</strong></div>';
			}
			$data .= '</div>';
		}
		return $data;
	}

	public function fetchAllByCategories(string $search): ?array
	{
		/*if ($search !== '') {
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
        }*/

		$categories = $this->connection->createQueryBuilder()
			->select('id AS category_id', 'title AS category_title', 'url AS category_url')
			->from($this->tableShowsCategories)
			->orderBy('rank', 'ASC')
			->fetchAllAssociative();

		$data = [];
		foreach ($categories as $value) {
			$qb = $this->connection->createQueryBuilder()
				->select('*, ' . $this->connection->quote('ne') . ' AS special')
				->from($this->table)
				->where('category_id = :cat_id')
				->andWhere('show_in_archive = 1')
				->andWhere('status = 1')
				->setParameter('cat_id', $value['category_id'])
				->orderBy('`order`', 'ASC');

			if ($search !== '') {
				$qb->andWhere('LOWER(CONVERT(title USING utf8)) LIKE :search')
					->setParameter('search', '%' . mb_strtolower($search, 'UTF-8') . '%');
			}

			$resultSet2 = $qb->fetchAllAssociative();

			/* doplnění z tabulky mimořádných pořadů */
			$qb2 = $this->connection->createQueryBuilder()
				->select('*, ' . $this->connection->quote('ano') . ' AS special')
				->from('special_shows')
				->where('category_id = :cat_id')
				->andWhere('status = 1')
				->setParameter('cat_id', $value['category_id'])
				->orderBy('`order`', 'ASC');

			if ($search !== '') {
				$qb2->andWhere('LOWER(CONVERT(title USING utf8)) LIKE :search')
					->setParameter('search', '%' . mb_strtolower($search, 'UTF-8') . '%');
			}

			$resultSet3 = $qb2->fetchAllAssociative();
			/**/

			$output = array_merge($resultSet2, $resultSet3);

			foreach ($output as $item) {
				$data[$value['category_id']]['category_id'] = $value['category_id'];
				$data[$value['category_id']]['category_title'] = $value['category_title'];
				$data[$value['category_id']]['category_url'] = $value['category_url'];
				$data[$value['category_id']]['shows'][] = $item;
			}
		}

		/* doplnění Hosté ve studiu */
		$data[1]['shows'][] = [
			'id' => '99',
			'title' => 'Hosté ve studiu',
			'short_description' => 'Zde najdete rozhovory se všemi osobnostmi a představiteli z MS kraje, kteří kdy byli pozváni do studia TV POLAR',
			'image' => 'data/program/show/hoste/small.png',
			'thumb' => 'data/program/show/hoste/small.png',
			'special' => 'hoste'
		];
		/**/

		return $data;
	}

	public function fetchForAutocomplete(): array
	{
		$resultSet = $resultSet2 = [];

		$resultSet = $this->connection->createQueryBuilder()
			->select('url', 'title', $this->connection->quote('ne') . ' AS special')
			->from($this->table)
			->where('show_in_archive = 1')
			->andWhere('status = 1')
			->orderBy('`order`', 'ASC')
			->fetchAllAssociative();

		/* doplnění z tabulky mimořádných pořadů */
		$resultSet2 = $this->connection->createQueryBuilder()
			->select('url', 'title', $this->connection->quote('ano') . ' AS special')
			->from('special_shows')
			->where('status = 1')
			->orderBy('`order`', 'ASC')
			->fetchAllAssociative();

		return array_merge($resultSet, $resultSet2);
	}

	public function getShowsForRSS(): ?array
	{
		return $this->connection->createQueryBuilder()
			->select('s.title', 's.url', 's.short_description', 's.image',
					 'p.time', 'p.short_description AS program_short_description', 'p.url AS program_url')
			->from('program_shows', 's')
			->leftJoin('s', 'program2shows', 'ps', 's.id = ps.show_id')
			->leftJoin('ps', 'program', 'p', 'p.id = ps.program_id')
			->where('s.show_in_archive = 1')
			->andWhere('s.status = 1')
			->andWhere('s.id IN (12, 40, 11, 50, 67, 68)')// 12 - Eko magazín, 40 - Študuj u nás, 11 - Magazín TV Medicína, 50 - Náš kraj není na okraji, 67 - Magazín 112, 68 - Legendy Moravskoslezského kraje
			->andWhere('p.premiere = 1')
			->andWhere('p.time < NOW()')
			->orderBy('p.time', 'DESC')
			->setMaxResults(10)
			->executeQuery()
			->fetchAllAssociative() ?: null;
	}
}
