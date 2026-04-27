<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Application\Repository\LogRepository;
use App\Application\View\PhtmlRenderer;
use DateTime;
use IntlDateFormatter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class LogListController
{
	public function __construct(
		private LogRepository $logRepository,
	) {}

	public function list(PhtmlRenderer $renderer): Response
	{
		return new Response($renderer->renderWithAdminLayout('admin/setting/log', [
			'pageTitle' => 'Logy',
		]));
	}

	public function getList(Request $request): JsonResponse
	{
		$params = $request->query->all();

		$rows = null;
		$total = 0;
		$success = true;

		try {
			$rows = $this->logRepository->fetchForBootstrapTable($params);
			$total = $this->logRepository->getCountForBootstrapTable($params);
		} catch (\Exception $e) {
			$success = $e->getMessage();
		}

		return new JsonResponse([
			'success' => $success,
			'rows' => $rows,
			'total' => $total,
		]);
	}

	public function getLogForDashboard(Request $request): JsonResponse
	{
		$datetimeStr = $request->request->get('datetime');
		$datetime = new DateTime($datetimeStr);

		$data = null;
		$success = true;

		try {
			$rows = $this->logRepository->fetchForDashboard($datetime);

			if ($rows) {
				$fmt = new IntlDateFormatter(
					'cs_CZ',
					IntlDateFormatter::NONE,
					IntlDateFormatter::MEDIUM,
				);

				$dataTmp = '';
				foreach ($rows as $item) {
					$dataTmp .= '<tr>';

					$dataTmp .= '<td class="width-nowrap">';
					switch ($item['priority']) {
						case 'EMERG':
							$dataTmp .= '<i class="fa fa-fw fa-exclamation-circle text-danger" data-toggle="tooltip" data-placement="top" title="Emergency"></i>';
							break;
						case 'ALERT':
							$dataTmp .= '<i class="fa fa-fw fa-exclamation-triangle text-danger" data-toggle="tooltip" data-placement="top" title="Alert"></i>';
							break;
						case 'CRIT':
							$dataTmp .= '<i class="fa fa-fw fa-exclamation text-danger" data-toggle="tooltip" data-placement="top" title="Critical"></i>';
							break;
						case 'ERR':
							$dataTmp .= '<i class="fa fa-fw fa-times-circle text-danger" data-toggle="tooltip" data-placement="top" title="Error"></i>';
							break;
						case 'WARN':
							$dataTmp .= '<i class="fa fa-fw fa-exclamation-triangle text-warning" data-toggle="tooltip" data-placement="top" title="Warning"></i>';
							break;
						case 'NOTICE':
							$dataTmp .= '<i class="fa fa-fw fa-info-circle text-info" data-toggle="tooltip" data-placement="top" title="Notice"></i>';
							break;
						case 'INFO':
							$dataTmp .= '<i class="fa fa-fw fa-info text-info" data-toggle="tooltip" data-placement="top" title="Informational"></i>';
							break;
						case 'DEBUG':
							$dataTmp .= '<i class="fa fa-fw fa-question-circle text-success" data-toggle="tooltip" data-placement="top" title="Debug"></i>';
							break;
					}
					$dataTmp .= '</td>';

					$dataTmp .= '<td class="width-nowrap text-muted">' . $fmt->format(new DateTime($item['datetime'])) . '</td>';
					$dataTmp .= '<td class="">' . htmlspecialchars($item['message'] ?? '') . '</td>';
					$dataTmp .= '<td class="">' . htmlspecialchars($item['description'] ?? '') . '</td>';
					$dataTmp .= '<td class="width-nowrap">' . htmlspecialchars($item['user'] ?? '') . '</td>';
					$dataTmp .= '</tr>';
				}

				$data = $dataTmp;
			}
		} catch (\Exception $e) {
			$success = $e->getMessage();
		}

		return new JsonResponse([
			'success' => $success,
			'datetime' => date('Y-m-d H:i:s'),
			'data' => $data,
		]);
	}
}
