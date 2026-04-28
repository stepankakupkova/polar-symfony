<?php

declare(strict_types=1);

namespace App\Banner\Controller\Admin;

use App\Application\Service\FlashMessenger;
use App\Application\Service\Logger;
use App\Application\View\PhtmlRenderer;
use App\Banner\Repository\LeaderboardRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class LeaderboardWriteController
{
    private string $imageDefault = 'data/banner/leaderboard/!default-banner.png';

    public function __construct(
        private FlashMessenger $flashMessenger,
        private Logger $logger,
        private LeaderboardRepository $leaderboardRepository,
        private PhtmlRenderer $renderer,
        private Security $security,
        private UrlGeneratorInterface $urlGenerator,
        private string $PUBLIC_PATH,
    ) {}

    public function add(Request $request): Response
    {
        $identity = $this->security->getUser();

        $date = new \DateTime();
        $post = [
            'active'           => '1',
            'public_from'      => $date->format('d.m.Y'),
            'public_from_time' => $date->format('H:i'),
            'public_to'        => '01.01.2100',
            'public_to_time'   => '0:00',
        ];
        $errors = [];

        if ($request->isMethod('POST')) {
            $post = $request->request->all();

            if (isset($post['cancel'])) {
                return new RedirectResponse($this->urlGenerator->generate('admin_banner_leaderboard_list'));
            }

            $errors = $this->validateForm($post);
            if (empty($errors)) {
                try {
                    // Datepicker formát d.m.Y → Y-m-d pro DB
                    $publicFrom = \DateTime::createFromFormat('d.m.Y', $post['public_from'] ?? '');
                    $publicTo   = \DateTime::createFromFormat('d.m.Y', $post['public_to']   ?? '');

                    $data = [
                        'lang'         => $post['lang']           ?? 'cs_CZ',
                        'active'       => !empty($post['active']) ? 1 : 0,
                        'title'        => $post['title']          ?? '',
                        'link'         => $post['link']           ?? '',
                        'image_alt'    => $post['image_alt']      ?? '',
                        'public_from'  => ($publicFrom ? $publicFrom->format('Y-m-d') : $post['public_from']) . ' ' . ($post['public_from_time'] ?? '00:00') . ':00',
                        'public_to'    => ($publicTo   ? $publicTo->format('Y-m-d')   : $post['public_to'])   . ' ' . ($post['public_to_time']   ?? '00:00') . ':00',
                        'rank'         => $this->leaderboardRepository->getCount() + 1,
                        'created_date' => date('Y-m-d H:i:s'),
                        'created_user' => $identity->getUserIdentifier(),
                        'updated_date' => date('Y-m-d H:i:s'),
                        'updated_user' => $identity->getUserIdentifier(),
                    ];

                    $id = $this->leaderboardRepository->insertPost($data);

                    // Adresář
                    $folder = 'data/banner/leaderboard/' . $id;
                    if (!is_dir($this->PUBLIC_PATH . '/' . $folder)) {
                        if (!mkdir($concurrentDirectory = $this->PUBLIC_PATH . '/' . $folder, 0777, true) && !is_dir($concurrentDirectory)) {
                            throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
                        }
                        chmod($this->PUBLIC_PATH . '/' . $folder, 0777);
                    }

                    // Obrázek
                    $image = $post['image'] ?? null;
                    if ($image === $this->imageDefault) {
                        $image = null;
                    } elseif ($image && str_contains($image, '/tmp/')) {
                        $newImage = $folder . '/' . substr($image, strrpos($image, '/') + 1);
                        rename($this->PUBLIC_PATH . '/' . $image, $this->PUBLIC_PATH . '/' . $newImage);
                        $image = $newImage;
                    }

                    $this->leaderboardRepository->updatePost($id, ['image' => $image]);

                    $this->flashMessenger->addMessage(
                        'success',
                        'Leaderboard',
                        'Banner <strong>"' . htmlspecialchars($post['title'] ?? '') . '"</strong> byl vytvořen'
                    );

                    // Log
                    $this->logger->notice('LEADERBOARD - Add banner', [
                        'description' => 'OK',
                        'user'        => $identity->getUserIdentifier(),
                        'file'        => __FILE__,
                    ]);

                    return new RedirectResponse($this->urlGenerator->generate('admin_banner_leaderboard_list'));
                } catch (\Exception $e) {
                    $errors['general'] = $e->getMessage();

                    // Log
                    $this->logger->err('LEADERBOARD - Add banner', [
                        'description' => 'ERROR',
                        'user'        => $identity->getUserIdentifier(),
                        'file'        => __FILE__,
                        'trace'       => $e->getMessage(),
                    ]);
                }
            }
        }

        return new Response($this->renderer->renderWithAdminLayout('banner/admin/leaderboard/add', [
            'pageTitle'    => 'Leaderboard',
            'post'         => $post,
            'errors'       => $errors,
            'imageDefault' => $this->imageDefault,
        ]));
    }

    public function edit(Request $request, int $id): Response
    {
        $identity = $this->security->getUser();

        try {
            $leaderboard = $this->leaderboardRepository->findPostBy('id', $id);
        } catch (\Exception) {
            return new RedirectResponse($this->urlGenerator->generate('admin_banner_leaderboard_list'));
        }

        $post   = $leaderboard;
        $errors = [];

        if ($request->isMethod('POST')) {
            $post = $request->request->all();

            if (isset($post['cancel'])) {
                return new RedirectResponse($this->urlGenerator->generate('admin_banner_leaderboard_list'));
            }

            $errors = $this->validateForm($post);
            if (empty($errors)) {
                try {
                    // Obrázek
                    $image = $post['image'] ?? $leaderboard['image'];
                    if ($image === $this->imageDefault) {
                        $image = null;
                    }

                    // Datepicker formát d.m.Y → Y-m-d pro DB
                    $publicFrom = \DateTime::createFromFormat('d.m.Y', $post['public_from'] ?? '');
                    $publicTo   = \DateTime::createFromFormat('d.m.Y', $post['public_to']   ?? '');

                    $data = [
                        'lang'         => $post['lang']           ?? 'cs_CZ',
                        'active'       => !empty($post['active']) ? 1 : 0,
                        'title'        => $post['title']          ?? '',
                        'link'         => $post['link']           ?? '',
                        'image_alt'    => $post['image_alt']      ?? '',
                        'public_from'  => ($publicFrom ? $publicFrom->format('Y-m-d') : $post['public_from']) . ' ' . ($post['public_from_time'] ?? '00:00') . ':00',
                        'public_to'    => ($publicTo   ? $publicTo->format('Y-m-d')   : $post['public_to'])   . ' ' . ($post['public_to_time']   ?? '00:00') . ':00',
                        'updated_date' => date('Y-m-d H:i:s'),
                        'updated_user' => $identity->getUserIdentifier(),
                        'image'        => $image,
                    ];

                    $this->leaderboardRepository->updatePost($id, $data);

                    $this->flashMessenger->addMessage(
                        'success',
                        'Leaderboard',
                        'Banner <strong>"' . htmlspecialchars($post['title'] ?? '') . '"</strong> byl upraven'
                    );

                    // Log
                    $this->logger->notice('LEADERBOARD - Edit banner', [
                        'description' => 'OK',
                        'user'        => $identity->getUserIdentifier(),
                        'file'        => __FILE__,
                    ]);

                    return new RedirectResponse($this->urlGenerator->generate('admin_banner_leaderboard_list'));
                } catch (\Exception $e) {
                    $errors['general'] = $e->getMessage();

                    // Log
                    $this->logger->err('LEADERBOARD - Edit banner', [
                        'description' => 'ERROR',
                        'user'        => $identity->getUserIdentifier(),
                        'file'        => __FILE__,
                        'trace'       => $e->getMessage(),
                    ]);
                }
            }
        }

        return new Response($this->renderer->renderWithAdminLayout('banner/admin/leaderboard/edit', [
            'pageTitle'    => 'Leaderboard',
            'id'           => $id,
            'post'         => $post,
            'leaderboard'  => $leaderboard,
            'errors'       => $errors,
            'imageDefault' => $this->imageDefault,
        ]));
    }

    public function deleteBanner(Request $request): JsonResponse
    {
        $success        = true;
        $message        = null;
        $leaderboard_id = null;

        $identity = $this->security->getUser();

        try {
            $leaderboard_id = $request->request->getInt('id');

            $leaderboard = $this->leaderboardRepository->findPostBy('id', $leaderboard_id);

            // Smazat adresář
            $dir = $this->PUBLIC_PATH . '/data/banner/leaderboard/' . $leaderboard['id'] . '/';
            if (is_dir($dir)) {
                $this->deleteDir($dir);
            }

            $this->leaderboardRepository->deletePost($leaderboard_id);

            // Log
            $this->logger->notice('LEADERBOARD - Delete banner', [
                'description' => 'OK',
                'user'        => $identity->getUserIdentifier(),
                'file'        => __FILE__,
            ]);
        } catch (\Exception $e) {
            $success = false;
            $message = $e->getMessage();

            // Log
            $this->logger->err('LEADERBOARD - Delete banner', [
                'description' => 'ERROR',
                'user'        => $identity->getUserIdentifier(),
                'file'        => __FILE__,
                'trace'       => $message,
            ]);
        }

        return new JsonResponse([
            'success'        => $success,
            'message'        => $message,
            'leaderboard_id' => $leaderboard_id,
        ]);
    }

    public function setSort(Request $request): JsonResponse
    {
        $success = true;
        $data    = null;

        try {
            $data = $request->request->all()['data'] ?? [];

            $this->saveBannerSort($data);
        } catch (\Exception $e) {
            $success = $e->getMessage();
        }

        return new JsonResponse([
            'success' => $success,
            'data'    => $data,
        ]);
    }

    public function uploadImage(Request $request): JsonResponse
    {
        $file = $request->files->get('file');

        if (!$file) {
            return new JsonResponse(['error' => 'No files found for upload']);
        }

        $leaderboard_id = $request->request->get('leaderboard_id');

        $folder = 'data/banner/leaderboard/';
        if ($leaderboard_id !== 'null' && $leaderboard_id !== null) {
            $folder .= $leaderboard_id . '/';
        } else {
            $folder .= 'tmp/';
        }

        if (!is_dir($this->PUBLIC_PATH . '/' . $folder)) {
            if (!mkdir($concurrentDirectory = $this->PUBLIC_PATH . '/' . $folder, 0777, true) && !is_dir($concurrentDirectory)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
            }
            chmod($this->PUBLIC_PATH . '/' . $folder, 0777);
        }

        $fileType = strtolower($file->getMimeType());

        try {
            $type = match ($fileType) {
                'image/gif' => 'gif',
                'image/png' => 'png',
                default     => 'jpg',
            };

            $filename      = 'banner-' . date('YmdHis') . '_' . random_int(100, 999);
            $imageFileName = $folder . $filename . '.' . $type;
            $file->move($this->PUBLIC_PATH . '/' . $folder, $filename . '.' . $type);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()]);
        }

        if ($leaderboard_id !== 'null' && $leaderboard_id !== null) {
            $leaderboard = $this->leaderboardRepository->findPostBy('id', $leaderboard_id);

            // Smazat bývalý obrázek
            if (!empty($leaderboard['image']) && $leaderboard['image'] !== $this->imageDefault) {
                @unlink($this->PUBLIC_PATH . '/' . $leaderboard['image']);
            }

            $this->leaderboardRepository->updatePost((int) $leaderboard_id, ['image' => $imageFileName]);
        }

        return new JsonResponse([
            'name' => $file->getClientOriginalName(),
            'url'  => $imageFileName,
            'type' => $fileType,
        ]);
    }

    public function setDefaultImage(Request $request): JsonResponse
    {
        $success        = true;
        $message        = null;
        $leaderboard_id = null;
        $field          = null;

        $identity = $this->security->getUser();

        try {
            $leaderboard_id = $request->request->getInt('leaderboard_id');
            $field          = $request->request->get('field');

            $leaderboard = $this->leaderboardRepository->findPostBy('id', $leaderboard_id);

            switch ($field) {
                case 'image':
                    // Smazat bývalý obrázek
                    if (!empty($leaderboard['image']) && $leaderboard['image'] !== $this->imageDefault) {
                        unlink($this->PUBLIC_PATH . '/' . $leaderboard['image']);
                    }
                    break;
            }

            $this->leaderboardRepository->updatePost($leaderboard_id, [
                'image'        => null,
                'updated_date' => date('Y-m-d H:i:s'),
                'updated_user' => $identity->getUserIdentifier(),
            ]);

            // Log
            $this->logger->notice('LEADERBOARD - Set banner image', [
                'description' => 'OK',
                'user'        => $identity->getUserIdentifier(),
                'file'        => __FILE__,
            ]);
        } catch (\Exception $e) {
            $success = false;
            $message = $e->getMessage();

            // Log
            $this->logger->err('LEADERBOARD - Set banner image', [
                'description' => 'ERROR',
                'user'        => $identity->getUserIdentifier(),
                'file'        => __FILE__,
                'trace'       => $message,
            ]);
        }

        return new JsonResponse([
            'success'        => $success,
            'message'        => $message,
            'leaderboard_id' => $leaderboard_id,
            'field'          => $field,
            'url'            => $this->imageDefault,
        ]);
    }

    private function saveBannerSort(array $data, int $rank = 1): int
    {
        if ($data) {
            $identity = $this->security->getUser();

            foreach ($data as $item) {
                $this->leaderboardRepository->updatePost((int) $item['id'], [
                    'rank'         => $rank,
                    'updated_date' => date('Y-m-d H:i:s'),
                    'updated_user' => $identity->getUserIdentifier(),
                ]);
                ++$rank;
            }
        }
        return $rank;
    }

    private function validateForm(array $post): array
    {
        $errors = [];

        if (empty($post['title'])) {
            $errors['title'] = 'Název je povinný';
        }
        if (empty($post['link'])) {
            $errors['link'] = 'Odkaz je povinný';
        }
        if (empty($post['public_from'])) {
            $errors['public_from'] = 'Datum od je povinné';
        }
        if (empty($post['public_to'])) {
            $errors['public_to'] = 'Datum do je povinné';
        }

        return $errors;
    }

    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . $file;
            is_dir($path) ? $this->deleteDir($path . '/') : unlink($path);
        }
        rmdir($dir);
    }
}
