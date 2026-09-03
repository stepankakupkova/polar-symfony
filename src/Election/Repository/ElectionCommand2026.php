<?php

declare(strict_types=1);

namespace App\Election\Repository;

use Doctrine\DBAL\Connection;
use RuntimeException;

class ElectionCommand2026
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
        $this->table = 'elections_2026';
    }

    /**
    * @param array $post ['title', 'description', 'video_id', 'OSTRANA', 'VSTRANA', 'KODZASTUP', 'rank']
     * @return array
     * @throws RuntimeException
     */
    public function insertPost(array $post): array
    {
        $data = [
            'title'       => $post['title'],
            'description' => $post['description'],
            'video_id'    => $post['video_id'] ?: null,
            'OSTRANA'     => $post['OSTRANA'],
            'VSTRANA'     => $post['VSTRANA'],
            'KODZASTUP'   => $post['KODZASTUP'],
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
            'OSTRANA'     => $post['OSTRANA'],
            'VSTRANA'     => $post['VSTRANA'],
            'KODZASTUP'   => $post['KODZASTUP'],
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
