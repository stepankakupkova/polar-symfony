<?php

declare(strict_types=1);

namespace App\Application\EventSubscriber;

use App\Application\Service\Logger;
use App\Application\View\PhtmlRenderer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class ErrorSubscriber implements EventSubscriberInterface
{
	public function __construct(
		private Logger $logger,
		private PhtmlRenderer $renderer,
		private string $environment,
	) {}

	public static function getSubscribedEvents(): array
	{
		return [
			KernelEvents::EXCEPTION => ['onKernelException', -128],
		];
	}

	public function onKernelException(ExceptionEvent $event): void
	{
		$exception = $event->getThrowable();
		$statusCode = $exception instanceof HttpExceptionInterface
			? $exception->getStatusCode()
			: 500;

		// Generování error reference
		$chars = md5(uniqid('', true));
		$errorReference = substr($chars, 2, 2) . substr($chars, 12, 2) . substr($chars, 26, 2);

		// Logování do DB (jako polar initErrorLogger)
		$extra = [
			'reference' => $errorReference,
			'file' => $exception->getFile(),
			'line' => (string) $exception->getLine(),
			'trace' => $exception->getTraceAsString(),
		];

		if (isset($exception->xdebug_message)) {
			$extra['xdebug'] = $exception->xdebug_message;
		}

		$this->logger->err($exception->getMessage(), $extra);

		// Renderování šablony
		$displayExceptions = ($this->environment === 'dev');

		$template = $statusCode === 404 ? 'error/404' : 'error/500';

		try {
			$html = $this->renderer->renderWithLayout($template, [
				'errorReference' => $errorReference,
				'displayExceptions' => $displayExceptions,
				'exception' => $exception,
				'statusCode' => $statusCode,
			]);
		} catch (\Throwable $renderException) {
			// Pokud selže i renderování, fallback na prostý text
			$html = '<h1>Error ' . $statusCode . '</h1>'
				. '<p>Reference: ' . htmlspecialchars($errorReference) . '</p>';

			// Zalogovat i chybu renderování
			$this->logger->err('RENDER ERROR: ' . $renderException->getMessage(), [
				'reference' => $errorReference,
				'file' => $renderException->getFile(),
				'line' => (string) $renderException->getLine(),
				'trace' => $renderException->getTraceAsString(),
			]);
		}

		$response = new Response($html, $statusCode);
		$event->setResponse($response);
	}
}
