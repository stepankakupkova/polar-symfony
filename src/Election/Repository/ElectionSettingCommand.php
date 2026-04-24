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

class ElectionSettingCommand
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
     * @param array $post
     * @return array
     * @throws RuntimeException
     */
    public function updateSetting(array $post): array
    {
        foreach ($post as $label => $value) {
            $affected = $this->connection->update(
                $this->table,
                [
                    'flag'     => 'setting',
                    'variable' => $label,
                    'value'    => $value,
                ],
                [
                    'flag'     => 'setting',
                    'variable' => $label,
                ]
            );

            if ($affected === false) {
                throw new RuntimeException(
                    'Během operace "Update" došlo k chybě databáze. Tabulka "' . $this->table . '".'
                );
            }
        }
        return $post;
    }
}
