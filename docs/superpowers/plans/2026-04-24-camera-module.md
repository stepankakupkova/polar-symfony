# Camera Module Implementation Plan

> **For agentic workers:** REQUIRED: Use the `subagent-driven-development` agent (recommended) or `executing-plans` agent to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Přepsat modul Camera z Laminas do Symfony 1:1 — admin (CameraList, CameraWrite, SettingList, SettingWrite) + web (cameras, camera) část.

**Architecture:** Modulární struktura `src/Camera/`, tenké controllery, Doctrine DBAL QueryBuilder v repository, phtml šablony přes PhtmlRenderer. Bez ORM, bez Twig, bez Laminas.

**Tech Stack:** Symfony 8, PHP 8.5, Doctrine DBAL, PhtmlRenderer, YAML routes, phtml templates.

---

## Mapování souborů

### Nové / upravené soubory

**src/Camera/Controller/Admin/**
- Vytvořit: `src/Camera/Controller/Admin/CameraListController.php`
- Vytvořit: `src/Camera/Controller/Admin/CameraWriteController.php`
- Vytvořit: `src/Camera/Controller/Admin/SettingListController.php`
- Vytvořit: `src/Camera/Controller/Admin/SettingWriteController.php`

**src/Camera/Controller/Web/** (existuje, upravit)
- Upravit: `src/Camera/Controller/Web/CameraController.php`

**src/Camera/Repository/** (existuje, rozšířit)
- Upravit: `src/Camera/Repository/CameraRepository.php`
- Vytvořit: `src/Camera/Repository/SettingRepository.php`

**config/routes/camera.yaml** (existuje, rozšířit)
- Upravit: přidat admin routy

**templates/camera/**
- Vytvořit: `templates/camera/admin/index.phtml`
- Vytvořit: `templates/camera/admin/list.phtml`
- Vytvořit: `templates/camera/admin/add.phtml`
- Vytvořit: `templates/camera/admin/edit.phtml`
- Vytvořit: `templates/camera/admin/setting/index.phtml`
- Vytvořit: `templates/camera/admin/setting/setting.phtml`
- Vytvořit: `templates/camera/admin/partial/changelog.phtml`
- Vytvořit: `templates/camera/admin/partial/dashboard/widget.phtml`
- Vytvořit: `templates/camera/admin/partial/setting/widget.phtml`
- Vytvořit: `templates/camera/web/cameras.phtml`
- Vytvořit: `templates/camera/web/camera.phtml`

---

## Task 1: CameraRepository — rozšíření metod

Polarsou repository (`src/Camera/Repository/CameraRepository.php`) má jen `fetchAllLimit()`. Je potřeba přidat metody podle Laminas `MariaDbSqlRepository`: `fetchAll`, `fetchForBootstrapTable`, `getCountForBootstrapTable`, `findPostBy`, `getCount`, `getPaginator`.

**Files:**
- Modify: `src/Camera/Repository/CameraRepository.php`

- [ ] **Step 1: Přepsat CameraRepository**

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

namespace App\Camera\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

final class CameraRepository
{
    private string $table = 'cameras';

    public function __construct(private Connection $connection) {}

    /**
     * @throws Exception
     */
    public function fetchAll(): ?array
    {
        $result = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->table)
            ->orderBy('rank', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();

        return $result ?: null;
    }

    /**
     * @throws Exception
     */
    public function fetchAllLimit(int $limit): ?array
    {
        $result = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->table)
            ->orderBy('rank', 'ASC')
            ->setMaxResults($limit)
            ->executeQuery()
            ->fetchAllAssociative();

        return $result ?: null;
    }

    /**
     * @throws Exception
     */
    public function fetchForBootstrapTable(array $params): ?array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('id', 'title', 'description', 'url_m3u8', 'url_mpd', 'rank')
            ->from($this->table)
            ->orderBy($params['sort'] ?? 'rank', $params['order'] ?? 'asc');

        if (isset($params['limit'])) {
            $qb->setMaxResults((int) $params['limit']);
        }
        if (isset($params['offset'])) {
            $qb->setFirstResult((int) $params['offset']);
        }
        if (isset($params['search']) && $params['search'] !== '') {
            $qb->andWhere('MATCH (title, description) AGAINST (:search IN BOOLEAN MODE)')
               ->setParameter('search', $params['search'] . '*');
        }

        $rows = $qb->executeQuery()->fetchAllAssociative();
        if (!$rows) {
            return null;
        }

        foreach ($rows as $i => $row) {
            $rows[$i]['id']   = (int) $row['id'];
            $rows[$i]['rank'] = (int) $row['rank'];
        }

        return $rows;
    }

    /**
     * @throws Exception
     */
    public function getCountForBootstrapTable(array $params): int
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from($this->table);

        if (isset($params['search']) && $params['search'] !== '') {
            $qb->andWhere('MATCH (title, description) AGAINST (:search IN BOOLEAN MODE)')
               ->setParameter('search', $params['search'] . '*');
        }

        return (int) $qb->executeQuery()->fetchOne();
    }

    /**
     * @throws Exception
     * @throws \InvalidArgumentException
     */
    public function findPostBy(string $column, int|string $value): array
    {
        $row = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->table)
            ->where($column . ' = :value')
            ->setParameter('value', $value)
            ->executeQuery()
            ->fetchAssociative();

        if (!$row) {
            throw new \InvalidArgumentException(sprintf(
                'Záznam s identifikátorem "%s" nenalezen. Tabulka "%s".',
                $column . ' => ' . $value,
                $this->table
            ));
        }

        return $row;
    }

    /**
     * @throws Exception
     */
    public function getCount(): int
    {
        return (int) $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from($this->table)
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * Vrátí pole všech kamer pro stránkování (bez Laminas Paginatoru).
     * Controller si stránkování řeší sám.
     * @throws Exception
     */
    public function getPaginator(): ?array
    {
        return $this->fetchAll();
    }

    /**
     * @throws Exception
     */
    public function insert(array $data): int
    {
        $this->connection->insert($this->table, $data);
        return (int) $this->connection->lastInsertId();
    }

    /**
     * @throws Exception
     */
    public function update(array $data, int $id): void
    {
        $this->connection->update($this->table, $data, ['id' => $id]);
    }

    /**
     * @throws Exception
     */
    public function delete(int $id): void
    {
        $this->connection->delete($this->table, ['id' => $id]);
    }
}
```

- [ ] **Step 2: Ověřit, že soubor neobsahuje BOM a syntaktické chyby**

```
php -l src/Camera/Repository/CameraRepository.php
```

Očekáváno: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add src/Camera/Repository/CameraRepository.php
git commit -m "feat(camera): rozšíření CameraRepository o admin metody"
```

