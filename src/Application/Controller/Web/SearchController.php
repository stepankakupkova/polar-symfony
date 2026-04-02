<?php

namespace App\Application\Controller\Web;

use App\View\PhtmlRenderer;
use Symfony\Component\HttpFoundation\Response;

final class SearchController
{
	public function index(PhtmlRenderer $renderer): Response
	{
		return new Response($renderer->renderWithLayout('application/web/search'));
	}
}
