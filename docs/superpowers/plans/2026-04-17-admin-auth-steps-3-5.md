# Admin + User CRUD Implementation Plan (Steps 3–5)

> **For agentic workers:** REQUIRED: Use the `subagent-driven-development` agent (recommended) or `executing-plans` agent to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ověřit bcrypt login, vytvořit admin layout a User CRUD (seznam, přidání, editace, smazání) — 1:1 kopie polaru.

**Architecture:** Admin layout jako samostatná šablona `templates/admin/layout.phtml` s fixním sidebarem. User CRUD controllery v `src/User/Controller/Admin/`. Repository pro DB dotazy. Bootstrap-table pro seznam uživatelů. JSON endpointy pro AJAX operace (getList, getUser, deleteUser, uploadImage).

**Tech Stack:** Symfony 8, PHP 8.5, Doctrine DBAL QueryBuilder, phtml templates, Bootstrap 4, Bootstrap-table, Imagine (image resize)

---

### Task 1: Ověřit bcrypt přihlášení

**Files:**
- Existující: `config/packages/security.yaml`
- Existující: `src/Security/UserProvider.php`
- Existující: `src/Authorization/Controller/SecurityController.php`

- [ ] **Step 1: Spustit dev server a otestovat login**

```bash
cd c:\web\www\polar-symfony
php -S localhost:8080 -t public
```

Otevřít `http://localhost:8080/prihlaseni` v prohlížeči.

- [ ] **Step 2: Ověřit, že formulář se zobrazuje**

Zkontrolovat, že se zobrazí login formulář s email + password polemi. Pokud je chyba v renderování, diagnostikovat a opravit.

- [ ] **Step 3: Přihlásit se existujícím účtem**

Použít existující účet z DB (tabulka `authorization`, sloupec `username` + `password` s bcrypt hashem). Po úspěšném přihlášení by měl redirect na `/admin` (zatím 404, to je OK).

- [ ] **Step 4: Ověřit chybovou hlášku při špatném hesle**

Zadat špatné heslo → mělo by přesměrovat zpět na `/prihlaseni` s hláškou "Neplatné přihlašovací údaje."

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: verify bcrypt login works with existing passwords"
```

---

### Task 2: Admin layout — základní šablona

**Files:**
- Create: `templates/admin/layout.phtml`
- Modify: `config/services.yaml` (přidat controller tag pro Admin + Authorization)
- Modify: `src/Application/View/PhtmlRenderer.php` (přidat `renderWithAdminLayout`)

Referenční soubor: `polar/module/Admin/view/layout/layout.phtml`

- [ ] **Step 1: Vytvořit admin layout šablonu**

Create `templates/admin/layout.phtml` — zjednodušená kopie polar admin layoutu. Bez Laminas navigation, s fixním menu. Sidebar bude mít hardcoded linky (Dashboard, Uživatelé). CSS/JS soubory stejné jako v polaru (vendor/admin/*).

```php
<?php
/*
 * @project polar
 * @author Rostislav Greipel
 * @link https://rogr.cz
 * @copyright (c) 2011 - 2021 ROGR All Rights Reserved
 * @license https://rogr.cz/license/eula EULA License
 */

$scheme = $scheme ?? 'dark';
$identity = $identity ?? null;
?>
<!DOCTYPE html>
<html lang="cs" class="fixed sidebar-left-collapsed <?= $scheme === 'dark' ? 'dark' : 'light sidebar-light' ?>">
<head>
    <meta charset="utf-8">
    <title><?= $view->getTitle() !== '' ? htmlspecialchars($view->getTitle()) . ' | ' : '' ?>Admin | POLAR</title>
    <meta name="author" content="Rostislav Greipel">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="application-name" content="POLAR televize Ostrava">
    <meta name="theme-color" content="#0f385a">
    <meta name="robots" content="noindex, nofollow">
    <meta name="googlebot" content="noindex, nofollow">

    <link rel="apple-touch-icon" sizes="180x180" href="<?= $view->asset('img/admin/icon/apple-touch-icon.png') ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= $view->asset('img/admin/icon/favicon-32x32.png') ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= $view->asset('img/admin/icon/favicon-16x16.png') ?>">
    <link rel="manifest" href="<?= $view->asset('img/admin/icon/site.webmanifest') ?>">
    <link rel="mask-icon" href="<?= $view->asset('img/admin/icon/safari-pinned-tab.svg') ?>" color="#0f385a">
    <link rel="shortcut icon" href="<?= $view->asset('img/admin/icon/favicon.ico') ?>">

    <link rel="stylesheet" href="<?= $view->asset('vendor/admin/font-google/css/font-google.min.css') ?>">
    <link rel="stylesheet" href="<?= $view->asset('vendor/admin/jquery-ui/jquery-ui.min.css') ?>">
    <link rel="stylesheet" href="<?= $view->asset('vendor/admin/jquery-ui/jquery-ui.theme.min.css') ?>">
    <link rel="stylesheet" href="<?= $view->asset('vendor/admin/bootstrap/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= $view->asset('vendor/admin/font-awesome/css/all.min.css') ?>">
    <link rel="stylesheet" href="<?= $view->asset('vendor/admin/animate/css/animate.min.css') ?>">
    <link rel="stylesheet" href="<?= $view->asset('vendor/admin/hover/css/hover.min.css') ?>">
    <link rel="stylesheet" href="<?= $view->asset('vendor/admin/pnotify/css/pnotify.custom.min.css') ?>">
    <link rel="stylesheet" href="<?= $view->asset('vendor/admin/magnific-popup/css/magnific-popup.min.css') ?>">
    <link rel="stylesheet" href="<?= $view->asset('vendor/admin/bootstrap-datepicker/css/bootstrap-datepicker3.min.css') ?>">
    <link rel="stylesheet" href="<?= $view->asset('vendor/porto-admin/css/theme.min.css') ?>">
    <link rel="stylesheet" href="<?= $view->asset('css/admin/skin/' . $scheme . '.min.css') ?>">
    <link rel="stylesheet" href="<?= $view->asset('css/admin/custom.min.css?ver=0.0.6') ?>">
    <?php foreach ($view->getHeadStyles() as $css) { ?>
        <style><?= $css ?></style>
    <?php } ?>
    <?php foreach ($view->getHeadLinks() as $link) { ?>
        <link rel="<?= $link['rel'] ?>" href="<?= $link['href'] ?>"<?= isset($link['media']) ? ' media="' . $link['media'] . '"' : '' ?>>
    <?php } ?>
    <script src="<?= $view->asset('vendor/admin/modernizr/js/modernizr.min.js') ?>"></script>
</head>
<body class="loading-overlay-showing" data-loading-overlay data-plugin-options="{'hideDelay': 150, 'effect': 'default'}">
<div class="loading-overlay">
    <div class="bounce-loader">
        <div class="bounce1"></div>
        <div class="bounce2"></div>
        <div class="bounce3"></div>
    </div>
