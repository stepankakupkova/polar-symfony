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
use App\Program\Repository\VideoRepository;
use App\Program\Xml\SimpleXMLElementExtended;
use DateTime;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ProgramWriteController
{
	public function __construct(
		private string $PUBLIC_PATH,
		private string $LIGHT_URL,
		private Security $security,
		private FlashMessenger $flashMessenger,
	) {}

	public function add(
		Request $request,
		PhtmlRenderer $renderer,
		ProgramRepository $programRepository,
		VideoRepository $videoRepository,
		ShowRepository $showRepository,
		LoggerInterface $logger,
		UrlGeneratorInterface $urlGenerator,
	): Response
	{
		$identity = $this->security->getUser();
		// Videa
		$videoOptions = $videoRepository->fetchForBootstrapSelect(200);

		// Pořady
		$showOptions = $showRepository->fetchForBootstrapSelect(200);

		// Výchozí hodnoty formuláře
		$form = [
			'id' => '',
			'premiere' => '1',
			'date' => date('d.m.Y'),
			'time' => date('H:i'),
			'title' => '',
			'url' => '',
			'file' => '',
			'video_id' => '',
			'show_id' => '',
			'short_description' => '',
			'description' => '',
			'overwrite' => '',
		];

		$errors = [];

		if ($request->isMethod('POST')) {
			$post = $request->request->all();

			if (isset($post['cancel'])) {
				return new RedirectResponse($urlGenerator->generate('admin_program_list'));
			}

			$form = array_merge($form, $post);

			// Validace
			if (empty($post['title'])) {
				$errors['title'] = 'Vyplňte název';
			}
			if (empty($post['url'])) {
				$errors['url'] = 'Vyplňte URL adresu';
			}

			if (empty($errors)) {
				try {
					$time = new DateTime($post['date'] . ' ' . $post['time']);

					$programData = [
						'premiere' => isset($post['premiere']) ? 1 : 0,
						'time' => $time->format('Y-m-d H:i:s'),
						'title' => $post['title'],
						'url' => $post['url'],
						'file' => $post['file'] ?? null,
						'video_id' => !empty($post['video_id']) ? (int) $post['video_id'] : null,
						'short_description' => $post['short_description'] ?? null,
						'description' => $post['description'] ?? null,
						'overwrite' => $post['overwrite'] ?? null,
					];

					$programId = $programRepository->insertPost($programData);

					if (!empty($post['show_id'])) {
						$programRepository->insertProgram2Shows($programId, (int) $post['show_id']);
					}

					$this->flashMessenger->addMessage(
						'success',
						'Úspěšné',
						'Program <strong>&quot;' . htmlspecialchars($post['title']) . '&quot;</strong> vytvořen'
					);

					// Log
					$logger->notice('PROGRAM - Add program', [
						'description' => 'OK',
						'user' => $identity->getUserIdentifier(),
						'file' => __FILE__,
					]);

					return new RedirectResponse($urlGenerator->generate('admin_program_list'));
				} catch (Exception $e) {
					$errors['_global'] = $e->getMessage();

					// Log
					$logger->error('PROGRAM - Add program', [
						'description' => 'ERROR',
						'user' => $identity->getUserIdentifier(),
						'file' => __FILE__,
						'trace' => $e->getMessage(),
					]);
				}
			}
		}

		return new Response($renderer->renderWithAdminLayout('program/program/add', [
			'pageTitle' => 'Program',
			'form' => $form,
			'errors' => $errors,
			'videoOptions' => $videoOptions,
			'showOptions' => $showOptions,
		]));
	}

	public function edit(
		Request $request,
		PhtmlRenderer $renderer,
		ProgramRepository $programRepository,
		VideoRepository $videoRepository,
		ShowRepository $showRepository,
		LoggerInterface $logger,
		UrlGeneratorInterface $urlGenerator,
	): Response
	{
		$identity = $this->security->getUser();
		$program_id = (int) $request->attributes->get('id', 0);

		if ($program_id === 0) {
			return new RedirectResponse($urlGenerator->generate('admin_program_add'));
		}

		try {
			$program = $programRepository->findPostBy('id', $program_id);
		} catch (Exception) {
			return new RedirectResponse($urlGenerator->generate('admin_program_list'));
		}

		if (!$program) {
			return new RedirectResponse($urlGenerator->generate('admin_program_list'));
		}

		$show = null;
		try {
			$show = $showRepository->findPostByProgram($program_id);
		} catch (Exception) {
			// ok
		}

		// Videa
		$videoOptions = $videoRepository->fetchForBootstrapSelect(200);

		// Pořady
		$showOptions = $showRepository->fetchForBootstrapSelect(200);

		// Formulář z DB dat
		$date = new DateTime($program['time']);
		$form = [
			'id' => $program['id'],
			'premiere' => $program['premiere'] ? '1' : '0',
			'date' => $date->format('d.m.Y'),
			'time' => $date->format('H:i'),
			'title' => $program['title'],
			'url' => $program['url'],
			'file' => $program['file'] ?? '',
			'video_id' => $program['video_id'] ?? '',
			'show_id' => $show ? $show['id'] : '',
			'short_description' => $program['short_description'] ?? '',
			'description' => $program['description'] ?? '',
			'overwrite' => $program['overwrite'] ?? '',
		];

		$errors = [];

		if ($request->isMethod('POST')) {
			$post = $request->request->all();

			if (isset($post['cancel'])) {
				return new RedirectResponse($urlGenerator->generate('admin_program_list'));
			}

			$form = array_merge($form, $post);

			// Validace
			if (empty($post['title'])) {
				$errors['title'] = 'Vyplňte název';
			}
			if (empty($post['url'])) {
				$errors['url'] = 'Vyplňte URL adresu';
			}

			if (empty($errors)) {
				try {
					$time = new DateTime($post['date'] . ' ' . $post['time']);

					$programData = [
						'premiere' => isset($post['premiere']) ? 1 : 0,
						'time' => $time->format('Y-m-d H:i:s'),
						'title' => $post['title'],
						'url' => $post['url'],
						'file' => $post['file'] ?? null,
						'video_id' => !empty($post['video_id']) ? (int) $post['video_id'] : null,
						'short_description' => $post['short_description'] ?? null,
						'description' => $post['description'] ?? null,
						'overwrite' => $post['overwrite'] ?? null,
					];

					// Smazání starých vazeb + vložení nových
					$programRepository->deleteProgram2ShowsByProgram($program_id);
					if (!empty($post['show_id'])) {
						$programRepository->insertProgram2Shows($program_id, (int) $post['show_id']);
					}

					$programRepository->updatePost($program_id, $programData);

					$this->flashMessenger->addMessage(
						'success',
						'Úspěšné',
						'Program <strong>&quot;' . htmlspecialchars($post['title']) . '&quot;</strong> upraven'
					);

					// Log
					$logger->notice('PROGRAM - Edit program', [
						'description' => 'OK',
						'user' => $identity->getUserIdentifier(),
						'file' => __FILE__,
					]);

					return new RedirectResponse($urlGenerator->generate('admin_program_list'));
				} catch (Exception $e) {
					$errors['_global'] = $e->getMessage();

					// Log
					$logger->error('PROGRAM - Edit program', [
						'description' => 'ERROR',
						'user' => $identity->getUserIdentifier(),
						'file' => __FILE__,
						'trace' => $e->getMessage(),
					]);
				}
			}
		}

		return new Response($renderer->renderWithAdminLayout('program/program/edit', [
			'pageTitle' => 'Program',
			'form' => $form,
			'errors' => $errors,
			'program' => $program,
			'videoOptions' => $videoOptions,
			'showOptions' => $showOptions,
		]));
	}

	public function deleteProgram(
		Request $request,
		ProgramRepository $programRepository,
		LoggerInterface $logger,
	): JsonResponse
	{
		$identity = $this->security->getUser();
		$success = true;
		$message = null;
		$program_id = null;

		try {
			$params = $request->request->all();
			$program_id = $params['id'];

			$program = $programRepository->findPostBy('id', $program_id);

			if ($program) {
				$programRepository->deleteProgram2ShowsByProgram($program_id);
				$programRepository->deletePost($program_id);

				// Log
				$logger->notice('PROGRAM - Delete program', [
					'description' => 'OK',
					'user' => $identity->getUserIdentifier(),
					'file' => __FILE__,
				]);
			} else {
				$success = false;
				$message = 'Nelze najít program';

				// Log
				$logger->error('PROGRAM - Delete program', [
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
			$logger->error('PROGRAM - Delete program', [
				'description' => 'ERROR',
				'user' => $identity->getUserIdentifier(),
				'file' => __FILE__,
				'trace' => $message,
			]);
		}

		return new JsonResponse([
			'success' => $success,
			'message' => $message,
			'program_id' => $program_id,
		]);
	}

	public function newton(
		PhtmlRenderer $renderer,
		SettingRepository $settingRepository,
	): Response
	{
		$setting = $settingRepository->fetchSetting();

		return new Response($renderer->renderWithAdminLayout('program/program/newton', [
			'pageTitle' => 'Newton',
			'setting' => $setting,
		]));
	}

	public function exportShows(
		Request $request,
		ProgramRepository $programRepository,
		SettingRepository $settingRepository,
		LoggerInterface $logger,
	): Response|JsonResponse
	{
		$cron = $request->query->get('cron');
		$message = null;

		if (!$cron) {
			// Zakázání bufferování pro NGINX
			header('X-Accel-Buffering: no');
			// Zakázání bufferování pro APACHE
			header("Content-Encoding: none");
			ob_implicit_flush(true);
			if (ob_get_level()) {
				ob_end_flush();
			}
		}

		/** @var mixed $identity */
		$identity = $this->security->getUser();

		try {
			$dir = $this->PUBLIC_PATH . '/data/program/export/shows';

			$shows = $programRepository->getPremieresForExportNewton();
			$count = count($shows);

			if (!$cron) {
				$this->pushProgress(0, $count + 2, 'Exportuji...');
			}

			$xml = new SimpleXMLElementExtended('<?xml version="1.0" encoding="UTF-8"?><PolarTV></PolarTV>');

			foreach ($shows as $i => $iValue) {
				if (!$cron) {
					$this->pushProgress($i + 1, $count + 2, $iValue['title']);
				}
				$show = $xml->addChild('show');
				$show->addChild('id',  $iValue['id']);
				$show->addChild('time',  $iValue['time']);
				if ($iValue['video_path'] && $iValue['video_name']) {
					$show->addChild('video', $this->LIGHT_URL . 'porady/publikovano/' . $iValue['video_path'] . '/' . $iValue['video_name'] . '_lq.mp4');
				} else if ($iValue['video_path2'] && $iValue['video_name2']) {
					$show->addChild('video', $this->LIGHT_URL . 'porady/publikovano/' . $iValue['video_path2'] . '/' . $iValue['video_name2'] . '_lq.mp4');
				}
				$show->addChild('url', 'https://' . $_SERVER['SERVER_NAME'] . '/porady/' . $iValue['show_url'] . '/' . $iValue['url']);
				$show->addChildWithCDATA('title', $iValue['title']);
				if ($iValue['short_description']) {
					$show->addChildWithCDATA('short_description', $iValue['short_description']);
				} else {
					$show->addChild('short_description');
				}
				if ($iValue['description']) {
					$show->addChildWithCDATA('description', $iValue['description']);
				} else {
					$show->addChild('description');
				}
				if ($iValue['overwrite']) {
					$show->addChildWithCDATA('overwrite', $iValue['overwrite']);
				} else {
					$show->addChild('overwrite');
				}
			}

			$xml->asXML($dir . '/polar.xml');

			if (!$cron) {
				$this->pushProgress($count, $count + 2, 'Dokončeno');
			}

			// Aktualizace datumu posledního exportu
			$settingRepository->updateSetting(['newton_update_date' => date('Y-m-d H:i:s')]);

			$success = true;

			// Log
			$logger->notice('PROGRAM - Newton - Export shows', [
				'description' => 'OK',
				'user' => $identity ? $identity->getUserIdentifier() : 'CRON',
				'file' => __FILE__,
			]);
		} catch (Exception $e) {
			$success = false;
			$message = $e->getMessage();

			if (!$cron) {
				$this->pushProgress(100, 100, '<span class="text-danger">Nelze exportovat pořady</span>');
				$this->pushProgress(100, 100, $e->getMessage());
			}

			$logger->error('PROGRAM - Newton - Export shows', [
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
