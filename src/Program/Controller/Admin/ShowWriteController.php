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
use App\Program\Repository\ProgramRepository;
use App\Program\Repository\SettingRepository;
use App\Program\Repository\ShowRepository;
use Exception;
use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Imagine\Image\ManipulatorInterface;
use Imagine\Image\Palette\RGB;
use Imagine\Image\Point;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Transliterator;

final class ShowWriteController
{
	private string $imageDefault = 'data/program/show/!default-show.png';

	public function __construct(
		private string $PUBLIC_PATH,
		private Security $security,
		private FlashMessenger $flashMessenger,
	) {}

	public function add(
		Request $request,
		PhtmlRenderer $renderer,
		ShowRepository $showRepository,
		SettingRepository $settingRepository,
		UrlGeneratorInterface $urlGenerator,
		LoggerInterface $logger,
	): Response
	{
		$identity = $this->security->getUser();
		$setting = $settingRepository->fetchSetting();
		$categories = $showRepository->fetchCategoryForBootstrapSelect();

		if ($request->isMethod('POST')) {
			$post = $request->request->all();

			if (isset($post['cancel'])) {
				return new RedirectResponse($urlGenerator->generate('admin_program_show'));
			}

			try {
				$data = [
					'title' => trim(strip_tags($post['title'] ?? '')),
					'url' => trim(strip_tags($post['url'] ?? '')),
					'category_id' => !empty($post['category_id']) ? (int) $post['category_id'] : null,
					'short_description' => trim($post['short_description'] ?? ''),
					'content' => $post['content'] ?? '',
					'status' => isset($post['status']) ? 1 : 0,
					'show_in_archive' => isset($post['show_in_archive']) ? 1 : 0,
					'video_parts' => isset($post['video_parts']) ? 1 : 0,
					'show_datetime' => isset($post['show_datetime']) ? 1 : 0,
					'download' => isset($post['download']) ? 1 : 0,
					'newton' => isset($post['newton']) ? 1 : 0,
					'seo_keywords' => trim(strip_tags($post['seo_keywords'] ?? '')),
					'seo_description' => trim(strip_tags($post['seo_description'] ?? '')),
					'order' => $showRepository->getCount() + 1,
				];

				$show_id = $showRepository->insertPost($data);

				// AdresĂˇĹ™
				$folder = 'data/program/show/' . $show_id;
				if (!is_dir($this->PUBLIC_PATH . '/' . $folder)) {
					if (!mkdir($concurrentDirectory = $this->PUBLIC_PATH . '/' . $folder, 0777, true) && !is_dir($concurrentDirectory)) {
						throw new RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
					}
					chmod($this->PUBLIC_PATH . '/' . $folder, 0777);
				}

				// ObrĂˇzek
				$image = $post['image'] ?? null;
				if ($image === $this->imageDefault) {
					$image = null;
				} elseif ($image && str_contains($image, '/tmp/')) {
					$newImage = $folder . '/' . substr($image, strrpos($image, '/') + 1);
					rename($this->PUBLIC_PATH . '/' . $image, $this->PUBLIC_PATH . '/' . $newImage);
					$image = $newImage;
				}

				$showRepository->updatePost($show_id, [
					'image' => $image,
					'thumb' => $image,
				]);

				// Redactor
				$dirFile = $this->PUBLIC_PATH . '/data/program/show/tmp/file';
				if (is_dir($dirFile)) {
					rename($dirFile, $this->PUBLIC_PATH . '/' . $folder . '/file');
					if (!mkdir($dirFile, 0777, true) && !is_dir($dirFile)) {
						throw new RuntimeException(sprintf('Directory "%s" was not created', $dirFile));
					}
				}
				$dirImage = $this->PUBLIC_PATH . '/data/program/show/tmp/image';
				if (is_dir($dirImage)) {
					rename($dirImage, $this->PUBLIC_PATH . '/' . $folder . '/image');
					if (!mkdir($dirImage, 0777, true) && !is_dir($dirImage)) {
						throw new RuntimeException(sprintf('Directory "%s" was not created', $dirImage));
					}
				}

				// Nahradit /tmp/ za /show_id/ v contentu
				$content = $data['content'];
				if ($content) {
					$content = str_replace('/tmp/', '/' . $show_id . '/', $content);
					$showRepository->updatePost($show_id, ['content' => $content]);
				}

				// VygenerovĂˇnĂ­ souboru config
				$this->createConfig($showRepository);

				$this->flashMessenger->addMessage(
					'success',
					'Úspěšně',
					'Porad <strong>&quot;' . htmlspecialchars($data['title']) . '&quot;</strong> vytvoren'
				);

				// Log
				$logger->notice('PROGRAM - Add show', [
					'description' => 'OK',
					'user' => $identity->getUserIdentifier(),
					'file' => __FILE__,
				]);

				return new RedirectResponse($urlGenerator->generate('admin_program_show'));
			} catch (Exception $e) {
				// Log
				$logger->error('PROGRAM - Add show', [
					'description' => 'ERROR',
					'user' => $identity->getUserIdentifier(),
					'file' => __FILE__,
					'trace' => $e->getMessage(),
				]);
			}
		}

		return new Response($renderer->renderWithAdminLayout('program/show/add', [
			'pageTitle' => 'Pořady',
			'scheme' => $request->getSession()->get('scheme', 'dark'),
			'setting' => $setting,
			'categories' => $categories,
		]));
	}