</div>
<section class="body">
    <header class="header">
        <div class="logo-container">
            <a href="<?= $view->path('admin_dashboard') ?>" class="logo">
                <img alt="POLAR" src="<?= $view->asset('img/admin/logo-' . $scheme . '.svg') ?>" width="166" height="35"/>
            </a>
            <div class="d-md-none toggle-sidebar-left" data-toggle-class="sidebar-left-opened" data-target="html" data-fire-event="sidebar-left-opened">
                <i class="fa fa-bars" aria-label="Toggle sidebar"></i>
            </div>
        </div>
        <div class="header-right">
            <span class="separator"></span>
            <div id="userbox" class="userbox">
                <a href="#" data-toggle="dropdown">
                    <?php if ($identity) { ?>
                        <figure class="profile-picture">
                            <img src="<?= $view->asset($identity->getImage()) ?>" alt="<?= htmlspecialchars($identity->getFirstName() . ' ' . $identity->getLastName()) ?>" class="rounded-circle" data-lock-picture="<?= $view->asset($identity->getImage()) ?>">
                        </figure>
                        <div class="profile-info">
                            <span class="name"><?= htmlspecialchars($identity->getFirstName() . ' ' . $identity->getLastName()) ?></span>
                            <span class="role"><?= htmlspecialchars(implode(', ', $identity->getRole())) ?></span>
                        </div>
                    <?php } ?>
                    <i class="fa custom-caret"></i>
                </a>
                <div class="dropdown-menu">
                    <ul class="list-unstyled mb-2">
                        <li class="divider"></li>
                        <li>
                            <a role="menuitem" tabindex="-1" class="dropdown-item" href="<?= $view->path('app_logout') ?>"><i class="fa fa-power-off"></i> Odhlásit</a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </header>
    <div class="inner-wrapper">
        <aside id="sidebar-left" class="sidebar-left">
            <div class="sidebar-header">
                <div class="sidebar-title">
                    Navigace
                </div>
                <div class="sidebar-toggle d-none d-md-block" data-toggle-class="sidebar-left-collapsed" data-target="html" data-fire-event="sidebar-left-toggle">
                    <i class="fa fa-bars" aria-label="Toggle sidebar"></i>
                </div>
            </div>
            <div class="nano">
                <div class="nano-content">
                    <nav id="menu" class="nav-main" role="navigation">
                        <ul class="nav nav-main">
                            <li class="nav-item">
                                <a class="nav-link" href="<?= $view->path('admin_dashboard') ?>">
                                    <i class="fa fa-home" aria-hidden="true"></i>
                                    <span>Dashboard</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?= $view->path('admin_user_list') ?>">
                                    <i class="fa fa-users" aria-hidden="true"></i>
                                    <span>Uživatelé</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <hr class="separator"/>
                    <div class="text-center">
                        <a href="https://polar.cz" title="POLAR" target="_blank" class="logo d-inline-block">
                            <img alt="POLAR" height="10" src="<?= $view->asset('img/admin/logo-short-' . $scheme . '.svg') ?>" style="margin-top: -3px;">
                        </a>
                        <div class="d-inline-block">
                            <?= date("Y"); ?>
                        </div>
                    </div>
                </div>
            </div>
        </aside>
        <section role="main" class="content-body">
            <header class="page-header">
                <h2><?= $pageTitle ?? '' ?></h2>
            </header>
            <?= $content ?>
        </section>
    </div>
</section>
<script src="<?= $view->asset('vendor/admin/jquery/js/jquery.min.js') ?>"></script>
<script src="<?= $view->asset('vendor/admin/jquery-ui/jquery-ui.min.js') ?>"></script>
<script src="<?= $view->asset('vendor/admin/browser-mobile/js/browser-mobile.min.js') ?>"></script>
<script src="<?= $view->asset('vendor/admin/popper/js/popper.min.js') ?>"></script>
<script src="<?= $view->asset('vendor/admin/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= $view->asset('vendor/admin/bootstrap-datepicker/js/bootstrap-datepicker.min.js') ?>"></script>
<script src="<?= $view->asset('vendor/admin/nanoscroller/js/nanoscroller.min.js') ?>"></script>
<script src="<?= $view->asset('vendor/admin/magnific-popup/js/magnific-popup.min.js') ?>"></script>
<script src="<?= $view->asset('vendor/admin/pnotify/js/pnotify.custom.min.js') ?>"></script>
<script src="<?= $view->asset('vendor/admin/validation/js/validation.min.js') ?>"></script>
<script src="<?= $view->asset('vendor/admin/placeholder/js/placeholder.min.js') ?>"></script>
<script src="<?= $view->asset('vendor/admin/appear/js/appear.min.js') ?>"></script>
<script src="<?= $view->asset('vendor/porto-admin/js/theme.min.js') ?>"></script>
<?php foreach ($view->getInlineScripts() as $script) { ?>
    <script><?= $script ?></script>
<?php } ?>
<?php foreach ($view->getBodyScripts() as $src) { ?>
    <script src="<?= $src ?>"></script>
<?php } ?>
<script src="<?= $view->asset('js/admin/custom.min.js?ver=0.0.3') ?>"></script>
<script src="<?= $view->asset('vendor/porto-admin/js/theme.init.min.js') ?>"></script>
</body>
</html>
```

- [ ] **Step 2: Přidat `renderWithAdminLayout` do PhtmlRenderer**

Modify `src/Application/View/PhtmlRenderer.php` — přidat metodu `renderWithAdminLayout` která funguje jako `renderWithLayout` ale použije `admin/layout` a předá identitu:

```php
public function renderWithAdminLayout(string $template, array $params = [], string $layout = 'admin/layout'): string
{
    $sharedView = new ViewHelper($this, $this->urlGenerator, $this->basePath);
    $content = $this->render($template, $params, $sharedView);
    $layoutParams = ['content' => $content];
    foreach (['identity', 'scheme', 'pageTitle'] as $key) {
        if (isset($params[$key])) {
            $layoutParams[$key] = $params[$key];
        }
    }
    return $this->render($layout, $layoutParams, $sharedView);
}
```

- [ ] **Step 3: Commit**

```bash
git add templates/admin/layout.phtml src/Application/View/PhtmlRenderer.php
git commit -m "feat: add admin layout template"
```

---

### Task 3: Admin Dashboard controller + route

**Files:**
- Create: `src/Admin/Controller/AdminController.php`
- Create: `templates/admin/dashboard.phtml`
- Modify: `config/routes.yaml` (přidat admin routes)
- Modify: `config/services.yaml` (přidat Admin controller tag)

Referenční soubor: `polar/module/Admin/src/Controller/Admin/AdminListController.php` (metoda `dashboardAction`)

- [ ] **Step 1: Vytvořit admin routes**

Přidat do `config/routes.yaml`:

```yaml
# Admin
admin_dashboard:
  path: /admin/dashboard
  controller: App\Admin\Controller\AdminController::dashboard

admin_index:
  path: /admin
  controller: App\Admin\Controller\AdminController::index
```

- [ ] **Step 2: Registrovat Admin controller v services.yaml**

Přidat do `config/services.yaml`:

```yaml
App\Admin\Controller\:
    resource: '../src/Admin/Controller/'
    tags: ['controller.service_arguments']

App\Authorization\Controller\:
    resource: '../src/Authorization/Controller/'
    tags: ['controller.service_arguments']
```

- [ ] **Step 3: Vytvořit AdminController**

Create `src/Admin/Controller/AdminController.php`:

```php
<?php

namespace App\Admin\Controller;

use App\Application\View\PhtmlRenderer;
use App\Security\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;

final class AdminController
{
    public function index(Response $response): Response
    {
        // Polar: redirectuje na dashboard
        return new Response('', 302, ['Location' => '/admin/dashboard']);
    }

