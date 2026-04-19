# Page Web Module — Implementation Plan

> **For agentic workers:** REQUIRED: Use the `subagent-driven-development` agent (recommended) or `executing-plans` agent to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Migrovat web (frontend) část Page modulu z polaru do polar-symfony jako 1:1 kopii.

**Architecture:** Jeden controller `PageWebController` s jednou akcí `page()`, která načte stránku podle `page_id` z route defaults (vygenerovaných v `page_generated.yaml`). Šablona kopíruje polar 1:1 s převodem helperů.

**Tech Stack:** Symfony 8, PHP 8.5, Doctrine DBAL, PhtmlRenderer, video.js, Google Maps API

---

### Task 1: Vytvořit PageWebController

**Files:**
- Create: `src/Page/Controller/Web/PageWebController.php`

- [ ] **Step 1: Vytvořit controller**

```php
<?php

namespace App\Page\Controller\Web;

use App\Page\Repository\PageRepository;
use App\Application\View\PhtmlRenderer;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PageWebController
{
	public function __construct(
		private PageRepository $pageRepository,
		private UrlGeneratorInterface $urlGenerator,
	) {}

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

		$renderer->setHeadTitle($page['title']);
		$renderer->setHeadMeta('description', $page['seo_description'] ?? '');
		$renderer->setHeadMeta('keywords', $page['seo_keywords'] ?? '');

		// META pro Facebook
		$actual_link = $request->getSchemeAndHttpHost() . $request->getRequestUri();
		$renderer->setHeadMeta('og:title', $page['title'] . ' | TV POLAR');
		$renderer->setHeadMeta('og:description', $page['seo_description'] ?? '');
		$renderer->setHeadMeta('og:url', $actual_link);
		$renderer->setHeadMeta('og:type', 'website');
		$renderer->setHeadMeta('og:image', 'https://' . $request->getHost() . '/img/web/layout/microformat.png');
		$renderer->setHeadMeta('og:image:secure_url', 'https://' . $request->getHost() . '/img/web/layout/microformat.png');
		$renderer->setHeadMeta('og:image:width', '1920');
		$renderer->setHeadMeta('og:image:height', '1080');

		return $renderer->renderResponse('page/web/page', [
			'page' => $page,
		]);
	}
}
```

- [ ] **Step 2: Ověřit syntaxi**

Run: `php -l src/Page/Controller/Web/PageWebController.php`
Expected: No syntax errors

- [ ] **Step 3: Commit**

```bash
git add src/Page/Controller/Web/PageWebController.php
git commit -m "feat(page): add PageWebController for frontend page display"
```

---

### Task 2: Vytvořit web šablonu page.phtml

**Files:**
- Create: `templates/page/web/page.phtml`

Polar originál: `module/Page/view/page/web/web-list/page.phtml`

- [ ] **Step 1: Vytvořit šablonu**

```php
<?php
/*
 * @project polar
 * @author Rostislav Greipel
 * @link https://rogr.cz
 * @copyright (c) 2011 - 2021 ROGR All Rights Reserved
 * @license https://rogr.cz/license/eula EULA License
 */

$view->addHeadLink('stylesheet', $view->asset('vendor/web/video-js/video-js.min.css'));
$view->addHeadLink('stylesheet', $view->asset('vendor/web/video-js/theme/custom/index.css?ver=11'));
$view->addHeadStyle(
    '@media (min-width: 992px) {
        .custom-height-1 {height:95px;}
        .custom-height-2 {height:50px;}
    }'
);

$view->addBodyScript($view->asset('vendor/web/video-js/video.min.js'));
$view->addBodyScript($view->asset('vendor/web/video-js/lang/cs.js'));

// google mapa
$view->addBodyScript('https://maps.googleapis.com/maps/api/js?key=' . GOOGLE_MAP_KEY . '&callback=initMap');
$view->addInlineScript(
    'function initMap() {
        var place = {
            lat: 49.8309733,
            lng: 18.2508454
        };
        var map = new google.maps.Map(
            document.getElementById("googlemaps"), {
                zoom: 15,
                center: place
            }
        );
        var infowindow = new google.maps.InfoWindow({
            content: "<strong>POLAR televize Ostrava, s.r.o.</strong><br>Boleslavova 710/19, 709 00,<br>Ostrava-Mariánské Hory"
        });
        var marker = new google.maps.Marker({
            position: place,
            map: map,
            title: "POLAR televize Ostrava, s.r.o.",
            icon: "/img/web/layout/pin.png"
        });
        marker.addListener("click", function() {
            infowindow.open(map, marker);
        });
        infowindow.open(map, marker);
    }
    '
);

// Přepočet ceny pro stránku REKLAMA
$view->addInlineScript(
    '$("#cena").on("change", function()
        {       
            var procento = $(this).val();  
            
            var castka1 = procento * 1500 / 100;
            $("#castka1").text(castka1);
            
            var castka2 = procento * 2500 / 100;
            $("#castka2").text(castka2);
            
            var castka3 = procento * 2000 / 100;
            $("#castka3").text(castka3);
            
            var castka4 = procento * 1500 / 100;
            $("#castka4").text(castka4);
            
            var castka5 = procento * 500 / 100;
            $("#castka5").text(castka5);
            
            var castka6 = procento * 1000 / 100;
            $("#castka6").text(castka6);
        });
    '
);
?>

<section class="page-header page-header-modern bg-color-light-scale-1 page-header-sm mb-0 mb-lg-4">
    <div class="container">
        <div class="row">
            <div class="col-md-12 align-self-center order-1">
                <?php
                    // TODO: breadcrumb navigace
                ?>
            </div>
        </div>
    </div>
</section>

<?php if ($page['url'] === 'kontakt') { ?>
    <div id="googlemaps" class="google-map"></div>
<?php } ?>

<div class="container-fluid py-4 px-0">
    <div class="row gx-0">
        <div class="col">
            <div class="blog-posts single-post">
                <article class="page-post post post-large blog-single-post border-0 m-0 p-0">
                    <?php if ($page['image']) { ?>
                        <div class="post-image ms-0">
                            <img src="/<?= $page['image'] ?>" class="img-fluid img-thumbnail img-thumbnail-no-borders rounded-0" alt="<?= $page['title'] ?>"/>
                        </div>
                    <?php } ?>
                    <div class="post-content ms-0">
                        <?= $page['content'] ?>

                    </div>
                </article>
            </div>
        </div>
    </div>
</div>
```

