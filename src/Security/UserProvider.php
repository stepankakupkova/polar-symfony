<?php

namespace App\Security;

use Doctrine\DBAL\Connection;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Načítá uživatele z tabulek authorization + user + authorization2role + authorization_role.
 * Ekvivalent polar AuthorizationAdapter::authenticate() — část načítání dat.
 */
class UserProvider implements UserProviderInterface, PasswordUpgraderInterface
{
	public function __construct(
		private Connection $connection,
	) {}

	public function loadUserByIdentifier(string $identifier): UserInterface
	{
		// Najdi authorization záznam podle username (= email)
		$authorization = $this->connection->createQueryBuilder()
			->select('*')
			->from('authorization')
			->where('username = :username')
			->setParameter('username', $identifier)
			->fetchAssociative();

		if (!$authorization) {
			throw new UserNotFoundException(sprintf('User "%s" not found.', $identifier));
		}

		return $this->buildUser($authorization);
	}

	public function refreshUser(UserInterface $user): UserInterface
	{
		if (!$user instanceof User) {
			throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
		}

		return $this->loadUserByIdentifier($user->getUserIdentifier());
	}

	public function supportsClass(string $class): bool
	{
		return $class === User::class;
	}

	public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
	{
		if (!$user instanceof User) {
			throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
		}

		$this->connection->createQueryBuilder()
			->update('authorization')
			->set('password', ':password')
			->where('username = :username')
			->setParameter('password', $newHashedPassword)
			->setParameter('username', $user->getUserIdentifier())
			->executeStatement();
	}

	private function buildUser(array $authorization): User
	{
		$authorizationId = (int) $authorization['id'];

		// Načti role
		$roles = $this->connection->createQueryBuilder()
			->select('ar.role')
			->from('authorization2role', 'a2r')
			->leftJoin('a2r', 'authorization_role', 'ar', 'ar.id = a2r.role_id')
			->where('a2r.authorization_id = :authorizationId')
			->setParameter('authorizationId', $authorizationId)
			->fetchFirstColumn();

		// Načti user data (first_name, last_name, image)
		$userData = $this->connection->createQueryBuilder()
			->select('first_name', 'last_name', 'image')
			->from('user')
			->where('authorization_id = :authorizationId')
			->setParameter('authorizationId', $authorizationId)
			->fetchAssociative();

		$user = new User();
		$user->setId($authorizationId);
		$user->setUsername($authorization['username']);
		$user->setPassword($authorization['password']);
		$user->setActive((bool) $authorization['active']);
		$user->setRole($roles ?: []);

		if ($userData) {
			$user->setFirstName($userData['first_name'] ?? null);
			$user->setLastName($userData['last_name'] ?? null);
			$user->setImage($userData['image'] ?? null);
		}

		return $user;
	}
}