    public function dashboard(
        PhtmlRenderer $renderer,
        Security $security,
    ): Response
    {
        /** @var User $identity */
        $identity = $security->getUser();

        return new Response($renderer->renderWithAdminLayout('admin/dashboard', [
            'identity' => $identity,
            'pageTitle' => 'Dashboard',
        ]));
    }
}
```

- [ ] **Step 4: Vytvořit dashboard šablonu**

Create `templates/admin/dashboard.phtml` — zatím zjednodušený, bez widget counts (ty přidáme postupně jak budou repository):

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
    <div class="col-lg-12">
        <div class="alert alert-info">
            Dashboard — zatím prázdný. Widgety budou přidány postupně.
        </div>
    </div>
</div>
```

- [ ] **Step 5: Otestovat — přihlásit se a ověřit redirect na /admin/dashboard**

```bash
php -S localhost:8080 -t public
```

Přihlásit se → `/admin/dashboard` by měl zobrazit admin layout s dashboardem.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: add admin dashboard controller and template"
```

---

### Task 4: UserRepository pro admin

**Files:**
- Create: `src/User/Repository/UserRepository.php`
- Modify: `config/services.yaml` (přidat UserRepository pokud potřebuje explicitní config)

Referenční soubor: `polar/module/User/src/Model/User/UserRepository.php`

- [ ] **Step 1: Vytvořit UserRepository**

Create `src/User/Repository/UserRepository.php`:

```php
<?php

namespace App\User\Repository;

use Doctrine\DBAL\Connection;

final class UserRepository
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function getCount(bool $activeOnly = false): int
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from('user');

        if ($activeOnly) {
            $qb->innerJoin('user', 'authorization', 'a', 'user.authorization_id = a.id')
                ->andWhere('a.active = 1');
        }

        return (int) $qb->fetchOne();
    }

    public function findPostBy(string $column, int|string $value): ?array
    {
        return $this->connection->createQueryBuilder()
            ->select('*')
            ->from('user')
            ->where("$column = :value")
            ->setParameter('value', $value)
            ->fetchAssociative() ?: null;
    }

    /**
     * Pro bootstrap-table AJAX endpoint.
     * @param array $params Query parametry z bootstrap-table (sort, order, offset, limit, search)
     * @return array
     */
    public function fetchForBootstrapTable(array $params): array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select(
                'u.id',
                'u.authorization_id',
                'u.first_name',
                'u.last_name',
                'u.image',
                'u.created_date',
                'u.updated_date',
                'u.created_user',
                'u.updated_user',
                'a.username',
                'a.active'
            )
            ->from('user', 'u')
            ->innerJoin('u', 'authorization', 'a', 'u.authorization_id = a.id');

        // Search
        $search = $params['search'] ?? '';
        if ($search !== '') {
            $qb->andWhere(
                $qb->expr()->or(
                    $qb->expr()->like('a.username', ':search'),
                    $qb->expr()->like('u.first_name', ':search'),
                    $qb->expr()->like('u.last_name', ':search'),
                )
            )->setParameter('search', "%$search%");
        }

        // Sort
        $sort = $params['sort'] ?? 'username';
        $order = $params['order'] ?? 'asc';
        $allowedSort = ['id', 'username', 'active', 'first_name', 'last_name', 'created_date', 'updated_date', 'created_user', 'updated_user'];
        if (in_array($sort, $allowedSort, true)) {
            $sortColumn = match($sort) {
                'username' => 'a.username',
                'active' => 'a.active',
                default => 'u.' . $sort,
            };
            $qb->orderBy($sortColumn, $order === 'desc' ? 'DESC' : 'ASC');
        }

        // Pagination
        $offset = (int) ($params['offset'] ?? 0);
        $limit = (int) ($params['limit'] ?? 25);
        $qb->setFirstResult($offset)->setMaxResults($limit);

        $rows = $qb->fetchAllAssociative();

        // Přidat role ke každému řádku
        foreach ($rows as &$row) {
            $row['role'] = $this->getRolesForAuthorization((int) $row['authorization_id']);
        }

        return $rows;
    }

    public function getCountForBootstrapTable(array $params): int
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from('user', 'u')
            ->innerJoin('u', 'authorization', 'a', 'u.authorization_id = a.id');

        $search = $params['search'] ?? '';
        if ($search !== '') {
            $qb->andWhere(
                $qb->expr()->or(
                    $qb->expr()->like('a.username', ':search'),
                    $qb->expr()->like('u.first_name', ':search'),
                    $qb->expr()->like('u.last_name', ':search'),
                )
            )->setParameter('search', "%$search%");
        }

        return (int) $qb->fetchOne();
    }

    private function getRolesForAuthorization(int $authorizationId): array
    {
        return $this->connection->createQueryBuilder()
            ->select('r.role')
            ->from('authorization2role', 'ar')
            ->innerJoin('ar', 'authorization_role', 'r', 'ar.role_id = r.id')
            ->where('ar.authorization_id = :id')
            ->setParameter('id', $authorizationId)
            ->fetchFirstColumn();
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/User/Repository/UserRepository.php
git commit -m "feat: add UserRepository with bootstrap-table support"
```

---

### Task 5: AuthorizationRepository pro admin

**Files:**
- Create: `src/Authorization/Repository/AuthorizationRepository.php`

- [ ] **Step 1: Vytvořit AuthorizationRepository**

Create `src/Authorization/Repository/AuthorizationRepository.php`:

```php
<?php

namespace App\Authorization\Repository;

use Doctrine\DBAL\Connection;

final class AuthorizationRepository
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function findPostBy(string $column, int|string $value): ?array
    {
        return $this->connection->createQueryBuilder()
            ->select('*')
            ->from('authorization')
            ->where("$column = :value")
            ->setParameter('value', $value)
            ->fetchAssociative() ?: null;
    }

    public function insertPost(array $data): int
    {
        $this->connection->insert('authorization', [
            'username' => $data['username'],
            'password' => $data['password'],
            'active' => $data['active'] ? 1 : 0,
        ]);

        $authorizationId = (int) $this->connection->lastInsertId();

        // Vytvořit záznam v user tabulce
        $this->connection->insert('user', [
            'authorization_id' => $authorizationId,
        ]);

        // Přiřadit roli
        if (!empty($data['role'])) {
            $roleId = $this->connection->createQueryBuilder()
                ->select('id')
                ->from('authorization_role')
                ->where('role = :role')
                ->setParameter('role', $data['role'])
                ->fetchOne();

            if ($roleId) {
                $this->connection->insert('authorization2role', [
                    'authorization_id' => $authorizationId,
                    'role_id' => $roleId,
                ]);
            }
        }

        return $authorizationId;
    }

    public function updatePost(int $id, array $data): void
    {
        $update = [];
        if (isset($data['username'])) {
            $update['username'] = $data['username'];
        }
        if (isset($data['password']) && $data['password'] !== '') {
            $update['password'] = $data['password'];
        }
        if (isset($data['active'])) {
            $update['active'] = $data['active'] ? 1 : 0;
        }

        if (!empty($update)) {
            $this->connection->update('authorization', $update, ['id' => $id]);
        }

        // Aktualizovat roli
        if (isset($data['role'])) {
            $this->connection->delete('authorization2role', ['authorization_id' => $id]);

            $roleId = $this->connection->createQueryBuilder()
                ->select('id')
                ->from('authorization_role')
                ->where('role = :role')
                ->setParameter('role', $data['role'])
                ->fetchOne();

            if ($roleId) {
                $this->connection->insert('authorization2role', [
                    'authorization_id' => $id,
                    'role_id' => $roleId,
                ]);
            }
        }
    }

    public function deletePost(int $id): void
    {
        // Kaskáda: authorization2role → user → authorization
        $this->connection->delete('authorization2role', ['authorization_id' => $id]);

        $this->connection->delete('user', ['authorization_id' => $id]);

        $this->connection->delete('authorization', ['id' => $id]);
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Authorization/Repository/AuthorizationRepository.php
git commit -m "feat: add AuthorizationRepository for admin CRUD"
```

---

### Task 6: User admin routes

**Files:**
- Create: `config/routes/admin.yaml`
- Modify: `config/routes.yaml` (přidat import)

- [ ] **Step 1: Vytvořit admin routes soubor**

Create `config/routes/admin.yaml`:

```yaml
admin_index:
  path: /admin
  controller: App\Admin\Controller\AdminController::index

admin_dashboard:
  path: /admin/dashboard
  controller: App\Admin\Controller\AdminController::dashboard

# User
admin_user_index:
  path: /admin/users
  controller: App\User\Controller\Admin\UserListController::index

admin_user_list:
  path: /admin/users/list
  controller: App\User\Controller\Admin\UserListController::list

admin_user_get_list:
  path: /admin/users/json-list/get-list
  controller: App\User\Controller\Admin\UserListController::getList
  methods: [GET]

admin_user_get_user:
  path: /admin/users/json-list/get-user
  controller: App\User\Controller\Admin\UserListController::getUser
  methods: [POST]

admin_user_add:
  path: /admin/users/add
  controller: App\User\Controller\Admin\UserWriteController::add

admin_user_edit:
  path: /admin/users/edit/{id}
  controller: App\User\Controller\Admin\UserWriteController::edit
  requirements:
    id: '\d+'

admin_user_delete:
  path: /admin/users/json-write/delete-user
  controller: App\User\Controller\Admin\UserWriteController::deleteUser
  methods: [POST]

admin_user_upload_image:
  path: /admin/users/json-write/upload-image
  controller: App\User\Controller\Admin\UserWriteController::uploadImage
  methods: [POST]
```

- [ ] **Step 2: Importovat admin routes v routes.yaml**

Přidat na začátek `config/routes.yaml`:

```yaml
admin:
  resource: routes/admin.yaml
```

- [ ] **Step 3: Odstranit admin_dashboard a admin_index z routes.yaml**

Smazat routes `admin_dashboard` a `admin_index`, které jsou teď v `admin.yaml`.

- [ ] **Step 4: Commit**

```bash
git add config/routes/admin.yaml config/routes.yaml
git commit -m "feat: add admin routes (dashboard + user CRUD)"
```

---

### Task 7: UserListController (admin)

**Files:**
- Create: `src/User/Controller/Admin/UserListController.php`

Referenční soubor: `polar/module/User/src/Controller/User/UserListController.php`

- [ ] **Step 1: Vytvořit UserListController**

Create `src/User/Controller/Admin/UserListController.php`:

```php
<?php

namespace App\User\Controller\Admin;

use App\Application\View\PhtmlRenderer;
use App\Authorization\Repository\AuthorizationRepository;
use App\Security\User;
use App\User\Repository\UserRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class UserListController
{
    public function __construct(
        private UserRepository $userRepository,
        private AuthorizationRepository $authorizationRepository,
        private PhtmlRenderer $renderer,
        private Security $security,
    ) {}

    public function index(): Response
    {
        /** @var User $identity */
        $identity = $this->security->getUser();

        return new Response($this->renderer->renderWithAdminLayout('user/admin/index', [
            'identity' => $identity,
            'pageTitle' => 'Uživatelé',
            'countUsers' => $this->userRepository->getCount(),
            'countUsersActive' => $this->userRepository->getCount(true),
        ]));
    }

    public function list(): Response
    {
        /** @var User $identity */
        $identity = $this->security->getUser();

        return new Response($this->renderer->renderWithAdminLayout('user/admin/list', [
            'identity' => $identity,
            'pageTitle' => 'Uživatelé',
        ]));
    }

    public function getList(Request $request): JsonResponse
    {
        $params = $request->query->all();

        try {
            $rows = $this->userRepository->fetchForBootstrapTable($params);
            $total = $this->userRepository->getCountForBootstrapTable($params);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => $e->getMessage(),
                'rows' => null,
                'total' => 0,
            ]);
        }

        return new JsonResponse([
            'success' => true,
            'rows' => $rows,
            'total' => $total,
        ]);
    }

    public function getUser(Request $request): JsonResponse
    {
        try {
            $userId = $request->request->getInt('id');
            $user = $this->userRepository->findPostBy('id', $userId);
            $authorization = $this->authorizationRepository->findPostBy('id', $user['authorization_id']);

            $user['username'] = $authorization['username'];

            return new JsonResponse([
                'success' => true,
                'message' => null,
                'user' => $user,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
                'user' => null,
            ]);
        }
    }
}
```

- [ ] **Step 2: Registrovat User controller v services.yaml**

Přidat do `config/services.yaml`:

```yaml
App\User\Controller\:
    resource: '../src/User/Controller/'
    tags: ['controller.service_arguments']
