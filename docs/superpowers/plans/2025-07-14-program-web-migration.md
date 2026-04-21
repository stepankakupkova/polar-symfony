# Program Web Migration Implementation Plan

> **For agentic workers:** REQUIRED: Use the `subagent-driven-development` agent (recommended) or `executing-plans` agent to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Migrovat webovou (frontend) část modulu Program z Laminas do Symfony 1:1.

**Architecture:** Každý Laminas controller action dostane vlastní Symfony metodu. Repositories vrací plain arrays přes Doctrine DBAL QueryBuilder. Šablony jsou kopie Laminas phtml s přepsanými helpery na `$view->*`.

**Tech Stack:** Symfony 8, PHP 8.5, Doctrine DBAL QueryBuilder, PhtmlRenderer, phtml šablony, BannerRepository, NewsRepository (PR články)

---

## Přehled souborů

### Nové soubory
- `src/Program/Controller/Web/ProgramController.php` — TV program stránka + JSON endpointy
- `src/Program/Controller/Web/ShowexController.php` — mimořádné pořady (showex + videoex)
- `src/Program/Controller/Web/DownloadController.php` — stahování videa + DOCX přepisu
- `templates/program/web/program.phtml` — TV program stránka
- `templates/program/web/shows.phtml` — seznam pořadů
- `templates/program/web/show.phtml` — detail pořadu s videi
- `templates/program/web/video.phtml` — detail videa pořadu
- `templates/program/web/showex.phtml` — detail mimořádného pořadu
- `templates/program/web/videoex.phtml` — detail mimořádného videa

### Upravované soubory
- `src/Program/Controller/Web/ShowController.php` — nahradit stub metodami `shows()`, `show()`, `video()`
- `src/Program/Controller/Admin/ShowWriteController.php` — updatePageParam `createConfig()` → generuje routes na `::show` a `::video`
- `src/Program/Controller/Admin/ShowexWriteController.php` — přidat `createConfig()` pro generování showex routes
- `src/Program/Repository/ProgramRepository.php` — přidat `fetchForWeb`, `getProgram2FromNow`, `getProgramFromNow`
- `src/Program/Repository/ShowRepository.php` — přidat `fetchTimesForWeb`, `fetchAllByCategories`, `fetchForAutocomplete`
- `src/Program/Repository/VideoRepository.php` — přidat `getPaginatorByShow`, `getCountByShow`, `getNewVideosForWeb`, `getMostWatchedShowsForWeb`
- `src/Program/Repository/VideoexRepository.php` — přidat `getPaginatorByShow`, `getCountByShow`
- `config/routes/program.yaml` — přidat/upravit web routes

### Referenční soubory (jen pro čtení)
- `polar/module/Program/src/Controller/Web/WebListController.php` — zdrojový Laminas controller
- `polar/module/Program/view/program/web/web-list/*.phtml` — zdrojové Laminas šablony
- `polar/module/Program/src/Model/*/MariaDbSqlRepository.php` — zdrojové Laminas repositories

---

## Klíčové konverze: Laminas → Symfony

| Laminas | Symfony |
|---|---|
| `$show->getId()` | `$show['id']` |
| `$show->getTitle()` | `$show['title']` |
| `$show->isStatus()` | `(bool)$show['status']` |
| `$show->getSeoDescription()` | `$show['seo_description']` |
| `$show->getSeoKeywords()` | `$show['seo_keywords']` |
| `$show->getContent()` | `$show['content']` |
| `$show->getImage()` | `$show['image']` |
| `$show->getUrl()` | `$show['url']` |
| `$show->getShortDescription()` | `$show['short_description']` |
| `$program->getVideoId()` | `$program['video_id']` |
| `$program->getTime()` | `$program['time']` |
| `$program->getOverwrite()` | `$program['overwrite']` |
| `$program->getFile()` | `$program['file']` |
| `$video->getName()` | `$video['name']` |
| `$video->getPath()` | `$video['path']` |
| `$video->getSizeLq()` | `$video['size_lq']` |
| `$this->url('route/name')` v šabloně | `$view->path('route_name')` |
| `$this->basePath('path')` v šabloně | `$view->asset('path')` |
| `$this->inlineScript()->appendScript(...)` | `$view->addInlineScript(...)` |
| `$this->headLink()->appendStylesheet(...)` | `$view->addHeadLink('stylesheet', ...)` |
| `$this->navigation(...)→breadcrumbs()` | statické breadcrumby `$view->setBreadcrumbs(...)` |
| Laminas Paginator objekt | array z `getPaginatorByShow(id, page, limit)` + int z `getCountByShow(id)` |

---

## Task 1: Routes — přidat web routes do program.yaml

**Files:**
- Modify: `config/routes/program.yaml`

> **Poznámka:** `program_show_N` a `program_showex_N` routes jsou auto-generované (`program_show_generated.yaml`, `program_showex_generated.yaml`). Ručně spravujeme jen `/program`, `/porady` a stahování.

- [ ] **Step 1: Přidat/upravit routes v program.yaml**

Aktuální stav souboru začíná:
```yaml
# Program - Web
program_web:
  path: /program
  controller: App\Program\Controller\Web\ShowController::index
```

Nahradit tuto sekci (a přidat nové routes):
```yaml
# Program - Web
program_web:
  path: /program
  controller: App\Program\Controller\Web\ProgramController::program

program_web_json_get_program:
  path: /program/json/get-program-for-web
  controller: App\Program\Controller\Web\ProgramController::getProgramForWeb

program_web_json_get_program2:
  path: /program/json/get-program2-for-web
  controller: App\Program\Controller\Web\ProgramController::getProgram2ForWeb

program_web_json_get_program_hd:
  path: /program/json/get-program-for-hd
  controller: App\Program\Controller\Web\ProgramController::getProgramForHd

program_web_shows:
  path: /porady
  controller: App\Program\Controller\Web\ShowController::shows

program_web_download:
  path: /porady/stahnout-video/{video_id}/{quality}
  controller: App\Program\Controller\Web\DownloadController::download
  requirements:
    video_id: '\d+'
    quality: '(hq|lq)'

program_web_overwrite_docx:
  path: /porady/prepis/{video_id}
  controller: App\Program\Controller\Web\DownloadController::overwriteDocx
  requirements:
    video_id: '\d+'

program_web_downloadex:
  path: /mimoradne/stahnout-video/{video_id}/{quality}
  controller: App\Program\Controller\Web\DownloadController::downloadex
  requirements:
    video_id: '\d+'
    quality: '(hq|lq)'
```

- [ ] **Step 2: Ověřit, že routes jsou dostupné**

```bash
cd c:\web\www\polar-symfony
php bin/console debug:router | findstr program_web
```

Očekávaný výstup obsahuje: `program_web`, `program_web_shows`, `program_web_download` atd.

- [ ] **Step 3: Commit**

```bash
git add config/routes/program.yaml
git commit -m "feat: add Program web routes to program.yaml"
```

---

## Task 2: ShowWriteController — opravit createConfig() pro split show/video metod

**Files:**
- Modify: `src/Program/Controller/Admin/ShowWriteController.php:797-814`

Aktuálně `createConfig()` generuje obě routes s `ShowController::index`. Změníme na `::show` a `::video`.

- [ ] **Step 1: Najít a opravit createConfig()**

V souboru `src/Program/Controller/Admin/ShowWriteController.php` najdi část:
```php
		$yaml .= 'program_show_' . $row['id'] . ":\n";
		$yaml .= '  path: /porady/' . $row['url'] . "\n";
		$yaml .= "  controller: App\\Program\\Controller\\Web\\ShowController::index\n";
		$yaml .= "\n";

		$yaml .= 'program_show_video_' . $row['id'] . ":\n";
		$yaml .= '  path: /porady/' . $row['url'] . '/{program_url}' . "\n";
		$yaml .= "  controller: App\\Program\\Controller\\Web\\ShowController::index\n";
```

Nahradit za:
```php
		$yaml .= 'program_show_' . $row['id'] . ":\n";
		$yaml .= '  path: /porady/' . $row['url'] . "\n";
		$yaml .= "  controller: App\\Program\\Controller\\Web\\ShowController::show\n";
		$yaml .= "\n";

		$yaml .= 'program_show_video_' . $row['id'] . ":\n";
		$yaml .= '  path: /porady/' . $row['url'] . '/{program_url}' . "\n";
		$yaml .= "  controller: App\\Program\\Controller\\Web\\ShowController::video\n";
```

- [ ] **Step 2: Přidat createConfig() do ShowexWriteController**

Na konec třídy `ShowexWriteController` (před poslední `}`) přidat:

```php
	private function createConfig(ShowexRepository $showexRepository): void
	{
		$configDir = dirname($this->PUBLIC_PATH) . '/config/';
		$routesDir = $configDir . 'routes/';

		if (!is_dir($routesDir) && !mkdir($concurrentDirectory = $routesDir, 0777, true) && !is_dir($concurrentDirectory)) {
			throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
		}

		$routes = $showexRepository->fetchRoutesForConfig();
		$yaml = "# Auto-generated Program Showex routes - DO NOT EDIT MANUALLY\n";
		$yaml .= "# Generated: " . date('Y-m-d H:i:s') . "\n\n";

		foreach ($routes as $row) {
			$yaml .= 'program_showex_' . $row['id'] . ":\n";
			$yaml .= '  path: /mimoradne/' . $row['url'] . "\n";
			$yaml .= "  controller: App\\Program\\Controller\\Web\\ShowexController::showex\n";
			$yaml .= "\n";

			$yaml .= 'program_showex_video_' . $row['id'] . ":\n";
			$yaml .= '  path: /mimoradne/' . $row['url'] . '/{video_url}' . "\n";
			$yaml .= "  controller: App\\Program\\Controller\\Web\\ShowexController::videoex\n";
			$yaml .= "  requirements:\n";
			$yaml .= "    video_url: '[a-zA-Z0-9][a-zA-Z0-9_-]+'\n";
			$yaml .= "\n";
		}

		file_put_contents($routesDir . 'program_showex_generated.yaml', $yaml);
	}
```

- [ ] **Step 3: Zavolat createConfig() v metodách add/edit showexu**

Ve `ShowexWriteController`, v metodách `add()` a `edit()` (a `delete()` pokud existuje), po úspěšném uložení přidat volání `$this->createConfig($showexRepository)`. Přidej `ShowexRepository $showexRepository` jako parametr metody pokud tam ještě není.

