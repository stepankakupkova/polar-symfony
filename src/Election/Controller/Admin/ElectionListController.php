<?php
/*
 * @project polar
 * @author Rostislav Greipel
 * @link https://rogr.cz
 * @copyright (c) 2011 - 2021 ROGR All Rights Reserved
 * @license https://rogr.cz/license/eula EULA License
 */

declare(strict_types=1);

namespace App\Election\Controller\Admin;

use App\Application\View\PhtmlRenderer;
use App\Election\Repository\ElectionRepository2025;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ElectionListController
{
    public function __construct(
        private ElectionRepository2025 $electionRepository,
        private PhtmlRenderer $renderer,
    ) {}

    public function index(): Response
    {
        return new Response($this->renderer->renderWithAdminLayout('election/admin/index', [
            'pageTitle'     => 'Elections',
            'countElection' => $this->electionRepository->getCount(),
        ]));
    }

    public function list(Request $request): Response
    {
        // Flash messages zpracovány v šabloně přes session flash bag
        return new Response($this->renderer->renderWithAdminLayout('election/admin/list', [
            'pageTitle' => 'Elections',
        ]));
    }

    public function getList(Request $request): JsonResponse
    {
        $params = $request->query->all();
        $rows   = null;
        $total  = 0;
        $success = true;

        try {
            $rows  = $this->electionRepository->fetchForBootstrapTable($params);
            $total = $this->electionRepository->getCountForBootstrapTable($params);
        } catch (\Exception $e) {
            $success = $e->getMessage();
        }

        return new JsonResponse([
            'success' => $success,
            'rows'    => $rows,
            'total'   => $total,
        ]);
    }

    public function getElection(Request $request): JsonResponse
    {
        $success  = true;
        $message  = null;
        $election = null;

        try {
            $id       = $request->request->getInt('id');
            $election = $this->electionRepository->findPostBy('id', $id);
        } catch (\Exception $e) {
            $success = false;
            $message = $e->getMessage();
        }

        return new JsonResponse([
            'success'  => $success,
            'message'  => $message,
            'election' => $election,
        ]);
    }
}
