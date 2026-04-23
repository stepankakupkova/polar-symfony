<?php

declare(strict_types=1);

namespace App\Banner\Controller\Web;

use App\Banner\Repository\BannerRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class BannerWriteController
{
    public function __construct(
        private BannerRepository $bannerRepository,
    ) {}

    public function setShowed(Request $request): JsonResponse
    {
        $type = (string) $request->request->get('type', '');
        $id   = (int)    $request->request->get('id', 0);

        if (!$type || !$id) {
            return new JsonResponse(['success' => false]);
        }

        $success = $this->bannerRepository->setShowed($type, $id);

        return new JsonResponse(['success' => $success]);
    }

    public function setClicked(Request $request): JsonResponse
    {
        $type = (string) $request->request->get('type', '');
        $id   = (int)    $request->request->get('id', 0);

        if (!$type || !$id) {
            return new JsonResponse(['success' => false]);
        }

        $success = $this->bannerRepository->setClicked($type, $id);

        return new JsonResponse(['success' => $success]);
    }
}
