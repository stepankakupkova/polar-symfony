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
use App\Election\Repository\ElectionSettingCommand;
use App\Election\Repository\ElectionSettingRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class SettingWriteController
{
    public function __construct(
        private ElectionSettingRepository $settingRepository,
        private ElectionSettingCommand $settingCommand,
        private PhtmlRenderer $renderer,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function setting(Request $request): Response|RedirectResponse
    {
        try {
            //$setting = $this->settingRepository->fetchSetting();
        } catch (\Exception) {
            return new RedirectResponse($this->urlGenerator->generate('admin'));
        }

        //$identity = ...

        return new Response($this->renderer->renderWithAdminLayout('election/admin/setting', [
            'pageTitle' => 'Setting | Elections',
        ]));
    }

    public function jsonList(Request $request, string $action): JsonResponse
    {
        return new JsonResponse(['success' => true]);
    }

    public function jsonWrite(Request $request, string $action): JsonResponse
    {
        return new JsonResponse(['success' => true]);
    }
}