---

## Task 2: SettingRepository

**Files:**
- Create: `src/Camera/Repository/SettingRepository.php`

- [ ] **Step 1: Vytvořit SettingRepository**

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

namespace App\Camera\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

final class SettingRepository
{
    private string $table = 'camera_setting';

    public function __construct(private Connection $connection) {}

    /**
     * @throws Exception
     * @throws \InvalidArgumentException
     */
    public function fetchSetting(): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->table)
            ->where('flag = :flag')
            ->setParameter('flag', 'setting')
            ->executeQuery()
            ->fetchAllAssociative();

        if (!$rows) {
            throw new \InvalidArgumentException('Nastavení kamer nenalezeno.');
        }

        $setting = [];
        foreach ($rows as $row) {
            $setting[$row['variable']] = $row['value'];
        }
        return $setting;
    }
}
```

- [ ] **Step 2: Ověřit syntaxi**

```
php -l src/Camera/Repository/SettingRepository.php
```

Očekáváno: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add src/Camera/Repository/SettingRepository.php
git commit -m "feat(camera): SettingRepository"
```

---

## Task 3: Admin routy v camera.yaml

**Files:**
- Modify: `config/routes/camera.yaml`

- [ ] **Step 1: Přidat admin routy**

Přidat na konec souboru `config/routes/camera.yaml`:

```yaml
# Admin — Camera
admin_camera:
  path: /admin/kamery
  controller: App\Camera\Controller\Admin\CameraListController::index

admin_camera_list:
  path: /admin/kamery/seznam
  controller: App\Camera\Controller\Admin\CameraListController::list

admin_camera_add:
  path: /admin/kamery/seznam/pridat
  controller: App\Camera\Controller\Admin\CameraWriteController::add
  methods: [GET, POST]

admin_camera_edit:
  path: /admin/kamery/seznam/upravit/{id}
  controller: App\Camera\Controller\Admin\CameraWriteController::edit
  methods: [GET, POST]
  requirements:
    id: '\d+'

admin_camera_json_list:
  path: /admin/kamery/json-list/{action}
  controller: App\Camera\Controller\Admin\CameraListController::jsonList
  requirements:
    action: '[a-zA-Z][a-zA-Z0-9_-]+'

admin_camera_json_write:
  path: /admin/kamery/json-write/{action}
  controller: App\Camera\Controller\Admin\CameraWriteController::jsonWrite
  requirements:
    action: '[a-zA-Z][a-zA-Z0-9_-]+'

# Admin — Setting
admin_camera_setting:
  path: /admin/kamery/nastaveni
  controller: App\Camera\Controller\Admin\SettingWriteController::setting
  methods: [GET, POST]

admin_camera_setting_index:
  path: /admin/kamery/nastaveni/index
  controller: App\Camera\Controller\Admin\SettingListController::index
```

- [ ] **Step 2: Commit**

```bash
git add config/routes/camera.yaml
git commit -m "feat(camera): admin routy"
```

---

## Task 4: CameraListController (admin)

Odpovídá Laminas `CameraListController` — akce `index`, `list`, `getList`, `getCamera`.

**Files:**
- Create: `src/Camera/Controller/Admin/CameraListController.php`

- [ ] **Step 1: Vytvořit controller**

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

namespace App\Camera\Controller\Admin;

