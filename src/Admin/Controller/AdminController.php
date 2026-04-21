<?php

namespace App\Admin\Controller;

use App\Application\View\PhtmlRenderer;
use App\Security\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class AdminController
{
	public function index(UrlGeneratorInterface $urlGenerator): RedirectResponse
	{
		return new RedirectResponse($urlGenerator->generate('admin_dashboard'));
	}

	public function dashboard(
		PhtmlRenderer $renderer,
		Security $security,
	): Response
	{
		/** @var User $identity */
		return new Response($renderer->renderWithAdminLayout('admin/dashboard', [
			'pageTitle' => 'Dashboard',
		]));
	}
}