Rozdíly oproti polaru:
- `$page->getTitle()` → `$page['title']` (pole místo objektu)
- `$this->basePath()` → `$view->asset()`
- `$this->inlineScript()` → `$view->addInlineScript()` / `$view->addBodyScript()`
- `$this->headLink()` → `$view->addHeadLink()`
- `$this->headStyle()` → `$view->addHeadStyle()`
- Breadcrumb: TODO komentář (vyřeší se v Task 3)
- Laminas navigation breadcrumb → zatím vynecháno

- [ ] **Step 2: Ověřit syntaxi**

Run: `php -l templates/page/web/page.phtml`
Expected: No syntax errors

- [ ] **Step 3: Commit**

```bash
git add templates/page/web/page.phtml
git commit -m "feat(page): add web page template (1:1 copy from polar)"
```

---

### Task 3: Přidat metody do PhtmlRenderer (pokud chybí)

**Files:**
- Modify: `src/Application/View/PhtmlRenderer.php`

Šablona potřebuje metody, které v rendereru možná ještě nejsou:
- `addHeadStyle()` — pro inline CSS  
- `setHeadMeta()` — pro meta tagy (description, keywords, og:*)

- [ ] **Step 1: Přečíst PhtmlRenderer a zjistit, co chybí**

Run: `grep -n "addHeadStyle\|setHeadMeta\|setHeadTitle" src/Application/View/PhtmlRenderer.php`

- [ ] **Step 2: Doplnit chybějící metody**

Příklad pro `addHeadStyle` (pokud chybí):
```php
private array $headStyles = [];

public function addHeadStyle(string $css): void
{
    $this->headStyles[] = $css;
}
```

Příklad pro `setHeadMeta` (pokud chybí):
```php
private array $headMeta = [];

public function setHeadMeta(string $name, string $content): void
{
    $this->headMeta[$name] = $content;
}
```

A odpovídající výstup v `renderHeadMeta()`:
```php
public function renderHeadMeta(): string
{
    $html = '';
    foreach ($this->headMeta as $name => $content) {
        if (str_starts_with($name, 'og:')) {
            $html .= '<meta property="' . $name . '" content="' . htmlspecialchars($content) . '">' . "\n";
        } else {
            $html .= '<meta name="' . $name . '" content="' . htmlspecialchars($content) . '">' . "\n";
        }
    }
    return $html;
}
```

- [ ] **Step 3: Ověřit syntaxi**

Run: `php -l src/Application/View/PhtmlRenderer.php`
Expected: No syntax errors

- [ ] **Step 4: Commit**

```bash
git add src/Application/View/PhtmlRenderer.php
git commit -m "feat(renderer): add headStyle and headMeta support"
```

---

### Task 4: Ověřit generované routes a otestovat

**Files:**
- No new files — testing existing generated config

- [ ] **Step 1: Ověřit page_generated.yaml po admin akci**

Pokud v DB existují stránky a admin je dosud needitoval (config se generuje po edit/add/delete/sort), je `page_generated.yaml` prázdný. Buď:
- Udělat edit libovolné stránky v adminu, aby se config vygeneroval
- Nebo ručně spustit generování

- [ ] **Step 2: Ověřit generované routy**

Run: `php bin/console debug:router | Select-String "page_"`
Expected: Routy jako `page_13`, `page_14` atd. s cestami jako `/reklama`, `/jak-naladit`

- [ ] **Step 3: Ověřit stránku v prohlížeči**

Otevřít: `http://localhost/reklama` (nebo jiná URL stránky)
Expected: Zobrazí se obsah stránky s obrázkem a textem

- [ ] **Step 4: Ověřit kontaktní stránku s mapou**

Otevřít: `http://localhost/kontakt`
Expected: Zobrazí se Google mapa + obsah stránky

---

### Task 5: Breadcrumb navigace (volitelné — závisí na existujícím řešení)

**Files:**
- Possible: `templates/page/web/page.phtml` (update TODO)
- Possible: `templates/application/navigation/breadcrumb.phtml` (pokud existuje)

Breadcrumb v polaru používá `Laminas\Navigation\Submenu` službu. V Symfony verzi potřebujeme zjistit, jak breadcrumb funguje v jiných modulech (např. News) a použít stejný přístup.

- [ ] **Step 1: Zjistit, zda existuje breadcrumb v jiných web šablonách**

Run: `grep -rn "breadcrumb" templates/`

- [ ] **Step 2: Implementovat breadcrumb podle existujícího vzoru**

Závisí na výsledku kroku 1. Pokud žádný breadcrumb neexistuje, odložit na později.

- [ ] **Step 3: Commit**

```bash
git add templates/page/web/page.phtml
git commit -m "feat(page): add breadcrumb navigation to web page"
```
