<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Application\Repository\LogRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class LogWriteController
{
	public function __construct(
		private LogRepository $logRepository,
	) {}

	public function deleteLog(Request $request): JsonResponse
	{
		$success = true;
		$message = null;
		$log_id = null;

		try {
			$log_id = (int) $request->request->get('log_id');
			$log = $this->logRepository->findById($log_id);

			if ($log !== null) {
				$this->logRepository->deleteById($log_id);
			}
		} catch (\Exception $e) {
			$success = false;
			$message = $e->getMessage();
		}

		return new JsonResponse([
			'success' => $success,
			'message' => $message,
			'log_id' => $log_id,
		]);
	}

	public function deleteLogs(Request $request): JsonResponse
	{
		$success = true;
		$message = null;
		$type = null;

		try {
			$type = $request->request->all('type') ?: null;
			$this->logRepository->deleteByPriority($type);
		} catch (\Exception $e) {
			$success = false;
			$message = $e->getMessage();
		}

		return new JsonResponse([
			'success' => $success,
			'message' => $message,
			'type' => $type,
		]);
	}
}
