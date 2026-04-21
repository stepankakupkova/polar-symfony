<?php

namespace App\Page\Controller\Admin;

use App\Application\Service\FlashMessenger;
use App\Application\Service\Logger;
use App\Application\View\PhtmlRenderer;
use App\Page\Repository\PageRepository;
use App\Page\Repository\PageSettingRepository;
use Exception;
use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Imagine\Image\ManipulatorInterface;
use Imagine\Image\Palette\RGB;
use Imagine\Image\Point;
use RuntimeException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Transliterator;

final class PageWriteController
{
	/**
	 * @var string
	 */
	private string $imageDefault;

	public function __construct(
		private FlashMessenger $flashMessenger,
		private Logger $logger,
		private PageRepository $pageRepository,
		private PageSettingRepository $settingRepository,
		private PhtmlRenderer $renderer,
		private Security $security,
		private UrlGeneratorInterface $urlGenerator,
		private string $PUBLIC_PATH,
	) {
		$this->imageDefault = 'data/page/!default-page.png';
	}

	/**
	 * @return Response|RedirectResponse
	 */
	public function add(Request $request): Response
	{
		$identity = $this->security->getUser();
		$lang = 'cs_CZ';
		$setting = $this->settingRepository->fetchSetting();

		$post = [];
		$errors = [];

		try {
			if (!$request->isMethod('POST')) {
				return new Response($this->renderer->renderWithAdminLayout('page/admin/add', [
					'pageTitle' => 'Stránky',
					'lang' => $lang,
					'setting' => $setting,
					'post' => $post,
					'errors' => $errors,
				]));
			}

			$post = $request->request->all();

			if (isset($post['cancel'])) {
				return new RedirectResponse($this->urlGenerator->generate('admin_page_list'));
			}

			$errors = $this->validateForm($post);

			if (!empty($errors)) {
				return new Response($this->renderer->renderWithAdminLayout('page/admin/add', [
					'pageTitle' => 'Stránky',
					'lang' => $lang,
					'setting' => $setting,
					'post' => $post,
					'errors' => $errors,
				]));
			}

			$id = $this->pageRepository->insertPost([
				'lang' => $lang,
				'active' => !empty($post['active']) ? 1 : 0,
				'header' => !empty($post['header']) ? 1 : 0,
				'title' => $post['title'],
				'url' => $post['url'],
				'content' => $post['content'] ?? '',
				'image' => null,
				'parent' => null,
				'depth' => 1,
				'rank' => $this->pageRepository->getCountByLangAndParent($lang) + 1,
				'rank_total' => $this->pageRepository->getCountByLang($lang) + 1,
				'seo_keywords' => $post['seo_keywords'] ?? '',
				'seo_description' => $post['seo_description'] ?? '',
				'created_date' => date('Y-m-d H:i:s'),
				'updated_date' => date('Y-m-d H:i:s'),
				'created_user' => $identity->getUserIdentifier(),
				'updated_user' => $identity->getUserIdentifier(),
			]);

			// Adresář
			$folder = 'data/page/' . $id;
			if (!is_dir($this->PUBLIC_PATH . '/' . $folder)) {
				if (!mkdir($concurrentDirectory = $this->PUBLIC_PATH . '/' . $folder, 0777, true) && !is_dir($concurrentDirectory)) {
					throw new RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
				}
				chmod($this->PUBLIC_PATH . '/' . $folder, 0777);
			}

			// Obrázek
			$image = $post['image'] ?? null;
			if ($image === $this->imageDefault) {
				$image = null;
			} else if ($image && str_contains($image, '/tmp/')) {
				$newImage = $folder . '/' . substr($image, strrpos($image, '/') + 1);
				rename($this->PUBLIC_PATH . '/' . $image, $this->PUBLIC_PATH . '/' . $newImage);
				$image = $newImage;
			}

			// Redactor
			$dirFile = $this->PUBLIC_PATH . '/data/page/tmp/file';
			if (is_dir($dirFile)) {
				rename($dirFile, $this->PUBLIC_PATH . '/' . $folder . '/file');
				if (!mkdir($dirFile, 0777, true) && !is_dir($dirFile)) {
					throw new RuntimeException(sprintf('Directory "%s" was not created', $dirFile));
				}
			}
			$dirImage = $this->PUBLIC_PATH . '/data/page/tmp/image';
			if (is_dir($dirImage)) {
				rename($dirImage, $this->PUBLIC_PATH . '/' . $folder . '/image');
				if (!mkdir($dirImage, 0777, true) && !is_dir($dirImage)) {
					throw new RuntimeException(sprintf('Directory "%s" was not created', $dirImage));
				}
			}

			$content = str_replace('/tmp/', '/' . $id . '/', $post['content'] ?? '');

			$this->pageRepository->updatePost($id, [
				'image' => $image,
				'content' => $content,
			]);

			$this->createConfig($lang);

			$this->logger->notice('PAGE - Add page', [
				'description' => 'OK',
				'user' => $identity->getUserIdentifier(),
				'file' => __FILE__,
			]);

			$this->flashMessenger->addMessage(
				'success',
				'Stránky',
				'Stránka <strong>' . htmlspecialchars($post['title']) . '</strong> byla vytvořena'
			);

			return new RedirectResponse($this->urlGenerator->generate('admin_page_list'));
		} catch (Exception $e) {
			$this->logger->err('PAGE - Add page', [
				'description' => 'ERROR',
				'user' => $identity->getUserIdentifier(),
				'file' => __FILE__,
				'trace' => $e->getMessage(),
			]);
			$errors['general'] = $e->getMessage();
		}

		return new Response($this->renderer->renderWithAdminLayout('page/admin/add', [
			'pageTitle' => 'Stránky',
			'lang' => $lang,
			'setting' => $setting,
			'post' => $post,
			'errors' => $errors,
		]));
	}

