<?php

declare(strict_types=1);

namespace App\Banner\Controller\Admin;

use App\Application\View\PhtmlRenderer;
use App\Banner\Repository\Mobilesquare1Repository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class Mobilesquare1ListController
{
    public function __construct(
        private Mobilesquare1Repository $mobilesquare1Repository,
        private PhtmlRenderer $renderer,
    ) {}

    public function list(): Response
    {
        // Flash messages jsou zpracovány automaticky přes PhtmlRenderer
        return new Response($this->renderer->renderWithAdminLayout('banner/admin/mobilesquare1/list', [
            'pageTitle' => 'Mobile Square 1',
        ]));
    }

    public function getList(Request $request): JsonResponse
    {
        $params = $request->query->all();

        try {
            $rows  = $this->mobilesquare1Repository->fetchForBootstrapTable($params);
            $total = $this->mobilesquare1Repository->getCountForBootstrapTable($params);
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
        $success       = true;
        $message       = null;
        $mobilesquare1 = null;

        try {
            $id            = $request->request->getInt('id');
            $mobilesquare1 = $this->mobilesquare1Repository->findPostBy('id', $id);
        } catch (\Exception $e) {
            $success = false;
            $message = $e->getMessage();
        }

        return new JsonResponse([
            'success'       => $success,
            'message'       => $message,
            'mobilesquare1' => $mobilesquare1,
        ]);
    }
}
