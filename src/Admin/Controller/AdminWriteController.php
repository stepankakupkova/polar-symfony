<?php

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
