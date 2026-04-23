# Banner Module – Implementation Plan

> **For agentic workers:** REQUIRED: Use the `subagent-driven-development` agent (recommended) or `executing-plans` agent to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Migrovat Banner modul z Laminas (polar) do Symfony (polar-symfony) jako kopii 1:1 — admin CRUD pro 6 typů bannerů (leaderboard, rectangle, square, mobilesticky, mobilesquare1, mobilesquare2) + web partial šablony.

**Architecture:** Admin controllery (ListController + WriteController) pro každý typ banneru, sdílený BannerAdminRepository, šablony v `templates/banner/admin/{type}/`. Web část (WebListController pro JSON endpointy, BannerWriteController pro set-clicked/set-showed, BannerGlobalListener) již z části existuje. Žádné ORM entity — vše přes pole a Doctrine DBAL.

**Tech Stack:** Symfony 8, PHP 8.5, Doctrine DBAL QueryBuilder, PhtmlRenderer, YAML routes, FlashMessenger service, Logger service, Symfony Security.

---

## Přehled souborů

### Nové soubory (src/)
- `src/Banner/Controller/Admin/Leaderboard/LeaderboardListController.php`
- `src/Banner/Controller/Admin/Leaderboard/LeaderboardWriteController.php`
- `src/Banner/Controller/Admin/Rectangle/RectangleListController.php`
- `src/Banner/Controller/Admin/Rectangle/RectangleWriteController.php`
- `src/Banner/Controller/Admin/Square/SquareListController.php`
- `src/Banner/Controller/Admin/Square/SquareWriteController.php`
- `src/Banner/Controller/Admin/Mobilesticky/MobilestickyListController.php`
- `src/Banner/Controller/Admin/Mobilesticky/MobilestickyWriteController.php`
- `src/Banner/Controller/Admin/Mobilesquare1/Mobilesquare1ListController.php`
- `src/Banner/Controller/Admin/Mobilesquare1/Mobilesquare1WriteController.php`
- `src/Banner/Controller/Admin/Mobilesquare2/Mobilesquare2ListController.php`
- `src/Banner/Controller/Admin/Mobilesquare2/Mobilesquare2WriteController.php`
- `src/Banner/Repository/LeaderboardRepository.php` (nahrazuje/doplňuje BannerRepository)
- `src/Banner/Repository/RectangleRepository.php`
- `src/Banner/Repository/SquareRepository.php`
- `src/Banner/Repository/MobilestickyRepository.php`
- `src/Banner/Repository/Mobilesquare1Repository.php`
- `src/Banner/Repository/Mobilesquare2Repository.php`

### Upravené soubory (src/)
- `src/Banner/Repository/BannerRepository.php` — přidat `setClicked()`
- `src/Banner/Controller/Web/BannerWriteController.php` — přidat `setClicked()`
- `config/routes/banner.yaml` — přidat admin routes
- `config/routes/admin.yaml` — přidat sekci `# Banner`

### Nové soubory (templates/)
- `templates/banner/admin/leaderboard/index.phtml`
- `templates/banner/admin/leaderboard/list.phtml`
- `templates/banner/admin/leaderboard/add.phtml`
- `templates/banner/admin/leaderboard/edit.phtml`
- `templates/banner/admin/leaderboard/bannerForm.phtml`
- `templates/banner/admin/rectangle/index.phtml`
- `templates/banner/admin/rectangle/list.phtml`
- `templates/banner/admin/rectangle/add.phtml`
- `templates/banner/admin/rectangle/edit.phtml`
- `templates/banner/admin/rectangle/bannerForm.phtml`
- `templates/banner/admin/square/index.phtml`
- `templates/banner/admin/square/list.phtml`
- `templates/banner/admin/square/add.phtml`
- `templates/banner/admin/square/edit.phtml`
- `templates/banner/admin/square/bannerForm.phtml`
- `templates/banner/admin/mobilesticky/index.phtml`
- `templates/banner/admin/mobilesticky/list.phtml`
- `templates/banner/admin/mobilesticky/add.phtml`
- `templates/banner/admin/mobilesticky/edit.phtml`
- `templates/banner/admin/mobilesticky/bannerForm.phtml`
- `templates/banner/admin/mobilesquare1/index.phtml`
- `templates/banner/admin/mobilesquare1/list.phtml`
- `templates/banner/admin/mobilesquare1/add.phtml`
- `templates/banner/admin/mobilesquare1/edit.phtml`
- `templates/banner/admin/mobilesquare1/bannerForm.phtml`
- `templates/banner/admin/mobilesquare2/index.phtml`
- `templates/banner/admin/mobilesquare2/list.phtml`
- `templates/banner/admin/mobilesquare2/add.phtml`
- `templates/banner/admin/mobilesquare2/edit.phtml`
- `templates/banner/admin/mobilesquare2/bannerForm.phtml`
- `templates/banner/admin/dashboard/widget.phtml`
- `templates/banner/admin/changelog.phtml`
- `templates/banner/partial/ads/mobilesticky.phtml` (přidat chybějící)

### Existující soubory (templates/banner/partial/ads/) — zkontrolovat/doplnit
- `article-151875.phtml`, `article-content.phtml`, `article-paragraph.phtml`, `article-right-bottom.phtml`, `article-right-top.phtml`, `article.phtml`, `layout-bottom.phtml`, `mobilesquare1.phtml`, `mobilesquare2.phtml`, `mobilesticky.phtml`, `rectangle.phtml`, `top.phtml` — tyto soubory existují, průběžně ověřit shodu s polarem

---

## Vzorové repository metody (Doctrine DBAL)

Každý `*Repository.php` má tuto sadu metod (vzor z existujícího `BannerRepository` + polar `MariaDbSqlRepository`):

```php
// Tabulka: banner_leaderboard (nebo rectangle, square, atd.)
public function fetchForBootstrapTable(array $params): ?array
public function getCountForBootstrapTable(array $params): ?int
public function getCount(bool $active = null): int
public function findPostBy(string $column, int|string $value): array
public function insertPost(array $data): int           // vrací ID
public function updatePost(int $id, array $data): void
public function deletePost(int $id): void
public function getBannerForLayout(): ?array            // jen leaderboard + mobilesticky
public function getBannerForWeb(): ?array               // rectangle, square, mobilesquare1, mobilesquare2
```

---

## Task 1: LeaderboardRepository

**Files:**
- Create: `src/Banner/Repository/LeaderboardRepository.php`

- [ ] **Step 1: Vytvořit `LeaderboardRepository.php`**

```php
<?php
/*
 * @project ferritenergy
 * @author Rostislav Greipel
 * @link https://rogr.cz
 * @copyright (c) 2011 - 2021 ROGR All Rights Reserved
 * @license https://rogr.cz/license/eula EULA License
 */

declare(strict_types=1);

namespace App\Banner\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

final class LeaderboardRepository
{
    private string $table = 'banner_leaderboard';

    public function __construct(private Connection $connection) {}

    /**
     * @throws Exception
     */
    public function fetchForBootstrapTable(array $params): ?array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->table)
            ->where('lang = :lang')
            ->setParameter('lang', $params['lang'])
            ->orderBy($params['sort'] ?? 'rank', $params['order'] ?? 'asc');

        if (isset($params['limit'])) {
            $qb->setMaxResults((int) $params['limit']);
        }
        if (isset($params['offset'])) {
            $qb->setFirstResult((int) $params['offset']);
        }
        if (isset($params['search']) && $params['search'] !== '') {
            $qb->andWhere('MATCH (title, link) AGAINST (:search IN BOOLEAN MODE)')
               ->setParameter('search', $params['search'] . '*');
        }

        return $qb->executeQuery()->fetchAllAssociative() ?: null;
    }

    /**
     * @throws Exception
     */
    public function getCountForBootstrapTable(array $params): int
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from($this->table)
            ->where('lang = :lang')
            ->setParameter('lang', $params['lang']);

        if (isset($params['search']) && $params['search'] !== '') {
            $qb->andWhere('MATCH (title, link) AGAINST (:search IN BOOLEAN MODE)')
               ->setParameter('search', $params['search'] . '*');
        }

        return (int) $qb->executeQuery()->fetchOne();
    }

    /**
     * @throws Exception
     */
    public function getCount(bool $active = null): int
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from($this->table);

        if ($active !== null) {
            $qb->where('active = :active')
               ->setParameter('active', (int) $active);
        }

        return (int) $qb->executeQuery()->fetchOne();
    }

    /**
     * @throws Exception|\RuntimeException|\InvalidArgumentException
     */
    public function findPostBy(string $column, int|string $value): array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select(
                '*',
                'SUBSTRING_INDEX(`public_from`, \' \', 1) AS public_from',
                'SUBSTRING_INDEX(`public_from`, \' \', -1) AS public_from_time',
                'SUBSTRING_INDEX(`public_to`, \' \', 1) AS public_to',
                'SUBSTRING_INDEX(`public_to`, \' \', -1) AS public_to_time'
            )
            ->from($this->table)
            ->where($column . ' = :value')
            ->setParameter('value', $value);

        $row = $qb->executeQuery()->fetchAssociative();

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
    public function insertPost(array $data): int
    {
        $this->connection->insert($this->table, $data);
        return (int) $this->connection->lastInsertId();
    }

    /**
     * @throws Exception
     */
    public function updatePost(int $id, array $data): void
    {
        $this->connection->update($this->table, $data, ['id' => $id]);
    }

    /**
     * @throws Exception
     */
    public function deletePost(int $id): void
    {
        $this->connection->delete($this->table, ['id' => $id]);
    }

    /**
     * @throws Exception
     */
    public function getBannerForLayout(): ?array
    {
        return $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->table)
            ->where('active = 1')
            ->andWhere('public_from <= NOW()')
            ->andWhere('public_to >= NOW()')
            ->orderBy('RAND()')
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative() ?: null;
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Banner/Repository/LeaderboardRepository.php
git commit -m "feat(banner): LeaderboardRepository"
```

