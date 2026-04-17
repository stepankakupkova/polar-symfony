<?php

namespace App\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Kontroluje, zda je uživatel aktivní (polar: $authorization->isActive()).
 */
class UserChecker implements UserCheckerInterface
{
	public function checkPreAuth(UserInterface $user): void
	{
		if (!$user instanceof User) {
			return;
		}

		if (!$user->isActive()) {
			throw new CustomUserMessageAccountStatusException('User is not active');
		}
	}

	public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
	{
	}
}
