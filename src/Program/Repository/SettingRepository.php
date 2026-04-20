<?php

namespace App\Program\Repository;

use Doctrine\DBAL\Connection;
use RuntimeException;

final class SettingRepository
{
	private string $table = 'program_setting';

	public function __construct(private Connection $connection) {}

	/**
	 * @return array{video_update_date: ?string, videoex_update_date: ?string, newton_update_date: ?string, show_img_width: ?string, show_img_height: ?string}
	 */
	public function fetchSetting(): array
	{
		$rows = $this->connection->createQueryBuilder()
			->select('variable', 'value')
			->from($this->table)
			->where('flag = :flag')
			->setParameter('flag', 'setting')
			->executeQuery()
			->fetchAllAssociative();

		$setting = [
			'video_update_date' => null,
			'videoex_update_date' => null,
			'newton_update_date' => null,
			'show_img_width' => null,
			'show_img_height' => null,
		];

		foreach ($rows as $row) {
			if (array_key_exists($row['variable'], $setting)) {
				$setting[$row['variable']] = $row['value'];
			}
		}

		return $setting;
	}

	/**
	 * @param array<string, string|null> $data
	 */
	public function updateSetting(array $data): void
	{
		foreach ($data as $label => $value) {
			$this->connection->createQueryBuilder()
				->update($this->table)
				->set('flag', ':flag')
				->set('variable', ':variable')
				->set('value', ':value')
				->where('flag = :flag')
				->andWhere('variable = :variable')
				->setParameter('flag', 'setting')
				->setParameter('variable', $label)
				->setParameter('value', $value)
				->executeStatement();
		}
	}
}
