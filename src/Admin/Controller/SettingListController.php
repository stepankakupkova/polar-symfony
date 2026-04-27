<?php

namespace App\Admin\Controller;

use App\Application\View\PhtmlRenderer;
use Symfony\Component\HttpFoundation\Response;

final class SettingListController
{
	public function phpInfo(PhtmlRenderer $renderer): Response
	{
		return new Response($renderer->renderWithAdminLayout('admin/setting/php-info', [
			'pageTitle' => 'PHP Info',
		]));
	}
}
