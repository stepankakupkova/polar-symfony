<?php

declare(strict_types=1);

namespace App\Program\Controller\Admin;

use App\Application\Service\FlashMessenger;
use App\Program\Repository\ShowexRepository;
use App\Application\View\PhtmlRenderer;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Transliterator;

class ShowexListController extends AbstractController
{
	public function __construct(
		private string $PUBLIC_PATH,
	) {}

	public function list(
		PhtmlRenderer $renderer,
		FlashMessenger $flashMessenger,
	): Response
	{
		return new Response($renderer->renderWithAdminLayout('program/showex/list', [
			'pageTitle' => 'Mimořádné pořady',
			'flashMessages' => $flashMessenger->getMessages(),
		]));
	}

	public function getList(
		Request $request,
		ShowexRepository $showexRepository,
	): JsonResponse
	{
		$params = $request->query->all();

		$rows = null;
		$total = 0;
		$success = true;

		try {
			$rows = $showexRepository->fetchForBootstrapTable($params);
			$total = $showexRepository->getCountForBootstrapTable($params);
		} catch (\Exception $e) {
			$success = $e->getMessage();
		}

		return new JsonResponse([
			'success' => $success,
			'rows' => $rows,
			'total' => $total,
		]);
	}

	public function getShow(
		Request $request,
		ShowexRepository $showexRepository,
	): JsonResponse
	{
		$success = true;
		$message = null;
		$show = null;

		try {
			$show_id = $request->request->getInt('id');
			$show = $showexRepository->findPostBy('id', $show_id);
		} catch (\Exception $e) {
			$success = false;
			$message = $e->getMessage();
		}

		return new JsonResponse([
			'success' => $success,
			'message' => $message,
			'show' => $show,
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
			$title = $request->request->get('title', '');
			$url = $this->removeAccent($title, '-');
		} catch (\Exception $e) {
			$success = false;
			$message = $e->getMessage();
		}

		return new JsonResponse([
			'success' => $success,
			'message' => $message,
			'url' => $url,
		]);
	}

	public function redactorImageManager(
		Request $request,
	): JsonResponse
	{
		$show_id = $request->query->get('show_id');
		$data = [];

		try {
			if ($show_id) {
				$path = '/data/program/showex/' . $show_id . '/image';
			} else {
				$path = '/data/program/showex/default/image';
			}

			$dir = $this->PUBLIC_PATH . $path;
			if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
				throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
			}

			$scan = array_diff(scandir($dir), ['..', '.', '.DS_Store']);

			if ($scan) {
				$id = 1;
				foreach ($scan as $file) {
					if (!str_contains($file, '_thumb.')) {
						$data[] = [
							'thumb' => $path . '/' . substr($file, 0, -4) . '_thumb' . substr($file, strlen($file) - 4),
							'url' => $path . '/' . $file,
							'id' => $id++,
							'title' => ucwords(strtolower(str_replace('_', ' ', substr($file, 0, -4))))
						];
					}
				}
			}
		} catch (\Exception $e) {
			$data = [];
		}

		return new JsonResponse($data);
	}

	public function redactorFileManager(
		Request $request,
	): JsonResponse
	{
		$show_id = $request->query->get('show_id');
		$data = [];

		try {
			if ($show_id) {
				$path = '/data/program/showex/' . $show_id . '/file';
			} else {
				$path = '/data/program/showex/default/file';
			}

			$dir = $this->PUBLIC_PATH . $path;
			if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
				throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
			}

			$scan = array_diff(scandir($dir), ['..', '.', '.DS_Store']);

			if ($scan) {
				$id = 1;
				foreach ($scan as $file) {
					$data[] = [
						'title' => ucwords(strtolower(str_replace('_', ' ', substr($file, 0, -4)))),
						'name' => $file,
						'url' => $path . '/' . $file,
						'id' => $id++,
						'size' => $this->prettyFileSize(filesize($dir . '/' . $file), 0),
					];
				}
			}
		} catch (\Exception $e) {
			$data = [];
		}

		return new JsonResponse($data);
	}

	private function prettyFileSize($bytes, int $decimals = 2): string
	{
		$sz = 'BKMGTP';
		$factor = floor((strlen((string)$bytes) - 1) / 3);
		return sprintf("%.{$decimals}f", $bytes / (1024 ** $factor)) . @$sz[(int)$factor];
	}

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
