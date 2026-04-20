<?php

namespace App\Application\Service;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;

final class FlashMessenger
{
	public function __construct(
		private RequestStack $requestStack,
	) {}

	private function getFlashBag(): \Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface
	{
		/** @var Session $session */
		$session = $this->requestStack->getSession();
		return $session->getFlashBag();
	}

	/**
	 * @param string $type 'success' | 'error'
	 * @param string $title
	 * @param string $text
	 */
	public function addMessage(string $type, string $title, string $text): void
	{
		$this->getFlashBag()->add($type, [
			'title' => $title,
			'text' => $text,
		]);
	}

	/**
	 * Vrátí a smaže všechny flash messages
	 * @return array<string, list<array{title: string, text: string}>>
	 */
	public function getMessages(): array
	{
		$messages = [];
		$flashBag = $this->getFlashBag();
		foreach (['success', 'error'] as $type) {
			$flashes = $flashBag->get($type);
			if (!empty($flashes)) {
				$messages[$type] = $flashes;
			}
		}
		return $messages;
	}
}