```

- [ ] **Step 3: Commit**

```bash
git add src/User/Controller/Admin/UserListController.php config/services.yaml
git commit -m "feat: add UserListController for admin"
```

---

### Task 8: User admin šablony — index + list

**Files:**
- Create: `templates/user/admin/index.phtml`
- Create: `templates/user/admin/list.phtml`
- Create: `templates/admin/modal/danger.phtml`

Referenční soubory: `polar/module/User/view/user/user/user-list/index.phtml`, `polar/module/User/view/user/user/user-list/list.phtml`

- [ ] **Step 1: Vytvořit user index šablonu**

Create `templates/user/admin/index.phtml` — přesměrování na list (polar to dělá stejně):

```php
<?php
// V polaru indexAction jen zobrazuje info o počtech.
// Redirect na list se provádí v controlleru.
?>
<div class="row">
    <div class="col-xl-6">
        <section class="card">
            <header class="card-header">
                <h2 class="card-title">Uživatelé</h2>
            </header>
            <div class="card-body">
                <div class="row">
                    <div class="col">
                        <strong><?= $countUsers ?></strong> celkem
                    </div>
                    <div class="col">
                        <strong><?= $countUsersActive ?></strong> aktivních
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>
```

- [ ] **Step 2: Vytvořit user list šablonu**

Create `templates/user/admin/list.phtml` — kopie polar list.phtml přepsaná na Symfony styl (bez Laminas helperů, URL přes `$view->path()`):

```php
<?php
/*
 * @project polar
 * @author Rostislav Greipel
 * @link https://rogr.cz
 * @copyright (c) 2011 - 2021 ROGR All Rights Reserved
 * @license https://rogr.cz/license/eula EULA License
 */

