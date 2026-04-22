<?php

namespace App\Job\Controller\Admin;

use App\Application\View\PhtmlRenderer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class SettingController
{
	public function index(PhtmlRenderer $renderer): Response
	{
		return new Response($renderer->renderWithAdminLayout('job/admin/setting/index', [
			'pageTitle' => 'Nastavení | Práce',
		]));
	}

	public function setting(Request $request, PhtmlRenderer $renderer): Response
	{
		return new Response($renderer->renderWithAdminLayout('job/admin/setting/setting', [
			'pageTitle' => 'Nastavení | Práce',
		]));
	}
}
