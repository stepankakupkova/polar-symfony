<?php

namespace App\Program\Controller\Admin;

use App\Application\View\PhtmlRenderer;
use App\Program\Repository\ProgramRepository;
use App\Program\Repository\SettingRepository;
use App\Program\Repository\VideoRepository;
use DateTime;
use Exception;
use Imagine\Exception\Exception as ImagineException;
use Imagine\Image\Box as ImagineBox;
use Imagine\Image\ImageInterface;
use Imagine\Image\Palette\RGB as ImaginePaletteRGB;
use Imagine\Image\Point as ImaginePoint;
use Imagine\Imagick\Imagine;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class VideoWriteController
{
	public function __construct(
		private string $PUBLIC_PATH,
		private string $LIGHT_PATH,
		private Security $security,
	) {}

	public function edit(
		Request $request,
		PhtmlRenderer $renderer,
		VideoRepository $videoRepository,
		UrlGeneratorInterface $urlGenerator,
	): Response
	{
		$video_id = (int) $request->attributes->get('id', 0);

		if ($video_id === 0) {
			return new RedirectResponse($urlGenerator->generate('admin_program_video_list'));
		}

		try {
			$video = $videoRepository->findPostBy('id', $video_id);
		} catch (Exception) {
			return new RedirectResponse($urlGenerator->generate('admin_program_video_list'));
		}

		if (!$video) {
			return new RedirectResponse($urlGenerator->generate('admin_program_video_list'));
		}

		return new Response($renderer->renderWithAdminLayout('program/admin/video/edit', [
			'pageTitle' => 'Videa',
			'video' => $video,
		]));
	}

	public function deleteVideo(
		Request $request,
		VideoRepository $videoRepository,
		ProgramRepository $programRepository,
		LoggerInterface $logger,
	): JsonResponse
	{
		$identity = $this->security->getUser();
		$success = true;
		$message = null;
		$video_id = null;

		try {
			$params = $request->request->all();
			$video_id = $params['id'];

			$video = $videoRepository->findPostBy('id', $video_id);

			if ($video) {

				@unlink($this->PUBLIC_PATH . '/data/program/thumbs/' . $video['name'] . '.jpg');
				@unlink($this->PUBLIC_PATH . '/data/program/thumbs/' . $video['name'] . '-260x146.jpg');
				@unlink($this->LIGHT_PATH . 'porady/publikovano/' . $video['path'] . '/' . $video['name'] . '_lq.mp4');
				@unlink($this->LIGHT_PATH . 'porady/publikovano/' . $video['path'] . '/' . $video['name'] . '_hq.mp4');

				$programs = $programRepository->findPostsBy('video_id', $video_id);

				if ($programs) {
					foreach ($programs as $program) {
						$programRepository->updatePost($program['id'], ['video_id' => null]);
					}
				}

				$videoRepository->deletePost($video_id);

				// Log
				$logger->notice('PROGRAM - Delete video', [
					'description' => 'OK',
					'user' => $identity->getUserIdentifier(),
					'file' => __FILE__,
				]);
			} else {
				$success = false;
				$message = 'Nelze najít video';

				// Log
				$logger->error('PROGRAM - Delete video', [
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
			$logger->error('PROGRAM - Delete video', [
				'description' => 'ERROR',
				'user' => $identity->getUserIdentifier(),
				'file' => __FILE__,
				'trace' => $message,
			]);
		}

		return new JsonResponse([
			'success' => $success,
			'message' => $message,
			'video_id' => $video_id,
		]);
	}

	public function loadVideos(
		Request $request,
		VideoRepository $videoRepository,
		ProgramRepository $programRepository,
		SettingRepository $settingRepository,
		LoggerInterface $logger,
	): Response
	{
		$identity = $this->security->getUser();
		$cron = $request->query->get('cron') === 'true';
		$message = null;

		$headers = $cron ? [] : [
			// Zakázání bufferování pro NGINX
			'X-Accel-Buffering' => 'no',
			// Zakázání bufferování pro APACHE
			'Content-Encoding' => 'none',
		];

		return new StreamedResponse(function() use ($videoRepository, $programRepository, $settingRepository, $logger, $identity, $cron, &$message) {
			if (!$cron) {
				// JsPush-like progress
				ob_implicit_flush(true);
				if (ob_get_level()) {
					ob_end_flush();
				}
			}

			try {
				$dir = $this->LIGHT_PATH . 'porady/nepublikovano';
				$files = [];

				$handle = opendir($dir);
				if ($handle) {
					while (false !== ($entry = readdir($handle))) {
						if ($entry !== "." && $entry !== ".." && $entry !== ".DS_Store") {
							$files[] = $entry;
						}
					}
					closedir($handle);
				}

				sort($files);

				$count = count($files) + 2;

				if (!$cron) {
					$this->pushProgress(0, $count + 2, 'Načítám...');
				}

				$data = [];
				foreach ($files as $iValue) {
					$file = $iValue;
					if (mb_strtolower(substr($file, -7), 'UTF-8') === '_hq.mp4') {
						$file = mb_substr($file, 0, -7, 'UTF-8');
					} elseif (mb_strtolower(substr($file, -7), 'UTF-8') === '_lq.mp4') {
						$file = mb_substr($file, 0, -7, 'UTF-8');
					}

					if (!in_array($file, $data, true)) {
						$data[] = $file;
					}
				}
				$ok = 0;
				$nok = 0;
				$sec = 55;

				foreach ($data as $i => $iValue) {
					$file = $iValue;

					$lq = $hq = false;

					if (in_array($file . '_hq.mp4', $files, true)) {
						$hq = true;
					}
					if (in_array($file . '_lq.mp4', $files, true)) {
						$lq = true;
					}

					if (!$cron) {
						$this->pushProgress(
							$i + 1,
							$count,
							'Soubor: ' . $file . ' - ' .
							((($lq === $hq) === true) ? '<i class="fa fa-fw fa-check-circle text-color-success"></i>' : '<i class="fa fa-fw fa-times-circle text-color-danger"></i>')
						);
					}

					if (($lq === $hq) === true) {
						$ok++;

						$programs = $programRepository->findPostsBy('file', $file . '.mp4');

						if ($programs) {
							$program = $programs[0];
							$date = new DateTime($program['time']);

							$dir = $this->LIGHT_PATH . 'porady/publikovano/' . $date->format('Y') . '/' . $date->format('m') . '/' . $date->format('d') . '/';
							if (!file_exists($dir)) {
								if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
									throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
								}
								chmod($dir, 0777);
							}

							rename($this->LIGHT_PATH . 'porady/nepublikovano/' . $file . '_hq.mp4', $dir . $file . '_hq.mp4');
							rename($this->LIGHT_PATH . 'porady/nepublikovano/' . $file . '_lq.mp4', $dir . $file . '_lq.mp4');

							$this->createPreview($dir . $file . '_hq.mp4', $sec, $logger, $identity);

							$videoData = [
								'name' => $file,
								'path' => $date->format('Y') . '/' . $date->format('m') . '/' . $date->format('d'),
								'lenght' => filesize($dir . $file . '_hq.mp4'),
								'size_lq' => filesize($dir . $file . '_lq.mp4'),
								'size_hq' => filesize($dir . $file . '_hq.mp4'),
								'duration' => $this->getDurationFromLight($dir . $file . '_lq.mp4'),
								'showed' => 0,
							];

							$newVideoId = $videoRepository->insertPost($videoData);

							foreach ($programs as $program) {
								$programRepository->updatePost($program['id'], ['video_id' => $newVideoId]);
							}
						}
					} else {
						$nok++;
					}
				}

				if (!$cron) {
					$this->pushProgress(
						$count,
						$count,
						'Dokončeno: ' . $ok . ' <i class="fa fa-fw fa-check-circle text-success"></i>, ' . $nok . ' <i class="fa fa-fw fa-times-circle text-danger"></i>'
					);
				}

				// Aktualizace datumu posledního načtení
				$settingRepository->updateSetting(['video_update_date' => date('Y-m-d H:i:s')]);

				$success = true;

				// Log
				$logger->notice('PROGRAM - Load videos', [
					'description' => 'OK',
					'user' => $identity ? $identity->getUserIdentifier() : 'CRON',
					'file' => __FILE__,
				]);
			} catch (Exception $e) {
				$success = false;
				$message = $e->getMessage();

				if (!$cron) {
					$this->pushProgress(100, 100, '<span class="text-danger">Nelze načíst videa</span>');
					$this->pushProgress(100, 100, $e->getMessage());
				}

				$logger->error('PROGRAM - Load videos', [
					'description' => 'ERROR',
					'user' => $identity ? $identity->getUserIdentifier() : 'CRON',
					'file' => __FILE__,
					'trace' => $e->getMessage(),
				]);
			}

			if (!$cron) {
				$this->pushFinish();
			} else {
				echo json_encode([
					'success' => $success,
					'message' => $message,
				]);
			}
		}, 200, $headers);
	}

	public function createPreviewAction(
		Request $request,
		VideoRepository $videoRepository,
		LoggerInterface $logger,
	): JsonResponse
	{
		$identity = $this->security->getUser();
		$success = true;
		$message = null;
		$video_id = null;
		$sec = null;
		$preview = null;

		try {
			$params = $request->request->all();
			$video_id = $params['video_id'];
			$sec = (int) $params['sec'];

			$video = $videoRepository->findPostBy('id', $video_id);

			if ($video) {
				$this->createPreview(
					$this->LIGHT_PATH . 'porady/publikovano/' . $video['path'] . '/' . $video['name'] . '_hq.mp4',
					$sec,
					$logger,
					$identity
				);

				$preview = '/data/program/thumbs/' . $video['name'] . '.jpg';

				// Log
				$logger->notice('PROGRAM - Edit video - Create preview', [
					'description' => 'OK',
					'user' => $identity->getUserIdentifier(),
					'file' => __FILE__,
				]);
			} else {
				$success = false;
				$message = 'Nelze najít video';

				// Log
				$logger->error('PROGRAM - Edit video - Create preview', [
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
			$logger->error('PROGRAM - Edit video - Create preview', [
				'description' => 'ERROR',
				'user' => $identity->getUserIdentifier(),
				'file' => __FILE__,
				'trace' => $message,
			]);
		}

		return new JsonResponse([
			'success' => $success,
			'message' => $message,
			'video_id' => $video_id,
			'sec' => $sec,
			'preview' => $preview,
		]);
	}

	public function uploadPreviewFromPc(
		Request $request,
		VideoRepository $videoRepository,
		LoggerInterface $logger,
	): JsonResponse
	{
		$identity = $this->security->getUser();
		$success = true;
		$message = null;
		$video_id = null;
		$preview = null;

		$file = $request->files->get('file');
		if (!$file) {
			return new JsonResponse([
				'error' => 'Žádné soubory k nahrání',
			]);
		}

		$fileType = strtolower($file->getMimeType());

		// Obrázek
		if (in_array($fileType, ['image/png', 'image/jpg', 'image/gif', 'image/jpeg', 'image/pjpeg'])) {

			if ($fileType === 'image/png') {
				return new JsonResponse([
					'success' => false,
					'error' => true,
					'message' => 'Chyba',
					'description' => 'Vkládejte pouze *.jpg obrázky',
				]);
			}

			try {
				$params = $request->request->all();
				$video_id = $params['video_id'];

				$video = $videoRepository->findPostBy('id', $video_id);

				if ($video) {
					$preview = '/data/program/thumbs/' . $video['name'];

					// JPG
					if (in_array($fileType, ['image/jpg', 'image/jpeg', 'image/pjpeg'])) {
						$this->createPreviewsFromUpload($file->getPathname(), $preview);
					}

					// Log
					$logger->notice('PROGRAM - Edit video - Upload preview', [
						'description' => 'OK',
						'user' => $identity->getUserIdentifier(),
						'file' => __FILE__,
					]);
				}
			} catch (Exception $e) {
				$success = false;
				$message = $e->getMessage();

				// Log
				$logger->error('PROGRAM - Edit video - Upload preview', [
					'description' => 'ERROR',
					'user' => $identity->getUserIdentifier(),
					'file' => __FILE__,
					'trace' => $message,
				]);
			}
		}

		return new JsonResponse([
			'success' => $success,
			'message' => $message,
			'video_id' => $video_id,
			'preview' => $preview . '.jpg',
		]);
	}

	/**
	 * @param string $file
	 * @param int $sec
	 */
	private function createPreview(string $file, int $sec, LoggerInterface $logger, mixed $identity): void
	{
		$previewDir = 'data/program/thumbs/';
		$preview = $previewDir . substr($file, strrpos($file, '/') + 1, -7) . '.jpg';
		$preview2 = $previewDir . substr($file, strrpos($file, '/') + 1, -7) . '-260x146.jpg';

		if (!file_exists($previewDir)) {
			if (!mkdir($previewDir, 0777, true) && !is_dir($previewDir)) {
				throw new RuntimeException(sprintf('Directory "%s" was not created', $previewDir));
			}
			chmod($previewDir, 0777);
		}

		try {
			$command = 'ffmpeg -y -ss ' . $sec . ' -i "' . $file . '" -vcodec mjpeg -qscale 7 -vframes 1 -an -f rawvideo -s 460x259 "' . $this->PUBLIC_PATH . '/' . $preview . '"';
			exec($command);
			$command = 'ffmpeg -y -ss ' . $sec . ' -i "' . $file . '" -vcodec mjpeg -qscale 7 -vframes 1 -an -f rawvideo -s 260x146 "' . $this->PUBLIC_PATH . '/' . $preview2 . '"';
			exec($command);

			// Log
			$logger->notice('PROGRAM - Load videos - Create preview', [
				'description' => 'OK',
				'user' => $identity->getUserIdentifier() ?? 'CRON',
				'file' => __FILE__,
			]);
		} catch (Exception $e) {
			$logger->error('PROGRAM - Load videos - Create preview', [
				'description' => 'ERROR',
				'user' => $identity->getUserIdentifier() ?? 'CRON',
				'file' => __FILE__,
				'trace' => $e->getMessage(),
			]);
		}
	}

	/**
	 * Vytvoří JPG soubory v daných formátech
	 */
	private function createPreviewsFromUpload(string $file, string $target): void
	{
		$imagine = new Imagine();
		try {
			$image = $imagine->open($file);

			$size = $image->getSize();
			$image_width = $size->getWidth();
			$image_height = $size->getHeight();

			$palette = new ImaginePaletteRGB();

			$ratio_hd = 16 / 9;
			$ratio_image = $image_width / $image_height;

			if ($ratio_image >= $ratio_hd) {
				$canvas_height = round(($image_width / 16) * 9);
				$size = new ImagineBox($image_width, $canvas_height);
				$color = $palette->color('#fff', 100);
				$image_tmp = $imagine->create($size, $color);
				$image_tmp->paste($image, new ImaginePoint(0, ($canvas_height / 2) - ($image_height / 2)));
				$image = $image_tmp;
			} else {
				$canvas_width = round(($image_height / 9) * 16);
				$size = new ImagineBox($canvas_width, $image_height);
				$color = $palette->color('#fff', 100);
				$image_tmp = $imagine->create($size, $color);
				$image_tmp->paste($image, new ImaginePoint(($canvas_width / 2) - ($image_width / 2), 0));
				$image = $image_tmp;
			}

			$image
				->resize(new ImagineBox(460, 259))
				->thumbnail(new ImagineBox(460, 259), ImageInterface::THUMBNAIL_INSET)
				->save($this->PUBLIC_PATH . '/' . $target . '.jpg', ['jpeg_quality' => 90]);

			$image
				->resize(new ImagineBox(260, 146))
				->thumbnail(new ImagineBox(260, 146), ImageInterface::THUMBNAIL_INSET)
				->save($this->PUBLIC_PATH . '/' . $target . '-260x146.jpg', ['jpeg_quality' => 90]);

		} catch (ImagineException $e) {
			// silent
		}
	}

	/**
	 * Zjistí duration jako počet vteřin
	 */
	private function getDurationFromLight(string $file): ?int
	{
		try {
			$command = 'ffprobe -i "' . $file . '" -v quiet -print_format json -show_format -show_streams -hide_banner > temp_file';
			exec($command, $output, $res);
			$info = json_decode(file_get_contents('temp_file'));

			return (int) round((int) $info->format->duration, 0);
		} catch (Exception $e) {
			return null;
		}
	}

	/**
	 * JsPush-like progress output
	 */
	private function pushProgress(int $current, int $total, string $text): void
	{
		$percent = $total > 0 ? ($current / $total) * 100 : 0;
		echo '<script type="text/javascript">'
			. 'parent.updateProgress({'
				. 'percent:' . round($percent, 2) . ','
				. 'text:"' . addslashes($text) . '",'
				. 'timeTaken:0,'
				. 'timeRemaining:0'
			. '})'
			. '</script>'
			. str_pad('', 4096) . "\n";
		flush();
	}

	private function pushFinish(): void
	{
		echo '<script type="text/javascript">'
			. 'parent.finishProgress()'
			. '</script>'
			. str_pad('', 4096) . "\n";
		flush();
	}
}
