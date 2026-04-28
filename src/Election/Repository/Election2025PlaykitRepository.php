<?php

declare(strict_types=1);

namespace App\Election\Repository;

use Doctrine\DBAL\Connection;

class Election2025PlaykitRepository
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
    public function fetchPsrklAll(): ?array
    {
        $result = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('polar_electionsps2025_psrkl')
            ->orderBy('LOWER(ZKRATKAK8) COLLATE utf8_czech_ci', 'ASC')
            ->fetchAllAssociative();

        return $result ?: null;
    }

    /**
     * @param string $column
     * @param int|string $value
     * @return array|null
     */
    public function findPsrklPostBy(string $column, int|string $value): ?array
    {
        $result = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('polar_electionsps2025_psrkl')
            ->where($column . ' = :value')
            ->setParameter('value', $value)
            ->fetchAssociative();

        return $result ?: null;
    }

    /**
     * @param int $kstrana
     * @return array|null
     */
    public function getPsrkByKstrana(int $kstrana): ?array
    {
        $elections = $this->connection->createQueryBuilder()
            ->select('p.KSTRANA', 'p.NAZEVCELK', 'p.NAZEV_STRK', 'p.ZKRATKAK8',
                     'r.HLASY', 'r.PROC_HLASU', 'r.MANDATY')
            ->from('polar_electionsps2025_psrkl', 'p')
            ->leftJoin('p', 'polar_electionsps2025_results', 'r', 'r.KSTRANA = p.KSTRANA')
            ->where('p.KSTRANA = :kstrana')
            ->setParameter('kstrana', $kstrana)
            ->orderBy('r.HLASY', 'DESC')
            ->groupBy('p.id')
            ->fetchAllAssociative();

        if ($elections) {
            $psrk = $this->connection->createQueryBuilder()
                ->select('k.PORCISLO', 'k.JMENO', 'k.PRIJMENI', 'k.TITULPRED', 'k.TITULZA',
                         'k.VEK', 'k.POVOLANI', 'k.BYDLISTEN', 'k.POCHLASU', 'k.POCPROC', 'k.MANDAT',
                         'rk.HLASY')
                ->from('polar_electionsps2025_psrk', 'k')
                ->leftJoin('k', 'polar_electionsps2025_results_kandid', 'rk',
                           'rk.KSTRANA = k.KSTRANA AND rk.PORCISLO = k.PORCISLO')
                ->where('k.KSTRANA = :kstrana')
                ->setParameter('kstrana', $elections[0]['KSTRANA'])
                ->orderBy('k.POCHLASU', 'DESC')
                ->addOrderBy('k.PORCISLO', 'ASC')
                ->fetchAllAssociative();

            $elections[0]['psrk'] = $psrk ?: [];
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
            ->select('p.*', 'r.*')
            ->from('polar_electionsps2025_psrkl', 'p');

        if (!$obec_id) {    // pouze okres
            $qb->innerJoin('p', 'polar_electionsps2025_results_okresy', 'r', 'r.KSTRANA = p.KSTRANA')
               ->where('r.NUTS_OKRES = :nuts_okres')
               ->setParameter('nuts_okres', $nuts_okres)
               ->orderBy('r.HLASY', 'DESC');
        } else {    // okres + obec
            $qb->innerJoin('p', 'polar_electionsps2025_results_obce', 'r', 'r.KSTRANA = p.KSTRANA')
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
                ->from('polar_electionsps2025_results_okresy')
                ->where('NUTS_OKRES = :nuts_okres')
                ->setParameter('nuts_okres', $nuts_okres)
                ->setMaxResults(1)
                ->fetchAssociative();
        } else {    // okres + obec
            $result = $this->connection->createQueryBuilder()
                ->select('*')
                ->from('polar_electionsps2025_results_obce')
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
    public function getPscocoArrayByColumn(string $column, int|string $value): ?array
    {
        $result = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('polar_electionsps2025_pscoco')
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
    public function getPscocoByColumn(string $column, int|string $value): ?array
    {
        $result = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('polar_electionsps2025_pscoco')
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
            ->from('polar_electionsps2025_psrkl', 'p')
            ->leftJoin('p', 'polar_electionsps2025_results', 'r', 'r.KSTRANA = p.KSTRANA')
            ->where('r.KRAJ = :kraj')
            ->andWhere('r.MANDATY <> 0') /* během sčítání zakomentovat */
            ->setParameter('kraj', 'MSK')
            ->orderBy('r.HLASY', 'DESC')
            ->groupBy('p.id')
            ->fetchAllAssociative();

        for ($i = 0, $iMax = count($elections); $i < $iMax; $i++) {
            $psrk = $this->connection->createQueryBuilder()
                ->select('PORCISLO', 'JMENO', 'PRIJMENI', 'TITULPRED', 'TITULZA',
                         'VEK', 'POVOLANI', 'BYDLISTEN', 'POCHLASU', 'POCPROC', 'MANDAT')  // počty hlasů bereme z výsledků. Po seštení budeme brát z registrů, pak zakomentovat JOIN a přidat tady do columns: 'POCHLASU'
                //$select->join('polar_electionsps2025_results_kandid', ...)  // JOIN zakomentován
                ->from('polar_electionsps2025_psrk')
                ->where('MANDAT = :mandat') /* během sčítání zakomentovat */
                ->andWhere('KSTRANA = :kstrana')
                ->setParameter('mandat', 'A')
                ->setParameter('kstrana', $elections[$i]['KSTRANA'])
                ->orderBy('POCHLASU', 'DESC')
                ->addOrderBy('PORCISLO', 'ASC')
                ->fetchAllAssociative();

            $elections[$i]['psrk'] = $psrk ?: [];
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
            ->from('polar_electionsps2025_psrkl', 'p')
            ->leftJoin('p', 'polar_electionsps2025_results', 'r', 'r.KSTRANA = p.KSTRANA')
            ->where('r.KRAJ = :kraj')
            /*->andWhere('r.MANDATY <> 0')*/
            ->setParameter('kraj', 'MSK')
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
            ->from('polar_electionsps2025_results')
            ->where('KRAJ = :kraj')
            ->setParameter('kraj', 'MSK')
            ->setMaxResults(1)
            ->fetchAssociative();

        return $result ?: null;
    }

    /**
     * @return array|null
     */
    public function fetchPsrklForBootstrapSelect(): ?array
    {
        return $this->connection->createQueryBuilder()
            ->select('*')
            ->from('polar_electionsps2025_psrkl')
            ->orderBy('LOWER(ZKRATKAK8) COLLATE utf8_czech_ci', 'ASC')
            ->fetchAllAssociative();
    }
}
