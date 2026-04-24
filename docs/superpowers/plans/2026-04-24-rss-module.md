# RSS Module Implementation Plan

> **For agentic workers:** REQUIRED: Use the `subagent-driven-development` agent (recommended) or `executing-plans` agent to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Migrovat RSS modul z Laminas do Symfony 1:1 — 15 standalone HTML stránek bez layoutu, parsování RSS feedů přes SimpleXML.

**Architecture:** Jeden controller `RssController` s 15 metodami. Šablony jsou standalone HTML (bez web layoutu) — renderují se přes `$renderer->render()` a výsledek se vrátí jako `Response`. Metoda `msk()` čerpá data z `ShowRepository`, ostatní parsují RSS XML z polar.cz přes `file_get_contents` + `simplexml_load_string`.

**Tech Stack:** PHP 8.5, Symfony 8, YAML routy, phtml šablony, Doctrine DBAL (jen ShowRepository pro msk), SimpleXML pro RSS parsing.

**Důležitá konvence:** Šablony jsou standalone HTML — **nevolat** `renderWithLayout()`, jen `$renderer->render()`. Proměnné `$this->feed[...]` → `$feed[...]`, `$this->basePath('...')` → `/vendor/...`.

**Přehled struktur šablon:**
- **Carousel (standard):** ostrava, novy-jicin, stonava, frydek-mistek, havirov, karvina, bruntal, opava, celadna, studenka
- **Carousel (speciální CSS):** frydek-mistek2 (dark blue + Tabac font), bruntal2 (white controls), orlova (white controls + height: 420px)
- **Tabs (jiná struktura!):** krnov (limit 4, auto-rotation JS)
- **DB data (ne RSS):** msk (ShowRepository)

---

## Soubory

| Soubor | Akce |
|---|---|
| `src/Rss/Controller/Web/RssController.php` | Vytvořit |
| `config/routes/rss.yaml` | Vytvořit |
| `config/routes.yaml` | Upravit (přidat import) |
| `src/Program/Repository/ShowRepository.php` | Upravit (přidat metodu, pokud chybí) |
| `templates/rss/web/ostrava.phtml` | Vytvořit |
| `templates/rss/web/novy-jicin.phtml` | Vytvořit |
| `templates/rss/web/stonava.phtml` | Vytvořit |
| `templates/rss/web/frydek-mistek.phtml` | Vytvořit |
| `templates/rss/web/frydek-mistek2.phtml` | Vytvořit |
| `templates/rss/web/havirov.phtml` | Vytvořit |
| `templates/rss/web/karvina.phtml` | Vytvořit |
| `templates/rss/web/krnov.phtml` | Vytvořit |
| `templates/rss/web/bruntal.phtml` | Vytvořit |
| `templates/rss/web/bruntal2.phtml` | Vytvořit |
| `templates/rss/web/opava.phtml` | Vytvořit |
| `templates/rss/web/orlova.phtml` | Vytvořit |
| `templates/rss/web/celadna.phtml` | Vytvořit |
| `templates/rss/web/studenka.phtml` | Vytvořit |
| `templates/rss/web/msk.phtml` | Vytvořit |

---

## Task 1: Routy

**Files:**
- Create: `config/routes/rss.yaml`
- Modify: `config/routes.yaml`

- [ ] **Krok 1: Vytvořit `config/routes/rss.yaml`**

```yaml
rss_ostrava:
  path: /rss/ostrava
  controller: App\Rss\Controller\Web\RssController::ostrava

rss_novy_jicin:
  path: /rss/novy-jicin
  controller: App\Rss\Controller\Web\RssController::novyJicin

rss_stonava:
  path: /rss/stonava
  controller: App\Rss\Controller\Web\RssController::stonava

rss_frydek_mistek:
  path: /rss/frydek-mistek
  controller: App\Rss\Controller\Web\RssController::frydekMistek

rss_frydek_mistek2:
  path: /rss/frydek-mistek2
  controller: App\Rss\Controller\Web\RssController::frydekMistek2

rss_havirov:
  path: /rss/havirov
  controller: App\Rss\Controller\Web\RssController::havirov

rss_karvina:
  path: /rss/karvina
  controller: App\Rss\Controller\Web\RssController::karvina

rss_krnov:
  path: /rss/krnov
  controller: App\Rss\Controller\Web\RssController::krnov

rss_bruntal:
  path: /rss/bruntal
  controller: App\Rss\Controller\Web\RssController::bruntal

rss_bruntal2:
  path: /rss/bruntal2
  controller: App\Rss\Controller\Web\RssController::bruntal2

rss_opava:
  path: /rss/opava
  controller: App\Rss\Controller\Web\RssController::opava

rss_orlova:
  path: /rss/orlova
  controller: App\Rss\Controller\Web\RssController::orlova

rss_celadna:
  path: /rss/celadna
  controller: App\Rss\Controller\Web\RssController::celadna

rss_studenka:
  path: /rss/studenka
  controller: App\Rss\Controller\Web\RssController::studenka

rss_msk:
  path: /rss/msk
  controller: App\Rss\Controller\Web\RssController::msk
```

- [ ] **Krok 2: Přidat import do `config/routes.yaml`** (za řádek `camera: resource: routes/camera.yaml`)

```yaml
rss:
    resource: routes/rss.yaml
```

- [ ] **Krok 3: Commit**

```bash
git add config/routes/rss.yaml config/routes.yaml
git commit -m "feat(rss): add routes"
```

---

## Task 2: Controller

**Files:**
- Create: `src/Rss/Controller/Web/RssController.php`

- [ ] **Krok 1: Vytvořit `src/Rss/Controller/Web/RssController.php`**

```php
<?php
/*
 * @project polar
 * @author Rostislav Greipel
 * @link https://rogr.cz
 * @copyright (c) 2011 - 2021 ROGR All Rights Reserved
 * @license https://rogr.cz/license/eula EULA License
 */

declare(strict_types=1);

namespace App\Rss\Controller\Web;

use App\Application\View\PhtmlRenderer;
use App\Program\Repository\ShowRepository;
use Symfony\Component\HttpFoundation\Response;

final class RssController
{
    public function __construct(
        private ShowRepository $showRepository,
    ) {}

    public function ostrava(PhtmlRenderer $renderer): Response
    {
        $feed = $this->parseFeed('https://polar.cz/rss/ostrava.xml');
        return new Response($renderer->render('rss/web/ostrava', ['feed' => $feed]));
    }

    public function novyJicin(PhtmlRenderer $renderer): Response
    {
        $feed = $this->parseFeed('https://polar.cz/rss/novy-jicin.xml');
        return new Response($renderer->render('rss/web/novy-jicin', ['feed' => $feed]));
    }

    public function stonava(PhtmlRenderer $renderer): Response
    {
        $feed = $this->parseFeed('https://polar.cz/rss/stonava.xml');
        return new Response($renderer->render('rss/web/stonava', ['feed' => $feed]));
    }

    public function frydekMistek(PhtmlRenderer $renderer): Response
    {
        $feed = $this->parseFeed('https://polar.cz/rss/frydek-mistek.xml');
        return new Response($renderer->render('rss/web/frydek-mistek', ['feed' => $feed]));
    }

    public function frydekMistek2(PhtmlRenderer $renderer): Response
    {
        $feed = $this->parseFeed('https://polar.cz/rss/frydek-mistek.xml');
        return new Response($renderer->render('rss/web/frydek-mistek2', ['feed' => $feed]));
    }

    public function havirov(PhtmlRenderer $renderer): Response
    {
        $feed = $this->parseFeed('https://polar.cz/rss/havirov.xml');
        return new Response($renderer->render('rss/web/havirov', ['feed' => $feed]));
    }

    public function karvina(PhtmlRenderer $renderer): Response
    {
        $feed = $this->parseFeed('https://polar.cz/rss/karvina.xml');
        return new Response($renderer->render('rss/web/karvina', ['feed' => $feed]));
    }

    public function krnov(PhtmlRenderer $renderer): Response
    {
        $feed = $this->parseFeed('https://polar.cz/rss/krnov.xml', 4);
        return new Response($renderer->render('rss/web/krnov', ['feed' => $feed]));
    }

    public function bruntal(PhtmlRenderer $renderer): Response
    {
        $feed = $this->parseFeed('https://polar.cz/rss/bruntal.xml');
        return new Response($renderer->render('rss/web/bruntal', ['feed' => $feed]));
    }

    public function bruntal2(PhtmlRenderer $renderer): Response
    {
        $feed = $this->parseFeed('https://polar.cz/rss/bruntal.xml', 6);
        return new Response($renderer->render('rss/web/bruntal2', ['feed' => $feed]));
    }

    public function opava(PhtmlRenderer $renderer): Response
    {
        $feed = $this->parseFeed('https://polar.cz/rss/opava.xml');
        return new Response($renderer->render('rss/web/opava', ['feed' => $feed]));
    }

    public function orlova(PhtmlRenderer $renderer): Response
    {
        $feed = $this->parseFeed('https://polar.cz/rss/orlova.xml');
        return new Response($renderer->render('rss/web/orlova', ['feed' => $feed]));
    }

    public function celadna(PhtmlRenderer $renderer): Response
    {
        $feed = $this->parseFeed('https://polar.cz/rss/celadna.xml');
        return new Response($renderer->render('rss/web/celadna', ['feed' => $feed]));
    }

    public function studenka(PhtmlRenderer $renderer): Response
    {
        $feed = $this->parseFeed('https://polar.cz/rss/studenka.xml');
        return new Response($renderer->render('rss/web/studenka', ['feed' => $feed]));
    }

    public function msk(PhtmlRenderer $renderer): Response
    {
        $shows = null;
        try {
            $shows = $this->showRepository->getShowsForRSS();
        } catch (\Exception $e) {
            //var_dump($e->getMessage());
        }
        return new Response($renderer->render('rss/web/msk', ['shows' => $shows]));
    }

    private function parseFeed(string $url, ?int $limit = null): array
    {
        $data = [
            'title'       => '',
            'link'        => '',
            'description' => '',
            'language'    => '',
            'content'     => [],
        ];

        try {
            $xml = @simplexml_load_string((string) file_get_contents($url));
            if (!$xml) {
                return $data;
            }

            $channel = $xml->channel;
            $data['title']       = (string) $channel->title;
            $data['link']        = (string) $channel->link;
            $data['description'] = (string) $channel->description;
            $data['language']    = (string) $channel->language;

            $i = 1;
            foreach ($channel->item as $item) {
                if ($limit !== null && $i > $limit) break;

                // Enclosure (image)
                $image = 'https://polar.cz/data/microformats/polar.jpg';
                if (isset($item->enclosure)) {
                    $attrs = $item->enclosure->attributes();
                    if (!empty($attrs['url'])) {
                        $image = (string) $attrs['url'];
                    }
                }

                // dateModified
                $dateModified = '';
                try {
                    $date = new \DateTime((string) $item->pubDate);
                    $dateModified = $date->format('d.m.Y');
                } catch (\Exception) {}

                $data['content'][] = [
                    'title'        => (string) $item->title,
                    'description'  => (string) $item->description,
                    'dateModified' => $dateModified,
                    'authors'      => (string) $item->author,
                    'link'         => (string) $item->link,
                    'content'      => (string) $item->children('content', true)->encoded ?? '',
                    'image'        => $image,
                ];
                $i++;
            }
        } catch (\Exception $e) {
            //var_dump($e->getMessage());
        }

        return $data;
    }
}
```