	public function edit(
		Request $request,
		PhtmlRenderer $renderer,
		ShowRepository $showRepository,
		SettingRepository $settingRepository,
		UrlGeneratorInterface $urlGenerator,
		LoggerInterface $logger,
	): Response
	{
		$identity = $this->security->getUser();
		$show_id = (int) $request->attributes->get('id', 0);

		if ($show_id === 0) {
			return new RedirectResponse($urlGenerator->generate('admin_program_show_add'));
		}

		try {
			$show = $showRepository->findPostBy('id', $show_id);
		} catch (Exception) {
			return new RedirectResponse($urlGenerator->generate('admin_program_show'));
		}

		if (!$show) {
			return new RedirectResponse($urlGenerator->generate('admin_program_show'));
		}

		$setting = $settingRepository->fetchSetting();
		$categories = $showRepository->fetchCategoryForBootstrapSelect();

		if ($request->isMethod('POST')) {
			$post = $request->request->all();

			if (isset($post['cancel'])) {
				return new RedirectResponse($urlGenerator->generate('admin_program_show'));
			}

			try {
				$data = [
					'title' => trim(strip_tags($post['title'] ?? '')),
					'url' => trim(strip_tags($post['url'] ?? '')),
					'category_id' => !empty($post['category_id']) ? (int) $post['category_id'] : null,
					'short_description' => trim($post['short_description'] ?? ''),
					'content' => $post['content'] ?? '',
					'status' => isset($post['status']) ? 1 : 0,
					'show_in_archive' => isset($post['show_in_archive']) ? 1 : 0,
					'video_parts' => isset($post['video_parts']) ? 1 : 0,
					'show_datetime' => isset($post['show_datetime']) ? 1 : 0,
					'download' => isset($post['download']) ? 1 : 0,
					'newton' => isset($post['newton']) ? 1 : 0,
					'seo_keywords' => trim(strip_tags($post['seo_keywords'] ?? '')),
					'seo_description' => trim(strip_tags($post['seo_description'] ?? '')),
				];

				// ObrĂˇzek
				$image = $post['image'] ?? null;
				if ($image === $this->imageDefault) {
					$data['image'] = null;
					$data['thumb'] = null;
				}

				$showRepository->updatePost($show_id, $data);

				// VygenerovĂˇnĂ­ souboru config
				$this->createConfig($showRepository);

				$this->flashMessenger->addMessage(
					'success',
					'Úspěšně',
					'Porad <strong>&quot;' . htmlspecialchars($data['title']) . '&quot;</strong> upraven'
				);

				// Log
				$logger->notice('PROGRAM - Edit show', [
					'description' => 'OK',
					'user' => $identity->getUserIdentifier(),
					'file' => __FILE__,
				]);

				return new RedirectResponse($urlGenerator->generate('admin_program_show'));
			} catch (Exception $e) {
				// Log
				$logger->error('PROGRAM - Edit show', [
					'description' => 'ERROR',
					'user' => $identity->getUserIdentifier(),
					'file' => __FILE__,
					'trace' => $e->getMessage(),
				]);
			}
		}

		return new Response($renderer->renderWithAdminLayout('program/show/edit', [
			'pageTitle' => 'Pořady',
			'scheme' => $request->getSession()->get('scheme', 'dark'),
			'show' => $show,
			'setting' => $setting,
			'categories' => $categories,
		]));
	}

