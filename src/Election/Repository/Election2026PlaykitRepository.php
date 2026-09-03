<?php

declare(strict_types=1);

namespace App\Election\Repository;

use Doctrine\DBAL\Connection;

class Election2026PlaykitRepository
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
    public function fetchKvrkAll(): ?array
    {
        $result = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('polar_electionszo2026_kvrk')
            ->orderBy('OSTRANA', 'ASC')
            ->fetchAllAssociative();

        return $result ?: null;
    }

    /**
     * @param string $column
     * @param int|string $value
     * @return array|null
     */
    public function getKvrosByColumn(string $column, int|string $value): ?array
    {
        $result = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('polar_electionszo2026_kvros')
            ->where($column . ' = :value')
            ->setParameter('value', $value)
            ->fetchAssociative();

        return $result ?: null;
    }

    /**
     * @param int $obec_id
     * @return array|null
     */
    public function getKvrkByObec(int $obec_id): ?array
    {
        $elections = $this->connection->createQueryBuilder()
            ->select('k.KODZASTUP', 'k.NAZEVCELK', 'k.VSTRANA', 'k.OSTRANA',
                     'r.id', 'r.VOLEBNI_STRANA_HLASY', 'r.VOLEBNI_STRANA_HLASY_PROC', 'r.ZASTUPITELE_POCET')
            ->from('polar_electionszo2026_kvros', 'k')
            ->leftJoin('k', 'polar_electionszo2026_results_obce', 'r',
                       'r.VSTRANA = k.VSTRANA AND r.KODZASTUP = k.KODZASTUP AND r.NAZEV_STRANY = k.NAZEVCELK')
            ->where('k.KODZASTUP = :obec_id')
            ->setParameter('obec_id', $obec_id)
            ->orderBy('r.VOLEBNI_STRANA_HLASY', 'DESC')
            ->addOrderBy('LOWER(r.NAZEV_STRANY) COLLATE utf8_czech_ci', 'ASC')
            ->groupBy('k.OSTRANA')
            ->fetchAllAssociative();

        if ($elections) {
            for ($i = 0, $iMax = count($elections); $i < $iMax; $i++) {
                $kvrk = $this->connection->createQueryBuilder()
                ->select('k.PORCISLO', 'k.JMENO', 'k.PRIJMENI', 'k.TITULPRED', 'k.TITULZA',
                         'k.VEK', 'k.POVOLANI', 'k.BYDLISTEN', 'k.POCHLASU', 'k.POCPROCVSE', 'k.MANDAT',
                         'COALESCE(r.ZASTUPITEL_HLASY, k.POCHLASU) AS HLASY')
                ->from('polar_electionszo2026_kvrk', 'k')
                ->leftJoin('k', 'polar_electionszo2026_results_obce', 'r',
                           'r.KODZASTUP = k.KODZASTUP AND r.JMENO = k.JMENO AND r.PRIJMENI = k.PRIJMENI AND r.PORADOVE_CISLO = k.PORCISLO')
                ->where('k.OSTRANA = :OSTRANA')
                ->andWhere('k.KODZASTUP = :obec_id')
                ->setParameter('OSTRANA', $elections[$i]['OSTRANA'])
                ->setParameter('obec_id', $obec_id)
                ->orderBy('r.ZASTUPITEL_HLASY', 'DESC')
                ->addOrderBy('k.POCHLASU', 'DESC')
                ->addOrderBy('k.PORCISLO', 'ASC')
                ->groupBy('k.PORCISLO')
                ->fetchAllAssociative();

                $elections[$i]['kvrk'] = $kvrk ?: [];
            }

            return $elections;
        }

        return null;
    }

    /**
     * @param int $obec_id
     * @return array|null
     */
    public function getResultsOkresyObceForWeb(int $obec_id): ?array
    {
        $result = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('polar_electionszo2026_results_obce')
            ->where('KODZASTUP = :obec_id')
            ->setParameter('obec_id', $obec_id)
            ->orderBy('VOLEBNI_STRANA_HLASY', 'DESC')
            ->addOrderBy('LOWER(NAZEV_STRANY) COLLATE utf8_czech_ci', 'ASC')
            ->groupBy('NAZEV_STRANY')
            ->fetchAllAssociative();

        return $result ?: null;
    }

    /**
     * @param int $obec_id
     * @return array|null
     */
    public function getResultsOkresyObceTotal(int $obec_id): ?array
    {
        $result = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('polar_electionszo2026_results_obce')
            ->where('KODZASTUP = :obec_id')
            ->setParameter('obec_id', $obec_id)
            ->setMaxResults(1)
            ->fetchAssociative();

        return $result ?: null;
    }

    /**
     * @param string $column
     * @param int|string $value
     * @return array|null
     */
    public function getKvrosArrayByColumn(string $column, int|string $value): ?array
    {
        $result = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('polar_electionszo2026_kvros')
            ->where($column . ' = :value')
            ->setParameter('value', $value)
            ->orderBy('LOWER(NAZEVZAST) COLLATE utf8_czech_ci', 'ASC')
            ->groupBy('KODZASTUP')
            ->fetchAllAssociative();

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
            ->from('polar_electionszo2026_kvrk', 'p')
            ->leftJoin('p', 'polar_electionszo2026_results', 'r', 'r.KSTRANA = p.KSTRANA')
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
                //$select->join('polar_electionszo2026_results_kandid', ...)  // JOIN zakomentován
                ->from('polar_electionszo2026_kvrk')
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
            ->from('polar_electionszo2026_kvrk', 'p')
            ->leftJoin('p', 'polar_electionszo2026_results', 'r', 'r.KSTRANA = p.KSTRANA')
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
    public function findKvrosPost(int $OSTRANA, int $VSTRANA, int $KODZASTUP): ?array
    {
        $result = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('polar_electionszo2026_kvros')
            ->where('OSTRANA = :OSTRANA')
            ->andWhere('VSTRANA = :VSTRANA')
            ->andWhere('KODZASTUP = :KODZASTUP')
            ->setParameter('OSTRANA', $OSTRANA)
            ->setParameter('VSTRANA', $VSTRANA)
            ->setParameter('KODZASTUP', $KODZASTUP)
            ->fetchAssociative();

        return $result ?: null;
    }

    /**
     * @return array
     */
    public function fetchKvrosForBootstrapSelect(): array
    {
        //Ostrava 554821
        //Havířov 555088
        //Karviná 598917
        //Opava 505927
        //Frýdek-Místek 598003
        //Třinec 598810
        //Orlová 599069
        $resources = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('polar_electionszo2026_kvros')
            ->where('KODZASTUP IN (554821, 555088, 598917, 505927, 598003, 598810, 599069)')
            ->orderBy('LOWER(NAZEVZAST) COLLATE utf8_czech_ci', 'ASC')
            ->addOrderBy('LOWER(NAZEVCELK) COLLATE utf8_czech_ci', 'ASC')
            ->fetchAllAssociative();

        $data = [['value' => null, 'label' => null]];
        foreach ($resources as $resource) {
            $data[] = [
                'value' => $resource['OSTRANA'] . '-' . $resource['VSTRANA'] . '-' . $resource['KODZASTUP'],
                'label' => $resource['NAZEVZAST'] . ' - ' . $resource['NAZEVCELK'],
            ];
        }
        return $data;
    }
}