Najdi v metodě `add()` část kde se vrací redirect po úspěchu:
```php
			return new RedirectResponse($urlGenerator->generate('admin_program_showex'));
```
Přidat PŘED tento řádek:
```php
			$this->createConfig($showexRepository);
```

Stejný postup pro `edit()`.

- [ ] **Step 4: Ručně regenerovat config**

Otevři admin panel, najdi libovolný Pořad v admin sekci Pořady a ulož ho (klikni Edit → Save). Tím se přegeneruje `program_show_generated.yaml` s novými metodami `::show` a `::video`.

Pak otevři admin panel, najdi libovolný Mimořádný pořad a ulož ho. Tím se vygeneruje `program_showex_generated.yaml`.

Alternativně ručně přepište soubor `config/routes/program_show_generated.yaml` — pro každý řádek s `::index` změňte na `::show` (pro `program_show_N`) nebo `::video` (pro `program_show_video_N`).

- [ ] **Step 5: Commit**

```bash
git add src/Program/Controller/Admin/ShowWriteController.php
git add src/Program/Controller/Admin/ShowexWriteController.php
git add config/routes/program_show_generated.yaml
git add config/routes/program_showex_generated.yaml
git commit -m "feat: split ShowController::index to show/video, add ShowexController config generation"
```

---

## Task 3: ProgramRepository — web metody

**Files:**
- Modify: `src/Program/Repository/ProgramRepository.php`

- [ ] **Step 1: Přidat metody na konec třídy**

Přidat před poslední `}` v souboru:

```php
	/**
	 * Vrátí položky programu pro daný den (YYYY-MM-DD).
	 * Zahrnuje join na video a show pro zobrazení odkazu na video.
	 */
	public function fetchForWeb(string $date): array
	{
		$rows = $this->connection->createQueryBuilder()
			->select(
				'program.id',
				'program.time',
				'program.premiere',
				'program.title',
				'program.short_description',
				'program.url',
				'program_videos.name AS video_name',
				'program_shows.id AS show_id',
				'program_shows.url AS show_url'
			)
			->from('program')
			->leftJoin('program', 'program_videos', 'program_videos', 'program_videos.id = program.video_id')
			->leftJoin('program', 'program2shows', 'program2shows', 'program2shows.program_id = program.id')
			->leftJoin('program2shows', 'program_shows', 'program_shows', 'program_shows.id = program2shows.show_id')
			->where('program.time LIKE :date')
			->setParameter('date', $date . '%')
			->orderBy('program.time', 'ASC')
			->fetchAllAssociative();

		foreach ($rows as $i => $row) {
			$rows[$i]['id'] = (int) $row['id'];
			$rows[$i]['premiere'] = (bool) $row['premiere'];
		}

		return $rows;
	}

	/**
	 * Vrátí program2 od aktuálního vysílání do +2 dny, seskupený podle data.
	 */
	public function getProgram2FromNow(): ?array
	{
		$now = new \DateTime();

		$lastRow = $this->connection->createQueryBuilder()
			->select('time')
			->from('program2')
			->where('time < :now')
			->setParameter('now', $now->format('Y-m-d H:i:s'))
			->orderBy('time', 'DESC')
			->setMaxResults(1)
			->fetchAssociative();

		if (!$lastRow) {
			return null;
		}

		$rows = $this->connection->createQueryBuilder()
			->select('time', 'premiere', 'title', 'short_description')
			->from('program2')
			->where('time >= :from')
			->andWhere('DATE(time) <= :to')
			->setParameter('from', $lastRow['time'])
			->setParameter('to', $now->modify('+2 days')->format('Y-m-d'))
			->orderBy('time', 'ASC')
			->fetchAllAssociative();

		if (!$rows) {
			return null;
		}

		$data = [];
		foreach ($rows as $item) {
			$date = new \DateTime($item['time']);
			$data[$date->format('Y-m-d')][] = $item;
		}

		return $data;
	}

	/**
	 * Vrátí program od aktuálního vysílání do +2 dny, seskupený podle data.
	 */
	public function getProgramFromNow(): ?array
	{
		$now = new \DateTime();

		$lastRow = $this->connection->createQueryBuilder()
			->select('time')
			->from('program')
			->where('time < :now')
			->setParameter('now', $now->format('Y-m-d H:i:s'))
			->orderBy('time', 'DESC')
			->setMaxResults(1)
			->fetchAssociative();

		if (!$lastRow) {
			return null;
		}

		$rows = $this->connection->createQueryBuilder()
			->select('time', 'premiere', 'title', 'short_description')
			->from('program')
			->where('time >= :from')
			->andWhere('DATE(time) <= :to')
			->setParameter('from', $lastRow['time'])
			->setParameter('to', $now->modify('+2 days')->format('Y-m-d'))
			->orderBy('time', 'ASC')
			->fetchAllAssociative();

		if (!$rows) {
			return null;
		}

		$data = [];
		foreach ($rows as $item) {
			$date = new \DateTime($item['time']);
			$data[$date->format('Y-m-d')][] = $item;
		}

		return $data;
	}
```

- [ ] **Step 2: Ověřit syntax**

```bash
php -l src/Program/Repository/ProgramRepository.php
```

Očekávaný výstup: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add src/Program/Repository/ProgramRepository.php
git commit -m "feat: add fetchForWeb, getProgram2FromNow, getProgramFromNow to ProgramRepository"
```

---

## Task 4: ShowRepository — web metody

**Files:**
- Modify: `src/Program/Repository/ShowRepository.php`

- [ ] **Step 1: Přidat metody na konec třídy**

Přidat před poslední `}`:

```php
	/**
	 * Vrátí HTML string s časy vysílání pořadu (skupiny dnů s časy).
	 */
	public function fetchTimesForWeb(int $show_id): string
	{
		$rows = $this->connection->createQueryBuilder()
			->select('*')
			->from($this->tableShowsTimes)
			->where('show_id = :show_id')
			->setParameter('show_id', $show_id)
			->orderBy('FIELD(day, "PO", "UT", "ST", "CT", "PA", "SO", "NE", "PREM", "REPR"), time', 'ASC')
			->fetchAllAssociative();

		if (!$rows) {
			return '';
		}

		$dataTmp = [];
		foreach ($rows as $time) {
			$dataTmp[$time['day']][] = [
				'id' => $time['id'],
				'time' => $time['time'],
				'premiere' => $time['premiere'],
			];
		}

		$days = [
			'PO' => 'Pondělí',
			'UT' => 'Úterý',
			'ST' => 'Středa',
			'CT' => 'Čtvrtek',
			'PA' => 'Pátek',
			'SO' => 'Sobota',
			'NE' => 'Neděle',
			'PREM' => 'Premiéra',
			'REPR' => 'Repríza',
		];

		$data = '<div class="row pt-2">';
		if ($dataTmp) {
			foreach ($dataTmp as $day => $items) {
				$data .= '<div class="col-sm-2 col-xs-3"><strong>' . $days[$day] . '</strong></div>';
				$data .= '<div class="col-sm-10 col-xs-9">';
				foreach ($items as $item) {
					if ($item['premiere']) {
						$data .= '<strong class="text-color-primary">' . $item['time'] . '</strong>, ';
					} else {
						$data .= $item['time'] . ', ';
					}
				}
				$data = mb_substr($data, 0, -2, 'UTF-8');
				$data .= '</div>';
			}
		} else {
			$data .= '<div class="col-md-12"><strong>Tento pořad momentálně není v TV POLAR vysílán.</strong></div>';
		}
		$data .= '</div>';

		return $data;
	}

	/**
	 * Vrátí pořady seskupené podle kategorií, volitelně filtrované podle hledaného výrazu.
	 * Zahrnuje i mimořádné pořady (special_shows) a "Hosté ve studiu".
	 */
	public function fetchAllByCategories(string $search): ?array
	{
		$categories = $this->connection->createQueryBuilder()
			->select('id AS category_id', 'title AS category_title', 'url AS category_url')
			->from($this->tableShowsCategories)
			->orderBy('rank', 'ASC')
			->fetchAllAssociative();

		$data = [];

		foreach ($categories as $category) {
			$qb = $this->connection->createQueryBuilder()
				->select('*, "ne" AS special')
				->from($this->table)
				->where('category_id = :cat')
				->andWhere('show_in_archive = 1')
				->andWhere('status = 1')
				->setParameter('cat', $category['category_id'])
				->orderBy('`order`', 'ASC');

			if ($search !== '') {
				$qb->andWhere('LOWER(CONVERT(title USING utf8)) LIKE :search')
					->setParameter('search', '%' . mb_strtolower($search, 'UTF-8') . '%');
			}

			$shows = $qb->fetchAllAssociative();

			$qb2 = $this->connection->createQueryBuilder()
				->select('*, "ano" AS special')
				->from('special_shows')
				->where('category_id = :cat')
				->andWhere('status = 1')
				->setParameter('cat', $category['category_id'])
				->orderBy('`order`', 'ASC');

			if ($search !== '') {
				$qb2->andWhere('LOWER(CONVERT(title USING utf8)) LIKE :search')
					->setParameter('search', '%' . mb_strtolower($search, 'UTF-8') . '%');
			}

			$showsEx = $qb2->fetchAllAssociative();

			$output = array_merge($shows, $showsEx);

			foreach ($output as $item) {
				$data[$category['category_id']]['category_id'] = $category['category_id'];
				$data[$category['category_id']]['category_title'] = $category['category_title'];
				$data[$category['category_id']]['category_url'] = $category['category_url'];
				$data[$category['category_id']]['shows'][] = $item;
			}
		}

		/* doplnění Hosté ve studiu */
		$data[1]['shows'][] = [
			'id' => '99',
			'title' => 'Hosté ve studiu',
			'short_description' => 'Zde najdete rozhovory se všemi osobnostmi a představiteli z MS kraje, kteří kdy byli pozváni do studia TV POLAR',
			'image' => 'data/program/show/hoste/small.png',
			'thumb' => 'data/program/show/hoste/small.png',
			'special' => 'hoste',
		];

		return $data;
	}

	/**
	 * Vrátí pořady a mimořádné pořady pro našeptávač vyhledávání.
	 */
	public function fetchForAutocomplete(): array
	{
		$shows = $this->connection->createQueryBuilder()
			->select('url', 'title', '"ne" AS special')
			->from($this->table)
			->where('show_in_archive = 1')
			->andWhere('status = 1')
			->orderBy('`order`', 'ASC')
			->fetchAllAssociative();

		$showsEx = $this->connection->createQueryBuilder()
			->select('url', 'title', '"ano" AS special')
			->from('special_shows')
			->where('status = 1')
			->orderBy('`order`', 'ASC')
			->fetchAllAssociative();

		return array_merge($shows, $showsEx);
	}
