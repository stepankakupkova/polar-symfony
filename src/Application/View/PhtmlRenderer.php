<?php

namespace App\Application\View;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PhtmlRenderer
{
	public function __construct(
		private string $templatesDir,
		private string $basePath,
		private UrlGeneratorInterface $urlGenerator,
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

	public function renderWithLayout(string $template, array $params = [], string $layout = 'layout'): string
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
}
