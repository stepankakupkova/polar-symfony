<?php
/*
 * @project ferritenergy
 * @author Rostislav Greipel
 * @link https://rogr.cz
 * @copyright (c) 2011 - 2021 ROGR All Rights Reserved
 * @license https://rogr.cz/license/eula EULA License
 */

declare(strict_types=1);

namespace App\Banner\Controller\Admin;

use App\Application\View\PhtmlRenderer;
use App\Banner\Repository\Mobilesquare2Repository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class Mobilesquare2ListController
{
    public function __construct(
        private Mobilesquare2Repository $mobilesquare2Repository,
        private PhtmlRenderer $renderer,
    ) {}

    public function list(): Response
    {
        // Flash messages jsou zpracovány automaticky přes PhtmlRenderer
        return new Response($this->renderer->renderWithAdminLayout('banner/admin/mobilesquare2/list', [
            'pageTitle' => 'Mobile Square 2',
        ]));
    }

    public function getList(Request $request): JsonResponse
    {
        $params = $request->query->all();

        try {
            $rows  = $this->mobilesquare2Repository->fetchForBootstrapTable($params);
            $total = $this->mobilesquare2Repository->getCountForBootstrapTable($params);
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
        $mobilesquare2 = null;

        try {
            $id            = $request->request->getInt('id');
            $mobilesquare2 = $this->mobilesquare2Repository->findPostBy('id', $id);
        } catch (\Exception $e) {
            $success = false;
            $message = $e->getMessage();
        }

        return new JsonResponse([
            'success'       => $success,
            'message'       => $message,
            'mobilesquare2' => $mobilesquare2,
        ]);
    }
}
