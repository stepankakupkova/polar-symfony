<?php

namespace App\News\Controller\Web;

use Symfony\Component\HttpFoundation\Response;

final class NewsDetailController
{
	public function index(): Response
	{
		// TODO: implementovat detail článku
		return new Response('Stránka se připravuje.', Response::HTTP_NOT_IMPLEMENTED);
	}
}