- [ ] **Krok 2: Commit**

```bash
git add src/Rss/Controller/Web/RssController.php
git commit -m "feat(rss): add RssController"
```

---

## Task 3: ShowRepository::getShowsForRSS

**Files:**
- Modify: `src/Program/Repository/ShowRepository.php` (jen pokud metoda chybí)

- [ ] **Krok 1: Zkontrolovat existenci metody**

```bash
php -r "echo file_exists('src/Program/Repository/ShowRepository.php') ? 'exists' : 'missing';"
grep -n "getShowsForRSS" src/Program/Repository/ShowRepository.php
```

Pokud grep vrátí výsledek → metoda existuje, task hotov. Pokud ne → pokračovat krokem 2.

- [ ] **Krok 2: Přidat metodu do `ShowRepository.php`**

Přidat před uzavírací `}` třídy:

```php
public function getShowsForRSS(): ?array
{
    return $this->connection->createQueryBuilder()
        ->select('s.title', 's.url', 's.short_description', 's.image',
                 'p.time', 'p.short_description AS program_short_description', 'p.url AS program_url')
        ->from('program_shows', 's')
        ->leftJoin('s', 'program2shows', 'ps', 's.id = ps.show_id')
        ->leftJoin('ps', 'program', 'p', 'p.id = ps.program_id')
        ->where('s.show_in_archive = 1')
        ->andWhere('s.status = 1')
        ->andWhere('s.id IN (12, 40, 11, 50, 67, 68)')
        ->andWhere('p.premiere = 1')
        ->andWhere('p.time < NOW()')
        ->orderBy('p.time', 'DESC')
        ->setMaxResults(10)
        ->executeQuery()
        ->fetchAllAssociative() ?: null;
}
```

- [ ] **Krok 3: Commit**

```bash
git add src/Program/Repository/ShowRepository.php
git commit -m "feat(rss): add ShowRepository::getShowsForRSS"
```

---

## Task 4: Šablona ostrava.phtml

**Files:**
- Create: `templates/rss/web/ostrava.phtml`

**CSS:** `#990000`, standard carousel bez extra glyphicon/font-family.

- [ ] **Krok 1: Vytvořit `templates/rss/web/ostrava.phtml`**

```php
<!DOCTYPE html>
<html lang="cs">
	<head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="application-name" content="<?= $feed['description'];?>">
        <meta name="author" content="Rostislav Greipel">
        <meta name="robots" content="index,follow">
        <meta name="googlebot" content="index,follow">
        <title>RSS | Ostrava</title>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Open+Sans:400,700&subset=latin,latin-ext&font-display=swap">
        <link rel="stylesheet" href="/vendor/web/rss-readers/bootstrap/bootstrap.min.css?ver=0.1">
        <style>
            .carousel-control {color: #990000;}
            .carousel-control:hover, .carousel-control:focus {color: #990000;}
            .carousel-indicators {bottom: 3px; margin-bottom: 0px;}
            .carousel-indicators li {border-color: #990000;}
            .carousel-indicators .active {background-color: #990000;}
            .carousel-caption {color: #990000; position: relative; right: 0; left: 0; bottom: 0; padding: 5px 10px;}
            .carousel-caption a {color: #990000;}
            .carousel {max-width: 768px;}
            .carousel-inner>.item:hover .carousel-caption a {text-decoration: underline;}
            .date {color: #666; text-shadow: none; font-size: 11px;}
            body {background-color: transparent;}
        </style>
        <script src="/vendor/web/rss-readers/jquery/jquery.min.js?ver=0.1"></script>
	</head>
	<body>
		<!-- <h1><?= $feed['description'];?></h1> -->
		
		<?php if ($feed) {?>
			<div id="carousel" class="carousel slide" data-ride="carousel">
    			<!-- Wrapper for slides -->
    			<div class="carousel-inner" role="listbox">
    				<?php $active = true;?>
        			<?php foreach ($feed['content'] as $entry) {?>
        				<div class="item<?= ($active)?' active':'';?>">
                			<img src="<?= $entry['image'];?>" alt="<?= $entry['title'];?>">
                			<div class="carousel-caption">
                				<a href="<?= $entry['link'];?>" title="<?= $entry['title'];?>" target="_blank">
                					<?= $entry['title'];?>
                				</a>
                				<div class="date"><?= $entry['dateModified'];?></div>
                			</div>
        				</div>
        				<?php $active = false;?>
        			<?php }?>
    			</div>
    			<!-- Controls -->
    			<a class="left carousel-control" href="#carousel" role="button" data-slide="prev">
					<span class="glyphicon glyphicon-chevron-left" aria-hidden="true"></span>
					<span class="sr-only">Předchozí</span>
				</a>
				<a class="right carousel-control" href="#carousel" role="button" data-slide="next">
					<span class="glyphicon glyphicon-chevron-right" aria-hidden="true"></span>
					<span class="sr-only">Další</span>
				</a>
			</div>
		<?php }?>
        <script src="/vendor/web/rss-readers/bootstrap/bootstrap.min.js?ver=0.1"></script>
	</body>
</html>
```

- [ ] **Krok 2: Ověřit v prohlížeči**

Načíst `/rss/ostrava` — musí se zobrazit carousel s titulky a daty.

- [ ] **Krok 3: Commit**

```bash
git add templates/rss/web/ostrava.phtml
git commit -m "feat(rss): add ostrava template"
```

---

## Task 5: Šablona novy-jicin.phtml

**Files:**
- Create: `templates/rss/web/novy-jicin.phtml`

**CSS:** `#990000` + rozšířený styl (`background-image: none`, `glyphicon top 25%`, caption s `font-size 12px text-align left min-height 88px`, font Arial).

- [ ] **Krok 1: Vytvořit `templates/rss/web/novy-jicin.phtml`**

Kopie ostrava.phtml se změnami v `<title>`, `<meta application-name>` a `<style>`:

```php
<!DOCTYPE html>
<html lang="cs">
	<head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="application-name" content="<?= $feed['description'];?>">
        <meta name="author" content="Rostislav Greipel">
        <meta name="robots" content="index,follow">
        <meta name="googlebot" content="index,follow">
        <title>RSS | Nový Jičín</title>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Open+Sans:400,700&subset=latin,latin-ext&font-display=swap">
        <link rel="stylesheet" href="/vendor/web/rss-readers/bootstrap/bootstrap.min.css?ver=0.1">
        <style>
            .carousel-control {color: #990000; background-image: none !important; filter: none;}
            .carousel-control:hover, .carousel-control:focus {color: #990000;}
            .carousel-control .glyphicon {top: 25%;}
            .carousel-indicators {bottom: 3px; margin-bottom: 0px;}
            .carousel-indicators li {border-color: #990000;}
            .carousel-indicators .active {background-color: #990000;}
            .carousel-caption {color: #990000; position: relative; right: 0; left: 0; bottom: 0; padding: 5px 0px; font-size: 12px; line-height: 15px; text-shadow: none; text-align: left; min-height: 88px;}
            .carousel-caption a {color: #990000;}
            .carousel {max-width: 768px;}
            .carousel-inner>.item:hover .carousel-caption a {text-decoration: underline;}
            .date {color: #666; text-shadow: none; font-size: 11px;}
            body {background-color: transparent; font-family: Arial, sans-serif;}
        </style>
        <script src="/vendor/web/rss-readers/jquery/jquery.min.js?ver=0.1"></script>
	</head>
	<body>
		<?php if ($feed) {?>
            <div id="carousel" class="carousel slide" data-ride="carousel">
    			<!-- Wrapper for slides -->
    			<div class="carousel-inner" role="listbox">
    				<?php $active = true;?>
        			<?php foreach ($feed['content'] as $entry) {?>
        				<div class="item<?= ($active)?' active':'';?>">
                			<img src="<?= $entry['image'];?>" alt="<?= $entry['title'];?>">
                			<div class="carousel-caption">
                				<a href="<?= $entry['link'];?>" title="<?= $entry['title'];?>" target="_blank">
                					<?= $entry['title'];?>
                				</a>
                				<div class="date"><?= $entry['dateModified'];?></div>
                			</div>
        				</div>
        				<?php $active = false;?>
        			<?php }?>
    			</div>
    			<!-- Controls -->
    			<a class="left carousel-control" href="#carousel" role="button" data-slide="prev">
					<span class="glyphicon glyphicon-chevron-left" aria-hidden="true"></span>
					<span class="sr-only">Předchozí</span>
                </a>
                <a class="right carousel-control" href="#carousel" role="button" data-slide="next">
					<span class="glyphicon glyphicon-chevron-right" aria-hidden="true"></span>
					<span class="sr-only">Další</span>
				</a>
			</div>
		<?php }?>
        <script src="/vendor/web/rss-readers/bootstrap/bootstrap.min.js?ver=0.1"></script>
	</body>
</html>
```

- [ ] **Krok 2: Commit**

```bash
git add templates/rss/web/novy-jicin.phtml
git commit -m "feat(rss): add novy-jicin template"
```

---

## Task 6: Šablona stonava.phtml

**Files:**
- Create: `templates/rss/web/stonava.phtml`

**CSS:** Jako novy-jicin, ale `glyphicon top 20%`. Navíc `data-interval="3000"` na carousel divu.

- [ ] **Krok 1: Vytvořit `templates/rss/web/stonava.phtml`**

Kopie novy-jicin.phtml se změnami:
- `<title>RSS | Stonava</title>`
- `.carousel-control .glyphicon {top: 20%;}` (místo 25%)
- Carousel div: `<div id="carousel" class="carousel slide" data-ride="carousel" data-interval="3000">`
- Přidat komentář za `<body>`: `<!-- <h1><?= $feed['description'];?></h1> -->`

