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

	public function getPaginator(int $kraj = 132, ?int $okres = null, int $page = 1, int $limit = 10, int|string|null $oborCode = null, int|string|null $vztahCode = null, int|string|null $vzdelaniCode = null): array
	{
		$offset = ($page - 1) * $limit;
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
				'jok.kod',
			)
			->from('joboffer', 'j')
			->leftJoin('j', 'joboffer_obce', 'jo', 'jo.kod = j.mistoVykonuPrace_obec')
			->leftJoin('j', 'joboffer_profese_czisco', 'profese_czisco', 'profese_czisco.kod = j.profeseCzIsco')
			->leftJoin('j', 'joboffer_typy_mzdy', 'typy_mzdy', 'typy_mzdy.kod = j.typMzdy')
			->leftJoin('jo', 'joboffer_okresy', 'jok', 'jok.kod = jo.okres')
			->andWhere('DATE(j.terminZahajeniPracovnihoPomeru) >= DATE(NOW())')
			->andWhere('jok.kraj = :kraj')
			->setParameter('kraj', $kraj)
			->orderBy('j.datumZmeny', 'DESC')
			->setFirstResult($offset)
			->setMaxResults($limit);

		if ($okres) {
			$qb->andWhere('jok.kod = :okres')->setParameter('okres', $okres);
		}
		if ($oborCode !== null) {
			$qb->andWhere('profese_czisco.oborCinnostiVm = :obor')->setParameter('obor', $oborCode);
		}
		if ($vztahCode !== null) {
			$qb->leftJoin('j', 'joboffer2pracovnepravni_vztahy', 'j2p', 'j2p.portalId = j.portalId')
				->andWhere('j2p.kod = :vztah')->setParameter('vztah', $vztahCode);
		}
		if ($vzdelaniCode !== null) {
			$qb->andWhere('j.minPozadovaneVzdelani = :vzdelani')->setParameter('vzdelani', $vzdelaniCode);
		}

		$items = $qb->fetchAllAssociative() ?: [];

		// count query
		$cqb = $this->connection->createQueryBuilder()
			->select('COUNT(j.referencniCislo)')
			->from('joboffer', 'j')
			->leftJoin('j', 'joboffer_obce', 'jo', 'jo.kod = j.mistoVykonuPrace_obec')
			->leftJoin('j', 'joboffer_profese_czisco', 'profese_czisco', 'profese_czisco.kod = j.profeseCzIsco')
			->leftJoin('jo', 'joboffer_okresy', 'jok', 'jok.kod = jo.okres')
			->andWhere('DATE(j.terminZahajeniPracovnihoPomeru) >= DATE(NOW())')
			->andWhere('jok.kraj = :kraj')
			->setParameter('kraj', $kraj);

		if ($okres) {
			$cqb->andWhere('jok.kod = :okres')->setParameter('okres', $okres);
		}
		if ($oborCode !== null) {
			$cqb->andWhere('profese_czisco.oborCinnostiVm = :obor')->setParameter('obor', $oborCode);
		}
		if ($vztahCode !== null) {
			$cqb->leftJoin('j', 'joboffer2pracovnepravni_vztahy', 'j2p', 'j2p.portalId = j.portalId')
				->andWhere('j2p.kod = :vztah')->setParameter('vztah', $vztahCode);
		}
		if ($vzdelaniCode !== null) {
			$cqb->andWhere('j.minPozadovaneVzdelani = :vzdelani')->setParameter('vzdelani', $vzdelaniCode);
		}

		return [
			'items' => $items,
			'total' => (int) $cqb->fetchOne(),
		];
	}

	public function fetchForBootstrapTable(array $params): array
	{
		$qb = $this->connection->createQueryBuilder();
		$qb->select(
			'portalId', 'referencniCislo', 'azylant', 'cizinecMimoEu', 'datumVlozeni', 'datumZmeny', 'mesicniMzdaDo', 'mesicniMzdaOd',
			'modraKarta', 'pocetHodinTydne', 'pocetMist', 'pozadovanaProfese', 'terminZahajeniPracovnihoPomeru', 'terminUkonceniPracovnihoPomeru',
			'upresnujiciInformace', 'urlAdresa', 'zamestnaneckaKarta', 'mistoVykonuPrace_pracoviste_nazev', 'mistoVykonuPrace_pracoviste_email',
			'mistoVykonuPrace_pracoviste_telefon', 'mistoVykonuPrace_pracoviste_adresa_ulice', 'mistoVykonuPrace_pracoviste_adresa_cisloDomovni',
			'mistoVykonuPrace_pracoviste_adresa_cisloOrientacni', 'mistoVykonuPrace_pracoviste_adresa_psc', 'mistoVykonuPrace_pracoviste_adresa_kraj',
			'mistoVykonuPrace_pracoviste_adresa_okres', 'mistoVykonuPrace_pracoviste_adresa_obec', 'mistoVykonuPrace_pracoviste_adresa_castObce',
			'zamestnavatel_nazev', 'zamestnavatel_ico', 'kontaktniOsobaZamestnavatele_jmeno', 'kontaktniOsobaZamestnavatele_prijmeni',
			'kontaktniOsobaZamestnavatele_titulPredJmenem', 'kontaktniOsobaZamestnavatele_titulZaJmenem', 'kontaktniOsobaZamestnavatele_poziceVeSpolecnosti',
			'prvniKontaktSeZamestnavatelem_mistoKontaktu', 'prvniKontaktSeZamestnavatelem_kontaktniTelefon', 'prvniKontaktSeZamestnavatelem_kontaktniEmail',
			'prvniKontaktSeZamestnavatelem_jmenoKontaktniOsoby', 'prvniKontaktSeZamestnavatelem_prijmeniKontaktniOsoby', 'prvniKontaktSeZamestnavatelem_adresaKontaktu',
			'prvniKontaktSeZamestnavatelem_titulPredJmenem', 'prvniKontaktSeZamestnavatelem_poziceVeSpolecnosti', 'prvniKontaktSeZamestnavatelem_titulZaJmenem'
		)
		->from('joboffer', 'joboffer')
		->leftJoin('joboffer', 'joboffer_profese_czisco', 'joboffer_profese_czisco', 'joboffer_profese_czisco.kod = joboffer.profeseCzIsco')
		->leftJoin('joboffer', 'joboffer_typy_mzdy', 'joboffer_typy_mzdy', 'joboffer_typy_mzdy.kod = joboffer.typMzdy')
		->leftJoin('joboffer', 'joboffer_vzdelani', 'joboffer_vzdelani', 'joboffer_vzdelani.kod = joboffer.minPozadovaneVzdelani')
		->leftJoin('joboffer', 'joboffer_smennosti', 'joboffer_smennosti', 'joboffer_smennosti.kod = joboffer.smennost')
		->leftJoin('joboffer', 'joboffer_obce', 'joboffer_obce', 'joboffer_obce.kod = joboffer.mistoVykonuPrace_pracoviste_adresa_obec')
		->leftJoin('joboffer', 'joboffer_casti_obci', 'joboffer_casti_obci', 'joboffer_casti_obci.kod = joboffer.mistoVykonuPrace_pracoviste_adresa_castObce')
		->leftJoin('joboffer', 'joboffer_okresy', 'joboffer_okresy', 'joboffer_okresy.kod = joboffer.mistoVykonuPrace_pracoviste_adresa_okres')
		->leftJoin('joboffer', 'joboffer_kraje', 'joboffer_kraje', 'joboffer_kraje.kod = joboffer.mistoVykonuPrace_pracoviste_adresa_kraj')
		->orderBy($params['sort'] . ' ' . $params['order'])
		->setMaxResults((int) $params['limit'])
		->setFirstResult((int) $params['offset']);

		if (!empty($params['search'])) {
			$qb->where('MATCH (portalId, referencniCislo, datumVlozeni, datumZmeny) AGAINST (:search IN BOOLEAN MODE)')
				->setParameter('search', $params['search'] . '*');
		}

		return $qb->fetchAllAssociative() ?: [];
	}

	public function getCount(): int
	{
		return (int) $this->connection->createQueryBuilder()
			->select('COUNT(*) AS count')
			->from('joboffer', 'joboffer')
			->fetchOne();
	}

	public function getCountForBootstrapTable(array $params): int
	{
		$qb = $this->connection->createQueryBuilder()
			->select('COUNT(*) AS count')
			->from('joboffer', 'joboffer');

		if (!empty($params['search'])) {
			$qb->where('MATCH (portalId, referencniCislo, datumVlozeni, datumZmeny) AGAINST (:search IN BOOLEAN MODE)')
				->setParameter('search', $params['search'] . '*');
		}

		return (int) $qb->fetchOne();
	}

	public function getForWeb(int $id): ?array
	{
		return $this->connection->createQueryBuilder()
			->select(
				'j.*',
				'jo.nazev AS obec',
				'profese_czisco.nazev AS profese',
				'typy_mzdy.nazev AS typMzdy',
				'jok.kod AS okres_kod',
				'jok.nazev AS okres_nazev',
				'z.nazev AS zamestnavatel_nazev',
			)
			->from('joboffer', 'j')
			->leftJoin('j', 'joboffer_obce', 'jo', 'jo.kod = j.mistoVykonuPrace_obec')
			->leftJoin('j', 'joboffer_profese_czisco', 'profese_czisco', 'profese_czisco.kod = j.profeseCzIsco')
			->leftJoin('j', 'joboffer_typy_mzdy', 'typy_mzdy', 'typy_mzdy.kod = j.typMzdy')
			->leftJoin('jo', 'joboffer_okresy', 'jok', 'jok.kod = jo.okres')
			->leftJoin('j', 'joboffer_zamestnavatele', 'z', 'z.ico = j.zamestnavatelIco')
			->where('j.referencniCislo = :id')
			->setParameter('id', $id)
			->fetchAssociative() ?: null;
	}

	public function getAllOboryCinnostiVmForMenu(): array
	{
		$rows = $this->connection->createQueryBuilder()
			->select('kod', 'nazev')
			->from('joboffer_obory_cinnosti_vm')
			->orderBy('nazev', 'ASC')
			->fetchAllAssociative();
		foreach ($rows as $i => $row) {
			$rows[$i]['url'] = $this->removeAccent($row['nazev'], '-');
		}
		return $rows;
	}

	public function getOborCinnostiVmByUrl(string $url): ?array
	{
		$rows = $this->connection->createQueryBuilder()
			->select('kod', 'nazev')
			->from('joboffer_obory_cinnosti_vm')
			->fetchAllAssociative();
		foreach ($rows as $row) {
			if ($this->removeAccent($row['nazev'], '-') === $url) {
				$row['url'] = $url;
				return $row;
			}
		}
		return null;
	}

	public function getAllPracovnepravniVztahyForMenu(): array
	{
		$rows = $this->connection->createQueryBuilder()
			->select('kod', 'nazev')
			->from('joboffer_pracovnepravni_vztahy')
			->orderBy('nazev', 'ASC')
			->fetchAllAssociative();
		foreach ($rows as $i => $row) {
			$rows[$i]['url'] = $this->removeAccent($row['nazev'], '-');
		}
		return $rows;
	}

	public function getPracovnepravniVztahByUrl(string $url): ?array
	{
		$rows = $this->connection->createQueryBuilder()
			->select('kod', 'nazev')
			->from('joboffer_pracovnepravni_vztahy')
			->fetchAllAssociative();
		foreach ($rows as $row) {
			if ($this->removeAccent($row['nazev'], '-') === $url) {
				$row['url'] = $url;
				return $row;
			}
		}
		return null;
	}

	public function getAllVzdelaniForMenu(): array
	{
		$rows = $this->connection->createQueryBuilder()
			->select('kod', 'nazev')
			->from('joboffer_vzdelani')
			->orderBy('nazev', 'ASC')
			->fetchAllAssociative();
		foreach ($rows as $i => $row) {
			$rows[$i]['url'] = $this->removeAccent($row['nazev'], '-');
		}
		return $rows;
	}

	public function getVzdelaniByUrl(string $url): ?array
	{
		$rows = $this->connection->createQueryBuilder()
			->select('kod', 'nazev')
			->from('joboffer_vzdelani')
			->fetchAllAssociative();
		foreach ($rows as $row) {
			if ($this->removeAccent($row['nazev'], '-') === $url) {
				$row['url'] = $url;
				return $row;
			}
		}
		return null;
	}

	private function removeAccent(string $string, string $separator = '-'): string
	{
		$transliterator = \Transliterator::create('Any-Latin; Latin-ASCII; Lower()');
		$string = $transliterator->transliterate($string);
		$string = preg_replace('/[^a-z0-9]+/', $separator, $string);
		return trim($string, $separator);
	}
}
