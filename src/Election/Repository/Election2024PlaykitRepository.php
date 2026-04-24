<?php
/*
 * @project polar
 * @author Rostislav Greipel
 * @link https://rogr.cz
 * @copyright (c) 2011 - 2021 ROGR All Rights Reserved
 * @license https://rogr.cz/license/eula EULA License
 */

declare(strict_types=1);

namespace App\Election\Repository;

use Doctrine\DBAL\Connection;

class Election2024PlaykitRepository
{
    /**
     * @param Connection $connection
     */
    public function __construct(
        private Connection $connection,
    ) {}

    /**
     * @return array|null
     */
    public function fetchKzrklAll(): ?array
    {
        $result = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('polar_electionskz2024_kzrkl')
            ->orderBy('LOWER(ZKRATKAK8) COLLATE utf8_czech_ci', 'ASC')
            ->fetchAllAssociative();

        return $result ?: null;
    }

    /**
     * @param string $column
     * @param int|string $value
     * @return array|null
     */
    public function findKzrklPostBy(string $column, int|string $value): ?array
    {
        $result = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('polar_electionskz2024_kzrkl')
            ->where($column . ' = :value')
            ->setParameter('value', $value)
            ->fetchAssociative();

        return $result ?: null;
    }

    /**
     * @param int $kstrana
     * @return array|null
     */
    public function getKzrkByKstrana(int $kstrana): ?array
    {
        $elections = $this->connection->createQueryBuilder()
            ->select('p.KSTRANA', 'p.NAZEVCELK', 'p.NAZEV_STRK', 'p.ZKRATKAK8',
                     'r.HLASY', 'r.PROC_HLASU', 'r.MANDATY')
            ->from('polar_electionskz2024_kzrkl', 'p')
            ->leftJoin('p', 'polar_electionskz2024_results', 'r', 'r.KSTRANA = p.KSTRANA')
            ->where('p.KSTRANA = :kstrana')
            ->setParameter('kstrana', $kstrana)
            ->orderBy('r.HLASY', 'DESC')
            ->groupBy('p.id')
            ->fetchAllAssociative();

        if ($elections) {
            $kzrk = $this->connection->createQueryBuilder()
                ->select('k.PORCISLO', 'k.JMENO', 'k.PRIJMENI', 'k.TITULPRED', 'k.TITULZA',
                         'k.VEK', 'k.POVOLANI', 'k.BYDLISTEN', 'k.POCHLASU', 'k.POCPROC', 'k.MANDAT',
                         'rk.HLASY')
                ->from('polar_electionskz2024_kzrk', 'k')
                ->leftJoin('k', 'polar_electionskz2024_results_kandid', 'rk',
                           'rk.KSTRANA = k.KSTRANA AND rk.PORCISLO = k.PORCISLO')
                ->where('k.KSTRANA = :kstrana')
                ->setParameter('kstrana', $elections[0]['KSTRANA'])
                ->orderBy('k.POCHLASU', 'DESC')
                ->addOrderBy('k.PORCISLO', 'ASC')
                ->fetchAllAssociative();

            $elections[0]['kzrk'] = $kzrk ?: [];
            return $elections[0];
        }

        return null;
    }

    /**
     * @param string $nuts_okres
     * @param int|null $obec_id
     * @return array|null
     */
    public function getResultsOkresyObceForWeb(string $nuts_okres, int|null $obec_id): ?array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('p.*')
            ->from('polar_electionskz2024_kzrkl', 'p');

        if (!$obec_id) {    // pouze okres
            $qb->innerJoin('p', 'polar_electionskz2024_results_okresy', 'r', 'r.KSTRANA = p.KSTRANA')
               ->where('r.NUTS_OKRES = :nuts_okres')
               ->setParameter('nuts_okres', $nuts_okres)
               ->orderBy('r.HLASY', 'DESC');
        } else {    // okres + obec
            $qb->innerJoin('p', 'polar_electionskz2024_results_obce', 'r', 'r.KSTRANA = p.KSTRANA')
               ->where('r.NUTS_OKRES = :nuts_okres')
               ->andWhere('r.CIS_OBEC = :obec_id')
               ->setParameter('nuts_okres', $nuts_okres)
               ->setParameter('obec_id', $obec_id)
               ->orderBy('r.HLASY', 'DESC');
        }

