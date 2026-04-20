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
	) {}

	public function add(
		Request $request,
		PhtmlRenderer $renderer,
		FlashMessenger $flashMessenger,
		ProgramRepository $programRepository,
		VideoRepository $videoRepository,
		ShowRepository $showRepository,
		LoggerInterface $logger,
		Security $security,
		UrlGeneratorInterface $urlGenerator,
	): Response
	{
		$identity = $security->getUser();

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

					$flashMessenger->addMessage(
						'success',
						'Úspěšné',
						'Program <strong>&quot;' . htmlspecialchars($post['title']) . '&quot;</strong> vytvořen'
					);

					// Log
					$logger->notice('PROGRAM - Add program', [
						'description' => 'OK',
						'user' => $identity?->getUserIdentifier(),
						'file' => __FILE__,
					]);

					return new RedirectResponse($urlGenerator->generate('admin_program_list'));
				} catch (Exception $e) {
					$errors['_global'] = $e->getMessage();

					// Log
					$logger->error('PROGRAM - Add program', [
						'description' => 'ERROR',
						'user' => $identity?->getUserIdentifier(),
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
		FlashMessenger $flashMessenger,
		ProgramRepository $programRepository,
		VideoRepository $videoRepository,
		ShowRepository $showRepository,
		LoggerInterface $logger,
		Security $security,
		UrlGeneratorInterface $urlGenerator,
	): Response
	{
		$identity = $security->getUser();
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

					$flashMessenger->addMessage(
						'success',
						'Úspěšné',
						'Program <strong>&quot;' . htmlspecialchars($post['title']) . '&quot;</strong> upraven'
					);

					// Log
					$logger->notice('PROGRAM - Edit program', [
						'description' => 'OK',
						'user' => $identity?->getUserIdentifier(),
						'file' => __FILE__,
					]);

					return new RedirectResponse($urlGenerator->generate('admin_program_list'));
				} catch (Exception $e) {
					$errors['_global'] = $e->getMessage();

					// Log
					$logger->error('PROGRAM - Edit program', [
						'description' => 'ERROR',
						'user' => $identity?->getUserIdentifier(),
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
		Security $security,
	): JsonResponse
	{
		$success = true;
		$message = null;
		$program_id = null;

		$identity = $security->getUser();

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
					'user' => $identity?->getUserIdentifier(),
					'file' => __FILE__,
				]);
			} else {
				$success = false;
				$message = 'Nelze najít program';

				// Log
				$logger->error('PROGRAM - Delete program', [
					'description' => 'ERROR',
					'user' => $identity?->getUserIdentifier(),
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
				'user' => $identity?->getUserIdentifier(),
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
		Security $security,
	): Response
	{
		$setting = $settingRepository->fetchSetting();

		return new Response($renderer->renderWithAdminLayout('program/program/newton', [
			'pageTitle' => 'Program',
			'setting' => $setting,
			'identity' => $security->getUser(),
		]));
	}

	public function exportShows(
		Request $request,
		ProgramRepository $programRepository,
		SettingRepository $settingRepository,
		LoggerInterface $logger,
		Security $security,
	): JsonResponse
	{
		$identity = $security->getUser();
		$success = false;
		$message = null;

		try {
			$dir = $this->PUBLIC_PATH . '/data/program/export/shows';

			$shows = $programRepository->getPremieresForExportNewton();

			$xml = new SimpleXMLElementExtended('<?xml version="1.0" encoding="UTF-8"?><PolarTV></PolarTV>');

			foreach ($shows as $iValue) {
				$show = $xml->addChild('show');
				$show->addChild('id', $iValue['id']);
				$show->addChild('time', $iValue['time']);
				if ($iValue['video_path'] && $iValue['video_name']) {
					$show->addChild('video', $this->LIGHT_URL . 'porady/publikovano/' . $iValue['video_path'] . '/' . $iValue['video_name'] . '_lq.mp4');
				} else if ($iValue['video_path2'] && $iValue['video_name2']) {
					$show->addChild('video', $this->LIGHT_URL . 'porady/publikovano/' . $iValue['video_path2'] . '/' . $iValue['video_name2'] . '_lq.mp4');
				}
				$show->addChild('url', 'https://' . ($request->getHost()) . '/porady/' . $iValue['show_url'] . '/' . $iValue['url']);
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

			// Aktualizace datumu posledního exportu
			$settingRepository->updateSetting(['newton_update_date' => date('Y-m-d H:i:s')]);

			$success = true;

			// Log
			$logger->notice('PROGRAM - Newton - Export shows', [
				'description' => 'OK',
				'user' => $identity?->getUserIdentifier(),
				'file' => __FILE__,
			]);
		} catch (Exception $e) {
			$success = false;
			$message = $e->getMessage();

			$logger->error('PROGRAM - Newton - Export shows', [
				'description' => 'ERROR',
				'user' => $identity?->getUserIdentifier(),
				'file' => __FILE__,
				'trace' => $e->getMessage(),
			]);
		}

		return new JsonResponse([
			'success' => $success,
			'message' => $message,
		]);
	}
}
