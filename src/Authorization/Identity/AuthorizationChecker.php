<?php

namespace App\Authorization\Identity;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Kontroluje, zda je uživatel aktivní (polar: $authorization->isActive()).
 */
class AuthorizationChecker implements UserCheckerInterface
{
	public function checkPreAuth(UserInterface $user): void
	{
		if (!$user instanceof AuthorizationUser) {
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
