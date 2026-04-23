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
use App\Banner\Repository\LeaderboardRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class LeaderboardListController
{
    public function __construct(
        private LeaderboardRepository $leaderboardRepository,
        private PhtmlRenderer $renderer,
    ) {}

    public function index(): Response
    {
        return new Response($this->renderer->renderWithAdminLayout('banner/admin/leaderboard/index', [
            'pageTitle'              => 'Leaderboard',
            'countLeaderboard'       => $this->leaderboardRepository->getCount(),
            'countLeaderboardActive' => $this->leaderboardRepository->getCount(true),
        ]));
    }

    public function list(): Response
    {
        // Flash messages jsou zpracovány automaticky přes PhtmlRenderer
        return new Response($this->renderer->renderWithAdminLayout('banner/admin/leaderboard/list', [
            'pageTitle' => 'Leaderboard',
        ]));
    }

    public function getList(Request $request): JsonResponse
    {
        $params = $request->query->all();

        try {
            $rows  = $this->leaderboardRepository->fetchForBootstrapTable($params);
            $total = $this->leaderboardRepository->getCountForBootstrapTable($params);
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
        $leaderboard = null;

        try {
            $id          = $request->request->getInt('id');
            $leaderboard = $this->leaderboardRepository->findPostBy('id', $id);
        } catch (\Exception $e) {
            $success = false;
            $message = $e->getMessage();
        }

        return new JsonResponse([
            'success'     => $success,
            'message'     => $message,
            'leaderboard' => $leaderboard,
        ]);
    }
}
