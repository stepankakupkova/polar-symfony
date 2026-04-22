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
use App\Program\Repository\ShowexRepository;
use App\Program\Repository\VideoexRepository;
use DateTime;
use Exception;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class VideoexWriteController
{
	public function __construct(
		private string $PUBLIC_PATH,
		private string $LIGHT_PATH,
		private string $LIGHT_URL,
		private Security $security,
		private FlashMessenger $flashMessenger,
	) {}

	public function edit(
		Request $request,
		PhtmlRenderer $renderer,
		VideoexRepository $videoexRepository,
		ShowexRepository $showexRepository,
		UrlGeneratorInterface $urlGenerator,
		LoggerInterface $logger,
	): Response
	{
		$identity = $this->security->getUser();
		$video_id = (int) $request->attributes->get('id', 0);

		if ($video_id === 0) {
			return new RedirectResponse($urlGenerator->generate('admin_program_videoex_list'));
		}

		try {
			$video = $videoexRepository->findPostBy('id', $video_id);
		} catch (Exception) {
			return new RedirectResponse($urlGenerator->generate('admin_program_videoex_list'));
		}

		if (!$video) {
			return new RedirectResponse($urlGenerator->generate('admin_program_videoex_list'));
		}

		// Pořady
		$shows = $showexRepository->fetchForBootstrapSelect(200);

		if ($request->isMethod('POST')) {
			$post = $request->request->all();

			if (isset($post['cancel'])) {
				return new RedirectResponse($urlGenerator->generate('admin_program_videoex_list'));
			}

			try {
				$data = [
					'title' => trim(strip_tags($post['title'] ?? '')),
					'url' => trim(strip_tags($post['url'] ?? '')),
					'show_id' => !empty($post['show_id']) ? (int) $post['show_id'] : null,
				];

				$videoexRepository->updatePost($video_id, $data);

				$this->flashMessenger->addMessage(
					'success',
					'Úspěšně',
					'Mimoradne video <strong>&quot;' . htmlspecialchars($data['title']) . '&quot;</strong> upraveno'
				);

				// Log
				$logger->notice('PROGRAM - Edit extraordinary video', [
					'description' => 'OK',
					'user' => $identity->getUserIdentifier(),
					'file' => __FILE__,
				]);

				return new RedirectResponse($urlGenerator->generate('admin_program_videoex_list'));
			} catch (Exception $e) {
				// Log
				$logger->error('PROGRAM - Edit extraordinary video', [
					'description' => 'ERROR',
					'user' => $identity->getUserIdentifier(),
					'file' => __FILE__,
					'trace' => $e->getMessage(),
				]);
			}
		}

		return new Response($renderer->renderWithAdminLayout('program/admin/videoex/edit', [
			'pageTitle' => 'Mimořádná videa',
			'video' => $video,
			'shows' => $shows,
			'PUBLIC_PATH' => $this->PUBLIC_PATH,
			'LIGHT_URL' => $this->LIGHT_URL,
		]));
	}

	public function deleteVideo(
		Request $request,
		VideoexRepository $videoexRepository,
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

			$video = $videoexRepository->findPostBy('id', $video_id);

			if ($video) {

				@unlink($this->PUBLIC_PATH . '/data/mimoradne/thumbs/' . $video['name'] . '.jpg');
				@unlink($this->PUBLIC_PATH . '/data/mimoradne/thumbs/' . $video['name'] . '-260x146.jpg');
				@unlink($this->LIGHT_PATH . 'mimoradne/publikovano/' . $video['path'] . '/' . $video['name'] . '_lq.mp4');
				@unlink($this->LIGHT_PATH . 'mimoradne/publikovano/' . $video['path'] . '/' . $video['name'] . '_hq.mp4');

				$programs = $programRepository->findPostsBy('video_id', $video_id);

				if ($programs) {
					foreach ($programs as $program) {
						$programRepository->updatePost($program['id'], ['video_id' => null]);
					}
				}

				$videoexRepository->deletePost($video_id);

				// Log
				$logger->notice('PROGRAM - Delete extraordinary video', [
					'description' => 'OK',
					'user' => $identity->getUserIdentifier(),
					'file' => __FILE__,
				]);
			} else {
				$success = false;
				$message = 'Nelze najít mimořádné video';

				// Log
				$logger->error('PROGRAM - Delete extraordinary video', [
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
			$logger->error('PROGRAM - Delete extraordinary video', [
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
		VideoexRepository $videoexRepository,
		SettingRepository $settingRepository,
		LoggerInterface $logger,
	): Response
	{
		$identity = $this->security->getUser();
		$cron = $request->query->get('cron');
		$message = null;

		if (!$cron) {
			// Zakázání bufferování pro NGINX
			header('X-Accel-Buffering: no');
			// Zakázání bufferování pro APACHE
			header("Content-Encoding: none");

			// JsPush-like progress
			ob_implicit_flush(true);
			if (ob_get_level()) {
				ob_end_flush();
			}
		}

		try {
			$dir = $this->LIGHT_PATH . 'mimoradne/nepublikovano';
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
				if (mb_strtolower(substr($file, -7), 'UTF-8') === '_lq.mp4') {
					$file = mb_substr($file, 0, -7, 'UTF-8');
				}

				if (!in_array($file, $data, true)) {
					$data[] = $file;
				}
			}
			$ok = 0;
			$nok = 0;
			$sec = 10;

			foreach ($data as $i => $iValue) {
				$file = $iValue;

				$lq = false;

				if (in_array($file . '_lq.mp4', $files, true)) {
					$lq = true;
				}

				if (!$cron) {
					$this->pushProgress(
						$i + 1,
						$count,
						'Soubor: ' . $file . ' - ' .
						(($lq === true) ? '<i class="fa fa-fw fa-check-circle text-color-success"></i>' : '<i class="fa fa-fw fa-times-circle text-color-danger"></i>')
					);
				}

				if ($lq === true) {
					$ok++;

					$date = new DateTime();

					$dir = $this->LIGHT_PATH . 'mimoradne/publikovano/' . $date->format('Y') . '/' . $date->format('m') . '/' . $date->format('d') . '/';
					if (!file_exists($dir)) {
						if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
							throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
						}
						chmod($dir, 0777);
					}

					rename($this->LIGHT_PATH . 'mimoradne/nepublikovano/' . $file . '_lq.mp4', $dir . $file . '_lq.mp4');

					$this->createPreview($dir . $file . '_lq.mp4', $sec, $logger, $identity);

					$videoData = [
						'name' => $file,
						'title' => '',
						'url' => '',
						'path' => $date->format('Y') . '/' . $date->format('m') . '/' . $date->format('d'),
						'duration' => gmdate("H:i:s", $this->getDuration($dir . $file . '_lq.mp4')),
						'time' => $date->format('Y-m-d H:i:s'),
						'lenght' => filesize($dir . $file . '_lq.mp4'),
						'size_lq' => filesize($dir . $file . '_lq.mp4'),
						'size_hq' => null,
						'duration_sec' => $this->getDurationFromLight($dir . $file . '_lq.mp4'),
						'showed' => 0,
					];

					$videoexRepository->insertPost($videoData);

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
			$settingRepository->updateSetting(['videoex_update_date' => date('Y-m-d H:i:s')]);

			$success = true;

			// Log
			$logger->notice('PROGRAM - Load extraordinary videos', [
				'description' => 'OK',
				'user' => $identity ? $identity->getUserIdentifier() : 'CRON',
				'file' => __FILE__,
			]);
		} catch (Exception $e) {
			$success = false;
			$message = $e->getMessage();

			if (!$cron) {
				$this->pushProgress(100, 100, '<span class="text-danger">Nelze načíst mimořádná videa</span>');
				$this->pushProgress(100, 100, $e->getMessage());
			}

			$logger->error('PROGRAM - Load extraordinary videos', [
				'description' => 'ERROR',
				'user' => $identity ? $identity->getUserIdentifier() : 'CRON',
				'file' => __FILE__,
				'trace' => $e->getMessage(),
			]);
		}

		if (!$cron) {
			$this->pushFinish();
			return new Response('', 200);
		}

		return new JsonResponse([
			'success' => $success,
			'message' => $message,
		]);
	}

	public function setPart(
		Request $request,
		VideoexRepository $videoexRepository,
		LoggerInterface $logger,
	): JsonResponse
	{
		$identity = $this->security->getUser();
		$success = true;
		$message = null;
		$part_id = null;
		$video_id = null;
		$sec_from = null;
		$sec_to = null;
		$title = null;

		try {
			$params = $request->request->all();
			$part_id = isset($params['part_id']) ? (int) $params['part_id'] : null;
			$video_id = (int) $params['video_id'];
			$sec_from = (int) $params['sec_from'];
			$sec_to = (int) $params['sec_to'];
			$title = $params['title'];

			if ($part_id) {
				$videoexRepository->updatePostPart($part_id, $video_id, $sec_from, $sec_to, $title);
			} else {
				$videoexRepository->insertPostPart($video_id, $sec_from, $sec_to, $title);
			}

			// Log
			$logger->notice('PROGRAM - Add video part', [
				'description' => 'OK',
				'user' => $identity->getUserIdentifier(),
				'file' => __FILE__,
			]);
		} catch (Exception $e) {
			$success = false;
			$message = $e->getMessage();

			// Log
			$logger->error('PROGRAM - Add video part', [
				'description' => 'ERROR',
				'user' => $identity->getUserIdentifier(),
				'file' => __FILE__,
				'trace' => $message,
			]);
		}

		return new JsonResponse([
			'success' => $success,
			'message' => $message,
			'part_id' => $part_id,
			'video_id' => $video_id,
			'sec_from' => $sec_from,
			'sec_to' => $sec_to,
			'title' => $title,
		]);
	}

	public function deleteVideoPart(
		Request $request,
		VideoexRepository $videoexRepository,
		LoggerInterface $logger,
	): JsonResponse
	{
		$identity = $this->security->getUser();
		$success = true;
		$message = null;
		$part_id = null;

		try {
			$params = $request->request->all();
			$part_id = (int) $params['id'];

			$videoexRepository->deletePostPart($part_id);

			// Log
			$logger->notice('PROGRAM - Delete extraordinary video part', [
				'description' => 'OK',
				'user' => $identity->getUserIdentifier(),
				'file' => __FILE__,
			]);

		} catch (Exception $e) {
			$success = false;
			$message = $e->getMessage();

			// Log
			$logger->error('PROGRAM - Delete extraordinary video part', [
				'description' => 'ERROR',
				'user' => $identity->getUserIdentifier(),
				'file' => __FILE__,
				'trace' => $message,
			]);
		}

		return new JsonResponse([
			'success' => $success,
			'message' => $message,
			'part_id' => $part_id,
		]);
	}

	public function createPreviewAction(
		Request $request,
		VideoexRepository $videoexRepository,
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

			$video = $videoexRepository->findPostBy('id', $video_id);

			if ($video) {
				$this->createPreview(
					$this->LIGHT_PATH . 'mimoradne/publikovano/' . $video['path'] . '/' . $video['name'] . '_lq.mp4',
					$sec,
					$logger,
					$identity
				);

				$preview = '/data/mimoradne/thumbs/' . $video['name'] . '.jpg';

				// Log
				$logger->notice('PROGRAM - Edit extraordinary video - Create preview', [
					'description' => 'OK',
					'user' => $identity->getUserIdentifier(),
					'file' => __FILE__,
				]);
			} else {
				$success = false;
				$message = 'Nelze najít mimořádné video';

				// Log
				$logger->error('PROGRAM - Edit extraordinary video - Create preview', [
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
			$logger->error('PROGRAM - Edit extraordinary video - Create preview', [
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

	/**
	 * @param string $file
	 * @param int $sec
	 */
	private function createPreview(string $file, int $sec, LoggerInterface $logger, mixed $identity): void
	{
		$previewDir = 'data/mimoradne/thumbs/';
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
			$logger->notice('PROGRAM - Load extraordinary videos - Create preview', [
				'description' => 'OK',
				'user' => $identity ? $identity->getUserIdentifier() : 'CRON',
				'file' => __FILE__,
			]);
		} catch (Exception $e) {
			$logger->error('PROGRAM - Load extraordinary videos - Create preview', [
				'description' => 'ERROR',
				'user' => $identity ? $identity->getUserIdentifier() : 'CRON',
				'file' => __FILE__,
				'trace' => $e->getMessage(),
			]);
		}
	}

	/**
	 * @param string $video_url
	 * @return int
	 */
	private function getDuration(string $video_url): int
	{
		set_time_limit(3600);
		ob_start();
		passthru("ffmpeg -i " . $video_url . " 2>&1");
		$duration = ob_get_clean();
		$search = '/Duration: (.*?),/';
		preg_match($search, $duration, $matches, PREG_OFFSET_CAPTURE, 3);
		$videoDuration = $matches[1][0];
		$dur = explode(":", $videoDuration);
		return $dur[0] * 3600 + $dur[1] * 60 + (int) $dur[2];
	}

	/**
	 * Zjistí duration jako počet vteřin
	 */
	private function getDurationFromLight(string $file): ?int
	{
		try {
			$command = 'ffprobe -i "' . $file . '" -v quiet -print_format json -show_format -show_streams -hide_banner > temp_file';
			exec($command, $output, $res);
			$info = json_decode(file_get_contents("temp_file"));

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
