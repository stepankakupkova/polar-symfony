<?php
/*
 * @project polar
 * @author Rostislav Greipel
 * @link https://rogr.cz
 * @copyright (c) 2011 - 2021 ROGR All Rights Reserved
 * @license https://rogr.cz/license/eula EULA License
 */

declare(strict_types=1);

namespace App\Camera\Controller\Web;

use App\Camera\Repository\CameraRepository;
use App\Application\View\PhtmlRenderer;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class CameraController
{
	public function __construct(
		private CameraRepository $cameraRepository,
		private UrlGeneratorInterface $urlGenerator,
	) {}

	public function cameras(Request $request, PhtmlRenderer $renderer): Response
	{
		try {
			$cameras = $this->cameraRepository->fetchAll();
		} catch (\Exception) {
			return new RedirectResponse($this->urlGenerator->generate('news'));
		}

		return new Response($renderer->renderWithLayout('camera/web/cameras', [
			'cameras'    => $cameras,
			'currentUrl' => $request->getUri(),
			'schemeHost' => $request->getSchemeAndHttpHost(),
		]));
	}

	public function camera(Request $request, PhtmlRenderer $renderer): Response
	{
		$camera_id = (int) $request->attributes->get('camera_id');

		if (!$camera_id) {
			return new RedirectResponse($this->urlGenerator->generate('camera_list'));
		}

		try {
			$camera = $this->cameraRepository->findPostBy('id', $camera_id);
		} catch (\Exception) {
			return new RedirectResponse($this->urlGenerator->generate('camera_list'));
		}

		return new Response($renderer->renderWithLayout('camera/web/camera', [
			'camera'     => $camera,
			'currentUrl' => $request->getUri(),
			'schemeHost' => $request->getSchemeAndHttpHost(),
		]));
	}
}
