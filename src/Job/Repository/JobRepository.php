<?php

namespace App\Job\Repository;

use Doctrine\DBAL\Connection;

final class JobRepository
{
	public function __construct(private Connection $connection) {}

	public function getRandForWeb(int $kraj = 132, int $limit = 4): ?array
	{
		// Na první místo dáme naše nabídky
		$our = $this->connection->createQueryBuilder()
			->select(
				'pj.id',
				'pj.title AS profese',
				'pj.company AS mistoVykonuPrace_pracoviste_nazev',
				'pj.term AS terminZahajeniPracovnihoPomeru',
				'pj.salary',
				'jo.nazev AS obec',
			)
			->from('polar_joboffer', 'pj')
			->leftJoin('pj', 'joboffer_obce', 'jo', 'jo.kod = pj.city')
			->where('DATE(pj.valid_date_from) <= DATE(NOW())')
			->andWhere('DATE(pj.valid_date_to) >= DATE(NOW())')
			->orderBy('RAND()')
			->fetchAllAssociative();

		$ourCount = count($our);

		// Ostatní nabídky (MPSV)
		$mpsv = $this->connection->createQueryBuilder()
			->select(
				'j.referencniCislo',
				'j.mistoVykonuPrace_pracoviste_nazev',
				'j.mesicniMzdaOd',
				'j.mesicniMzdaDo',
				'j.terminZahajeniPracovnihoPomeru',
				'jo.nazev AS obec',
				'profese_czisco.nazev AS profese',
				'typy_mzdy.nazev AS typMzdy',
				'jok.kod',
			)
			->from('joboffer', 'j')
			->leftJoin('j', 'joboffer_obce', 'jo', 'jo.kod = j.mistoVykonuPrace_obec')
			->leftJoin('j', 'joboffer_profese_czisco', 'profese_czisco', 'profese_czisco.kod = j.profeseCzIsco')
			->leftJoin('j', 'joboffer_typy_mzdy', 'typy_mzdy', 'typy_mzdy.kod = j.typMzdy')
			->leftJoin('jo', 'joboffer_okresy', 'jok', 'jok.kod = jo.okres')
			->where('jok.kraj = :kraj')
			->andWhere('DATE(j.terminZahajeniPracovnihoPomeru) >= DATE(NOW())')
			->setParameter('kraj', $kraj)
			->orderBy('RAND()')
			->setMaxResults($limit - $ourCount)
			->fetchAllAssociative();

		$data = array_merge($our, $mpsv);
		return $data ?: null;
	}

	public function getRandForWebByCityCode(?int $okresCode = 3807, int $limit = 4): ?array
	{
		$our = $this->connection->createQueryBuilder()
			->select(
				'pj.id',
				'pj.title AS profese',
				'pj.company AS mistoVykonuPrace_pracoviste_nazev',
				'pj.term AS terminZahajeniPracovnihoPomeru',
				'pj.salary',
				'jo.nazev AS obec'
			)
			->from('polar_joboffer', 'pj')
			->leftJoin('pj', 'joboffer_obce', 'jo', 'jo.kod = pj.city')
			->where('DATE(pj.valid_date_from) <= DATE(NOW())')
			->andWhere('DATE(pj.valid_date_to) >= DATE(NOW())')
			->orderBy('RAND()')
			->fetchAllAssociative();

		$ourCount = count($our);

		$qb = $this->connection->createQueryBuilder()
			->select(
				'j.referencniCislo',
				'j.mistoVykonuPrace_pracoviste_nazev',
				'j.mesicniMzdaOd',
				'j.mesicniMzdaDo',
				'j.terminZahajeniPracovnihoPomeru',
				'jo.nazev AS obec',
				'profese_czisco.nazev AS profese',
				'typy_mzdy.nazev AS typMzdy',
				'jok.kod'
			)
			->from('joboffer', 'j')
			->leftJoin('j', 'joboffer_obce', 'jo', 'jo.kod = j.mistoVykonuPrace_obec')
			->leftJoin('j', 'joboffer_profese_czisco', 'profese_czisco', 'profese_czisco.kod = j.profeseCzIsco')
			->leftJoin('j', 'joboffer_typy_mzdy', 'typy_mzdy', 'typy_mzdy.kod = j.typMzdy')
			->leftJoin('jo', 'joboffer_okresy', 'jok', 'jok.kod = jo.okres')
			->where('DATE(j.terminZahajeniPracovnihoPomeru) >= DATE(NOW())')
			->orderBy('RAND()')
			->setMaxResults(max(0, $limit - $ourCount));

		if ($okresCode) {
			$qb->andWhere('jok.kod = :okresCode')
				->setParameter('okresCode', $okresCode);
		} else {
			$qb->andWhere('jok.kraj = :kraj')
				->setParameter('kraj', 132);
		}

		$mpsv = $qb->fetchAllAssociative();

		$data = array_merge($our, $mpsv);
		return $data ?: null;
	}
}
