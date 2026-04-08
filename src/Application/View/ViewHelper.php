<?php

namespace App\Application\View;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ViewHelper
{
	private string $title = '';
	private array $metas = [];
	private array $ogMetas = [];
	private array $breadcrumbs = [];
	private array $inlineScripts = [];
	private array $headStyles = [];
	private array $headLinks = [];
	private array $deferredScripts = [];

	public function __construct(
		private PhtmlRenderer $renderer,
		private UrlGeneratorInterface $urlGenerator,
		private TranslatorInterface $translator,
		private string $basePath,
	) {}

	public function path(string $route, array $params = []): string
	{
		return $this->urlGenerator->generate($route, $params);
	}

	public function asset(string $path): string
	{
		return rtrim($this->basePath, '/') . '/' . ltrim($path, '/');
	}

	public function trans(string $id, array $params = [], ?string $domain = null, ?string $locale = null): string
	{
		return $this->translator->trans($id, $params, $domain, $locale);
	}

	public function include(string $template, array $params = []): string
	{
		return $this->renderer->render($template, $params, $this);
	}

	public function addInlineScript(string $script): void
	{
		$this->inlineScripts[] = $script;
	}

	public function addHeadStyle(string $css): void
	{
		$this->headStyles[] = $css;
	}

	public function getInlineScripts(): array
	{
		return $this->inlineScripts;
	}

	public function getHeadStyles(): array
	{
		return $this->headStyles;
	}

	public function addHeadLink(string $rel, string $href, string $type = 'text/css', ?string $media = null): void
	{
		$this->headLinks[] = compact('rel', 'href', 'type', 'media');
	}

	public function getHeadLinks(): array
	{
		return $this->headLinks;
	}

	public function addBodyScript(string $src): void
	{
		$this->deferredScripts[] = $src;
	}

	public function getBodyScripts(): array
	{
		return $this->deferredScripts;
	}

	public function setTitle(string $title): void
	{
		$this->title = $title;
	}

	public function getTitle(): string
	{
		return $this->title;
	}

	public function addMeta(string $name, string $content): void
	{
		$this->metas[] = compact('name', 'content');
	}

	public function getMetas(): array
	{
		return $this->metas;
	}

	public function addOgMeta(string $property, string $content): void
	{
		$this->ogMetas[] = compact('property', 'content');
	}

	public function getOgMetas(): array
	{
		return $this->ogMetas;
	}

	/**
	 * @param array<array{label: string, url?: string}> $crumbs
	 */
	public function setBreadcrumbs(array $crumbs): void
	{
		$this->breadcrumbs = $crumbs;
	}

	public function getBreadcrumbs(): array
	{
		return $this->breadcrumbs;
	}
}