```

> **Poznámka:** `$this->tableShowsCategories` — ověř, že tato property je definována v konstruktoru. Pokud ne, přidej ji. Zkontroluj `ShowRepository::__construct`.

- [ ] **Step 2: Ověřit, že tableShowsCategories je v konstruktoru**

Otevři `src/Program/Repository/ShowRepository.php` a zkontroluj konstruktor. Pokud `$this->tableShowsCategories` chybí, přidej do konstruktoru:
```php
private string $tableShowsCategories = 'program_shows_categories';
```
jako property třídy (spolu s ostatními `$table*` properties).

- [ ] **Step 3: Ověřit syntax**

```bash
php -l src/Program/Repository/ShowRepository.php
```

- [ ] **Step 4: Commit**

```bash
git add src/Program/Repository/ShowRepository.php
git commit -m "feat: add fetchTimesForWeb, fetchAllByCategories, fetchForAutocomplete to ShowRepository"
```

---

## Task 5: VideoRepository — web metody

**Files:**
- Modify: `src/Program/Repository/VideoRepository.php`

- [ ] **Step 1: Přidat metody na konec třídy**

```php
	/**
	 * Vrátí videa pořadu stránkovaně (premiéry daného show).
	 */
	public function getPaginatorByShow(int $show_id, int $page, int $limit): array
	{
		$offset = ($page - 1) * $limit;

		return $this->connection->createQueryBuilder()
			->select(
				'program_videos.id',
				'program_videos.name',
				'program_videos.path',
				'program_videos.duration',
				'program.title',
				'program.short_description',
				'program.description',
				'program.url',
				'program.time',
				'program_shows.id AS show_id'
			)
			->from($this->table)
			->leftJoin($this->table, 'program', 'program', 'program.video_id = program_videos.id')
			->leftJoin('program', 'program2shows', 'program2shows', 'program2shows.program_id = program.id')
			->leftJoin('program2shows', 'program_shows', 'program_shows', 'program_shows.id = program2shows.show_id')
			->where('program.premiere = 1')
			->andWhere('program_shows.id = :show_id')
			->andWhere('program.time < NOW()')
			->setParameter('show_id', $show_id)
			->orderBy('program.time', 'DESC')
			->setFirstResult($offset)
			->setMaxResults($limit)
			->fetchAllAssociative();
	}

	/**
	 * Vrátí celkový počet videí pořadu (premiéry).
	 */
	public function getCountByShow(int $show_id): int
	{
		return (int) $this->connection->createQueryBuilder()
			->select('COUNT(*)')
			->from($this->table)
			->leftJoin($this->table, 'program', 'program', 'program.video_id = program_videos.id')
			->leftJoin('program', 'program2shows', 'program2shows', 'program2shows.program_id = program.id')
			->leftJoin('program2shows', 'program_shows', 'program_shows', 'program_shows.id = program2shows.show_id')
			->where('program.premiere = 1')
			->andWhere('program_shows.id = :show_id')
			->andWhere('program.time < NOW()')
			->setParameter('show_id', $show_id)
			->fetchOne();
	}

	/**
	 * Vrátí N nejnovějších videí pro web (s URL a obrázkem thumbnailu).
	 */
	public function getNewVideosForWeb(int $limit): array
	{
		$rows = $this->connection->createQueryBuilder()
			->select(
				'program_videos.name',
				'program_videos.duration',
				'program.time',
				'program.title',
				'program.short_description',
				'program.url',
				'program_shows.url AS show_url'
			)
			->from($this->table)
			->innerJoin($this->table, 'program', 'program', 'program.video_id = program_videos.id')
			->innerJoin('program', 'program2shows', 'program2shows', 'program2shows.program_id = program.id')
			->innerJoin('program2shows', 'program_shows', 'program_shows', 'program_shows.id = program2shows.show_id')
			->where('program.premiere = 1')
			->orderBy('program.time', 'DESC')
			->setMaxResults($limit)
			->fetchAllAssociative();

		foreach ($rows as $i => $row) {
			$rows[$i]['url'] = '/porady/' . $row['show_url'] . '/' . $row['url'];
			$short_desc = (string) ($row['short_description'] ?? '');
			$rows[$i]['anotation'] = mb_substr($short_desc, 0, 160, 'UTF-8') . ((mb_strlen($short_desc, 'UTF-8') > 160) ? '...' : '');
			$rows[$i]['image'] = '/data/program/thumbs/' . $row['name'] . '.jpg';
			unset($rows[$i]['name'], $rows[$i]['show_url'], $rows[$i]['short_description']);
		}

		return $rows;
	}

	/**
	 * Vrátí N nejsledovanějších pořadů posledních 3 dní (dle showed count videa).
	 */
	public function getMostWatchedShowsForWeb(int $limit): array
	{
		$rows = $this->connection->createQueryBuilder()
			->select(
				'program_videos.name',
				'program.time',
				'program.title',
				'program.short_description AS anotation',
				'program.url',
				'program_shows.url AS show_url'
			)
			->from($this->table)
			->innerJoin($this->table, 'program', 'program', 'program.video_id = program_videos.id')
			->innerJoin('program', 'program2shows', 'program2shows', 'program2shows.program_id = program.id')
			->innerJoin('program2shows', 'program_shows', 'program_shows', 'program_shows.id = program2shows.show_id')
			->where('program.premiere = 1')
			->andWhere('DATE(program.time) >= DATE(DATE_ADD(NOW(), INTERVAL -3 DAY))')
			->andWhere('DATE(program.time) <= NOW()')
			->orderBy('program_videos.showed', 'DESC')
			->setMaxResults($limit)
			->fetchAllAssociative();

		foreach ($rows as $i => $row) {
			$rows[$i]['url'] = '/porady/' . $row['show_url'] . '/' . $row['url'];
			$anotation = (string) ($row['anotation'] ?? '');
			$rows[$i]['anotation'] = $anotation ? mb_substr($anotation, 0, 160, 'UTF-8') . ((mb_strlen($anotation, 'UTF-8') > 160) ? '...' : '') : '';
			$rows[$i]['image'] = '/data/program/thumbs/' . $row['name'] . '.jpg';
			unset($rows[$i]['name'], $rows[$i]['show_url']);
		}

		return $rows;
	}
```

- [ ] **Step 2: Ověřit syntax**

```bash
php -l src/Program/Repository/VideoRepository.php
```

- [ ] **Step 3: Commit**

```bash
git add src/Program/Repository/VideoRepository.php
git commit -m "feat: add getPaginatorByShow, getCountByShow, getNewVideosForWeb, getMostWatchedShowsForWeb to VideoRepository"
```

---

## Task 6: VideoexRepository — web metody

**Files:**
- Modify: `src/Program/Repository/VideoexRepository.php`

Zkontroluj název tabulky videoex — v Laminas se jmenuje `special_videos`. V Symfony zkontroluj `$this->table` v konstruktoru.

- [ ] **Step 1: Zjistit název tabulky videoex**

```bash
Select-String -Path "src/Program/Repository/VideoexRepository.php" -Pattern "table\s*=" | Select-Object Line
```

Pokud `$this->table = 'special_videos'`, metody níže jsou správné.

- [ ] **Step 2: Přidat metody na konec třídy VideoexRepository**

```php
	/**
	 * Vrátí videa mimořádného pořadu stránkovaně.
	 */
	public function getPaginatorByShow(int $show_id, int $page, int $limit): array
	{
		$offset = ($page - 1) * $limit;

		return $this->connection->createQueryBuilder()
			->select(
				'special_videos.id',
				'special_videos.name',
				'special_videos.path',
				'special_videos.duration',
				'special_videos.title',
				'special_videos.short_description',
				'special_videos.url',
				'special_videos.time',
				'special_shows.id AS show_id'
			)
			->from($this->table)
			->leftJoin($this->table, 'special_shows', 'special_shows', 'special_shows.id = special_videos.show_id')
			->where('special_shows.id = :show_id')
			->setParameter('show_id', $show_id)
			->orderBy('special_videos.time', 'DESC')
			->setFirstResult($offset)
			->setMaxResults($limit)
			->fetchAllAssociative();
	}

	/**
	 * Vrátí celkový počet videí mimořádného pořadu.
	 */
	public function getCountByShow(int $show_id): int
	{
		return (int) $this->connection->createQueryBuilder()
			->select('COUNT(*)')
			->from($this->table)
			->leftJoin($this->table, 'special_shows', 'special_shows', 'special_shows.id = special_videos.show_id')
			->where('special_shows.id = :show_id')
			->setParameter('show_id', $show_id)
			->fetchOne();
	}
```

> **Poznámka:** Zkontroluj v Laminas `VideoexRepository` jaký sloupec spojuje video se showem (`show_id` ve `special_videos`?). Pokud je jiný, uprav JOIN podmínku.

- [ ] **Step 3: Ověřit syntax**

```bash
php -l src/Program/Repository/VideoexRepository.php
```

- [ ] **Step 4: Commit**

```bash
git add src/Program/Repository/VideoexRepository.php
git commit -m "feat: add getPaginatorByShow, getCountByShow to VideoexRepository"
```

---

## Task 7: ProgramController — TV program stránka + JSON endpointy

**Files:**
- Create: `src/Program/Controller/Web/ProgramController.php`

Referenční metody v Laminas: `programAction()`, `getProgramForWebAction()`, `getProgram2ForWebAction()`, `getProgramForHdAction()`.

- [ ] **Step 1: Vytvořit ProgramController.php**

```php
<?php

namespace App\Program\Controller\Web;

use App\Application\View\PhtmlRenderer;
use App\Banner\Repository\BannerRepository;
use App\Program\Repository\ProgramRepository;
use App\Program\Repository\VideoRepository;
use DateTime;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Transliterator;

