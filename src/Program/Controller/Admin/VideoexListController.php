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
use App\Program\Repository\VideoexRepository;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Transliterator;

final class VideoexListController
{
	public function __construct(
		private FlashMessenger $flashMessenger,
	) {}

	public function list(
		PhtmlRenderer $renderer,
		SettingRepository $settingRepository,
	): Response
	{
		$setting = $settingRepository->fetchSetting();

		return new Response($renderer->renderWithAdminLayout('program/videoex/list', [
			'pageTitle' => 'Mimořádná videa',
			'setting' => $setting,
		]));
	}

	public function getList(
		Request $request,
		VideoexRepository $videoexRepository,
	): JsonResponse
	{
		$params = $request->query->all();

		$rows = null;
		$total = 0;
		$success = true;

		try {
			$rows = $videoexRepository->fetchForBootstrapTable($params);
			$total = $videoexRepository->getCountForBootstrapTable($params);
		} catch (Exception $e) {
			$success = $e->getMessage();
		}

		return new JsonResponse([
			'success' => $success,
			'rows' => $rows,
			'total' => $total,
		]);
	}

	public function getListParts(
		Request $request,
		VideoexRepository $videoexRepository,
	): JsonResponse
	{
		$params = $request->query->all();

		$rows = null;
		$total = 0;
		$success = true;

		try {
			$rows = $videoexRepository->fetchForBootstrapTableParts($params);
			$total = $videoexRepository->getCountForBootstrapTableParts($params);
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
		VideoexRepository $videoexRepository,
	): JsonResponse
	{
		$success = true;
		$message = null;
		$video = null;

		try {
			$params = $request->request->all();
			$video_id = $params['id'];

			$video = $videoexRepository->findPostBy('id', $video_id);
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

	public function getVideoPart(
		Request $request,
		VideoexRepository $videoexRepository,
	): JsonResponse
	{
		$success = true;
		$message = null;
		$part = null;

		try {
			$params = $request->request->all();
			$part_id = (int) $params['id'];

			$part = $videoexRepository->findPartBy($part_id);
		} catch (Exception $e) {
			$success = false;
			$message = $e->getMessage();
		}

		return new JsonResponse([
			'success' => $success,
			'message' => $message,
			'part' => $part,
		]);
	}

	public function getUrl(
		Request $request,
	): JsonResponse
	{
		$success = true;
		$message = null;
		$url = null;

		try {
			$params = $request->request->all();
			$title = $params['title'];

			$url = $this->removeAccent($title, '-');
		} catch (Exception $e) {
			$success = false;
			$message = $e->getMessage();
		}

		return new JsonResponse([
			'success' => $success,
			'message' => $message,
			'url' => $url,
		]);
	}

	/**
	 * @param string $text
	 * @param string|null $replace
	 * @return string
	 */
	private function removeAccent(string $text, ?string $replace = null): string
	{
		$transliterator = Transliterator::createFromRules(':: Any-Latin; :: NFD; :: [:Nonspacing Mark:] Remove; :: NFC; :: [:Punctuation:] Remove; :: Lower();', Transliterator::FORWARD);
		$textTmp = $text;
		if ($transliterator) {
			$textTmp = $transliterator->transliterate($text);
			$textTmp = preg_replace('/[^a-z0-9]+/', '-', $textTmp);
			$textTmp = strtolower($textTmp);
			if ($replace) {
				$textTmp = str_replace(' ', $replace, $textTmp);
			}
		}

		return $textTmp;
	}
}