$view->addHeadLink('stylesheet', $view->asset('vendor/admin/bootstrap-table/css/bootstrap-table.min.css'));
$view->addBodyScript($view->asset('vendor/admin/bootstrap-table/js/bootstrap-table.min.js'));
$view->addBodyScript($view->asset('vendor/admin/bootstrap-table/js/locale/cs_CZ.min.js'));
$view->addBodyScript($view->asset('vendor/admin/bootstrap-table/extension/cookie/js/cookie.min.js'));
$view->addBodyScript($view->asset('vendor/admin/bootstrap-table/extension/mobile/js/mobile.min.js'));
$view->addBodyScript($view->asset('vendor/admin/moment/js/moment.min.js'));
$view->addBodyScript($view->asset('vendor/admin/moment/js/locale/cs_CZ.min.js'));

$view->addInlineScript(
    '$("#btblUser").on("post-body.bs.table", function (e, data) {
        // Zobrazení náhledu avatara
        $(".image-popup-no-margins").magnificPopup({
            type: "image",
            image: {
                verticalFit: true
            },
            zoom: {
                enabled: true,
                duration: 300
            }
        });
        // Smazání uživatele
        $(".btblBtnDeleteUser").unbind("click").bind("click", function() {
            var id = $(this).data("user-id");
            $.post("' . $view->path('admin_user_get_user') . '",
                {
                    id: id
                },
                function(json) {
                    if (json.success) {
                        if (json.user.id !== "' . $identity->getId() . '") {
                            $("#modalDanger .card-title").html("Smazat uživatele");
                            $("#modalDanger .modal-text h4").html("Opravdu chcete smazat uživatele?<br><strong>\"" + json.user.username + "\"</strong> <small>(" + json.user.first_name + " " + json.user.last_name + ")</small>" );
                            $("#modalDanger .modal-text p").html("Všechny závislé záznamy budou smazány. Tuto akci nelze vrátit!");
                            var btnConfirm = $("#modalDanger .card-footer button.modal-confirm");

                            $.magnificPopup.open({
                                items: {src: "#modalDanger"},
                                type: "inline",
                                fixedContentPos: false,
                                fixedBgPos: true,
                                overflowY: "auto",
                                closeBtnInside: true,
                                preloader: false,
                                midClick: true,
                                removalDelay: 300,
                                mainClass: "my-mfp-slide-bottom",
                                modal: true,
                                callbacks: {
                                    open: function() {
                                        btnConfirm.unbind("click").click(function(e){
                                            e.preventDefault();
                                            $.post("' . $view->path('admin_user_delete') . '",
                                                {
                                                    id: id
                                                },
                                                function(json) {
                                                    if (json.success) {
                                                        var notice = new PNotify({
                                                            title: "Úspěch",
                                                            text: "Uživatel byl smazán!",
                                                            type: "success",
                                                            addclass: "click-2-close",
                                                            buttons: {
                                                                sticker: false
                                                            }
                                                        });
                                                        $("#btblUser").bootstrapTable("refresh", {"silent": true});
                                                    }else{
                                                        var notice = new PNotify({
                                                            title: "Chyba",
                                                            text: "Nelze smazat uživatele!",
                                                            type: "error",
                                                            addclass: "click-2-close",
                                                            buttons: {
                                                                sticker: false
                                                            }
                                                        });
                                                    }
                                                    $.magnificPopup.close();
                                                },
                                                "json"
                                            );
                                        });
                                    },
                                    close: function() {

                                    }
                                }
                            });
                        }else{
                            var notice = new PNotify({
                                title: "Chyba",
                                text: "Jste přihlášen pod tímto uživatelem!",
                                type: "error",
                                addclass: "click-2-close",
                                buttons: {
                                    sticker: false
                                }
                            });
                            $.magnificPopup.close();
                        }
                    } else {
                        var notice = new PNotify({
                            title: "Chyba",
                            text: "Nelze načíst informace o uživateli!",
                            type: "error",
                            addclass: "click-2-close",
                            buttons: {
                                sticker: false
                            }
                        });
                        $.magnificPopup.close();
                        $("#btblUser").bootstrapTable("refresh", {"silent": true});
                    }
                },
                "json"
            );
        });
    });'
);
?>
    <div id="toolbar" class="btn-group">
        <a href="<?= $view->path('admin_user_add') ?>" class="btn btn-primary">
            <i class="fa fa-fw fa-plus"></i>
            <span class="hidden-sm hidden-xs">
                Přidat uživatele
            </span>
        </a>
    </div>
    <table id="btblUser"
           data-url="<?= $view->path('admin_user_get_list') ?>"
           data-toggle="table"
           data-show-search-button="true"
           data-show-search-clear-button="true"
           data-valign="middle"
           data-sort-name="username"
           data-sort-order="asc"
           data-toolbar="#toolbar"
           data-cookie-id-table="USER-<?= $identity->getId() ?>-userTable"
           data-mobile-responsive="true">
        <thead>
        <tr>
            <th class="text-nowrap width-nowrap"
                data-formatter="optionsFormatter"
                data-align="center">
                <i class="fa fa-cogs" data-toggle="tooltip" data-placement="right" title="Možnosti"></i>
                <span class="d-sm-none">Možnosti</span>
            </th>
            <th data-field="id"
                class="text-nowrap width-nowrap"
                data-sortable="true"
                data-visible="false">
                ID
            </th>
            <th data-field="active"
                class="text-nowrap width-nowrap"
                data-formatter="activeFormatter"
                data-sortable="true"
                data-align="center">
                <i class="fa fa-toggle-on pb-sm-0 pb-2" data-toggle="tooltip" data-placement="right" title="Aktivní"></i>
                <span class="d-sm-none">Aktivní</span>
            </th>
            <th data-field="image"
                class="width-nowrap p-0"
                data-formatter="imageFormatter"
                data-align="center">
                <i class="fa fa-image" data-toggle="tooltip" data-placement="right" title="Obrázek"></i>
                <span class="d-sm-none">Obrázek</span>
            </th>
            <th data-field="username"
                class="text-truncate"
                data-sortable="true">
                Uživatel
            </th>
            <th data-field="role"
                class="text-nowrap width-nowrap line-height-xs"
                data-formatter="roleFormatter"
                data-sortable="true">
                Role
            </th>
            <th data-field="first_name"
                class="text-nowrap width-nowrap"
                data-sortable="true">
                Jméno
            </th>
            <th data-field="last_name"
                class="text-nowrap width-nowrap"
                data-sortable="true">
                Příjmení
            </th>
            <th data-field="created_date"
                class="text-nowrap width-nowrap"
                data-formatter="datetimeFormatter"
                data-sortable="true"
                data-visible="false">
                Datum vytvoření
            </th>
            <th data-field="updated_date"
                class="text-nowrap width-nowrap"
                data-formatter="datetimeFormatter"
                data-sortable="true"
                data-visible="false">
                Datum úpravy
            </th>
            <th data-field="created_user"
                class="text-nowrap width-nowrap"
                data-sortable="true"
                data-visible="false">
                Vytvořil
            </th>
            <th data-field="updated_user"
                class="text-nowrap width-nowrap"
                data-sortable="true"
                data-visible="false">
                Upravil
            </th>
        </tr>
        </thead>
    </table>

    <script>
        function optionsFormatter(value, row) {
            let data = '<div class="btn-group pb-sm-0 pb-2">';
            if (row.role.indexOf("owner") === -1 || "<?= in_array('owner', $identity->getRole(), true) ? 'true' : 'false' ?>" === "true") {
                data += '<a href="<?= $view->path('admin_user_edit', ['id' => '']) ?>' + row.id + '" class="btn btn-sm btn-success"><i class="fa fa-edit"></i> <span class="d-none">Upravit</span></a> ' +
                    '<button data-user-id="' + row.id + '" class="btblBtnDeleteUser btn btn-sm btn-danger"><i class="fa fa-trash-alt"></i> <span class="d-none">Smazat</span></button>';
            }
            data += '</div>';
            return data;
        }

        function roleFormatter(value) {
            let role = '';
            value.forEach(function (item) {
                switch (item) {
                    case 'owner':
                        role += 'vlastník';
                        break;
                    case 'admin':
                        role += 'administrátor';
                        break;
                    case 'member':
                        role += 'člen';
                        break;
                }
                role += '<br>';
            });
            return role;
        }

        function activeFormatter(value) {
            let icon = 'fa fa-fw fa-lg';
            let color = 'text-success';
            let text = '';
            switch (value) {
                case '1':
                    icon = 'fa fa-fw fa-lg fa-check-circle';
                    color = 'text-success';
                    text = 'Aktivní';
                    break;
                case '0':
                    icon = 'fa fa-fw fa-lg fa-times-circle';
                    color = 'text-danger';
                    text = 'Neaktivní';
                    break;
            }
            return '<i class="' + icon + ' ' + color + '" data-toggle="tooltip" data-placement="right" title="' + text + '"></i>';
        }

        function imageFormatter(value) {
            if (value) {
                return '<a class="image-popup-no-margins cur-zoom-in" href="/' + value + '">' +
                    '<img src="/' + value + '" height="40" alt="" />' +
                    '</a>';
            }
            return null;
        }

        function datetimeFormatter(value) {
            if (value) {
                return moment(value).format('LLLL');
            }
            return null;
        }
    </script>