	/**
	 * @return Response|RedirectResponse
	 */
	public function edit(Request $request, int $id = 0): Response
	{
		$identity = $this->security->getUser();
		$lang = 'cs_CZ';
		if ($id === 0) {
			return new RedirectResponse($this->urlGenerator->generate('admin_page_add'));
		}

		$page = $this->pageRepository->findPostBy('id', $id);
		if (!$page) {
			return new RedirectResponse($this->urlGenerator->generate('admin_page_list'));
		}

		$setting = $this->settingRepository->fetchSetting();

		$post = $page;
		$errors = [];

		try {
			if (!$request->isMethod('POST')) {
				return new Response($this->renderer->renderWithAdminLayout('page/admin/edit', [
					'pageTitle' => 'Stránky',
					'page' => $page,
					'lang' => $lang,
					'setting' => $setting,
					'post' => $post,
					'errors' => $errors,
				]));
			}

			$post = $request->request->all();

			if (isset($post['cancel'])) {
				return new RedirectResponse($this->urlGenerator->generate('admin_page_list'));
			}

			$errors = $this->validateForm($post);

			if (!empty($errors)) {
				return new Response($this->renderer->renderWithAdminLayout('page/admin/edit', [
					'pageTitle' => 'Stránky',
					'page' => array_merge($page, $post),
					'lang' => $lang,
					'setting' => $setting,
					'post' => array_merge($page, $post),
					'errors' => $errors,
				]));
			}

			// Obrázek
			$image = $post['image'] ?? $page['image'];
			if ($image === $this->imageDefault) {
				$image = null;
			}

			$this->pageRepository->updatePost($id, [
				'active' => !empty($post['active']) ? 1 : 0,
				'header' => !empty($post['header']) ? 1 : 0,
				'title' => $post['title'],
				'url' => $post['url'],
				'content' => $post['content'] ?? '',
				'image' => $image,
				'seo_keywords' => $post['seo_keywords'] ?? '',
				'seo_description' => $post['seo_description'] ?? '',
				'updated_date' => date('Y-m-d H:i:s'),
				'updated_user' => $identity->getUserIdentifier(),
			]);

			$this->createConfig($lang);

			$this->logger->notice('PAGE - Edit page', [
				'description' => 'OK',
				'user' => $identity->getUserIdentifier(),
				'file' => __FILE__,
			]);

			$this->flashMessenger->addMessage(
				'success',
				'Stránky',
				'Stránka <strong>' . htmlspecialchars($post['title']) . '</strong> byla upravena'
			);

			return new RedirectResponse($this->urlGenerator->generate('admin_page_list'));
		} catch (Exception $e) {
			$this->logger->err('PAGE - Edit page', [
				'description' => 'ERROR',
				'user' => $identity->getUserIdentifier(),
				'file' => __FILE__,
				'trace' => $e->getMessage(),
			]);
			$errors['general'] = $e->getMessage();
		}

		return new Response($this->renderer->renderWithAdminLayout('page/admin/edit', [
			'pageTitle' => 'Stránky',
			'page' => is_array($post) ? array_merge($page, $post) : $page,
			'lang' => $lang,
			'setting' => $setting,
			'post' => is_array($post) ? array_merge($page, $post) : $page,
			'errors' => $errors,
		]));
	}