final class ProgramController
{
	public function __construct(
		private ProgramRepository $programRepository,
		private VideoRepository $videoRepository,
		private BannerRepository $bannerRepository,
		private UrlGeneratorInterface $urlGenerator,
	) {}

	public function program(Request $request, PhtmlRenderer $renderer): Response
	{
		$mostWatchedShows = null;
		try {
			$mostWatchedShows = $this->videoRepository->getMostWatchedShowsForWeb(5);
		} catch (Exception) {
		}

		$bannerLeaderboard = null;
		$bannerMobilesticky = null;
		try {
			$bannerLeaderboard = $this->bannerRepository->getLeaderboard();
			$bannerMobilesticky = $this->bannerRepository->getMobilesticky();
		} catch (Exception) {
		}

		return new Response($renderer->renderWithLayout('program/web/program', [
			'mostWatchedShows' => $mostWatchedShows,
			'bannerLeaderboard' => $bannerLeaderboard,
			'bannerMobilesticky' => $bannerMobilesticky,
			'schemeHost' => $request->getSchemeAndHttpHost(),
			'currentUrl' => $request->getUri(),
		]));
	}

	public function getProgramForWeb(Request $request): JsonResponse
	{
		$date = $request->query->get('date', '');
		$content = '';
		$success = true;
		$id = null;

		try {
			$program = $this->programRepository->fetchForWeb($date);

			if ($program) {
				$i = 0;
				$ranges = [1 => 4, 2 => 4, 3 => 4, 4 => 4, 5 => 4];
				$backgrounds = [1 => 'bg-primary', 2 => 'bg-primary', 3 => 'bg-00adee', 4 => 'bg-00adee', 5 => 'bg-secondary', 6 => 'bg-secondary'];
				$now = new DateTime();
				$range = new DateTime($date . ' 00:00:00');
				$range2 = new DateTime($date . ' 04:00:00');
				$show = (($now >= $range) && ($now <= $range2)) ? 'show' : '';
				$collapsed = (($now >= $range) && ($now <= $range2)) ? '' : ' collapsed';
				$nextTime = false;
				$content .=
					'<div id="accordion" class="accordion accordion-modern-status accordion-modern-status-arrow">' .
					'<div class="card card-default mt-1">' .
					'<div class="card-header" id="collapse' . $i . 'Heading">' .
					'<h3 class="card-title m-0">' .
					'<a class="accordion-toggle text-color-light ' . $collapsed . ' ' . $backgrounds[$i + 1] . ' px-3 py-2" data-bs-toggle="collapse" data-bs-target="#collapse' . $i . '"href="#collapse' . $i . '">' .
					'<i class="fa fa-fw fa-clock"></i> ' .
					$range->format('H:i') . ' - ' . $range2->format('H:i') .
					'</a></h3></div>' .
					'<div id="collapse' . $i . '" class="collapse ' . $show . '" data-bs-parent="#accordion">' .
					'<div class="card-body p-3">';

				foreach ($program as $item) {
					$time = new DateTime($item['time']);

					if ($time >= $range2) {
						$content .= '</div></div></div>';
						$i++;
						$range = new DateTime($range2->format('Y-m-d H:i:s'));
						$range2->modify('+' . $ranges[$i] . ' hours');
						$show = (($now >= $range) && ($now <= $range2)) ? 'show' : '';
						$collapsed = (($now >= $range) && ($now <= $range2)) ? '' : ' collapsed';
						$content .=
							'<div class="card card mt-1">' .
							'<div class="card-header" id="collapse' . $i . 'Heading">' .
							'<h3 class="card-title m-0">' .
							'<a class="accordion-toggle text-color-light ' . $collapsed . ' ' . $backgrounds[$i + 1] . ' px-3 py-2" data-bs-toggle="collapse" data-bs-target="#collapse' . $i . '"href="#collapse' . $i . '">' .
							'<i class="fa fa-fw fa-clock"></i> ' .
							$range->format('H:i') . ' - ' . $range2->format('H:i') .
							'</a></h3></div>' .
							'<div id="collapse' . $i . '" class="collapse ' . $show . '" data-bs-parent="#accordion">' .
							'<div class="card-body p-3">';
					}

					// Text na UL->LI
					$description = $item['short_description'];
					if ($description && str_contains($description, PHP_EOL)) {
						$tmpDescription = '';
						foreach (explode(PHP_EOL, $description) as $row) {
							$tmpDescription .= trim(preg_replace('/\s\s+/', ' ', $row)) . '; ';
						}
						$description = substr($tmpDescription, 0, -2);
					}

					$content .= '<div id="item-' . $this->removeAccent($item['time'], '-') . '" class="item"><div class="row">';
					if ($item['video_name'] && $item['show_id'] && $item['show_url'] && ($time < $now)) {
						$videoUrl = $this->urlGenerator->generate('program_show_' . $item['show_id'], ['program_url' => $item['url']]);
						$content .=
							'<div class="col-12">' .
							'<h4 class="text-4 font-weight-500 text-primary mb-0">' .
							'<span class="time">' . $time->format('H:i') . '</span>' .
							'&nbsp;&nbsp;<i class="fa fa-angle-right"></i>&nbsp;&nbsp;' .
							'<a class="title" href="' . $videoUrl . '" title="' . htmlspecialchars($item['title']) . '">' .
							htmlspecialchars($item['title']) .
							($item['premiere'] ? ' (P)' : '') .
							'</a></h4>' .
							'<p class="text-color-secondary mb-3">' . htmlspecialchars((string)$description) . '</p>' .
							'</div>';
					} else {
						$content .=
							'<div class="col-12">' .
							'<h4 class="text-4 font-weight-500 text-primary mb-0">' .
							'<span class="time">' . $time->format('H:i') . '</span>' .
							'&nbsp;&nbsp;<i class="fa fa-angle-right"></i>&nbsp;&nbsp;' .
							htmlspecialchars($item['title']) .
							($item['premiere'] ? ' (P)' : '') .
							'</h4>' .
							'<p class="text-color-secondary mb-3">' . htmlspecialchars((string)$description) . '</p>' .
							'</div>';
					}
					$content .= '</div></div>';

					if (($now > $time) && ($now->format('Y-m-d') === $time->format('Y-m-d'))) {
						$id = $this->removeAccent($item['time'], '-');
					} elseif (!$nextTime) {
						$nextTime = strtotime($time->format('Y-m-d H:i:s')) - strtotime($now->format('Y-m-d H:i:s'));
					}
				}
				$content .= '</div></div></div></div>';
			} else {
				$content = '<div class="alert alert-warning" role="alert">Program nebyl nalezen.</div>';
			}
		} catch (Exception $e) {
			$success = $e->getMessage();
		}

		return new JsonResponse([
			'success' => $success,
			'date' => $date,
			'active' => $id,
			'content' => $content,
		]);
	}

	public function getProgram2ForWeb(): JsonResponse
	{
		$success = true;
		$message = null;
		$content = null;
		$reload = null;

		try {
			$program = $this->programRepository->getProgram2FromNow();

			if ($program) {
				$content = '';
				$i = 1;
				$first = true;
				foreach ($program as $day) {
					$content .=
						'<div id="program2-' . $i . '" class="nano' . ($first ? '' : ' hide') . '">' .
						'<div class="nano-content">';
					foreach ($day as $item) {
						$datetime = new DateTime($item['time']);
						$content .=
							'<div class="item font-weight-bold' . ($first ? ' text-color-secondary' : '') . '">' .
							$datetime->format('H:i') . ' <i class="fa fa-angle-right"></i> ' . htmlspecialchars($item['title']) .
							'</div>' .
							'<div class="description text-muted mb-2 line-height-2">' . htmlspecialchars((string)$item['short_description']) . '</div>';
						$first = false;
					}
					$content .= '</div></div>';
					$i++;
				}
				$reload = new DateTime();
				$now = new DateTime();
				$dayKey = $now->format('Y-m-d');
				if (isset($program[$dayKey][1]['time'])) {
					$nextItemTime = new DateTime($program[$dayKey][1]['time']);
					$interval = $nextItemTime->diff($reload);
					$reload = ($interval->days * 86400) + ($interval->h * 3600) + ($interval->i * 60) + $interval->s;
					// přičíst vteřinu pro správný reload
					$reload++;
				} else {
					$reload = null;
				}
			}
		} catch (Exception $e) {
			$success = false;
			$message = $e->getMessage();
		}

		return new JsonResponse([
			'content' => $content,
			'reload' => $reload,
			'success' => $success,
			'message' => $message,
		]);
	}

	public function getProgramForHd(): JsonResponse
	{
		$success = true;
		$message = null;
		$content = null;
		$reload = null;

		try {
			$program = $this->programRepository->getProgramFromNow();

			if ($program) {
				$content = '';
				$i = 1;
				$first = true;
				foreach ($program as $day) {
					$content .=
						'<div id="program-' . $i . '" class="nano' . ($first ? '' : ' hide') . '">' .
						'<div class="nano-content">';
					foreach ($day as $item) {
						$datetime = new DateTime($item['time']);
						$content .=
							'<div class="item font-weight-bold' . ($first ? ' text-color-secondary' : '') . '">' .
							$datetime->format('H:i') . ' <i class="fa fa-angle-right"></i> ' . htmlspecialchars($item['title']) .
							'</div>' .
							'<div class="description text-muted mb-2 line-height-2">' . htmlspecialchars((string)$item['short_description']) . '</div>';
						$first = false;
					}
					$content .= '</div></div>';
					$i++;
				}
				$reload = new DateTime();
				$now = new DateTime();
				$dayKey = $now->format('Y-m-d');
				if (isset($program[$dayKey][1]['time'])) {
					$nextItemTime = new DateTime($program[$dayKey][1]['time']);
					$interval = $nextItemTime->diff($reload);
					$reload = ($interval->days * 86400) + ($interval->h * 3600) + ($interval->i * 60) + $interval->s;
					// přičíst vteřinu pro správný reload
					$reload++;
				} else {
					$reload = null;
				}
			}
		} catch (Exception $e) {
			$success = false;
			$message = $e->getMessage();
		}

		return new JsonResponse([
			'content' => $content,
			'reload' => $reload,
			'success' => $success,
			'message' => $message,
		]);
	}

