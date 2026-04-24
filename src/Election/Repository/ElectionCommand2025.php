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

class ElectionCommand2025
{
    /**
     * @var string $table
     */
    private string $table;

    /**
     * @param Connection $connection
     */
    public function __construct(
        private Connection $connection,
    ) {
        $this->table = 'elections_2025';
    }

    /**
     * @param array $post ['title', 'description', 'video_id', 'rank']
     * @return array
     * @throws RuntimeException
     */
    public function insertPost(array $post): array
    {
        $data = [
            'title'       => $post['title'],
            'description' => $post['description'],
            'video_id'    => $post['video_id'] ?: null,
            'rank'        => $post['rank'] ?? 0,
        ];

        $this->connection->insert($this->table, $data);

        $id = (int)$this->connection->lastInsertId();

        if (!$id) {
            throw new RuntimeException(
                'Během operace "Insert" došlo k chybě databáze. Tabulka "' . $this->table . '".'
            );
        }

        $data['id'] = $id;

        return $data;
    }

    /**
     * @param array $post musí obsahovat 'id'
     * @return array
     * @throws RuntimeException
     */
    public function updatePost(array $post): array
    {
        if (empty($post['id'])) {
            throw new RuntimeException('Záznam nelze upravit. Chybí identifikátor. Tabulka "' . $this->table . '".');
        }

        $data = [
            'title'       => $post['title'],
            'description' => $post['description'],
            'video_id'    => $post['video_id'] ?: null,
            'rank'        => $post['rank'] ?? 0,
        ];

        $this->connection->update($this->table, $data, ['id' => (int)$post['id']]);

        return $post;
    }

    /**
     * @param array $post musí obsahovat 'id'
     * @return bool
     * @throws RuntimeException
     */
    public function deletePost(array $post): bool
    {
        if (empty($post['id'])) {
            throw new RuntimeException('Záznam nelze smazat. Chybí identifikátor. Tabulka "' . $this->table . '".');
        }

        $this->connection->delete($this->table, ['id' => (int)$post['id']]);

        return true;
    }
}
