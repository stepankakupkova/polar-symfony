# Error Logging & Error Pages Implementation Plan

> **For agentic workers:** REQUIRED: Use the `subagent-driven-development` agent (recommended) or `executing-plans` agent to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Zachytit všechny neošetřené výjimky a chyby (404, 500, fatální) a zalogovat je do `application_log` tabulky s error reference kódem – přesně jako polar's `initErrorLogger()` v Module.php.

**Architecture:** Symfony `kernel.exception` EventSubscriber zachytí výjimky, zaloguje je přes existující `App\Application\Service\Logger` a vykreslí phtml error šablony (404/500) s error reference. Pro fatální chyby (OOM, segfault) registrujeme `register_shutdown_function`.

**Tech Stack:** Symfony 8 EventSubscriber, KernelEvents::EXCEPTION, ExceptionEvent, existující Logger + LogRepository, phtml šablony přes PhtmlRenderer

---

### Task 1: Error šablona – 404

**Files:**
- Create: `templates/error/404.phtml`

Šablona pro 404 chybu. Zjednodušená kopie polaru (bez admin větve a Laminas navigation – ty dodáme později, až bude admin layout pro error stránky).

- [ ] **Step 1: Vytvořit šablonu `templates/error/404.phtml`**

```php
<?php
/**
 * @var string $errorReference
 * @var bool $displayExceptions
 * @var \Throwable|null $exception
 */
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 text-center">
            <h1 class="display-1 fw-bold text-muted">404</h1>
            <h2>Stránka nebyla nalezena</h2>
            <p class="text-muted">
                Omlouváme se, ale stránka, kterou hledáte, neexistuje.
            </p>
            <?php if (!empty($errorReference)) { ?>
                <p class="small text-muted">Reference: <?= htmlspecialchars($errorReference) ?></p>
            <?php } ?>
            <a href="/" class="btn btn-primary mt-3">Zpět na úvodní stránku</a>
        </div>
    </div>

    <?php if (!empty($displayExceptions) && $exception instanceof \Throwable) { ?>
        <hr />
        <h3>Podrobnosti (jen dev):</h3>
        <dl>
            <dt>Exception:</dt>
            <dd><?= htmlspecialchars($exception::class) ?></dd>
            <dt>File:</dt>
            <dd><pre><?= htmlspecialchars($exception->getFile()) ?>:<?= $exception->getLine() ?></pre></dd>
            <dt>Message:</dt>
            <dd><pre><?= htmlspecialchars($exception->getMessage()) ?></pre></dd>
            <dt>Stack trace:</dt>
            <dd><pre><?= htmlspecialchars($exception->getTraceAsString()) ?></pre></dd>
        </dl>
    <?php } ?>
</div>
```

- [ ] **Step 2: Commit**

```bash
git add templates/error/404.phtml
git commit -m "feat: add 404 error template"
```

---

### Task 2: Error šablona – 500 (index)

**Files:**
- Create: `templates/error/500.phtml`

Šablona pro obecnou serverovou chybu (500). Kopie polaru s error reference.

- [ ] **Step 1: Vytvořit šablonu `templates/error/500.phtml`**

```php
<?php
/**
 * @var string $errorReference
 * @var bool $displayExceptions
 * @var \Throwable|null $exception
 */
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 text-center">
            <h1 class="display-1 fw-bold text-muted">500</h1>
            <h2>Nastala chyba</h2>
            <p class="text-muted">
                Omlouváme se, došlo k neočekávané chybě. Zkuste to prosím později.
            </p>
            <?php if (!empty($errorReference)) { ?>
                <p class="small text-muted">Reference: <?= htmlspecialchars($errorReference) ?></p>
            <?php } ?>
            <a href="/" class="btn btn-primary mt-3">Zpět na úvodní stránku</a>
        </div>
    </div>

    <?php if (!empty($displayExceptions) && $exception instanceof \Throwable) { ?>
        <hr />
        <h3>Podrobnosti (jen dev):</h3>
        <dl>
            <dt>Exception:</dt>
            <dd><?= htmlspecialchars($exception::class) ?></dd>
            <dt>File:</dt>
            <dd><pre><?= htmlspecialchars($exception->getFile()) ?>:<?= $exception->getLine() ?></pre></dd>
            <dt>Message:</dt>
            <dd><pre><?= htmlspecialchars($exception->getMessage()) ?></pre></dd>
            <dt>Stack trace:</dt>
            <dd><pre><?= htmlspecialchars($exception->getTraceAsString()) ?></pre></dd>
        </dl>
        <?php
        $ex = $exception->getPrevious();
        $count = 0;
        while ($ex && $count < 50) { ?>
            <hr />
            <h4>Previous: <?= htmlspecialchars($ex::class) ?></h4>
            <dl>
                <dt>File:</dt>
                <dd><pre><?= htmlspecialchars($ex->getFile()) ?>:<?= $ex->getLine() ?></pre></dd>
                <dt>Message:</dt>
                <dd><pre><?= htmlspecialchars($ex->getMessage()) ?></pre></dd>
                <dt>Stack trace:</dt>
                <dd><pre><?= htmlspecialchars($ex->getTraceAsString()) ?></pre></dd>
            </dl>
        <?php
            $ex = $ex->getPrevious();
            $count++;
        } ?>
    <?php } ?>
</div>
```

