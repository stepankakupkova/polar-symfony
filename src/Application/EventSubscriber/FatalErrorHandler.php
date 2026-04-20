<?php

declare(strict_types=1);

namespace App\Application\EventSubscriber;

use App\Application\Service\Logger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class FatalErrorHandler implements EventSubscriberInterface
{
	private bool $registered = false;

	public function __construct(
		private Logger $logger,
		private string $templatesDir,
	) {}

	public static function getSubscribedEvents(): array
	{
		return [
			KernelEvents::REQUEST => ['onRequest', 255],
		];
	}

	public function onRequest(RequestEvent $event): void
	{
		if ($this->registered || !$event->isMainRequest()) {
			return;
		}

		$this->registered = true;
		$logger = $this->logger;
		$templatesDir = $this->templatesDir;

		register_shutdown_function(static function () use ($logger, $templatesDir) {
			$error = error_get_last();
			if (null === $error || $error['type'] !== E_ERROR) {
				return;
			}

			while (ob_get_level() > 0) {
				ob_end_clean();
			}

			$chars = md5(uniqid('', true));
			$errorReference = substr($chars, 2, 2) . substr($chars, 12, 2) . substr($chars, 26, 2);

			try {
				$logger->crit($error['message'], [
					'reference' => $errorReference,
					'file' => $error['file'],
					'line' => (string) $error['line'],
				]);
			} catch (\Throwable) {
				// DB může být nedostupná při fatální chybě
			}

			$fatalTemplatePath = $templatesDir . '/application/error/fatal.phtml';

			if (is_file($fatalTemplatePath)) {
				$body = file_get_contents($fatalTemplatePath);
				$body = str_replace(
					[
						'%__ERROR_REFERENCE__%',
						'%__ERROR_MESSAGE__%',
						'%__ERROR_FILE__%',
						'%__ERROR_LINE__%',
					],
					[
						'Reference: ' . $errorReference,
						'Message: ' . htmlspecialchars($error['message']),
						'File: ' . htmlspecialchars($error['file']),
						'Line: ' . $error['line'],
					],
					$body
				);
				echo $body;
			} else {
				echo '<h1>Fatal error</h1><p>Reference: ' . htmlspecialchars($errorReference) . '</p>';
			}

			exit(1);
		});
	}
}
