<?php

declare(strict_types=1);

namespace App\Application\Repository;

use Doctrine\DBAL\Connection;

class SettingRepository
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function fetchFooterNumbers(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT variable, value
               FROM page_setting
              WHERE flag = 'setting'
                AND variable IN ('footer_number_1', 'footer_number_2', 'footer_number_3', 'footer_number_4')",
        );

        $result = [
            'footer_number_1' => null,
            'footer_number_2' => null,
            'footer_number_3' => null,
            'footer_number_4' => null,
        ];
        foreach ($rows as $row) {
            $result[$row['variable']] = $row['value'];
        }

        return $result;
    }
}
