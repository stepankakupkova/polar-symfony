<?php

namespace App\Security;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Nahrazuje polar Identity objekt.
 * Kombinuje data z tabulek authorization + user.
 */
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
	private ?int $id = null;
	private ?string $username = null;
	private ?string $password = null;
	private bool $active = false;
	private array $role = [];
	private ?string $first_name = null;
	private ?string $last_name = null;
	private ?string $image = null;

	public function getId(): ?int
	{
		return $this->id;
	}

	public function setId(int $id): void
	{
		$this->id = $id;
	}

	public function getUserIdentifier(): string
	{
		return $this->username ?? '';
	}

	public function getUsername(): ?string
	{
		return $this->username;
	}

	public function setUsername(string $username): void
	{
		$this->username = $username;
	}

	public function getPassword(): ?string
	{
		return $this->password;
	}

	public function setPassword(string $password): void
	{
		$this->password = $password;
	}

	public function isActive(): bool
	{
		return $this->active;
	}

	public function setActive(bool $active): void
	{
		$this->active = $active;
	}

	/**
	 * Vrací role ve formátu Symfony (ROLE_OWNER, ROLE_ADMIN, ROLE_MEMBER)
	 */
	public function getRoles(): array
	{
		$roles = [];
		foreach ($this->role as $r) {
			$roles[] = 'ROLE_' . strtoupper($r);
		}
		// Symfony vyžaduje alespoň jednu roli
		if ($roles === []) {
			$roles[] = 'ROLE_USER';
		}
		return array_unique($roles);
	}

	/**
	 * Vrací původní role (owner, admin, member) — pro kompatibilitu s polarem
	 */
	public function getRole(): array
	{
		return $this->role;
	}

	public function setRole(array $role): void
	{
		$this->role = $role;
	}

	public function getFirstName(): ?string
	{
		return $this->first_name;
	}

	public function setFirstName(?string $first_name): void
	{
		$this->first_name = $first_name;
	}

	public function getLastName(): ?string
	{
		return $this->last_name;
	}

	public function setLastName(?string $last_name): void
	{
		$this->last_name = $last_name;
	}

	public function getImage(): ?string
	{
		return $this->image;
	}

	public function setImage(?string $image): void
	{
		$this->image = $image;
	}

	public function eraseCredentials(): void
	{
		// Nesmazáváme password, Symfony ho potřebuje pro ověření
	}
}
