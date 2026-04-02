<?php

namespace App\News\Controller\Web;

use Symfony\Component\HttpFoundation\Response;

final class RedactorController
{
	public function index(): Response
	{
		// TODO: implementovat výpis článků redaktora
		return new Response('Stránka se připravuje.', Response::HTTP_NOT_IMPLEMENTED);
	}
}
