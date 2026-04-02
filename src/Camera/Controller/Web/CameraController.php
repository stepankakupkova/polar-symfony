<?php

namespace App\Camera\Controller\Web;

use Symfony\Component\HttpFoundation\Response;

final class CameraController
{
	public function index(): Response
	{
		// TODO: implementovat seznam kamer
		return new Response('Stránka se připravuje.', Response::HTTP_NOT_IMPLEMENTED);
	}

	public function detail(): Response
	{
		// TODO: implementovat detail kamery
		return new Response('Stránka se připravuje.', Response::HTTP_NOT_IMPLEMENTED);
	}
}
