<?php
/*
 * @project polar
 * @author Rostislav Greipel
 * @link https://rogr.cz
 * @copyright (c) 2011 - 2021 ROGR All Rights Reserved
 * @license https://rogr.cz/license/eula EULA License
 */

declare(strict_types=1);

namespace App\Page\Controller\Web;

use App\Page\Repository\PageRepository;
use App\Application\View\PhtmlRenderer;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PageController
{
	public function __construct(
		private PageRepository $pageRepository,
		private UrlGeneratorInterface $urlGenerator,
	) {}

	/**
	 * @return Response
	 */
	public function page(Request $request, PhtmlRenderer $renderer, int $page_id): Response
	{
		try {
			$page = $this->pageRepository->findPostBy('id', $page_id);
		} catch (\Exception) {
			return new RedirectResponse($this->urlGenerator->generate('news'));
		}

		if (!$page) {
			return new RedirectResponse($this->urlGenerator->generate('news'));
		}

		return new Response($renderer->renderWithLayout('page/web/page', [
			'page' => $page,
			'schemeHost' => $request->getSchemeAndHttpHost(),
		]));
	}
}
