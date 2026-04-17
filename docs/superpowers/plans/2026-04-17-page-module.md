# Page Module — Implementační plán

> **For agentic workers:** REQUIRED: Use the `subagent-driven-development` agent (recommended) or `executing-plans` agent to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Migrovat Page modul z polar (Laminas) do polar-symfony (Symfony 8) jako 1:1 kopii. Admin CRUD pro stránky (seznam, přidání, editace, řazení, duplikování, smazání) + generování config souboru pro dynamické routes a navigaci + web zobrazení stránek.

**Architecture:** PageRepository pro DB dotazy. PageListController (admin seznam + JSON endpointy), PageWriteController (admin CRUD + config generace), PageWebController (web zobrazení). PageConfigGenerator service pro generování YAML routes. Bootstrap-table pro admin seznam, Nestable pro drag-drop řazení, Redactor pro WYSIWYG editor.

**Tech Stack:** Symfony 8, PHP 8.5, Doctrine DBAL, phtml templates, Imagine (image resize)

---

## Tabulky v DB

**`page`**: id, lang, active, header, title, url, content, image, parent, depth, rank, rank_total, seo_keywords, seo_description, created_date, updated_date, created_user, updated_user

**`page_setting`**: key-value (img_width, img_height, footer_number_1-4)

## File Structure

```
src/Page/
├── Repository/
│   ├── PageRepository.php          # Všechny DB dotazy pro page
│   └── PageSettingRepository.php   # DB dotazy pro page_setting
├── Service/
│   └── PageConfigGenerator.php     # Generování YAML config + routes
├── Controller/
│   ├── Admin/
│   │   ├── PageListController.php  # index, list, getList, getPage, getSort, getUrl, redactor managers
│   │   ├── PageWriteController.php # add, edit, sort, setSort, deletePage, duplicatePage, uploadImage, setDefaultImage, redactor uploads
│   │   └── PageSettingController.php # setting
│   └── Web/
│       └── PageWebController.php   # page (zobrazení stránky na webu)
templates/page/
├── admin/
│   ├── index.phtml
│   ├── list.phtml
│   ├── add.phtml
│   ├── edit.phtml
│   ├── sort.phtml
│   └── pageForm.phtml
└── web/
    └── page.phtml
config/routes/page.yaml             # Statické admin + web routes
config/routes/page_dynamic.yaml     # Generovaný soubor s dynamickými routes
```

---

### Task 1: PageRepository — základní DB dotazy

**Files:**
- Create: `src/Page/Repository/PageRepository.php`

- [ ] **Step 1: Vytvořit PageRepository s základními metodami**

```php
<?php

namespace App\Page\Repository;

use Doctrine\DBAL\Connection;

final class PageRepository
{
	public function __construct(
		private Connection $connection,
	) {}

	public function getCount(?bool $active = null): int
	{
		$qb = $this->connection->createQueryBuilder()
			->select('COUNT(*)')
			->from('page');

		if ($active !== null) {
			$qb->andWhere('active = 1');
		}

		return (int) $qb->fetchOne();
	}

	public function getCountByLang(string $lang): int
	{
		return (int) $this->connection->createQueryBuilder()
			->select('COUNT(*)')
			->from('page')
			->where('lang = :lang')
			->setParameter('lang', $lang)
			->fetchOne();
	}

	public function getCountByLangAndParent(string $lang, ?int $parent = null): int
	{
		$qb = $this->connection->createQueryBuilder()
			->select('COUNT(*)')
			->from('page')
			->where('lang = :lang')
			->setParameter('lang', $lang);

		if ($parent === null) {
			$qb->andWhere('parent IS NULL OR parent = 0');
		} else {
			$qb->andWhere('parent = :parent')
				->setParameter('parent', $parent);
		}

		return (int) $qb->fetchOne();
	}

	public function findPostBy(string $column, int|string $value, ?string $lang = null): ?array
	{
		$qb = $this->connection->createQueryBuilder()
			->select('*')
			->from('page')
			->where("$column = :value")
			->setParameter('value', $value);

		if ($lang !== null) {
			$qb->andWhere('lang = :lang')
				->setParameter('lang', $lang);
		}

		return $qb->fetchAssociative() ?: null;
	}

	public function insertPost(array $data): int
	{
		$this->connection->insert('page', $data);
		return (int) $this->connection->lastInsertId();
	}

	public function updatePost(int $id, array $data): void
	{
		$this->connection->update('page', $data, ['id' => $id]);
	}

	public function deletePost(int $id): void
	{
		$this->connection->delete('page', ['id' => $id]);
	}

	public function fetchForBootstrapTable(array $params): array
	{
		$qb = $this->connection->createQueryBuilder()
			->select('id', 'lang', 'active', 'header', 'title', 'url', 'content', 'image',
				'parent', 'depth', 'rank', 'rank_total',
				'seo_keywords', 'seo_description',
				'created_date', 'updated_date', 'created_user', 'updated_user')
			->from('page')
			->where('lang = :lang')
			->setParameter('lang', $params['lang'])
			->orderBy('header', 'ASC')
			->addOrderBy($params['sort'] ?? 'rank_total', ($params['order'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC');

		if (!empty($params['search'])) {
			$qb->andWhere('MATCH (title, content) AGAINST (:search IN BOOLEAN MODE)')
				->setParameter('search', $params['search'] . '*');
		}

		if (isset($params['limit'])) {
			$qb->setMaxResults((int) $params['limit']);
		}
		if (isset($params['offset'])) {
			$qb->setFirstResult((int) $params['offset']);
		}

		$rows = $qb->fetchAllAssociative();

		// Připojit parent URL k child stránkám
		foreach ($rows as $i => $row) {
			if ($row['parent'] != 0) {
				foreach ($rows as $row2) {
					if ($rows[$i]['parent'] === $row2['id']) {
						$rows[$i]['url'] = $row2['url'] . '/' . $rows[$i]['url'];
					}
				}
			}
		}

		return $rows;
	}

	public function getCountForBootstrapTable(array $params): int
	{
		$qb = $this->connection->createQueryBuilder()
			->select('COUNT(*)')
			->from('page')
			->where('lang = :lang')
			->setParameter('lang', $params['lang']);

		if (!empty($params['search'])) {
			$qb->andWhere('MATCH (title, content) AGAINST (:search IN BOOLEAN MODE)')
				->setParameter('search', $params['search'] . '*');
		}

		return (int) $qb->fetchOne();
	}

	/**
	 * Hierarchické načtení stránek pro Nestable drag-drop.
	 */
	public function fetchForNestable(string $lang, bool $header, ?int $parent = null): string
	{
		$qb = $this->connection->createQueryBuilder()
			->select('id', 'lang', 'active', 'header', 'title', 'parent', 'depth', 'rank', 'rank_total')
			->from('page')
			->where('lang = :lang')
			->andWhere('header = :header')
			->setParameter('lang', $lang)
			->setParameter('header', $header ? 1 : 0)
			->orderBy('rank', 'ASC');

		if ($parent === null) {
			$qb->andWhere('parent IS NULL OR parent = 0');
		} else {
			$qb->andWhere('parent = :parent')
				->setParameter('parent', $parent);
		}

		$rows = $qb->fetchAllAssociative();

		if (empty($rows)) {
			return '';
		}

		$data = '<ol class="dd-list">';
		foreach ($rows as $row) {
			$icon = $row['active'] ? 'fa-check-circle text-success' : 'fa-times-circle text-danger';
			$data .=
				'<li class="dd-item" data-id="' . $row['id'] . '">' .
				'<div class="dd-handle cur-move">' .
				'<i class="fa fa-fw ' . $icon . '"></i> ' .
				htmlspecialchars($row['title']) .
				'</div>';

			$data .= $this->fetchForNestable($lang, $header, (int) $row['id']);

			$data .= '</li>';
		}
		$data .= '</ol>';

		return $data;
	}

	/**
	 * Načtení stránek pro generování YAML routes.
	 */
	public function fetchAllForRoutes(): array
	{
		return $this->connection->createQueryBuilder()
			->select('id', 'url', 'active')
			->from('page')
			->orderBy('id', 'ASC')
			->fetchAllAssociative();
	}

	/**
	 * Hierarchické načtení stránek pro generování navigation config.
	 */
	public function fetchForNavigation(string $lang, bool $header, ?int $parent = null): array
	{
		$qb = $this->connection->createQueryBuilder()
			->select('id', 'title', 'url', 'rank_total', 'updated_date')
			->from('page')
			->where('active = 1')
			->andWhere('lang = :lang')
			->andWhere('header = :header')
			->setParameter('lang', $lang)
			->setParameter('header', $header ? 1 : 0)
			->orderBy('rank', 'ASC');

		if ($parent === null) {
			$qb->andWhere('parent IS NULL OR parent = 0');
		} else {
			$qb->andWhere('parent = :parent')
				->setParameter('parent', $parent);
		}

		return $qb->fetchAllAssociative();
	}
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Page/Repository/PageRepository.php
git commit -m "feat(page): add PageRepository with all DB queries"
```

