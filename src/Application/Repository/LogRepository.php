<?php

declare(strict_types=1);

namespace App\Application\Repository;

use Doctrine\DBAL\Connection;

class LogRepository
{
	public function __construct(private Connection $connection) {}

	/**
	 * @param array $data
	 */
	public function insert(array $data): void
	{
		$this->connection->insert('application_log', $data);
	}
}