---

## Task 2: RectangleRepository, SquareRepository, MobilestickyRepository, Mobilesquare1Repository, Mobilesquare2Repository

**Files:**
- Create: `src/Banner/Repository/RectangleRepository.php`
- Create: `src/Banner/Repository/SquareRepository.php`
- Create: `src/Banner/Repository/MobilestickyRepository.php`
- Create: `src/Banner/Repository/Mobilesquare1Repository.php`
- Create: `src/Banner/Repository/Mobilesquare2Repository.php`

Každý soubor je kopie `LeaderboardRepository` se změnou:
- namespace, class name, `$table` hodnota
- metoda `getBannerForWeb()` místo `getBannerForLayout()` (u rectangle, square, mobilesquare1, mobilesquare2)
- mobilesticky má `getBannerForLayout()` stejně jako leaderboard

- [ ] **Step 1: Vytvořit `RectangleRepository.php`**

```php
<?php
/*
 * @project ferritenergy
 * @author Rostislav Greipel
 * @link https://rogr.cz
 * @copyright (c) 2011 - 2021 ROGR All Rights Reserved
 * @license https://rogr.cz/license/eula EULA License
 */

declare(strict_types=1);

namespace App\Banner\Repository;

use Doctrine\DBAL\Connection;

final class RectangleRepository
{
    private string $table = 'banner_rectangle';

    public function __construct(private Connection $connection) {}

    public function fetchForBootstrapTable(array $params): ?array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->table)
            ->where('lang = :lang')
            ->setParameter('lang', $params['lang'])
            ->orderBy($params['sort'] ?? 'rank', $params['order'] ?? 'asc');

        if (isset($params['limit'])) {
            $qb->setMaxResults((int) $params['limit']);
        }
        if (isset($params['offset'])) {
            $qb->setFirstResult((int) $params['offset']);
        }
        if (isset($params['search']) && $params['search'] !== '') {
            $qb->andWhere('MATCH (title, link) AGAINST (:search IN BOOLEAN MODE)')
               ->setParameter('search', $params['search'] . '*');
        }

        return $qb->executeQuery()->fetchAllAssociative() ?: null;
    }

    public function getCountForBootstrapTable(array $params): int
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from($this->table)
            ->where('lang = :lang')
            ->setParameter('lang', $params['lang']);

        if (isset($params['search']) && $params['search'] !== '') {
            $qb->andWhere('MATCH (title, link) AGAINST (:search IN BOOLEAN MODE)')
               ->setParameter('search', $params['search'] . '*');
        }

        return (int) $qb->executeQuery()->fetchOne();
    }

    public function getCount(bool $active = null): int
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from($this->table);

        if ($active !== null) {
            $qb->where('active = :active')
               ->setParameter('active', (int) $active);
        }

        return (int) $qb->executeQuery()->fetchOne();
    }

    public function findPostBy(string $column, int|string $value): array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select(
                '*',
                'SUBSTRING_INDEX(`public_from`, \' \', 1) AS public_from',
                'SUBSTRING_INDEX(`public_from`, \' \', -1) AS public_from_time',
                'SUBSTRING_INDEX(`public_to`, \' \', 1) AS public_to',
                'SUBSTRING_INDEX(`public_to`, \' \', -1) AS public_to_time'
            )
            ->from($this->table)
            ->where($column . ' = :value')
            ->setParameter('value', $value);

        $row = $qb->executeQuery()->fetchAssociative();

        if (!$row) {
            throw new \InvalidArgumentException(sprintf(
                'Záznam s identifikátorem "%s" nenalezen. Tabulka "%s".',
                $column . ' => ' . $value,
                $this->table
            ));
        }

        return $row;
    }

    public function insertPost(array $data): int
    {
        $this->connection->insert($this->table, $data);
        return (int) $this->connection->lastInsertId();
    }

    public function updatePost(int $id, array $data): void
    {
        $this->connection->update($this->table, $data, ['id' => $id]);
    }

    public function deletePost(int $id): void
    {
        $this->connection->delete($this->table, ['id' => $id]);
    }

    public function getBannerForWeb(): ?array
    {
        return $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->table)
            ->where('active = 1')
            ->andWhere('public_from <= NOW()')
            ->andWhere('public_to >= NOW()')
            ->orderBy('RAND()')
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative() ?: null;
    }
}
```

- [ ] **Step 2: Vytvořit `SquareRepository.php`** — kopie Rectangle, změnit `class SquareRepository`, `$table = 'banner_square'`

- [ ] **Step 3: Vytvořit `MobilestickyRepository.php`** — kopie Leaderboard (má `getBannerForLayout()`), změnit `class MobilestickyRepository`, `$table = 'banner_mobilesticky'`

- [ ] **Step 4: Vytvořit `Mobilesquare1Repository.php`** — kopie Rectangle, změnit `class Mobilesquare1Repository`, `$table = 'banner_mobilesquare1'`

- [ ] **Step 5: Vytvořit `Mobilesquare2Repository.php`** — kopie Rectangle, změnit `class Mobilesquare2Repository`, `$table = 'banner_mobilesquare2'`

- [ ] **Step 6: Commit**

```bash
git add src/Banner/Repository/
git commit -m "feat(banner): repositories pro všechny typy bannerů"
```

---

## Task 3: BannerRepository — přidat setClicked()

**Files:**
- Modify: `src/Banner/Repository/BannerRepository.php`

Polar `WebWriteController` má `setClickedAction()`, Symfony zatím jen `setShowed()`.

- [ ] **Step 1: Přidat metodu `setClicked()` do BannerRepository**

Přidat za metodu `setShowed()`:
```php
public function setClicked(string $type, int $id): bool
{
    $allowed = ['leaderboard', 'rectangle', 'square', 'mobilesticky', 'mobilesquare1', 'mobilesquare2'];
    if (!in_array($type, $allowed, true)) {
        return false;
    }

    $affected = $this->connection->executeStatement(
        'UPDATE banner_' . $type . ' SET clicked = clicked + 1 WHERE id = ?',
        [$id],
    );

    return $affected > 0;
}
```

- [ ] **Step 2: Přidat `setClicked()` do BannerWriteController**

Upravit `src/Banner/Controller/Web/BannerWriteController.php` — přidat metodu:
```php
public function setClicked(Request $request): JsonResponse
{
    $type = (string) $request->request->get('type', '');
    $id   = (int)    $request->request->get('id', 0);

    if (!$type || !$id) {
        return new JsonResponse(['success' => false]);
    }

    $success = $this->bannerRepository->setClicked($type, $id);

    return new JsonResponse(['success' => $success]);
}
```

- [ ] **Step 3: Přidat route do banner.yaml**

Upravit `config/routes/banner.yaml` — přidat:
```yaml
banner_set_clicked:
    path: /banner/set-clicked
    controller: App\Banner\Controller\Web\BannerWriteController::setClicked
    methods: [POST]
```

- [ ] **Step 4: Commit**

```bash
git add src/Banner/Repository/BannerRepository.php src/Banner/Controller/Web/BannerWriteController.php config/routes/banner.yaml
git commit -m "feat(banner): setClicked endpoint"
```

---

## Task 4: Admin routes v admin.yaml — sekce Banner

**Files:**
- Modify: `config/routes/admin.yaml`

- [ ] **Step 1: Přidat sekci `# Banner` do admin.yaml**