	/**
	 * @return JsonResponse
	 */
	public function duplicatePage(Request $request): JsonResponse
	{
		$identity = $this->security->getUser();
		$success = true;
		$message = null;
		$page_id = null;
		$lang = null;

		try {
			$params = $request->request->all();
			$page_id = $params['id'];
			$lang = $params['lang'];

			$page = $this->pageRepository->findPostBy('id', (int) $page_id);

			if ($page) {

				$newData = $page;
				unset($newData['id']);
				$newData['lang'] = $lang;
				$newData['created_date'] = date('Y-m-d H:i:s');
				$newData['updated_date'] = date('Y-m-d H:i:s');
				$newData['created_user'] = $identity->getUserIdentifier();
				$newData['updated_user'] = $identity->getUserIdentifier();

				$newId = $this->pageRepository->insertPost($newData);

				$updateData = [];
				if ($page['image']) {
					$updateData['image'] = str_replace('data/page/' . $page_id, 'data/page/' . $newId, $page['image']);
				}
				$updateData['content'] = str_replace('data/page/' . $page_id, 'data/page/' . $newId, $page['content']);

				if (!empty($updateData)) {
					$this->pageRepository->updatePost($newId, $updateData);
				}

				$this->copyDir($this->PUBLIC_PATH . '/data/page/' . $page_id, $this->PUBLIC_PATH . '/data/page/' . $newId);

				$this->createConfig($lang);

				$this->logger->notice('PAGE - Duplicate page', [
					'description' => 'OK',
					'user' => $identity->getUserIdentifier(),
					'file' => __FILE__,
				]);

			} else {
				$success = false;
				$message = 'Stránka nenalezena';
				$this->logger->err('PAGE - Duplicate page', [
					'description' => 'ERROR',
					'user' => $identity->getUserIdentifier(),
					'file' => __FILE__,
					'trace' => $message,
				]);
			}
		} catch (Exception $e) {
			$success = false;
			$message = $e->getMessage();
			$this->logger->err('PAGE - Duplicate page', [
				'description' => 'ERROR',
				'user' => $identity->getUserIdentifier(),
				'file' => __FILE__,
				'trace' => $e->getMessage(),
			]);
		}

		return new JsonResponse([
			'success' => $success,
			'message' => $message,
			'page_id' => $page_id,
			'lang' => $lang,
		]);
	}

