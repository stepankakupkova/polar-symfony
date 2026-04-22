<?php

namespace App\Job\Repository;

use Doctrine\DBAL\Connection;

final class JobOurRepository
{
	public function __construct(private Connection $connection) {}

	public function fetchRandForWeb(int $limit): ?array
	{
		return $this->connection->createQueryBuilder()
			->select('pj.id', 'pj.title', 'pj.company', 'pj.term', 'pj.salary', 'jo.nazev AS city')
			->from('polar_joboffer', 'pj')
			->leftJoin('pj', 'joboffer_obce', 'jo', 'jo.kod = pj.city')
			->where('DATE(pj.valid_date_from) <= DATE(NOW())')
			->andWhere('DATE(pj.valid_date_to) >= DATE(NOW())')
			->orderBy('RAND()')
			->setMaxResults($limit)
			->fetchAllAssociative() ?: null;
	}

	public function fetchForBootstrapTable(array $params): array
	{
		$qb = $this->connection->createQueryBuilder();
		$qb->select(
			'id', 'title', 'place', 'term', 'salary',
			'company', 'company_in', 'company_street', 'company_city', 'company_postcode',
			'person', 'phone', 'email', 'content', 'valid_date_from', 'valid_date_to', 'export_tv',
			'created_date', 'updated_date', 'created_user', 'updated_user'
		)
			->from('polar_joboffer', 'polar_joboffer')
			->leftJoin('polar_joboffer', 'joboffer_pracovnepravni_vztahy', 'joboffer_pracovnepravni_vztahy', 'joboffer_pracovnepravni_vztahy.kod = polar_joboffer.employment')
			->leftJoin('polar_joboffer', 'joboffer_vzdelani', 'joboffer_vzdelani', 'joboffer_vzdelani.kod = polar_joboffer.education')
			->leftJoin('polar_joboffer', 'joboffer_smennosti', 'joboffer_smennosti', 'joboffer_smennosti.kod = polar_joboffer.shift')
			->leftJoin('polar_joboffer', 'joboffer_obce', 'joboffer_obce', 'joboffer_obce.kod = polar_joboffer.city')
			->orderBy($params['sort'] . ' ' . $params['order'])
			->setMaxResults((int) $params['limit'])
			->setFirstResult((int) $params['offset']);

		if (!empty($params['search'])) {
			$qb->where('MATCH (title, place, company, company_in) AGAINST (:search IN BOOLEAN MODE)')
				->setParameter('search', $params['search'] . '*');
		}

		return $qb->fetchAllAssociative() ?: [];
	}

	public function getCount(): int
	{
		return (int) $this->connection->createQueryBuilder()
			->select('COUNT(*) AS count')
			->from('polar_joboffer', 'polar_joboffer')
			->fetchOne();
	}

	public function getCountForBootstrapTable(array $params): int
	{
		$qb = $this->connection->createQueryBuilder()
			->select('COUNT(*) AS count')
			->from('polar_joboffer', 'polar_joboffer');

		if (!empty($params['search'])) {
			$qb->where('MATCH (title, place, company, company_in) AGAINST (:search IN BOOLEAN MODE)')
				->setParameter('search', $params['search'] . '*');
		}

		return (int) $qb->fetchOne();
	}

	public function getPostForWeb(int $id): ?array
	{
		return $this->connection->createQueryBuilder()
			->select(
				'pj.id', 'pj.title', 'pj.place', 'pj.term', 'pj.salary',
				'pj.company', 'pj.company_in', 'pj.company_street', 'pj.company_city', 'pj.company_postcode',
				'pj.person', 'pj.phone', 'pj.email', 'pj.content', 'pj.valid_date_from', 'pj.valid_date_to',
				'pj.created_date', 'pj.updated_date', 'pj.created_user', 'pj.updated_user',
				'pp.nazev AS employment',
				'v.nazev AS education',
				's.nazev AS shift',
				'jo.nazev AS city',
			)
			->from('polar_joboffer', 'pj')
			->leftJoin('pj', 'joboffer_pracovnepravni_vztahy', 'pp', 'pp.kod = pj.employment')
			->leftJoin('pj', 'joboffer_vzdelani', 'v', 'v.kod = pj.education')
			->leftJoin('pj', 'joboffer_smennosti', 's', 's.kod = pj.shift')
			->leftJoin('pj', 'joboffer_obce', 'jo', 'jo.kod = pj.city')
			->where('pj.id = :id')
			->setParameter('id', $id)
			->fetchAssociative() ?: null;
	}
}
