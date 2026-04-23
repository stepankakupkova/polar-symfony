<?php

namespace App\Banner\Repository;

use Doctrine\DBAL\Connection;

final class BannerRepository
{
	public function __construct(private Connection $connection) {}

	private function fetchBanner(string $table): ?array
	{
		return $this->connection->createQueryBuilder()
			->select('*')
			->from($table)
			->where('active = 1')
			->andWhere('public_from <= NOW()')
			->andWhere('public_to >= NOW()')
			->orderBy('RAND()')
			->setMaxResults(1)
			->executeQuery()
			->fetchAssociative() ?: null;
	}

	public function getLeaderboard(): ?array
	{
		return $this->fetchBanner('banner_leaderboard');
	}

	public function getMobilesticky(): ?array
	{
		return $this->fetchBanner('banner_mobilesticky');
	}

	public function getRectangle(): ?array
	{
		return $this->fetchBanner('banner_rectangle');
	}

	public function getSquare(): ?array
	{
		return $this->fetchBanner('banner_square');
	}

	public function getMobilesquare1(): ?array
	{
		return $this->fetchBanner('banner_mobilesquare1');
	}

	public function getMobilesquare2(): ?array
	{
		return $this->fetchBanner('banner_mobilesquare2');
	}

	public function setShowed(string $type, int $id): bool
	{
		$allowed = ['leaderboard', 'rectangle', 'square', 'mobilesticky', 'mobilesquare1', 'mobilesquare2'];
		if (!in_array($type, $allowed, true)) {
			return false;
		}

		$affected = $this->connection->executeStatement(
			'UPDATE banner_' . $type . ' SET showed = showed + 1 WHERE id = ?',
			[$id],
		);

		return $affected > 0;
	}

	public function setClicked(string $type, int $id): bool
	{
		$allowed = ['leaderboard', 'rectangle', 'square', 'mobilesticky', 'mobilesquare1', 'mobilesquare2'];
		if (!in_array($type, $allowed, true)) {
			return false;
		}

		$affected = $this->connection->executeStatement(
			'UPDATE banner_' . $type . ' SET clicked = clicked + 1 WHERE id = ?',
			[$id],
		);

		return $affected > 0;
	}
}
