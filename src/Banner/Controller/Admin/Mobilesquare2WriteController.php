<?php

declare(strict_types=1);

namespace App\Banner\Controller\Admin;

use App\Application\Service\FlashMessenger;
use App\Application\Service\Logger;
use App\Application\View\PhtmlRenderer;
use App\Banner\Repository\Mobilesquare2Repository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class Mobilesquare2WriteController
{
    private string $imageDefault = 'data/banner/mobilesquare2/!default-banner.png';

    public function __construct(
        private FlashMessenger $flashMessenger,
        private Logger $logger,
        private Mobilesquare2Repository $mobilesquare2Repository,
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
                return new RedirectResponse($this->urlGenerator->generate('admin_banner_mobilesquare2_list'));
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
                        'rank'         => $this->mobilesquare2Repository->getCount() + 1,
                        'created_date' => date('Y-m-d H:i:s'),
                        'created_user' => $identity->getUserIdentifier(),
                        'updated_date' => date('Y-m-d H:i:s'),
                        'updated_user' => $identity->getUserIdentifier(),
                    ];

                    $id = $this->mobilesquare2Repository->insertPost($data);

                    // Adresář
                    $folder = 'data/banner/mobilesquare2/' . $id;
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

                    $this->mobilesquare2Repository->updatePost($id, ['image' => $image]);

                    $this->flashMessenger->addMessage(
                        'success',
                        'Mobile Square 2',
                        'Banner <strong>"' . htmlspecialchars($post['title'] ?? '') . '"</strong> byl vytvořen'
                    );

                    // Log
                    $this->logger->notice('MOBILESQUARE2 - Add banner', [
                        'description' => 'OK',
                        'user'        => $identity->getUserIdentifier(),
                        'file'        => __FILE__,
                    ]);

                    return new RedirectResponse($this->urlGenerator->generate('admin_banner_mobilesquare2_list'));
                } catch (\Exception $e) {
                    $errors['general'] = $e->getMessage();

                    // Log
                    $this->logger->err('MOBILESQUARE2 - Add banner', [
                        'description' => 'ERROR',
                        'user'        => $identity->getUserIdentifier(),
                        'file'        => __FILE__,
                        'trace'       => $e->getMessage(),
                    ]);
                }
            }
        }

        return new Response($this->renderer->renderWithAdminLayout('banner/admin/mobilesquare2/add', [
            'pageTitle'    => 'Mobile Square 2',
            'post'         => $post,
            'errors'       => $errors,
            'imageDefault' => $this->imageDefault,
        ]));
    }

    public function edit(Request $request, int $id): Response
    {
        $identity = $this->security->getUser();

        try {
            $mobilesquare2 = $this->mobilesquare2Repository->findPostBy('id', $id);
        } catch (\Exception) {
            return new RedirectResponse($this->urlGenerator->generate('admin_banner_mobilesquare2_list'));
        }

        $post   = $mobilesquare2;
        $errors = [];

        if ($request->isMethod('POST')) {
            $post = $request->request->all();

            if (isset($post['cancel'])) {
                return new RedirectResponse($this->urlGenerator->generate('admin_banner_mobilesquare2_list'));
            }

            $errors = $this->validateForm($post);
            if (empty($errors)) {
                try {
                    // Obrázek
                    $image = $post['image'] ?? $mobilesquare2['image'];
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

                    $this->mobilesquare2Repository->updatePost($id, $data);

                    $this->flashMessenger->addMessage(
                        'success',
                        'Mobile Square 2',
                        'Banner <strong>"' . htmlspecialchars($post['title'] ?? '') . '"</strong> byl upraven'
                    );

                    // Log
                    $this->logger->notice('MOBILESQUARE2 - Edit banner', [
                        'description' => 'OK',
                        'user'        => $identity->getUserIdentifier(),
                        'file'        => __FILE__,
                    ]);

                    return new RedirectResponse($this->urlGenerator->generate('admin_banner_mobilesquare2_list'));
                } catch (\Exception $e) {
                    $errors['general'] = $e->getMessage();

                    // Log
                    $this->logger->err('MOBILESQUARE2 - Edit banner', [
                        'description' => 'ERROR',
                        'user'        => $identity->getUserIdentifier(),
                        'file'        => __FILE__,
                        'trace'       => $e->getMessage(),
                    ]);
                }
            }
        }

        return new Response($this->renderer->renderWithAdminLayout('banner/admin/mobilesquare2/edit', [
            'pageTitle'     => 'Mobile Square 2',
            'id'            => $id,
            'post'          => $post,
            'mobilesquare2' => $mobilesquare2,
            'errors'        => $errors,
            'imageDefault'  => $this->imageDefault,
        ]));
    }

    public function deleteBanner(Request $request): JsonResponse
    {
        $success          = true;
        $message          = null;
        $mobilesquare2_id = null;

        $identity = $this->security->getUser();

        try {
            $mobilesquare2_id = $request->request->getInt('id');

            $mobilesquare2 = $this->mobilesquare2Repository->findPostBy('id', $mobilesquare2_id);

            // Smazat adresář
            $dir = $this->PUBLIC_PATH . '/data/banner/mobilesquare2/' . $mobilesquare2['id'] . '/';
            if (is_dir($dir)) {
                $this->deleteDir($dir);
            }

            $this->mobilesquare2Repository->deletePost($mobilesquare2_id);

            // Log
            $this->logger->notice('MOBILESQUARE2 - Delete banner', [
                'description' => 'OK',
                'user'        => $identity->getUserIdentifier(),
                'file'        => __FILE__,
            ]);
        } catch (\Exception $e) {
            $success = false;
            $message = $e->getMessage();

            // Log
            $this->logger->err('MOBILESQUARE2 - Delete banner', [
                'description' => 'ERROR',
                'user'        => $identity->getUserIdentifier(),
                'file'        => __FILE__,
                'trace'       => $message,
            ]);
        }

        return new JsonResponse([
            'success'          => $success,
            'message'          => $message,
            'mobilesquare2_id' => $mobilesquare2_id,
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

        $mobilesquare2_id = $request->request->get('mobilesquare2_id');

        $folder = 'data/banner/mobilesquare2/';
        if ($mobilesquare2_id !== 'null' && $mobilesquare2_id !== null) {
            $folder .= $mobilesquare2_id . '/';
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

        if ($mobilesquare2_id !== 'null' && $mobilesquare2_id !== null) {
            $mobilesquare2 = $this->mobilesquare2Repository->findPostBy('id', $mobilesquare2_id);

            // Smazat bývalý obrázek
            if (!empty($mobilesquare2['image']) && $mobilesquare2['image'] !== $this->imageDefault) {
                @unlink($this->PUBLIC_PATH . '/' . $mobilesquare2['image']);
            }

            $this->mobilesquare2Repository->updatePost((int) $mobilesquare2_id, ['image' => $imageFileName]);
        }

        return new JsonResponse([
            'name' => $file->getClientOriginalName(),
            'url'  => $imageFileName,
            'type' => $fileType,
        ]);
    }

    public function setDefaultImage(Request $request): JsonResponse
    {
        $success          = true;
        $message          = null;
        $mobilesquare2_id = null;
        $field            = null;

        $identity = $this->security->getUser();

        try {
            $mobilesquare2_id = $request->request->getInt('mobilesquare2_id');
            $field            = $request->request->get('field');

            $mobilesquare2 = $this->mobilesquare2Repository->findPostBy('id', $mobilesquare2_id);

            switch ($field) {
                case 'image':
                    // Smazat bývalý obrázek
                    if (!empty($mobilesquare2['image']) && $mobilesquare2['image'] !== $this->imageDefault) {
                        unlink($this->PUBLIC_PATH . '/' . $mobilesquare2['image']);
                    }
                    break;
            }

            $this->mobilesquare2Repository->updatePost($mobilesquare2_id, [
                'image'        => null,
                'updated_date' => date('Y-m-d H:i:s'),
                'updated_user' => $identity->getUserIdentifier(),
            ]);

            // Log
            $this->logger->notice('MOBILESQUARE2 - Set banner image', [
                'description' => 'OK',
                'user'        => $identity->getUserIdentifier(),
                'file'        => __FILE__,
            ]);
        } catch (\Exception $e) {
            $success = false;
            $message = $e->getMessage();

            // Log
            $this->logger->err('MOBILESQUARE2 - Set banner image', [
                'description' => 'ERROR',
                'user'        => $identity->getUserIdentifier(),
                'file'        => __FILE__,
                'trace'       => $message,
            ]);
        }

        return new JsonResponse([
            'success'          => $success,
            'message'          => $message,
            'mobilesquare2_id' => $mobilesquare2_id,
            'field'            => $field,
            'url'              => $this->imageDefault,
        ]);
    }

    private function saveBannerSort(array $data, int $rank = 1): int
    {
        if ($data) {
            $identity = $this->security->getUser();

            foreach ($data as $item) {
                $this->mobilesquare2Repository->updatePost((int) $item['id'], [
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