Přidat za sekci `# User` (nebo na konec souboru):
```yaml
# Banner

# Leaderboard
admin_banner_leaderboard_index:
  path: /admin/banner/leaderboard
  controller: App\Banner\Controller\Admin\Leaderboard\LeaderboardListController::index

admin_banner_leaderboard_list:
  path: /admin/banner/leaderboard/seznam/{lang}
  controller: App\Banner\Controller\Admin\Leaderboard\LeaderboardListController::list
  defaults:
    lang: cs_CZ
  requirements:
    lang: '[a-zA-Z_]+'

admin_banner_leaderboard_get_list:
  path: /admin/banner/leaderboard/json-list/get-list
  controller: App\Banner\Controller\Admin\Leaderboard\LeaderboardListController::getList
  methods: [GET]

admin_banner_leaderboard_get_banner:
  path: /admin/banner/leaderboard/json-list/get-banner
  controller: App\Banner\Controller\Admin\Leaderboard\LeaderboardListController::getBanner
  methods: [POST]

admin_banner_leaderboard_add:
  path: /admin/banner/leaderboard/seznam/{lang}/pridat
  controller: App\Banner\Controller\Admin\Leaderboard\LeaderboardWriteController::add
  defaults:
    lang: cs_CZ
  requirements:
    lang: '[a-zA-Z_]+'

admin_banner_leaderboard_edit:
  path: /admin/banner/leaderboard/seznam/{lang}/upravit/{id}
  controller: App\Banner\Controller\Admin\Leaderboard\LeaderboardWriteController::edit
  defaults:
    lang: cs_CZ
  requirements:
    lang: '[a-zA-Z_]+'
    id: '\d+'

admin_banner_leaderboard_delete:
  path: /admin/banner/leaderboard/json-write/smazat-banner
  controller: App\Banner\Controller\Admin\Leaderboard\LeaderboardWriteController::deleteBanner
  methods: [POST]

admin_banner_leaderboard_set_sort:
  path: /admin/banner/leaderboard/json-write/set-sort
  controller: App\Banner\Controller\Admin\Leaderboard\LeaderboardWriteController::setSort
  methods: [POST]

admin_banner_leaderboard_upload_image:
  path: /admin/banner/leaderboard/json-write/nahrat-obrazek
  controller: App\Banner\Controller\Admin\Leaderboard\LeaderboardWriteController::uploadImage
  methods: [POST]

admin_banner_leaderboard_set_default_image:
  path: /admin/banner/leaderboard/json-write/set-default-image
  controller: App\Banner\Controller\Admin\Leaderboard\LeaderboardWriteController::setDefaultImage
  methods: [POST]

# Rectangle
admin_banner_rectangle_index:
  path: /admin/banner/rectangle
  controller: App\Banner\Controller\Admin\Rectangle\RectangleListController::index

admin_banner_rectangle_list:
  path: /admin/banner/rectangle/seznam/{lang}
  controller: App\Banner\Controller\Admin\Rectangle\RectangleListController::list
  defaults:
    lang: cs_CZ
  requirements:
    lang: '[a-zA-Z_]+'

admin_banner_rectangle_get_list:
  path: /admin/banner/rectangle/json-list/get-list
  controller: App\Banner\Controller\Admin\Rectangle\RectangleListController::getList
  methods: [GET]

admin_banner_rectangle_get_banner:
  path: /admin/banner/rectangle/json-list/get-banner
  controller: App\Banner\Controller\Admin\Rectangle\RectangleListController::getBanner
  methods: [POST]

admin_banner_rectangle_add:
  path: /admin/banner/rectangle/seznam/{lang}/pridat
  controller: App\Banner\Controller\Admin\Rectangle\RectangleWriteController::add
  defaults:
    lang: cs_CZ
  requirements:
    lang: '[a-zA-Z_]+'

admin_banner_rectangle_edit:
  path: /admin/banner/rectangle/seznam/{lang}/upravit/{id}
  controller: App\Banner\Controller\Admin\Rectangle\RectangleWriteController::edit
  defaults:
    lang: cs_CZ
  requirements:
    lang: '[a-zA-Z_]+'
    id: '\d+'

admin_banner_rectangle_delete:
  path: /admin/banner/rectangle/json-write/smazat-banner
  controller: App\Banner\Controller\Admin\Rectangle\RectangleWriteController::deleteBanner
  methods: [POST]

admin_banner_rectangle_set_sort:
  path: /admin/banner/rectangle/json-write/set-sort
  controller: App\Banner\Controller\Admin\Rectangle\RectangleWriteController::setSort
  methods: [POST]

admin_banner_rectangle_upload_image:
  path: /admin/banner/rectangle/json-write/nahrat-obrazek
  controller: App\Banner\Controller\Admin\Rectangle\RectangleWriteController::uploadImage
  methods: [POST]

admin_banner_rectangle_set_default_image:
  path: /admin/banner/rectangle/json-write/set-default-image
  controller: App\Banner\Controller\Admin\Rectangle\RectangleWriteController::setDefaultImage
  methods: [POST]

# Square
admin_banner_square_index:
  path: /admin/banner/square
  controller: App\Banner\Controller\Admin\Square\SquareListController::index

admin_banner_square_list:
  path: /admin/banner/square/seznam/{lang}
  controller: App\Banner\Controller\Admin\Square\SquareListController::list
  defaults:
    lang: cs_CZ
  requirements:
    lang: '[a-zA-Z_]+'

admin_banner_square_get_list:
  path: /admin/banner/square/json-list/get-list
  controller: App\Banner\Controller\Admin\Square\SquareListController::getList
  methods: [GET]

admin_banner_square_get_banner:
  path: /admin/banner/square/json-list/get-banner
  controller: App\Banner\Controller\Admin\Square\SquareListController::getBanner
  methods: [POST]

admin_banner_square_add:
  path: /admin/banner/square/seznam/{lang}/pridat
  controller: App\Banner\Controller\Admin\Square\SquareWriteController::add
  defaults:
    lang: cs_CZ
  requirements:
    lang: '[a-zA-Z_]+'

admin_banner_square_edit:
  path: /admin/banner/square/seznam/{lang}/upravit/{id}
  controller: App\Banner\Controller\Admin\Square\SquareWriteController::edit
  defaults:
    lang: cs_CZ
  requirements:
    lang: '[a-zA-Z_]+'
    id: '\d+'

admin_banner_square_delete:
  path: /admin/banner/square/json-write/smazat-banner
  controller: App\Banner\Controller\Admin\Square\SquareWriteController::deleteBanner
  methods: [POST]

admin_banner_square_set_sort:
  path: /admin/banner/square/json-write/set-sort
  controller: App\Banner\Controller\Admin\Square\SquareWriteController::setSort
  methods: [POST]

admin_banner_square_upload_image:
  path: /admin/banner/square/json-write/nahrat-obrazek
  controller: App\Banner\Controller\Admin\Square\SquareWriteController::uploadImage
  methods: [POST]

admin_banner_square_set_default_image:
  path: /admin/banner/square/json-write/set-default-image
  controller: App\Banner\Controller\Admin\Square\SquareWriteController::setDefaultImage
  methods: [POST]

# Mobilesticky
admin_banner_mobilesticky_index:
  path: /admin/banner/mobilesticky
  controller: App\Banner\Controller\Admin\Mobilesticky\MobilestickyListController::index

admin_banner_mobilesticky_list:
  path: /admin/banner/mobilesticky/seznam/{lang}
  controller: App\Banner\Controller\Admin\Mobilesticky\MobilestickyListController::list
  defaults:
    lang: cs_CZ
  requirements:
    lang: '[a-zA-Z_]+'

admin_banner_mobilesticky_get_list:
  path: /admin/banner/mobilesticky/json-list/get-list
  controller: App\Banner\Controller\Admin\Mobilesticky\MobilestickyListController::getList
  methods: [GET]

admin_banner_mobilesticky_get_banner:
  path: /admin/banner/mobilesticky/json-list/get-banner
  controller: App\Banner\Controller\Admin\Mobilesticky\MobilestickyListController::getBanner
  methods: [POST]

admin_banner_mobilesticky_add:
  path: /admin/banner/mobilesticky/seznam/{lang}/pridat
  controller: App\Banner\Controller\Admin\Mobilesticky\MobilestickyWriteController::add
  defaults:
    lang: cs_CZ
  requirements:
    lang: '[a-zA-Z_]+'

admin_banner_mobilesticky_edit:
  path: /admin/banner/mobilesticky/seznam/{lang}/upravit/{id}
  controller: App\Banner\Controller\Admin\Mobilesticky\MobilestickyWriteController::edit
  defaults:
    lang: cs_CZ
  requirements:
    lang: '[a-zA-Z_]+'
    id: '\d+'

admin_banner_mobilesticky_delete:
  path: /admin/banner/mobilesticky/json-write/smazat-banner
  controller: App\Banner\Controller\Admin\Mobilesticky\MobilestickyWriteController::deleteBanner
  methods: [POST]

admin_banner_mobilesticky_set_sort:
  path: /admin/banner/mobilesticky/json-write/set-sort
  controller: App\Banner\Controller\Admin\Mobilesticky\MobilestickyWriteController::setSort
  methods: [POST]

admin_banner_mobilesticky_upload_image:
  path: /admin/banner/mobilesticky/json-write/nahrat-obrazek
  controller: App\Banner\Controller\Admin\Mobilesticky\MobilestickyWriteController::uploadImage
  methods: [POST]

admin_banner_mobilesticky_set_default_image:
  path: /admin/banner/mobilesticky/json-write/set-default-image
  controller: App\Banner\Controller\Admin\Mobilesticky\MobilestickyWriteController::setDefaultImage
  methods: [POST]

# Mobilesquare1
admin_banner_mobilesquare1_index:
  path: /admin/banner/mobilesquare1
  controller: App\Banner\Controller\Admin\Mobilesquare1\Mobilesquare1ListController::index

admin_banner_mobilesquare1_list:
  path: /admin/banner/mobilesquare1/seznam/{lang}
  controller: App\Banner\Controller\Admin\Mobilesquare1\Mobilesquare1ListController::list
  defaults:
    lang: cs_CZ
  requirements:
    lang: '[a-zA-Z_]+'

admin_banner_mobilesquare1_get_list:
  path: /admin/banner/mobilesquare1/json-list/get-list
  controller: App\Banner\Controller\Admin\Mobilesquare1\Mobilesquare1ListController::getList
  methods: [GET]

admin_banner_mobilesquare1_get_banner:
  path: /admin/banner/mobilesquare1/json-list/get-banner
  controller: App\Banner\Controller\Admin\Mobilesquare1\Mobilesquare1ListController::getBanner
  methods: [POST]

admin_banner_mobilesquare1_add:
  path: /admin/banner/mobilesquare1/seznam/{lang}/pridat
  controller: App\Banner\Controller\Admin\Mobilesquare1\Mobilesquare1WriteController::add
  defaults:
    lang: cs_CZ
  requirements:
    lang: '[a-zA-Z_]+'

admin_banner_mobilesquare1_edit:
  path: /admin/banner/mobilesquare1/seznam/{lang}/upravit/{id}
  controller: App\Banner\Controller\Admin\Mobilesquare1\Mobilesquare1WriteController::edit
  defaults:
    lang: cs_CZ
  requirements:
    lang: '[a-zA-Z_]+'
    id: '\d+'

admin_banner_mobilesquare1_delete:
  path: /admin/banner/mobilesquare1/json-write/smazat-banner
  controller: App\Banner\Controller\Admin\Mobilesquare1\Mobilesquare1WriteController::deleteBanner
  methods: [POST]

admin_banner_mobilesquare1_set_sort:
  path: /admin/banner/mobilesquare1/json-write/set-sort
  controller: App\Banner\Controller\Admin\Mobilesquare1\Mobilesquare1WriteController::setSort
  methods: [POST]

admin_banner_mobilesquare1_upload_image:
  path: /admin/banner/mobilesquare1/json-write/nahrat-obrazek
  controller: App\Banner\Controller\Admin\Mobilesquare1\Mobilesquare1WriteController::uploadImage
  methods: [POST]

admin_banner_mobilesquare1_set_default_image:
  path: /admin/banner/mobilesquare1/json-write/set-default-image
  controller: App\Banner\Controller\Admin\Mobilesquare1\Mobilesquare1WriteController::setDefaultImage
  methods: [POST]

# Mobilesquare2
admin_banner_mobilesquare2_index:
  path: /admin/banner/mobilesquare2
  controller: App\Banner\Controller\Admin\Mobilesquare2\Mobilesquare2ListController::index

admin_banner_mobilesquare2_list:
  path: /admin/banner/mobilesquare2/seznam/{lang}
  controller: App\Banner\Controller\Admin\Mobilesquare2\Mobilesquare2ListController::list
  defaults:
    lang: cs_CZ
  requirements:
    lang: '[a-zA-Z_]+'

admin_banner_mobilesquare2_get_list:
  path: /admin/banner/mobilesquare2/json-list/get-list
  controller: App\Banner\Controller\Admin\Mobilesquare2\Mobilesquare2ListController::getList
  methods: [GET]

admin_banner_mobilesquare2_get_banner:
  path: /admin/banner/mobilesquare2/json-list/get-banner
  controller: App\Banner\Controller\Admin\Mobilesquare2\Mobilesquare2ListController::getBanner
  methods: [POST]

admin_banner_mobilesquare2_add:
  path: /admin/banner/mobilesquare2/seznam/{lang}/pridat
  controller: App\Banner\Controller\Admin\Mobilesquare2\Mobilesquare2WriteController::add
  defaults:
    lang: cs_CZ
  requirements:
    lang: '[a-zA-Z_]+'

admin_banner_mobilesquare2_edit:
  path: /admin/banner/mobilesquare2/seznam/{lang}/upravit/{id}
  controller: App\Banner\Controller\Admin\Mobilesquare2\Mobilesquare2WriteController::edit
  defaults:
    lang: cs_CZ
  requirements:
    lang: '[a-zA-Z_]+'
    id: '\d+'

admin_banner_mobilesquare2_delete:
  path: /admin/banner/mobilesquare2/json-write/smazat-banner
  controller: App\Banner\Controller\Admin\Mobilesquare2\Mobilesquare2WriteController::deleteBanner
  methods: [POST]

admin_banner_mobilesquare2_set_sort:
  path: /admin/banner/mobilesquare2/json-write/set-sort
  controller: App\Banner\Controller\Admin\Mobilesquare2\Mobilesquare2WriteController::setSort
  methods: [POST]

admin_banner_mobilesquare2_upload_image:
  path: /admin/banner/mobilesquare2/json-write/nahrat-obrazek
  controller: App\Banner\Controller\Admin\Mobilesquare2\Mobilesquare2WriteController::uploadImage
  methods: [POST]

admin_banner_mobilesquare2_set_default_image:
  path: /admin/banner/mobilesquare2/json-write/set-default-image
  controller: App\Banner\Controller\Admin\Mobilesquare2\Mobilesquare2WriteController::setDefaultImage
  methods: [POST]
```