```php
<!DOCTYPE html>
<html lang="cs">
	<head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="application-name" content="<?= $feed['description'];?>">
        <meta name="author" content="Rostislav Greipel">
        <meta name="robots" content="index,follow">
        <meta name="googlebot" content="index,follow">
        <title>RSS | Stonava</title>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Open+Sans:400,700&subset=latin,latin-ext&font-display=swap">
        <link rel="stylesheet" href="/vendor/web/rss-readers/bootstrap/bootstrap.min.css?ver=0.1">
        <style>
            .carousel-control {color: #990000; background-image: none !important; filter: none;}
            .carousel-control:hover, .carousel-control:focus {color: #990000;}
            .carousel-control .glyphicon {top: 20%;}
            .carousel-indicators {bottom: 3px; margin-bottom: 0px;}
            .carousel-indicators li {border-color: #990000;}
            .carousel-indicators .active {background-color: #990000;}
            .carousel-caption {color: #990000; position: relative; right: 0; left: 0; bottom: 0; padding: 5px 0px; font-size: 12px; line-height: 15px; text-shadow: none; text-align: left; min-height: 88px;}
            .carousel-caption a {color: #990000;}
            .carousel {max-width: 768px;}
            .carousel-inner>.item:hover .carousel-caption a {text-decoration: underline;}
            .date {color: #666; text-shadow: none; font-size: 11px;}
            body {background-color: transparent; font-family: Arial, sans-serif;}
        </style>
        <script src="/vendor/web/rss-readers/jquery/jquery.min.js?ver=0.1"></script>
	</head>
	<body>
		<!-- <h1><?= $feed['description'];?></h1> -->
		
		<?php if ($feed) {?>
			<div id="carousel" class="carousel slide" data-ride="carousel" data-interval="3000">
    			<!-- Wrapper for slides -->
    			<div class="carousel-inner" role="listbox">
    				<?php $active = true;?>
        			<?php foreach ($feed['content'] as $entry) {?>
        				<div class="item<?= ($active)?' active':'';?>">
                			<img src="<?= $entry['image'];?>" alt="<?= $entry['title'];?>">
                			<div class="carousel-caption">
                				<a href="<?= $entry['link'];?>" title="<?= $entry['title'];?>" target="_blank">
                					<?= $entry['title'];?>
                				</a>
                				<div class="date"><?= $entry['dateModified'];?></div>
                			</div>
        				</div>
        				<?php $active = false;?>
        			<?php }?>
    			</div>
    			<!-- Controls -->
    			<a class="left carousel-control" href="#carousel" role="button" data-slide="prev">
					<span class="glyphicon glyphicon-chevron-left" aria-hidden="true"></span>
					<span class="sr-only">Předchozí</span>
				</a>
				<a class="right carousel-control" href="#carousel" role="button" data-slide="next">
					<span class="glyphicon glyphicon-chevron-right" aria-hidden="true"></span>
					<span class="sr-only">Další</span>
				</a>
			</div>
		<?php }?>
        <script src="/vendor/web/rss-readers/bootstrap/bootstrap.min.js?ver=0.1"></script>
	</body>
</html>
```

- [ ] **Krok 2: Commit**

```bash
git add templates/rss/web/stonava.phtml
git commit -m "feat(rss): add stonava template"
```

---

## Task 7: Šablona frydek-mistek.phtml

**Files:**
- Create: `templates/rss/web/frydek-mistek.phtml`

**CSS:** `#990000`, `glyphicon top 20%`, caption `padding 10px 0px`, date `margin-top: 5px`, font Arial.

- [ ] **Krok 1: Vytvořit `templates/rss/web/frydek-mistek.phtml`**

```php
<!DOCTYPE html>
<html lang="cs">
	<head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="application-name" content="<?= $feed['description'];?>">
        <meta name="author" content="Rostislav Greipel">
        <meta name="robots" content="index,follow">
        <meta name="googlebot" content="index,follow">
        <title>RSS | Frýdek-Místek</title>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Open+Sans:400,700&subset=latin,latin-ext&font-display=swap">
        <link rel="stylesheet" href="/vendor/web/rss-readers/bootstrap/bootstrap.min.css?ver=0.1">
        <style>
            .carousel-control {color: #990000; background-image: none !important; filter: none;}
            .carousel-control:hover, .carousel-control:focus {color: #990000;}
            .carousel-control .glyphicon {top: 20%;}
            .carousel-indicators {bottom: 3px; margin-bottom: 0px;}
            .carousel-indicators li {border-color: #990000;}
            .carousel-indicators .active {background-color: #990000;}
            .carousel-caption {color: #990000; position: relative; right: 0; left: 0; bottom: 0; padding: 10px 0px; font-size: 12px; line-height: 15px; text-shadow: none; text-align: left; min-height: 88px;}
            .carousel-caption a {color: #990000;}
            .carousel {max-width: 768px;}
            .carousel-inner>.item:hover .carousel-caption a {text-decoration: underline;}
            .date {color: #666; text-shadow: none; font-size: 11px; margin-top: 5px;}
            body {background-color: transparent; font-family: Arial, sans-serif;}
        </style>
        <script src="/vendor/web/rss-readers/jquery/jquery.min.js?ver=0.1"></script>
	</head>
	<body>
		<!-- <h1><?= $feed['description'];?></h1> -->
		
		<?php if ($feed) {?>
			<div id="carousel" class="carousel slide" data-ride="carousel">
    			<!-- Wrapper for slides -->
    			<div class="carousel-inner" role="listbox">
    				<?php $active = true;?>
        			<?php foreach ($feed['content'] as $entry) {?>
        				<div class="item<?= ($active)?' active':'';?>">
                			<img src="<?= $entry['image'];?>" alt="<?= $entry['title'];?>">
                			<div class="carousel-caption">
                				<a href="<?= $entry['link'];?>" title="<?= $entry['title'];?>" target="_blank">
                					<?= $entry['title'];?>
                				</a>
                				<div class="date"><?= $entry['dateModified'];?></div>
                			</div>
        				</div>
        				<?php $active = false;?>
        			<?php }?>
    			</div>
    			<!-- Controls -->
    			<a class="left carousel-control" href="#carousel" role="button" data-slide="prev">
					<span class="glyphicon glyphicon-chevron-left" aria-hidden="true"></span>
					<span class="sr-only">Předchozí</span>
				</a>
				<a class="right carousel-control" href="#carousel" role="button" data-slide="next">
					<span class="glyphicon glyphicon-chevron-right" aria-hidden="true"></span>
					<span class="sr-only">Další</span>
				</a>
			</div>
		<?php }?>
        <script src="/vendor/web/rss-readers/bootstrap/bootstrap.min.js?ver=0.1"></script>
	</body>
</html>
```

- [ ] **Krok 2: Commit**

```bash
git add templates/rss/web/frydek-mistek.phtml
git commit -m "feat(rss): add frydek-mistek template"
```

---

## Task 8: Šablona frydek-mistek2.phtml

**Files:**
- Create: `templates/rss/web/frydek-mistek2.phtml`

**CSS:** Tmavě modrá `#00264a`, Tabac Slab Regular font, `max-width: 460px`, `glyphicon top 37%`, caption a `color #ffffff font-size 14px`, bg `#00264a`. Stejný RSS feed jako frydek-mistek.

- [ ] **Krok 1: Vytvořit `templates/rss/web/frydek-mistek2.phtml`**

```php
<!DOCTYPE html>
<html lang="cs">
	<head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="application-name" content="<?= $feed['description'];?>">
        <meta name="author" content="Rostislav Greipel">
        <meta name="robots" content="index,follow">
        <meta name="googlebot" content="index,follow">
        <title>RSS | Frýdek-Místek 2</title>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Open+Sans:400,700&subset=latin,latin-ext&font-display=swap">
        <link rel="stylesheet" href="/vendor/web/rss-readers/bootstrap/bootstrap.min.css?ver=0.1">
        <style>
            .carousel-control {color: #00264a; background-image: none !important; filter: none;}
            .carousel-control:hover, .carousel-control:focus {color: #00264a;}
            .carousel-control .glyphicon {top: 37%;}
            .carousel-indicators {bottom: 3px; margin-bottom: 0px;}
            .carousel-indicators li {border-color: #00264a;}
            .carousel-indicators .active {background-color: #00264a;}
            .carousel-caption {color: #00264a; position: relative; right: 0; left: 0; bottom: 0; padding: 10px 0px; font-size: 12px; line-height: 15px; text-shadow: none; text-align: left; min-height: 88px;}
            .carousel-caption a {color: #ffffff; font-size: 14px;}
            .carousel {max-width: 460px;}
            .carousel-inner>.item:hover .carousel-caption a {text-decoration: underline;}
            .date {color: #ccc; text-shadow: none; font-size: 11px; margin-top: 5px;}
            @font-face {font-family: "Tabac Slab Regular"; src: url("/font/Tabac-Slab-Regular.otf");}
            body {background-color: #00264a; font-family: "Tabac Slab Regular", Arial, sans-serif;}
        </style>
        <script src="/vendor/web/rss-readers/jquery/jquery.min.js?ver=0.1"></script>
	</head>
	<body>
		<!-- <h1><?= $feed['description'];?></h1> -->
		
		<?php if ($feed) {?>
			<div id="carousel" class="carousel slide" data-ride="carousel">
    			<!-- Wrapper for slides -->
    			<div class="carousel-inner" role="listbox">
    				<?php $active = true;?>
        			<?php foreach ($feed['content'] as $entry) {?>
        				<div class="item<?= ($active)?' active':'';?>">
                			<img src="<?= $entry['image'];?>" alt="<?= $entry['title'];?>">
                			<div class="carousel-caption">
                				<a href="<?= $entry['link'];?>" title="<?= $entry['title'];?>" target="_blank">
                					<?= $entry['title'];?>
                				</a>
                				<div class="date"><?= $entry['dateModified'];?></div>
                			</div>
        				</div>
        				<?php $active = false;?>
        			<?php }?>
    			</div>
    			<!-- Controls -->
    			<a class="left carousel-control" href="#carousel" role="button" data-slide="prev">
					<span class="glyphicon glyphicon-chevron-left" aria-hidden="true"></span>
					<span class="sr-only">Předchozí</span>
				</a>
				<a class="right carousel-control" href="#carousel" role="button" data-slide="next">
					<span class="glyphicon glyphicon-chevron-right" aria-hidden="true"></span>
					<span class="sr-only">Další</span>
				</a>
			</div>
		<?php }?>
        <script src="/vendor/web/rss-readers/bootstrap/bootstrap.min.js?ver=0.1"></script>
	</body>
</html>
```

- [ ] **Krok 2: Commit**

```bash
git add templates/rss/web/frydek-mistek2.phtml
git commit -m "feat(rss): add frydek-mistek2 template"
```

---

## Task 9: Šablony havirov.phtml a karvina.phtml

**Files:**
- Create: `templates/rss/web/havirov.phtml`
- Create: `templates/rss/web/karvina.phtml`

**CSS:** Obě identické s ostrava — standard `#990000`, žádné extra třídy. Jen jiný `<title>`.

- [ ] **Krok 1: Vytvořit `templates/rss/web/havirov.phtml`**

Kopie ostrava.phtml, změnit pouze:
- `<title>RSS | Havířov</title>`

- [ ] **Krok 2: Vytvořit `templates/rss/web/karvina.phtml`**

Kopie ostrava.phtml, změnit pouze:
- `<title>RSS | Karviná</title>`

- [ ] **Krok 3: Commit**

```bash
git add templates/rss/web/havirov.phtml templates/rss/web/karvina.phtml
git commit -m "feat(rss): add havirov and karvina templates"
```

