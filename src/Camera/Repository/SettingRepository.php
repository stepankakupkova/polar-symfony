<?php
/*
 * @project polar
 * @author Rostislav Greipel
 * @link https://rogr.cz
 * @copyright (c) 2011 - 2021 ROGR All Rights Reserved
 * @license https://rogr.cz/license/eula EULA License
 */

declare(strict_types=1);

namespace App\Camera\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

final class SettingRepository
{
    private string $table = 'camera_setting';

    public function __construct(private Connection $connection) {}

    /**
     * @throws Exception
     * @throws \InvalidArgumentException
     */
    public function fetchSetting(): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->table)
            ->where('flag = :flag')
            ->setParameter('flag', 'setting')
            ->executeQuery()
            ->fetchAllAssociative();

        if (!$rows) {
            throw new \InvalidArgumentException('Nastavení kamer nenalezeno.');
        }

        $setting = [];
        foreach ($rows as $row) {
            $setting[$row['variable']] = $row['value'];
        }
        return $setting;
    }
}