        return $qb->fetchAllAssociative() ?: null;
    }

    /**
     * @param string $nuts_okres
     * @param int|null $obec_id
     * @return array|null
     */
    public function getResultsOkresyObceTotal(string $nuts_okres, int|null $obec_id): ?array
    {
        if (!$obec_id) {    // pouze okres
            $result = $this->connection->createQueryBuilder()
                ->select('*')
                ->from('polar_electionskz2024_results_okresy')
                ->where('NUTS_OKRES = :nuts_okres')
                ->setParameter('nuts_okres', $nuts_okres)
                ->setMaxResults(1)
                ->fetchAssociative();
        } else {    // okres + obec
            $result = $this->connection->createQueryBuilder()
                ->select('*')
                ->from('polar_electionskz2024_results_obce')
                ->where('CIS_OBEC = :obec_id')
                ->setParameter('obec_id', $obec_id)
                ->setMaxResults(1)
                ->fetchAssociative();
        }

        return $result ?: null;
    }

    /**
     * @param string $column
     * @param int|string $value
     * @return array|null
     */
    public function getKzcocoArrayByColumn(string $column, int|string $value): ?array
    {
        $result = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('polar_electionskz2024_kzcoco')
            ->where($column . ' = :value')
            ->setParameter('value', $value)
            ->orderBy('LOWER(NAZEVOBCE) COLLATE utf8_czech_ci')
            ->fetchAllAssociative();

        return $result ?: null;
    }

    /**
     * @param string $column
     * @param int|string $value
     * @return array|null
     */
    public function getKzcocoByColumn(string $column, int|string $value): ?array
    {
        $result = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('polar_electionskz2024_kzcoco')
            ->where($column . ' = :value')
            ->setParameter('value', $value)
            ->fetchAssociative();

        return $result ?: null;
    }

    /**
     * @return array|null
     */
    public function fetchAllMandatForWeb(): ?array
    {
        $elections = $this->connection->createQueryBuilder()
            ->select('p.KSTRANA', 'p.NAZEVCELK', 'p.NAZEV_STRK',
                     'r.HLASY', 'r.PROC_HLASU', 'r.MANDATY')
            ->from('polar_electionskz2024_kzrkl', 'p')
            ->leftJoin('p', 'polar_electionskz2024_results', 'r', 'r.KSTRANA = p.KSTRANA')
            ->andWhere('r.MANDATY <> 0') /* během sčítání zakomentovat */
            ->orderBy('r.HLASY', 'DESC')
            ->groupBy('p.id')
            ->fetchAllAssociative();

        for ($i = 0, $iMax = count($elections); $i < $iMax; $i++) {
            $kzrk = $this->connection->createQueryBuilder()
                ->select('PORCISLO', 'JMENO', 'PRIJMENI', 'TITULPRED', 'TITULZA',
                         'VEK', 'POVOLANI', 'BYDLISTEN', 'POCHLASU', 'POCPROC', 'MANDAT')
                ->from('polar_electionskz2024_kzrk')
                ->where('MANDAT = :mandat') /* během sčítání zakomentovat */
                ->andWhere('KSTRANA = :kstrana')
                ->setParameter('mandat', 'A')
                ->setParameter('kstrana', $elections[$i]['KSTRANA'])
                ->orderBy('POCHLASU', 'DESC')
                ->addOrderBy('PORCISLO', 'ASC')
                ->fetchAllAssociative();

            $elections[$i]['kzrk'] = $kzrk ?: [];
        }

        return $elections ?: null;
    }

    /**
     * @return array|null
     */
    public function fetchAllKreslaForWeb(): ?array
    {
        $result = $this->connection->createQueryBuilder()
            ->select('p.KSTRANA', 'p.NAZEVCELK', 'p.NAZEV_STRK', 'p.ZKRATKAK8',
                     'r.HLASY', 'r.PROC_HLASU', 'r.MANDATY')
            ->from('polar_electionskz2024_kzrkl', 'p')
            ->leftJoin('p', 'polar_electionskz2024_results', 'r', 'r.KSTRANA = p.KSTRANA')
            /*->andWhere('r.MANDATY <> 0')*/
            ->orderBy('r.MANDATY', 'DESC')
            ->addOrderBy('r.HLASY', 'DESC')
            ->groupBy('p.id')
            ->fetchAllAssociative();

        return $result ?: null;
    }

    /**
     * @return array|null
     */
    public function getResultsTotal(): ?array
    {
        $result = $this->connection->createQueryBuilder()
            ->select('PLATNE_HLASY', 'OKRSKY_ZPRAC_PROC', 'UCAST_PROC')
            ->from('polar_electionskz2024_results')
            ->setMaxResults(1)
            ->fetchAssociative();

        return $result ?: null;
    }

    /**
     * @return array|null
     */
    public function fetchKzrklForBootstrapSelect(): ?array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('polar_electionskz2024_kzrkl')
            ->orderBy('LOWER(ZKRATKAK8) COLLATE utf8_czech_ci', 'ASC')
            ->fetchAllAssociative();

        $data = [];
        $data[] = [
            'value' => null,
            'label' => null,
        ];
        foreach ($rows as $item) {
            $data[] = [
                'value' => $item['ZKRATKAK8'],
                'label' => $item['ZKRATKAK8'],
            ];
        }
        return $data;
    }
}
