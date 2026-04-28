<?php

declare(strict_types=1);

namespace App\Banner\Controller\Admin;

use App\Application\View\PhtmlRenderer;
use App\Banner\Repository\SquareRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class SquareListController
{
    public function __construct(
        private SquareRepository $squareRepository,
        private PhtmlRenderer $renderer,
    ) {}

    public function list(): Response
    {
        // Flash messages jsou zpracovány automaticky přes PhtmlRenderer
        return new Response($this->renderer->renderWithAdminLayout('banner/admin/square/list', [
            'pageTitle' => 'Square',
        ]));
    }

    public function getList(Request $request): JsonResponse
    {
        $params = $request->query->all();

        try {
            $rows  = $this->squareRepository->fetchForBootstrapTable($params);
            $total = $this->squareRepository->getCountForBootstrapTable($params);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => $e->getMessage(),
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

    public function getBanner(Request $request): JsonResponse
    {
        $success = true;
        $message = null;
        $square  = null;

        try {
            $id     = $request->request->getInt('id');
            $square = $this->squareRepository->findPostBy('id', $id);
        } catch (\Exception $e) {
            $success = false;
            $message = $e->getMessage();
        }

        return new JsonResponse([
            'success' => $success,
            'message' => $message,
            'square'  => $square,
        ]);
    }
}
