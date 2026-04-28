<?php

declare(strict_types=1);

namespace App\Camera\Controller\Admin;

use App\Application\View\PhtmlRenderer;
use App\Camera\Repository\CameraRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class CameraListController
{
    public function __construct(
        private CameraRepository $cameraRepository,
        private PhtmlRenderer $renderer,
    ) {}

    public function list(): Response
    {
        return new Response($this->renderer->renderWithAdminLayout('camera/admin/list', [
            'pageTitle' => 'Kamery',
        ]));
    }

    public function getList(Request $request): JsonResponse
    {
        $params = $request->query->all();

        $rows  = null;
        $total = 0;

        try {
            $rows  = $this->cameraRepository->fetchForBootstrapTable($params);
            $total = $this->cameraRepository->getCountForBootstrapTable($params);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'rows'    => null,
                'total'   => 0,
            ]);
        }

        return new JsonResponse([
            'success' => true,
            'rows'    => $rows,
            'total'   => $total,
        ]);
    }

    public function getCamera(Request $request): JsonResponse
    {
        $success = true;
        $message = null;
        $camera  = null;

        try {
            $id     = $request->request->getInt('id');
            $camera = $this->cameraRepository->findPostBy('id', $id);
        } catch (\Exception $e) {
            $success = false;
            $message = $e->getMessage();
        }

        return new JsonResponse([
            'success' => $success,
            'message' => $message,
            'camera'  => $camera,
        ]);
    }
}