use App\Application\View\PhtmlRenderer;
use App\Camera\Repository\CameraRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class CameraListController
{
    public function __construct(
        private CameraRepository $cameraRepository,
        private PhtmlRenderer $renderer,
    ) {}

    public function index(): Response
    {
        return new Response($this->renderer->renderWithAdminLayout('camera/admin/index', [
            'pageTitle'   => 'Cameras',
            'countCamera' => $this->cameraRepository->getCount(),
        ]));
    }

    public function list(): Response
    {
        return new Response($this->renderer->renderWithAdminLayout('camera/admin/list', [
            'pageTitle' => 'Cameras',
        ]));
    }

    public function jsonList(Request $request, string $action): JsonResponse
    {
        $params = $request->query->all();

        $rows  = null;
        $total = 0;

        try {
            $rows  = $this->cameraRepository->fetchForBootstrapTable($params);
            $total = $this->cameraRepository->getCountForBootstrapTable($params);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'rows'    => null,
                'total'   => 0,
            ]);
        }

        return new JsonResponse([
            'success' => true,
            'rows'    => $rows,
            'total'   => $total,
        ]);
    }

    public function getCamera(Request $request): JsonResponse
    {
        $success = true;
        $message = null;
        $camera  = null;

        try {
            $id     = $request->request->getInt('id');
            $camera = $this->cameraRepository->findPostBy('id', $id);
        } catch (\Exception $e) {
            $success = false;
            $message = $e->getMessage();
        }

        return new JsonResponse([
            'success' => $success,
            'message' => $message,
            'camera'  => $camera,
        ]);
    }
}
```

- [ ] **Step 2: Ověřit syntaxi**

```
php -l src/Camera/Controller/Admin/CameraListController.php
```

- [ ] **Step 3: Commit**

```bash
git add src/Camera/Controller/Admin/CameraListController.php
git commit -m "feat(camera): CameraListController (admin)"
```

---

## Task 5: CameraWriteController (admin)

Odpovídá Laminas `CameraWriteController` — akce `add`, `edit`, `deleteCamera`, `setOrder`.

**Files:**
- Create: `src/Camera/Controller/Admin/CameraWriteController.php`

- [ ] **Step 1: Vytvořit controller**

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

namespace App\Camera\Controller\Admin;

use App\Application\Service\FlashMessenger;
use App\Application\Service\Logger;
use App\Application\View\PhtmlRenderer;
use App\Camera\Repository\CameraRepository;
use App\Camera\Repository\SettingRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class CameraWriteController
{
    public function __construct(
        private CameraRepository $cameraRepository,
        private FlashMessenger $flashMessenger,
        private Logger $logger,
        private PhtmlRenderer $renderer,
        private Security $security,
        private SettingRepository $settingRepository,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function add(Request $request): Response
    {
        $identity = $this->security->getUser();

        $post   = [];
        $errors = [];

        if ($request->isMethod('POST')) {
            $post = $request->request->all();

            if (isset($post['cancel'])) {
                return new RedirectResponse($this->urlGenerator->generate('admin_camera_list'));
            }

            $errors = $this->validateForm($post);
            if (empty($errors)) {
                try {
                    $data = [
                        'title'       => $post['title']       ?? '',
                        'description' => $post['description'] ?? '',
                        'url_m3u8'    => $post['url_m3u8']    ?? '',
                        'url_mpd'     => $post['url_mpd']     ?? '',
                        'rank'        => $this->cameraRepository->getCount() + 1,
                    ];

                    $camera = $this->cameraRepository->insert($data);

                    $this->flashMessenger->addSuccess(
                        'Cameras <strong>"' . htmlspecialchars($post['title']) . '"</strong> byla přidána.'
                    );

                    // Log
                    $this->logger->notice('CAMERA - Add camera', [
                        'description' => 'OK',
                        'user'        => $identity->getUserIdentifier(),
                        'file'        => __FILE__,
                    ]);

                    return new RedirectResponse($this->urlGenerator->generate('admin_camera_list'));
                } catch (\Exception $e) {
                    // Log
                    $this->logger->error('CAMERA - Add camera', [
                        'description' => 'ERROR',
                        'user'        => $identity->getUserIdentifier(),
                        'file'        => __FILE__,
                        'trace'       => $e->getMessage(),
                    ]);

                    $errors[] = $e->getMessage();
                }
            }
        }

        //$setting = $this->settingRepository->fetchSetting();

        return new Response($this->renderer->renderWithAdminLayout('camera/admin/add', [
            'pageTitle' => 'Cameras',
            'post'      => $post,
            'errors'    => $errors,
            //'setting'   => $setting,
        ]));
    }

    public function edit(Request $request, int $id): Response
    {
        $identity = $this->security->getUser();

        try {
            $camera = $this->cameraRepository->findPostBy('id', $id);
        } catch (\Exception) {
            return new RedirectResponse($this->urlGenerator->generate('admin_camera_list'));
        }

        $errors = [];

        if ($request->isMethod('POST')) {
            $post = $request->request->all();

            if (isset($post['cancel'])) {
                return new RedirectResponse($this->urlGenerator->generate('admin_camera_list'));
            }

            $errors = $this->validateForm($post);
            if (empty($errors)) {
                try {
                    $data = [
                        'title'       => $post['title']       ?? '',
                        'description' => $post['description'] ?? '',
                        'url_m3u8'    => $post['url_m3u8']    ?? '',
                        'url_mpd'     => $post['url_mpd']     ?? '',
                    ];

                    $this->cameraRepository->update($data, $id);

                    $this->flashMessenger->addSuccess(
                        'Cameras <strong>"' . htmlspecialchars($post['title']) . '"</strong> byla upravena.'
                    );

                    // Log
                    $this->logger->notice('CAMERA - Edit camera', [
                        'description' => 'OK',
                        'user'        => $identity->getUserIdentifier(),
                        'file'        => __FILE__,
                    ]);

                    return new RedirectResponse($this->urlGenerator->generate('admin_camera_list'));
                } catch (\Exception $e) {
                    // Log
                    $this->logger->error('CAMERA - Edit camera', [
                        'description' => 'ERROR',
                        'user'        => $identity->getUserIdentifier(),
                        'file'        => __FILE__,
                        'trace'       => $e->getMessage(),
                    ]);

                    $errors[] = $e->getMessage();
                }
            } else {
                $camera = array_merge($camera, $post);
            }
        }

        //$setting = $this->settingRepository->fetchSetting();

        return new Response($this->renderer->renderWithAdminLayout('camera/admin/edit', [
            'pageTitle' => 'Cameras',
            'camera'    => $camera,
            'errors'    => $errors,
            //'setting'   => $setting,
        ]));
    }

    public function jsonWrite(Request $request, string $action): JsonResponse
    {
        return match ($action) {
            'delete-camera' => $this->deleteCamera($request),
            'set-order'     => $this->setOrder($request),
            default         => new JsonResponse(['success' => false, 'message' => 'Unknown action']),
        };
    }

    private function deleteCamera(Request $request): JsonResponse
    {
        $success   = true;
        $message   = null;
        $camera_id = null;

        $identity = $this->security->getUser();

        try {
            $camera_id = $request->request->getInt('id');

            $camera = $this->cameraRepository->findPostBy('id', $camera_id);

            if ($camera) {
                $this->cameraRepository->delete($camera_id);

                $cameras = $this->cameraRepository->fetchAll();
                $rank = 1;
                foreach ($cameras as $cam) {
                    $this->cameraRepository->update(['rank' => $rank], (int) $cam['id']);
                    $rank++;
                }

                // Log
                $this->logger->notice('CAMERA - Delete camera', [
                    'description' => 'OK',
                    'user'        => $identity->getUserIdentifier(),
                    'file'        => __FILE__,
                ]);
            } else {
                $success = false;
                $message = 'Cannot find camera';

                // Log
                $this->logger->error('CAMERA - Delete camera', [
                    'description' => 'ERROR',
                    'user'        => $identity->getUserIdentifier(),
                    'file'        => __FILE__,
                    'trace'       => $message,
                ]);
            }
        } catch (\Exception $e) {
            $success = false;
            $message = $e->getMessage();

            // Log
            $this->logger->error('CAMERA - Delete camera', [
                'description' => 'ERROR',
                'user'        => $identity->getUserIdentifier(),
                'file'        => __FILE__,
                'trace'       => $message,
            ]);
        }

        return new JsonResponse([
            'success'   => $success,
            'message'   => $message,
            'camera_id' => $camera_id,
        ]);
    }

    private function setOrder(Request $request): JsonResponse
    {
        $success = true;
        $message = null;

        try {
            $data = $request->request->all()['data'] ?? [];
            $rank = 1;
            foreach ($data as $item) {
                $this->cameraRepository->update(['rank' => $rank], (int) $item['id']);
                $rank++;
            }
        } catch (\Exception $e) {
            $success = false;
            $message = $e->getMessage();
        }

        return new JsonResponse([
            'success' => $success,
            'message' => $message,
        ]);
    }

    private function validateForm(array $post): array
    {
        $errors = [];

        if (empty($post['title'])) {
            $errors['title'] = 'Název je povinný.';
        }

        return $errors;
    }
}
```