- [ ] **Step 2: Commit**

```bash
git add config/routes/admin.yaml
git commit -m "feat(banner): admin routes pro všechny typy bannerů"
```

---

## Task 5: LeaderboardListController

**Files:**
- Create: `src/Banner/Controller/Admin/Leaderboard/LeaderboardListController.php`

- [ ] **Step 1: Vytvořit controller**

```php
<?php
/*
 * @project ferritenergy
 * @author Rostislav Greipel
 * @link https://rogr.cz
 * @copyright (c) 2011 - 2021 ROGR All Rights Reserved
 * @license https://rogr.cz/license/eula EULA License
 */

declare(strict_types=1);

namespace App\Banner\Controller\Admin\Leaderboard;

use App\Application\Service\FlashMessenger;
use App\Application\View\PhtmlRenderer;
use App\Banner\Repository\LeaderboardRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class LeaderboardListController
{
    public function __construct(
        private LeaderboardRepository $leaderboardRepository,
        private FlashMessenger $flashMessenger,
        private PhtmlRenderer $renderer,
    ) {}

    public function index(): Response
    {
        return new Response($this->renderer->renderWithAdminLayout('banner/admin/leaderboard/index', [
            'pageTitle' => 'Leaderboard',
            'countLeaderboard' => $this->leaderboardRepository->getCount(),
            'countLeaderboardFuture' => $this->leaderboardRepository->getCount(true),
        ]));
    }

    public function list(Request $request): Response
    {
        $lang = $request->attributes->get('lang', 'cs_CZ');

        $this->flashMessenger->processFlashMessages();

        return new Response($this->renderer->renderWithAdminLayout('banner/admin/leaderboard/list', [
            'pageTitle' => 'Leaderboard',
            'lang' => $lang,
        ]));
    }

    public function getList(Request $request): JsonResponse
    {
        $params = $request->query->all();

        try {
            $rows = $this->leaderboardRepository->fetchForBootstrapTable($params);
            $total = $this->leaderboardRepository->getCountForBootstrapTable($params);
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

    public function getBanner(Request $request): JsonResponse
    {
        try {
            $id = $request->request->getInt('id');
            $leaderboard = $this->leaderboardRepository->findPostBy('id', $id);

            return new JsonResponse([
                'success' => true,
                'message' => null,
                'leaderboard' => $leaderboard,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
                'leaderboard' => null,
            ]);
        }
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Banner/Controller/Admin/Leaderboard/LeaderboardListController.php
git commit -m "feat(banner): LeaderboardListController"
```

---

## Task 6: LeaderboardWriteController

**Files:**
- Create: `src/Banner/Controller/Admin/Leaderboard/LeaderboardWriteController.php`

- [ ] **Step 1: Vytvořit controller**

