<?php

declare(strict_types=1);

namespace App\Camera\Controller\Admin;

use App\Application\Service\FlashMessenger;
use App\Application\Service\Logger;
use App\Application\View\PhtmlRenderer;
use App\Camera\Repository\CameraRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class CameraWriteController
{
    public function __construct(
        private CameraRepository $cameraRepository,
        private FlashMessenger $flashMessenger,
        private Logger $logger,
        private PhtmlRenderer $renderer,
        private Security $security,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function add(Request $request): Response
    {
        $identity = $this->security->getUser();

        $post = [
            'title'       => '',
            'description' => '',
            'url_m3u8'    => '',
            'url_mpd'     => '',
            'rank'        => $this->cameraRepository->getCount() + 1,
        ];
        $errors = [];

        if ($request->isMethod('POST')) {
            $post = $request->request->all();

            if (isset($post['cancel'])) {
                return new RedirectResponse($this->urlGenerator->generate('admin_camera_list'));
            }

            $errors = $this->validateForm($post);
            if (empty($errors)) {
                try {
                    $data = [
                        'title'        => $post['title']       ?? '',
                        'description'  => $post['description'] ?? null,
                        'url_m3u8'     => $post['url_m3u8']    ?? '',
                        'url_mpd'      => $post['url_mpd']     ?? null,
                        'rank'         => $this->cameraRepository->getCount() + 1,
                    ];

                    $this->cameraRepository->insert($data);

                    $this->flashMessenger->addMessage(
                        'success',
                        'Kamery',
                        'Kamera <strong>"' . htmlspecialchars($post['title'] ?? '') . '"</strong> byla vytvořena'
                    );

                    // Log
                    $this->logger->notice('CAMERA - Add camera', [
                        'description' => 'OK',
                        'user'        => $identity->getUserIdentifier(),
                        'file'        => __FILE__,
                    ]);

                    return new RedirectResponse($this->urlGenerator->generate('admin_camera_list'));
                } catch (\Exception $e) {
                    $errors['general'] = $e->getMessage();

                    // Log
                    $this->logger->err('CAMERA - Add camera', [
                        'description' => 'ERROR',
                        'user'        => $identity->getUserIdentifier(),
                        'file'        => __FILE__,
                        'trace'       => $e->getMessage(),
                    ]);
                }
            }
        }

        return new Response($this->renderer->renderWithAdminLayout('camera/admin/add', [
            'pageTitle' => 'Kamery',
            'post'      => $post,
            'errors'    => $errors,
        ]));
    }

    public function edit(Request $request, int $id): Response
    {
        $identity = $this->security->getUser();

        try {
            $camera = $this->cameraRepository->findPostBy('id', $id);
        } catch (\Exception) {
            return new RedirectResponse($this->urlGenerator->generate('admin_camera_list'));
        }

        $post   = $camera;
        $errors = [];

        if ($request->isMethod('POST')) {
            $post = $request->request->all();

            if (isset($post['cancel'])) {
                return new RedirectResponse($this->urlGenerator->generate('admin_camera_list'));
            }

            $errors = $this->validateForm($post);
            if (empty($errors)) {
                try {
                    $data = [
                        'title'        => $post['title']       ?? '',
                        'description'  => $post['description'] ?? null,
                        'url_m3u8'     => $post['url_m3u8']    ?? '',
                        'url_mpd'      => $post['url_mpd']     ?? null,
                    ];

                    $this->cameraRepository->update($data, $id);

                    $this->flashMessenger->addMessage(
                        'success',
                        'Kamery',
                        'Kamera <strong>"' . htmlspecialchars($post['title'] ?? '') . '"</strong> byla upravena'
                    );

                    // Log
                    $this->logger->notice('CAMERA - Edit camera', [
                        'description' => 'OK',
                        'user'        => $identity->getUserIdentifier(),
                        'file'        => __FILE__,
                    ]);

                    return new RedirectResponse($this->urlGenerator->generate('admin_camera_list'));
                } catch (\Exception $e) {
                    $errors['general'] = $e->getMessage();

                    // Log
                    $this->logger->err('CAMERA - Edit camera', [
                        'description' => 'ERROR',
                        'user'        => $identity->getUserIdentifier(),
                        'file'        => __FILE__,
                        'trace'       => $e->getMessage(),
                    ]);
                }
            }
        }

        return new Response($this->renderer->renderWithAdminLayout('camera/admin/edit', [
            'pageTitle' => 'Kamery',
            'id'        => $id,
            'post'      => $post,
            'camera'    => $camera,
            'errors'    => $errors,
        ]));
    }

    public function deleteCamera(Request $request): JsonResponse
    {
        $success   = true;
        $message   = null;
        $camera_id = null;

        $identity = $this->security->getUser();

        try {
            $camera_id = $request->request->getInt('id');

            $camera = $this->cameraRepository->findPostBy('id', $camera_id);

            if ($camera) {
                $this->cameraRepository->delete($camera_id);

                // Přeřazení ranků
                $cameras = $this->cameraRepository->fetchAll();
                $rank = 1;
                foreach ($cameras as $row) {
                    $this->cameraRepository->update(['rank' => $rank], (int) $row['id']);
                    $rank++;
                }

                // Log
                $this->logger->notice('CAMERA - Delete camera', [
                    'description' => 'OK',
                    'user'        => $identity->getUserIdentifier(),
                    'file'        => __FILE__,
                ]);
            } else {
                $success = false;
                $message = 'Kamera nebyla nalezena';

                // Log
                $this->logger->err('CAMERA - Delete camera', [
                    'description' => 'ERROR',
                    'user'        => $identity->getUserIdentifier(),
                    'file'        => __FILE__,
                    'trace'       => $message,
                ]);
            }
        } catch (\Exception $e) {
            $success = false;
            $message = $e->getMessage();

            // Log
            $this->logger->err('CAMERA - Delete camera', [
                'description' => 'ERROR',
                'user'        => $identity->getUserIdentifier(),
                'file'        => __FILE__,
                'trace'       => $message,
            ]);
        }

        return new JsonResponse([
            'success'   => $success,
            'message'   => $message,
            'camera_id' => $camera_id,
        ]);
    }

    public function setOrder(Request $request): JsonResponse
    {
        $success = true;
        $message = null;

        try {
            $data = $request->request->all()['data'] ?? [];

            $rank = 1;
            foreach ($data as $item) {
                $this->cameraRepository->update(['rank' => $rank], (int) $item['id']);
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

    private function validateForm(array $post): array
    {
        $errors = [];

        if (empty($post['title'])) {
            $errors['title'] = 'Titulek je povinný';
        } elseif (mb_strlen($post['title']) > 254) {
            $errors['title'] = 'Titulek může mít nejvýše 254 znaků';
        }

        if (!empty($post['description']) && mb_strlen($post['description']) > 1000) {
            $errors['description'] = 'Popis může mít nejvýše 1000 znaků';
        }

        if (empty($post['url_m3u8'])) {
            $errors['url_m3u8'] = 'URL M3U8 je povinná';
        } elseif (mb_strlen($post['url_m3u8']) > 254) {
            $errors['url_m3u8'] = 'URL M3U8 může mít nejvýše 254 znaků';
        }

        if (!empty($post['url_mpd']) && mb_strlen($post['url_mpd']) > 254) {
            $errors['url_mpd'] = 'URL MPD může mít nejvýše 254 znaků';
        }

        return $errors;
    }
}