- [ ] **Step 2: Ověřit syntaxi**

```
php -l src/Camera/Controller/Admin/CameraWriteController.php
```

- [ ] **Step 3: Commit**

```bash
git add src/Camera/Controller/Admin/CameraWriteController.php
git commit -m "feat(camera): CameraWriteController (admin)"
```

---

## Task 6: SettingListController + SettingWriteController (admin)

**Files:**
- Create: `src/Camera/Controller/Admin/SettingListController.php`
- Create: `src/Camera/Controller/Admin/SettingWriteController.php`

- [ ] **Step 1: Vytvořit SettingListController**

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

namespace App\Camera\Controller\Admin;

use App\Application\View\PhtmlRenderer;
use Symfony\Component\HttpFoundation\Response;

final class SettingListController
{
    public function __construct(
        private PhtmlRenderer $renderer,
    ) {}

    public function index(): Response
    {
        return new Response($this->renderer->renderWithAdminLayout('camera/admin/setting/index', [
            'pageTitle' => 'Cameras — Nastavení',
        ]));
    }
}
```

- [ ] **Step 2: Vytvořit SettingWriteController**

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

namespace App\Camera\Controller\Admin;

use App\Application\Service\Logger;
use App\Application\View\PhtmlRenderer;
use App\Camera\Repository\SettingRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class SettingWriteController
{
    public function __construct(
        private Logger $logger,
        private PhtmlRenderer $renderer,
        private Security $security,
        private SettingRepository $settingRepository,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function setting(Request $request): Response
    {
        //$setting = $this->settingRepository->fetchSetting();

        /** @var \Symfony\Component\Security\Core\User\UserInterface $identity */
        //$identity = $this->security->getUser();

        return new Response($this->renderer->renderWithAdminLayout('camera/admin/setting/setting', [
            'pageTitle' => 'Cameras — Nastavení',
            //'setting'   => $setting,
        ]));
    }
}
```

- [ ] **Step 3: Ověřit syntaxi obou souborů**

```
php -l src/Camera/Controller/Admin/SettingListController.php
php -l src/Camera/Controller/Admin/SettingWriteController.php
```

- [ ] **Step 4: Commit**

```bash
git add src/Camera/Controller/Admin/SettingListController.php src/Camera/Controller/Admin/SettingWriteController.php
git commit -m "feat(camera): SettingListController + SettingWriteController (admin)"
```

---

## Task 7: Admin šablony — adresářová struktura + partial

**Files:**
- Create: `templates/camera/admin/partial/changelog.phtml`
- Create: `templates/camera/admin/partial/dashboard/widget.phtml`
- Create: `templates/camera/admin/partial/setting/widget.phtml`

- [ ] **Step 1: Vytvořit adresáře**

```powershell
New-Item -ItemType Directory -Path templates/camera/admin/partial/dashboard -Force
New-Item -ItemType Directory -Path templates/camera/admin/partial/setting -Force
New-Item -ItemType Directory -Path templates/camera/admin/setting -Force
New-Item -ItemType Directory -Path templates/camera/web -Force
```

- [ ] **Step 2: Vytvořit `templates/camera/admin/partial/changelog.phtml`**

Kopie z `polar/module/Camera/view/partial/changelog.phtml`, Laminas helpery nahradit:
- `$this->translate(...)` → `$view->trans(...)`
- `$this->dateFormat(...)` → přímé PHP formátování

