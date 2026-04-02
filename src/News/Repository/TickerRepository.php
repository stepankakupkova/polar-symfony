<?php

namespace App\News\Repository;

use Doctrine\DBAL\Connection;

final class TickerRepository
{
	public function __construct(private Connection $connection) {}

	public function getItems(): array
	{
		return $this->connection->createQueryBuilder()
			->select('content')
			->from('polar_ticker_content')
			->orderBy('order', 'ASC')
			->executeQuery()
			->fetchFirstColumn();
	}
}
