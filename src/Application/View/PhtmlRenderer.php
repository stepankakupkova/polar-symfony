<?php

namespace App\Application\View;

use App\Application\Service\FlashMessenger;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PhtmlRenderer
{
	public function __construct(
		private string $templatesDir,
		private string $basePath,
		private UrlGeneratorInterface $urlGenerator,
		private FlashMessenger $flashMessenger,
		private RequestStack $requestStack,
		private Security $security,
		private array $globals = [],
	) {}

	public function addGlobal(string $key, mixed $value): void
	{
		$this->globals[$key] = $value;
	}

	public function render(string $template, array $params = [], ?ViewHelper $sharedView = null): string
	{
		$file = rtrim($this->templatesDir, '/\\') . '/' . ltrim($template, '/\\') . '.phtml';

		if (!is_file($file)) {
			throw new \RuntimeException("Template not found: {$file}");
		}

		$view = $sharedView ?? new ViewHelper($this, $this->urlGenerator, $this->basePath);

		extract($this->globals + $params, EXTR_SKIP);

		ob_start();
		require $file;
		return (string) ob_get_clean();
	}

	public function renderWithLayout(string $template, array $params = [], string $layout = 'application/layout'): string
	{
		$sharedView = new ViewHelper($this, $this->urlGenerator, $this->basePath);
		$content = $this->render($template, $params, $sharedView);
		$layoutParams = ['content' => $content];
		foreach (['bannerLeaderboard', 'bannerMobilesticky', 'robots'] as $key) {
			if (isset($params[$key])) {
				$layoutParams[$key] = $params[$key];
			}
		}
		return $this->render($layout, $layoutParams, $sharedView);
	}

	public function renderWithAdminLayout(string $template, array $params = [], string $layout = 'admin/layout'): string
	{
		$sharedView = new ViewHelper($this, $this->urlGenerator, $this->basePath);

		// Scheme – načti ze session, fallback dark
		$session = $this->requestStack->getSession();
		$params['scheme'] = $params['scheme'] ?? $session->get('scheme', 'dark');

		// Identity – automaticky doplň pokud controller nepředal
		$params['identity'] = $params['identity'] ?? $this->security->getUser();
		$params['schemeOpposite'] = $params['scheme'] === 'dark' ? 'light' : 'dark';

		// Flash messages → PNotify inline scripty (jako polar InlineScriptPlugin)
		foreach ($this->flashMessenger->getMessages() as $type => $flashes) {
			foreach ($flashes as $flash) {
				$sharedView->addInlineScript(
					'var notice = new PNotify({
						title: "' . addslashes($flash['title'] ?? '') . '",
						text: "' . addslashes($flash['text'] ?? '') . '",
						type: "' . $type . '",
						addclass: "click-2-close",
						buttons: {
							sticker: false
						}
					});'
				);
			}
		}

		$content = $this->render($template, $params, $sharedView);
		$layoutParams = ['content' => $content];
		foreach (['identity', 'scheme', 'schemeOpposite', 'pageTitle'] as $key) {
			if (isset($params[$key])) {
				$layoutParams[$key] = $params[$key];
			}
		}
		return $this->render($layout, $layoutParams, $sharedView);
	}
}
