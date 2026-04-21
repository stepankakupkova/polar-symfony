<?php
/*
 * @project polar
 * @author Rostislav Greipel
 * @link https://rogr.cz
 * @copyright (c) 2011 - 2021 ROGR All Rights Reserved
 * @license https://rogr.cz/license/eula EULA License
 */

namespace App\Program\Controller\Admin;

use App\Application\Service\FlashMessenger;
use App\Application\View\PhtmlRenderer;
use App\Program\Repository\SettingRepository;
use App\Program\Repository\VideoRepository;
use Exception;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class VideoListController
{
	public function __construct(
		private FlashMessenger $flashMessenger,
	) {}

	public function list(
		PhtmlRenderer $renderer,
		SettingRepository $settingRepository,
		Security $security,
	): Response
	{
		$setting = $settingRepository->fetchSetting();

		return new Response($renderer->renderWithAdminLayout('program/video/list', [
			'pageTitle' => 'Videa',
			'setting' => $setting,
		]));
	}

	public function getList(
		Request $request,
		VideoRepository $videoRepository,
	): JsonResponse
	{
		$params = $request->query->all();

		$rows = null;
		$total = 0;
		$success = true;

		try {
			$rows = $videoRepository->fetchForBootstrapTable($params);
			$total = $videoRepository->getCountForBootstrapTable($params);
		} catch (Exception $e) {
			$success = $e->getMessage();
		}

		return new JsonResponse([
			'success' => $success,
			'rows' => $rows,
			'total' => $total,
		]);
	}

	public function getVideo(
		Request $request,
		VideoRepository $videoRepository,
	): JsonResponse
	{
		$success = true;
		$message = null;
		$video = null;

		try {
			$params = $request->request->all();
			$video_id = $params['id'];

			$video = $videoRepository->findPostBy('id', $video_id);
		} catch (Exception $e) {
			$success = false;
			$message = $e->getMessage();
		}

		return new JsonResponse([
			'success' => $success,
			'message' => $message,
			'video' => $video,
		]);
	}
}