---

### Task 2: PageSettingRepository

**Files:**
- Create: `src/Page/Repository/PageSettingRepository.php`

- [ ] **Step 1: Vytvořit PageSettingRepository**

```php
<?php

namespace App\Page\Repository;

use Doctrine\DBAL\Connection;

final class PageSettingRepository
{
	public function __construct(
		private Connection $connection,
	) {}

	public function fetchSetting(): array
	{
		$rows = $this->connection->createQueryBuilder()
			->select('*')
			->from('page_setting')
			->fetchAllAssociative();

		$setting = [];
		foreach ($rows as $row) {
			$setting[$row['key']] = $row['value'];
		}

		return $setting;
	}

	public function updateSetting(string $key, string $value): void
	{
		$exists = $this->connection->createQueryBuilder()
			->select('COUNT(*)')
			->from('page_setting')
			->where('`key` = :key')
			->setParameter('key', $key)
			->fetchOne();

		if ($exists) {
			$this->connection->update('page_setting', ['value' => $value], ['key' => $key]);
		} else {
			$this->connection->insert('page_setting', ['key' => $key, 'value' => $value]);
		}
	}
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Page/Repository/PageSettingRepository.php
git commit -m "feat(page): add PageSettingRepository"
```

---

### Task 3: PageConfigGenerator — generování dynamických routes

**Files:**
- Create: `src/Page/Service/PageConfigGenerator.php`

Toto je klíč celého modulu. V polaru se generuje PHP config s Laminas routes + navigation. V Symfony místo toho generujeme YAML routes soubor.

- [ ] **Step 1: Vytvořit PageConfigGenerator**

```php
<?php

namespace App\Page\Service;

use App\Page\Repository\PageRepository;
use Symfony\Component\Yaml\Yaml;

final class PageConfigGenerator
{
	public function __construct(
		private PageRepository $pageRepository,
		private string $configDir,
		private string $cacheDir,
	) {}

	/**
	 * Vygeneruje YAML soubor s dynamickými routes pro stránky.
	 * Volá se po každé změně stránky (add/edit/delete/duplicate/sort).
	 */
	public function generate(): void
	{
		$pages = $this->pageRepository->fetchAllForRoutes();

		$routes = [];
		foreach ($pages as $page) {
			$routeName = 'page_' . $page['id'];
			$routes[$routeName] = [
				'path' => '/' . $page['url'],
				'controller' => 'App\\Page\\Controller\\Web\\PageWebController::page',
				'defaults' => [
					'page_id' => $page['id'],
				],
			];
		}

		$yaml = Yaml::dump($routes, 4);
		file_put_contents($this->configDir . '/routes/page_dynamic.yaml', $yaml);

		// Smazat Symfony cache pro načtení nových routes
		$this->clearRouteCache();
	}

	private function clearRouteCache(): void
	{
		$files = glob($this->cacheDir . '/url_*');
		if ($files) {
			foreach ($files as $file) {
				@unlink($file);
			}
		}
		// Smazat celý dev cache pro jistotu
		$cacheFile = $this->cacheDir . '/../dev/';
		// Necháme na Symfony cache:clear, jen smažeme route matching cache
	}
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Page/Service/PageConfigGenerator.php
git commit -m "feat(page): add PageConfigGenerator for dynamic YAML routes"
```

---

### Task 4: Admin routes + sidebar link

**Files:**
- Create: `config/routes/page.yaml`
- Modify: `config/routes.yaml` (přidat import)
- Modify: `templates/admin/layout.phtml` (přidat "Stránky" do sidebaru)

- [ ] **Step 1: Vytvořit config/routes/page.yaml**

