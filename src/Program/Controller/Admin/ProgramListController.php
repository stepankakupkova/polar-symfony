<?php

namespace App\Program\Controller\Admin;

use App\Application\View\PhtmlRenderer;
use App\Program\Repository\ProgramRepository;
use App\Program\Repository\ShowRepository;
use App\Program\Repository\VideoRepository;
use App\Authorization\Identity\AuthorizationUser;
use DateTime;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Transliterator;

final class ProgramListController
{
	public function index(
		PhtmlRenderer $renderer,
		ProgramRepository $programRepository,
		VideoRepository $videoRepository,
		ShowRepository $showRepository,
	): Response
	{
		return new Response($renderer->renderWithAdminLayout('program/admin/program/index', [
			'pageTitle' => 'Program',
			'countProgram' => $programRepository->getCount(),
			'countProgramPremiere' => $programRepository->getCount(true),
			'countVideo' => $videoRepository->getCount(),
			'countShow' => $showRepository->getCount(),
			'countShowActive' => $showRepository->getCount(true),
		]));
	}

	public function list(
		PhtmlRenderer $renderer,
		Security $security,
	): Response
	{
		return new Response($renderer->renderWithAdminLayout('program/admin/program/list', [
			'pageTitle' => 'Program',
		]));
	}

	public function getList(
		Request $request,
		ProgramRepository $programRepository,
	): JsonResponse
	{
		$params = $request->query->all();

		$rows = null;
		$total = 0;
		$success = true;

		try {
			$rows = $programRepository->fetchForBootstrapTable($params);
			$total = $programRepository->getCountForBootstrapTable($params);
		} catch (Exception $e) {
			$success = $e->getMessage();
		}

		return new JsonResponse([
			'success' => $success,
			'rows' => $rows,
			'total' => $total,
		]);
	}

	public function getProgram(
		Request $request,
		ProgramRepository $programRepository,
	): JsonResponse
	{
		$success = true;
		$message = null;
		$program = null;

		try {
			$params = $request->request->all();
			$program_id = $params['id'];

			$program = $programRepository->findPostBy('id', $program_id);
		} catch (Exception $e) {
			$success = false;
			$message = $e->getMessage();
		}

		return new JsonResponse([
			'success' => $success,
			'message' => $message,
			'program' => $program,
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
			$datetime = new DateTime($params['date'] . ' ' . $params['time']);

			$url = $this->removeAccent($title, '-') . '-' . $datetime->format('d-m-Y-H-i-s');
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