```php
<?php
/*
 * @project ferritenergy
 * @author Rostislav Greipel
 * @link https://rogr.cz
 * @copyright (c) 2011 - 2021 ROGR All Rights Reserved
 * @license https://rogr.cz/license/eula EULA License
 */

declare(strict_types=1);

namespace App\Banner\Controller\Admin\Leaderboard;

use App\Application\Service\FlashMessenger;
use App\Application\Service\Logger;
use App\Application\View\PhtmlRenderer;
use App\Authorization\Identity\AuthorizationUser;
use App\Banner\Repository\LeaderboardRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class LeaderboardWriteController
{
    private string $imageDefault = 'data/banner/leaderboard/!default-banner.png';

    public function __construct(
        private FlashMessenger $flashMessenger,
        private Logger $logger,
        private LeaderboardRepository $leaderboardRepository,
        private PhtmlRenderer $renderer,
        private Security $security,
        private UrlGeneratorInterface $urlGenerator,
        private string $PUBLIC_PATH,
    ) {}

    public function add(Request $request): Response
    {
        /** @var AuthorizationUser $identity */
        $identity = $this->security->getUser();
        $lang = $request->attributes->get('lang', 'cs_CZ');

        $defaults = [
            'active' => '1',
            'public_from' => (new \DateTime())->format('d.m.Y'),
            'public_from_time' => (new \DateTime())->format('H:i'),
            'public_to' => '01.01.2100',
            'public_to_time' => '0:00',
            'image' => $this->imageDefault,
        ];

        if ($request->isMethod('POST')) {
            $post = $request->request->all();

            if (isset($post['cancel'])) {
                return new RedirectResponse($this->urlGenerator->generate('admin_banner_leaderboard_list', ['lang' => $lang]));
            }

            $errors = $this->validateForm($post);
            if (!empty($errors)) {
                return new Response($this->renderer->renderWithAdminLayout('banner/admin/leaderboard/add', [
                    'pageTitle' => 'Leaderboard',
                    'post' => $post,
                    'errors' => $errors,
                    'lang' => $lang,
                ]));
            }

            try {
                $rank = $this->leaderboardRepository->getCount() + 1;
                $now = date('Y-m-d H:i:s');

                $id = $this->leaderboardRepository->insertPost([
                    'lang' => $lang,
                    'active' => !empty($post['active']) ? 1 : 0,
                    'rank' => $rank,
                    'title' => $post['title'] ?? '',
                    'link' => $post['link'] ?? '',
                    'image_alt' => $post['image_alt'] ?? '',
                    'public_from' => $this->formatDatetime($post['public_from'] ?? '', $post['public_from_time'] ?? ''),
                    'public_to' => $this->formatDatetime($post['public_to'] ?? '', $post['public_to_time'] ?? ''),
                    'created_date' => $now,
                    'updated_date' => $now,
                    'created_user' => $identity->getUserIdentifier(),
                    'updated_user' => $identity->getUserIdentifier(),
                ]);

                // Adresář
                $folder = 'data/banner/leaderboard/' . $id;
                if (!is_dir($this->PUBLIC_PATH . '/' . $folder)) {
                    mkdir($this->PUBLIC_PATH . '/' . $folder, 0777, true);
                    chmod($this->PUBLIC_PATH . '/' . $folder, 0777);
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

                $this->leaderboardRepository->updatePost($id, ['image' => $image]);

                $this->flashMessenger->addMessage(
                    'success',
                    'Banner',
                    'Banner <strong>"' . htmlspecialchars($post['title'] ?? '') . '"</strong> byl vytvořen'
                );

                // Log
                $this->logger->notice('LEADERBOARD - Add banner', [
                    'description' => 'OK',
                    'user' => $identity->getUserIdentifier(),
                    'file' => __FILE__,
                ]);

                return new RedirectResponse($this->urlGenerator->generate('admin_banner_leaderboard_list', ['lang' => $lang]));
            } catch (\Exception $e) {
                $this->logger->err('LEADERBOARD - Add banner', [
                    'description' => 'ERROR',
                    'user' => $identity->getUserIdentifier(),
                    'file' => __FILE__,
                    'trace' => $e->getMessage(),
                ]);

                return new Response($this->renderer->renderWithAdminLayout('banner/admin/leaderboard/add', [
                    'pageTitle' => 'Leaderboard',
                    'post' => $post,
                    'errors' => ['general' => $e->getMessage()],
                    'lang' => $lang,
                ]));
            }
        }

        return new Response($this->renderer->renderWithAdminLayout('banner/admin/leaderboard/add', [
            'pageTitle' => 'Leaderboard',
            'post' => $defaults,
            'errors' => [],
            'lang' => $lang,
        ]));
    }

    public function edit(Request $request, int $id): Response
    {
        /** @var AuthorizationUser $identity */
        $identity = $this->security->getUser();
        $lang = $request->attributes->get('lang', 'cs_CZ');

        try {
            $leaderboard = $this->leaderboardRepository->findPostBy('id', $id);
        } catch (\Exception) {
            return new RedirectResponse($this->urlGenerator->generate('admin_banner_leaderboard_list', ['lang' => $lang]));
        }

        if ($request->isMethod('POST')) {
            $post = $request->request->all();

            if (isset($post['cancel'])) {
                return new RedirectResponse($this->urlGenerator->generate('admin_banner_leaderboard_list', ['lang' => $lang]));
            }

            $errors = $this->validateForm($post);
            if (!empty($errors)) {
                return new Response($this->renderer->renderWithAdminLayout('banner/admin/leaderboard/edit', [
                    'pageTitle' => 'Leaderboard',
                    'id' => $id,
                    'post' => array_merge($leaderboard, $post),
                    'errors' => $errors,
                    'lang' => $lang,
                ]));
            }

            try {
                $update = [
                    'active' => !empty($post['active']) ? 1 : 0,
                    'title' => $post['title'] ?? '',
                    'link' => $post['link'] ?? '',
                    'image_alt' => $post['image_alt'] ?? '',
                    'public_from' => $this->formatDatetime($post['public_from'] ?? '', $post['public_from_time'] ?? ''),
                    'public_to' => $this->formatDatetime($post['public_to'] ?? '', $post['public_to_time'] ?? ''),
                    'updated_date' => date('Y-m-d H:i:s'),
                    'updated_user' => $identity->getUserIdentifier(),
                ];

                // Obrázek
                $image = $post['image'] ?? '';
                if ($image === $this->imageDefault) {
                    $update['image'] = null;
                }

                $this->leaderboardRepository->updatePost($id, $update);

                $this->flashMessenger->addMessage(
                    'success',
                    'Banner',
                    'Banner <strong>"' . htmlspecialchars($post['title'] ?? '') . '"</strong> byl upraven'
                );

                // Log
                $this->logger->notice('LEADERBOARD - Edit banner', [
                    'description' => 'OK',
                    'user' => $identity->getUserIdentifier(),
                    'file' => __FILE__,
                ]);

                return new RedirectResponse($this->urlGenerator->generate('admin_banner_leaderboard_list', ['lang' => $lang]));
            } catch (\Exception $e) {
                $this->logger->err('LEADERBOARD - Edit banner', [
                    'description' => 'ERROR',
                    'user' => $identity->getUserIdentifier(),
                    'file' => __FILE__,
                    'trace' => $e->getMessage(),
                ]);

                return new Response($this->renderer->renderWithAdminLayout('banner/admin/leaderboard/edit', [
                    'pageTitle' => 'Leaderboard',
                    'id' => $id,
                    'post' => array_merge($leaderboard, $post),
                    'errors' => ['general' => $e->getMessage()],
                    'lang' => $lang,
                ]));
            }
        }

        return new Response($this->renderer->renderWithAdminLayout('banner/admin/leaderboard/edit', [
            'pageTitle' => 'Leaderboard',
            'id' => $id,
            'post' => $leaderboard,
            'errors' => [],
            'lang' => $lang,
        ]));
    }

    public function deleteBanner(Request $request): JsonResponse
    {
        /** @var AuthorizationUser $identity */
        $identity = $this->security->getUser();
        $success = true;
        $message = null;
        $leaderboard_id = null;

        try {
            $leaderboard_id = $request->request->getInt('id');
            $leaderboard = $this->leaderboardRepository->findPostBy('id', $leaderboard_id);

            if ($leaderboard) {
                // Smazat adresář
                $dir = $this->PUBLIC_PATH . '/data/banner/leaderboard/' . $leaderboard['id'] . '/';
                $this->deleteDir($dir);

                $this->leaderboardRepository->deletePost($leaderboard_id);

                // Log
                $this->logger->notice('LEADERBOARD - Delete banner', [
                    'description' => 'OK',
                    'user' => $identity->getUserIdentifier(),
                    'file' => __FILE__,
                ]);
            } else {
                $success = false;
                $message = 'Cannot find banner';

                // Log
                $this->logger->err('LEADERBOARD - Delete banner', [
                    'description' => 'ERROR',
                    'user' => $identity->getUserIdentifier(),
                    'file' => __FILE__,
                    'trace' => $message,
                ]);
            }
        } catch (\Exception $e) {
            $success = false;
            $message = $e->getMessage();

            // Log
            $this->logger->err('LEADERBOARD - Delete banner', [
                'description' => 'ERROR',
                'user' => $identity->getUserIdentifier(),
                'file' => __FILE__,
                'trace' => $message,
            ]);
        }

        return new JsonResponse([
            'success' => $success,
            'message' => $message,
            'leaderboard_id' => $leaderboard_id,
        ]);
    }

    public function setSort(Request $request): JsonResponse
    {
        /** @var AuthorizationUser $identity */
        $identity = $this->security->getUser();
        $success = true;
        $lang = null;
        $data = null;

        try {
            $lang = $request->request->get('lang');
            $data = $request->request->all('data');

            if ($data) {
                $rank = 1;
                foreach ($data as $item) {
                    $this->leaderboardRepository->updatePost((int) $item['id'], [
                        'rank' => $rank,
                        'updated_date' => date('Y-m-d H:i:s'),
                        'updated_user' => $identity->getUserIdentifier(),
                    ]);
                    ++$rank;
                }
            }
        } catch (\Exception $e) {
            $success = $e->getMessage();
        }

        return new JsonResponse([
            'success' => $success,
            'lang' => $lang,
            'data' => $data,
        ]);
    }

    public function uploadImage(Request $request): JsonResponse
    {
        $file = $request->files->get('file');

        if (!$file) {
            return new JsonResponse(['error' => 'No files found for upload']);
        }

        $leaderboard_id = $request->request->get('leaderboard_id');

        $folder = 'data/banner/leaderboard/';
        if ($leaderboard_id !== 'null' && $leaderboard_id !== null) {
            $folder .= $leaderboard_id . '/';
        } else {
            $folder .= 'tmp/';
        }

        if (!is_dir($this->PUBLIC_PATH . '/' . $folder)) {
            mkdir($this->PUBLIC_PATH . '/' . $folder, 0777, true);
            chmod($this->PUBLIC_PATH . '/' . $folder, 0777);
        }

        $mimeType = $file->getMimeType();

        try {
            $type = match ($mimeType) {
                'image/gif' => 'gif',
                'image/png' => 'png',
                default => 'jpg',
            };

            $filename = 'banner-' . date('YmdHis') . '_' . random_int(100, 999);
            $imageFileName = $folder . $filename . '.' . $type;
            $file->move($this->PUBLIC_PATH . '/' . $folder, $filename . '.' . $type);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()]);
        }

        if ($leaderboard_id !== 'null' && $leaderboard_id !== null) {
            try {
                $leaderboard = $this->leaderboardRepository->findPostBy('id', (int) $leaderboard_id);

                // Smazat bývalý obrázek
                if (!empty($leaderboard['image']) && $leaderboard['image'] !== $this->imageDefault) {
                    @unlink($this->PUBLIC_PATH . '/' . $leaderboard['image']);
                }

                $this->leaderboardRepository->updatePost((int) $leaderboard_id, ['image' => $imageFileName]);
            } catch (\Exception $e) {
                // pokračuj i bez uložení do DB
            }
        }

        return new JsonResponse([
            'name' => $file->getClientOriginalName(),
            'url' => $imageFileName,
            'type' => $mimeType,
        ]);
    }

    public function setDefaultImage(Request $request): JsonResponse
    {
        /** @var AuthorizationUser $identity */
        $identity = $this->security->getUser();
        $success = true;
        $message = null;
        $leaderboard_id = null;
        $field = null;

        try {
            $leaderboard_id = $request->request->getInt('leaderboard_id');
            $field = $request->request->get('field');

            $leaderboard = $this->leaderboardRepository->findPostBy('id', $leaderboard_id);

            if ($leaderboard) {
                if ($field === 'image') {
                    // Smazat bývalý obrázek
                    if (!empty($leaderboard['image']) && $leaderboard['image'] !== $this->imageDefault) {
                        unlink($this->PUBLIC_PATH . '/' . $leaderboard['image']);
                    }

                    $this->leaderboardRepository->updatePost($leaderboard_id, [
                        'image' => null,
                        'updated_date' => date('Y-m-d H:i:s'),
                        'updated_user' => $identity->getUserIdentifier(),
                    ]);
                }

                // Log
                $this->logger->notice('LEADERBOARD - Set banner image', [
                    'description' => 'OK',
                    'user' => $identity->getUserIdentifier(),
                    'file' => __FILE__,
                ]);
            } else {
                $success = false;
                $message = 'Cannot find banner';

                // Log
                $this->logger->err('LEADERBOARD - Set banner image', [
                    'description' => 'ERROR',
                    'user' => $identity->getUserIdentifier(),
                    'file' => __FILE__,
                    'trace' => $message,
                ]);
            }
        } catch (\Exception $e) {
            $success = false;
            $message = $e->getMessage();

            // Log
            $this->logger->err('LEADERBOARD - Set banner image', [
                'description' => 'ERROR',
                'user' => $identity->getUserIdentifier(),
                'file' => __FILE__,
                'trace' => $message,
            ]);
        }

        return new JsonResponse([
            'success' => $success,
            'message' => $message,
            'leaderboard_id' => $leaderboard_id,
            'field' => $field,
            'url' => $this->imageDefault,
        ]);
    }

    private function validateForm(array $post): array
    {
        $errors = [];

        if (empty($post['title'])) {
            $errors['title'] = 'Title je povinný';
        }

        return $errors;
    }

    private function formatDatetime(string $date, string $time): string
    {
        // formát vstupu: d.m.Y a H:i → výstup: Y-m-d H:i:s
        if ($date) {
            try {
                $dt = \DateTime::createFromFormat('d.m.Y', $date);
                if ($dt) {
                    return $dt->format('Y-m-d') . ' ' . ($time ?: '00:00') . ':00';
                }
            } catch (\Exception) {}
        }
        return '';
    }

    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Banner/Controller/Admin/Leaderboard/LeaderboardWriteController.php
git commit -m "feat(banner): LeaderboardWriteController"
```

