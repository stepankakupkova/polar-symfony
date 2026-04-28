<?php

namespace App\Page\Controller\Admin;

use App\Application\View\PhtmlRenderer;
use App\Page\Repository\PageRepository;
use Exception;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Transliterator;

final class PageListController
{
	public function __construct(
		private PageRepository $pageRepository,
		private PhtmlRenderer $renderer,
		private string $PUBLIC_PATH,
	) {}

	/**
	 * @return Response
	 */
	public function list(): Response
	{
		$lang = 'cs_CZ';

		return new Response($this->renderer->renderWithAdminLayout('page/admin/list', [
			'pageTitle' => 'Stránky',
			'lang' => $lang,
			'locales' => ['cs_CZ' => 'Čeština'],
		]));
	}

	/**
	 * @return JsonResponse
	 */
	public function getList(Request $request): JsonResponse
	{
		$params = $request->query->all();

		$rows = null;
		$total = 0;
		$success = true;

		try {
			$rows = $this->pageRepository->fetchForBootstrapTable($params);
			$total = $this->pageRepository->getCountForBootstrapTable($params);
		} catch (Exception $e) {
			$success = $e->getMessage();
		}

		return new JsonResponse([
			'success' => $success,
			'rows' => $rows,
			'total' => $total,
		]);
	}

	/**
	 * @return JsonResponse
	 */
	public function getPage(Request $request): JsonResponse
	{
		$success = true;
		$message = null;
		$page = null;
		try {
			$params = $request->request->all();
			$page_id = $params['id'];

			$page = $this->pageRepository->findPostBy('id', (int) $page_id);
		} catch (Exception $e) {
			$success = false;
			$message = $e->getMessage();
		}

		return new JsonResponse([
			'success' => $success,
			'message' => $message,
			'page' => $page,
		]);
	}

	/**
	 * @return JsonResponse
	 */
	public function getSort(Request $request): JsonResponse
	{
		$html = '';
		$success = true;

		try {
			$params = $request->request->all();
			$lang = $params['lang'];
			$header = $params['header'] === 'true';

			$html = $this->pageRepository->fetchForNestable($lang, $header, null);
		} catch (Exception $e) {
			$success = $e->getMessage();
		}

		return new JsonResponse([
			'success' => $success,
			'html' => $html,
		]);
	}

	/**
	 * @return JsonResponse
	 */
	public function getUrl(Request $request): JsonResponse
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
	 * @return JsonResponse
	 */
	public function redactorImageManager(Request $request): JsonResponse
	{
		$params = $request->query->all();
		$page_id = $params['page_id'];
		$data = [];

		try {
			if ($page_id) {
				$path = '/data/page/' . $page_id . '/image';
			} else {
				$path = '/data/page/default/image';
			}

			$dir = $this->PUBLIC_PATH . $path;
			if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
				throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
			}

			$scan = array_diff(scandir($dir), ['..', '.', '.DS_Store']);

			if ($scan) {
				foreach ($scan as $file) {
					if (!str_contains($file, '_thumb.')) {
						$data[] = [
							'thumb' => $path . '/' . substr($file, 0, -4) . '_thumb' . substr($file, strlen($file) - 4),
							'url' => $path . '/' . $file,
							'id' => $page_id,
							'title' => ucwords(strtolower(str_replace('_', ' ', substr($file, 0, -4))))
						];
					}
				}
			}

		} catch (Exception $e) {
			$data = $e->getMessage();
		}

		return new JsonResponse($data);
	}

	/**
	 * @return JsonResponse
	 */
	public function redactorFileManager(Request $request): JsonResponse
	{
		$params = $request->query->all();
		$page_id = $params['page_id'];
		$data = [];

		try {
			if ($page_id) {
				$path = '/data/page/' . $page_id . '/file';
			} else {
				$path = '/data/page/default/file';
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

		} catch (Exception $e) {
			$data = $e->getMessage();
		}

		return new JsonResponse($data);
	}

	/**
	 * @param $bytes
	 * @param int $decimals
	 * @return string
	 */
	private function prettyFileSize($bytes, int $decimals = 2): string
	{
		$sz = 'BKMGTP';
		$factor = floor((strlen((string) $bytes) - 1) / 3);
		return sprintf("%.{$decimals}f", $bytes / (1024 ** $factor)) . @$sz[(int) $factor];
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
