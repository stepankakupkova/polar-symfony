<?php

namespace App\News\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

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

	public function getRegionByUrl(string $url): ?array
	{
		$result = $this->connection->createQueryBuilder()
			->select('*')
			->from('polar_news_regions')
			->where('url = :url')
			->setParameter('url', $url)
			->fetchAssociative();

		return $result ?: null;
	}

	public function getCityByUrl(string $url): ?array
	{
		$result = $this->connection->createQueryBuilder()
			->select('*')
			->from('polar_news_cities')
			->where('url = :url')
			->setParameter('url', $url)
			->fetchAssociative();

		return $result ?: null;
	}

	public function getArticle(int $articleId, ?int $cityId = null): ?array
	{
		$qb = $this->connection->createQueryBuilder()
			->select(
				'pna.*',
				'pnr.name',
				'pnr.surname',
				'pnr.url AS redactor_url',
				'pnc.city',
				'pnc.url AS city_url',
				'pnc.latitude',
				'pnc.longitude',
				'pnrg.region',
				'pnrg.url AS region_url',
				'pnvp.sec_from',
				'pnvp.medium'
			)
			->from('polar_news_articles', 'pna')
			->leftJoin('pna', 'polar_news_redaction', 'pnr', 'pnr.id = pna.redactor_id')
			->leftJoin('pna', 'polar_news_articles2cities', 'pnac', $cityId ? 'pnac.article_id = pna.id AND pnac.city_id = :cityId' : 'pnac.article_id = pna.id AND pnac.rank = 1')
			->leftJoin('pnac', 'polar_news_cities', 'pnc', 'pnc.id = pnac.city_id')
			->leftJoin('pnc', 'polar_news_regions', 'pnrg', 'pnrg.id = pnc.region_id')
			->leftJoin('pna', 'polar_news_articles2gallery_files', 'pna2gf', 'pna2gf.article_id = pna.id')
			->leftJoin('pna2gf', 'polar_news_videos_parts', 'pnvp', 'pnvp.id = pna2gf.part')
			->where('pna.id = :articleId')
			->setParameter('articleId', $articleId)
			->setMaxResults(1);

		if ($cityId) {
			$qb->setParameter('cityId', $cityId, ParameterType::INTEGER);
		}

		$result = $qb->fetchAssociative();
		if (!$result) {
			return null;
		}

		$result['url'] = $this->removeAccent((string) ($result['title'] ?? ''), '-');
		$result['label'] = $this->getArticleLabels($articleId, (string) ($result['updated_date'] ?? ''));
		$result['topics'] = $this->getArticleTopics($articleId);
		$result['files'] = $this->getArticleFiles($articleId, (string) ($result['public_from'] ?? ''));

		return $result;
	}

	public function getCityRank1ByArticleId(int $articleId): ?array
	{
		$result = $this->connection->createQueryBuilder()
			->select('pnc.url AS city_rank1_url', 'pnr.url AS region_rank1_url')
			->from('polar_news_articles2cities', 'pnac')
			->leftJoin('pnac', 'polar_news_cities', 'pnc', 'pnc.id = pnac.city_id')
			->leftJoin('pnc', 'polar_news_regions', 'pnr', 'pnr.id = pnc.region_id')
			->where('pnac.article_id = :articleId')
			->andWhere('pnac.rank = 1')
			->setParameter('articleId', $articleId)
			->setMaxResults(1)
			->fetchAssociative();

		return $result ?: null;
	}

	public function getArticlePr(int $articleId): ?array
	{
		$result = $this->connection->createQueryBuilder()
			->select('pnp.*')
			->from('polar_news_pr', 'pnp')
			->where('pnp.id = :articleId')
			->setParameter('articleId', $articleId, ParameterType::INTEGER)
			->setMaxResults(1)
			->fetchAssociative();

		if (!$result) {
			return null;
		}

		$result['url'] = $this->removeAccent((string) ($result['title'] ?? ''), '-');

		// Všechny soubory z galerie
		$files = $this->connection->createQueryBuilder()
			->select('gf.folder', 'gf.type', 'gf.module', 'gf.file', 'gf.folder_light', 'gf.size', 'gf.size_hq', 'gf.ext', 'gf.description', 'gf.updated_date', 'pnp2gf.file_id', 'pnp2gf.rank')
			->from('gallery_files', 'gf')
			->leftJoin('gf', 'polar_news_pr2gallery_files', 'pnp2gf', 'pnp2gf.file_id = gf.id')
			->where('pnp2gf.pr_id = :prId')
			->andWhere('pnp2gf.checked = 1')
			->orderBy('pnp2gf.rank', 'ASC')
			->setParameter('prId', $articleId, ParameterType::INTEGER)
			->fetchAllAssociative();

		if ($files) {
			foreach ($files as &$file) {
				$version = str_replace(['-', ' ', ':'], '', (string) $file['updated_date']);
				if ($file['type'] === 'video') {
					$file['medium'] = '/data/gallery/modules/' . $file['module'] . '/videos/' . $file['folder'] . '/715x402.jpg?ver=' . $version;
					if ($result['public_from'] >= '2015-12-10') {
						$file['link_lq'] = 'https://light.polar.cz/videa/polar/zpravy/publikovano/' . $file['folder_light'] . '/' . $file['file'] . '_lq.mp4';
						$file['link_hq'] = 'https://light.polar.cz/videa/polar/zpravy/publikovano/' . $file['folder_light'] . '/' . $file['file'] . '_hq.mp4';
					} else {
						$file['link_lq'] = 'https://light.polar.cz/videa/polar/zpravy/publikovano/' . $file['folder_light'] . '/' . $file['file'] . '_hq.mp4';
						$file['link_hq'] = 'https://light.polar.cz/videa/polar/zpravy/publikovano/' . $file['folder_light'] . '/' . $file['file'] . '_hq.mp4';
					}
				}
				if ($file['type'] === 'image') {
					$file['medium'] = '/data/gallery/modules/' . $file['module'] . '/images/' . $file['folder'] . '/715x402.' . $file['ext'] . '?ver=' . $version;
				}
			}
			unset($file);
			$result['files'] = $files;
		} else {
			$result['files'] = null;
		}

		return $result;
	}

	public function getTriptip(int $articleId): ?array
	{
		$result = $this->connection->createQueryBuilder()
			->select(
				'pnt.id',
				'pnt.title',
				'pnt.public_from',
				'pnc.url AS city_url',
				'pnr.url AS region_url'
			)
			->from('polar_news_triptips', 'pnt')
			->leftJoin('pnt', 'polar_news_triptips2cities', 'pnt2c', 'pnt2c.triptips_id = pnt.id')
			->leftJoin('pnt2c', 'polar_news_cities', 'pnc', 'pnc.id = pnt2c.city_id')
			->leftJoin('pnc', 'polar_news_regions', 'pnr', 'pnr.id = pnc.region_id')
			->where('pnt.id = :articleId')
			->andWhere('pnt.public_from <= NOW()')
			->andWhere('pnt.public_to >= NOW()')
			->orderBy('pnt2c.rank', 'ASC')
			->setParameter('articleId', $articleId, ParameterType::INTEGER)
			->setMaxResults(1)
			->fetchAssociative();

		if (!$result) {
			return null;
		}

		$result['url'] = $this->removeAccent((string) ($result['title'] ?? ''), '-');

		return $result;
	}

	public function getTriptips(int $limit, int $important = 1, ?string $fromDate = null, int $show = 0): ?array
	{
		$qb = $this->connection->createQueryBuilder()
			->select(
				'pnt.id', 'pnt.title', 'pnt.anotation', 'pnt.public_from AS date',
				'pnt.term_from', 'pnt.term_to', 'pnt.place', 'pnt.address',
				'pnt.ticket_price', 'pnt.ticket_link',
				'pnt.operator', 'pnt.operator_address', 'pnt.operator_phone', 'pnt.operator_email', 'pnt.operator_www',
				'pnc.url AS city_url', 'pnc.city',
				'pnr.url AS region_url', 'pnr.region'
			)
			->from('polar_news_triptips', 'pnt')
			->innerJoin('pnt', 'polar_news_triptips2cities', 'pnt2c', 'pnt2c.triptips_id = pnt.id')
			->innerJoin('pnt2c', 'polar_news_cities', 'pnc', 'pnc.id = pnt2c.city_id')
			->innerJoin('pnc', 'polar_news_regions', 'pnr', 'pnr.id = pnc.region_id')
			->groupBy('pnt.id')
			->setMaxResults($limit);

		if ($important) {
			$qb->orderBy('RAND()')->addOrderBy('pnt.term_from', 'ASC')
				->andWhere('pnt.recommended = 1');
		} else {
			$qb->orderBy('pnt.term_from', 'ASC');
		}

		if ($fromDate) {
			$qb->andWhere('((DATE(:fromDate1) BETWEEN DATE(pnt.term_from) AND DATE(pnt.term_to)) OR (DATE(pnt.term_from) >= DATE(:fromDate2)))')
				->setParameter('fromDate1', $fromDate)
				->setParameter('fromDate2', $fromDate);
		} else {
			$qb->andWhere('((DATE(NOW()) BETWEEN DATE(pnt.term_from) AND DATE(pnt.term_to)) OR (DATE(pnt.term_from) >= DATE(NOW())))');
		}

		$qb->andWhere('pnt.public_from <= NOW()')
			->andWhere('pnt.public_to >= NOW()')
			->andWhere('pnt.show = :show')
			->setParameter('show', $show, ParameterType::INTEGER);

		$resultSet = $qb->fetchAllAssociative();
		if (!$resultSet) {
			return null;
		}

		// aasort by term_from
		usort($resultSet, fn($a, $b) => $a['term_from'] <=> $b['term_from']);

		for ($i = 0, $iMax = count($resultSet); $i < $iMax; $i++) {
			$resultSet[$i]['anotation'] = $resultSet[$i]['anotation'] ? mb_substr($resultSet[$i]['anotation'], 0, 300, 'UTF-8') . ((mb_strlen($resultSet[$i]['anotation'], 'UTF-8') > 300) ? '...' : '') : '';
		}

		return $this->processTriptipResults($resultSet, $limit, $fromDate);
	}

	public function getRandTriptipByRegion(int $limit, int $regionId, int $important = 1, ?string $fromDate = null, int $show = 0): ?array     // Dříve "$this->getCoverageTable()->getRandArticleBySectionAndRegion()"
	{
		$qb = $this->connection->createQueryBuilder()
			->select(
				'pnt.id', 'pnt.title', 'pnt.anotation', 'pnt.public_from AS date',
				'pnt.term_from', 'pnt.term_to', 'pnt.place', 'pnt.address',
				'pnt.ticket_price', 'pnt.ticket_link',
				'pnt.operator', 'pnt.operator_address', 'pnt.operator_phone', 'pnt.operator_email', 'pnt.operator_www',
				'pnc.url AS city_url', 'pnc.city',
				'pnr.url AS region_url', 'pnr.region'
			)
			->from('polar_news_triptips', 'pnt')
			->innerJoin('pnt', 'polar_news_triptips2cities', 'pnt2c', 'pnt2c.triptips_id = pnt.id')
			->innerJoin('pnt2c', 'polar_news_cities', 'pnc', 'pnc.id = pnt2c.city_id')
			->innerJoin('pnc', 'polar_news_regions', 'pnr', 'pnr.id = pnc.region_id')
			->groupBy('pnt.id')
			->setMaxResults($limit + 10);

		if ($important) {
			$qb->orderBy('RAND()')->addOrderBy('pnt.term_from', 'ASC')
				->andWhere('pnt.recommended = 1');
		} else {
			$qb->orderBy('pnt.term_from', 'ASC');
		}

		if ($fromDate) {
			$qb->andWhere('((DATE(:fromDate1) BETWEEN DATE(pnt.term_from) AND DATE(pnt.term_to)) OR (DATE(pnt.term_from) >= DATE(:fromDate2)))')
				->setParameter('fromDate1', $fromDate)
				->setParameter('fromDate2', $fromDate);
		} else {
			$qb->andWhere('((DATE(NOW()) BETWEEN DATE(pnt.term_from) AND DATE(pnt.term_to)) OR (DATE(pnt.term_from) >= DATE(NOW())))');
		}

		$qb->andWhere('pnr.id = :regionId')
			->andWhere('pnt.public_from <= NOW()')
			->andWhere('pnt.public_to >= NOW()')
			->andWhere('pnt.show = :show')
			->setParameter('regionId', $regionId, ParameterType::INTEGER)
			->setParameter('show', $show, ParameterType::INTEGER);

		$resultSet = $qb->fetchAllAssociative();
		if (!$resultSet) {
			return null;
		}

		return $this->processTriptipResults($resultSet, $limit, $fromDate);
	}

	public function getRandTriptipByCity(int $limit, int $cityId, int $important = 1, ?string $fromDate = null, int $show = 0): ?array     // Dříve "$this->getCoverageTable()->getRandArticleBySectionAndCity()"
	{
		$qb = $this->connection->createQueryBuilder()
			->select(
				'pnt.id', 'pnt.title', 'pnt.anotation', 'pnt.public_from AS date',
				'pnt.term_from', 'pnt.term_to', 'pnt.place', 'pnt.address',
				'pnt.ticket_price', 'pnt.ticket_link',
				'pnt.operator', 'pnt.operator_address', 'pnt.operator_phone', 'pnt.operator_email', 'pnt.operator_www',
				'pnc.url AS city_url', 'pnc.city',
				'pnr.url AS region_url', 'pnr.region'
			)
			->from('polar_news_triptips', 'pnt')
			->innerJoin('pnt', 'polar_news_triptips2cities', 'pnt2c', 'pnt2c.triptips_id = pnt.id')
			->innerJoin('pnt2c', 'polar_news_cities', 'pnc', 'pnc.id = pnt2c.city_id')
			->innerJoin('pnc', 'polar_news_regions', 'pnr', 'pnr.id = pnc.region_id')
			->groupBy('pnt.id')
			->setMaxResults($limit + 10);

		if ($important) {
			$qb->orderBy('RAND()')->addOrderBy('pnt.term_from', 'ASC')
				->andWhere('pnt.recommended = 1');
		} else {
			$qb->orderBy('pnt.term_from', 'ASC');
		}

		if ($fromDate) {
			$qb->andWhere('((DATE(:fromDate1) BETWEEN DATE(pnt.term_from) AND DATE(pnt.term_to)) OR (DATE(pnt.term_from) >= DATE(:fromDate2)))')
				->setParameter('fromDate1', $fromDate)
				->setParameter('fromDate2', $fromDate);
		} else {
			$qb->andWhere('((DATE(NOW()) BETWEEN DATE(pnt.term_from) AND DATE(pnt.term_to)) OR (DATE(pnt.term_from) >= DATE(NOW())))');
		}

		$qb->andWhere('pnc.id = :cityId')
			->andWhere('pnt.public_from <= NOW()')
			->andWhere('pnt.public_to >= NOW()')
			->andWhere('pnt.show = :show')
			->setParameter('cityId', $cityId, ParameterType::INTEGER)
			->setParameter('show', $show, ParameterType::INTEGER);

		$resultSet = $qb->fetchAllAssociative();
		if (!$resultSet) {
			return null;
		}

		return $this->processTriptipResults($resultSet, $limit, $fromDate);
	}

	public function getOnlineNewsByArticleId(int $articleId, int $page, int $limit = 10): ?array
	{
		$result = $this->connection->createQueryBuilder()
			->select('*')
			->from('polar_news_articles_online')
			->where('article_id = :articleId')
			->andWhere('datetime <= NOW()')
			->orderBy('datetime', 'DESC')
			->setFirstResult(($page - 1) * $limit)
			->setMaxResults($limit)
			->setParameter('articleId', $articleId, ParameterType::INTEGER)
			->fetchAllAssociative();

		return $result ?: null;
	}

	public function getCountOnlineNewsByArticleId(int $articleId): int
	{
		$result = $this->connection->createQueryBuilder()
			->select('COUNT(*) AS count')
			->from('polar_news_articles_online')
			->where('article_id = :articleId')
			->andWhere('datetime <= NOW()')
			->setParameter('articleId', $articleId, ParameterType::INTEGER)
			->fetchAssociative();

		return (int) ($result['count'] ?? 0);
	}

	public function getArticlesByTopicsAndDate(array $tagsId, int $withoutArticleId): ?array
	{
		if ($tagsId === []) {
			return null;
		}

		$now = (new \DateTimeImmutable())->modify('-1 year')->format('Y-m-d H:i:s');
		$result = $this->connection->createQueryBuilder()
			->select('pna2t.article_id')
			->from('polar_news_articles2tags', 'pna2t')
			->leftJoin('pna2t', 'polar_news_articles', 'pna', 'pna.id = pna2t.article_id')
			->where('pna2t.tag_id IN (:tagsId)')
			->andWhere('pna.public_from >= :now')
			->andWhere('pna.id != :withoutArticleId')
			->andWhere('pna.public = 1')
			->andWhere('pna.public_from <= NOW()')
			->andWhere('pna.public_to >= NOW()')
			->setParameter('tagsId', $tagsId, \Doctrine\DBAL\ArrayParameterType::INTEGER)
			->setParameter('now', $now)
			->setParameter('withoutArticleId', $withoutArticleId, ParameterType::INTEGER)
			->fetchFirstColumn();

		return $result ?: null;
	}

	public function getVideoById(int $id): ?array
	{
		$result = $this->connection->createQueryBuilder()
			->select('gf.*')
			->from('gallery_files', 'gf')
			->where('gf.id = :id')
			->andWhere('gf.type = :type')
			->setParameter('id', $id, ParameterType::INTEGER)
			->setParameter('type', 'video')
			->setMaxResults(1)
			->fetchAssociative();

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

	public function getRedactorByUrl(string $url): ?array
	{
		$result = $this->connection->createQueryBuilder()
			->select('*')
			->from('polar_news_redaction')
			->where('url = :url')
			->setParameter('url', $url)
			->fetchAssociative();

		return $result ?: null;
	}

	private function getArticleFiles(int $articleId, string $publicFrom): ?array
	{
		$result = $this->connection->createQueryBuilder()
			->select('gf.folder', 'gf.type', 'gf.module', 'gf.file', 'gf.folder_light', 'gf.size', 'gf.size_hq', 'gf.ext', 'gf.duration', 'gf.description', 'gf.updated_date', 'pna2gf.file_id', 'pna2gf.rank')
			->from('gallery_files', 'gf')
			->leftJoin('gf', 'polar_news_articles2gallery_files', 'pna2gf', 'pna2gf.file_id = gf.id')
			->where('pna2gf.article_id = :articleId')
			->andWhere('pna2gf.checked = 1')
			->orderBy('pna2gf.rank', 'ASC')
			->setParameter('articleId', $articleId)
			->fetchAllAssociative();

		if (!$result) {
			return null;
		}

		foreach ($result as &$file) {
			$version = str_replace(['-', ' ', ':'], '', (string) $file['updated_date']);

			if ($file['type'] === 'video') {
				$file['medium'] = '/data/gallery/modules/' . $file['module'] . '/videos/' . $file['folder'] . '/715x402.jpg?ver=' . $version;
				$file['seznam_image'] = '/data/gallery/modules/' . $file['module'] . '/videos/' . $file['folder'] . '/seznam.jpg';
				if ($publicFrom >= '2015-12-10') {
					$file['link_lq'] = 'https://light.polar.cz/videa/polar/zpravy/publikovano/' . $file['folder_light'] . '/' . $file['file'] . '_lq.mp4';
					$file['link_hq'] = 'https://light.polar.cz/videa/polar/zpravy/publikovano/' . $file['folder_light'] . '/' . $file['file'] . '_hq.mp4';
				} else {
					$file['link_lq'] = 'https://light.polar.cz/videa/polar/zpravy/publikovano/' . $file['folder_light'] . '/' . $file['file'] . '_hq.mp4';
					$file['link_hq'] = 'https://light.polar.cz/videa/polar/zpravy/publikovano/' . $file['folder_light'] . '/' . $file['file'] . '_hq.mp4';
				}
			}

			if ($file['type'] === 'image') {
				$file['medium'] = '/data/gallery/modules/' . $file['module'] . '/images/' . $file['folder'] . '/715x402.' . $file['ext'] . '?ver=' . $version;
				$file['thumb'] = '/data/gallery/modules/' . $file['module'] . '/images/' . $file['folder'] . '/310x174.' . $file['ext'] . '?ver=' . $version;
				$file['full'] = '/data/gallery/modules/' . $file['module'] . '/images/' . $file['folder'] . '/origin.' . $file['ext'] . '?ver=' . $version;
			}
		}
		unset($file);

		return $result;
	}

	private function getArticleTopics(int $articleId): ?array
	{
		$result = $this->connection->createQueryBuilder()
			->select('pnt.id AS tag_id', 'pnt.tag', 'pnt.url')
			->from('polar_news_tags', 'pnt')
			->leftJoin('pnt', 'polar_news_articles2tags', 'pna2t', 'pna2t.tag_id = pnt.id')
			->where('pna2t.article_id = :articleId')
			->setParameter('articleId', $articleId, ParameterType::INTEGER)
			->fetchAllAssociative();

		return $result ?: null;
	}

	private function getArticleLabels(int $articleId, string $updatedDate): ?array
	{
		$cutoff = (new \DateTimeImmutable())->modify('-24 hours')->format('Y-m-d H:i:s');
		if ($updatedDate !== '' && $updatedDate < $cutoff) {
			return null;
		}

		$result = $this->connection->createQueryBuilder()
			->select('pnl.title', 'pnl.color', 'pnl.icon')
			->from('polar_news_label', 'pnl')
			->leftJoin('pnl', 'polar_news_articles2label', 'pna2l', 'pna2l.label_id = pnl.id')
			->where('pna2l.article_id = :articleId')
			->setParameter('articleId', $articleId, ParameterType::INTEGER)
			->fetchAllAssociative();

		return $result ?: null;
	}

	public function getPaginatorByPR(int $page, int $limit): array
	{
		$offset = ($page - 1) * $limit;

		$rows = $this->connection->createQueryBuilder()
			->select('pnp.id', 'pnp.title', 'pnp.anotation', 'pnp.public_from')
			->from('polar_news_pr', 'pnp')
			->where('pnp.public_from <= NOW()')
			->andWhere('pnp.public_to >= NOW()')
			->groupBy('pnp.id')
			->orderBy('pnp.public_from', 'DESC')
			->setFirstResult($offset)
			->setMaxResults($limit)
			->fetchAllAssociative();

		foreach ($rows as $i => $row) {
			$file = $this->connection->createQueryBuilder()
				->select('gf.folder', 'gf.type', 'gf.module', 'gf.ext', 'gf.duration', 'gf.updated_date')
				->from('gallery_files', 'gf')
				->leftJoin('gf', 'polar_news_pr2gallery_files', 'pnp2gf', 'pnp2gf.file_id = gf.id')
				->where('pnp2gf.pr_id = :prId')
				->andWhere('pnp2gf.checked = 1')
				->orderBy('pnp2gf.rank', 'ASC')
				->setMaxResults(1)
				->setParameter('prId', $row['id'], ParameterType::INTEGER)
				->fetchAssociative();

			if ($file) {
				$version = str_replace(['-', ' ', ':'], '', (string) $file['updated_date']);
				if ($file['type'] === 'video') {
					$rows[$i]['picture'] = '/data/gallery/modules/' . $file['module'] . '/videos/' . $file['folder'] . '/310x174.jpg?ver=' . $version;
				}
				if ($file['type'] === 'image') {
					$rows[$i]['picture'] = '/data/gallery/modules/' . $file['module'] . '/images/' . $file['folder'] . '/310x174.' . $file['ext'] . '?ver=' . $version;
				}
				$rows[$i]['duration'] = $file['duration'];
			} else {
				$rows[$i]['picture'] = null;
				$rows[$i]['duration'] = null;
			}
		}

		return $rows;
	}

	public function getCountPR(): int
	{
		$result = $this->connection->createQueryBuilder()
			->select('COUNT(DISTINCT pnp.id) AS total')
			->from('polar_news_pr', 'pnp')
			->where('pnp.public_from <= NOW()')
			->andWhere('pnp.public_to >= NOW()')
			->fetchAssociative();

		return (int) ($result['total'] ?? 0);
	}

	private function processTriptipResults(array $resultSet, int $limit, ?string $fromDate): array
	{
		for ($i = 0, $iMax = count($resultSet); $i < $iMax; $i++) {
			$resultSet[$i]['url'] = '/kam-vyrazit/' . $resultSet[$i]['region_url'] . '/' . $resultSet[$i]['city_url'] . '/' . $resultSet[$i]['id'] . '/' . $this->removeAccent($resultSet[$i]['title'], '-');

			// Soubor z galerie (video / obrázek)
			$file = $this->connection->createQueryBuilder()
				->select('gf.folder', 'gf.type', 'gf.module', 'gf.file', 'gf.folder_light', 'gf.size', 'gf.ext', 'gf.description', 'gf.updated_date')
				->from('gallery_files', 'gf')
				->leftJoin('gf', 'polar_news_triptips2gallery_files', 'pnt2gf', 'pnt2gf.file_id = gf.id')
				->where('pnt2gf.triptip_id = :triptipId')
				->andWhere('pnt2gf.checked = 1')
				->orderBy('pnt2gf.rank', 'ASC')
				->setMaxResults(1)
				->setParameter('triptipId', $resultSet[$i]['id'], ParameterType::INTEGER)
				->fetchAssociative();

			if ($file) {
				$version = str_replace(['-', ' ', ':'], '', (string) $file['updated_date']);
				if ($file['type'] === 'video') {
					$resultSet[$i]['image'] = '/data/gallery/modules/' . $file['module'] . '/videos/' . $file['folder'] . '/310x174.jpg?ver=' . $version;
				}
				if ($file['type'] === 'image') {
					$resultSet[$i]['image'] = '/data/gallery/modules/' . $file['module'] . '/images/' . $file['folder'] . '/310x174.' . $file['ext'] . '?ver=' . $version;
				}
			} else {
				$resultSet[$i]['image'] = null;
			}
		}

		$data = [];
		$count = 0;
		for ($i = 0, $iMax = count($resultSet); $i < $iMax; $i++) {
			if ($resultSet[$i]['image'] != null) {
				$data[] = $resultSet[$i];
				$count++;
			}
			if ($count == $limit) {
				break;
			}
		}

		// vícedenní události na konec dne
		if ($data) {
			if ($fromDate) {
				$fromDay = $fromDate;
			}
			for ($z = 0; $z < 2; $z++) {// pojistka, kvuly prvnim dvema dlouhym udalostem
				for ($i = 0, $iMax = count($data); $i < $iMax; $i++) {
					$term_from = new \DateTime($data[$i]['term_from']);
					if ($data[$i]['term_to']) {
						$term_to = new \DateTime($data[$i]['term_to']);
					} else {
						$term_to = null;
					}
					if ($term_to) {
						$days = $term_to->diff($term_from, true);
						$days = $days->format('%a');
						if ($days >= 2) {
							$x = 1;
							while (isset($data[$i + $x]) && isset($fromDay)) {
								$dateTmp = new \DateTime($data[$i + $x]['term_from']);
								if ($data[$i + $x]['term_to']) {
									$dateToTmp = new \DateTime($data[$i + $x]['term_to']);
									$daysTmp = $dateToTmp->diff($dateTmp, true);
									$daysTmp = $daysTmp->format('%d');
								}
								if (($fromDay > $dateTmp->format('Y-m-d')) && isset($daysTmp) && ($daysTmp >= 2)) {
									$x++;
								} else {
									if ($fromDay < $dateTmp->format('Y-m-d')) {
										for ($y = $i; $y < $x; $y++) {
											$dataTmp = $data[$y];
											$data[$y] = $data[$y + 1];
											$data[$y + 1] = $dataTmp;
										}
									} else {
										$dataTmp = $data[$i];
										$data[$i] = $data[$i + $x];
										$data[$i + $x] = $dataTmp;
									}
									break;
								}
							}
						}
					}
				}
			}
		}

		return $data;
	}

	/**
	 * Hosté ve studiu - seznam
	 */
	public function getGuests(): ?array
	{
		$resultSet = $this->connection->createQueryBuilder()
			->select('id', 'title', 'anotation', 'show_id', 'created_date', 'public_from', 'text')
			->from('polar_news_guests')
			->where('polar_news_guests.public_from <= NOW()')
			->andWhere('polar_news_guests.public_to >= NOW()')
			->groupBy('polar_news_guests.id')
			->orderBy('polar_news_guests.public_from', 'DESC')
			->fetchAllAssociative();

		if (!$resultSet) {
			return null;
		}

		for ($i = 0, $iMax = count($resultSet); $i < $iMax; $i++) {
			// video thumbnail
			$video = $this->connection->createQueryBuilder()
				->select('gf.folder', 'gf.module', 'gf.ext', 'gf.duration', 'gf.updated_date')
				->from('gallery_files', 'gf')
				->leftJoin('gf', 'polar_news_guests2gallery_files', 'g2f', 'g2f.file_id = gf.id')
				->where('g2f.guest_id = :guest_id')
				->andWhere('gf.type = :type')
				->andWhere('g2f.checked = 1')
				->orderBy('g2f.rank', 'ASC')
				->setMaxResults(1)
				->setParameter('guest_id', $resultSet[$i]['id'])
				->setParameter('type', 'video')
				->fetchAssociative();

			if ($video) {
				$resultSet[$i]['picture'] = '/data/gallery/modules/' . $video['module'] . '/videos/' . $video['folder'] . '/715x402.jpg?ver=' . str_replace(['-', ' ', ':'], ['', '', ''], $video['updated_date']);
				$resultSet[$i]['duration'] = $video['duration'];
			} else {
				// image thumbnail
				$image = $this->connection->createQueryBuilder()
					->select('gf.folder', 'gf.module', 'gf.ext', 'gf.updated_date')
					->from('gallery_files', 'gf')
					->leftJoin('gf', 'polar_news_guests2gallery_files', 'g2f', 'g2f.file_id = gf.id')
					->where('g2f.guest_id = :guest_id')
					->andWhere('gf.type = :type')
					->andWhere('g2f.checked = 1')
					->orderBy('g2f.rank', 'ASC')
					->setMaxResults(1)
					->setParameter('guest_id', $resultSet[$i]['id'])
					->setParameter('type', 'image')
					->fetchAssociative();

				if ($image) {
					$resultSet[$i]['picture'] = '/data/gallery/modules/' . $image['module'] . '/images/' . $image['folder'] . '/715x402.' . $image['ext'] . '?ver=' . str_replace(['-', ' ', ':'], ['', '', ''], $image['updated_date']);
					$resultSet[$i]['duration'] = null;
				} else {
					$resultSet[$i]['picture'] = null;
					$resultSet[$i]['duration'] = null;
				}
			}
		}

		return $resultSet;
	}

	/**
	 * Host ve studiu - detail
	 */
	public function getGuest(int $guestId): ?array
	{
		$resultSet = $this->connection->createQueryBuilder()
			->select('*')
			->from('polar_news_guests')
			->where('polar_news_guests.id = :id')
			->setParameter('id', $guestId)
			->setMaxResults(1)
			->fetchAssociative();

		if (!$resultSet) {
			return null;
		}

		// video soubor
		$video = $this->connection->createQueryBuilder()
			->select('gf.folder', 'gf.type', 'gf.module', 'gf.file', 'gf.folder_light', 'gf.size', 'gf.size_hq', 'gf.ext', 'gf.updated_date')
			->addSelect('g2f.file_id')
			->from('gallery_files', 'gf')
			->leftJoin('gf', 'polar_news_guests2gallery_files', 'g2f', 'g2f.file_id = gf.id')
			->where('g2f.guest_id = :guest_id')
			->andWhere('gf.type = :type')
			->andWhere('g2f.checked = 1')
			->orderBy('g2f.rank', 'ASC')
			->setMaxResults(1)
			->setParameter('guest_id', $resultSet['id'])
			->setParameter('type', 'video')
			->fetchAssociative();

		if ($video) {
			$resultSet['picture'] = '/data/gallery/modules/' . $video['module'] . '/videos/' . $video['folder'] . '/715x402.jpg?ver=' . str_replace(['-', ' ', ':'], ['', '', ''], $video['updated_date']);
			$resultSet['type'] = $video['type'];
			$resultSet['folder_light'] = $video['folder_light'];
			$resultSet['file'] = $video['file'];
			$resultSet['size'] = $video['size'];
			$resultSet['size_hq'] = $video['size_hq'];
			$resultSet['file_id'] = $video['file_id'];

			if ($resultSet['public_from'] >= '2015-12-10') {
				$resultSet['link_lq'] = 'https://light.polar.cz/videa/polar/zpravy/publikovano/' . $video['folder_light'] . '/' . $video['file'] . '_lq.mp4';
				$resultSet['link_hq'] = 'https://light.polar.cz/videa/polar/zpravy/publikovano/' . $video['folder_light'] . '/' . $video['file'] . '_hq.mp4';
			} else {
				$resultSet['link_lq'] = 'https://light.polar.cz/videa/polar/zpravy/publikovano/' . $video['folder_light'] . '/' . $video['file'] . '_hq.mp4';
				$resultSet['link_hq'] = 'https://light.polar.cz/videa/polar/zpravy/publikovano/' . $video['folder_light'] . '/' . $video['file'] . '_hq.mp4';
			}
		}

		$resultSet['url'] = $this->removeAccent((string) ($resultSet['title'] ?? ''), '-');

		return $resultSet;
	}

	/**
	 * Hosté ve studiu - podle ID (nejsledovanější)
	 */
	public function getGuestsByIDs(array $ids, int $limit = 5): ?array
	{
		if (!$ids) {
			return null;
		}

		$resultSet = $this->connection->createQueryBuilder()
			->select('*')
			->from('polar_news_guests')
			->where('polar_news_guests.id IN (:ids)')
			->orderBy('FIELD(polar_news_guests.id, ' . implode(',', array_map('intval', $ids)) . ')')
			->setMaxResults($limit)
			->setParameter('ids', $ids, \Doctrine\DBAL\ArrayParameterType::INTEGER)
			->fetchAllAssociative();

		if (!$resultSet) {
			return null;
		}

		for ($i = 0, $iMax = count($resultSet); $i < $iMax; $i++) {
			$video = $this->connection->createQueryBuilder()
				->select('gf.folder', 'gf.module', 'gf.ext', 'gf.updated_date')
				->from('gallery_files', 'gf')
				->leftJoin('gf', 'polar_news_guests2gallery_files', 'g2f', 'g2f.file_id = gf.id')
				->where('g2f.guest_id = :guest_id')
				->andWhere('gf.type = :type')
				->andWhere('g2f.checked = 1')
				->orderBy('g2f.rank', 'ASC')
				->setMaxResults(1)
				->setParameter('guest_id', $resultSet[$i]['id'])
				->setParameter('type', 'video')
				->fetchAssociative();

			if ($video) {
				$resultSet[$i]['picture'] = '/data/gallery/modules/' . $video['module'] . '/videos/' . $video['folder'] . '/310x174.jpg?ver=' . str_replace(['-', ' ', ':'], ['', '', ''], $video['updated_date']);
			} else {
				$image = $this->connection->createQueryBuilder()
					->select('gf.folder', 'gf.module', 'gf.ext', 'gf.updated_date')
					->from('gallery_files', 'gf')
					->leftJoin('gf', 'polar_news_guests2gallery_files', 'g2f', 'g2f.file_id = gf.id')
					->where('g2f.guest_id = :guest_id')
					->andWhere('gf.type = :type')
					->andWhere('g2f.checked = 1')
					->orderBy('g2f.rank', 'ASC')
					->setMaxResults(1)
					->setParameter('guest_id', $resultSet[$i]['id'])
					->setParameter('type', 'image')
					->fetchAssociative();

				if ($image) {
					$resultSet[$i]['picture'] = '/data/gallery/modules/' . $image['module'] . '/images/' . $image['folder'] . '/310x174.' . $image['ext'] . '?ver=' . str_replace(['-', ' ', ':'], ['', '', ''], $image['updated_date']);
				} else {
					$resultSet[$i]['picture'] = null;
				}
			}
		}

		return $resultSet;
	}

	private function removeAccent(string $text, string $replace = ''): string
	{
		$transliterator = \Transliterator::createFromRules(':: Any-Latin; :: NFD; :: [:Nonspacing Mark:] Remove; :: NFC; :: [:Punctuation:] Remove; :: Lower();', \Transliterator::FORWARD);
		$text = $transliterator->transliterate($text);
		$text = preg_replace('/\p{C}+/u', '', $text) ?? $text;
		if ($replace) {
			$text = str_replace(' ', $replace, $text);
		}
		return $text;
	}

	public function getAlRegionsForSearch(string $query, ?int $region_id, ?int $city_id): ?string
	{
		$regions = $this->connection->createQueryBuilder()
			->select('id', 'region', 'url', 'sort')
			->from('polar_news_regions')
			->orderBy('sort', 'ASC')
			->fetchAllAssociative();

		if (!$regions) {
			return null;
		}

		$menu = '<li' . ((!$region_id && !$city_id) ? ' class="active"' : '') . '>' .
			'<a' . ((!$region_id && !$city_id) ? ' class="font-weight-extra-bold"' : '') . ' href="/hledat?q=' . $query . '" title="">Vše</a>' .
			'</li>';

		foreach ($regions as $region) {
			$cities = $this->connection->createQueryBuilder()
				->select('id', 'city', 'url')
				->from('polar_news_cities')
				->where('region_id = :regionId')
				->setParameter('regionId', $region['id'])
				->fetchAllAssociative();

			$menu2 = '';
			if ($cities) {
				$menu2 .= '<ul>';
				foreach ($cities as $city) {
					$menu2 .= '<li' . ((int)$city['id'] === $city_id ? ' class="active"' : '') . '>' .
						'<a' . ((int)$city['id'] === $city_id ? ' class="font-weight-extra-bold"' : '') . ' href="/hledat?q=' . $query . '&c=' . $city['id'] . '" title="">' . $city['city'] . '</a>' .
						'</li>';
				}
				$menu2 .= '</ul>';
			}

			$menu .= '<li' . ((int)$region['id'] === $region_id ? ' class="active"' : '') . '>' .
				'<a' . ((int)$region['id'] === $region_id ? ' class="font-weight-extra-bold"' : '') . ' href="/hledat?q=' . $query . '&r=' . $region['id'] . '" title="">' . $region['region'] . '</a>' .
				$menu2 .
				'</li>';
		}

		return $menu;
	}

	public function getOnlineNewsForSpecialByArticleId(int $article_id, int $limit = 5): ?array
	{
		$qb = $this->connection->createQueryBuilder();
		$qb->select('datetime', 'content')
			->from('polar_news_articles_online')
			->where('article_id = :article_id')
			->andWhere('content NOT LIKE :figure')
			->andWhere('content NOT LIKE :img')
			->andWhere('content NOT LIKE :widget')
			->orderBy('datetime', 'DESC')
			->setMaxResults($limit)
			->setParameter('article_id', $article_id)
			->setParameter('figure', '%<figure>%')
			->setParameter('img', '%<img %')
			->setParameter('widget', '%<div class="widget">%');

		$result = $qb->executeQuery()->fetchAllAssociative();
		return $result ?: null;
	}

	public function getArticleUrlByShortlink(string $shortlink): ?string
	{
		$qb = $this->connection->createQueryBuilder();
		$qb->select('url')
			->from('polar_news_articles_shortlink')
			->where('shortlink = :shortlink')
			->setParameter('shortlink', $shortlink);

		$result = $qb->executeQuery()->fetchOne();
		return $result !== false ? $result : null;
	}
}