```php
<?php
/*
 * @project polar
 * @author Rostislav Greipel
 * @link https://rogr.cz
 * @copyright (c) 2011 - 2021 ROGR All Rights Reserved
 * @license https://rogr.cz/license/eula EULA License
 */
?>

<h4 class="d-lg-none">
    Cameras
</h4>
<div class="timeline timeline-simple changelog">
    <div class="scrollable visible-slider colored-slider" data-plugin-scrollable style="height: 425px;">
        <div class="scrollable-content">
            <div class="tm-body">
                <ol class="tm-items">
                    <div class="tm-title">
                        <h5 class="m-0 pt-2 pb-2 text-uppercase">
                            2021
                        </h5>
                    </div>
                    <?php $datetime = new DateTime('2021-10-04') ?>
                    <li>
                        <div class="tm-box">
                            <h4>
                                0.0.1
                            </h4>
                            –
                            <span class="release-date">
                                <?= $datetime->format('j. n. Y') ?>
                            </span>
                            <ul class="list-unstyled">
                                <li>
                                    <span class="badge badge-primary">
                                        Počáteční
                                    </span>
                                    -
                                    Počáteční vydání
                                </li>
                            </ul>
                        </div>
                    </li>
                </ol>
            </div>
        </div>
    </div>
</div>
```

- [ ] **Step 3: Vytvořit `templates/camera/admin/partial/dashboard/widget.phtml`**

Kopie z `polar/module/Camera/view/partial/dashboard/widget.phtml`, nahradit helpery:
- `$this->translate(...)` → hardcoded česky nebo `$view->trans(...)`
- `$this->url(...)` → `$view->path(...)`
- `$this->numberFormat(...)` → `number_format(...)`
- `Module::VERSION` → hardcoded verze nebo vynechat

```php
<?php
/*
 * @project polar
 * @author Rostislav Greipel
 * @link https://rogr.cz
 * @copyright (c) 2011 - 2021 ROGR All Rights Reserved
 * @license https://rogr.cz/license/eula EULA License
 */
?>

<div class="col-sm">
    <section class="card card-featured-top card-featured-primary mb-4">
        <div class="card-body">
            <div class="widget-summary">
                <div class="widget-summary-col widget-summary-col-icon">
                    <div class="summary-icon bg-primary hvr-grow">
                        <i class="fa fa-camera"></i>
                    </div>
                </div>
                <div class="widget-summary-col">
                    <div class="summary">
                        <h4 class="title text-nowrap">
                            Cameras
                        </h4>
                        <div class="info text-nowrap">
                            <strong class="amount">
                                <?= number_format($countCamera ?? 0) ?>
                            </strong>
                            <span class="text-primary">

                            </span>
                        </div>
                    </div>
                    <div class="summary-footer">
                        <span class="pull-left" data-toggle="tooltip" data-placement="right" title="Verze">
                            0.0.1
                        </span>
                        <a href="<?= $view->path('admin_camera_list') ?>" class="text-muted text-uppercase">
                            <i class="fa fa-fw fa-ellipsis-v"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
```

- [ ] **Step 4: Vytvořit `templates/camera/admin/partial/setting/widget.phtml`**

Kopie z `polar/module/Camera/view/partial/setting/widget.phtml`:

```php
<?php
/*
 * @project polar
 * @author Rostislav Greipel
 * @link https://rogr.cz
 * @copyright (c) 2011 - 2021 ROGR All Rights Reserved
 * @license https://rogr.cz/license/eula EULA License
 */
?>

<section class="card card-featured-top card-featured-danger mb-4">
    <div class="card-body">
        <div class="widget-summary">
            <div class="widget-summary-col widget-summary-col-icon">
                <div class="summary-icon bg-danger hvr-grow">
                    <i class="fa fa-camera"></i>
                </div>
            </div>
            <div class="widget-summary-col">
                <div class="summary">
                    <h4 class="title text-nowrap">
                        Cameras
                    </h4>
                    <div class="info text-nowrap">
                        <strong class="amount">
                            <?= number_format($countCamera ?? 0) ?>
                        </strong>
                        <span class="text-danger">

                        </span>
                    </div>
                </div>
                <div class="summary-footer text-nowrap">
                    <a href="<?= $view->path('admin_camera_setting') ?>" class="text-muted text-uppercase">
                        <i class="fa fa-fw fa-ellipsis-v"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
```

- [ ] **Step 5: Commit**

```bash
git add templates/camera/admin/partial/
git commit -m "feat(camera): admin partial šablony (changelog, dashboard/widget, setting/widget)"
```

---

## Task 8: Admin šablona index.phtml

Odpovídá `polar/module/Camera/view/camera/camera/camera-list/index.phtml`.

**Files:**
- Create: `templates/camera/admin/index.phtml`

- [ ] **Step 1: Vytvořit `templates/camera/admin/index.phtml`**

Nahradit:
- `$this->render('camera/dashboard/widget')` → `$view->include('camera/admin/partial/dashboard/widget', ['countCamera' => $countCamera])`
- `$this->render('camera/changelog')` → `$view->include('camera/admin/partial/changelog')`
- `$this->url('admin/camera/setting')` → `$view->path('admin_camera_setting')`
- `Module::VERSION` → `0.0.1`
- `$this->translate(...)` → hardcoded česky

