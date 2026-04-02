<?php

namespace App\News\Controller\Web;

use Symfony\Component\HttpFoundation\Response;

final class RegionController
{
	public function index(): Response
	{
		// TODO: implementovat výpis článků pro region
		return new Response('Stránka se připravuje.', Response::HTTP_NOT_IMPLEMENTED);
	}

	public function city(): Response
	{
		// TODO: implementovat výpis článků pro region + město
		return new Response('Stránka se připravuje.', Response::HTTP_NOT_IMPLEMENTED);
	}
}