```yaml
# Admin Page routes
admin_page_index:
  path: /admin/pages
  controller: App\Page\Controller\Admin\PageListController::index

admin_page_list:
  path: /admin/pages/{lang}
  controller: App\Page\Controller\Admin\PageListController::list
  defaults:
    lang: cs_CZ

admin_page_add:
  path: /admin/pages/{lang}/add
  controller: App\Page\Controller\Admin\PageWriteController::add
  defaults:
    lang: cs_CZ

admin_page_edit:
  path: /admin/pages/{lang}/edit/{id}
  controller: App\Page\Controller\Admin\PageWriteController::edit
  defaults:
    lang: cs_CZ
  requirements:
    id: '\d+'

admin_page_sort:
  path: /admin/pages/{lang}/sort
  controller: App\Page\Controller\Admin\PageWriteController::sort
  defaults:
    lang: cs_CZ

# JSON endpointy - list
admin_page_get_list:
  path: /admin/pages/json-list/get-list
  controller: App\Page\Controller\Admin\PageListController::getList
  methods: [GET]

admin_page_get_page:
  path: /admin/pages/json-list/get-page
  controller: App\Page\Controller\Admin\PageListController::getPage
  methods: [POST]

admin_page_get_sort:
  path: /admin/pages/json-list/get-sort
  controller: App\Page\Controller\Admin\PageListController::getSort
  methods: [POST]

admin_page_get_url:
  path: /admin/pages/json-list/get-url
  controller: App\Page\Controller\Admin\PageListController::getUrl
  methods: [POST]

admin_page_redactor_image_manager:
  path: /admin/pages/json-list/redactor-image-manager
  controller: App\Page\Controller\Admin\PageListController::redactorImageManager
  methods: [GET]

admin_page_redactor_file_manager:
  path: /admin/pages/json-list/redactor-file-manager
  controller: App\Page\Controller\Admin\PageListController::redactorFileManager
  methods: [GET]

# JSON endpointy - write
admin_page_duplicate:
  path: /admin/pages/json-write/duplicate-page
  controller: App\Page\Controller\Admin\PageWriteController::duplicatePage
  methods: [POST]

admin_page_delete:
  path: /admin/pages/json-write/delete-page
  controller: App\Page\Controller\Admin\PageWriteController::deletePage
  methods: [POST]

admin_page_set_sort:
  path: /admin/pages/json-write/set-sort
  controller: App\Page\Controller\Admin\PageWriteController::setSort
  methods: [POST]

admin_page_upload_image:
  path: /admin/pages/json-write/upload-image
  controller: App\Page\Controller\Admin\PageWriteController::uploadImage
  methods: [POST]

admin_page_set_default_image:
  path: /admin/pages/json-write/set-default-image
  controller: App\Page\Controller\Admin\PageWriteController::setDefaultImage
  methods: [POST]

admin_page_redactor_image_upload:
  path: /admin/pages/json-write/redactor-image-upload
  controller: App\Page\Controller\Admin\PageWriteController::redactorImageUpload
  methods: [POST]

admin_page_redactor_file_upload:
  path: /admin/pages/json-write/redactor-file-upload
  controller: App\Page\Controller\Admin\PageWriteController::redactorFileUpload
  methods: [POST]

# Page setting
admin_page_setting:
  path: /admin/pages/setting
  controller: App\Page\Controller\Admin\PageSettingController::setting
```

- [ ] **Step 2: Přidat import do routes.yaml**

Do `config/routes.yaml` přidat pod `admin:`:

```yaml
page:
  resource: routes/page.yaml
```

- [ ] **Step 3: Přidat "Stránky" do admin sidebaru**

Do `templates/admin/layout.phtml` přidat link pod "Uživatelé":

```html
<li class="nav-item">
    <a class="nav-link" href="<?= $view->path('admin_page_list', ['lang' => 'cs_CZ']) ?>">
        <i class="fa fa-file-alt" aria-hidden="true"></i>
        <span>Stránky</span>
    </a>
</li>
```

- [ ] **Step 4: Commit**

```bash
git add config/routes/page.yaml config/routes.yaml templates/admin/layout.phtml
git commit -m "feat(page): add admin routes and sidebar link"
```

---

### Task 5: PageListController (admin)

**Files:**
- Create: `src/Page/Controller/Admin/PageListController.php`

- [ ] **Step 1: Vytvořit PageListController**