	/**
	 * @return JsonResponse
	 */
	public function deletePage(Request $request): JsonResponse
	{
		$identity = $this->security->getUser();
		$success = true;
		$message = null;
		$page_id = null;

		try {
			$params = $request->request->all();
			$page_id = $params['id'];
			$lang = $params['lang'];

			$page = $this->pageRepository->findPostBy('id', (int) $page_id);

			if ($page) {
				$this->deleteDir($this->PUBLIC_PATH . '/data/page/' . $page['id'] . '/');

				$this->pageRepository->deletePost((int) $page['id']);

				$this->createConfig($lang);

				$this->logger->notice('PAGE - Delete page', [
					'description' => 'OK',
					'user' => $identity->getUserIdentifier(),
					'file' => __FILE__,
				]);

			} else {
				$success = false;
				$message = 'Stránka nenalezena';
				$this->logger->err('PAGE - Delete page', [
					'description' => 'ERROR',
					'user' => $identity->getUserIdentifier(),
					'file' => __FILE__,
					'trace' => $message,
				]);
			}
		} catch (Exception $e) {
			$success = false;
			$message = $e->getMessage();
			$this->logger->err('PAGE - Delete page', [
				'description' => 'ERROR',
				'user' => $identity->getUserIdentifier(),
				'file' => __FILE__,
				'trace' => $e->getMessage(),
			]);
		}

		return new JsonResponse([
			'success' => $success,
			'message' => $message,
			'page_id' => $page_id,
		]);
	}

	/**
	 * @return Response
	 */
	public function sort(): Response
	{
		$lang = 'cs_CZ';
		return new Response($this->renderer->renderWithAdminLayout('page/admin/sort', [
			'pageTitle' => 'Stránky',
			'lang' => $lang,
		]));
	}

	/**
	 * @return JsonResponse
	 */
	public function setSort(Request $request): JsonResponse
	{
		$lang = null;
		$data = null;
		$success = true;

		try {
			$params = $request->request->all();
			$lang = $params['lang'];
			$data = $params['data'];

			$this->savePagesSort($data);

			$this->createConfig($lang);
		} catch (Exception $e) {
			$success = $e->getMessage();
		}

		return new JsonResponse([
			'success' => $success,
			'lang' => $lang,
			'data' => $data,
		]);
	}

	/**
	 * @param array $data
	 * @param int|null $parent
	 * @param int $depth
	 * @param int $rank
	 * @param int $rankTotal
	 * @return int
	 */
	protected function savePagesSort(array $data, ?int $parent = null, int $depth = 1, int $rank = 1, int $rankTotal = 1): int
	{
		$identity = $this->security->getUser();
		if ($data) {
			foreach ($data as $item) {
				$this->pageRepository->updatePost((int) $item['id'], [
					'parent' => $parent,
					'depth' => $depth,
					'rank' => $rank,
					'rank_total' => $rankTotal,
					'updated_date' => date('Y-m-d H:i:s'),
					'updated_user' => $identity->getUserIdentifier(),
				]);
				++$rank;
				++$rankTotal;
				if (isset($item['children'])) {
					$rankTotal = $this->savePagesSort($item['children'], (int) $item['id'], $depth + 1, 1, $rankTotal);
				}
			}
		}
		return $rankTotal;
	}

