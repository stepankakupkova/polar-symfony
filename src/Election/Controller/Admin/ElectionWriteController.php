<?php
/*
 * @project polar
 * @author Rostislav Greipel
 * @link https://rogr.cz
 * @copyright (c) 2011 - 2021 ROGR All Rights Reserved
 * @license https://rogr.cz/license/eula EULA License
 */

declare(strict_types=1);

namespace App\Election\Controller\Admin;

use App\Application\Service\FlashMessenger;
use App\Application\View\PhtmlRenderer;
use App\Election\Repository\Election2025PlaykitRepository;
use App\Election\Repository\ElectionCommand2025;
use App\Election\Repository\ElectionRepository2025;
use App\Program\Repository\VideoRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ElectionWriteController
{
    public function __construct(
        private ElectionRepository2025 $electionRepository,
        private ElectionCommand2025 $electionCommand,
        private Election2025PlaykitRepository $electionPlaykitRepository,
        private VideoRepository $videoRepository,
        private FlashMessenger $flashMessenger,
        private PhtmlRenderer $renderer,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $logger,
    ) {}

    public function add(Request $request): Response|RedirectResponse
    {
        $title_options = $this->electionPlaykitRepository->fetchPsrklForBootstrapSelect();
        $video_options = $this->videoRepository->fetchForBootstrapSelectByShowId(161);
        $error = null;

        if ($request->isMethod('POST')) {
            $post = $request->request->all();

            if (isset($post['cancel'])) {
                return new RedirectResponse($this->urlGenerator->generate('admin_election_list'));
            }

            try {
                $id = $this->electionCommand->insertPost([
                    'title'       => $post['title'] ?? '',
                    'description' => $post['description'] ?? '',
                    'video_id'    => !empty($post['video_id']) ? (int) $post['video_id'] : null,
                    'rank'        => (int) ($post['rank'] ?? 0),
                ]);

                $this->flashMessenger->addMessage('success', 'Volby', 'Položka přidána');

                // Log
                $this->logger->notice('ELECTION - Add election', [
                    'description' => 'OK',
                    'file' => __FILE__,
                ]);

                return new RedirectResponse($this->urlGenerator->generate('admin_election_list'));
            } catch (\Exception $e) {
                $error = $e->getMessage();

                // Log
                $this->logger->error('ELECTION - Add election', [
                    'description' => 'ERROR',
                    'file' => __FILE__,
                    'trace' => $e->getMessage(),
                ]);
            }
        }

        return new Response($this->renderer->renderWithAdminLayout('election/admin/add', [
            'pageTitle'     => 'Volby',
            'title_options' => $title_options,
            'video_options' => $video_options,
            'error'         => $error,
        ]));
    }

    public function edit(Request $request, int $id): Response|RedirectResponse
    {
        if ($id === 0) {
            return new RedirectResponse($this->urlGenerator->generate('admin_election_add'));
        }

        try {
            $election = $this->electionRepository->findPostBy('id', $id);
        } catch (\Exception) {
            return new RedirectResponse($this->urlGenerator->generate('admin_election_list'));
        }

        $title_options = $this->electionPlaykitRepository->fetchPsrklForBootstrapSelect();
        $video_options = $this->videoRepository->fetchForBootstrapSelectByShowId(161);
        $error = null;

        if ($request->isMethod('POST')) {
            $post = $request->request->all();

            if (isset($post['cancel'])) {
                return new RedirectResponse($this->urlGenerator->generate('admin_election_list'));
            }

            try {
                $this->electionCommand->updatePost([
                    'id'          => $id,
                    'title'       => $post['title'] ?? $election['title'],
                    'description' => $post['description'] ?? $election['description'],
                    'video_id'    => !empty($post['video_id']) ? (int) $post['video_id'] : null,
                    'rank'        => (int) ($post['rank'] ?? $election['rank']),
                ]);

                $this->flashMessenger->addMessage('success', 'Volby',
                    'Položka <strong>"' . htmlspecialchars($election['title']) . '"</strong> upravena');

                // Log
                $this->logger->notice('ELECTION - Edit election', [
                    'description' => 'OK',
                    'file' => __FILE__,
                ]);

                return new RedirectResponse($this->urlGenerator->generate('admin_election_list'));
            } catch (\Exception $e) {
                $error = $e->getMessage();

                // Log
                $this->logger->error('ELECTION - Edit election', [
                    'description' => 'ERROR',
                    'file' => __FILE__,
                    'trace' => $e->getMessage(),
                ]);
            }
        }

        return new Response($this->renderer->renderWithAdminLayout('election/admin/edit', [
            'pageTitle'     => 'Volby',
            'election'      => $election,
            'title_options' => $title_options,
            'video_options' => $video_options,
            'error'         => $error,
        ]));
    }

    public function deleteElection(Request $request): JsonResponse
    {
        $success     = true;
        $message     = null;
        $election_id = null;

        try {
            $election_id = $request->request->getInt('id');
            $election    = $this->electionRepository->findPostBy('id', $election_id);

            if ($election) {
                $this->electionCommand->deletePost($election);

                $elections = $this->electionRepository->fetchAll();
                $rank = 1;
                foreach ($elections as $item) {
                    $this->electionCommand->updatePost(array_merge($item, ['rank' => $rank]));
                    $rank++;
                }

                // Log
                $this->logger->notice('ELECTION - Delete election', [
                    'description' => 'OK',
                    'file' => __FILE__,
                ]);
            } else {
                $success = false;
                $message = 'Cannot find election';

                // Log
                $this->logger->error('ELECTION - Delete election', [
                    'description' => 'ERROR',
                    'file' => __FILE__,
                    'trace' => $message,
                ]);
            }
        } catch (\Exception $e) {
            $success = false;
            $message = $e->getMessage();

            // Log
            $this->logger->error('ELECTION - Delete election', [
                'description' => 'ERROR',
                'file' => __FILE__,
                'trace' => $message,
            ]);
        }

        return new JsonResponse([
            'success'     => $success,
            'message'     => $message,
            'election_id' => $election_id,
        ]);
    }

    public function setOrder(Request $request): JsonResponse
    {
        $data    = $request->request->all('data');
        $success = true;
        $message = null;

        try {
            $rank = 1;
            foreach ($data as $item) {
                $election = $this->electionRepository->findPostBy('id', $item['id']);
                $this->electionCommand->updatePost(array_merge($election, ['rank' => $rank]));
                $rank++;
            }
        } catch (\Exception $e) {
            $success = false;
            $message = $e->getMessage();
        }

        return new JsonResponse([
            'success' => $success,
            'message' => $message,
        ]);
    }
}