<?= $view->include('admin/modal/danger') ?>
```

- [ ] **Step 3: Vytvořit danger modal šablonu**

Create `templates/admin/modal/danger.phtml`:

```php
<div id="modalDanger" class="modal-block modal-block-danger mfp-hide">
    <section class="card">
        <header class="card-header">
            <h2 class="card-title"></h2>
        </header>
        <div class="card-body">
            <div class="modal-wrapper">
                <div class="modal-icon">
                    <i class="fa fa-times-circle"></i>
                </div>
                <div class="modal-text">
                    <h4></h4>
                    <p></p>
                </div>
            </div>
        </div>
        <footer class="card-footer">
            <div class="row">
                <div class="col-md-12 text-right">
                    <button class="btn btn-danger modal-confirm">Potvrdit</button>
                    <button class="btn btn-default modal-dismiss" onclick="$.magnificPopup.close();">Zrušit</button>
                </div>
            </div>
        </footer>
    </section>
</div>
```

- [ ] **Step 4: Commit**

```bash
git add templates/user/admin/ templates/admin/modal/
git commit -m "feat: add user list + index admin templates"
```

---

### Task 9: UserWriteController (admin) — add + edit

**Files:**
- Create: `src/User/Controller/Admin/UserWriteController.php`

Referenční soubor: `polar/module/User/src/Controller/User/UserWriteController.php`

- [ ] **Step 1: Vytvořit UserWriteController**

Create `src/User/Controller/Admin/UserWriteController.php`:

```php
<?php

namespace App\User\Controller\Admin;