	public function deleteShow(
		Request $request,
		ShowRepository $showRepository,
		ProgramRepository $programRepository,
		LoggerInterface $logger,
	): JsonResponse
	{
		$identity = $this->security->getUser();
		$success = true;
		$message = null;
		$show_id = null;

		try {
			$params = $request->request->all();
			$show_id = $params['id'];

			$show = $showRepository->findPostBy('id', $show_id);

			if ($show) {
				$this->deleteDir($this->PUBLIC_PATH . '/data/program/show/' . $show['id'] . '/');

				$programRepository->deleteProgram2Shows($show['id']);
				$showRepository->deleteShowsTimes($show['id']);
				$showRepository->deletePost($show['id']);

				// Re-order
				$shows = $showRepository->fetchAll();
				$rank = 1;
				foreach ($shows as $s) {
					$showRepository->updatePost($s['id'], ['order' => $rank]);
					$rank++;
				}

				// VygenerovĂˇnĂ­ souboru config
				$this->createConfig($showRepository);

				// Log
				$logger->notice('PROGRAM - Delete show', [
					'description' => 'OK',
					'user' => $identity->getUserIdentifier(),
					'file' => __FILE__,
				]);
			} else {
				$success = false;
				$message = 'Nelze najĂ­t poĹ™ad';

				// Log
				$logger->error('PROGRAM - Delete show', [
					'description' => 'ERROR',
					'user' => $identity->getUserIdentifier(),
					'file' => __FILE__,
					'trace' => $message,
				]);
			}
		} catch (Exception $e) {
			$success = false;
			$message = $e->getMessage();

			// Log
			$logger->error('PROGRAM - Delete show', [
				'description' => 'ERROR',
				'user' => $identity->getUserIdentifier(),
				'file' => __FILE__,
				'trace' => $message,
			]);
		}

		return new JsonResponse([
			'success' => $success,
			'message' => $message,
			'show_id' => $show_id,
		]);
	}

	public function setOrder(
		Request $request,
		ShowRepository $showRepository,
	): JsonResponse
	{
		$params = $request->request->all();
		$data = $params['data'];
		$success = true;
		$message = null;

		try {
			$rank = 1;
			foreach ($data as $item) {
				$showRepository->updatePost($item['id'], ['order' => $rank]);
				$rank++;
			}
		} catch (Exception $e) {
			$success = false;
			$message = $e->getMessage();
		}

		return new JsonResponse([
			'success' => $success,
			'message' => $message,
		]);
	}

	public function setTime(
		Request $request,
		ShowRepository $showRepository,
	): JsonResponse
	{
		$params = $request->request->all();
		$id = (int) ($params['id'] ?? 0);
		$show_id = (int) $params['show_id'];
		$day = $params['day'];
		$value = $params['value'];
		$premiere = ($params['premiere'] ?? 'false') === 'true';
		$success = true;
		$message = null;

		try {
			if ($id) {
				if ($value) {
					$showRepository->updateTime($id, $show_id, $day, $value, $premiere);
				} else {
					$showRepository->deleteTime($id);
				}
			} elseif ($value) {
				$showRepository->insertTime($show_id, $day, $value, $premiere);
			}
		} catch (Exception $e) {
			$success = false;
			$message = $e->getMessage();
		}

		return new JsonResponse([
			'success' => $success,
			'message' => $message,
			'id' => $id,
			'day' => $day,
			'value' => $value,
			'premiere' => $premiere,
		]);
	}