	/**
	 * @return JsonResponse
	 */
	public function uploadImage(Request $request): JsonResponse
	{
		$identity = $this->security->getUser();
		$file = $request->files->get('file');

		if (!$file) {
			return new JsonResponse([
				'error' => 'Žádné soubory k nahrání',
			]);
		}

		$page_id = $request->request->get('page_id');

		$folder = 'data/page/';
		if ($page_id !== 'null' && $page_id !== null) {
			$folder .= $page_id . '/';
		} else {
			$folder .= 'tmp/';
		}

		if (!is_dir($this->PUBLIC_PATH . '/' . $folder)) {
			if (!mkdir($concurrentDirectory = $this->PUBLIC_PATH . '/' . $folder, 0777, true) && !is_dir($concurrentDirectory)) {
				throw new RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
			}
			chmod($this->PUBLIC_PATH . '/' . $folder, 0777);
		}

		$fileType = strtolower($file->getMimeType());

		try {
			$setting = $this->settingRepository->fetchSetting();

			$type = match ($fileType) {
				'image/gif' => 'gif',
				'image/png' => 'png',
				default => 'jpg',
			};

			$filename = 'page-' . date('YmdHis') . '_' . random_int(100, 999);
			[$width, $height] = getimagesize($file->getPathname());
			if ($width === (int) ($setting['img_width'] ?? 800) && $height === (int) ($setting['img_height'] ?? 450)) {
				$imageFileName = $folder . $filename . '.' . $type;
				$file->move($this->PUBLIC_PATH . '/' . $folder, $filename . '.' . $type);
			} else {
				$imageFileName = $this->createImage($file->getPathname(), $folder, $filename, (int) ($setting['img_width'] ?? 800), (int) ($setting['img_height'] ?? 450), $type);
			}
		} catch (Exception $e) {
			return new JsonResponse([
				'error' => $e->getMessage(),
			]);
		}

		if ($page_id !== 'null' && $page_id !== null) {
			$page = $this->pageRepository->findPostBy('id', (int) $page_id);

			// Smazat bývalý obrázek
			if ($page && $page['image'] && $page['image'] !== $this->imageDefault) {
				@unlink($this->PUBLIC_PATH . '/' . $page['image']);
			}

			if ($page) {
				$this->pageRepository->updatePost((int) $page['id'], [
					'image' => $imageFileName,
					'updated_date' => date('Y-m-d H:i:s'),
					'updated_user' => $identity->getUserIdentifier(),
				]);
			}
		}

		if ($imageFileName === $this->imageDefault) {
			return new JsonResponse([
				'name' => $file->getClientOriginalName(),
				'url' => $imageFileName,
				'error' => 'Obrázek je příliš velký',
			]);
		}

		return new JsonResponse([
			'name' => $file->getClientOriginalName(),
			'url' => $imageFileName,
			'type' => $fileType,
		]);
	}

	/**
	 * @return JsonResponse
	 */
	public function setDefaultImage(Request $request): JsonResponse
	{
		$identity = $this->security->getUser();
		$success = true;
		$message = null;
		$page_id = null;
		$field = null;

		try {
			$params = $request->request->all();
			$page_id = $params['page_id'];
			$field = $params['field'];

			$page = $this->pageRepository->findPostBy('id', (int) $page_id);

			if ($page) {
				switch ($field) {
					case 'image':
						// Smazat bývalý obrázek
						if ($page['image'] && $page['image'] !== $this->imageDefault) {
							@unlink($this->PUBLIC_PATH . '/' . $page['image']);
						}

						$this->pageRepository->updatePost((int) $page_id, [
							'image' => null,
							'updated_user' => $identity->getUserIdentifier(),
						]);
						break;
				}

				$this->logger->notice('PAGE - Set page image', [
					'description' => 'OK',
					'user' => $identity->getUserIdentifier(),
					'file' => __FILE__,
				]);
			} else {
				$success = false;
				$message = 'Stránka nenalezena';
				$this->logger->err('PAGE - Set page image', [
					'description' => 'ERROR',
					'user' => $identity->getUserIdentifier(),
					'file' => __FILE__,
					'trace' => $message,
				]);
			}
		} catch (Exception $e) {
			$success = false;
			$message = $e->getMessage();
			$this->logger->err('PAGE - Set page image', [
				'description' => 'ERROR',
				'user' => $identity->getUserIdentifier(),
				'file' => __FILE__,
				'trace' => $e->getMessage(),
			]);
		}

		return new JsonResponse([
			'success' => $success,
			'message' => $message,
			'page_id' => $page_id,
			'url' => $this->imageDefault,
		]);
	}