```php
<?php
/*
 * @project polar
 * @author Rostislav Greipel
 * @link https://rogr.cz
 * @copyright (c) 2011 - 2021 ROGR All Rights Reserved
 * @license https://rogr.cz/license/eula EULA License
 */
?>

<div class="row">
    <div class="col-lg-6">
        <div class="row">
            <?= $view->include('camera/admin/partial/dashboard/widget', ['countCamera' => $countCamera]) ?>
            <div class="col-sm">
                <section class="card card-featured-top card-featured-danger mb-4">
                    <div class="card-body">
                        <div class="widget-summary">
                            <div class="widget-summary-col widget-summary-col-icon">
                                <div class="summary-icon bg-danger hvr-grow">
                                    <i class="fa fa-cogs"></i>
                                </div>
                            </div>
                            <div class="widget-summary-col">
                                <div class="summary">
                                    <h4 class="title text-nowrap">
                                        Nastavení
                                    </h4>
                                    <div class="info text-nowrap">
                                        <strong class="amount">

                                        </strong>
                                        <span class="text-danger">

                                        </span>
                                    </div>
                                </div>
                                <div class="summary-footer">
                                    <a href="<?= $view->path('admin_camera_setting') ?>" class="text-muted text-uppercase">
                                        <i class="fa fa-fw fa-ellipsis-v"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <section class="card mb-4">
            <header class="card-header">
                <div class="card-header-icon bg-primary">
                    <i class="fa fa-camera"></i>
                </div>
            </header>
            <div class="card-body text-center">
                <h3 class="font-weight-semibold mt-3 text-center">
                    Cameras
                </h3>
                <p class="text-center">
                    0.0.1
                </p>
            </div>
        </section>
        <section class="card card-featured card-featured-primary mt-0">
            <header class="card-header">
                <div class="card-actions pull-right mb-0">
                    <a href="#" class="card-action card-action-toggle" data-card-toggle></a>
                    <a href="#" class="card-action card-action-dismiss" data-card-dismiss></a>
                </div>
                <h2 class="card-title">
                    Changelog
                </h2>
            </header>
            <div class="card-body">
                <?= $view->include('camera/admin/partial/changelog') ?>
            </div>
        </section>
    </div>
</div>
```

- [ ] **Step 2: Commit**

```bash
git add templates/camera/admin/index.phtml
git commit -m "feat(camera): admin/index.phtml"
```

---

## Task 9: Admin šablona list.phtml

Odpovídá `polar/module/Camera/view/camera/camera/camera-list/list.phtml`. Obsahuje bootstrap-table s AJAX načítáním.

**Files:**
- Create: `templates/camera/admin/list.phtml`

- [ ] **Step 1: Přečíst celou Laminas šablonu**

Přečti `polar/module/Camera/view/camera/camera/camera-list/list.phtml` celou a nahraď:
- `$this->url('admin/camera/list/add')` → `$view->path('admin_camera_add')`
- `$this->url('admin/camera/json-list', ['action' => 'get-list'])` → `$view->path('admin_camera_json_list', ['action' => 'get-list'])`
- `$this->url('admin/camera/json-list', ['action' => 'get-camera'])` → `$view->path('admin_camera_json_list', ['action' => 'get-camera'])`
- `$this->url('admin/camera/json-write', ['action' => 'delete-camera'])` → `$view->path('admin_camera_json_write', ['action' => 'delete-camera'])`
- `$this->url('admin/camera/list/edit', ['id' => ...])` → `$view->path('admin_camera_edit', ['id' => ...])`
- `$this->basePath(...)` → `$view->asset(...)`
- `$this->translate(...)` → hardcoded česky
- `$this->inlineScript()->appendFile(...)` → `$view->addScript(...)`
- `$this->headLink()->appendStylesheet(...)` → `$view->addStyle(...)`
- `$identity = $this->layout()->getVariable('identity')` → `$identity = $view->getUser()`
- `$locale = Locale::getDefault()` → `$locale = 'cs_CZ'`

> Pozor: metody `$view->addScript()`, `$view->addStyle()`, `$view->getUser()` — ověř, jak jsou implementovány v `src/Application/View/PhtmlRenderer.php` a použij správný způsob. Viz ostatní existující list.phtml šablony v projektu (např. `templates/banner/admin/leaderboard/list.phtml`).

- [ ] **Step 2: Commit**

```bash
git add templates/camera/admin/list.phtml
git commit -m "feat(camera): admin/list.phtml"
```

---

## Task 10: Admin šablony add.phtml a edit.phtml

Odpovídají `polar/module/Camera/view/camera/camera/camera-write/add.phtml` a `edit.phtml`.

V Laminas se formulář renderoval přes `$this->partial('camera/form/camera', [...])` — v Symfony nemáme Laminas Form. Formulář renderujeme přímo v šabloně jako HTML, nebo přes `$view->include('camera/admin/partial/form', [...])`. Protože polarsou `cameraForm.php` je Laminas Form objekt — **nahradit celé renderování formu přímým HTML** (viz vzorové šablony v banneru, kde jsou editační formuláře).

**Files:**
- Create: `templates/camera/admin/add.phtml`
- Create: `templates/camera/admin/edit.phtml`

- [ ] **Step 1: Přečíst vzorovou add šablonu v banner modulu**

Přečti např. `templates/banner/admin/leaderboard/add.phtml` pro pochopení struktury formuláře v Symfony verzi.

- [ ] **Step 2: Vytvořit `templates/camera/admin/add.phtml`**