---

## Task 7: Zbývající List + Write controllery (Rectangle, Square, Mobilesticky, Mobilesquare1, Mobilesquare2)

**Files:**
- Create: `src/Banner/Controller/Admin/Rectangle/RectangleListController.php`
- Create: `src/Banner/Controller/Admin/Rectangle/RectangleWriteController.php`
- Create: `src/Banner/Controller/Admin/Square/SquareListController.php`
- Create: `src/Banner/Controller/Admin/Square/SquareWriteController.php`
- Create: `src/Banner/Controller/Admin/Mobilesticky/MobilestickyListController.php`
- Create: `src/Banner/Controller/Admin/Mobilesticky/MobilestickyWriteController.php`
- Create: `src/Banner/Controller/Admin/Mobilesquare1/Mobilesquare1ListController.php`
- Create: `src/Banner/Controller/Admin/Mobilesquare1/Mobilesquare1WriteController.php`
- Create: `src/Banner/Controller/Admin/Mobilesquare2/Mobilesquare2ListController.php`
- Create: `src/Banner/Controller/Admin/Mobilesquare2/Mobilesquare2WriteController.php`

Každý pár je kopie `LeaderboardListController` / `LeaderboardWriteController` s těmito záměnami (vždy hledat a nahradit ve všech řetězcích i v kódu):

| Typ | Namespace segment | Repository třída | Route prefix | imageDefault cesta | JSON klíč |
|-----|-------------------|------------------|--------------|----------------------|-----------|
| Rectangle | `Rectangle` | `RectangleRepository` | `admin_banner_rectangle_` | `data/banner/rectangle/!default-banner.png` | `rectangle` |
| Square | `Square` | `SquareRepository` | `admin_banner_square_` | `data/banner/square/!default-banner.png` | `square` |
| Mobilesticky | `Mobilesticky` | `MobilestickyRepository` | `admin_banner_mobilesticky_` | `data/banner/mobilesticky/!default-banner.png` | `mobilesticky` |
| Mobilesquare1 | `Mobilesquare1` | `Mobilesquare1Repository` | `admin_banner_mobilesquare1_` | `data/banner/mobilesquare1/!default-banner.png` | `mobilesquare1` |
| Mobilesquare2 | `Mobilesquare2` | `Mobilesquare2Repository` | `admin_banner_mobilesquare2_` | `data/banner/mobilesquare2/!default-banner.png` | `mobilesquare2` |

Také log hlášky: `LEADERBOARD` → `RECTANGLE` / `SQUARE` / atd.

- [ ] **Step 1: Vytvořit RectangleListController + RectangleWriteController** (kopie Leaderboard, záměny z tabulky výše)
- [ ] **Step 2: Vytvořit SquareListController + SquareWriteController**
- [ ] **Step 3: Vytvořit MobilestickyListController + MobilestickyWriteController**
- [ ] **Step 4: Vytvořit Mobilesquare1ListController + Mobilesquare1WriteController**
- [ ] **Step 5: Vytvořit Mobilesquare2ListController + Mobilesquare2WriteController**

- [ ] **Step 6: Commit**

```bash
git add src/Banner/Controller/Admin/
git commit -m "feat(banner): admin controllery pro Rectangle, Square, Mobilesticky, Mobilesquare1, Mobilesquare2"
```

---

## Task 8: Šablony — dashboard widget + changelog

**Files:**
- Create: `templates/banner/admin/dashboard/widget.phtml`
- Create: `templates/banner/admin/changelog.phtml`

Šablony jsou skoro kopie polaru, nahradit jen Laminas-specifická volání ($this->url, $this->translate atd.) za symfony ekvivalenty.

Vzor z polaru: `polar/module/Banner/view/partial/dashboard/widget.phtml` a `polar/module/Banner/view/partial/changelog.phtml`.

- [ ] **Step 1: Vytvořit `templates/banner/admin/dashboard/widget.phtml`**

```php
<?php
/*
 * @project ferritenergy
 * @author Rostislav Greipel
 * @link https://rogr.cz
 * @copyright (c) 2011 - 2021 ROGR All Rights Reserved
 * @license https://rogr.cz/license/eula EULA License
 */

$lang = $locale ?? 'cs_CZ';
?>

<section class="card card-featured-top card-featured-primary mb-4">
    <div class="card-body">
        <div class="widget-summary">
            <div class="widget-summary-col widget-summary-col-icon">
                <div class="summary-icon bg-primary hvr-grow">
                    <i class="fa fa-newspaper"></i>
                </div>
            </div>
            <div class="widget-summary-col">
                <div class="summary">
                    <h4 class="title text-nowrap">
                        <?= $view->trans('Banner') ?>
                    </h4>
                    <div class="info text-nowrap">
                        <strong class="amount">
                            <?= $countBanner ?>
                        </strong>
                        <span class="text-primary">
                            (<?= $countBannerFuture ?> <?= $view->trans('future') ?>)
                        </span>
                    </div>
                </div>
                <div class="summary-footer">
                    <span class="pull-left" data-toggle="tooltip" data-placement="right" title="<?= $view->trans('Version') ?>">
                        0.0.6
                    </span>
                    <a href="<?= $view->path('admin_banner_leaderboard_list', ['lang' => $lang]) ?>" class="text-muted text-uppercase">
                        <i class="fa fa-fw fa-ellipsis-v"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
```

- [ ] **Step 2: Vytvořit `templates/banner/admin/changelog.phtml`**

Zkopírovat obsah z `polar/module/Banner/view/partial/changelog.phtml`, nahradit:
- `$this->translate(...)` → `$view->trans(...)`
- `$this->dateFormat(...)` → plain PHP `(new DateTime(...))->format(...)`
- Odstranit Laminas `$layout = $this->layout(); $locale = $layout->getVariable('locale');` — `$locale` přichází z PhtmlRenderer

- [ ] **Step 3: Commit**

```bash
git add templates/banner/admin/dashboard/ templates/banner/admin/changelog.phtml
git commit -m "feat(banner): dashboard widget + changelog šablony"
```

---

## Task 9: Šablony leaderboard/index.phtml + leaderboard/list.phtml

**Files:**
- Create: `templates/banner/admin/leaderboard/index.phtml`
- Create: `templates/banner/admin/leaderboard/list.phtml`

Polar reference: `polar/module/Banner/view/banner/leaderboard/leaderboard-list/index.phtml` a `list.phtml`.

- [ ] **Step 1: Vytvořit `templates/banner/admin/leaderboard/index.phtml`**

Kopie polar `index.phtml`, nahradit:
- `$this->render('banner/dashboard/widget')` → `$view->include('banner/admin/dashboard/widget', ['countBanner' => $countLeaderboard, 'countBannerFuture' => $countLeaderboardFuture])`
- `$this->url('admin/banner/leaderboard/setting')` → `$view->path('admin_banner_leaderboard_index')` (setting route zatím neexistuje, použít index)
- `$this->translate(...)` → `$view->trans(...)`
- `$this->render('banner/changelog')` → `$view->include('banner/admin/changelog')`
- `Module::VERSION` → `'0.0.6'`

```php
<?php
/*
 * @project ferritenergy
 * @author Rostislav Greipel
 * @link https://rogr.cz
 * @copyright (c) 2011 - 2021 ROGR All Rights Reserved
 * @license https://rogr.cz/license/eula EULA License
 */
?>

<div class="row">
    <div class="col-lg-6">
        <div class="row">
            <div class="col-sm">
                <?= $view->include('banner/admin/dashboard/widget', ['countBanner' => $countLeaderboard, 'countBannerFuture' => $countLeaderboardFuture]) ?>
            </div>
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
                                        <?= $view->trans('Settings') ?>
                                    </h4>
                                    <div class="info text-nowrap">
                                        <strong class="amount">

                                        </strong>
                                        <span class="text-danger">

                                        </span>
                                    </div>
                                </div>
                                <div class="summary-footer">
                                    <a href="<?= $view->path('admin_banner_leaderboard_index') ?>" class="text-muted text-uppercase">
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
                    <i class="fa fa-newspaper"></i>
                </div>
            </header>
            <div class="card-body text-center">
                <h3 class="font-weight-semibold mt-3 text-center">
                    <?= $view->trans('Leaderboard') ?>
                </h3>
                <p class="text-center">
                    0.0.6
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
                    <?= $view->trans('Changelog') ?>
                </h2>
            </header>
            <div class="card-body">
                <?= $view->include('banner/admin/changelog') ?>
            </div>
        </section>
    </div>
</div>
```

- [ ] **Step 2: Vytvořit `templates/banner/admin/leaderboard/list.phtml`**