---

## Task 10: Šablona krnov.phtml (tabs layout)

**Files:**
- Create: `templates/rss/web/krnov.phtml`

**Pozor:** Krnov používá jiný layout — **záložky (tabs)** místo carouselu! Tmavé pozadí `#354647`, auto-rotace záložek přes JS. Controller předává limit 4 položky.

- [ ] **Krok 1: Vytvořit `templates/rss/web/krnov.phtml`**

```php
<!DOCTYPE html>
<html lang="cs">
	<head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="application-name" content="<?= $feed['description'];?>">
        <meta name="author" content="Rostislav Greipel">
        <meta name="robots" content="index,follow">
        <meta name="googlebot" content="index,follow">
        <title>RSS | Krnov</title>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Open+Sans:400,700&subset=latin,latin-ext&font-display=swap">
        <link rel="stylesheet" href="/vendor/web/rss-readers/bootstrap/bootstrap.min.css?ver=0.1">
        <style>
            .container {margin: 0;}
            #krnovTab {max-width: 768px;}
            .date {color: #666; text-shadow: none; font-size: 11px; margin-top: 5px;}
            body {background-color: #354647; font-family: Arial, sans-serif;}
            .nav-pills>li>a {border-radius: 50% 50%;}
            .nav>li>a {font-size: 26px; font-weight: 500; color: #021639; display: inline-block; padding: 3px 15px; margin: 18px 0px; background-color: #47BEE0;}
            .nav>li>a:hover, 
            .nav>li>a:focus, 
            .nav-pills>li.active>a, 
            .nav-pills>li.active>a:hover, 
            .nav-pills>li.active>a:focus {text-decoration: none; color: #021639; background-color: #FED001; outline: none;}
            .title-section {margin-top: 20px; margin-bottom: 10px; padding-left: 0px; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #fff;}
            .title-section a {font-size: 24px; color: #fff; text-decoration: none; border-bottom: 2px solid #fff; padding-bottom: 2px; line-height: 34px;}
            .title-section a:hover {border-bottom: 2px solid #47BEE0;}
            .date {color: #fff; font-size: 16px; margin-top: 0px; padding-left: 0px;}
            .col-tab {padding: 0 0 0 3px;}
            .col-nav {padding: 20px 1px 0 25px}
            .nav-stacked>li+li, .nav>li {margin-left: 2px;}
            @media screen and (max-width: 480px) {
                .nav>li>a {font-size: 12px; padding: 2px 7px; margin: 8px 0px;} 
                .col-nav {padding: 10px 1px 0 20px}
                .title-section a {font-size: 20px;}
                .title-section {margin-top: 5px; margin-bottom: 5px; padding-left: 10px;}
                .date {padding-left: 10px;}
            }
        </style>
        <script src="/vendor/web/rss-readers/jquery/jquery.min.js?ver=0.1"></script>
	</head>
	<body>
		<!-- <h1><?= $feed['description'];?></h1> -->
    	<div class="container">
    		<?php if ($feed) {?>
                <div id="krnovTab" class="row" role="tabpanel">
                    <?php $active = true;?>
                    <?php $i = 1;?>
                    <div class="col-xs-10 col-tab">
                        <div class="tab-content">
                            <?php foreach ($feed['content'] as $entry) {?>
                                <div role="tabpanel" class="tab-pane fade<?= ($active)?' in active':'';?>" id="tab<?= $i;?>">
                                    <div>
                                        <img class="img-responsive" src="<?= $entry['image'];?>" alt="<?= $entry['title'];?>" />
                                    </div>
                                    <div class="title-section">
                                        <a href="<?= $entry['link'];?>" title="<?= $entry['title'];?>" target="_blank">
                                            <?= $entry['title'];?>
                                        </a>
                                    </div>
                                    <div class="date"><?= $entry['dateModified'];?></div>
                                </div>
                                <?php $active = false;?>
                                <?php $i++;?>
                            <?php } ?>
                        </div>
                    </div>
                    <?php $active = true;?>
                    <?php $i = 1;?>
                    <div class="col-xs-2 col-nav">
                        <ul class="nav nav-pills nav-stacked" role="tablist">
                            <?php foreach ($feed['content'] as $entry) {?>
                                <li role="presentation" class="<?= ($active)?'active':'';?>">
                                    <a href="#tab<?= $i;?>" aria-controls="tab<?= $i;?>" role="tab" data-toggle="tab">
                                        <?= $i;?>
                                    </a>
                                </li>
                                <?php $active = false;?>
                                <?php $i++;?>
                            <?php } ?>
                        </ul>
                    </div>
                </div>
    		<?php }?>
        </div>
        <script src="/vendor/web/rss-readers/bootstrap/bootstrap.min.js?ver=0.1"></script>
        <script>
            var tabCarousel = setInterval(function() {
                var tabs = $("#krnovTab .nav-pills > li"),
                    active = tabs.filter(".active"),
                    next = active.next("li"),
                    toClick = next.length ? next.find("a") : tabs.eq(0).find("a");
            
                toClick.trigger("click");
            }, 4000);
        </script>
	</body>
</html>
```

- [ ] **Krok 2: Commit**

```bash
git add templates/rss/web/krnov.phtml
git commit -m "feat(rss): add krnov template"
```

---

## Task 11: Šablona bruntal.phtml

**Files:**
- Create: `templates/rss/web/bruntal.phtml`

**Poznámka:** CSS obsahuje `.nav-pills`, `.col-tab`, `.col-nav` (zděděné ze starší verze), ale HTML body je standardní carousel. Zachovat 1:1 jako v polaru. Tmavé pozadí `#1E1E1E`.

- [ ] **Krok 1: Vytvořit `templates/rss/web/bruntal.phtml`**

```php
<!DOCTYPE html>
<html lang="cs">
	<head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="application-name" content="<?= $feed['description'];?>">
        <meta name="author" content="Rostislav Greipel">
        <meta name="robots" content="index,follow">
        <meta name="googlebot" content="index,follow">
        <title>RSS | Bruntál</title>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Open+Sans:400,700&subset=latin,latin-ext&font-display=swap">
        <link rel="stylesheet" href="/vendor/web/rss-readers/bootstrap/bootstrap.min.css?ver=0.1">
        <style>
            .container {margin: 0;}
            #bruntalTab {max-width: 768px;}
            .date {color: #666; text-shadow: none; font-size: 11px; margin-top: 5px;}
            body {background-color: #1E1E1E; font-family: Arial, sans-serif;}
            .nav-pills>li>a {border-radius: 50% 50%;}
            .nav>li>a {font-size: 22px; font-weight: 500; color: #fff; display: inline-block; padding: 2px 12px;}
            .nav>li>a:hover, 
            .nav>li>a:focus, 
            .nav-pills>li.active>a, 
            .nav-pills>li.active>a:hover, 
            .nav-pills>li.active>a:focus {text-decoration: none; color: #000; background-color: #F8C10F; outline: none;}
            .title-section {margin-top: 20px; margin-bottom: 20px; padding-left: 30px; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #fff;}
            .title-section a {font-size: 24px; color: #fff; text-decoration: none; border-bottom: 2px solid #565656; padding-bottom: 2px; line-height: 34px;}
            .title-section a:hover {border-bottom: 2px solid #fff;}
            .date {color: #fff; font-size: 16px; margin-top: 0px; padding-left: 30px;}
            .col-tab {padding: 0 0 0 3px;}
            .col-nav {padding: 15px 1px 0 1px}
            .nav-stacked>li+li, .nav>li {margin-left: 2px;}
            @media screen and (max-width: 480px) {
                .nav>li>a {font-size: 12px; padding: 2px 7px;} 
                .title-section a {font-size: 20px;}
                .title-section {margin-top: 10px; margin-bottom: 15px; padding-left: 10px;}
                .date {padding-left: 10px;}
            }
        </style>
        <script src="/vendor/web/rss-readers/jquery/jquery.min.js?ver=0.1"></script>
	</head>
	<body>
		<!-- <h1><?= $feed['description'];?></h1> -->
		
		<?php if ($feed) {?>
			<div id="carousel" class="carousel slide" data-ride="carousel">
    			<!-- Wrapper for slides -->
    			<div class="carousel-inner" role="listbox">
    				<?php $active = true;?>
        			<?php foreach ($feed['content'] as $entry) {?>
        				<div class="item<?= ($active)?' active':'';?>">
                			<img src="<?= $entry['image'];?>" alt="<?= $entry['title'];?>">
                			<div class="carousel-caption">
                				<a href="<?= $entry['link'];?>" title="<?= $entry['title'];?>" target="_blank">
                					<?= $entry['title'];?>
                				</a>
                				<div class="date"><?= $entry['dateModified'];?></div>
                			</div>
        				</div>
        				<?php $active = false;?>
        			<?php }?>
    			</div>
    			<!-- Controls -->
    			<a class="left carousel-control" href="#carousel" role="button" data-slide="prev">
					<span class="glyphicon glyphicon-chevron-left" aria-hidden="true"></span>
					<span class="sr-only">Předchozí</span>
                </a>
                <a class="right carousel-control" href="#carousel" role="button" data-slide="next">
					<span class="glyphicon glyphicon-chevron-right" aria-hidden="true"></span>
					<span class="sr-only">Další</span>
				</a>
			</div>
		<?php }?>
        <script src="/vendor/web/rss-readers/bootstrap/bootstrap.min.js?ver=0.1"></script>
	</body>
</html>
```

- [ ] **Krok 2: Commit**

```bash
git add templates/rss/web/bruntal.phtml
git commit -m "feat(rss): add bruntal template"
```

---

## Task 12: Šablona bruntal2.phtml

**Files:**
- Create: `templates/rss/web/bruntal2.phtml`

**CSS:** Bílé šipky (`#ffffff` controls), link v caption `#34549b`, `min-height: 78px`, `margin-top: 5px` na datu, font Arial. Stejný RSS feed jako bruntal, limit 6.

- [ ] **Krok 1: Vytvořit `templates/rss/web/bruntal2.phtml`**

