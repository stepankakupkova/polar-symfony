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

class Election2020PlaykitRepository
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
    public function fetchAllKreslaForWeb(): ?array
    {
        $result = $this->connection->createQueryBuilder()
            ->select('p.KSTRANA', 'p.NAZEVCELK', 'p.NAZEV_STRK', 'p.ZKRATKAK8',
                     'r.HLASY', 'r.PROC_HLASU', 'r.MANDATY')
            ->from('polar_electionskz2020_kzrkl', 'p')
            ->leftJoin('p', 'polar_electionskz2020_results', 'r', 'r.KSTRANA = p.KSTRANA')
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
            ->from('polar_electionskz2020_results')
            ->setMaxResults(1)
            ->fetchAssociative();

        return $result ?: null;
    }
}
