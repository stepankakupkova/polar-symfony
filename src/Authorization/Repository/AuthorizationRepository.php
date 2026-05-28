<?php

namespace App\Authorization\Repository;

use Doctrine\DBAL\Connection;

final class AuthorizationRepository
{
	public function __construct(
		private Connection $connection,
	) {}

	public function findPostBy(string $column, int|string $value): ?array
	{
		return $this->connection->createQueryBuilder()
			->select('*')
			->from('authorization')
			->where("$column = :value")
			->setParameter('value', $value)
			->fetchAssociative() ?: null;
	}

	public function insertPost(array $data): int
	{
		$this->connection->insert('authorization', [
			'username' => $data['username'],
			'password' => $data['password'],
			'active' => $data['active'] ? 1 : 0,
		]);

		$authorizationId = (int) $this->connection->lastInsertId();

		// Vytvořit záznam v user tabulce
		$this->connection->insert('user', [
			'authorization_id' => $authorizationId,
			'created_user'     => $data['created_user'] ?? '',
			'updated_user'     => $data['created_user'] ?? '',
		]);

		// Přiřadit roli
		if (!empty($data['role'])) {
			$roleId = $this->connection->createQueryBuilder()
				->select('id')
				->from('authorization_role')
				->where('role = :role')
				->setParameter('role', $data['role'])
				->fetchOne();

			if ($roleId) {
				$this->connection->insert('authorization2role', [
					'authorization_id' => $authorizationId,
					'role_id' => $roleId,
				]);
			}
		}

		return $authorizationId;
	}

	public function updatePost(int $id, array $data): void
	{
		$update = [];
		if (isset($data['username'])) {
			$update['username'] = $data['username'];
		}
		if (isset($data['password']) && $data['password'] !== '') {
			$update['password'] = $data['password'];
		}
		if (isset($data['active'])) {
			$update['active'] = $data['active'] ? 1 : 0;
		}

		if (!empty($update)) {
			$this->connection->update('authorization', $update, ['id' => $id]);
		}

		// Aktualizovat roli
		if (isset($data['role'])) {
			$this->connection->delete('authorization2role', ['authorization_id' => $id]);

			$roleId = $this->connection->createQueryBuilder()
				->select('id')
				->from('authorization_role')
				->where('role = :role')
				->setParameter('role', $data['role'])
				->fetchOne();

			if ($roleId) {
				$this->connection->insert('authorization2role', [
					'authorization_id' => $id,
					'role_id' => $roleId,
				]);
			}
		}
	}

	public function deletePost(int $id): void
	{
		// Kaskáda: authorization2role → user → authorization
		$this->connection->delete('authorization2role', ['authorization_id' => $id]);
		$this->connection->delete('user', ['authorization_id' => $id]);
		$this->connection->delete('authorization', ['id' => $id]);
	}

	public function setHash(int $id, string $hash): void
	{
		$this->connection->update('authorization', ['hash' => $hash], ['id' => $id]);
	}

	public function clearHash(int $id): void
	{
		$this->connection->update('authorization', ['hash' => null], ['id' => $id]);
	}

	public function updatePassword(int $id, string $passwordHash): void
	{
		$this->connection->update('authorization', ['password' => $passwordHash], ['id' => $id]);
	}
}