```php
<?php

namespace App\Page\Controller\Admin;

use App\Application\View\PhtmlRenderer;
use App\Page\Repository\PageRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Transliterator;

final class PageListController
{
	public function __construct(
		private PageRepository $pageRepository,
		private PhtmlRenderer $renderer,
		private Security $security,
		private string $PUBLIC_PATH,
	) {}

	public function index(): Response
	{
		$identity = $this->security->getUser();

		return new Response($this->renderer->renderWithAdminLayout('page/admin/index', [
			'identity' => $identity,
			'pageTitle' => 'Stránky',
			'countPage' => $this->pageRepository->getCount(),
			'countPageActive' => $this->pageRepository->getCount(true),
		]));
	}

	public function list(string $lang = 'cs_CZ'): Response
	{
		$identity = $this->security->getUser();

		return new Response($this->renderer->renderWithAdminLayout('page/admin/list', [
			'identity' => $identity,
			'pageTitle' => 'Stránky',
			'lang' => $lang,
		]));
	}

	public function getList(Request $request): JsonResponse
	{
		$params = $request->query->all();
		$rows = $this->pageRepository->fetchForBootstrapTable($params);
		$total = $this->pageRepository->getCountForBootstrapTable($params);

		return new JsonResponse([
			'success' => true,
			'rows' => $rows,
			'total' => $total,
		]);
	}

	public function getPage(Request $request): JsonResponse
	{
		try {
			$pageId = $request->request->getInt('id');
			$page = $this->pageRepository->findPostBy('id', $pageId);

			return new JsonResponse([
				'success' => (bool) $page,
				'message' => $page ? null : 'Stránka nenalezena',
				'page' => $page,
			]);
		} catch (\Exception $e) {
			return new JsonResponse([
				'success' => false,
				'message' => $e->getMessage(),
				'page' => null,
			]);
		}
	}

	public function getSort(Request $request): JsonResponse
	{
		try {
			$lang = $request->request->get('lang', 'cs_CZ');
			$header = $request->request->get('header') === 'true';
			$html = $this->pageRepository->fetchForNestable($lang, $header);

			return new JsonResponse([
				'success' => true,
				'html' => $html,
			]);
		} catch (\Exception $e) {
			return new JsonResponse([
				'success' => $e->getMessage(),
				'html' => '',
			]);
		}
	}

	public function getUrl(Request $request): JsonResponse
	{
		try {
			$title = $request->request->get('title', '');
			$url = $this->removeAccent($title, '-');

			return new JsonResponse([
				'success' => true,
				'message' => null,
				'url' => $url,
			]);
		} catch (\Exception $e) {
			return new JsonResponse([
				'success' => false,
				'message' => $e->getMessage(),
				'url' => null,
			]);
		}
	}

	public function redactorImageManager(Request $request): JsonResponse
	{
		$pageId = $request->query->get('page_id');
		$data = [];

		$path = '/data/page/';
		if ($pageId) {
			$path .= $pageId . '/image';
		} else {
			$path .= 'default/image';
		}

		$dir = $this->PUBLIC_PATH . $path;
		if (!is_dir($dir)) {
			mkdir($dir, 0755, true);
		}

		$scan = array_diff(scandir($dir), ['..', '.', '.DS_Store']);
		foreach ($scan as $file) {
			if (!str_contains($file, '_thumb.')) {
				$data[] = [
					'thumb' => $path . '/' . substr($file, 0, -4) . '_thumb' . substr($file, strlen($file) - 4),
					'url' => $path . '/' . $file,
					'id' => $pageId,
					'title' => ucwords(strtolower(str_replace('_', ' ', substr($file, 0, -4)))),
				];
			}
		}

		return new JsonResponse($data);
	}

	public function redactorFileManager(Request $request): JsonResponse
	{
		$pageId = $request->query->get('page_id');
		$data = [];

		$path = '/data/page/';
		if ($pageId) {
			$path .= $pageId . '/file';
		} else {
			$path .= 'default/file';
		}

		$dir = $this->PUBLIC_PATH . $path;
		if (!is_dir($dir)) {
			mkdir($dir, 0755, true);
		}

		$scan = array_diff(scandir($dir), ['..', '.', '.DS_Store']);
		$id = 1;
		foreach ($scan as $file) {
			$data[] = [
				'id' => (string) $id,
				'url' => $path . '/' . $file,
				'title' => $file,
			];
			$id++;
		}

		return new JsonResponse($data);
	}

	private function removeAccent(string $string, string $separator = '-'): string
	{
		$transliterator = Transliterator::createFromRules(
			':: Any-Latin; :: Latin-ASCII; :: NFD; :: [:Nonspacing Mark:] Remove; :: Lower(); :: NFC;',
			Transliterator::FORWARD
		);

		$string = $transliterator->transliterate($string);
		$string = preg_replace('/[^a-z0-9\s]/', '', $string);
		$string = preg_replace('/[\s]+/', $separator, $string);

		return trim($string, $separator);
	}
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Page/Controller/Admin/PageListController.php
git commit -m "feat(page): add PageListController with all JSON endpoints"
```

---

### Task 6: PageWriteController (admin) — add, edit, sort

**Files:**
- Create: `src/Page/Controller/Admin/PageWriteController.php`

Tento controller je největší soubor. Obsahuje add, edit, sort, setSort, duplicatePage, deletePage, uploadImage, setDefaultImage, redactor uploady a createConfig.

- [ ] **Step 1: Vytvořit PageWriteController**