```php
<!DOCTYPE html>
<html lang="cs">
	<head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="application-name" content="<?= $feed['description'];?>">
        <meta name="author" content="Rostislav Greipel">
        <meta name="robots" content="index,follow">
        <meta name="googlebot" content="index,follow">
        <title>RSS | Bruntál 2</title>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Open+Sans:400,700&subset=latin,latin-ext&font-display=swap">
        <link rel="stylesheet" href="/vendor/web/rss-readers/bootstrap/bootstrap.min.css?ver=0.1">
        <style>
            .carousel-control {color: #ffffff; background-image: none !important; filter: none;}
            .carousel-control:hover, .carousel-control:focus {color: #ffffff;}
            .carousel-control .glyphicon {top: 25%;}
            .carousel-indicators {bottom: 3px; margin-bottom: 0px;}
            .carousel-indicators li {border-color: #990000;}
            .carousel-indicators .active {background-color: #990000;}
            .carousel-caption {color: #990000; position: relative; right: 0; left: 0; bottom: 0; padding: 5px 5px; font-size: 12px; line-height: 15px; text-shadow: none; text-align: left; min-height: 78px;}
            .carousel-caption a {color: #34549b; text-decoration: underline;}
            .carousel-caption a:hover {color: #14306f;}
            .carousel {max-width: 768px;}
            .carousel-inner>.item:hover .carousel-caption a {text-decoration: underline;}
            .date {color: #666; text-shadow: none; font-size: 11px; margin-top: 5px;}
            body {background-color: transparent; font-family: Arial, sans-serif; margin-top: 5px;}
        </style>
        <script src="/vendor/web/rss-readers/jquery/jquery.min.js?ver=0.1"></script>
	</head>
	<body>
		<?php if ($feed) {?>
			<div id="carousel" class="carousel slide" data-ride="carousel">
    			<!-- Wrapper for slides -->
    			<div class="carousel-inner" role="listbox">
    				<?php $active = true;?>
        			<?php foreach ($feed['content'] as $entry) {?>
        				<div class="item<?= ($active)?' active':'';?>">
                			<img src="<?= $entry['image'];?>" alt="<?= $entry['title'];?>">
                			<div class="carousel-caption">
                				<a href="<?= $entry['link'];?>" title="<?= $entry['title'];?>" target="_blank">
                					<?= $entry['title'];?>
                				</a>
                				<div class="date"><?= $entry['dateModified'];?></div>
                			</div>
        				</div>
        				<?php $active = false;?>
        			<?php }?>
    			</div>
    			<!-- Controls -->
    			<a class="left carousel-control" href="#carousel" role="button" data-slide="prev">
					<span class="glyphicon glyphicon-chevron-left" aria-hidden="true"></span>
					<span class="sr-only">Předchozí</span>
                </a>
                <a class="right carousel-control" href="#carousel" role="button" data-slide="next">
					<span class="glyphicon glyphicon-chevron-right" aria-hidden="true"></span>
					<span class="sr-only">Další</span>
				</a>
			</div>
		<?php }?>
        <script src="/vendor/web/rss-readers/bootstrap/bootstrap.min.js?ver=0.1"></script>
	</body>
</html>
```

- [ ] **Krok 2: Commit**

```bash
git add templates/rss/web/bruntal2.phtml
git commit -m "feat(rss): add bruntal2 template"
```

---

## Task 13: Šablona opava.phtml

**Files:**
- Create: `templates/rss/web/opava.phtml`

**CSS:** Identická s ostrava — standard `#990000`. Jen jiný `<title>`.

- [ ] **Krok 1: Vytvořit `templates/rss/web/opava.phtml`**

Kopie ostrava.phtml, změnit pouze:
- `<title>RSS | Opava</title>`

- [ ] **Krok 2: Commit**

```bash
git add templates/rss/web/opava.phtml
git commit -m "feat(rss): add opava template"
```

---

## Task 14: Šablona orlova.phtml

**Files:**
- Create: `templates/rss/web/orlova.phtml`

**CSS:** Bílé šipky (`#ffffff`), červené ukazatele (`#990000`), caption a `#ffffff font-size 16px line-height 22px`, item `height: 420px`, `glyphicon top 15%`.

- [ ] **Krok 1: Vytvořit `templates/rss/web/orlova.phtml`**

```php
<!DOCTYPE html>
<html lang="cs">
	<head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="application-name" content="<?= $feed['description'];?>">
        <meta name="author" content="Rostislav Greipel">
        <meta name="robots" content="index,follow">
        <meta name="googlebot" content="index,follow">
        <title>RSS | Orlová</title>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Open+Sans:400,700&subset=latin,latin-ext&font-display=swap">
        <link rel="stylesheet" href="/vendor/web/rss-readers/bootstrap/bootstrap.min.css?ver=0.1">
        <style>
            .carousel-control {color: #ffffff; background-image: none !important; filter: none;}
            .carousel-control:hover, .carousel-control:focus {color: #ffffff;}
            .carousel-control .glyphicon {top: 15%;}
            .carousel-indicators {bottom: 3px; margin-bottom: 0px;}
            .carousel-indicators li {border-color: #990000;}
            .carousel-indicators .active {background-color: #990000;}
            .carousel-caption {color: #990000; position: relative; right: 0; left: 0; bottom: 0; padding: 5px 0px; text-align: left;}
            .carousel-caption a {color: #ffffff; text-shadow: none; font-size:16px; line-height: 22px;}
            .carousel {max-width: 768px;}
            .carousel-inner>.item {height: 420px;}
            .carousel-inner>.item:hover .carousel-caption a {text-decoration: underline;}
            .date {color: #999; text-shadow: none; font-size: 12px;line-height: 18px;}
            body {background-color: transparent;}
        </style>
        <script src="/vendor/web/rss-readers/jquery/jquery.min.js?ver=0.1"></script>
	</head>
	<body>
		<!-- <h1><?= $feed['description'];?></h1> -->
		
		<?php if ($feed) {?>
			<div id="carousel" class="carousel slide" data-ride="carousel">
    			<!-- Wrapper for slides -->
    			<div class="carousel-inner" role="listbox">
    				<?php $active = true;?>
        			<?php foreach ($feed['content'] as $entry) {?>
        				<div class="item<?= ($active)?' active':'';?>">
                			<img src="<?= $entry['image'];?>" alt="<?= $entry['title'];?>">
                			<div class="carousel-caption">
                				<a href="<?= $entry['link'];?>" title="<?= $entry['title'];?>" target="_blank">
                					<?= $entry['title'];?>
                				</a>
                				<div class="date"><?= $entry['dateModified'];?></div>
                			</div>
        				</div>
        				<?php $active = false;?>
        			<?php }?>
    			</div>
    			<!-- Controls -->
    			<a class="left carousel-control" href="#carousel" role="button" data-slide="prev">
					<span class="glyphicon glyphicon-chevron-left" aria-hidden="true"></span>
					<span class="sr-only">Předchozí</span>
                </a>
                <a class="right carousel-control" href="#carousel" role="button" data-slide="next">
					<span class="glyphicon glyphicon-chevron-right" aria-hidden="true"></span>
					<span class="sr-only">Další</span>
				</a>
			</div>
		<?php }?>
        <script src="/vendor/web/rss-readers/bootstrap/bootstrap.min.js?ver=0.1"></script>
	</body>
</html>
```

- [ ] **Krok 2: Commit**

```bash
git add templates/rss/web/orlova.phtml
git commit -m "feat(rss): add orlova template"
```

---

## Task 15: Šablona celadna.phtml

**Files:**
- Create: `templates/rss/web/celadna.phtml`

**CSS:** Zelená `#7CB92C`, font Source Sans Pro (jiný než ostatní!), bílé pozadí, `padding 10px 8px`, `font-size 14px`, `font-weight 600` na linku, `.miniexpres` overlay na vrchu obrázku.
**Speciální:** Po uzavření carouselu je div `.miniexpres` s odkazem na "Čeladenský miniexpres".

- [ ] **Krok 1: Vytvořit `templates/rss/web/celadna.phtml`**

```php
<!DOCTYPE html>
<html lang="cs">
	<head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="application-name" content="<?= $feed['description'];?>">
        <meta name="author" content="Rostislav Greipel">
        <meta name="robots" content="index,follow">
        <meta name="googlebot" content="index,follow">
        <title>RSS | Čeladná</title>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:400,700&subset=latin,latin-ext&font-display=swap">
        <link rel="stylesheet" href="/vendor/web/rss-readers/bootstrap/bootstrap.min.css?ver=0.1">
        <style>
            .carousel-control {color: #7CB92C; background-image: none !important; filter: none;}
            .carousel-control:hover, .carousel-control:focus {color: #7CB92C;}
            .carousel-control .glyphicon {top: 25%;}
            .carousel-indicators {bottom: 3px; margin-bottom: 0px;}
            .carousel-indicators li {border-color: #7CB92C;}
            .carousel-indicators .active {background-color: #7CB92C;}
            .carousel-caption {color: #7CB92C; position: relative; right: 0; left: 0; bottom: 0; padding: 10px 8px; font-size: 14px; line-height: 15px; text-shadow: none; text-align: left; min-height: 88px;}
            .carousel-caption a {color: #7CB92C; font-weight: 600;}
            .carousel {max-width: 768px;}
            .carousel-inner>.item:hover .carousel-caption a {text-decoration: underline;}
            .date {color: #666; text-shadow: none; font-size: 13px; margin-top: 5px;}
            body {background-color: #fff; font-family: "Source Sans Pro", sans-serif;}
            .miniexpres {position: absolute; top: 0px; left:0; font-size:18px; text-align:center; width: 283px;}
            .miniexpres a {color: rgb(255,255,255); background: rgba(15, 55, 89, 0.7); width: 283px; display: block; text-decoration: none; outline: 0;}
            .miniexpres a:hover, .miniexpres a:focus {color: #eee;}
        </style>
        <script src="/vendor/web/rss-readers/jquery/jquery.min.js?ver=0.1"></script>
	</head>
	<body>
		<!-- <h1><?= $feed['description'];?></h1> -->
		
		<?php if ($feed) {?>
			<div id="carousel" class="carousel slide" data-ride="carousel">
    			<!-- Wrapper for slides -->
    			<div class="carousel-inner" role="listbox">
    				<?php $active = true;?>
        			<?php foreach ($feed['content'] as $entry) {?>
        				<div class="item<?= ($active)?' active':'';?>">
                			<img src="<?= $entry['image'];?>" alt="<?= $entry['title'];?>">
                			<div class="carousel-caption">
                				<a href="<?= $entry['link'];?>" title="<?= $entry['title'];?>" target="_blank">
                					<?= $entry['title'];?>
                				</a>
                				<div class="date"><?= $entry['dateModified'];?></div>
                			</div>
        				</div>
        				<?php $active = false;?>
        			<?php }?>
    			</div>
    			<!-- Controls -->
    			<a class="left carousel-control" href="#carousel" role="button" data-slide="prev">
					<span class="glyphicon glyphicon-chevron-left" aria-hidden="true"></span>
					<span class="sr-only">Předchozí</span>
                </a>
                <a class="right carousel-control" href="#carousel" role="button" data-slide="next">
					<span class="glyphicon glyphicon-chevron-right" aria-hidden="true"></span>
					<span class="sr-only">Další</span>
				</a>
			</div>

            <div class="miniexpres">
                  <a href="https://polar.cz/porady/celadensky-miniexpres" target="_blank">TV POLAR - Čeladenský miniexpres</a>
            </div>
		<?php }?>
        <script src="/vendor/web/rss-readers/bootstrap/bootstrap.min.js?ver=0.1"></script>
	</body>
</html>
```

- [ ] **Krok 2: Commit**

