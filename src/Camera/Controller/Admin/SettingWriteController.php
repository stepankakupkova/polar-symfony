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

use App\Application\Service\FlashMessenger;
use App\Application\Service\Logger;
use App\Application\View\PhtmlRenderer;
use App\Camera\Repository\SettingRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class SettingWriteController
{
    public function __construct(
        private FlashMessenger $flashMessenger,
        private Logger $logger,
        private PhtmlRenderer $renderer,
        private Security $security,
        private SettingRepository $settingRepository,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function setting(Request $request): Response
    {
        $identity = $this->security->getUser();

        //$setting = $this->settingRepository->fetchSetting();

        return new Response($this->renderer->renderWithAdminLayout('camera/admin/setting/setting', [
            'pageTitle' => 'Kamery — Nastavení',
            //'setting'  => $setting,
        ]));
    }
}