```php
<?php

namespace App\Page\Controller\Admin;

use App\Application\View\PhtmlRenderer;
use App\Page\Repository\PageRepository;
use App\Page\Repository\PageSettingRepository;
use App\Page\Service\PageConfigGenerator;
use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Imagine\Image\ManipulatorInterface;
use Imagine\Image\Palette\RGB;
use Imagine\Image\Point;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Transliterator;

final class PageWriteController
{
	private string $imageDefault = 'data/page/!default-page.png';

	public function __construct(
		private PageRepository $pageRepository,
		private PageSettingRepository $settingRepository,
		private PageConfigGenerator $configGenerator,
		private PhtmlRenderer $renderer,
		private Security $security,
		private UrlGeneratorInterface $urlGenerator,
		private string $PUBLIC_PATH,
	) {}

	public function add(Request $request, string $lang = 'cs_CZ'): Response
	{
		$identity = $this->security->getUser();
		$setting = $this->settingRepository->fetchSetting();

		if ($request->isMethod('POST')) {
			$post = $request->request->all();

			if (isset($post['cancel'])) {
				return new RedirectResponse($this->urlGenerator->generate('admin_page_list', ['lang' => $lang]));
			}

			// Validace
			$errors = $this->validateForm($post);
			if (!empty($errors)) {
				return new Response($this->renderer->renderWithAdminLayout('page/admin/add', [
					'identity' => $identity,
					'pageTitle' => 'Stránky',
					'lang' => $lang,
					'setting' => $setting,
					'post' => $post,
					'errors' => $errors,
				]));
			}

			try {
				$id = $this->pageRepository->insertPost([
					'lang' => $lang,
					'active' => !empty($post['active']) ? 1 : 0,
					'header' => !empty($post['header']) ? 1 : 0,
					'title' => $post['title'],
					'url' => $post['url'],
					'content' => $post['content'] ?? '',
					'image' => null,
					'parent' => null,
					'depth' => 1,
					'rank' => $this->pageRepository->getCountByLangAndParent($lang) + 1,
					'rank_total' => $this->pageRepository->getCountByLang($lang) + 1,
					'seo_keywords' => $post['seo_keywords'] ?? '',
					'seo_description' => $post['seo_description'] ?? '',
					'created_date' => date('Y-m-d H:i:s'),
					'updated_date' => date('Y-m-d H:i:s'),
					'created_user' => $identity->getUserIdentifier(),
					'updated_user' => $identity->getUserIdentifier(),
				]);

				// Adresář
				$folder = 'data/page/' . $id;
				if (!is_dir($this->PUBLIC_PATH . '/' . $folder)) {
					mkdir($this->PUBLIC_PATH . '/' . $folder, 0777, true);
				}

				// Obrázek
				$image = $post['image'] ?? $this->imageDefault;
				if ($image === $this->imageDefault) {
					$image = null;
				} elseif (str_contains($image, '/tmp/')) {
					$newImage = $folder . '/' . substr($image, strrpos($image, '/') + 1);
					rename($this->PUBLIC_PATH . '/' . $image, $this->PUBLIC_PATH . '/' . $newImage);
					$image = $newImage;
				}

				// Redactor - přesun tmp adresářů
				$dirFile = $this->PUBLIC_PATH . '/data/page/tmp/file';
				if (is_dir($dirFile)) {
					rename($dirFile, $this->PUBLIC_PATH . '/' . $folder . '/file');
					mkdir($dirFile, 0777, true);
				}
				$dirImage = $this->PUBLIC_PATH . '/data/page/tmp/image';
				if (is_dir($dirImage)) {
					rename($dirImage, $this->PUBLIC_PATH . '/' . $folder . '/image');
					mkdir($dirImage, 0777, true);
				}

				$content = str_replace('/tmp/', '/' . $id . '/', $post['content'] ?? '');

				$this->pageRepository->updatePost($id, [
					'image' => $image,
					'content' => $content,
				]);

				// Vygenerování config
				$this->configGenerator->generate();

				return new RedirectResponse($this->urlGenerator->generate('admin_page_list', ['lang' => $lang]));
			} catch (\Exception $e) {
				return new Response($this->renderer->renderWithAdminLayout('page/admin/add', [
					'identity' => $identity,
					'pageTitle' => 'Stránky',
					'lang' => $lang,
					'setting' => $setting,
					'post' => $post,
					'errors' => ['general' => $e->getMessage()],
				]));
			}
		}

		return new Response($this->renderer->renderWithAdminLayout('page/admin/add', [
			'identity' => $identity,
			'pageTitle' => 'Stránky',
			'lang' => $lang,
			'setting' => $setting,
			'post' => [],
			'errors' => [],
		]));
	}

	public function edit(Request $request, string $lang = 'cs_CZ', int $id = 0): Response
	{
		$identity = $this->security->getUser();

		if ($id === 0) {
			return new RedirectResponse($this->urlGenerator->generate('admin_page_add', ['lang' => $lang]));
		}

		$page = $this->pageRepository->findPostBy('id', $id);
		if (!$page) {
			return new RedirectResponse($this->urlGenerator->generate('admin_page_list', ['lang' => $lang]));
		}

		$setting = $this->settingRepository->fetchSetting();

		if ($request->isMethod('POST')) {
			$post = $request->request->all();

			if (isset($post['cancel'])) {
				return new RedirectResponse($this->urlGenerator->generate('admin_page_list', ['lang' => $lang]));
			}

			$errors = $this->validateForm($post);
			if (!empty($errors)) {
				return new Response($this->renderer->renderWithAdminLayout('page/admin/edit', [
					'identity' => $identity,
					'pageTitle' => 'Stránky',
					'lang' => $lang,
					'setting' => $setting,
					'page' => array_merge($page, $post),
					'post' => array_merge($page, $post),
					'errors' => $errors,
				]));
			}

			try {
				$image = $post['image'] ?? $page['image'];
				if ($image === $this->imageDefault) {
					$image = null;
				}

				$this->pageRepository->updatePost($id, [
					'active' => !empty($post['active']) ? 1 : 0,
					'header' => !empty($post['header']) ? 1 : 0,
					'title' => $post['title'],
					'url' => $post['url'],
					'content' => $post['content'] ?? '',
					'image' => $image,
					'seo_keywords' => $post['seo_keywords'] ?? '',
					'seo_description' => $post['seo_description'] ?? '',
					'updated_date' => date('Y-m-d H:i:s'),
					'updated_user' => $identity->getUserIdentifier(),
				]);

				// Vygenerování config
				$this->configGenerator->generate();

				return new RedirectResponse($this->urlGenerator->generate('admin_page_list', ['lang' => $lang]));
			} catch (\Exception $e) {
				return new Response($this->renderer->renderWithAdminLayout('page/admin/edit', [
					'identity' => $identity,
					'pageTitle' => 'Stránky',
					'lang' => $lang,
					'setting' => $setting,
					'page' => array_merge($page, $post),
					'post' => array_merge($page, $post),
					'errors' => ['general' => $e->getMessage()],
				]));
			}
		}

		return new Response($this->renderer->renderWithAdminLayout('page/admin/edit', [
			'identity' => $identity,
			'pageTitle' => 'Stránky',
			'lang' => $lang,
			'setting' => $setting,
			'page' => $page,
			'post' => $page,
			'errors' => [],
		]));
	}

	public function sort(string $lang = 'cs_CZ'): Response
	{
		$identity = $this->security->getUser();

		return new Response($this->renderer->renderWithAdminLayout('page/admin/sort', [
			'identity' => $identity,
			'pageTitle' => 'Stránky',
			'lang' => $lang,
		]));
	}

	public function setSort(Request $request): JsonResponse
	{
		try {
			$lang = $request->request->get('lang', 'cs_CZ');
			$data = $request->request->all('data');

			$this->savePagesSort($data);

			// Vygenerování config
			$this->configGenerator->generate();

			return new JsonResponse([
				'success' => true,
				'lang' => $lang,
				'data' => $data,
			]);
		} catch (\Exception $e) {
			return new JsonResponse([
				'success' => $e->getMessage(),
				'lang' => null,
				'data' => null,
			]);
		}
	}

	public function duplicatePage(Request $request): JsonResponse
	{
		$identity = $this->security->getUser();

		try {
			$pageId = $request->request->getInt('id');
			$lang = $request->request->get('lang');

			$page = $this->pageRepository->findPostBy('id', $pageId);
			if (!$page) {
				return new JsonResponse([
					'success' => false,
					'message' => 'Stránka nenalezena',
					'page_id' => $pageId,
					'lang' => $lang,
				]);
			}

			$newId = $this->pageRepository->insertPost([
				'lang' => $lang,
				'active' => $page['active'],
				'header' => $page['header'],
				'title' => $page['title'],
				'url' => $page['url'],
				'content' => $page['content'],
				'image' => $page['image'],
				'parent' => $page['parent'],
				'depth' => $page['depth'],
				'rank' => $page['rank'],
				'rank_total' => $page['rank_total'],
				'seo_keywords' => $page['seo_keywords'],
				'seo_description' => $page['seo_description'],
				'created_date' => date('Y-m-d H:i:s'),
				'updated_date' => date('Y-m-d H:i:s'),
				'created_user' => $identity->getUserIdentifier(),
				'updated_user' => $identity->getUserIdentifier(),
			]);

			// Opravit cesty v image a content
			$updateData = [];
			if ($page['image']) {
				$updateData['image'] = str_replace('data/page/' . $pageId, 'data/page/' . $newId, $page['image']);
			}
			$updateData['content'] = str_replace('data/page/' . $pageId, 'data/page/' . $newId, $page['content']);

			if (!empty($updateData)) {
				$this->pageRepository->updatePost($newId, $updateData);
			}

			// Kopírovat adresář
			$this->copyDir(
				$this->PUBLIC_PATH . '/data/page/' . $pageId,
				$this->PUBLIC_PATH . '/data/page/' . $newId
			);

			// Vygenerování config
			$this->configGenerator->generate();

			return new JsonResponse([
				'success' => true,
				'message' => null,
				'page_id' => $pageId,
				'lang' => $lang,
			]);
		} catch (\Exception $e) {
			return new JsonResponse([
				'success' => false,
				'message' => $e->getMessage(),
				'page_id' => null,
				'lang' => null,
			]);
		}
	}

	public function deletePage(Request $request): JsonResponse
	{
		try {
			$pageId = $request->request->getInt('id');
			$lang = $request->request->get('lang');

			$page = $this->pageRepository->findPostBy('id', $pageId);
			if (!$page) {
				return new JsonResponse([
					'success' => false,
					'message' => 'Stránka nenalezena',
					'page_id' => $pageId,
				]);
			}

			$this->deleteDir($this->PUBLIC_PATH . '/data/page/' . $page['id'] . '/');
			$this->pageRepository->deletePost($page['id']);

			// Vygenerování config
			$this->configGenerator->generate();

			return new JsonResponse([
				'success' => true,
				'message' => null,
				'page_id' => $pageId,
			]);
		} catch (\Exception $e) {
			return new JsonResponse([
				'success' => false,
				'message' => $e->getMessage(),
				'page_id' => null,
			]);
		}
	}

	public function uploadImage(Request $request): JsonResponse
	{
		$file = $request->files->get('file');
		if (!$file) {
			return new JsonResponse(['error' => 'Žádné soubory k nahrání']);
		}

		$pageId = $request->request->get('page_id');
		$setting = $this->settingRepository->fetchSetting();

		$folder = 'data/page/';
		if ($pageId && $pageId !== 'null') {
			$folder .= $pageId . '/';
		} else {
			$folder .= 'tmp/';
		}

		if (!is_dir($this->PUBLIC_PATH . '/' . $folder)) {
			mkdir($this->PUBLIC_PATH . '/' . $folder, 0777, true);
		}

		$fileType = strtolower($file->getMimeType());
		$type = match ($fileType) {
			'image/gif' => 'gif',
			'image/png' => 'png',
			default => 'jpg',
		};

		$filename = 'page-' . date('YmdHis') . '_' . random_int(100, 999);
		[$width, $height] = getimagesize($file->getPathname());
		$imgWidth = (int) ($setting['img_width'] ?? 800);
		$imgHeight = (int) ($setting['img_height'] ?? 450);

		if ($width === $imgWidth && $height === $imgHeight) {
			$imageFileName = $folder . $filename . '.' . $type;
			$file->move($this->PUBLIC_PATH . '/' . $folder, $filename . '.' . $type);
		} else {
			$imageFileName = $this->createImage(
				$file->getPathname(), $folder, $filename, $imgWidth, $imgHeight, $type
			);
		}

		if ($pageId && $pageId !== 'null') {
			$identity = $this->security->getUser();
			$page = $this->pageRepository->findPostBy('id', (int) $pageId);

			if ($page && $page['image'] && $page['image'] !== $this->imageDefault) {
				$oldImage = $this->PUBLIC_PATH . '/' . $page['image'];
				if (file_exists($oldImage)) {
					@unlink($oldImage);
				}
			}
			if ($page) {
				$this->pageRepository->updatePost((int) $page['id'], [
					'image' => $imageFileName,
					'updated_date' => date('Y-m-d H:i:s'),
					'updated_user' => $identity->getUserIdentifier(),
				]);
			}
		}

		return new JsonResponse([
			'name' => $file->getClientOriginalName(),
			'url' => $imageFileName,
			'type' => $fileType,
		]);
	}

	public function setDefaultImage(Request $request): JsonResponse
	{
		$identity = $this->security->getUser();

		try {
			$pageId = $request->request->getInt('page_id');
			$page = $this->pageRepository->findPostBy('id', $pageId);

			if ($page) {
				if ($page['image'] && $page['image'] !== $this->imageDefault) {
					@unlink($this->PUBLIC_PATH . '/' . $page['image']);
				}
				$this->pageRepository->updatePost($pageId, [
					'image' => null,
					'updated_user' => $identity->getUserIdentifier(),
				]);

				return new JsonResponse([
					'success' => true,
					'message' => null,
					'page_id' => $pageId,
					'url' => $this->imageDefault,
				]);
			}

			return new JsonResponse([
				'success' => false,
				'message' => 'Stránka nenalezena',
				'page_id' => $pageId,
				'url' => $this->imageDefault,
			]);
		} catch (\Exception $e) {
			return new JsonResponse([
				'success' => false,
				'message' => $e->getMessage(),
				'page_id' => null,
				'url' => $this->imageDefault,
			]);
		}
	}

	public function redactorImageUpload(Request $request): JsonResponse
	{
		$pageId = $request->query->get('page_id');
		$data = [];

		$path = '/data/page/';
		if ($pageId) {
			$path .= $pageId . '/image';
		} else {
			$path .= 'tmp/image';
		}

		$dir = $this->PUBLIC_PATH . $path;
		if (!is_dir($dir)) {
			mkdir($dir, 0755, true);
		}

		$files = $request->files->get('file');
		if ($files) {
			foreach ($files as $key => $file) {
				$type = strtolower($file->getMimeType());
				$types = ['image/png', 'image/jpg', 'image/gif', 'image/jpeg', 'image/pjpeg'];
				if (in_array($type, $types, true)) {
					$name = $file->getClientOriginalName();
					$ext = pathinfo($name, PATHINFO_EXTENSION);
					$filename = $this->removeAccent(pathinfo($name, PATHINFO_FILENAME), '-');
					$thumbName = $filename . '_thumb.' . $ext;
					$filename .= '.' . $ext;

					// Thumbnail
					$imagine = new Imagine();
					$size = new Box(100, 74);
					$mode = ManipulatorInterface::THUMBNAIL_INSET;
					$resizeImg = $imagine->open($file->getPathname())->thumbnail($size, $mode);
					$resizeSize = $resizeImg->getSize();
					$preserve = $imagine->create($size);
					$startX = ($size->getWidth() - $resizeSize->getWidth()) / 2;
					$startY = ($size->getHeight() - $resizeSize->getHeight()) / 2;
					$preserve->paste($resizeImg, new Point((int) $startX, (int) $startY))
						->save($dir . '/' . $thumbName);

					$file->move($dir, $filename);

					$data['file-' . $key] = [
						'id' => $pageId ? (string) $pageId : '0',
						'url' => $path . '/' . $filename,
					];
				}
			}
		}

		return new JsonResponse($data);
	}

	public function redactorFileUpload(Request $request): JsonResponse
	{
		$pageId = $request->query->get('page_id');
		$data = [];

		$path = '/data/page/';
		if ($pageId) {
			$path .= $pageId . '/file';
		} else {
			$path .= 'tmp/file';
		}

		$dir = $this->PUBLIC_PATH . $path;
		if (!is_dir($dir)) {
			mkdir($dir, 0755, true);
		}

		$files = $request->files->get('file');
		if ($files) {
			foreach ($files as $key => $file) {
				$name = $file->getClientOriginalName();
				$ext = pathinfo($name, PATHINFO_EXTENSION);
				$filename = $this->removeAccent(pathinfo($name, PATHINFO_FILENAME), '-') . '.' . $ext;
				$file->move($dir, $filename);

				$data['file-' . $key] = [
					'id' => $pageId ? (string) $pageId : '0',
					'url' => $path . '/' . $filename,
					'name' => $name,
				];
			}
		}

		return new JsonResponse($data);
	}

	private function savePagesSort(array $data, ?int $parent = null, int $depth = 1, int $rank = 1, int $rankTotal = 1): int
	{
		$identity = $this->security->getUser();

		foreach ($data as $item) {
			$this->pageRepository->updatePost((int) $item['id'], [
				'parent' => $parent,
				'depth' => $depth,
				'rank' => $rank,
				'rank_total' => $rankTotal,
				'updated_date' => date('Y-m-d H:i:s'),
				'updated_user' => $identity->getUserIdentifier(),
			]);
			$rank++;
			$rankTotal++;
			if (isset($item['children'])) {
				$rankTotal = $this->savePagesSort($item['children'], (int) $item['id'], $depth + 1, 1, $rankTotal);
			}
		}

		return $rankTotal;
	}

	private function validateForm(array $post): array
	{
		$errors = [];
		if (empty($post['title'])) {
			$errors['title'] = 'Název je povinný';
		}
		if (empty($post['url'])) {
			$errors['url'] = 'URL adresa je povinná';
		}
		return $errors;
	}

	private function createImage(string $file, string $target, string $filename, int $width, int $height, string $type = 'jpg'): ?string
	{
		$imagine = new Imagine();
		try {
			$image = $imagine->open($file);
			$size = $image->getSize();
			$imageWidth = $size->getWidth();
			$imageHeight = $size->getHeight();
			$palette = new RGB();
			$alpha = $type === 'png' ? 0 : 100;
			$ratioHd = $width / $height;
			$ratioImage = $imageWidth / $imageHeight;

			if ($ratioImage >= $ratioHd) {
				$canvasHeight = (int) round(($imageWidth / $width) * $height);
				$size = new Box($imageWidth, $canvasHeight);
				$color = $palette->color('#fff', $alpha);
				$imageTmp = $imagine->create($size, $color);
				$imageTmp->paste($image, new Point(0, (int) (($canvasHeight / 2) - ($imageHeight / 2))));
			} else {
				$canvasWidth = (int) round(($imageHeight / $height) * $width);
				$size = new Box($canvasWidth, $imageHeight);
				$color = $palette->color('#fff', $alpha);
				$imageTmp = $imagine->create($size, $color);
				$imageTmp->paste($image, new Point((int) (($canvasWidth / 2) - ($imageWidth / 2)), 0));
			}

			$image = $imageTmp;
			$image->resize(new Box($width, $height))
				->thumbnail(new Box($width, $height), ManipulatorInterface::THUMBNAIL_INSET);

			$ext = $type;
			$options = match ($type) {
				'gif' => ['flatten' => false],
				'png' => ['png_compression_level' => 8],
				default => ['jpeg_quality' => 85],
			};
			$image->save($this->PUBLIC_PATH . '/' . $target . $filename . '.' . $ext, $options);
			unlink($file);

			return $target . $filename . '.' . $ext;
		} catch (\Imagine\Exception\RuntimeException $e) {
			return $e->getMessage();
		}
	}

	private function removeAccent(string $string, string $separator = '-'): string
	{
		$transliterator = Transliterator::createFromRules(
			':: Any-Latin; :: Latin-ASCII; :: NFD; :: [:Nonspacing Mark:] Remove; :: Lower(); :: NFC;',
			Transliterator::FORWARD
		);
		$string = $transliterator->transliterate($string);
		$string = preg_replace('/[^a-z0-9\s]/', '', $string);
		$string = preg_replace('/[\s]+/', $separator, $string);
		return trim($string, $separator);
	}

	private function copyDir(string $source, string $target): void
	{
		if (is_dir($source)) {
			if (!is_dir($target)) {
				mkdir($target, 0777, true);
			}
			foreach (array_diff(scandir($source), ['.', '..']) as $file) {
				if (is_dir($source . '/' . $file)) {
					$this->copyDir($source . '/' . $file, $target . '/' . $file);
				} else {
					copy($source . '/' . $file, $target . '/' . $file);
				}
			}
		}
	}

	private function deleteDir(string $target): void
	{
		if (is_dir($target)) {
			$files = glob($target . '*', GLOB_MARK);
			foreach ($files as $file) {
				$this->deleteDir($file);
			}
			rmdir($target);
		} elseif (is_file($target)) {
			unlink($target);
		}
	}
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Page/Controller/Admin/PageWriteController.php
git commit -m "feat(page): add PageWriteController (add, edit, sort, delete, duplicate, upload)"
```