use App\Application\View\PhtmlRenderer;
use App\Authorization\Repository\AuthorizationRepository;
use App\Security\User;
use App\User\Repository\UserRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class UserWriteController
{
    public function __construct(
        private UserRepository $userRepository,
        private AuthorizationRepository $authorizationRepository,
        private PhtmlRenderer $renderer,
        private Security $security,
        private UserPasswordHasherInterface $passwordHasher,
        private UrlGeneratorInterface $urlGenerator,
        private string $PUBLIC_PATH,
    ) {}

    public function add(Request $request): Response
    {
        /** @var User $identity */
        $identity = $this->security->getUser();

        if ($request->isMethod('POST')) {
            $post = $request->request->all();

            if (isset($post['cancel'])) {
                return new RedirectResponse($this->urlGenerator->generate('admin_user_list'));
            }

            // Validace
            $errors = $this->validateForm($post, true);
            if (!empty($errors)) {
                return new Response($this->renderer->renderWithAdminLayout('user/admin/add', [
                    'identity' => $identity,
                    'pageTitle' => 'Uživatelé',
                    'post' => $post,
                    'errors' => $errors,
                ]));
            }

            try {
                $password = password_hash($post['password'], PASSWORD_BCRYPT);

                $authorizationId = $this->authorizationRepository->insertPost([
                    'username' => $post['username'],
                    'password' => $password,
                    'active' => !empty($post['active']),
                    'role' => $post['role'] ?? '',
                ]);

                $user = $this->userRepository->findPostBy('authorization_id', $authorizationId);
                if ($user) {
                    $image = $post['image'] ?? 'data/user/!default-user.png';

                    if (str_contains($image, '/tmp/')) {
                        $folder = 'data/user/' . $user['id'] . '/';
                        if (!is_dir($this->PUBLIC_PATH . '/' . $folder)) {
                            mkdir($this->PUBLIC_PATH . '/' . $folder, 0777, true);
                        }
                        $newImage = $folder . substr($image, strrpos($image, '/') + 1);
                        rename($this->PUBLIC_PATH . '/' . $image, $this->PUBLIC_PATH . '/' . $newImage);
                        $image = $newImage;
                    }

                    $this->userRepository->updatePost($user['id'], [
                        'first_name' => $post['first_name'] ?? '',
                        'last_name' => $post['last_name'] ?? '',
                        'image' => $image,
                        'created_user' => $identity->getUserIdentifier(),
                        'updated_user' => $identity->getUserIdentifier(),
                    ]);
                }

                return new RedirectResponse($this->urlGenerator->generate('admin_user_list'));
            } catch (\Exception $e) {
                return new Response($this->renderer->renderWithAdminLayout('user/admin/add', [
                    'identity' => $identity,
                    'pageTitle' => 'Uživatelé',
                    'post' => $post,
                    'errors' => ['general' => $e->getMessage()],
                ]));
            }
        }

        return new Response($this->renderer->renderWithAdminLayout('user/admin/add', [
            'identity' => $identity,
            'pageTitle' => 'Uživatelé',
            'post' => [],
            'errors' => [],
        ]));
    }

    public function edit(Request $request, int $id): Response
    {
        /** @var User $identity */
        $identity = $this->security->getUser();

        $user = $this->userRepository->findPostBy('id', $id);
        if (!$user) {
            return new RedirectResponse($this->urlGenerator->generate('admin_user_list'));
        }

        $authorization = $this->authorizationRepository->findPostBy('id', $user['authorization_id']);
        if (!$authorization) {
            return new RedirectResponse($this->urlGenerator->generate('admin_user_list'));
        }

        // Načíst role
        $roles = $this->userRepository->getRolesForUser((int) $user['authorization_id']);
        $currentRole = $roles[0] ?? '';

        if ($request->isMethod('POST')) {
            $post = $request->request->all();

            if (isset($post['cancel'])) {
                return new RedirectResponse($this->urlGenerator->generate('admin_user_list'));
            }

            $errors = $this->validateForm($post, false);
            if (!empty($errors)) {
                return new Response($this->renderer->renderWithAdminLayout('user/admin/edit', [
                    'identity' => $identity,
                    'pageTitle' => 'Uživatelé',
                    'id' => $id,
                    'post' => array_merge($user, $authorization, $post),
                    'currentRole' => $post['role'] ?? $currentRole,
                    'errors' => $errors,
                ]));
            }

            try {
                $updateAuth = [
                    'username' => $post['username'],
                    'active' => !empty($post['active']),
                    'role' => $post['role'] ?? '',
                ];

                if (!empty($post['password'])) {
                    $updateAuth['password'] = password_hash($post['password'], PASSWORD_BCRYPT);
                }

                $this->authorizationRepository->updatePost((int) $authorization['id'], $updateAuth);

                $image = $post['image'] ?? $user['image'];

                if (str_contains($image, '/tmp/')) {
                    $folder = 'data/user/' . $user['id'] . '/';
                    if (!is_dir($this->PUBLIC_PATH . '/' . $folder)) {
                        mkdir($this->PUBLIC_PATH . '/' . $folder, 0777, true);
                    }
                    $newImage = $folder . substr($image, strrpos($image, '/') + 1);
                    rename($this->PUBLIC_PATH . '/' . $image, $this->PUBLIC_PATH . '/' . $newImage);
                    $image = $newImage;
                }

                $this->userRepository->updatePost($id, [
                    'first_name' => $post['first_name'] ?? '',
                    'last_name' => $post['last_name'] ?? '',
                    'image' => $image,
                    'updated_user' => $identity->getUserIdentifier(),
                ]);

                return new RedirectResponse($this->urlGenerator->generate('admin_user_list'));
            } catch (\Exception $e) {
                return new Response($this->renderer->renderWithAdminLayout('user/admin/edit', [
                    'identity' => $identity,
                    'pageTitle' => 'Uživatelé',
                    'id' => $id,
                    'post' => array_merge($user, $authorization, $post),
                    'currentRole' => $post['role'] ?? $currentRole,
                    'errors' => ['general' => $e->getMessage()],
                ]));
            }
        }

        $post = array_merge($user, $authorization);

        return new Response($this->renderer->renderWithAdminLayout('user/admin/edit', [
            'identity' => $identity,
            'pageTitle' => 'Uživatelé',
            'id' => $id,
            'post' => $post,
            'currentRole' => $currentRole,
            'errors' => [],
        ]));
    }

    public function deleteUser(Request $request): JsonResponse
    {
        try {
            $userId = $request->request->getInt('id');
            $user = $this->userRepository->findPostBy('id', $userId);

            if ($user) {
                // Smazat adresář s obrázky
                $dir = $this->PUBLIC_PATH . '/data/user/' . $user['id'] . '/';
                if (is_dir($dir)) {
                    $this->deleteDir($dir);
                }

                $this->authorizationRepository->deletePost((int) $user['authorization_id']);

                return new JsonResponse([
                    'success' => true,
                    'message' => null,
                    'user_id' => $userId,
                ]);
            }

            return new JsonResponse([
                'success' => false,
                'message' => 'Uživatel nenalezen',
                'user_id' => $userId,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
                'user_id' => null,
            ]);
        }
    }

    public function uploadImage(Request $request): JsonResponse
    {
        $file = $request->files->get('file');

        if (!$file) {
            return new JsonResponse(['error' => 'Soubor nenalezen']);
        }

        $userId = $request->request->get('user_id');

        $folder = 'data/user/';
        if ($userId && $userId !== 'null') {
            $folder .= $userId . '/';
        } else {
            $folder .= 'tmp/';
        }

        if (!is_dir($this->PUBLIC_PATH . '/' . $folder)) {
            mkdir($this->PUBLIC_PATH . '/' . $folder, 0777, true);
        }

        $ext = $file->guessExtension() ?: 'jpg';
        $filename = 'avatar-' . date('YmdHis') . '_' . random_int(100, 999) . '.' . $ext;
        $file->move($this->PUBLIC_PATH . '/' . $folder, $filename);
        $imageFileName = $folder . $filename;

        // Resize na 300x300 pokud potřeba (zjednodušeno oproti polaru — Imagine přidáme později pokud bude potřeba)

        if ($userId && $userId !== 'null') {
            $user = $this->userRepository->findPostBy('id', (int) $userId);
            if ($user && $user['image'] !== 'data/user/!default-user.png') {
                $oldImage = $this->PUBLIC_PATH . '/' . $user['image'];
                if (file_exists($oldImage)) {
                    unlink($oldImage);
                }
            }
            if ($user) {
                $this->userRepository->updatePost((int) $user['id'], ['image' => $imageFileName]);
            }
        }

        return new JsonResponse([
            'name' => $file->getClientOriginalName(),
            'url' => $imageFileName,
            'type' => $file->getClientMimeType(),
        ]);
    }

    private function validateForm(array $post, bool $isNew): array
    {
        $errors = [];

        if (empty($post['username'])) {
            $errors['username'] = 'Email je povinný';
        }
        if ($isNew && empty($post['password'])) {
            $errors['password'] = 'Heslo je povinné';
        }
        if (!empty($post['password']) && !empty($post['password2']) && $post['password'] !== $post['password2']) {
            $errors['password2'] = 'Hesla se neshodují';
        }
        if (empty($post['role'])) {
            $errors['role'] = 'Role je povinná';
        }

        return $errors;
    }

    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = array_diff(scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->deleteDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
```

- [ ] **Step 2: Přidat `updatePost` a `getRolesForUser` do UserRepository**

Přidat do `src/User/Repository/UserRepository.php`:

```php
public function updatePost(int $id, array $data): void
{
    $this->connection->update('user', $data, ['id' => $id]);
}

public function getRolesForUser(int $authorizationId): array
{
    return $this->getRolesForAuthorization($authorizationId);
}
```

A změnit `getRolesForAuthorization` z `private` na `public` (nebo nechat private a přidat public wrapper `getRolesForUser`).

- [ ] **Step 3: Commit**

```bash
git add src/User/Controller/Admin/UserWriteController.php src/User/Repository/UserRepository.php
git commit -m "feat: add UserWriteController (add, edit, delete, upload)"
```

---

### Task 10: User admin šablony — add + edit + form

**Files:**
- Create: `templates/user/admin/add.phtml`
- Create: `templates/user/admin/edit.phtml`
- Create: `templates/user/admin/userForm.phtml`

Referenční soubory: `polar/module/User/view/user/user/user-write/add.phtml`, `edit.phtml`, `userForm.php`

- [ ] **Step 1: Vytvořit userForm šablonu**

Create `templates/user/admin/userForm.phtml` — společný formulář pro add i edit:

```php
<?php
/*
 * @project polar
 * @author Rostislav Greipel
 * @link https://rogr.cz
 * @copyright (c) 2011 - 2021 ROGR All Rights Reserved
 * @license https://rogr.cz/license/eula EULA License
 */

$post = $post ?? [];
$errors = $errors ?? [];
$isEdit = $isEdit ?? false;
$currentRole = $currentRole ?? '';

$roleOptions = [
    '' => '-- Vyberte --',
    'owner' => 'Vlastník',
    'admin' => 'Administrátor',
    'member' => 'Člen',
];

// Omezení role — ne-owner nevidí roli owner
if (!in_array('owner', $identity->getRole(), true)) {
    unset($roleOptions['owner']);
}
?>

<form action="" method="post" class="needs-validation" novalidate>
    <?php if (!empty($errors['general'])) { ?>
        <div class="alert alert-danger"><?= htmlspecialchars($errors['general']) ?></div>
    <?php } ?>

    <div class="form-group row">
        <label class="col-sm-3 col-form-label required" for="username">Email</label>
        <div class="col-sm-9">
            <input type="email" name="username" id="username" class="form-control<?= isset($errors['username']) ? ' is-invalid' : '' ?>" required maxlength="50" value="<?= htmlspecialchars($post['username'] ?? '') ?>">
            <?php if (isset($errors['username'])) { ?>
                <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['username']) ?></div>
            <?php } ?>
        </div>
    </div>

    <div class="form-group row">
        <label class="col-sm-3 col-form-label<?= !$isEdit ? ' required' : '' ?>" for="password">Heslo</label>
        <div class="col-sm-9">
            <input type="password" name="password" id="password" class="form-control<?= isset($errors['password']) ? ' is-invalid' : '' ?>" <?= !$isEdit ? 'required' : '' ?> maxlength="50" placeholder="<?= $isEdit ? 'ponechte prázdné pro zachování' : 'povinné' ?>">
            <?php if (isset($errors['password'])) { ?>
                <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['password']) ?></div>
            <?php } ?>
        </div>
    </div>

    <div class="form-group row">
        <label class="col-sm-3 col-form-label<?= !$isEdit ? ' required' : '' ?>" for="password2">Heslo znovu</label>
        <div class="col-sm-9">
            <input type="password" name="password2" id="password2" class="form-control<?= isset($errors['password2']) ? ' is-invalid' : '' ?>" <?= !$isEdit ? 'required' : '' ?> maxlength="50">
            <?php if (isset($errors['password2'])) { ?>
                <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['password2']) ?></div>
            <?php } ?>
        </div>
    </div>

    <div class="form-group row">
        <label class="col-sm-3 col-form-label required" for="role">Role</label>
        <div class="col-sm-9">
            <select name="role" id="role" class="form-control<?= isset($errors['role']) ? ' is-invalid' : '' ?>" required>
                <?php foreach ($roleOptions as $value => $label) { ?>
                    <option value="<?= htmlspecialchars($value) ?>"<?= ($post['role'] ?? $currentRole) === $value ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                <?php } ?>
            </select>
            <?php if (isset($errors['role'])) { ?>
                <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['role']) ?></div>
            <?php } ?>
        </div>
    </div>

    <div class="form-group row">
        <label class="col-sm-3 col-form-label" for="active">Aktivní</label>
        <div class="col-sm-9">
            <div class="custom-control custom-checkbox">
                <input type="checkbox" name="active" id="active" class="custom-control-input" value="1"<?= !empty($post['active']) || (!$isEdit && !isset($post['active'])) ? ' checked' : '' ?>>
                <label class="custom-control-label" for="active">Ano</label>
            </div>
        </div>
    </div>

    <hr>

    <div class="form-group row">
        <label class="col-sm-3 col-form-label" for="first_name">Jméno</label>
        <div class="col-sm-9">
            <input type="text" name="first_name" id="first_name" class="form-control" maxlength="50" value="<?= htmlspecialchars($post['first_name'] ?? '') ?>">
        </div>
    </div>

    <div class="form-group row">
        <label class="col-sm-3 col-form-label" for="last_name">Příjmení</label>
        <div class="col-sm-9">
            <input type="text" name="last_name" id="last_name" class="form-control" maxlength="50" value="<?= htmlspecialchars($post['last_name'] ?? '') ?>">
        </div>
    </div>

    <input type="hidden" name="image" id="image" value="<?= htmlspecialchars($post['image'] ?? 'data/user/!default-user.png') ?>">

    <div class="row">
        <div class="col-sm-9 offset-sm-3">
            <button type="submit" name="submit" class="btn btn-primary">
                <i class="fa fa-save"></i> <?= $isEdit ? 'Uložit' : 'Vytvořit' ?>
            </button>
            <button type="submit" name="cancel" class="btn btn-default">
                Zrušit
            </button>
        </div>
    </div>