	private function removeAccent(string $text, ?string $replace = null): string
	{
		$transliterator = Transliterator::createFromRules(':: Any-Latin; :: NFD; :: [:Nonspacing Mark:] Remove; :: NFC; :: [:Punctuation:] Remove; :: Lower();', Transliterator::FORWARD);
		$textTmp = $text;
		if ($transliterator) {
			$textTmp = $transliterator->transliterate($text);
			$textTmp = preg_replace('/[^a-z0-9]+/', '-', $textTmp);
			$textTmp = strtolower($textTmp);
			if ($replace) {
				$textTmp = str_replace(' ', $replace, $textTmp);
			}
		}
		return $textTmp;
	}
}
```

- [ ] **Step 2: Ověřit syntax**

```bash
php -l src/Program/Controller/Web/ProgramController.php
```

- [ ] **Step 3: Commit**

```bash
git add src/Program/Controller/Web/ProgramController.php
git commit -m "feat: create ProgramController with program, getProgramForWeb, getProgram2ForWeb, getProgramForHd"
```

---

## Task 8: ShowController — nahradit stub metodami shows(), show(), video()

**Files:**
- Modify: `src/Program/Controller/Web/ShowController.php`

Referenční Laminas metody: `showsAction()`, `showAction()`, `videoAction()`.

- [ ] **Step 1: Nahradit celý soubor ShowController.php**

```php
<?php

namespace App\Program\Controller\Web;

use App\Application\View\PhtmlRenderer;
use App\Banner\Repository\BannerRepository;
use App\News\Repository\NewsRepository;
use App\Program\Repository\ProgramRepository;
use App\Program\Repository\ShowRepository;
use App\Program\Repository\VideoRepository;
use DateTime;
use Exception;
use IntlDateFormatter;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ShowController
{
	public function __construct(
		private ShowRepository $showRepository,
		private ProgramRepository $programRepository,
		private VideoRepository $videoRepository,
		private NewsRepository $newsRepository,
		private BannerRepository $bannerRepository,
		private UrlGeneratorInterface $urlGenerator,
	) {}

	public function shows(Request $request, PhtmlRenderer $renderer): Response
	{
		$query = (string) $request->query->get('q', '');

		try {
			if ($query !== '' && mb_strlen($query, 'UTF-8') >= 3) {
				// zakázat hledání krátkých slov
				$query = preg_replace('/\s+/', ' ', $query);
				$query = trim($query);
				$query = stripslashes($query);
				$query = htmlspecialchars($query, ENT_QUOTES, 'UTF-8');
			} else {
				$query = '';
			}
			$shows = $this->showRepository->fetchAllByCategories($query);
			$showsForAutocomplete = $this->showRepository->fetchForAutocomplete();
		} catch (Exception $e) {
			return new RedirectResponse($this->urlGenerator->generate('app_homepage'));
		}

		$bannerLeaderboard = $bannerMobilesticky = null;
		try {
			$bannerLeaderboard = $this->bannerRepository->getLeaderboard();
			$bannerMobilesticky = $this->bannerRepository->getMobilesticky();
		} catch (Exception) {
		}

		return new Response($renderer->renderWithLayout('program/web/shows', [
			'shows' => $shows,
			'query' => $query,
			'showsForAutocomplete' => $showsForAutocomplete,
			'bannerLeaderboard' => $bannerLeaderboard,
			'bannerMobilesticky' => $bannerMobilesticky,
			'schemeHost' => $request->getSchemeAndHttpHost(),
			'currentUrl' => $request->getUri(),
		]));
	}

	public function show(Request $request, PhtmlRenderer $renderer): Response
	{
		$show_url = $request->attributes->get('show_url', '');

		if (!$show_url) {
			return new RedirectResponse($this->urlGenerator->generate('program_web_shows'));
		}

		try {
			$show = $this->showRepository->findPostBy('url', $show_url);

			if (!$show || !(bool)$show['status']) {
				return new RedirectResponse($this->urlGenerator->generate('program_web_shows'));
			}

			$times = $this->showRepository->fetchTimesForWeb((int)$show['id']);

			$page = (int) $request->query->get('strana', 1);
			$limit = 10;

			$videos = $this->videoRepository->getPaginatorByShow((int)$show['id'], $page, $limit);
			$videosTotal = $this->videoRepository->getCountByShow((int)$show['id']);

			$pr = $this->newsRepository->getPrArticles(2);
		} catch (Exception) {
			return new RedirectResponse($this->urlGenerator->generate('program_web_shows'));
		}

		$bannerLeaderboard = $bannerMobilesticky = $bannerSquare = null;
		try {
			$bannerLeaderboard = $this->bannerRepository->getLeaderboard();
			$bannerMobilesticky = $this->bannerRepository->getMobilesticky();
			$bannerSquare = $this->bannerRepository->getSquare();
		} catch (Exception) {
		}

		return new Response($renderer->renderWithLayout('program/web/show', [
			'show' => $show,
			'times' => $times,
			'videos' => $videos,
			'videosTotal' => $videosTotal,
			'page' => $page,
			'limit' => $limit,
			'pr' => $pr,
			'bannerSquare' => $bannerSquare,
			'bannerLeaderboard' => $bannerLeaderboard,
			'bannerMobilesticky' => $bannerMobilesticky,
			'schemeHost' => $request->getSchemeAndHttpHost(),
			'currentUrl' => $request->getUri(),
		]));
	}

	public function video(Request $request, PhtmlRenderer $renderer): Response
	{
		$show_url = $request->attributes->get('show_url', '');
		$program_url = $request->attributes->get('program_url', '');

		if (!$show_url) {
			return new RedirectResponse($this->urlGenerator->generate('program_web_shows'));
		}

		$program = null;
		$video = null;
		$newVideos = null;
		$mostWatchedShows = null;

		try {
			$show = $this->showRepository->findPostBy('url', $show_url);

			if (!$show) {
				return new RedirectResponse($this->urlGenerator->generate('program_web_shows'));
			}

			$program = $this->programRepository->findPostBy('url', $program_url);

			if ($program && $program['video_id']) {
				$video = $this->videoRepository->findPostBy('id', (int)$program['video_id']);
			} else {
				return new RedirectResponse($this->urlGenerator->generate('program_show_' . $show['id']));
			}

			$newVideos = $this->videoRepository->getNewVideosForWeb(3);
			$mostWatchedShows = $this->videoRepository->getMostWatchedShowsForWeb(5);
		} catch (Exception $e) {
			return new RedirectResponse($this->urlGenerator->generate('program_web_shows'));
		}

		// sestavíme breadcrumbList pro SEO
		$programTime = new DateTime($program['time']);
		$formatter = new IntlDateFormatter('cs_CZ', IntlDateFormatter::NONE, IntlDateFormatter::NONE, null, null, 'd. MMMM y, H:mm');
		$prettyProgramTime = $formatter->format($programTime);

		$breadcrumbItems = [
			['@type' => 'ListItem', 'position' => 1, 'name' => 'Domů', 'item' => '/'],
			['@type' => 'ListItem', 'position' => 2, 'name' => 'Pořady', 'item' => '/porady'],
			['@type' => 'ListItem', 'position' => 3, 'name' => $show['title'], 'item' => '/porady/' . $show_url],
		];
		$lastBreadcrumbHref = $this->urlGenerator->generate('program_show_video_' . $show['id'], ['program_url' => $program['url']]);
		$breadcrumbItems[] = [
			'@type' => 'ListItem',
			'position' => count($breadcrumbItems) + 1,
			'name' => $prettyProgramTime,
			'item' => $lastBreadcrumbHref,
		];

		$pr = $this->newsRepository->getPrArticles(2);

		$bannerLeaderboard = $bannerMobilesticky = $bannerSquare = null;
		try {
			$bannerLeaderboard = $this->bannerRepository->getLeaderboard();
			$bannerMobilesticky = $this->bannerRepository->getMobilesticky();
			$bannerSquare = $this->bannerRepository->getSquare();
		} catch (Exception) {
		}

		return new Response($renderer->renderWithLayout('program/web/video', [
			'show' => $show,
			'program' => $program,
			'video' => $video,
			'newVideos' => $newVideos,
			'mostWatchedShows' => $mostWatchedShows,
			'pr' => $pr,
			'breadcrumbItems' => $breadcrumbItems,
			'prettyProgramTime' => $prettyProgramTime,
			'bannerSquare' => $bannerSquare,
			'bannerLeaderboard' => $bannerLeaderboard,
			'bannerMobilesticky' => $bannerMobilesticky,
			'schemeHost' => $request->getSchemeAndHttpHost(),
			'currentUrl' => $request->getUri(),
		]));
	}
}
```

> **Poznámka k routes:** Symfony routes `program_show_N` nemají parametr `show_url` v URL (URL je baked-in: `/porady/eko-magazin`). Proto `show_url` není v `$request->attributes` — místo toho použijeme `$request->getPathInfo()` a parsujeme. Nebo lépe: přidáme do generovaných routes parametr `show_url` jako konstantu.

> **Alternativní přístup pro show_url:** Pokud routes neexponují `show_url` jako parametr, musíme ho parsovat z URL: 
> ```php
> $path = $request->getPathInfo(); // "/porady/eko-magazin"
> $parts = explode('/', trim($path, '/'));
> $show_url = $parts[1] ?? ''; // "eko-magazin"
> ```
> Toto použij v `show()` i `video()` místo `$request->attributes->get('show_url')`.

Aktualizuj `show()` a `video()` metody:
```php
// V show():
$path = $request->getPathInfo();
$parts = explode('/', trim($path, '/'));
$show_url = $parts[1] ?? '';

// V video():
$path = $request->getPathInfo();
$parts = explode('/', trim($path, '/'));
$show_url = $parts[1] ?? '';
$program_url = $request->attributes->get('program_url', '');
```

- [ ] **Step 2: Ověřit syntax**

```bash
php -l src/Program/Controller/Web/ShowController.php
```

- [ ] **Step 3: Commit**

```bash
git add src/Program/Controller/Web/ShowController.php
git commit -m "feat: implement ShowController::shows, show, video methods"
```

---

## Task 9: ShowexController — showex() a videoex()

**Files:**
- Create: `src/Program/Controller/Web/ShowexController.php`

Referenční Laminas metody: `showexAction()`, `videoexAction()`.

- [ ] **Step 1: Vytvořit ShowexController.php**

```php
<?php

namespace App\Program\Controller\Web;