	/**
	 * @return JsonResponse
	 */
	public function redactorImageUpload(Request $request): JsonResponse
	{
		$params = $request->query->all();
		$page_id = $params['page_id'] ?? null;
		$data = [];

		try {
			$path = '/data/page/';
			if ($page_id) {
				$path .= $page_id . '/image';
			} else {
				$path .= 'tmp/image';
			}

			$dir = $this->PUBLIC_PATH . $path;
			if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
				throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
			}

			$types = [
				'image/png',
				'image/jpg',
				'image/gif',
				'image/jpeg',
				'image/pjpeg',
			];

			$files = $request->files->get('file');

			if ($files) {
				foreach ($files as $key => $file) {
					$type = strtolower($file->getMimeType());
					if (in_array($type, $types, true)) {
						$name = $file->getClientOriginalName();
						$filename = substr($name, 0, strpos($name, '.'));
						$ext = substr($name, strpos($name, '.') + 1);
						$filename = $this->removeAccent($filename, '-');
						$thumbName = $filename . '_thumb.' . $ext;
						$filename .= '.' . $ext;
						$source = $file->getPathname();
						$destination = $dir . '/' . $thumbName;
						$width = 100;
						$height = 74;
						$size = new Box($width, $height);
						$mode = ManipulatorInterface::THUMBNAIL_INSET;
						$imagine = new Imagine();

						$resizeImg = $imagine
							->open($source)
							->thumbnail($size, $mode);
						$resizeSize = $resizeImg->getSize();
						$resizeWidth = $resizeSize->getWidth();
						$resizeHeight = $resizeSize->getHeight();

						$preserve = $imagine->create($size);
						$startX = 0;
						$startY = 0;
						if ($resizeWidth < $width) {
							$startX = ($width - $resizeWidth) / 2;
						}
						if ($resizeHeight < $height) {
							$startY = ($height - $resizeHeight) / 2;
						}
						$preserve
							->paste($resizeImg, new Point((int) $startX, (int) $startY))
							->save($destination);

						$file->move($dir, $filename);

						$data['file-' . $key] = [
							'id' => $page_id ? (string) $page_id : (string) 0,
							'url' => $path . '/' . $filename,
						];
					}
				}
			}
		} catch (Exception $e) {
			$data = [
				'error' => true,
				'message' => $e->getMessage(),
			];
		}

