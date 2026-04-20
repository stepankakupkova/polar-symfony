<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\Repository\LogRepository;
use Symfony\Component\HttpFoundation\RequestStack;

class Logger
{
	private const EMERG = 0;
	private const ALERT = 1;
	private const CRIT = 2;
	private const ERR = 3;
	private const WARN = 4;
	private const NOTICE = 5;
	private const INFO = 6;
	private const DEBUG = 7;

	private const PRIORITY_NAMES = [
		self::EMERG => 'EMERG',
		self::ALERT => 'ALERT',
		self::CRIT => 'CRIT',
		self::ERR => 'ERR',
		self::WARN => 'WARN',
		self::NOTICE => 'NOTICE',
		self::INFO => 'INFO',
		self::DEBUG => 'DEBUG',
	];

	public function __construct(
		private LogRepository $logRepository,
		private RequestStack $requestStack,
	) {}

	/**
	 * @param string $message
	 * @param array $extra
	 */
	public function emerg(string $message, array $extra = []): void
	{
		$this->log(self::EMERG, $message, $extra);
	}

	/**
	 * @param string $message
	 * @param array $extra
	 */
	public function alert(string $message, array $extra = []): void
	{
		$this->log(self::ALERT, $message, $extra);
	}

	/**
	 * @param string $message
	 * @param array $extra
	 */
	public function crit(string $message, array $extra = []): void
	{
		$this->log(self::CRIT, $message, $extra);
	}

	/**
	 * @param string $message
	 * @param array $extra
	 */
	public function err(string $message, array $extra = []): void
	{
		$this->log(self::ERR, $message, $extra);
	}

	/**
	 * @param string $message
	 * @param array $extra
	 */
	public function warn(string $message, array $extra = []): void
	{
		$this->log(self::WARN, $message, $extra);
	}

	/**
	 * @param string $message
	 * @param array $extra
	 */
	public function notice(string $message, array $extra = []): void
	{
		$this->log(self::NOTICE, $message, $extra);
	}

	/**
	 * @param string $message
	 * @param array $extra
	 */
	public function info(string $message, array $extra = []): void
	{
		$this->log(self::INFO, $message, $extra);
	}

	/**
	 * @param string $message
	 * @param array $extra
	 */
	public function debug(string $message, array $extra = []): void
	{
		$this->log(self::DEBUG, $message, $extra);
	}

	/**
	 * @param int $priority
	 * @param string $message
	 * @param array $extra
	 */
	private function log(int $priority, string $message, array $extra): void
	{
		$request = $this->requestStack->getCurrentRequest();

		$reference = $extra['reference'] ?? $this->getErrorReference();

		$this->logRepository->insert([
			'datetime' => (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Prague')))->format('Y-m-d H:i:s'),
			'priority' => self::PRIORITY_NAMES[$priority],
			'message' => $message,
			'description' => $extra['description'] ?? null,
			'user' => $extra['user'] ?? null,
			'reference' => $reference,
			'file' => $extra['file'] ?? null,
			'line' => $extra['line'] ?? null,
			'trace' => $extra['trace'] ?? null,
			'xdebug' => $extra['xdebug'] ?? null,
			'uri' => $request?->getRequestUri(),
			'ip' => $request?->getClientIp(),
			'session_id' => $request?->hasSession() ? $request->getSession()->getId() : null,
		]);
	}

	/**
	 * @return string
	 */
	private function getErrorReference(): string
	{
		$chars = md5(uniqid('', true));
		return substr($chars, 2, 2) . substr($chars, 12, 2) . substr($chars, 26, 2);
	}
}