	public function uploadImage(
		Request $request,
		ShowRepository $showRepository,
		SettingRepository $settingRepository,
	): JsonResponse
	{
		$file = $request->files->get('file');
		if (!$file) {
			return new JsonResponse([
				'error' => 'Ĺ˝ĂˇdnĂ© soubory k nahrĂˇnĂ­',
			]);
		}

		$show_id = $request->request->get('show_id');

		$folder = 'data/program/show/';
		if ($show_id && $show_id !== 'null') {
			$folder .= $show_id . '/';
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
			$setting = $settingRepository->fetchSetting();

			$type = match ($fileType) {
				'image/gif' => 'gif',
				'image/png' => 'png',
				default => 'jpg',
			};

			$filename = 'show-' . date('YmdHis') . '_' . random_int(100, 999);
			[$width, $height] = getimagesize($file->getPathname());
			if ($width === (int) $setting['show_img_width'] && $height === (int) $setting['show_img_height']) {
				$imageFileName = $folder . $filename . '.' . $type;
				$file->move($this->PUBLIC_PATH . '/' . $folder, $filename . '.' . $type);
			} else {
				$imageFileName = $this->createImage($file->getPathname(), $folder, $filename, (int) $setting['show_img_width'], (int) $setting['show_img_height'], $type);
			}
		} catch (Exception $e) {
			return new JsonResponse([
				'error' => $e->getMessage(),
			]);
		}

		if ($show_id && $show_id !== 'null') {
			$show = $showRepository->findPostBy('id', (int) $show_id);

			// Smazat bĂ˝valĂ˝ obrĂˇzek
			if ($show && $show['image'] && $show['image'] !== $this->imageDefault) {
				@unlink($this->PUBLIC_PATH . '/' . $show['image']);
			}

			$showRepository->updatePost((int) $show_id, [
				'image' => $imageFileName,
				'thumb' => $imageFileName,
			]);
		}

		if ($imageFileName === $this->imageDefault) {
			return new JsonResponse([
				'name' => $file->getClientOriginalName(),
				'url' => $imageFileName,
				'error' => 'ObrĂˇzek je pĹ™Ă­liĹˇ velkĂ˝',
			]);
		}

		return new JsonResponse([
			'name' => $file->getClientOriginalName(),
			'url' => $imageFileName,
			'type' => $fileType,
		]);
	}

	public function setDefaultImage(
		Request $request,
		ShowRepository $showRepository,
		LoggerInterface $logger,
	): JsonResponse
	{
		$identity = $this->security->getUser();
		$success = true;
		$message = null;
		$show_id = null;
		$field = null;

		try {
			$params = $request->request->all();
			$show_id = $params['show_id'];
			$field = $params['field'];

			$show = $showRepository->findPostBy('id', (int) $show_id);

			if ($show) {
				switch ($field) {
					case 'image':
						// Smazat bĂ˝valĂ˝ obrĂˇzek
						if ($show['image'] && $show['image'] !== $this->imageDefault) {
							@unlink($this->PUBLIC_PATH . '/' . $show['image']);
						}

						$showRepository->updatePost((int) $show_id, [
							'image' => null,
							'thumb' => null,
						]);
						break;
				}

				// Log
				$logger->notice('PROGRAM - Set show image', [
					'description' => 'OK',
					'user' => $identity->getUserIdentifier(),
					'file' => __FILE__,
				]);
			} else {
				$success = false;
				$message = 'Nelze najĂ­t poĹ™ad';

				// Log
				$logger->error('PROGRAM - Set show image', [
					'description' => 'ERROR',
					'user' => $identity->getUserIdentifier(),
					'file' => __FILE__,
					'trace' => $message,
				]);
			}
		} catch (Exception $e) {
			$success = false;
			$message = $e->getMessage();

			// Log
			$logger->error('PROGRAM - Set show image', [
				'description' => 'ERROR',
				'user' => $identity->getUserIdentifier(),
				'file' => __FILE__,
				'trace' => $message,
			]);
		}

		return new JsonResponse([
			'success' => $success,
			'message' => $message,
			'show_id' => $show_id,
			'field' => $field,
			'url' => $this->imageDefault,
		]);
	}