---

### Task 7: PageSettingController (admin)

**Files:**
- Create: `src/Page/Controller/Admin/PageSettingController.php`

- [ ] **Step 1: Vytvořit PageSettingController**

```php
<?php

namespace App\Page\Controller\Admin;

use App\Application\View\PhtmlRenderer;
use App\Page\Repository\PageSettingRepository;
use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Imagine\Image\Palette\RGB;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PageSettingController
{
	public function __construct(
		private PageSettingRepository $settingRepository,
		private PhtmlRenderer $renderer,
		private Security $security,
		private UrlGeneratorInterface $urlGenerator,
		private string $PUBLIC_PATH,
	) {}

	public function setting(Request $request): Response
	{
		$identity = $this->security->getUser();
		$setting = $this->settingRepository->fetchSetting();

		if ($request->isMethod('POST')) {
			$post = $request->request->all();

			if (isset($post['cancel'])) {
				return new RedirectResponse($this->urlGenerator->generate('admin_page_index'));
			}

			$imgWidth = (int) ($post['img_width'] ?? 800);
			$imgHeight = (int) ($post['img_height'] ?? 450);

			$this->settingRepository->updateSetting('img_width', (string) $imgWidth);
			$this->settingRepository->updateSetting('img_height', (string) $imgHeight);

			// Footer telefonní čísla
			for ($i = 1; $i <= 4; $i++) {
				$key = 'footer_number_' . $i;
				if (isset($post[$key])) {
					$this->settingRepository->updateSetting($key, $post[$key]);
				}
			}

			// Vygenerovat výchozí placeholder obrázek
			$this->generateDefaultImage($imgWidth, $imgHeight);

			$setting = $this->settingRepository->fetchSetting();
		}

		return new Response($this->renderer->renderWithAdminLayout('page/admin/setting', [
			'identity' => $identity,
			'pageTitle' => 'Stránky - nastavení',
			'setting' => $setting,
		]));
	}

	private function generateDefaultImage(int $width, int $height): void
	{
		$imagine = new Imagine();
		$palette = new RGB();
		$size = new Box($width, $height);
		$color = $palette->color('#e9ecef', 100);
		$image = $imagine->create($size, $color);
		$image->save($this->PUBLIC_PATH . '/data/page/!default-page.png', ['png_compression_level' => 8]);
	}
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Page/Controller/Admin/PageSettingController.php
git commit -m "feat(page): add PageSettingController"
```

