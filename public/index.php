<?php

use App\Kernel;
use Symfony\Component\HttpKernel\HttpCache\HttpCache;
use Symfony\Component\HttpKernel\HttpCache\Store;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    $kernel = new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);

    if ($context['APP_ENV'] === 'prod') {
        $kernel = new HttpCache($kernel, new Store($kernel->getCacheDir() . '/http_cache'));
    }

    return $kernel;
};