	public function redactorImageUpload(
		Request $request,
	): JsonResponse
	{
		$show_id = $request->query->get('show_id');
		$data = [];

		try {
			$path = '/data/program/show/';
			if ($show_id) {
				$path .= $show_id . '/image';
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
				if (!is_array($files)) {
					$files = [$files];
				}
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
							->paste($resizeImg, new Point($startX, $startY))
							->save($destination);

						$file->move($dir, $filename);

						$data['file-' . $key] = [
							'id' => $show_id ? (string) $show_id : '0',
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

	public function redactorFileUpload(
		Request $request,
	): JsonResponse
	{
		$show_id = $request->query->get('show_id');
		$data = [];

		try {
			$path = '/data/program/show/';
			if ($show_id) {
				$path .= $show_id . '/file';
			} else {
				$path .= 'tmp/file';
			}

			$dir = $this->PUBLIC_PATH . $path;
			if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
				throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
			}

			$types = [
				'text/plain',
				'application/zip',
				'application/x-rar-compressed',
				'application/pdf',
				'application/msword',
				'application/rtf',
				'application/vnd.ms-excel',
				'application/vnd.ms-powerpoint',
				'application/vnd.oasis.opendocument.text',
				'application/vnd.oasis.opendocument.spreadsheet',
			];

			$files = $request->files->get('file');
			if ($files) {
				if (!is_array($files)) {
					$files = [$files];
				}
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
				$canvas_height = round(($image_width / $width) * $height);
				$size = new Box($image_width, $canvas_height);
				$color = $palette->color('#fff', $alpha);
				$image_tmp = $imagine->create($size, $color);
				$image_tmp->paste($image, new Point(0, ($canvas_height / 2) - ($image_height / 2)));
			} else {
				$canvas_width = round(($image_height / $height) * $width);
				$size = new Box($canvas_width, $image_height);
				$color = $palette->color('#fff', $alpha);
				$image_tmp = $imagine->create($size, $color);
				$image_tmp->paste($image, new Point(($canvas_width / 2) - ($image_width / 2), 0));
			}
			$image = $image_tmp;

			$image
				->resize(new Box($width, $height))
				->thumbnail(new Box($width, $height), ManipulatorInterface::THUMBNAIL_INSET);

			switch ($type) {
				case 'gif':
					$image->save($this->PUBLIC_PATH . '/' . $target . $filename . '.gif', ['flatten' => false]);
					break;
				case 'png':
					$image->save($this->PUBLIC_PATH . '/' . $target . $filename . '.png', ['png_compression_level' => 8]);
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

	private function createConfig(ShowRepository $showRepository): void
	{
		$configDir = dirname($this->PUBLIC_PATH) . '/config/';
		$programDir = $configDir . 'program/';
		$routesDir = $configDir . 'routes/';

		if (!is_dir($programDir) && !mkdir($concurrentDirectory = $programDir, 0777, true) && !is_dir($concurrentDirectory)) {
			throw new RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
		}

		if (!is_dir($routesDir) && !mkdir($concurrentDirectory = $routesDir, 0777, true) && !is_dir($concurrentDirectory)) {
			throw new RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
		}

		$routes = $showRepository->fetchRoutesForConfig();
		$yaml = "# Auto-generated Program Show routes - DO NOT EDIT MANUALLY\n";
		$yaml .= "# Generated: " . date('Y-m-d H:i:s') . "\n\n";

		foreach ($routes as $row) {
			$yaml .= 'program_show_' . $row['id'] . ":\n";
			$yaml .= '  path: /porady/' . $row['url'] . "\n";
			$yaml .= "  controller: App\\Program\\Controller\\Web\\ShowController::index\n";
			$yaml .= "\n";

			$yaml .= 'program_show_video_' . $row['id'] . ":\n";
			$yaml .= '  path: /porady/' . $row['url'] . '/{program_url}' . "\n";
			$yaml .= "  controller: App\\Program\\Controller\\Web\\ShowController::index\n";
			$yaml .= "  requirements:\n";
			$yaml .= "    program_url: '[a-zA-Z0-9][a-zA-Z0-9_-]+'\n";
			$yaml .= "\n";
		}

		file_put_contents($routesDir . 'program_show_generated.yaml', $yaml);

		$navigation = [
			'submenu-dropdown' => [
				'show' => [
					'pages' => [],
				],
			],
		];

		$shows = $showRepository->fetchForConfig();
		foreach ($shows as $show) {
			$navigation['submenu-dropdown']['show']['pages'][] = [
				'id' => 'menuProgramShowWeb-' . $show['id'],
				'label' => $show['title'],
				'route' => 'program_show_' . $show['id'],
				'visible' => 0,
				'order' => $show['order'],
			];
		}

		$content = "<?php\n\n";
		$content .= "// Auto-generated Program Show navigation - DO NOT EDIT MANUALLY\n";
		$content .= "// Generated: " . date('Y-m-d H:i:s') . "\n\n";
		$content .= 'return ' . $this->exportArray($navigation) . ";\n";

		file_put_contents($programDir . 'show_navigation.php', $content);
	}

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