---

### Task 8: PageWebController (web zobrazení)

**Files:**
- Create: `src/Page/Controller/Web/PageWebController.php`

- [ ] **Step 1: Vytvořit PageWebController**

```php
<?php

namespace App\Page\Controller\Web;

use App\Application\View\PhtmlRenderer;
use App\Page\Repository\PageRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PageWebController
{
	public function __construct(
		private PageRepository $pageRepository,
		private PhtmlRenderer $renderer,
		private UrlGeneratorInterface $urlGenerator,
	) {}

	public function page(Request $request, int $page_id = 0): Response
	{
		if ($page_id === 0) {
			// Fallback: najít stránku podle URL
			$url = basename($request->getPathInfo());
			$page = $this->pageRepository->findPostBy('url', $url);
		} else {
			$page = $this->pageRepository->findPostBy('id', $page_id);
		}

		if (!$page) {
			return new RedirectResponse($this->urlGenerator->generate('app_home'));
		}

		return new Response($this->renderer->renderWithLayout('page/web/page', [
			'page' => $page,
		]));
	}
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Page/Controller/Web/PageWebController.php
git commit -m "feat(page): add PageWebController"
```

---

### Task 9: Admin šablony — index + list

**Files:**
- Create: `templates/page/admin/index.phtml`
- Create: `templates/page/admin/list.phtml`