```bash
git add templates/rss/web/celadna.phtml
git commit -m "feat(rss): add celadna template"
```

---

## Task 16: Šablona studenka.phtml

**Files:**
- Create: `templates/rss/web/studenka.phtml`

**CSS:** `#d42c10` (červeno-oranžová), bílé pozadí, `font-size 18px line-height 20px` v caption, `color #4d4d4d` datum, `font-weight: 400`. Carousel inner bez `role="listbox"`.

- [ ] **Krok 1: Vytvořit `templates/rss/web/studenka.phtml`**

```php
<!DOCTYPE html>
<html lang="cs">
	<head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="application-name" content="<?= $feed['description'];?>">
        <meta name="author" content="Rostislav Greipel">
        <meta name="robots" content="index,follow">
        <meta name="googlebot" content="index,follow">
        <title>RSS | Studénka</title>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Open+Sans:400,700&subset=latin,latin-ext&font-display=swap">
        <link rel="stylesheet" href="/vendor/web/rss-readers/bootstrap/bootstrap.min.css?ver=0.1">
        <style>
            body {font-family: "Open Sans"; background-color: #fff;}
            .carousel-control {color: #d42c10; background-image: none !important; filter: none;}
            .carousel-control:hover, .carousel-control:focus {color: #d42c10;}
            .carousel-control .glyphicon {top: 25%;}
            .carousel-indicators {bottom: 3px; margin-bottom: 0px;}
            .carousel-indicators li {border-color: #d42c10;}
            .carousel-indicators .active {background-color: #d42c10;}
            .carousel-caption {color: #d42c10; position: relative; right: 0; left: 0; bottom: 0; padding: 10px 8px; font-size: 18px; line-height: 20px; text-shadow: none; text-align: left; min-height: 88px;}
            .carousel-caption a {color: #d42c10; font-weight: 400;}
            .carousel {max-width: 768px;}
            .carousel-inner>.item:hover .carousel-caption a {text-decoration: underline;}
            .date {color: #4d4d4d; text-shadow: none; font-size: 13px; margin-top: 5px;}
        </style>
        <script src="/vendor/web/rss-readers/jquery/jquery.min.js?ver=0.1"></script>
	</head>
	<body>
		<!-- <h1><?= $feed['description'];?></h1> -->
		
		<?php if ($feed) {?>
			<div id="carousel" class="carousel slide" data-ride="carousel">
    			<!-- Wrapper for slides -->
    			<div class="carousel-inner">
    				<?php $active = true;?>
        			<?php foreach ($feed['content'] as $entry) {?>
        				<div class="item<?= ($active)?' active':'';?>">
                			<img src="<?= $entry['image'];?>" alt="<?= $entry['title'];?>">
                			<div class="carousel-caption">
                				<a href="<?= $entry['link'];?>" title="<?= $entry['title'];?>" target="_blank">
                					<?= $entry['title'];?>
                				</a>
                				<div class="date"><?= $entry['dateModified'];?></div>
                			</div>
        				</div>
        				<?php $active = false;?>
        			<?php }?>
    			</div>
    			<!-- Controls -->
    			<a class="left carousel-control" href="#carousel" role="button" data-slide="prev">
					<span class="glyphicon glyphicon-chevron-left" aria-hidden="true"></span>
					<span class="sr-only">Předchozí</span>
				</a>
				<a class="right carousel-control" href="#carousel" role="button" data-slide="next">
					<span class="glyphicon glyphicon-chevron-right" aria-hidden="true"></span>
					<span class="sr-only">Další</span>
				</a>
			</div>
		<?php }?>
        <script src="/vendor/web/rss-readers/bootstrap/bootstrap.min.js?ver=0.1"></script>
	</body>
</html>
```

- [ ] **Krok 2: Commit**

```bash
git add templates/rss/web/studenka.phtml
git commit -m "feat(rss): add studenka template"
```

---

## Task 17: Šablona msk.phtml

**Files:**
- Create: `templates/rss/web/msk.phtml`

**Pozor:** Nemá RSS feed, čerpá z `$shows` (DB data z `ShowRepository::getShowsForRSS`). Carousel s obrázky pořadů, seznam pořadů vlevo. Modrá `#004189` + `#007BBF`.

- [ ] **Krok 1: Přečíst originální `c:\web\www\polar\module\Rss\view\rss\web\web-list\msk.phtml` pro kompletní CSS a HTML**

Zkopírovat šablonu z polaru a nahradit:
- `$this->feed[...]` → `$shows[...]` (nebo přímo iterovat `$shows`)
- `$this->basePath('...')` → `/vendor/...`
- `$this->headStyle()->appendStyle(...)` → přímý `<style>` tag
- Laminas view helpery → přímé HTML

- [ ] **Krok 2: Vytvořit `templates/rss/web/msk.phtml`**

```php
<!DOCTYPE html>
<html lang="cs">
	<head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="application-name" content="MSK">
        <meta name="author" content="Rostislav Greipel">
        <meta name="robots" content="index,follow">
        <meta name="googlebot" content="index,follow">
        <title>RSS | MSK</title>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Open+Sans:400,700&subset=latin,latin-ext&font-display=swap">
        <link rel="stylesheet" href="/vendor/web/rss-readers/bootstrap/bootstrap.min.css?ver=0.1">
        <style>
            body {font-family: "Open Sans"}
            .container {width: 307px; padding-right: 0px; padding-left: 0px;}
            .row {margin-right: 0px; margin-left: 0px}
            section.shows {background-color: #004189; padding: 10px 0px;}
            section.shows ul {-webkit-padding-start: 10px; margin-top: 15px; margin-bottom: 15px; list-style-position: inside;}
            section.shows ul li {color: #ffffff;}
            section.shows ul li a {color: #ffffff; font-weight: 700; font-size: 12px;}
            .carousel-caption {background-color: #007BBF; position: relative; right: 0; left: 0; bottom: 0; padding: 5px 10px; text-align: left; text-shadow: none; font-size: 15px; line-height: 18px; height: 86px;}
            .carousel-caption a {color: #ffffff;}
            .carousel {max-width: 100%;}
            .carousel-inner .item .date {position: absolute; top: 149px; background-color: #004189; color: #ffffff; font-weight: 700; font-size: 14px; padding: 2px 10px;}
            .carousel-inner>.item:hover .carousel-caption a {text-decoration: underline;}
        </style>
        <script src="/vendor/web/rss-readers/jquery/jquery.min.js?ver=0.1"></script>
	</head>
	<body>
        <div class="container">
            <section class="shows">
                <div class="row">
                    <div class="col-xs-7">
                        <ul>
                            <li><a href="https://polar.cz/porady/magazin-112" title="Magazín 112" target="_blank">Magazín 112</a></li>
                            <li><a href="https://polar.cz/porady/nas-kraj-neni-na-okraji" title="Náš kraj není na okraji" target="_blank">Náš kraj není na okraji</a></li>
                            <li><a href="https://polar.cz/porady/eko-magazin" title="Ekomagazín" target="_blank">Eko magazín</a></li>
                        </ul>
                    </div>
                    <div class="col-xs-5">
                        <ul>
                            <li><a href="https://polar.cz/porady/studuj-u-nas" title="Študuj u nás" target="_blank">Študuj u nás</a></li>
                            <li><a href="https://polar.cz/porady/magazin-tv-medicina" title="TV Medicína" target="_blank">TV Medicína</a></li>
                            <li><a href="https://polar.cz/porady/legendy-moravskoslezskeho-kraje" title="Legendy MSK" target="_blank">Legendy MSK</a></li>
                        </ul>
                    </div>
                </div>
            </section>

            <?php if ($shows) {?>
                <div class="row">
                    <div class="col-xs-12">
                        <div id="carousel" class="carousel slide" data-ride="carousel">
                            <div class="carousel-inner" role="listbox">
                                <?php $active = true;?>
                                <?php foreach ($shows as $entry) {?>
                                    <div class="item<?= ($active)?' active':'';?>">
                                        <a href="https://polar.cz/porady/<?= $entry['url'];?>/<?= $entry['program_url'];?>" title="<?= $entry['title'];?>" target="_blank">
                                            <img class="img-responsive" src="https://polar.cz/public/<?= $entry['image'];?>" alt="<?= $entry['title'];?>">
                                        </a>
                                        <span class="date">
                                            <?php $date = new \DateTime($entry['time']);?>
                                            <?= $date->format('d.m.Y');?>
                                        </span>
                                        <div class="carousel-caption">
                                            <a href="https://polar.cz/porady/<?= $entry['url'];?>/<?= $entry['program_url'];?>" title="<?= $entry['title'];?>" target="_blank">
                                                <?= $entry['short_description'] ?? $entry['program_short_description'] ?? '';?>
                                            </a>
                                        </div>
                                    </div>
                                    <?php $active = false;?>
                                <?php }?>
                            </div>
                            <!-- Controls -->
                            <a class="left carousel-control" href="#carousel" role="button" data-slide="prev">
                                <span class="glyphicon glyphicon-chevron-left" aria-hidden="true"></span>
                                <span class="sr-only">Předchozí</span>
                            </a>
                            <a class="right carousel-control" href="#carousel" role="button" data-slide="next">
                                <span class="glyphicon glyphicon-chevron-right" aria-hidden="true"></span>
                                <span class="sr-only">Další</span>
                            </a>
                        </div>
                    </div>
                </div>
            <?php }?>
        </div>
        <script src="/vendor/web/rss-readers/bootstrap/bootstrap.min.js?ver=0.1"></script>
	</body>
</html>
```

- [ ] **Krok 3: Commit**

```bash
git add templates/rss/web/msk.phtml
git commit -m "feat(rss): add msk template"
```

---

## Task 18: Cache clear a ověření

- [ ] **Krok 1: Vyčistit cache**

```bash
php bin/console cache:clear
```

Očekávaný výstup: `[OK] Cache for the "dev" environment (debug=true) was successfully cleared.`

- [ ] **Krok 2: Ověřit routy**

```bash
php bin/console debug:router | findstr rss
```

Očekávaný výstup — 15 řádků začínajících `rss_`:
```
 rss_ostrava        ANY      ANY    ANY  /rss/ostrava
 rss_novy_jicin     ANY      ANY    ANY  /rss/novy-jicin
 rss_stonava        ANY      ANY    ANY  /rss/stonava
 ...
 rss_msk            ANY      ANY    ANY  /rss/msk
```

- [ ] **Krok 3: Otestovat carousel šablony v prohlížeči**

- `/rss/ostrava` — musí se zobrazit carousel s červenými šipkami
- `/rss/frydek-mistek2` — tmavě modrý carousel, max-width 460px
- `/rss/krnov` — tab layout, tmavé pozadí, číslované záložky
- `/rss/celadna` — zelené šipky, miniexpres overlay

- [ ] **Krok 4: Otestovat msk v prohlížeči**

- `/rss/msk` — sekce pořadů vlevo, carousel s obrázky programů

- [ ] **Krok 5: Commit**