use App\Application\View\PhtmlRenderer;
use App\Banner\Repository\BannerRepository;
use App\News\Repository\NewsRepository;
use App\Program\Repository\ShowexRepository;
use App\Program\Repository\VideoexRepository;
use App\Program\Repository\VideoRepository;
use Exception;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ShowexController
{
	public function __construct(
		private ShowexRepository $showexRepository,
		private VideoexRepository $videoexRepository,
		private VideoRepository $videoRepository,
		private NewsRepository $newsRepository,
		private BannerRepository $bannerRepository,
		private UrlGeneratorInterface $urlGenerator,
	) {}

	public function showex(Request $request, PhtmlRenderer $renderer): Response
	{
		$path = $request->getPathInfo();
		$parts = explode('/', trim($path, '/'));
		$show_url = $parts[1] ?? '';

		if (!$show_url) {
			return new RedirectResponse($this->urlGenerator->generate('program_web_shows'));
		}

		try {
			$show = $this->showexRepository->findPostBy('url', $show_url);

			if (!$show) {
				return new RedirectResponse($this->urlGenerator->generate('program_web_shows'));
			}

			$page = (int) $request->query->get('strana', 1);
			$limit = 10;

			$videos = $this->videoexRepository->getPaginatorByShow((int)$show['id'], $page, $limit);
			$videosTotal = $this->videoexRepository->getCountByShow((int)$show['id']);

			$pr = $this->newsRepository->getPrArticles(2);
		} catch (Exception) {
			return new RedirectResponse($this->urlGenerator->generate('program_web_shows'));
		}

		$bannerLeaderboard = $bannerMobilesticky = $bannerSquare = null;
		try {
			$bannerLeaderboard = $this->bannerRepository->getLeaderboard();
			$bannerMobilesticky = $this->bannerRepository->getMobilesticky();
			$bannerSquare = $this->bannerRepository->getSquare();
		} catch (Exception) {
		}

		return new Response($renderer->renderWithLayout('program/web/showex', [
			'show' => $show,
			'videos' => $videos,
			'videosTotal' => $videosTotal,
			'page' => $page,
			'limit' => $limit,
			'pr' => $pr,
			'bannerSquare' => $bannerSquare,
			'bannerLeaderboard' => $bannerLeaderboard,
			'bannerMobilesticky' => $bannerMobilesticky,
			'schemeHost' => $request->getSchemeAndHttpHost(),
			'currentUrl' => $request->getUri(),
		]));
	}

	public function videoex(Request $request, PhtmlRenderer $renderer): Response
	{
		$path = $request->getPathInfo();
		$parts = explode('/', trim($path, '/'));
		$show_url = $parts[1] ?? '';
		$video_url = $request->attributes->get('video_url', '');

		if (!$show_url) {
			return new RedirectResponse($this->urlGenerator->generate('program_web_shows'));
		}

		$video = null;
		$parts_video = null;
		$newVideos = null;
		$mostWatchedShows = null;

		try {
			$show = $this->showexRepository->findPostBy('url', $show_url);

			if (!$show) {
				return new RedirectResponse($this->urlGenerator->generate('program_web_shows'));
			}

			$video = $this->videoexRepository->findPostBy('url', $video_url);

			if ($video) {
				$parts_video = $this->videoexRepository->findPartsBy('video_id', (int)$video['id']);
			} else {
				return new RedirectResponse($this->urlGenerator->generate('program_showex_' . $show['id']));
			}

			$newVideos = $this->videoRepository->getNewVideosForWeb(3);
			$mostWatchedShows = $this->videoRepository->getMostWatchedShowsForWeb(5);
		} catch (Exception $e) {
			return new RedirectResponse($this->urlGenerator->generate('program_web_shows'));
		}

		$pr = $this->newsRepository->getPrArticles(2);

		$bannerLeaderboard = $bannerMobilesticky = $bannerSquare = null;
		try {
			$bannerLeaderboard = $this->bannerRepository->getLeaderboard();
			$bannerMobilesticky = $this->bannerRepository->getMobilesticky();
			$bannerSquare = $this->bannerRepository->getSquare();
		} catch (Exception) {
		}

		return new Response($renderer->renderWithLayout('program/web/videoex', [
			'show' => $show,
			'video' => $video,
			'parts' => $parts_video,
			'newVideos' => $newVideos,
			'mostWatchedShows' => $mostWatchedShows,
			'pr' => $pr,
			'bannerSquare' => $bannerSquare,
			'bannerLeaderboard' => $bannerLeaderboard,
			'bannerMobilesticky' => $bannerMobilesticky,
			'schemeHost' => $request->getSchemeAndHttpHost(),
			'currentUrl' => $request->getUri(),
		]));
	}
}
```

- [ ] **Step 2: Ověřit syntax**

```bash
php -l src/Program/Controller/Web/ShowexController.php
```

- [ ] **Step 3: Commit**

```bash
git add src/Program/Controller/Web/ShowexController.php
git commit -m "feat: create ShowexController with showex() and videoex() methods"
```

---

## Task 10: DownloadController — download, overwriteDocx, downloadex

**Files:**
- Create: `src/Program/Controller/Web/DownloadController.php`

Referenční Laminas metody: `downloadAction()`, `overwriteDocxAction()`, `downloadexAction()`.

- [ ] **Step 1: Vytvořit DownloadController.php**

```php
<?php

namespace App\Program\Controller\Web;

use App\Program\Repository\ProgramRepository;
use App\Program\Repository\ShowRepository;
use App\Program\Repository\VideoexRepository;
use App\Program\Repository\VideoRepository;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Html;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class DownloadController
{
	public function __construct(
		private VideoRepository $videoRepository,
		private VideoexRepository $videoexRepository,
		private ProgramRepository $programRepository,
		private ShowRepository $showRepository,
		private UrlGeneratorInterface $urlGenerator,
		private string $PUBLIC_PATH,
		private string $LIGHT_URL,
	) {}

	public function download(Request $request): Response
	{
		$video_id = (int) $request->attributes->get('video_id', 0);
		$quality = $request->attributes->get('quality', 'hq');

		$video = $this->videoRepository->findPostBy('id', $video_id);

		if (!$video) {
			return new RedirectResponse($this->urlGenerator->generate('program_web_shows'));
		}

		$filePath = $this->LIGHT_URL . 'porady/publikovano/' . $video['path'] . '/' . $video['name'] . '_' . $quality . '.mp4';

		$response = new StreamedResponse(function () use ($filePath) {
			readfile($filePath);
		});

		$response->headers->set('Content-Description', 'File Transfer');
		$response->headers->set('Content-Disposition', 'attachment; filename="' . $video['name'] . '_' . $quality . '.mp4"');
		$response->headers->set('Content-Type', 'application/force-download');

		return $response;
	}

	public function downloadex(Request $request): Response
	{
		$video_id = (int) $request->attributes->get('video_id', 0);
		$quality = $request->attributes->get('quality', 'hq');

		$video = $this->videoexRepository->findPostBy('id', $video_id);

		if (!$video) {
			return new RedirectResponse($this->urlGenerator->generate('program_web_shows'));
		}

		$filePath = $this->LIGHT_URL . 'mimoradne/publikovano/' . $video['path'] . '/' . $video['name'] . '_' . $quality . '.mp4';

		$response = new StreamedResponse(function () use ($filePath) {
			readfile($filePath);
		});

		$response->headers->set('Content-Description', 'File Transfer');
		$response->headers->set('Content-Disposition', 'attachment; filename="' . $video['name'] . '_' . $quality . '.mp4"');
		$response->headers->set('Content-Type', 'application/force-download');

		return $response;
	}

	public function overwriteDocx(Request $request): Response
	{
		$video_id = (int) $request->attributes->get('video_id', 0);

		$program = $this->programRepository->findPremiereByVideoId($video_id);
		if (!$program) {
			return new RedirectResponse($this->urlGenerator->generate('program_web_shows'));
		}

		$show = $this->showRepository->findPostByProgram((int)$program['id']);
		if (!$show) {
			return new RedirectResponse($this->urlGenerator->generate('program_web_shows'));
		}

		$html = (string) ($program['overwrite'] ?? '');
		$html = preg_replace('~<script\b[^>]*>.*?</script>~is', '', $html);

		// 1) &nbsp; rozbíjí XML uvnitř DOCX
		$html = str_replace('&nbsp;', ' ', $html);

		// 2) PhpWord (loadXML) potřebuje XML-friendly <br/>
		$html = preg_replace('~</br\s*>~i', '<br/>', $html);
		$html = preg_replace('~<br\s*>~i', '<br/>', $html);

		// 3) odstranit prázdné odstavce typu <p><br></p>
		$html = preg_replace('~<p\b[^>]*>\s*(<br\s*/?>|&nbsp;|\s)*\s*</p>~i', '', $html);

		// 4) převod <div class="synchron"...> na <p>
		$dom = new \DOMDocument('1.0', 'UTF-8');
		libxml_use_internal_errors(true);
		$dom->loadHTML('<?xml encoding="utf-8" ?><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
		libxml_clear_errors();

		$xpath = new \DOMXPath($dom);
		foreach ($xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " synchron ")]') as $node) {
			$p = $dom->createElement('p');
			while ($node->firstChild) {
				$p->appendChild($node->firstChild);
			}
			$node->parentNode->replaceChild($p, $node);
		}

		$html = '';
		foreach ($dom->documentElement->childNodes as $child) {
			$html .= $dom->saveHTML($child);
		}
		// END 4)

		// 5) odstranit prázdné seznamy
		$html = preg_replace('~<ul\b[^>]*>\s*</ul>~i', '', $html);
		$html = preg_replace('~<ol\b[^>]*>\s*</ol>~i', '', $html);

		// 6) odstranit prázdné em odstavce
		$html = preg_replace('~<p\b[^>]*>\s*<em>\s*(<br\s*/?>|&nbsp;|\s|-)*\s*</em>\s*</p>~i', '', $html);

		// 7) odstranit <br>
		$html = preg_replace('~<br\s*/?>~i', ' ', $html);

		$phpWord = new PhpWord();
		$phpWord->getSettings()->setThemeFontLang(new \PhpOffice\PhpWord\Style\Language('cs-CZ'));
		$phpWord->setDefaultParagraphStyle(['spaceAfter' => 240, 'lineHeight' => 1.4]);
		$section = $phpWord->addSection();

		$createdAt = new \DateTime($program['time']);
		$articleUrl = $this->urlGenerator->generate(
			'program_show_video_' . $show['id'],
			['program_url' => $program['url']],
			UrlGeneratorInterface::ABSOLUTE_URL
		);

		// hlavička (logo vlevo, datum + odkaz vpravo)
		$table = $section->addTable([
			'width' => 5000,
			'unit' => \PhpOffice\PhpWord\SimpleType\TblWidth::PERCENT,
			'borderSize' => 0,
			'borderColor' => 'FFFFFF',
			'cellMarginTop' => 0,
			'cellMarginLeft' => 0,
			'cellMarginRight' => 200,
			'cellMarginBottom' => 0,
		]);
		$table->addRow();

		$cellLeft = $table->addCell(6500, ['valign' => 'top']);
		$cellLeft->addImage($this->PUBLIC_PATH . '/img/web/logo_polar.png', ['height' => 40]);

		$cellRight = $table->addCell(2500, ['valign' => 'top']);
		$cellRight->addText('Datum vydání:', ['size' => 9, 'bold' => true], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::END, 'spaceAfter' => 0]);
		$cellRight->addText($createdAt->format('j.n.Y, H:i'), ['size' => 9, 'bold' => true], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::END, 'spaceAfter' => 80]);
		$cellRight->addLink($articleUrl, 'Otevřít video na polar.cz', ['color' => '0563C1', 'underline' => 'single', 'size' => 10], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::END, 'spaceAfter' => 0]);

		$section->addTextBreak(1);
		$section->addText($show['title'], ['bold' => true, 'size' => 18], ['spaceAfter' => 160]);

		// try/catch z toho důvodu, aby při složitém HTML nezůstala zobrazená chybová stránka 500
		try {
			Html::addHtml($section, $html, false, false);
		} catch (\Throwable $e) {
			$section->addText('Přepis se nepodařilo převést do DOCX.');
		}

		$baseName = pathinfo((string)($program['file'] ?? ''), PATHINFO_FILENAME);
		$filename = 'polar-prepis-' . $baseName . '.docx';

		$tmp = tempnam(sys_get_temp_dir(), 'docx_');
		$writer = IOFactory::createWriter($phpWord, 'Word2007');
		$writer->save($tmp);

		$content = file_get_contents($tmp);
		@unlink($tmp);

		$response = new Response($content);
		$response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
		$response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
		$response->headers->set('Content-Length', (string) strlen($content));

		return $response;
	}
}
```

> **Poznámka:** `$this->LIGHT_URL` a `$this->PUBLIC_PATH` musí být zaregistrovány v `services.yaml` jako parametry. Zkontroluj, jak jsou definovány pro ostatní controllery (např. `NewsController`).

- [ ] **Step 2: Ověřit syntax**

```bash
php -l src/Program/Controller/Web/DownloadController.php
```

- [ ] **Step 3: Commit**

```bash
git add src/Program/Controller/Web/DownloadController.php
git commit -m "feat: create DownloadController with download, downloadex, overwriteDocx"
```

---

## Task 11: Šablona program.phtml — TV program stránka

**Files:**
- Create: `templates/program/web/program.phtml`

Reference: `polar/module/Program/view/program/web/web-list/program.phtml`

Klíčové části k přizpůsobení:
- `$this->url('application/program/json-list', ['action' => 'get-program-for-web'])` → `$view->path('program_web_json_get_program')`
- `$this->basePath(...)` → `$view->asset(...)`
- `$this->inlineScript()->appendFile(...)` → `$view->addBodyScript(...)`
- `$this->headLink()->appendStylesheet(...)` → `$view->addHeadLink('stylesheet', ...)`
- `$this->inlineScript()->prependFile(...)` → `$view->addBodyScript(...)` (nebo headLink)
- `$this->inlineScript()->appendScript(...)` → `$view->addInlineScript(...)`
- Laminas headTitle/headMeta → `$view->setTitle(...)`, `$view->addMeta(...)`, `$view->addOgMeta(...)`
- Breadcrumbs navigace → `$view->setBreadcrumbs([['label' => 'Program', 'url' => null]])`

- [ ] **Step 1: Vytvořit templates/program/web/program.phtml**

Otevři Laminas šablonu `polar/module/Program/view/program/web/web-list/program.phtml` a překopíruj celý obsah. Pak proveď tyto náhrady:

```php
<?php
/*
 * @project polar
 * ...
 */

