<?php

namespace App\Application\Service;

final class RoutingCacheInvalidator
{
	public function __construct(
		private string $cacheDir,
	) {}

	public function invalidate(): void
	{
		foreach (glob($this->cacheDir . '/url_matching_routes.php*') as $file) {
			@unlink($file);
		}
		foreach (glob($this->cacheDir . '/url_generating_routes.php*') as $file) {
			@unlink($file);
		}
	}
}