Kopie polar `list.phtml`. Nahradit:
- `$this->url('admin/banner/leaderboard/list', ['lang' => $item])` → `$view->path('admin_banner_leaderboard_list', ['lang' => $item])`
- `$this->url('admin/banner/leaderboard/list/add', ['lang' => $lang])` → `$view->path('admin_banner_leaderboard_add', ['lang' => $lang])`
- `$this->url('admin/banner/leaderboard/json-list', ['action' => 'get-list'])` → `$view->path('admin_banner_leaderboard_get_list')`
- `$this->url('admin/banner/leaderboard/json-list', ['action' => 'get-banner'])` → `$view->path('admin_banner_leaderboard_get_banner')`
- `$this->url('admin/banner/leaderboard/json-write', ['action' => 'delete-banner'])` → `$view->path('admin_banner_leaderboard_delete')`
- `$this->url('admin/banner/leaderboard/json-write', ['action' => 'set-sort'])` → `$view->path('admin_banner_leaderboard_set_sort')`
- `$this->url('admin/banner/leaderboard/list/edit', ['lang' => $lang])` v optionsFormatter → `$view->path('admin_banner_leaderboard_list', ['lang' => $lang]) . '/upravit'` (nebo přímá URL `/admin/banner/leaderboard/seznam/{lang}/upravit`)
- `$this->translate(...)` → `$view->trans(...)`
- `$this->basePath(...)` → `$view->asset(...)`
- `$identity->getId()` → `$identity->getId()` (funguje stejně)
- `$this->headLink()->appendStylesheet(...)` → `$view->addHeadLink(...)`
- `$this->inlineScript()->appendFile(...)` → `$view->addScript(...)`
- `$this->inlineScript()->appendScript(...)` → `$view->addInlineScript(...)`
- `$this->partial('admin/modal/danger')` → `$view->include('admin/modal/danger')`
- `$this->partial('admin/modal/warning')` → `$view->include('admin/modal/warning')`
- `$locale` pochází z PhtmlRenderer (injektováno automaticky)
- Řádek `$locales = $this->layout()->getVariable('locales');` → `$locales` pochází z PhtmlRenderer (zkontrolovat zda je injektováno, pokud ne, řekneme v kroku)

- [ ] **Step 3: Commit**

```bash
git add templates/banner/admin/leaderboard/index.phtml templates/banner/admin/leaderboard/list.phtml
git commit -m "feat(banner): leaderboard index + list šablony"
```

---

## Task 10: Šablony leaderboard/add.phtml, edit.phtml, bannerForm.phtml

**Files:**
- Create: `templates/banner/admin/leaderboard/add.phtml`
- Create: `templates/banner/admin/leaderboard/edit.phtml`
- Create: `templates/banner/admin/leaderboard/bannerForm.phtml`

Polar reference: `polar/module/Banner/view/banner/leaderboard/leaderboard-write/add.phtml`, `edit.phtml`, `leaderboardForm.php`.

V symfony nemáme Laminas `Form` objekt — formulář renderujeme přímo v phtml ze `$post` a `$errors` (stejný vzor jako `userForm.phtml`).

- [ ] **Step 1: Vytvořit `templates/banner/admin/leaderboard/add.phtml`**

```php
<?php
/*
 * @project ferritenergy
 * @author Rostislav Greipel
 * @link https://rogr.cz
 * @copyright (c) 2011 - 2021 ROGR All Rights Reserved
 * @license https://rogr.cz/license/eula EULA License
 */

$view->addHeadLink('<link rel="stylesheet" href="' . $view->asset('vendor/admin/file-upload/css/file-upload.css') . '">');

$view->addScript($view->asset('vendor/admin/file-upload/js/ui-widget.js'));
$view->addScript($view->asset('vendor/admin/file-upload/js/iframe-transport.js'));
$view->addScript($view->asset('vendor/admin/file-upload/js/file-upload.js'));

$image = $post['image'] ?? 'data/banner/leaderboard/!default-banner.png';
$imageDefault = 'data/banner/leaderboard/!default-banner.png';
?>

<div class="row">
    <div class="col-lg-3">
        <div id="image">
            <h3 class="mt-0">
                <?= $view->trans('Image') ?>
                <small class="pull-right text-muted">
                    (970px x 200px)
                </small>
            </h3>
            <div class="mb-4">
                <div class="image-outer-container d-inline-flex">
                    <div class="image-inner-container bg-primary">
                        <img class="img-fluid border-color-<?= $scheme ?>" src="<?= $view->asset($image) . '?ver=' . date('YmdHis') ?>" alt="">
                        <span class="image-button bg-<?= $schemeOpposite ?>">
                            <i class="fa fa-camera text-color-<?= $scheme ?>"></i>
                        </span>
                    </div>
                    <input id="fileupload" class="image-input" type="file" name="file" data-url="<?= $view->path('admin_banner_leaderboard_upload_image') ?>" accept="image/*">
                </div>
            </div>
            <div class="progress d-none">
                <div class="progress-bar progress-bar-primary" role="progressbar" style="width: 0">
                    <span class="sr-only">0%</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-9">
        <section class="card card-primary">
            <header class="card-header">
                <div class="card-actions">
                    <span id="msgNotSaved" class="badge badge-danger d-none">
                        <?= $view->trans('Unsaved') ?>
                    </span>
                </div>
                <h2 class="card-title">
                    <?= $view->trans('Add banner') ?>
                </h2>
            </header>
            <?= $view->include('banner/admin/leaderboard/bannerForm', ['post', 'errors', 'lang', 'identity', 'isEdit' => false]) ?>
        </section>
    </div>
</div>
```

- [ ] **Step 2: Vytvořit `templates/banner/admin/leaderboard/edit.phtml`**

Kopie add.phtml se záměnami:
- `leaderboard_id: null` → `leaderboard_id: <?= $post['id'] ?? 0 ?>`
- upload-image url: přidat parametr (nebo ponechat stejně, upload controller si najde ID z requestu)
- nadpis: `'Add banner'` → `'Edit banner'`
- `'isEdit' => false` → `'isEdit' => true`
- Přidat JS pro nastavení obrázku z `$post['image']` pokud existuje:

```php
$img = $view->asset($imageDefault) . '?ver=' . date('YmdHis');
if (!empty($post['image']) && $post['image'] !== $imageDefault) {
    $img = $view->asset($post['image']);
}
```
A `src="<?= $img ?>"` na img tagu.

- [ ] **Step 3: Vytvořit `templates/banner/admin/leaderboard/bannerForm.phtml`**

Vzor z polar `leaderboardForm.php` + vzor z `userForm.phtml`. Formulář renderován z `$post`, chyby z `$errors`.