- [ ] **Step 1: Vytvořit index.phtml**

(Zjednodušená verze polar indexu — počet stránek, bez changelog a verze modulu)

- [ ] **Step 2: Vytvořit list.phtml**

(Bootstrap-table se všemi JS formattery, duplikovat/smazat modaly, query params s lang. Přeložit všechny texty do CZ z .po souboru.)

- [ ] **Step 3: Vytvořit warning modal**

Create `templates/admin/modal/warning.phtml` (pro duplikování — žlutý modal s potvrzením)

- [ ] **Step 4: Commit**

```bash
git add templates/page/admin/index.phtml templates/page/admin/list.phtml templates/admin/modal/warning.phtml
git commit -m "feat(page): add admin index and list templates"
```

---

### Task 10: Admin šablony — add + edit + pageForm

**Files:**
- Create: `templates/page/admin/add.phtml`
- Create: `templates/page/admin/edit.phtml`
- Create: `templates/page/admin/pageForm.phtml`

- [ ] **Step 1: Vytvořit pageForm.phtml**

(Formulář s active, header, title, url + generate button, content (Redactor WYSIWYG), image hidden, seo_keywords, seo_description, submit/cancel)

- [ ] **Step 2: Vytvořit add.phtml**

(Obrázek vlevo s file-upload, formulář vpravo)

- [ ] **Step 3: Vytvořit edit.phtml**

(Jako add + delete image button + existující obrázek + set-default-image AJAX)

- [ ] **Step 4: Commit**

```bash
git add templates/page/admin/add.phtml templates/page/admin/edit.phtml templates/page/admin/pageForm.phtml
git commit -m "feat(page): add admin form templates (add, edit, pageForm)"
```

---

### Task 11: Admin šablona — sort + setting

**Files:**
- Create: `templates/page/admin/sort.phtml`
- Create: `templates/page/admin/setting.phtml`

- [ ] **Step 1: Vytvořit sort.phtml**

(Nestable drag-drop s dvěma sloupci: Hlavní menu + Menu v záhlaví)

- [ ] **Step 2: Vytvořit setting.phtml**

(Formulář: img_width, img_height, footer_number_1-4)

- [ ] **Step 3: Commit**

```bash
git add templates/page/admin/sort.phtml templates/page/admin/setting.phtml
git commit -m "feat(page): add sort and setting templates"
```

---

### Task 12: Web šablona — page.phtml

**Files:**
- Create: `templates/page/web/page.phtml`

- [ ] **Step 1: Vytvořit page.phtml**

(Zobrazení stránky — page header, content, SEO meta, Google Maps pokud je na stránce kontakt)

- [ ] **Step 2: Commit**

```bash
git add templates/page/web/page.phtml
git commit -m "feat(page): add web page template"
```

---

### Task 13: services.yaml + Smoke test

**Files:**
- Modify: `config/services.yaml`

- [ ] **Step 1: Přidat registrace do services.yaml**

```yaml
App\Page\Controller\:
    resource: '../src/Page/Controller/'
    tags: ['controller.service_arguments']
    bind:
        $PUBLIC_PATH: '%app.PUBLIC_PATH%'

App\Page\Service\PageConfigGenerator:
    arguments:
        $configDir: '%kernel.project_dir%/config'
        $cacheDir: '%kernel.cache_dir%'
```

- [ ] **Step 2: Cache clear + ověřit routes**

```bash
php bin/console cache:clear
php bin/console debug:router | Select-String "page"
```

Očekávaný výstup: všechny admin_page_* routes.

- [ ] **Step 3: Manuální test**

1. `/admin/pages` → index s počtem stránek
2. `/admin/pages/cs_CZ` → seznam stránek
3. Přidat stránku → formulář
4. Editovat stránku → formulář s daty
5. Řazení stránek → drag-drop
6. Web zobrazení stránky → /{url}

- [ ] **Step 4: Commit**

```bash
git add config/services.yaml
git commit -m "feat(page): register Page controllers and services"
```
