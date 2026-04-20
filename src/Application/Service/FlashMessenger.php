<?php

namespace App\Application\Service;

use Symfony\Component\HttpFoundation\RequestStack;

final class FlashMessenger
{
	public function __construct(
		private RequestStack $requestStack,
	) {}

	/**
	 * @param string $type 'success' | 'error'
	 * @param string $title
	 * @param string $text
	 */
	public function addMessage(string $type, string $title, string $text): void
	{
		$this->requestStack->getSession()->getFlashBag()->add($type, [
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
		$flashBag = $this->requestStack->getSession()->getFlashBag();
		foreach (['success', 'error'] as $type) {
			$flashes = $flashBag->get($type);
			if (!empty($flashes)) {
				$messages[$type] = $flashes;
			}
		}
		return $messages;
	}
}
