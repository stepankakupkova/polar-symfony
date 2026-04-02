<?php

namespace App\Camera\Repository;

use Doctrine\DBAL\Connection;

final class CameraRepository
{
	public function __construct(private Connection $connection) {}

	public function fetchAllLimit(int $limit): ?array
	{
		$result = $this->connection->createQueryBuilder()
			->select('*')
			->from('cameras')
			->orderBy('rank', 'ASC')
			->setMaxResults($limit)
			->fetchAllAssociative();

		return $result ?: null;
	}
}
