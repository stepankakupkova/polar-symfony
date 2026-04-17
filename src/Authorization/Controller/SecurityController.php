<?php

namespace App\Authorization\Controller;

use App\Application\View\PhtmlRenderer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityController
{
	public function login(
		AuthenticationUtils $authUtils,
		CsrfTokenManagerInterface $csrfTokenManager,
		PhtmlRenderer $renderer,
	): Response
	{
		// Symfony form_login automaticky zpracuje POST.
		// Tady jen zobrazíme formulář + případnou chybu.
		$error = $authUtils->getLastAuthenticationError();
		$lastUsername = $authUtils->getLastUsername();

		$message = null;
		if ($error) {
			$message = $error->getMessageKey();
			// Překlad chybových hlášek do češtiny (jako polar)
			if (str_contains($message, 'Invalid credentials') || str_contains($message, 'Invalid password')) {
				$message = 'Neplatné přihlašovací údaje.';
			} elseif (str_contains($message, 'not found')) {
				$message = 'Přihlašovací údaje nenalezeny.';
			} elseif (str_contains($message, 'not active')) {
				$message = 'Uživatel není aktivní.';
			}
		}

		return new Response($renderer->renderWithLayout('authorization/sign-in', [
			'message' => $message,
			'lastUsername' => $lastUsername,
			'csrfToken' => $csrfTokenManager->getToken('authenticate')->getValue(),
		]));
	}

	public function logout(): void
	{
		// Symfony firewall logout tuto metodu nikdy nezavolá.
		// Je tu jen kvůli definici route.
		throw new \LogicException('This should never be reached.');
	}

	public function signOut(PhtmlRenderer $renderer): Response
	{
		return new Response($renderer->renderWithLayout('authorization/sign-out', [
			'message' => 'Byli jste úspěšně odhlášeni.',
		]));
	}
}