$view->addHeadLink('stylesheet', $view->asset('vendor/web/magnific-popup/css/magnific-popup.min.css'));

$view->addBodyScript($view->asset('vendor/web/bootstrap-datepicker/js/locale/cs.min.js'));
$view->addBodyScript($view->asset('vendor/web/bootstrap-datepicker/js/bootstrap-datepicker.min.js'));
$view->addBodyScript($view->asset('vendor/web/moment/js/moment.min.js'));
$view->addBodyScript($view->asset('vendor/web/moment/js/locale/cs.min.js'));

$view->addInlineScript(
    '$(\'".datepicker_cal"\').datepicker({
        language: "cs",
        todayHighlight: true,
        todayBtn: "linked",
        weekStart: 1,
    }).on("changeDate", function(e){
        var date = new Date(e.date);
        var d = date.getDate();
        var m = date.getMonth()+1;
        var y = date.getFullYear();
        if (d < 10) d= "0" + d
        if (m < 10) m = "0" + m;
        $.post("' . $view->path('program_web_json_get_program') . '?date=" + y + "-" + m + "-" + d,
            function(json) {
                $("#program").html(json.content);
                $(".page-header h1 strong").html(moment(json.date).format("LL"));
            }, 
            "json"      
        );
    });
    $(".datepicker_cal").datepicker("setDate", new Date());'
);

// Ticker + Crawl JS
$view->addInlineScript(
    '// Ticker
    getTickerData();
    interval = startTicker();
    // Crawl
    getCrawlData();'
);

$view->setTitle('TV program | POLAR');
$view->addMeta('description', 'Podrobný televizní program TV Polar nejen pro dnešní den.');
$view->addMeta('keywords', 'tv program polar, program, televize');
$view->addOgMeta('og:title', 'TV program | POLAR');
$view->addOgMeta('og:description', 'Podrobný televizní program TV Polar nejen pro dnešní den.');
$view->addOgMeta('og:url', $currentUrl);
$view->addOgMeta('og:type', 'website');
$view->addOgMeta('og:image', $schemeHost . $view->asset('img/web/layout/microformat.png'));
$view->addOgMeta('og:image:secure_url', $schemeHost . $view->asset('img/web/layout/microformat.png'));
$view->addOgMeta('og:image:width', '1920');
$view->addOgMeta('og:image:height', '1080');

$view->setBreadcrumbs([
    ['label' => 'Domů', 'url' => $view->path('app_homepage')],
    ['label' => 'TV program'],
]);
?>
```

Pak zkopíruj HTML část šablony z Laminas. Nahraď:
- `$this->navigation(...)→breadcrumbs()...` → `<?= $view->include('application/navigation/breadcrumb') ?>`
- `$this->basePath(...)` → `$view->asset(...)`
- `$this->url(...)` → `$view->path(...)`

Pro `$mostWatchedShows` — blok ze stávající Laminas šablony překopírovat 1:1, upravit pouze helpery.

- [ ] **Step 2: Ověřit, že šablona neobsahuje Laminas-specifické kódy**

```bash
Select-String -Path "templates/program/web/program.phtml" -Pattern "Laminas|headTitle|headMeta|inlineScript|basePath\(|->url\(" | Select-Object Line
```

Výstup musí být prázdný.

- [ ] **Step 3: Otevřít stránku v prohlížeči a ověřit**

Navštiv `http://localhost/program` — stránka se musí zobrazit bez PHP chyb.

- [ ] **Step 4: Commit**

```bash
git add templates/program/web/program.phtml
git commit -m "feat: add program/web/program.phtml template"
```

---

## Task 12: Šablona shows.phtml — seznam pořadů

**Files:**
- Create: `templates/program/web/shows.phtml`

Reference: `polar/module/Program/view/program/web/web-list/shows.phtml`

Klíčové náhrady:
- `$this->url('application/show')` → `$view->path('program_web_shows')`
- `$this->url('application/show/show-' . $show['id'])` → `$view->path('program_show_' . $show['id'])`
- `$this->url('application/showex/showex-' . $show['id'])` → `$view->path('program_showex_' . $show['id'])`
- `$this->url('application/guests')` → `/hoste` (statická URL nebo route pokud existuje)
- `$this->basePath(...)` → `$view->asset(...)`
- `$this->translate(...)` → česky inline
- Autocomplete JS: `$showsForAutocomplete_json` zůstane stejně
- Breadcrumbs

- [ ] **Step 1: Vytvořit templates/program/web/shows.phtml**

Překopíruj z Laminas, proveď náhrady pomocí výše uvedeného mappingu.

Přidej na začátek PHP blok s meta a title:
```php
$view->setTitle('Pořady | POLAR');
$view->addMeta('description', 'Přehled pořadů moravskoslezské regionální televize POLAR...');
$view->addMeta('keywords', 'tv pořady, pořady polar, ...');
$view->addOgMeta('og:title', 'Pořady | POLAR');
// ...etc
$view->setBreadcrumbs([
    ['label' => 'Domů', 'url' => $view->path('app_homepage')],
    ['label' => 'Pořady'],
]);
```

- [ ] **Step 2: Ověřit**

```bash
Select-String -Path "templates/program/web/shows.phtml" -Pattern "Laminas|headTitle|headMeta|inlineScript|basePath\(|->url\(" | Select-Object Line
```

Výstup musí být prázdný.

- [ ] **Step 3: Otevřít stránku**

Navštiv `http://localhost/porady` — musí se zobrazit seznam pořadů.

- [ ] **Step 4: Commit**

```bash
git add templates/program/web/shows.phtml
git commit -m "feat: add program/web/shows.phtml template"
```

---

## Task 13: Šablona show.phtml — detail pořadu

**Files:**
- Create: `templates/program/web/show.phtml`

Reference: `polar/module/Program/view/program/web/web-list/show.phtml`

