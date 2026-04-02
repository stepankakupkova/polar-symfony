<?php

namespace App\Job\Controller\Web;

use Symfony\Component\HttpFoundation\Response;

final class JobController
{
	public function index(): Response
	{
		// TODO: implementovat seznam nabídek práce
		return new Response('Stránka se připravuje.', Response::HTTP_NOT_IMPLEMENTED);
	}

	public function detail(): Response
	{
		// TODO: implementovat detail nabídky práce
		return new Response('Stránka se připravuje.', Response::HTTP_NOT_IMPLEMENTED);
	}
}
