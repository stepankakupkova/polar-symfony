<?php

namespace App\Program\Controller\Web;

use Symfony\Component\HttpFoundation\Response;

// TODO: Migrovat modul pořadů (Program/Show) z Laminas
class ShowController
{
	public function index(): Response
	{
		return new Response('TODO: Pořady', 200);
	}
}