Klíčové rozdíly:
- `$show` je teď array, ne objekt: `$show->getTitle()` → `$show['title']`, atd.
- `$videos` je array (ne Laminas Paginator) — procházíme `foreach ($videos as $item)`
- Paginace: místo Laminas paginator partial použijeme: `<?= $view->include('application/pagination/paginator', ['route' => 'program_show_' . $show['id'], 'page' => $page, 'total' => $videosTotal, 'limit' => $limit]) ?>`
- URL videa: `$view->path('program_show_video_' . $show['id'], ['program_url' => $item['url']])`
- `$item['show_id']` — dostupné z `getPaginatorByShow`

- [ ] **Step 1: Vytvořit templates/program/web/show.phtml**

Otevři Laminas šablonu a překopíruj. Hlavní záměny v PHP bloku:
```php
// Laminas:
$show = $this->show;
$videos = $this->videos;

// Symfony — $show a $videos jsou přístupné přímo jako proměnné (extract v PhtmlRenderer)
// $show['title'] místo $show->getTitle()
// $show['image'] místo $show->getImage()
// etc.
```

Pro videos paginator v HTML části:
```php
// Laminas:
foreach ($videos as $item) { ... $item['url'] ... }

// Symfony — stejné (array items)
foreach ($videos as $item) { ... $item['url'] ... }
```

PR blok (`$pr`) — zachovat strukturu z Laminas šablony.

- [ ] **Step 2: Ověřit**

```bash
Select-String -Path "templates/program/web/show.phtml" -Pattern "Laminas|headTitle|headMeta|inlineScript|basePath\(|->url\(|->getTitle\(|->getId\(" | Select-Object Line
```

Výstup musí být prázdný.

- [ ] **Step 3: Otevřít stránku**

Navštiv `http://localhost/porady/<url-poradU>` — musí se zobrazit detail pořadu s videi.

- [ ] **Step 4: Commit**

```bash
git add templates/program/web/show.phtml
git commit -m "feat: add program/web/show.phtml template"
```

---

## Task 14: Šablona video.phtml — detail videa pořadu

**Files:**
- Create: `templates/program/web/video.phtml`

Reference: `polar/module/Program/view/program/web/web-list/video.phtml`

Klíčové rozdíly:
- `$show`, `$program`, `$video` jsou arrays
- Video URL: `LIGHT_URL . 'porady/publikovano/' . $video['path'] . '/' . $video['name'] . '_lq.mp4'`
  - V Symfony: `$LIGHT_URL` bude muset být předán do šablony nebo se přečte z konstanty. Viz jak to řeší `NewsController` — hledej `$LIGHT_URL` v services.yaml nebo jako parametr.
  - Případně přidej `$LIGHT_URL` do params v `ShowController::video()`.
- Download URL: `$view->path('program_web_download', ['video_id' => $video['id'], 'quality' => 'lq'])`
- Breadcrumb JSON-LD script tag — zachovat z Laminas šablony, jen nahradit helpery.
- `$prettyProgramTime` — předáno z controlleru
- `$breadcrumbItems` — předáno z controlleru

- [ ] **Step 1: Zjistit hodnotu LIGHT_URL**

```bash
Select-String -Path "config/services.yaml" -Pattern "LIGHT_URL|light_url" | Select-Object Line
```

Pokud tam není, přidej do `ShowController::video()`:
```php
// Přidej do konstruktoru: private string $LIGHT_URL
// A do renderWithLayout params: 'LIGHT_URL' => $this->LIGHT_URL
```

- [ ] **Step 2: Přidat LIGHT_URL do ShowController konstruktoru (pokud chybí)**

Najdi v `src/Program/Controller/Web/ShowController.php` konstruktor a přidej parametr:
```php
	public function __construct(
		// ...existující parametry...
		private string $LIGHT_URL,
	) {}
```

A do `video()` metody, do `renderWithLayout` params:
```php
'LIGHT_URL' => $this->LIGHT_URL,
```

- [ ] **Step 3: Vytvořit templates/program/web/video.phtml**

Překopíruj z Laminas. Klíčové náhrady:
```php
// Laminas:
$video_url_lq = LIGHT_URL . 'porady/publikovano/' . $video->getPath() . '/' . $video->getName() . '_lq.mp4';
// Symfony:
$video_url_lq = $LIGHT_URL . 'porady/publikovano/' . $video['path'] . '/' . $video['name'] . '_lq.mp4';

// Laminas download link:
$this->url('application/show/show-' . $show->getId() . '/video', ['video_id' => ..., 'quality' => 'lq'])
// Symfony:
$view->path('program_web_download', ['video_id' => $video['id'], 'quality' => 'lq'])

// DOCX download:
$view->path('program_web_overwrite_docx', ['video_id' => $video['id']])
```

- [ ] **Step 4: Ověřit**

```bash
Select-String -Path "templates/program/web/video.phtml" -Pattern "Laminas|headTitle|headMeta|inlineScript|basePath\(|->url\(|->getTitle\(|->getId\(" | Select-Object Line
```

Výstup musí být prázdný.

- [ ] **Step 5: Commit**

```bash
git add templates/program/web/video.phtml
git add src/Program/Controller/Web/ShowController.php
git commit -m "feat: add program/web/video.phtml template"
```

---

## Task 15: Šablona showex.phtml — detail mimořádného pořadu

**Files:**
- Create: `templates/program/web/showex.phtml`

Reference: `polar/module/Program/view/program/web/web-list/showex.phtml`

Klíčové rozdíly:
- `$show` je array
- `$videos` je array (ne Laminas Paginator)
- Paginace: `<?= $view->include('application/pagination/paginator', ['route' => 'program_showex_' . $show['id'], 'page' => $page, 'total' => $videosTotal, 'limit' => $limit]) ?>`
- Video URL: `$view->path('program_showex_video_' . $show['id'], ['video_url' => $item['url']])`  
  (Pozor: route name je `program_showex_video_N`, nikoliv `program_showex_N/video`)

- [ ] **Step 1: Vytvořit templates/program/web/showex.phtml**

Překopíruj z Laminas, proveď náhrady (stejný vzor jako show.phtml).

- [ ] **Step 2: Ověřit**

```bash
Select-String -Path "templates/program/web/showex.phtml" -Pattern "Laminas|headTitle|headMeta|inlineScript|basePath\(|->url\(|->getTitle\(|->getId\(" | Select-Object Line
```

- [ ] **Step 3: Commit**

```bash
git add templates/program/web/showex.phtml
git commit -m "feat: add program/web/showex.phtml template"
```

---

## Task 16: Šablona videoex.phtml — detail mimořádného videa

**Files:**
- Create: `templates/program/web/videoex.phtml`

Reference: `polar/module/Program/view/program/web/web-list/videoex.phtml`

Klíčové rozdíly (stejný vzor jako video.phtml):
- `$show`, `$video` jsou arrays
- `$parts` jsou arrays (části videa z `videoexRepository->findPartsBy`)
- Video URL: `$LIGHT_URL . 'mimoradne/publikovano/' . $video['path'] . '/' . $video['name'] . '_lq.mp4'`
- Download: `$view->path('program_web_downloadex', ['video_id' => $video['id'], 'quality' => 'lq'])`

Přidej `$LIGHT_URL` do `ShowexController::videoex()` renderWithLayout params (a konstruktoru) stejně jako v ShowController.

- [ ] **Step 1: Přidat LIGHT_URL do ShowexController konstruktoru**

```php
	public function __construct(
		// ...existující...
		private string $LIGHT_URL,
	) {}
```

A do `videoex()` params: `'LIGHT_URL' => $this->LIGHT_URL`

- [ ] **Step 2: Vytvořit templates/program/web/videoex.phtml**

Překopíruj z Laminas, proveď náhrady.

- [ ] **Step 3: Ověřit**

```bash
Select-String -Path "templates/program/web/videoex.phtml" -Pattern "Laminas|headTitle|headMeta|inlineScript|basePath\(|->url\(|->getTitle\(|->getId\(" | Select-Object Line
```

- [ ] **Step 4: Commit**

```bash
git add templates/program/web/videoex.phtml
git add src/Program/Controller/Web/ShowexController.php
git commit -m "feat: add program/web/videoex.phtml template"
```

---

## Self-Review

### Spec coverage check

| Laminas action | Symfony | Status |
|---|---|---|
| `programAction()` | `ProgramController::program()` | Task 7 |
| `getProgramForWebAction()` | `ProgramController::getProgramForWeb()` | Task 7 |
| `getProgram2ForWebAction()` | `ProgramController::getProgram2ForWeb()` | Task 7 |
| `getProgramForHdAction()` | `ProgramController::getProgramForHd()` | Task 7 |
| `showsAction()` | `ShowController::shows()` | Task 8 |
| `showAction()` | `ShowController::show()` | Task 8 |
| `videoAction()` | `ShowController::video()` | Task 8 |
| `showexAction()` | `ShowexController::showex()` | Task 9 |
| `videoexAction()` | `ShowexController::videoex()` | Task 9 |
| `downloadAction()` | `DownloadController::download()` | Task 10 |
| `overwriteDocxAction()` | `DownloadController::overwriteDocx()` | Task 10 |
| `downloadexAction()` | `DownloadController::downloadex()` | Task 10 |

Všechny Laminas akce jsou pokryty. ✓

### Otevřené otázky pro implementátora

1. **`app_homepage` route** — existuje taková route v Symfony projektu? Pokud ne, najdi správný název pro redirect na homepage pomocí `php bin/console debug:router | findstr home`.

2. **`show_url` parameter v routes** — routes `program_show_N` nemají `{show_url}` v URL (URL je hardcoded). Proto v `show()` a `video()` musíš parsovat URL z `$request->getPathInfo()` jak je popsáno v Task 8.

3. **`tableShowsCategories` property** — ověř, že `ShowRepository` má tuto property. Pokud ne, přidej ji.

4. **`LIGHT_URL` a `PUBLIC_PATH` v services.yaml** — ověř binding těchto parametrů pro nové controllery. Zkontroluj jak jsou nastaveny pro jiné controllery:
   ```bash
   Select-String -Path "config/services.yaml" -Pattern "LIGHT_URL|PUBLIC_PATH" | Select-Object Line
   ```

5. **Videoex tabulka a join** — `getPaginatorByShow` v VideoexRepository předpokládá, že `special_videos.show_id` odkazuje na `special_shows.id`. Ověř schéma tabulky pokud dojde k SQL chybě.
