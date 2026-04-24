<?php
/*
 * @project polar
 * @author Rostislav Greipel
 * @link https://rogr.cz
 * @copyright (c) 2011 - 2021 ROGR All Rights Reserved
 * @license https://rogr.cz/license/eula EULA License
 */

declare(strict_types=1);

namespace App\Camera\Controller\Admin;

use App\Application\View\PhtmlRenderer;
use Symfony\Component\HttpFoundation\Response;

final class SettingListController
{
    public function __construct(
        private PhtmlRenderer $renderer,
    ) {}

    public function index(): Response
    {
        return new Response($this->renderer->renderWithAdminLayout('camera/admin/setting/index', [
            'pageTitle' => 'Kamery — Nastavení',
        ]));
    }
}