```bash
git add .
git commit -m "feat(rss): RSS module complete"
```


**Files:**
- Create: `config/routes/rss.yaml`

- [ ] **Krok 1: Vytvořit soubor routy**

```yaml
rss_ostrava:
  path: /rss/ostrava
  controller: App\Rss\Controller\Web\RssController::ostrava

rss_novy_jicin:
  path: /rss/novy-jicin
  controller: App\Rss\Controller\Web\RssController::novyJicin

rss_stonava:
  path: /rss/stonava
  controller: App\Rss\Controller\Web\RssController::stonava

rss_frydek_mistek:
  path: /rss/frydek-mistek
  controller: App\Rss\Controller\Web\RssController::frydekMistek

rss_frydek_mistek2:
  path: /rss/frydek-mistek2
  controller: App\Rss\Controller\Web\RssController::frydekMistek2

rss_havirov:
  path: /rss/havirov
  controller: App\Rss\Controller\Web\RssController::havirov

rss_karvina:
  path: /rss/karvina
  controller: App\Rss\Controller\Web\RssController::karvina

rss_krnov:
  path: /rss/krnov
  controller: App\Rss\Controller\Web\RssController::krnov

rss_bruntal:
  path: /rss/bruntal
  controller: App\Rss\Controller\Web\RssController::bruntal

rss_bruntal2:
  path: /rss/bruntal2
  controller: App\Rss\Controller\Web\RssController::bruntal2

rss_opava:
  path: /rss/opava
  controller: App\Rss\Controller\Web\RssController::opava

rss_orlova:
  path: /rss/orlova
  controller: App\Rss\Controller\Web\RssController::orlova

rss_celadna:
  path: /rss/celadna
  controller: App\Rss\Controller\Web\RssController::celadna

rss_studenka:
  path: /rss/studenka
  controller: App\Rss\Controller\Web\RssController::studenka

rss_msk:
  path: /rss/msk
  controller: App\Rss\Controller\Web\RssController::msk
```

- [ ] **Krok 2: Importovat routes/rss.yaml v config/routes.yaml**

Do `config/routes.yaml` přidat:
```yaml
rss:
    resource: routes/rss.yaml
```

- [ ] **Krok 3: Zkontrolovat import — ověřit, že ostatní moduly jsou importovány stejně, a zachovat stejný styl**

---

## Task 2: Controller `src/Rss/Controller/Web/RssController.php`

**Files:**
- Create: `src/Rss/Controller/Web/RssController.php`

**Pozn.:** Laminas používal `Laminas\Feed\Reader\Reader::import()`. V Symfony nahrazujeme pomocí `file_get_contents` + `simplexml_load_string`. Šablony se renderují BEZ layoutu — `$renderer->render()`, ne `renderWithLayout()`.

**Pomocná private metoda `parseFeed(string $url, ?int $limit = null): array`** centralizuje parsing RSS — všechny metody kromě `msk()` ji volají.

- [ ] **Krok 1: Vytvořit controller**

```php
<?php
/*
 * @project polar
 * @author Rostislav Greipel
 * @link https://rogr.cz
 * @copyright (c) 2011 - 2021 ROGR All Rights Reserved
 * @license https://rogr.cz/license/eula EULA License
 */

declare(strict_types=1);

namespace App\Rss\Controller\Web;

use App\Application\View\PhtmlRenderer;
use App\Program\Repository\ShowRepository;
use Symfony\Component\HttpFoundation\Response;

final class RssController
{
    public function __construct(
        private ShowRepository $showRepository,
    ) {}

    public function ostrava(PhtmlRenderer $renderer): Response
    {
        $feed = $this->parseFeed('https://polar.cz/rss/ostrava.xml');
        return new Response($renderer->render('rss/web/ostrava', ['feed' => $feed]));
    }

    public function novyJicin(PhtmlRenderer $renderer): Response
    {
        $feed = $this->parseFeed('https://polar.cz/rss/novy-jicin.xml');
        return new Response($renderer->render('rss/web/novy-jicin', ['feed' => $feed]));
    }

    public function stonava(PhtmlRenderer $renderer): Response
    {
        $feed = $this->parseFeed('https://polar.cz/rss/stonava.xml');
        return new Response($renderer->render('rss/web/stonava', ['feed' => $feed]));
    }

    public function frydekMistek(PhtmlRenderer $renderer): Response
    {
        $feed = $this->parseFeed('https://polar.cz/rss/frydek-mistek.xml');
        return new Response($renderer->render('rss/web/frydek-mistek', ['feed' => $feed]));
    }

    public function frydekMistek2(PhtmlRenderer $renderer): Response
    {
        $feed = $this->parseFeed('https://polar.cz/rss/frydek-mistek.xml');
        return new Response($renderer->render('rss/web/frydek-mistek2', ['feed' => $feed]));
    }

    public function havirov(PhtmlRenderer $renderer): Response
    {
        $feed = $this->parseFeed('https://polar.cz/rss/havirov.xml');
        return new Response($renderer->render('rss/web/havirov', ['feed' => $feed]));
    }

    public function karvina(PhtmlRenderer $renderer): Response
    {
        $feed = $this->parseFeed('https://polar.cz/rss/karvina.xml');
        return new Response($renderer->render('rss/web/karvina', ['feed' => $feed]));
    }

    public function krnov(PhtmlRenderer $renderer): Response
    {
        $feed = $this->parseFeed('https://polar.cz/rss/krnov.xml', 4);
        return new Response($renderer->render('rss/web/krnov', ['feed' => $feed]));
    }

    public function bruntal(PhtmlRenderer $renderer): Response
    {
        $feed = $this->parseFeed('https://polar.cz/rss/bruntal.xml');
        return new Response($renderer->render('rss/web/bruntal', ['feed' => $feed]));
    }

    public function bruntal2(PhtmlRenderer $renderer): Response
    {
        $feed = $this->parseFeed('https://polar.cz/rss/bruntal.xml', 6);
        return new Response($renderer->render('rss/web/bruntal2', ['feed' => $feed]));
    }

    public function opava(PhtmlRenderer $renderer): Response
    {
        $feed = $this->parseFeed('https://polar.cz/rss/opava.xml');
        return new Response($renderer->render('rss/web/opava', ['feed' => $feed]));
    }

    public function orlova(PhtmlRenderer $renderer): Response
    {
        $feed = $this->parseFeed('https://polar.cz/rss/orlova.xml');
        return new Response($renderer->render('rss/web/orlova', ['feed' => $feed]));
    }

    public function celadna(PhtmlRenderer $renderer): Response
    {
        $feed = $this->parseFeed('https://polar.cz/rss/celadna.xml');
        return new Response($renderer->render('rss/web/celadna', ['feed' => $feed]));
    }

    public function studenka(PhtmlRenderer $renderer): Response
    {
        $feed = $this->parseFeed('https://polar.cz/rss/studenka.xml');
        return new Response($renderer->render('rss/web/studenka', ['feed' => $feed]));
    }

    public function msk(PhtmlRenderer $renderer): Response
    {
        $shows = null;
        try {
            $shows = $this->showRepository->getShowsForRSS();
        } catch (\Exception $e) {
            //var_dump($e->getMessage());
        }
        return new Response($renderer->render('rss/web/msk', ['shows' => $shows]));
    }

    private function parseFeed(string $url, ?int $limit = null): array
    {
        $data = [
            'title'       => '',
            'link'        => '',
            'description' => '',
            'language'    => '',
            'content'     => [],
        ];

        try {
            $xml = @simplexml_load_string((string) file_get_contents($url));
            if (!$xml) {
                return $data;
            }

            $channel = $xml->channel;
            $data['title']       = (string) $channel->title;
            $data['link']        = (string) $channel->link;
            $data['description'] = (string) $channel->description;
            $data['language']    = (string) $channel->language;

            $i = 1;
            foreach ($channel->item as $item) {
                if ($limit !== null && $i > $limit) break;

                // Enclosure (image)
                $image = 'https://polar.cz/data/microformats/polar.jpg';
                if (isset($item->enclosure)) {
                    $attrs = $item->enclosure->attributes();
                    if (!empty($attrs['url'])) {
                        $image = (string) $attrs['url'];
                    }
                }

                // dateModified
                $dateModified = '';
                try {
                    $date = new \DateTime((string) $item->pubDate);
                    $dateModified = $date->format('d.m.Y');
                } catch (\Exception) {}

                $data['content'][] = [
                    'title'        => (string) $item->title,
                    'description'  => (string) $item->description,
                    'dateModified' => $dateModified,
                    'authors'      => (string) $item->author,
                    'link'         => (string) $item->link,
                    'content'      => (string) $item->children('content', true)->encoded ?? '',
                    'image'        => $image,
                ];
                $i++;
            }
        } catch (\Exception $e) {
            //var_dump($e->getMessage());
        }

        return $data;
    }
}
```

- [ ] **Krok 2: Ověřit, že ShowRepository::getShowsForRSS() existuje**

Metoda musí existovat v `src/Program/Repository/ShowRepository.php`. Pokud ne, přidat:

```php
public function getShowsForRSS(): ?array
{
    return $this->connection->createQueryBuilder()
        ->select('s.title', 's.url', 's.short_description', 's.image',
                 'p.time', 'p.short_description AS program_short_description', 'p.url AS program_url')
        ->from('program_shows', 's')
        ->leftJoin('s', 'program2shows', 'ps', 's.id = ps.show_id')
        ->leftJoin('ps', 'program', 'p', 'p.id = ps.program_id')
        ->where('s.show_in_archive = 1')
        ->andWhere('s.status = 1')
        ->andWhere('s.id IN (12, 40, 11, 50, 67, 68)')
        ->andWhere('p.premiere = 1')
        ->andWhere('p.time < NOW()')
        ->orderBy('p.time', 'DESC')
        ->setMaxResults(10)
        ->executeQuery()
        ->fetchAllAssociative() ?: null;
}
```

---

## Task 3: Šablony — carousel (ostrava, novy-jicin, stonava, frydek-mistek, havirov, karvina, bruntal, opava, orlova, celadna, studenka)

**Files:**
- Create: `templates/rss/web/ostrava.phtml` (a obdobně ostatní)

Tyto šablony jsou skoro identické — liší se jen barvou v CSS (`#990000` pro ostrava apod.) a description textem v meta. Zkopírovat z polaru 1:1, pouze nahradit Laminas view helpery za plain PHP/HTML.

**Klíčové změny oproti polaru:**
- `<?= $this->doctype()?>` → `<!DOCTYPE html>`
- `$this->headMeta()->...` → přímé `<meta>` tagy
- `$this->headTitle(...)` → přímý `<title>` tag
- `$this->headLink()->...` → přímé `<link>` tagy
- `$this->headStyle()->appendStyle(...)` → přímý `<style>` tag
- `$this->headScript()->prependFile(...)` → přímý `<script src>` tag
- `$this->inlineScript()->prependFile(...)` → přímý `<script src>` tag (za body)
- `$this->basePath('...')` → pevná relativní cesta `/vendor/web/rss-readers/...`
- `$this->feed[...]` → `$feed[...]`

