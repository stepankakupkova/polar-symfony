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
use RuntimeException;

class ElectionSettingRepository
{
    /**
     * @var string
     */
    private string $table;

    /**
     * @param Connection $connection
     */
    public function __construct(
        private Connection $connection,
    ) {
        $this->table = 'election_setting';
    }

    /**
     * @return array
     * @throws RuntimeException
     */
    public function fetchSetting(): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->table)
            ->where('flag = :flag')
            ->setParameter('flag', 'setting')
            ->fetchAllAssociative();

        $setting = [];
        foreach ($rows as $row) {
            $setting[$row['variable']] = $row['value'];
        }
        return $setting;
    }
}
