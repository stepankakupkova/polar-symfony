<?php

namespace App\News\Controller\Web;

use Symfony\Component\HttpFoundation\Response;

final class PrController
{
	public function index(): Response
	{
		// TODO: implementovat PR seznam
		return new Response('Stránka se připravuje.', Response::HTTP_NOT_IMPLEMENTED);
	}

	public function detail(): Response
	{
		// TODO: implementovat PR detail
		return new Response('Stránka se připravuje.', Response::HTTP_NOT_IMPLEMENTED);
	}
}