- [ ] **Krok 1: Vytvořit `templates/rss/web/ostrava.phtml`**

```php
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="application-name" content="<?= htmlspecialchars($feed['description'] ?? '') ?>">
    <meta name="author" content="Rostislav Greipel">
    <meta name="robots" content="index,follow">
    <meta name="googlebot" content="index,follow">
    <title>RSS | Ostrava</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Open+Sans:400,700&subset=latin,latin-ext&font-display=swap">
    <link rel="stylesheet" href="/vendor/web/rss-readers/bootstrap/bootstrap.min.css?ver=0.1">
    <style>
        .carousel-control {color: #990000;}
        .carousel-control:hover, .carousel-control:focus {color: #990000;}
        .carousel-indicators {bottom: 3px; margin-bottom: 0px;}
        .carousel-indicators li {border-color: #990000;}
        .carousel-indicators .active {background-color: #990000;}
        .carousel-caption {color: #990000; position: relative; right: 0; left: 0; bottom: 0; padding: 5px 10px;}
        .carousel-caption a {color: #990000;}
        .carousel {max-width: 768px;}
        .carousel-inner>.item:hover .carousel-caption a {text-decoration: underline;}
        .date {color: #666; text-shadow: none; font-size: 11px;}
        body {background-color: transparent;}
    </style>
    <script src="/vendor/web/rss-readers/jquery/jquery.min.js?ver=0.1"></script>
</head>
<body>
    <!-- <h1><?= htmlspecialchars($feed['description'] ?? '') ?></h1> -->

    <?php if ($feed) { ?>
        <div id="carousel" class="carousel slide" data-ride="carousel">
            <!-- Wrapper for slides -->
            <div class="carousel-inner" role="listbox">
                <?php $active = true; ?>
                <?php foreach ($feed['content'] as $entry) { ?>
                    <div class="item<?= ($active) ? ' active' : '' ?>">
                        <img src="<?= htmlspecialchars($entry['image']) ?>" alt="<?= htmlspecialchars($entry['title']) ?>">
                        <div class="carousel-caption">
                            <a href="<?= htmlspecialchars($entry['link']) ?>" title="<?= htmlspecialchars($entry['title']) ?>" target="_blank">
                                <?= htmlspecialchars($entry['title']) ?>
                            </a>
                            <div class="date"><?= htmlspecialchars($entry['dateModified']) ?></div>
                        </div>
                    </div>
                    <?php $active = false; ?>
                <?php } ?>
            </div>
            <!-- Controls -->
            <a class="left carousel-control" href="#carousel" role="button" data-slide="prev">
                <span class="glyphicon glyphicon-chevron-left" aria-hidden="true"></span>
                <span class="sr-only">Předchozí</span>
            </a>
            <a class="right carousel-control" href="#carousel" role="button" data-slide="next">
                <span class="glyphicon glyphicon-chevron-right" aria-hidden="true"></span>
                <span class="sr-only">Další</span>
            </a>
        </div>
    <?php } ?>
    <script src="/vendor/web/rss-readers/bootstrap/bootstrap.min.js?ver=0.1"></script>
</body>
</html>
```

- [ ] **Krok 2: Vytvořit zbývající carousel šablony (novy-jicin, stonava, frydek-mistek, frydek-mistek2, havirov, karvina, bruntal, bruntal2, opava, orlova, celadna, studenka)**

Každá šablona je kopie `ostrava.phtml` se změnou:
- `<title>` — název města
- `<meta name="application-name">` — u šablon které nemají `$feed['description']` dát pevný název
- CSS barva (každé město může mít jinou — zkontrolovat v polaru)

Prohlédni šablony v `c:\web\www\polar\module\Rss\view\rss\web\web-list\` a zkopíruj CSS blok 1:1, jen nahraď Laminas helpery za HTML.

---

## Task 4: Šablona `msk.phtml`

**Files:**
- Create: `templates/rss/web/msk.phtml`

- [ ] **Krok 1: Vytvořit `templates/rss/web/msk.phtml`**

```php
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="application-name" content="MSK">
    <meta name="author" content="Rostislav Greipel">
    <meta name="robots" content="index,follow">
    <meta name="googlebot" content="index,follow">
    <title>RSS | MSK</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Open+Sans:400,700&subset=latin,latin-ext&font-display=swap">
    <link rel="stylesheet" href="/vendor/web/rss-readers/bootstrap/bootstrap.min.css?ver=0.1">
    <style>
        body {font-family: "Open Sans"}
        .container {width: 307px; padding-right: 0px; padding-left: 0px;}
        .row {margin-right: 0px; margin-left: 0px}
        /* ... (zkopírovat kompletní CSS blok z polaru 1:1) ... */
        section.shows {background-color: #004189; padding: 10px 0px;}
        section.shows ul {-webkit-padding-start: 10px; margin-top: 15px; margin-bottom: 15px; list-style-position: inside;}
        section.shows ul li {color: #ffffff;}
        section.shows ul li a {color: #ffffff; font-weight: 700; font-size: 12px;}
        .carousel-caption {background-color: #007BBF; position: relative; right: 0; left: 0; bottom: 0; padding: 5px 10px; text-align: left; text-shadow: none; font-size: 15px; line-height: 18px; height: 86px;}
        .carousel-caption a {color: #ffffff;}
        .carousel {max-width: 100%;}
        .carousel-inner .item .date {position: absolute; top: 149px; background-color: #004189; color: #ffffff; font-weight: 700; font-size: 14px; padding: 2px 10px;}
        .carousel-inner>.item:hover .carousel-caption a {text-decoration: underline;}
    </style>
    <script src="/vendor/web/rss-readers/jquery/jquery.min.js?ver=0.1"></script>
</head>
<body>
    <div class="container">
        <section class="shows">
            <div class="row">
                <div class="col-xs-7">
                    <ul>
                        <li><a href="https://polar.cz/porady/magazin-112" title="Magazín 112" target="_blank">Magazín 112</a></li>
                        <li><a href="https://polar.cz/porady/nas-kraj-neni-na-okraji" title="Náš kraj není na okraji" target="_blank">Náš kraj není na okraji</a></li>
                        <li><a href="https://polar.cz/porady/eko-magazin" title="Ekomagazín" target="_blank">Eko magazín</a></li>
                    </ul>
                </div>
                <div class="col-xs-5">
                    <ul>
                        <li><a href="https://polar.cz/porady/studuj-u-nas" title="Študuj u nás" target="_blank">Študuj u nás</a></li>
                        <li><a href="https://polar.cz/porady/magazin-tv-medicina" title="TV Medicína" target="_blank">TV Medicína</a></li>
                        <li><a href="https://polar.cz/porady/legendy-moravskoslezskeho-kraje" title="Legendy MSK" target="_blank">Legendy MSK</a></li>
                    </ul>
                </div>
            </div>
        </section>

        <?php if ($shows) { ?>
            <div class="row">
                <div class="col-xs-12">
                    <div id="carousel" class="carousel slide" data-ride="carousel">
                        <div class="carousel-inner" role="listbox">
                            <?php $active = true; ?>
                            <?php foreach ($shows as $entry) { ?>
                                <div class="item<?= ($active) ? ' active' : '' ?>">
                                    <a href="https://polar.cz/porady/<?= htmlspecialchars($entry['url']) ?>/<?= htmlspecialchars($entry['program_url']) ?>" title="<?= htmlspecialchars($entry['title']) ?>" target="_blank">
                                        <img class="img-responsive" src="https://polar.cz/public/<?= htmlspecialchars($entry['image']) ?>" alt="<?= htmlspecialchars($entry['title']) ?>">
                                    </a>
                                    <span class="date">
                                        <?php $date = new \DateTime($entry['time']); ?>
                                        <?= $date->format('d.m.Y') ?>
                                    </span>
                                    <div class="carousel-caption">
                                        <a href="https://polar.cz/porady/<?= htmlspecialchars($entry['url']) ?>/<?= htmlspecialchars($entry['program_url']) ?>" title="<?= htmlspecialchars($entry['title']) ?>" target="_blank">
                                            <?= htmlspecialchars($entry['short_description'] ?? $entry['program_short_description'] ?? '') ?>
                                        </a>
                                    </div>
                                </div>
                                <?php $active = false; ?>
                            <?php } ?>
                        </div>
                        <!-- Controls -->
                        <a class="left carousel-control" href="#carousel" role="button" data-slide="prev">
                            <span class="glyphicon glyphicon-chevron-left" aria-hidden="true"></span>
                            <span class="sr-only">Předchozí</span>
                        </a>
                        <a class="right carousel-control" href="#carousel" role="button" data-slide="next">
                            <span class="glyphicon glyphicon-chevron-right" aria-hidden="true"></span>
                            <span class="sr-only">Další</span>
                        </a>
                    </div>
                </div>
            </div>
        <?php } ?>
    </div>
    <script src="/vendor/web/rss-readers/bootstrap/bootstrap.min.js?ver=0.1"></script>
</body>
</html>
```

---

## Task 5: Ověřit ShowRepository::getShowsForRSS a vyčistit cache

**Files:**
- Modify: `src/Program/Repository/ShowRepository.php` (jen pokud metoda chybí)

- [ ] **Krok 1: Zkontrolovat existenci metody**

```
grep -n "getShowsForRSS" src/Program/Repository/ShowRepository.php
```

- [ ] **Krok 2: Pokud chybí, přidat (viz Task 2, Krok 2)**

- [ ] **Krok 3: Vyčistit cache a ověřit routy**

```
php bin/console cache:clear
php bin/console debug:router | findstr rss
```

Očekávaný výstup — 15 rss_* routů v seznamu.

- [ ] **Krok 4: Otestovat v prohlížeči**

Načíst `/rss/ostrava`, `/rss/msk` — carousel se musí zobrazit.

---

## Poznámky k CSS

Každé město má jiný CSS styl. Při vytváření šablon (Task 3, Krok 2) otevři odpovídající phtml v polaru a zkopíruj CSS blok doslova. Přehled:

| Šablona | CSS barva hlavní |
|---|---|
| ostrava | #990000 (červená) |
| novy-jicin | zkontrolovat v polaru |
| stonava | zkontrolovat v polaru |
| frydek-mistek | zkontrolovat v polaru |
| frydek-mistek2 | zkontrolovat v polaru |
| havirov | zkontrolovat v polaru |
| karvina | zkontrolovat v polaru |
| krnov | zkontrolovat v polaru |
| bruntal | zkontrolovat v polaru |
| bruntal2 | zkontrolovat v polaru |
| opava | zkontrolovat v polaru |
| orlova | zkontrolovat v polaru |
| celadna | zkontrolovat v polaru |
| studenka | zkontrolovat v polaru |
| msk | #004189 / #007BBF (modrá) |