- [ ] **Step 2: Commit**

```bash
git add templates/error/500.phtml
git commit -m "feat: add 500 error template"
```

---

### Task 3: Error šablona – fatal.phtml

**Files:**
- Create: `templates/error/fatal.phtml`

Statická šablona pro fatální chyby (OOM, segfault). Nesmí záviset na žádném frameworku – jen čisté HTML s placeholdery, protože v shutdown funkci může být Symfony nedostupné.

- [ ] **Step 1: Vytvořit šablonu `templates/error/fatal.phtml`**

Kopie 1:1 z polaru (`module/Application/view/error/fatal.phtml`):

```html
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8" />
    <title>Fatal error</title>
</head>
<body>
<h1>Fatal error</h1>
<h2>%__ERROR_REFERENCE__%</h2>
<p>%__ERROR_MESSAGE__%</p>
<p>%__ERROR_FILE__%</p>
<p>%__ERROR_LINE__%</p>
</body>
</html>
```

- [ ] **Step 2: Commit**

```bash
git add templates/error/fatal.phtml
git commit -m "feat: add fatal error template"
```

---

### Task 4: ErrorSubscriber – logování + renderování chybových stránek

**Files:**
- Create: `src/Application/EventSubscriber/ErrorSubscriber.php`

Hlavní součástka. Symfony EventSubscriber pro `kernel.exception`. Zachytí všechny neošetřené výjimky, zaloguje je do DB přes `Logger`, a vrátí Response s vyrenderovanou phtml šablonou.

Odpovídá polaru `EVENT_DISPATCH_ERROR` + `EVENT_RENDER_ERROR` handlery.

- [ ] **Step 1: Vytvořit `src/Application/EventSubscriber/ErrorSubscriber.php`**

```php
<?php

declare(strict_types=1);

namespace App\Application\EventSubscriber;

use App\Application\Service\Logger;
use App\Application\View\PhtmlRenderer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class ErrorSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Logger $logger,
        private PhtmlRenderer $renderer,
        private string $environment,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', -128],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $statusCode = $exception instanceof HttpExceptionInterface
            ? $exception->getStatusCode()
            : 500;

        // Generování error reference
        $chars = md5(uniqid('', true));
        $errorReference = substr($chars, 2, 2) . substr($chars, 12, 2) . substr($chars, 26, 2);

        // Logování do DB (jako polar initErrorLogger)
        $extra = [
            'reference' => $errorReference,
            'file' => $exception->getFile(),
            'line' => (string) $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ];

        if (isset($exception->xdebug_message)) {
            $extra['xdebug'] = $exception->xdebug_message;
        }

        $this->logger->err($exception->getMessage(), $extra);

        // Renderování šablony
        $displayExceptions = ($this->environment === 'dev');

        $template = $statusCode === 404 ? 'error/404' : 'error/500';

        try {
            $html = $this->renderer->renderWithLayout($template, [
                'errorReference' => $errorReference,
                'displayExceptions' => $displayExceptions,
                'exception' => $exception,
                'statusCode' => $statusCode,
            ]);
        } catch (\Throwable $renderException) {
            // Pokud selže i renderování, fallback na prostý text
            $html = '<h1>Error ' . $statusCode . '</h1>'
                . '<p>Reference: ' . htmlspecialchars($errorReference) . '</p>';

            // Zalogovat i chybu renderování
            $this->logger->err('RENDER ERROR: ' . $renderException->getMessage(), [
                'reference' => $errorReference,
                'file' => $renderException->getFile(),
                'line' => (string) $renderException->getLine(),
                'trace' => $renderException->getTraceAsString(),
            ]);
        }

        $response = new Response($html, $statusCode);
        $event->setResponse($response);
    }
}
```

Klíčové detaily:
- Priorita **-128** = nízká priorita, aby se spustil jako poslední (Symfony profiler a jiné debug nástroje mají vyšší prioritu a v dev prostředí je přepíšou)
- V **dev** prostředí Symfony standardně zobrazí svůj vlastní exception handler (Whoops-like stránku), takže tento subscriber se uplatní hlavně v **prod**
- Error reference se generuje stejným algoritmem jako v polaru (`md5(uniqid) → 6 znaků`)
- Fallback HTML pokud selže i šablona (stejný princip jako polar `register_shutdown_function`)

- [ ] **Step 2: Zaregistrovat v `config/services.yaml`**

Přidat argument `$environment` do services.yaml:

