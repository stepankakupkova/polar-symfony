<?php

namespace App\News\Repository;

use Doctrine\DBAL\Connection;

final class ShowRepository
{
	public function __construct(private Connection $connection) {}

	/**
	 * Všechny pořady pro hosty (status=1, show_in_archive=1)
	 */
	public function fetchAllForGuests(): ?array
	{
		$result = $this->connection->createQueryBuilder()
			->select('*')
			->from('program_shows')
			->where('status = 1')
			->andWhere('show_in_archive = 1')
			->orderBy('`order`', 'ASC')
			->fetchAllAssociative();

		return $result ?: null;
	}

	/**
	 * Najde pořad podle ID
	 */
	public function findById(int $id): ?array
	{
		$result = $this->connection->createQueryBuilder()
			->select('*')
			->from('program_shows')
			->where('id = :id')
			->setParameter('id', $id)
			->fetchAssociative();

		return $result ?: null;
	}
}
