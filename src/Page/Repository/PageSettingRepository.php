<?php

namespace App\Page\Repository;

use Doctrine\DBAL\Connection;

final class PageSettingRepository
{
	public function __construct(
		private Connection $connection,
	) {}

	/**
	 * @return array
	 */
	public function fetchSetting(): array
	{
		$rows = $this->connection->createQueryBuilder()
			->select('*')
			->from('page_setting')
			->where('flag = :flag')
			->setParameter('flag', 'setting')
			->fetchAllAssociative();

		$setting = [];
		foreach ($rows as $row) {
			$setting[$row['variable']] = $row['value'];
		}

		return $setting;
	}

	/**
	 * @param string $variable
	 * @param string $value
	 * @return void
	 */
	public function updateSetting(string $variable, string $value): void
	{
		$this->connection->update('page_setting', [
			'flag' => 'setting',
			'variable' => $variable,
			'value' => $value,
		], [
			'flag' => 'setting',
			'variable' => $variable,
		]);
	}
}