		return new JsonResponse($data);
	}

	/**
	 * @return JsonResponse
	 */
	public function redactorFileUpload(Request $request): JsonResponse
	{
		$params = $request->query->all();
		$page_id = $params['page_id'] ?? null;
		$data = [];

		try {
			$path = '/data/page/';
			if ($page_id) {
				$path .= $page_id . '/file';
			} else {
				$path .= 'tmp/file';
			}

			$dir = $this->PUBLIC_PATH . $path;
			if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
				throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
			}

			$types = [
				'txt' => 'text/plain',
				'zip' => 'application/zip',
				'rar' => 'application/x-rar-compressed',
				'pdf' => 'application/pdf',
				'doc' => 'application/msword',
				'rtf' => 'application/rtf',
				'xls' => 'application/vnd.ms-excel',
				'ppt' => 'application/vnd.ms-powerpoint',
				'docx' => 'application/msword',
				'xlsx' => 'application/vnd.ms-excel',
				'pptx' => 'application/vnd.ms-powerpoint',
				'odt' => 'application/vnd.oasis.opendocument.text',
				'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
			];

			$files = $request->files->get('file');

			if ($files) {
				$id = 1;
				foreach ($files as $key => $file) {
					$type = strtolower($file->getMimeType());
					if (in_array($type, $types, true)) {
						$name = $file->getClientOriginalName();
						$filename = substr($name, 0, strpos($name, '.'));
						$ext = substr($name, strpos($name, '.') + 1);
						$filename = $this->removeAccent($filename, '-');
						$filename .= '.' . $ext;

						$file->move($dir, $filename);

						$data['file-' . $key] = [
							'id' => $id++,
							'name' => $filename,
							'url' => $path . '/' . $filename,
						];
					}
				}
			}
		} catch (Exception $e) {
			$data = [
				'error' => true,
				'message' => $e->getMessage(),
			];
		}

		return new JsonResponse($data);
	}

	/**
	 * @param array $post
	 * @return array
	 */
	private function validateForm(array $post): array
	{
		$errors = [];
		if (empty($post['title'])) {
			$errors['title'] = 'Název je povinný';
		}
		if (empty($post['url'])) {
			$errors['url'] = 'URL adresa je povinná';
		}
		return $errors;
	}

	/**
	 * @param string $file
	 * @param string $target
	 * @param string $filename
	 * @param int $width
	 * @param int $height
	 * @param string $type
	 * @return string|null
	 */
	private function createImage(string $file, string $target, string $filename, int $width, int $height, string $type = 'jpg'): ?string
	{
		$imagine = new Imagine();
		try {
			$image = $imagine->open($file);

			$size = $image->getSize();
			$image_width = $size->getWidth();
			$image_height = $size->getHeight();

			$palette = new RGB();

			$alpha = match ($type) {
				'png' => 0,
				default => 100,
			};

			$ratio_hd = $width / $height;
			$ratio_image = $image_width / $image_height;

			if ($ratio_image >= $ratio_hd) {
				// na šířku (obrázek, který je širší, přidat bílý horní a spodní pruh)
				$canvas_height = (int) round(($image_width / $width) * $height);
				$size = new Box($image_width, $canvas_height);
				$color = $palette->color('#fff', $alpha);
				$image_tmp = $imagine->create($size, $color);

				$image_tmp->paste($image, new Point(0, (int) (($canvas_height / 2) - ($image_height / 2))));
			} else {
				// na výšku (obrázek, který je vyšší, přidat bílý levý a pravý pruh)
				$canvas_width = (int) round(($image_height / $height) * $width);
				$size = new Box($canvas_width, $image_height);
				$color = $palette->color('#fff', $alpha);
				$image_tmp = $imagine->create($size, $color);

				$image_tmp->paste($image, new Point((int) (($canvas_width / 2) - ($image_width / 2)), 0));
			}
			$image = $image_tmp;

			$image
				->resize(new Box($width, $height))
				->thumbnail(new Box($width, $height), ManipulatorInterface::THUMBNAIL_INSET);

			switch ($type) {
				case 'gif':
					$image
						->save($this->PUBLIC_PATH . '/' . $target . $filename . '.gif', ['flatten' => false]);
					break;
				case 'png':
					$image
						->save($this->PUBLIC_PATH . '/' . $target . $filename . '.png', ['png_compression_level' => 8]);
					break;
				case 'jpg':
				default:
					$image->save($this->PUBLIC_PATH . '/' . $target . $filename . '.jpg', ['jpeg_quality' => 85]);
					break;
			}
			unlink($file);
			unset($image);
			return $target . $filename . '.' . $type;
		} catch (\Imagine\Exception\RuntimeException $e) {
			return $e->getMessage();
		}
	}

	/**
	 * @param string $source
	 * @param string $target
	 */
	private function copyDir(string $source, string $target): void
	{
		if (is_dir($source)) {
			$dir = opendir($source);
			if (!mkdir($target) && !is_dir($target)) {
				throw new RuntimeException(sprintf('Directory "%s" was not created', $target));
			}
			foreach (scandir($source) as $file) {
				if (($file !== '.') && ($file !== '..')) {
					if (is_dir($source . '/' . $file)) {
						$this->copyDir($source . '/' . $file, $target . '/' . $file);
					} else {
						copy($source . '/' . $file, $target . '/' . $file);
					}
				}
			}
			closedir($dir);
		}
	}

	/**
	 * @param string $target
	 */
	private function deleteDir(string $target): void
	{
		if (is_dir($target)) {
			$files = glob($target . '*', GLOB_MARK);
			foreach ($files as $file) {
				$this->deleteDir($file);
			}
			rmdir($target);
		} elseif (is_file($target)) {
			unlink($target);
		}
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

	/**
	 * Vygeneruje konfiguraci routování a navigace z databáze
	 */
	private function createConfig(string $language): void
	{
		$configDir = dirname($this->PUBLIC_PATH) . '/config/';

		// 1. Vygenerování routes (YAML)
		$routes = $this->pageRepository->fetchRoutesForConfig();

		$yaml = "# Auto-generated page routes - DO NOT EDIT MANUALLY\n";
		$yaml .= "# Generated: " . date('Y-m-d H:i:s') . "\n\n";

		foreach ($routes as $row) {
			$yaml .= "page_" . $row['id'] . ":\n";
			$yaml .= "  path: /" . $row['url'] . "\n";
			$yaml .= "  controller: App\\Page\\Controller\\Web\\PageController::page\n";
			$yaml .= "  defaults:\n";
			$yaml .= "    page_id: " . $row['id'] . "\n";
			$yaml .= "\n";
		}

		file_put_contents($configDir . 'routes/page_generated.yaml', $yaml);

		// 2. Vygenerování navigace (PHP)
		$buildNavigation = function (string $lang, bool $header, ?int $parent = null) use (&$buildNavigation): array {
			$rows = $this->pageRepository->fetchForConfig($lang, $header, $parent);
			$items = [];

			foreach ($rows as $row) {
				$item = [
					'id' => 'menuPageWeb-' . $row['id'],
					'label' => $row['title'],
					'route' => 'page_' . $row['id'],
					'visible' => 1,
					'order' => 1 + (int) $row['rank_total'],
				];

				$children = $buildNavigation($lang, $header, (int) $row['id']);
				if (!empty($children)) {
					$item['pages'] = $children;
				}

				$items[$row['url']] = $item;
			}

			return $items;
		};

		$navigation = [
			'default' => $buildNavigation($language, false),
			'submenu-dropdown' => $buildNavigation($language, true),
			'submenu' => [],
		];

		// submenu = ploché menu (bez vnořování) pro mobily a drobečky
		$buildSubmenuFlat = function (string $lang, bool $header, ?int $parent = null) use (&$buildSubmenuFlat): array {
			$rows = $this->pageRepository->fetchForConfig($lang, $header, $parent);
			$items = [];

			foreach ($rows as $row) {
				// ID 15 se v originále přeskakuje (O TV POLAR - jen jeho potomci)
				if ((int) $row['id'] !== 15) {
					$items[$row['url']] = [
						'id' => 'menuPageWeb-' . $row['id'],
						'label' => $row['title'],
						'route' => 'page_' . $row['id'],
						'visible' => 1,
						'order' => 1 + (int) $row['rank_total'],
					];
				}

				$children = $buildSubmenuFlat($lang, $header, (int) $row['id']);
				foreach ($children as $key => $child) {
					$items[$key] = $child;
				}
			}

			return $items;
		};

		$navigation['submenu'] = $buildSubmenuFlat($language, true);

		$content = "<?php\n\n";
		$content .= "// Auto-generated page navigation - DO NOT EDIT MANUALLY\n";
		$content .= "// Generated: " . date('Y-m-d H:i:s') . "\n\n";
		$content .= "return " . $this->exportArray($navigation) . ";\n";

		file_put_contents($configDir . 'page_navigation.php', $content);
	}

	/**
	 * Čistý export PHP pole (bez var_export ošklivostí)
	 */
	private function exportArray(array $array, int $indent = 0): string
	{
		$pad = str_repeat("\t", $indent);
		$padInner = str_repeat("\t", $indent + 1);
		$isAssoc = array_keys($array) !== range(0, count($array) - 1);
		$lines = [];

		foreach ($array as $key => $value) {
			$keyStr = $isAssoc ? var_export($key, true) . ' => ' : '';
			if (is_array($value)) {
				$lines[] = $padInner . $keyStr . $this->exportArray($value, $indent + 1);
			} else {
				$lines[] = $padInner . $keyStr . var_export($value, true);
			}
		}

		if (empty($lines)) {
			return '[]';
		}

		return "[\n" . implode(",\n", $lines) . ",\n" . $pad . ']';
	}
}