```php
<?php
/*
 * @project polar
 * @author Rostislav Greipel
 * @link https://rogr.cz
 * @copyright (c) 2011 - 2021 ROGR All Rights Reserved
 * @license https://rogr.cz/license/eula EULA License
 */
?>

<div class="row">
    <div class="col-lg-3">

    </div>
    <div class="col-lg-9">
        <section class="card card-primary">
            <header class="card-header">
                <div class="card-actions">
                    <span id="msgNotSaved" class="badge badge-danger d-none">
                        Neuloženo
                    </span>
                </div>
                <h2 class="card-title">
                    Přidat kameru
                </h2>
            </header>
            <div class="card-body">
                <form action="<?= $view->path('admin_camera_add') ?>" method="post" id="frmCamera" class="form-horizontal">
                    <div class="form-group row">
                        <label class="col-lg-3 col-form-label" for="title">Název *</label>
                        <div class="col-lg-6">
                            <input type="text" name="title" id="title" class="form-control<?= isset($errors['title']) ? ' is-invalid' : '' ?>" value="<?= htmlspecialchars($post['title'] ?? '') ?>" required>
                            <?php if (isset($errors['title'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errors['title']) ?></div>
                            <?php endif ?>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-3 col-form-label" for="description">Popis</label>
                        <div class="col-lg-6">
                            <textarea name="description" id="description" class="form-control" rows="3"><?= htmlspecialchars($post['description'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-3 col-form-label" for="url_m3u8">URL M3U8</label>
                        <div class="col-lg-6">
                            <input type="text" name="url_m3u8" id="url_m3u8" class="form-control" value="<?= htmlspecialchars($post['url_m3u8'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-3 col-form-label" for="url_mpd">URL MPD</label>
                        <div class="col-lg-6">
                            <input type="text" name="url_mpd" id="url_mpd" class="form-control" value="<?= htmlspecialchars($post['url_mpd'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-group row">
                        <div class="col-lg-9 ml-auto">
                            <button type="submit" name="submit" class="btn btn-primary">Uložit</button>
                            <button type="submit" name="cancel" class="btn btn-default">Zrušit</button>
                        </div>
                    </div>
                </form>
            </div>
        </section>
    </div>
</div>
```

- [ ] **Step 3: Vytvořit `templates/camera/admin/edit.phtml`**

Kopie `add.phtml`, změny:
- `action` → `$view->path('admin_camera_edit', ['id' => $camera['id']])`
- nadpis → `Upravit kameru`
- hodnoty polí → `$camera['title']` atd.

```php
<?php
/*
 * @project polar
 * @author Rostislav Greipel
 * @link https://rogr.cz
 * @copyright (c) 2011 - 2021 ROGR All Rights Reserved
 * @license https://rogr.cz/license/eula EULA License
 */
?>

<div class="row">
    <div class="col-lg-3">

    </div>
    <div class="col-lg-9">
        <section class="card card-primary">
            <header class="card-header">
                <div class="card-actions">
                    <span id="msgNotSaved" class="badge badge-danger d-none">
                        Neuloženo
                    </span>
                </div>
                <h2 class="card-title">
                    Upravit kameru
                </h2>
            </header>
            <div class="card-body">
                <form action="<?= $view->path('admin_camera_edit', ['id' => $camera['id']]) ?>" method="post" id="frmCamera" class="form-horizontal">
                    <div class="form-group row">
                        <label class="col-lg-3 col-form-label" for="title">Název *</label>
                        <div class="col-lg-6">
                            <input type="text" name="title" id="title" class="form-control<?= isset($errors['title']) ? ' is-invalid' : '' ?>" value="<?= htmlspecialchars($camera['title'] ?? '') ?>" required>
                            <?php if (isset($errors['title'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errors['title']) ?></div>
                            <?php endif ?>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-3 col-form-label" for="description">Popis</label>
                        <div class="col-lg-6">
                            <textarea name="description" id="description" class="form-control" rows="3"><?= htmlspecialchars($camera['description'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-3 col-form-label" for="url_m3u8">URL M3U8</label>
                        <div class="col-lg-6">
                            <input type="text" name="url_m3u8" id="url_m3u8" class="form-control" value="<?= htmlspecialchars($camera['url_m3u8'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-3 col-form-label" for="url_mpd">URL MPD</label>
                        <div class="col-lg-6">
                            <input type="text" name="url_mpd" id="url_mpd" class="form-control" value="<?= htmlspecialchars($camera['url_mpd'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-group row">
                        <div class="col-lg-9 ml-auto">
                            <button type="submit" name="submit" class="btn btn-primary">Uložit</button>
                            <button type="submit" name="cancel" class="btn btn-default">Zrušit</button>
                        </div>
                    </div>
                </form>
            </div>
        </section>
    </div>
</div>
```

- [ ] **Step 4: Commit**

```bash
git add templates/camera/admin/add.phtml templates/camera/admin/edit.phtml
git commit -m "feat(camera): admin/add.phtml + admin/edit.phtml"
```

---

## Task 11: Admin šablony Setting

Obě Laminas šablony jsou prázdné (jen komentář header).

**Files:**
- Create: `templates/camera/admin/setting/index.phtml`
- Create: `templates/camera/admin/setting/setting.phtml`

- [ ] **Step 1: Vytvořit obě šablony jako prázdné (jen header komentář)**

`templates/camera/admin/setting/index.phtml`:
```php
<?php
/*
 * @project polar
 * @author Rostislav Greipel
 * @link https://rogr.cz
 * @copyright (c) 2011 - 2021 ROGR All Rights Reserved
 * @license https://rogr.cz/license/eula EULA License
 */
```

`templates/camera/admin/setting/setting.phtml`:
```php
<?php
/*
 * @project polar
 * @author Rostislav Greipel
 * @link https://rogr.cz
 * @copyright (c) 2011 - 2021 ROGR All Rights Reserved
 * @license https://rogr.cz/license/eula EULA License
 */
```

- [ ] **Step 2: Commit**

```bash
git add templates/camera/admin/setting/
git commit -m "feat(camera): admin/setting šablony (prázdné)"
```

---

## Task 12: Web controller — CameraController aktualizace

