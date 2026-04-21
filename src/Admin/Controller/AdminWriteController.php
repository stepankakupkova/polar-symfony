<?php
/*
 * @project polar
 * @author Rostislav Greipel
 * @link https://rogr.cz
 * @copyright (c) 2011 - 2021 ROGR All Rights Reserved
 * @license https://rogr.cz/license/eula EULA License
 */

declare(strict_types=1);

namespace App\Admin\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class AdminWriteController
{
    public function setScheme(Request $request): JsonResponse
    {
        $scheme = $request->request->get('scheme');

        // Uložení do Session
        $request->getSession()->set('scheme', $scheme);

        return new JsonResponse([
            'success' => true,
            'scheme' => $scheme,
        ]);
    }
}