</form>
```

- [ ] **Step 2: Vytvořit add šablonu**

Create `templates/user/admin/add.phtml`:

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
        <section class="card">
            <header class="card-header">
                <h2 class="card-title">Přidat uživatele</h2>
            </header>
            <div class="card-body">
                <?= $view->include('user/admin/userForm', [
                    'post' => $post,
                    'errors' => $errors,
                    'identity' => $identity,
                    'isEdit' => false,
                ]) ?>
            </div>
        </section>
    </div>
    <div class="col-lg-6">
        <section class="card">
            <header class="card-header">
                <h2 class="card-title">Obrázek</h2>
            </header>
            <div class="card-body text-center">
                <img id="avatarPreview" src="/<?= htmlspecialchars($post['image'] ?? 'data/user/!default-user.png') ?>" class="rounded-circle" width="200" height="200" alt="">
            </div>
        </section>
    </div>
</div>
```

- [ ] **Step 3: Vytvořit edit šablonu**

Create `templates/user/admin/edit.phtml`:

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
        <section class="card">
            <header class="card-header">
                <h2 class="card-title">Upravit uživatele</h2>
            </header>
            <div class="card-body">
                <?= $view->include('user/admin/userForm', [
                    'post' => $post,
                    'errors' => $errors,
                    'identity' => $identity,
                    'isEdit' => true,
                    'currentRole' => $currentRole,
                ]) ?>
            </div>
        </section>
    </div>
    <div class="col-lg-6">
        <section class="card">
            <header class="card-header">
                <h2 class="card-title">Obrázek</h2>
            </header>
            <div class="card-body text-center">
                <img id="avatarPreview" src="/<?= htmlspecialchars($post['image'] ?? 'data/user/!default-user.png') ?>" class="rounded-circle" width="200" height="200" alt="">
            </div>
        </section>
    </div>
</div>
```

- [ ] **Step 4: Commit**

```bash
git add templates/user/admin/
git commit -m "feat: add user admin templates (add, edit, form)"
```

---

### Task 11: Smoke test celého admin flow

**Files:**
- Žádné nové soubory

- [ ] **Step 1: Vyčistit cache**

```bash
cd c:\web\www\polar-symfony
php bin/console cache:clear
```

- [ ] **Step 2: Ověřit routes**

```bash
php bin/console debug:router | Select-String "admin"
```

Očekávaný výstup: admin_index, admin_dashboard, admin_user_index, admin_user_list, admin_user_get_list, admin_user_get_user, admin_user_add, admin_user_edit, admin_user_delete, admin_user_upload_image

- [ ] **Step 3: Spustit dev server a otestovat login → dashboard → user list**

```bash
php -S localhost:8080 -t public
```

1. `/prihlaseni` → přihlásit se
2. Redirect na `/admin/dashboard` → admin layout s dashboardem
3. Klik na "Uživatelé" v sidebaru → `/admin/users/list` → bootstrap-table se načte
4. Klik na "Přidat uživatele" → formulář
5. Klik na "Upravit" u existujícího uživatele → edit formulář

- [ ] **Step 4: Opravit případné chyby**

Diagnostikovat a opravit problémy nalezené při smoke testu.

- [ ] **Step 5: Finální commit**

```bash
git add -A
git commit -m "feat: complete admin auth + user CRUD (steps 3-5)"
```
