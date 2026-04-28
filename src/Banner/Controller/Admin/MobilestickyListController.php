<?php

declare(strict_types=1);

namespace App\Banner\Controller\Admin;

use App\Application\View\PhtmlRenderer;
use App\Banner\Repository\MobilestickyRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class MobilestickyListController
{
    public function __construct(
        private MobilestickyRepository $mobilestickyRepository,
        private PhtmlRenderer $renderer,
    ) {}

    public function list(): Response
    {
        // Flash messages jsou zpracovány automaticky přes PhtmlRenderer
        return new Response($this->renderer->renderWithAdminLayout('banner/admin/mobilesticky/list', [
            'pageTitle' => 'Mobile Sticky',
        ]));
    }

    public function getList(Request $request): JsonResponse
    {
        $params = $request->query->all();

        try {
            $rows  = $this->mobilestickyRepository->fetchForBootstrapTable($params);
            $total = $this->mobilestickyRepository->getCountForBootstrapTable($params);
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
        $success     = true;
        $message     = null;
        $mobilesticky = null;

        try {
            $id           = $request->request->getInt('id');
            $mobilesticky = $this->mobilestickyRepository->findPostBy('id', $id);
        } catch (\Exception $e) {
            $success = false;
            $message = $e->getMessage();
        }

        return new JsonResponse([
            'success'     => $success,
            'message'     => $message,
            'mobilesticky' => $mobilesticky,
        ]);
    }
}