```yaml
    App\Application\EventSubscriber\ErrorSubscriber:
        arguments:
            $environment: '%kernel.environment%'
```

- [ ] **Step 3: Spustit `php bin/console cache:clear`**

Očekávaný výstup: `[OK] Cache for the "dev" environment (debug=true) was successfully cleared.`

- [ ] **Step 4: Spustit `php bin/console lint:container`**

Očekávaný výstup: `[OK] The container was linted successfully`

- [ ] **Step 5: Commit**

```bash
git add src/Application/EventSubscriber/ErrorSubscriber.php config/services.yaml
git commit -m "feat: add ErrorSubscriber for exception logging and error pages"
```

---

### Task 5: Shutdown handler – fatální chyby

**Files:**
- Create: `src/Application/EventSubscriber/FatalErrorHandler.php`

Odpovídá polaru `register_shutdown_function` v `initErrorLogger()`. Zachytí fatální chyby (E_ERROR), které prochází mimo Symfony exception handling – OOM, segfault apod.

Registrujeme jako `kernel.request` listener, který při prvním requestu zaregistruje shutdown funkci. Logger se předá dovnitř closure.

- [ ] **Step 1: Vytvořit `src/Application/EventSubscriber/FatalErrorHandler.php`**

```php
<?php

declare(strict_types=1);

namespace App\Application\EventSubscriber;

use App\Application\Service\Logger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class FatalErrorHandler implements EventSubscriberInterface
{
    private bool $registered = false;

    public function __construct(
        private Logger $logger,
        private string $templatesDir,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onRequest', 255],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if ($this->registered || !$event->isMainRequest()) {
            return;
        }

        $this->registered = true;
        $logger = $this->logger;
        $templatesDir = $this->templatesDir;

        register_shutdown_function(static function () use ($logger, $templatesDir) {
            $error = error_get_last();
            if (null === $error || $error['type'] !== E_ERROR) {
                return;
            }

            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            $chars = md5(uniqid('', true));
            $errorReference = substr($chars, 2, 2) . substr($chars, 12, 2) . substr($chars, 26, 2);

            try {
                $logger->crit($error['message'], [
                    'reference' => $errorReference,
                    'file' => $error['file'],
                    'line' => (string) $error['line'],
                ]);
            } catch (\Throwable) {
                // DB může být nedostupná při fatální chybě
            }

            $fatalTemplatePath = $templatesDir . '/error/fatal.phtml';

            if (is_file($fatalTemplatePath)) {
                $body = file_get_contents($fatalTemplatePath);
                $body = str_replace(
                    [
                        '%__ERROR_REFERENCE__%',
                        '%__ERROR_MESSAGE__%',
                        '%__ERROR_FILE__%',
                        '%__ERROR_LINE__%',
                    ],
                    [
                        'Reference: ' . $errorReference,
                        'Message: ' . htmlspecialchars($error['message']),
                        'File: ' . htmlspecialchars($error['file']),
                        'Line: ' . $error['line'],
                    ],
                    $body
                );
                echo $body;
            } else {
                echo '<h1>Fatal error</h1><p>Reference: ' . htmlspecialchars($errorReference) . '</p>';
            }

            exit(1);
        });
    }
}
```

- [ ] **Step 2: Zaregistrovat v `config/services.yaml`**

```yaml
    App\Application\EventSubscriber\FatalErrorHandler:
        arguments:
            $templatesDir: '%kernel.project_dir%/templates'
```

- [ ] **Step 3: Spustit `php bin/console cache:clear`**

Očekávaný výstup: `[OK] Cache for the "dev" environment (debug=true) was successfully cleared.`

- [ ] **Step 4: Spustit `php bin/console lint:container`**

Očekávaný výstup: `[OK] The container was linted successfully`

- [ ] **Step 5: Commit**

```bash
git add src/Application/EventSubscriber/FatalErrorHandler.php config/services.yaml
git commit -m "feat: add FatalErrorHandler for shutdown function logging"
```

---

### Task 6: Ověření celého řešení

**Files:**
- None (verification only)

- [ ] **Step 1: Ověřit registraci subscriberů**

```bash
php bin/console debug:event-dispatcher kernel.exception
```

Očekávaný výstup: `App\Application\EventSubscriber\ErrorSubscriber::onKernelException` v seznamu listenerů.

```bash
php bin/console debug:event-dispatcher kernel.request
```

Očekávaný výstup: `App\Application\EventSubscriber\FatalErrorHandler::onRequest` v seznamu (priorita 255).

- [ ] **Step 2: Ověřit autowiring**

```bash
php bin/console debug:autowiring Logger --all
```

Očekávaný výstup: `App\Application\Service\Logger` v seznamu.

- [ ] **Step 3: Spustit `php bin/console lint:container`**

Očekávaný výstup: `[OK] The container was linted successfully`

- [ ] **Step 4: Commit finální**

```bash
git add -A
git commit -m "feat: complete error logging and error pages"
```