```php
<?php
/*
 * @project ferritenergy
 * @author Rostislav Greipel
 * @link https://rogr.cz
 * @copyright (c) 2011 - 2021 ROGR All Rights Reserved
 * @license https://rogr.cz/license/eula EULA License
 */

$view->addHeadLink('<link rel="stylesheet" href="' . $view->asset('vendor/admin/bootstrap-timepicker/css/bootstrap-timepicker.min.css') . '">');

$view->addScript($view->asset('vendor/admin/bootstrap-maxlength/js/bootstrap-maxlength.min.js'));
$view->addScript($view->asset('vendor/admin/bootstrap-timepicker/bootstrap-timepicker.js'));
$view->addScript($view->asset('vendor/admin/ios7-switch/js/ios7-switch.min.js'));

$imageDefault = 'data/banner/leaderboard/!default-banner.png';
$image = $post['image'] ?? $imageDefault;

$action = $isEdit
    ? $view->path('admin_banner_leaderboard_edit', ['lang' => $lang, 'id' => $post['id'] ?? 0])
    : $view->path('admin_banner_leaderboard_add', ['lang' => $lang]);
?>

<form action="<?= $action ?>" method="post" id="frmBanner" class="form-horizontal" novalidate>
    <input type="hidden" name="id" value="<?= htmlspecialchars((string)($post['id'] ?? '')) ?>">
    <input type="hidden" name="lang" value="<?= htmlspecialchars($lang) ?>">
    <input type="hidden" name="image" value="<?= htmlspecialchars($image) ?>">
    <input type="hidden" name="created_date" value="<?= htmlspecialchars((string)($post['created_date'] ?? '')) ?>">
    <input type="hidden" name="updated_date" value="<?= htmlspecialchars((string)($post['updated_date'] ?? '')) ?>">
    <input type="hidden" name="created_user" value="<?= htmlspecialchars((string)($post['created_user'] ?? '')) ?>">
    <input type="hidden" name="updated_user" value="<?= htmlspecialchars((string)($post['updated_user'] ?? '')) ?>">
    <div class="card-body">
        <div class="form-group row">
            <label class="col-sm-3 control-label text-sm-right pt-2"><?= $view->trans('Active') ?></label>
            <div class="col-sm-3<?= isset($errors['active']) ? ' has-danger' : '' ?>">
                <div class="switch switch-primary">
                    <input type="checkbox" name="active" id="active" data-plugin-ios-switch<?= !empty($post['active']) ? ' checked' : '' ?>>
                    <label for="active"></label>
                </div>
                <?php if (isset($errors['active'])): ?>
                    <small class="form-text text-danger"><?= htmlspecialchars($errors['active']) ?></small>
                <?php endif ?>
            </div>
        </div>
        <div class="form-group row">
            <label class="col-sm-3 control-label text-sm-right pt-2 required" for="title"><?= $view->trans('Title') ?></label>
            <div class="col-sm-9<?= isset($errors['title']) ? ' has-danger' : '' ?>">
                <input type="text" name="title" id="title" class="form-control" placeholder="required" required maxlength="255" data-plugin-maxlength value="<?= htmlspecialchars((string)($post['title'] ?? '')) ?>">
                <?php if (isset($errors['title'])): ?>
                    <small class="form-text text-danger"><?= htmlspecialchars($errors['title']) ?></small>
                <?php endif ?>
            </div>
        </div>
        <div class="form-group row">
            <label class="col-sm-3 control-label text-sm-right pt-2" for="link"><?= $view->trans('Link') ?></label>
            <div class="col-sm-9<?= isset($errors['link']) ? ' has-danger' : '' ?>">
                <input type="text" name="link" id="link" class="form-control" placeholder="optional" maxlength="255" data-plugin-maxlength value="<?= htmlspecialchars((string)($post['link'] ?? '')) ?>">
                <?php if (isset($errors['link'])): ?>
                    <small class="form-text text-danger"><?= htmlspecialchars($errors['link']) ?></small>
                <?php endif ?>
            </div>
        </div>
        <!--<div class="form-group row">
            <label class="col-sm-3 control-label text-sm-right pt-2" for="image_alt"><?php //= $view->trans('Image ALT') ?></label>
            <div class="col-sm-9<?php //= isset($errors['image_alt']) ? ' has-danger' : '' ?>">
                <input type="text" name="image_alt" id="image_alt" class="form-control" placeholder="optional" maxlength="255" data-plugin-maxlength value="<?php //= htmlspecialchars((string)($post['image_alt'] ?? '')) ?>">
            </div>
        </div>-->
        <div class="form-group row">
            <label class="col-sm-3 control-label text-sm-right pt-2" for="public_from"><?= $view->trans('Public from') ?></label>
            <div class="col-10 col-md-5 col-xl-2<?= isset($errors['public_from']) ? ' has-danger' : '' ?>">
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><?= $view->trans('Date') ?></span>
                    </div>
                    <input type="text" name="public_from" id="public_from" class="form-control" placeholder="optional" data-plugin-datepicker value="<?= htmlspecialchars((string)($post['public_from'] ?? '')) ?>">
                </div>
                <?php if (isset($errors['public_from'])): ?>
                    <small class="form-text text-danger"><?= htmlspecialchars($errors['public_from']) ?></small>
                <?php endif ?>
            </div>
            <div class="col-10 col-md-4 col-xl-2<?= isset($errors['public_from_time']) ? ' has-danger' : '' ?>">
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><?= $view->trans('Time') ?></span>
                    </div>
                    <input type="text" name="public_from_time" id="public_from_time" class="form-control inputValidation" placeholder="optional" data-plugin-timepicker value="<?= htmlspecialchars((string)($post['public_from_time'] ?? '')) ?>">
                </div>
                <?php if (isset($errors['public_from_time'])): ?>
                    <small class="form-text text-danger"><?= htmlspecialchars($errors['public_from_time']) ?></small>
                <?php endif ?>
            </div>
        </div>
        <div class="form-group row">
            <label class="col-sm-3 control-label text-sm-right pt-2" for="public_to"><?= $view->trans('Public to') ?></label>
            <div class="col-10 col-md-4 col-xl-2<?= isset($errors['public_to']) ? ' has-danger' : '' ?>">
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><?= $view->trans('Date') ?></span>
                    </div>
                    <input type="text" name="public_to" id="public_to" class="form-control" placeholder="optional" data-plugin-datepicker value="<?= htmlspecialchars((string)($post['public_to'] ?? '')) ?>">
                </div>
                <?php if (isset($errors['public_to'])): ?>
                    <small class="form-text text-danger"><?= htmlspecialchars($errors['public_to']) ?></small>
                <?php endif ?>
            </div>
            <div class="col-10 col-md-4 col-xl-2<?= isset($errors['public_to_time']) ? ' has-danger' : '' ?>">
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><?= $view->trans('Time') ?></span>
                    </div>
                    <input type="text" name="public_to_time" id="public_to_time" class="form-control inputValidation" placeholder="optional" data-plugin-timepicker value="<?= htmlspecialchars((string)($post['public_to_time'] ?? '')) ?>">
                </div>
                <?php if (isset($errors['public_to_time'])): ?>
                    <small class="form-text text-danger"><?= htmlspecialchars($errors['public_to_time']) ?></small>
                <?php endif ?>
            </div>
        </div>
    </div>
    <footer class="card-footer text-right">
        <button type="submit" class="btn btn-primary"><?= $view->trans('Save') ?></button>
        <button type="submit" name="cancel" class="btn btn-default" formnovalidate><?= $view->trans('Cancel') ?></button>
    </footer>
</form>
```

- [ ] **Step 4: Commit**

```bash
git add templates/banner/admin/leaderboard/
git commit -m "feat(banner): leaderboard add + edit + bannerForm šablony"
```

---

## Task 11: Šablony pro Rectangle, Square, Mobilesticky, Mobilesquare1, Mobilesquare2

**Files:** Pro každý typ 5 souborů (index, list, add, edit, bannerForm) v `templates/banner/admin/{type}/`

Polar reference: `polar/module/Banner/view/banner/{type}/...`

Každý set šablon je kopie leaderboard šablon se záměnami:
- `leaderboard` → název typu (rectangle / square / atd.)
- `Leaderboard` → název v PascalCase
- `LEADERBOARD` → název velkými písmeny
- route prefix: `admin_banner_leaderboard_` → `admin_banner_rectangle_` atd.
- cesty obrázků: `data/banner/leaderboard/` → `data/banner/rectangle/` atd.
- nadpisy: `'Leaderboard'` → název banneru (Rectangle, Square, Mobilesticky, Mobilesquare 1, Mobilesquare 2)

- [ ] **Step 1: Rectangle** — 5 šablon
- [ ] **Step 2: Square** — 5 šablon
- [ ] **Step 3: Mobilesticky** — 5 šablon
- [ ] **Step 4: Mobilesquare1** — 5 šablon
- [ ] **Step 5: Mobilesquare2** — 5 šablon

- [ ] **Step 6: Commit**

```bash
git add templates/banner/admin/
git commit -m "feat(banner): admin šablony pro Rectangle, Square, Mobilesticky, Mobilesquare1, Mobilesquare2"
```

---

## Task 12: BannerGlobalListener — doplnit globální proměnné pro web bannery

**Files:**
- Modify: `src/Banner/EventListener/BannerGlobalListener.php`

Polar `WebListController` injektuje do layoutu: `rectangle`, `square`, `mobilesquare1`, `mobilesquare2`. Aktuálně Symfony listener injektuje jen `leaderboard` + `mobilesticky`.

- [ ] **Step 1: Doplnit Rectangle, Square, Mobilesquare1, Mobilesquare2 repository do listeneru**

```php
<?php

namespace App\Banner\EventListener;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use App\Application\View\PhtmlRenderer;
use App\Banner\Repository\BannerRepository;
use App\Banner\Repository\RectangleRepository;
use App\Banner\Repository\SquareRepository;
use App\Banner\Repository\Mobilesquare1Repository;
use App\Banner\Repository\Mobilesquare2Repository;

class BannerGlobalListener
{
    public function __construct(
        private BannerRepository $bannerRepository,
        private RectangleRepository $rectangleRepository,
        private SquareRepository $squareRepository,
        private Mobilesquare1Repository $mobilesquare1Repository,
        private Mobilesquare2Repository $mobilesquare2Repository,
        private PhtmlRenderer $phtmlRenderer
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        $this->phtmlRenderer->addGlobal('bannerLeaderboard', $this->bannerRepository->getLeaderboard());
        $this->phtmlRenderer->addGlobal('bannerMobilesticky', $this->bannerRepository->getMobilesticky());
        $this->phtmlRenderer->addGlobal('bannerRectangle', $this->rectangleRepository->getBannerForWeb());
        $this->phtmlRenderer->addGlobal('bannerSquare', $this->squareRepository->getBannerForWeb());
        $this->phtmlRenderer->addGlobal('bannerMobilesquare1', $this->mobilesquare1Repository->getBannerForWeb());
        $this->phtmlRenderer->addGlobal('bannerMobilesquare2', $this->mobilesquare2Repository->getBannerForWeb());
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Banner/EventListener/BannerGlobalListener.php
git commit -m "feat(banner): BannerGlobalListener – doplnit web bannery (rectangle, square, mobilesquare1, mobilesquare2)"
```

---

## Task 13: Ověření a finální test

- [ ] **Step 1: Zkontrolovat, že symfony container se zkompiluje bez chyb**

```bash
php bin/console cache:clear
```
Expected: `Cache for the "dev" environment (debug=true) was successfully cleared.`

- [ ] **Step 2: Zkontrolovat, že routes jsou správně registrovány**

```bash
php bin/console debug:router | findstr banner
```
Expected: viditelné všechny `admin_banner_*` routes

- [ ] **Step 3: Zkontrolovat, že services jsou autowired**

```bash
php bin/console debug:container | findstr Banner
```
Expected: všechny repository třídy viditelné

- [ ] **Step 4: Commit**

```bash
git add .
git commit -m "feat(banner): Banner modul kompletní migrace"
```

---

## Poznámky k migraci

1. **`$locales`** — v polar `list.phtml` se používá `$this->layout()->getVariable('locales')`. V Symfony musí PhtmlRenderer tuto proměnnou injektovat globálně. Zkontrolovat zda `$locales` je dostupné v šablonách — pokud ne, přidat do listeneru nebo přímo z DI.

2. **`$identity->getId()`** — polar list šablony používají `$identity->getId()` jako součást cookie ID tabulky. `AuthorizationUser` musí mít metodu `getId()`. Ověřit.

3. **Datepicker hodnoty pro edit** — polar `editAction` přidával JS snippet pro nastavení hodnot datepickeru. V symfony to řeší přímo PHP v šabloně: hodnoty se zobrazují z `$post['public_from']` atd. Formát z DB je `Y-m-d`, ale datepicker čeká `d.m.Y` — zajistit správnou konverzi v controlleru nebo šabloně.

4. **uploadImage** — po `$file->move()` se `$file` stane neplatným pro `getClientOriginalName()`. Uložit `$originalName` před voláním `move()`.

5. **Web partial šablony** (`templates/banner/partial/ads/`) — tyto soubory z velké části existují, ale nebyly systematicky ověřeny vůči polaru. Po dokončení admin části projít každý soubor.