Odpovídá Laminas `WebListController` — akce `cameras` (seznam, stránkování) a `camera` (detail).

**Files:**
- Modify: `src/Camera/Controller/Web/CameraController.php`

- [ ] **Step 1: Aktualizovat CameraController**

Stránkování: Laminas `Paginator` zde nahradíme ručně — `getPaginator()` vrátí celé pole, controller si stránkování (slice) udělá sám.

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

namespace App\Camera\Controller\Web;

use App\Application\View\PhtmlRenderer;
use App\Camera\Repository\CameraRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class CameraController
{
    private int $itemsPerPage = 12;

    public function __construct(
        private CameraRepository $cameraRepository,
        private PhtmlRenderer $renderer,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function cameras(Request $request): Response
    {
        try {
            $page    = max(1, $request->query->getInt('stranka', 1));
            $allRows = $this->cameraRepository->getPaginator();

            $total      = $allRows ? count($allRows) : 0;
            $pageCount  = $total > 0 ? (int) ceil($total / $this->itemsPerPage) : 1;
            $cameras    = $allRows
                ? array_slice($allRows, ($page - 1) * $this->itemsPerPage, $this->itemsPerPage)
                : [];
        } catch (\Exception) {
            return new RedirectResponse($this->urlGenerator->generate('homepage'));
        }

        return new Response($this->renderer->renderWithLayout('camera/web/cameras', [
            'cameras'   => $cameras,
            'page'      => $page,
            'pageCount' => $pageCount,
            'total'     => $total,
        ]));
    }

    public function camera(Request $request, int $camera_id): Response
    {
        if (!$camera_id) {
            return new RedirectResponse($this->urlGenerator->generate('camera_list'));
        }

        try {
            $camera = $this->cameraRepository->findPostBy('id', $camera_id);
        } catch (\Exception) {
            return new RedirectResponse($this->urlGenerator->generate('camera_list'));
        }

        return new Response($this->renderer->renderWithLayout('camera/web/camera', [
            'camera' => $camera,
        ]));
    }
}
```

> **Poznámka:** Ověř, jak se web šablony renderují v ostatních modulech — jestli je `renderWithLayout` nebo jiná metoda. Viz `src/News/Controller/Web/NewsController.php`.

- [ ] **Step 2: Aktualizovat routy `camera_list` a `camera_detail` v `config/routes/camera.yaml`**

Změnit controller reference a přidat `methods`:
```yaml
camera_list:
  path: /kamery
  controller: App\Camera\Controller\Web\CameraController::cameras

camera_detail:
  path: /kamery/{camera_id}/{camera_url}
  controller: App\Camera\Controller\Web\CameraController::camera
  requirements:
    camera_id: '[0-9]+'
    camera_url: '[a-zA-Z][a-zA-Z0-9_-]+'
```

- [ ] **Step 3: Ověřit syntaxi**

```
php -l src/Camera/Controller/Web/CameraController.php
```

- [ ] **Step 4: Commit**

```bash
git add src/Camera/Controller/Web/CameraController.php config/routes/camera.yaml
git commit -m "feat(camera): WebController cameras + camera akce"
```

---

## Task 13: Web šablony cameras.phtml a camera.phtml

Odpovídají `polar/module/Camera/view/camera/web/web-list/cameras.phtml` a `camera.phtml`.

**Files:**
- Create: `templates/camera/web/cameras.phtml`
- Create: `templates/camera/web/camera.phtml`

- [ ] **Step 1: Přečíst celé obě Laminas šablony**

Přečti `polar/module/Camera/view/camera/web/web-list/cameras.phtml` a `camera.phtml` celé (já jsem načetl jen začátek, potřebuješ celý obsah).

- [ ] **Step 2: Vytvořit `templates/camera/web/cameras.phtml`**

Nahraď:
- `$this->navigation(...)` → `$view->include(...)` nebo vynechat (záleží na implementaci v projektu)
- `$this->url(...)` → `$view->path(...)`
- `$this->basePath(...)` → `$view->asset(...)`
- `$this->translate(...)` → hardcoded česky
- `$this->inlineScript()` → `$view->addScript()`
- stránkování přes Laminas Paginator → vlastní PHP logika s `$page`, `$pageCount`
- `$cameras` je teď pole (array), ne Paginator objekt — `foreach ($cameras as $camera)` funguje stejně

- [ ] **Step 3: Vytvořit `templates/camera/web/camera.phtml`**

Nahraď stejné helpery jako výše. `$camera` je teď pole (array), ne objekt — `$camera['title']` místo `$camera->getTitle()`.

- [ ] **Step 4: Commit**

```bash
git add templates/camera/web/cameras.phtml templates/camera/web/camera.phtml
git commit -m "feat(camera): web šablony cameras.phtml + camera.phtml"
```

---

## Task 14: Ověření — cache:clear + otevření stránek

- [ ] **Step 1: Vymazat cache**

```powershell
cd c:\web\www\polar-symfony
php bin/console cache:clear
```

Očekáváno: `Cache for the "dev" environment (debug=true) was successfully cleared.`

- [ ] **Step 2: Ověřit admin routy**

```powershell
php bin/console debug:router | Select-String "camera"
```

Očekáváno: všechny routy `admin_camera*` a `camera_list`, `camera_detail` jsou vidět.

- [ ] **Step 3: Otevřít v prohlížeči**

- `/admin/kamery` → index stránka s widgety
- `/admin/kamery/seznam` → bootstrap-table seznam
- `/kamery` → web seznam kamer

Pokud se zobrazí PHP chyba, oprav ji a znovu `cache:clear`.

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "feat(camera): kompletní migrace Camera modulu"
```
