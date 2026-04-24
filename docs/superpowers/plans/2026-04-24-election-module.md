# Election Module – Implementation Plan

> **For agentic workers:** REQUIRED: Use the `subagent-driven-development` agent (recommended) or `executing-plans` agent to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Migrovat modul Election (volby 2025, 2024, 2020 + admin správa) z Laminas do Symfony 1:1.

**Architecture:** Modulární struktura `src/Election/`, routy v `config/routes/election.yaml`, šablony v `templates/election/`. Žádné ORM entity – vše vrací plain array. Playkit data (volební výsledky) jsou v separátní DB připojení `playkit_connection`.

**Tech Stack:** Symfony 8, PHP 8.5, Doctrine DBAL QueryBuilder, phtml šablony, PhtmlRenderer

---

## Mapování souborů

**Routy:**
- Create: `config/routes/election.yaml`
- Modify: `config/routes.yaml` (přidat import)

**Repositories (src/Election/Repository/):**
- Create: `ElectionRepository2025.php` – main DB, tabulka `elections_2025`, read + write
- Create: `ElectionCommand2025.php` – main DB, zápis/delete do `elections_2025`
- Create: `Election2025PlaykitRepository.php` – playkit DB, tabulky `polar_electionsps2025_*`
- Create: `ElectionRepository2024.php` – main DB, tabulka `elections_2024`, jen read
- Create: `Election2024PlaykitRepository.php` – playkit DB, tabulky `polar_electionskz2024_*`
- Create: `Election2020PlaykitRepository.php` – playkit DB, tabulky `polar_elections*2020_*`
- Create: `ElectionSettingRepository.php` – main DB, tabulka `elections_settings` (stub)
- Create: `ElectionSettingCommand.php` – main DB, stub

**Controllers (src/Election/Controller/):**
- Create: `Web/Web2025Controller.php` – metody: index, detail, obec, poslanci, kresla, senat
- Create: `Web/Web2024Controller.php` – metody: index, detail, obec, zastupitele, kresla, senat
- Create: `Web/Web2020Controller.php` – metody: index (redirect na kresla), kresla
- Create: `Admin/ElectionListController.php` – index, list, getList, getElection
- Create: `Admin/ElectionWriteController.php` – add, edit, deleteElection, setOrder
- Create: `Admin/SettingWriteController.php` – setting

**Templates (templates/election/):**
- Create: `web2025/index.phtml`, `detail.phtml`, `kresla.phtml`, `poslanci.phtml`, `obec.phtml`, `senat.phtml`
- Create: `web2024/index.phtml`, `detail.phtml`, `kresla.phtml`, `zastupitele.phtml`, `obec.phtml`, `senat.phtml`
- Create: `web2020/kresla.phtml`
- Create: `admin/index.phtml`, `list.phtml`, `add.phtml`, `edit.phtml`, `setting.phtml`

**Config:**
- Modify: `config/services.yaml` – registrace Election controllerů a repositories

---

## Obecné adaptační pravidlo (Laminas → Symfony)

Platí pro všechny šablony a controllery:

| Laminas | Symfony |
|---|---|
| `$this->foo` | `$foo` (z params) |
| `$this->basePath('...')` | `$view->asset('...')` |
| `$this->url('route/name', ['param' => val])` | `$view->path('route_name', ['param' => val])` |
| `$this->translate('X')` | `$view->trans('X')` |
| `$this->partial('partial/file', $data)` | `$view->include('partial/file', $data)` |
| `$this->params()->fromRoute('x')` | `$request->attributes->get('x')` |
| `$this->params()->fromQuery('x', def)` | `$request->query->get('x', def)` |
| `$this->params()->fromPost()` | `$request->request->all()` |
| `$this->redirect()->toRoute('r')` | `new RedirectResponse($this->urlGenerator->generate('r'))` |
| `new ViewModel(['k' => $v])` | `new Response($this->renderer->render('tmpl', ['k' => $v]))` |
| `new JsonModel([...])` | `new JsonResponse([...])` |
| `$this->flashMessenger->addMessage('success')` | `$request->getSession()->getFlashBag()->add('success', 'msg')` |
| `$studio->getVideoId()` | `$studio['video_id']` |
| `$election->getArrayCopy()` | přímý array z DB |
| Banner forward dispatch | `$this->bannerRepository->getLeaderboard()` + `getMobilesticky()` |

---

## Task 1: Routes + config import

**Files:**
- Create: `config/routes/election.yaml`
- Modify: `config/routes.yaml`

- [ ] **Step 1: Vytvořit `config/routes/election.yaml`**

```yaml
# Web — Volby 2025
election_2025:
  path: /volby
  controller: App\Election\Controller\Web\Web2025Controller::index

election_2025_detail:
  path: /volby/strana/{kstrana}/{strana_url}
  controller: App\Election\Controller\Web\Web2025Controller::detail
  requirements:
    kstrana: '[0-9]+'
    strana_url: '[a-zA-Z][a-zA-Z0-9_-]+'

election_2025_poslanci:
  path: /volby/poslanci
  controller: App\Election\Controller\Web\Web2025Controller::poslanci

election_2025_kresla:
  path: /volby/kresla
  controller: App\Election\Controller\Web\Web2025Controller::kresla

election_2025_obec:
  path: /volby/okres/{okres}
  controller: App\Election\Controller\Web\Web2025Controller::obec
  defaults:
    obec: null
  requirements:
    okres: '[0-9]+'

election_2025_obec_detail:
  path: /volby/okres/{okres}/obec/{obec}
  controller: App\Election\Controller\Web\Web2025Controller::obec
  requirements:
    okres: '[0-9]+'
    obec: '[0-9]+'

election_2025_senat:
  path: /volby/senat
  controller: App\Election\Controller\Web\Web2025Controller::senat

# Web — Volby 2024
election_2024:
  path: /volby-2024
  controller: App\Election\Controller\Web\Web2024Controller::index

election_2024_detail:
  path: /volby-2024/strana/{kstrana}/{strana_url}
  controller: App\Election\Controller\Web\Web2024Controller::detail
  requirements:
    kstrana: '[0-9]+'
    strana_url: '[a-zA-Z][a-zA-Z0-9_-]+'

election_2024_zastupitele:
  path: /volby-2024/zastupitele
  controller: App\Election\Controller\Web\Web2024Controller::zastupitele

election_2024_kresla:
  path: /volby-2024/kresla
  controller: App\Election\Controller\Web\Web2024Controller::kresla

election_2024_obec:
  path: /volby-2024/okres/{okres}
  controller: App\Election\Controller\Web\Web2024Controller::obec
  defaults:
    obec: null
  requirements:
    okres: '[0-9]+'

election_2024_obec_detail:
  path: /volby-2024/okres/{okres}/obec/{obec}
  controller: App\Election\Controller\Web\Web2024Controller::obec
  requirements:
    okres: '[0-9]+'
    obec: '[0-9]+'

election_2024_senat:
  path: /volby-2024/senat
  controller: App\Election\Controller\Web\Web2024Controller::senat

# Web — Volby 2020
election_2020_kresla:
  path: /volby-2020/kresla
  controller: App\Election\Controller\Web\Web2020Controller::kresla

election_2020:
  path: /volby-2020
  controller: App\Election\Controller\Web\Web2020Controller::index

# Admin — Elections 2025
admin_election:
  path: /admin/volby
  controller: App\Election\Controller\Admin\ElectionListController::index

admin_election_list:
  path: /admin/volby/seznam
  controller: App\Election\Controller\Admin\ElectionListController::list

admin_election_add:
  path: /admin/volby/seznam/pridat
  controller: App\Election\Controller\Admin\ElectionWriteController::add
  methods: [GET, POST]

admin_election_edit:
  path: /admin/volby/seznam/upravit/{id}
  controller: App\Election\Controller\Admin\ElectionWriteController::edit
  methods: [GET, POST]
  requirements:
    id: '\d+'

admin_election_json_list_get_list:
  path: /admin/volby/json-list/get-list
  controller: App\Election\Controller\Admin\ElectionListController::getList

admin_election_json_list_get_election:
  path: /admin/volby/json-list/get-election
  controller: App\Election\Controller\Admin\ElectionListController::getElection
  methods: [POST]

admin_election_json_write_delete_election:
  path: /admin/volby/json-write/delete-election
  controller: App\Election\Controller\Admin\ElectionWriteController::deleteElection
  methods: [POST]

admin_election_json_write_set_order:
  path: /admin/volby/json-write/set-order
  controller: App\Election\Controller\Admin\ElectionWriteController::setOrder
  methods: [POST]

admin_election_setting:
  path: /admin/volby/nastaveni
  controller: App\Election\Controller\Admin\SettingWriteController::setting
  methods: [GET, POST]

admin_election_setting_json_list:
  path: /admin/volby/nastaveni/json-list/{action}
  controller: App\Election\Controller\Admin\SettingWriteController::jsonList
  requirements:
    action: '[a-zA-Z][a-zA-Z0-9_-]+'

admin_election_setting_json_write:
  path: /admin/volby/nastaveni/json-write/{action}
  controller: App\Election\Controller\Admin\SettingWriteController::jsonWrite
  requirements:
    action: '[a-zA-Z][a-zA-Z0-9_-]+'
```

- [ ] **Step 2: Přidat import do `config/routes.yaml`**

Přidat za poslední `resource:` řádek:
```yaml
election:
  resource: routes/election.yaml
```

- [ ] **Step 3: Ověřit syntax**

```bash
php bin/console debug:router 2>&1 | Select-String "election"
```

Očekáváno: výpis s ~25 election routami.

- [ ] **Step 4: Commit**

```bash
git add config/routes/election.yaml config/routes.yaml
git commit -m "feat: election routes"
```

---

## Task 2: ElectionRepository2025

**Files:**
- Create: `src/Election/Repository/ElectionRepository2025.php`

Referenční soubor: `polar/module/Election/src/Model/Election2025/MariaDbSqlRepository.php`

- [ ] **Step 1: Vytvořit soubor**

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

namespace App\Election\Repository;

use Doctrine\DBAL\Connection;

final class ElectionRepository2025
{
    private string $table = 'elections_2025';

    public function __construct(
        private Connection $connection,
    ) {}

    /**
     * @return array|null
     */
    public function fetchAll(): ?array
    {
        return $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->table)
            ->orderBy('rank', 'ASC')
            ->fetchAllAssociative() ?: null;
    }

    /**
     * @param array $params
     * @return array|null
     */
    public function fetchForBootstrapTable(array $params): ?array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('e.id', 'e.title', 'e.description', 'e.video_id', 'e.rank', 'pv.name')
            ->from($this->table, 'e')
            ->leftJoin('e', 'program_videos', 'pv', 'pv.id = e.video_id')
            ->orderBy($params['sort'] ?? 'rank', $params['order'] ?? 'ASC');

        if (!empty($params['limit'])) {
            $qb->setMaxResults((int) $params['limit']);
        }
        if (!empty($params['offset'])) {
            $qb->setFirstResult((int) $params['offset']);
        }
        if (!empty($params['search'])) {
            $qb->andWhere('MATCH (e.title) AGAINST (:search IN BOOLEAN MODE)')
               ->setParameter('search', $params['search'] . '*');
        }

        $rows = $qb->fetchAllAssociative();
        foreach ($rows as &$row) {
            $row['id']   = (int) $row['id'];
            $row['rank'] = (int) $row['rank'];
        }
        return $rows ?: null;
    }

    /**
     * @param string $column
     * @param int|string $value
     * @return array
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    public function findPostBy(string $column, int|string $value): array
    {
        $row = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->table)
            ->where($column . ' = :value')
            ->setParameter('value', $value)
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
     * @return int
     */
    public function getCount(): int
    {
        $result = $this->connection->createQueryBuilder()
            ->select('COUNT(*) AS count')
            ->from($this->table)
            ->fetchAssociative();
        return (int) ($result['count'] ?? 0);
    }

    /**
     * @param array $params
     * @return int
     */
    public function getCountForBootstrapTable(array $params): int
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('COUNT(*) AS count')
            ->from($this->table);

        if (!empty($params['search'])) {
            $qb->andWhere('MATCH (title, description) AGAINST (:search IN BOOLEAN MODE)')
               ->setParameter('search', $params['search'] . '*');
        }

        $result = $qb->fetchAssociative();
        return (int) ($result['count'] ?? 0);
    }

    /**
     * @param int $limit
     * @return array|null
     */
    public function fetchAllLimit(int $limit): ?array
    {
        return $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->table)
            ->orderBy('rank', 'ASC')
            ->setMaxResults($limit)
            ->fetchAllAssociative() ?: null;
    }

    /**
     * @return array|null
     */
    public function fetchAllForWeb(): ?array
    {
        return $this->connection->createQueryBuilder()
            ->select('e.*', 'pv.name', 'pv.duration', 'p.url AS program_url', 'ps.url')
            ->from($this->table, 'e')
            ->innerJoin('e', 'program_videos', 'pv', 'pv.id = e.video_id')
            ->innerJoin('pv', 'program', 'p', 'p.video_id = pv.id')
            ->innerJoin('p', 'program2shows', 'p2s', 'p2s.program_id = p.id')
            ->innerJoin('p2s', 'program_shows', 'ps', 'ps.id = p2s.show_id')
            ->where('p.premiere = 1')
            ->andWhere('p.time < NOW()')
            ->orderBy('e.rank', 'ASC')
            ->groupBy('e.id')
            ->fetchAllAssociative() ?: null;
    }

    /**
     * @param int $video_id
     * @return array|null
     */
    public function fetchStudioForWeb(int $video_id): ?array
    {
        $row = $this->connection->createQueryBuilder()
            ->select('e.*', 'pv.id AS pv_id', 'pv.name', 'pv.path')
            ->from($this->table, 'e')
            ->innerJoin('e', 'program_videos', 'pv', 'pv.id = e.video_id')
            ->where('e.video_id = :video_id')
            ->setParameter('video_id', $video_id)
            ->fetchAssociative();
        return $row ?: null;
    }
}
```

- [ ] **Step 2: Ověřit syntax**

```bash
php -l src/Election/Repository/ElectionRepository2025.php
```

- [ ] **Step 3: Commit**

```bash
git add src/Election/Repository/ElectionRepository2025.php
git commit -m "feat: ElectionRepository2025"
```

---

## Task 3: ElectionCommand2025

**Files:**
- Create: `src/Election/Repository/ElectionCommand2025.php`

Referenční soubor: `polar/module/Election/src/Model/Election2025/MariaDbSqlCommand.php`

- [ ] **Step 1: Vytvořit soubor**

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

namespace App\Election\Repository;

use Doctrine\DBAL\Connection;

final class ElectionCommand2025
{
    private string $table = 'elections_2025';

    public function __construct(
        private Connection $connection,
    ) {}

    /**
     * @param array $data ['title', 'description', 'video_id', 'rank']
     * @return int  inserted id
     * @throws \RuntimeException
     */
    public function insertPost(array $data): int
    {
        $this->connection->insert($this->table, [
            'title'       => $data['title'],
            'description' => $data['description'],
            'video_id'    => $data['video_id'] ?: null,
            'rank'        => $data['rank'] ?? 0,
        ]);

        $id = (int) $this->connection->lastInsertId();
        if (!$id) {
            throw new \RuntimeException('Během operace "Insert" došlo k chybě databáze. Tabulka "' . $this->table . '".');
        }
        return $id;
    }

    /**
     * @param array $data musí obsahovat 'id'
     * @throws \RuntimeException
     */
    public function updatePost(array $data): void
    {
        if (empty($data['id'])) {
            throw new \RuntimeException('Záznam nelze upravit. Chybí identifikátor. Tabulka "' . $this->table . '".');
        }

        $this->connection->update($this->table, [
            'title'       => $data['title'],
            'description' => $data['description'],
            'video_id'    => $data['video_id'] ?: null,
            'rank'        => $data['rank'] ?? 0,
        ], ['id' => (int) $data['id']]);
    }

    /**
     * @param array $data musí obsahovat 'id'
     * @throws \RuntimeException
     */
    public function deletePost(array $data): void
    {
        if (empty($data['id'])) {
            throw new \RuntimeException('Záznam nelze smazat. Chybí identifikátor. Tabulka "' . $this->table . '".');
        }

        $this->connection->delete($this->table, ['id' => (int) $data['id']]);
    }

    /**
     * Přenastaví rank – volá se po smazání záznamu
     * @param array $elections  pole arrays, každý musí mít 'id'
     */
    public function reorderAll(array $elections): void
    {
        $rank = 1;
        foreach ($elections as $election) {
            $this->connection->update($this->table, ['rank' => $rank], ['id' => (int) $election['id']]);
            $rank++;
        }
    }
}
```

- [ ] **Step 2: Ověřit syntax**

```bash
php -l src/Election/Repository/ElectionCommand2025.php
```

- [ ] **Step 3: Commit**

```bash
git add src/Election/Repository/ElectionCommand2025.php
git commit -m "feat: ElectionCommand2025"
```

---

## Task 4: Election2025PlaykitRepository

**Files:**
- Create: `src/Election/Repository/Election2025PlaykitRepository.php`

Referenční soubor: `polar/module/Election/src/Model/Election2025/Playkit/MariaDbSqlRepository.php`

- [ ] **Step 1: Vytvořit soubor**

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

namespace App\Election\Repository;

use Doctrine\DBAL\Connection;

final class Election2025PlaykitRepository
{
    public function __construct(
        private Connection $connection,
    ) {}

    /**
     * @return array|null
     */
    public function fetchPsrklAll(): ?array
    {
        return $this->connection->createQueryBuilder()
            ->select('*')
            ->from('polar_electionsps2025_psrkl')
            ->orderBy('LOWER(ZKRATKAK8) COLLATE utf8_czech_ci', 'ASC')
            ->fetchAllAssociative() ?: null;
    }

    /**
     * @param string $column
     * @param int|string $value
     * @return array|null
     */
    public function findPsrklPostBy(string $column, int|string $value): ?array
    {
        $row = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('polar_electionsps2025_psrkl')
            ->where($column . ' = :value')
            ->setParameter('value', $value)
            ->fetchAssociative();
        return $row ?: null;
    }

    /**
     * @param int $kstrana
     * @return array|null
     */
    public function getPsrkByKstrana(int $kstrana): ?array
    {
        $election = $this->connection->createQueryBuilder()
            ->select('p.KSTRANA', 'p.NAZEVCELK', 'p.NAZEV_STRK', 'p.ZKRATKAK8',
                     'r.HLASY', 'r.PROC_HLASU', 'r.MANDATY')
            ->from('polar_electionsps2025_psrkl', 'p')
            ->leftJoin('p', 'polar_electionsps2025_results', 'r', 'r.KSTRANA = p.KSTRANA')
            ->where('p.KSTRANA = :kstrana')
            ->setParameter('kstrana', $kstrana)
            ->orderBy('r.HLASY', 'DESC')
            ->groupBy('p.id')
            ->fetchAssociative();

        if (!$election) {
            return null;
        }

        $psrk = $this->connection->createQueryBuilder()
            ->select('k.PORCISLO', 'k.JMENO', 'k.PRIJMENI', 'k.TITULPRED', 'k.TITULZA',
                     'k.VEK', 'k.POVOLANI', 'k.BYDLISTEN', 'k.POCHLASU', 'k.POCPROC', 'k.MANDAT',
                     'rk.HLASY')
            ->from('polar_electionsps2025_psrk', 'k')
            ->leftJoin('k', 'polar_electionsps2025_results_kandid', 'rk',
                       'rk.KSTRANA = k.KSTRANA AND rk.PORCISLO = k.PORCISLO')
            ->where('k.KSTRANA = :kstrana')
            ->setParameter('kstrana', $election['KSTRANA'])
            ->orderBy('k.POCHLASU', 'DESC')
            ->addOrderBy('k.PORCISLO', 'ASC')
            ->fetchAllAssociative();

        $election['psrk'] = $psrk ?: [];
        return $election;
    }

    /**
     * @param string $nuts_okres
     * @param int|null $obec_id
     * @return array|null
     */
    public function getResultsOkresyObceForWeb(string $nuts_okres, ?int $obec_id): ?array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('p.*')
            ->from('polar_electionsps2025_psrkl', 'p');

        if (!$obec_id) {
            $qb->innerJoin('p', 'polar_electionsps2025_results_okresy', 'r', 'r.KSTRANA = p.KSTRANA')
               ->where('r.NUTS_OKRES = :nuts_okres')
               ->setParameter('nuts_okres', $nuts_okres)
               ->orderBy('r.HLASY', 'DESC');
        } else {
            $qb->innerJoin('p', 'polar_electionsps2025_results_obce', 'r', 'r.KSTRANA = p.KSTRANA')
               ->where('r.NUTS_OKRES = :nuts_okres')
               ->andWhere('r.CIS_OBEC = :obec_id')
               ->setParameter('nuts_okres', $nuts_okres)
               ->setParameter('obec_id', $obec_id)
               ->orderBy('r.HLASY', 'DESC');
        }

        return $qb->fetchAllAssociative() ?: null;
    }

    /**
     * @param string $nuts_okres
     * @param int|null $obec_id
     * @return array|null
     */
    public function getResultsOkresyObceTotal(string $nuts_okres, ?int $obec_id): ?array
    {
        if (!$obec_id) {
            $row = $this->connection->createQueryBuilder()
                ->select('*')
                ->from('polar_electionsps2025_results_okresy')
                ->where('NUTS_OKRES = :nuts_okres')
                ->setParameter('nuts_okres', $nuts_okres)
                ->setMaxResults(1)
                ->fetchAssociative();
        } else {
            $row = $this->connection->createQueryBuilder()
                ->select('*')
                ->from('polar_electionsps2025_results_obce')
                ->where('CIS_OBEC = :obec_id')
                ->setParameter('obec_id', $obec_id)
                ->setMaxResults(1)
                ->fetchAssociative();
        }
        return $row ?: null;
    }

    /**
     * @param string $column
     * @param int|string $value
     * @return array|null
     */
    public function getPscocoArrayByColumn(string $column, int|string $value): ?array
    {
        return $this->connection->createQueryBuilder()
            ->select('*')
            ->from('polar_electionsps2025_pscoco')
            ->where($column . ' = :value')
            ->setParameter('value', $value)
            ->orderBy('LOWER(NAZEVOBCE) COLLATE utf8_czech_ci')
            ->fetchAllAssociative() ?: null;
    }

    /**
     * @param string $column
     * @param int|string $value
     * @return array|null
     */
    public function getPscocoByColumn(string $column, int|string $value): ?array
    {
        $row = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('polar_electionsps2025_pscoco')
            ->where($column . ' = :value')
            ->setParameter('value', $value)
            ->fetchAssociative();
        return $row ?: null;
    }

    /**
     * @return array|null
     */
    public function fetchAllMandatForWeb(): ?array
    {
        $elections = $this->connection->createQueryBuilder()
            ->select('p.KSTRANA', 'p.NAZEVCELK', 'p.NAZEV_STRK',
                     'r.HLASY', 'r.PROC_HLASU', 'r.MANDATY')
            ->from('polar_electionsps2025_psrkl', 'p')
            ->leftJoin('p', 'polar_electionsps2025_results', 'r', 'r.KSTRANA = p.KSTRANA')
            ->where('r.KRAJ = :kraj')
            ->andWhere('r.MANDATY <> 0') /* během sčítání zakomentovat */
            ->setParameter('kraj', 'MSK')
            ->orderBy('r.HLASY', 'DESC')
            ->groupBy('p.id')
            ->fetchAllAssociative();

        for ($i = 0, $iMax = count($elections); $i < $iMax; $i++) {
            $psrk = $this->connection->createQueryBuilder()
                ->select('PORCISLO', 'JMENO', 'PRIJMENI', 'TITULPRED', 'TITULZA',
                         'VEK', 'POVOLANI', 'BYDLISTEN', 'POCHLASU', 'POCPROC', 'MANDAT')
                // počty hlasů bereme z výsledků. Po seštení budeme brát z registrů, pak zakomentovat JOIN a přidat tady do columns: 'POCHLASU'
                //'polar_electionsps2025_results_kandid' JOIN zakomentován
                ->from('polar_electionsps2025_psrk')
                ->where('MANDAT = :mandat') /* během sčítání zakomentovat */
                ->andWhere('KSTRANA = :kstrana')
                ->setParameter('mandat', 'A')
                ->setParameter('kstrana', $elections[$i]['KSTRANA'])
                ->orderBy('POCHLASU', 'DESC')
                ->addOrderBy('PORCISLO', 'ASC')
                ->fetchAllAssociative();
            $elections[$i]['psrk'] = $psrk ?: [];
        }

        return $elections ?: null;
    }

    /**
     * @return array|null
     */
    public function fetchAllKreslaForWeb(): ?array
    {
        return $this->connection->createQueryBuilder()
            ->select('p.KSTRANA', 'p.NAZEVCELK', 'p.NAZEV_STRK', 'p.ZKRATKAK8',
                     'r.HLASY', 'r.PROC_HLASU', 'r.MANDATY')
            ->from('polar_electionsps2025_psrkl', 'p')
            ->leftJoin('p', 'polar_electionsps2025_results', 'r', 'r.KSTRANA = p.KSTRANA')
            ->where('r.KRAJ = :kraj')
            /*->andWhere('polar_electionsps2025_results.MANDATY <> 0')*/
            ->setParameter('kraj', 'MSK')
            ->orderBy('r.MANDATY', 'DESC')
            ->addOrderBy('r.HLASY', 'DESC')
            ->groupBy('p.id')
            ->fetchAllAssociative() ?: null;
    }

    /**
     * @return array|null
     */
    public function getResultsTotal(): ?array
    {
        $row = $this->connection->createQueryBuilder()
            ->select('PLATNE_HLASY', 'OKRSKY_ZPRAC_PROC', 'UCAST_PROC')
            ->from('polar_electionsps2025_results')
            ->where('KRAJ = :kraj')
            ->setParameter('kraj', 'MSK')
            ->setMaxResults(1)
            ->fetchAssociative();
        return $row ?: null;
    }

    /**
     * Pro admin bootstrap-select
     * @return array
     */
    public function fetchPsrklForBootstrapSelect(): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('polar_electionsps2025_psrkl')
            ->orderBy('LOWER(ZKRATKAK8) COLLATE utf8_czech_ci', 'ASC')
            ->fetchAllAssociative();

        $data = [['value' => null, 'label' => null]];
        foreach ($rows as $item) {
            $data[] = [
                'value' => $item['ZKRATKAK8'],
                'label' => $item['ZKRATKAK8'],
            ];
        }
        return $data;
    }
}
```

- [ ] **Step 2: Ověřit syntax**

```bash
php -l src/Election/Repository/Election2025PlaykitRepository.php
```

- [ ] **Step 3: Commit**

```bash
git add src/Election/Repository/Election2025PlaykitRepository.php
git commit -m "feat: Election2025PlaykitRepository"
```

---

## Task 5: ElectionRepository2024

**Files:**
- Create: `src/Election/Repository/ElectionRepository2024.php`

Referenční soubor: `polar/module/Election/src/Model/Election2024/MariaDbSqlRepository.php`

Pozn.: Pro web se používá jen `findPostBy()` a `fetchStudioForWeb()`. Tabulka: `elections_2024`.

- [ ] **Step 1: Přečíst zdrojový soubor**

```
read_file polar/module/Election/src/Model/Election2024/MariaDbSqlRepository.php
```

- [ ] **Step 2: Vytvořit soubor — stejná struktura jako ElectionRepository2025, ale `$table = 'elections_2024'` a join na `elections_2024.video_id`**

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

namespace App\Election\Repository;

use Doctrine\DBAL\Connection;

final class ElectionRepository2024
{
    private string $table = 'elections_2024';

    public function __construct(
        private Connection $connection,
    ) {}

    public function findPostBy(string $column, int|string $value): array
    {
        $row = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->table)
            ->where($column . ' = :value')
            ->setParameter('value', $value)
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

    public function fetchAllForWeb(): ?array
    {
        return $this->connection->createQueryBuilder()
            ->select('e.*', 'pv.name', 'pv.duration', 'p.url AS program_url', 'ps.url')
            ->from($this->table, 'e')
            ->innerJoin('e', 'program_videos', 'pv', 'pv.id = e.video_id')
            ->innerJoin('pv', 'program', 'p', 'p.video_id = pv.id')
            ->innerJoin('p', 'program2shows', 'p2s', 'p2s.program_id = p.id')
            ->innerJoin('p2s', 'program_shows', 'ps', 'ps.id = p2s.show_id')
            ->where('p.premiere = 1')
            ->andWhere('p.time < NOW()')
            ->orderBy('e.rank', 'ASC')
            ->groupBy('e.id')
            ->fetchAllAssociative() ?: null;
    }

    public function fetchStudioForWeb(int $video_id): ?array
    {
        $row = $this->connection->createQueryBuilder()
            ->select('e.*', 'pv.id AS pv_id', 'pv.name', 'pv.path')
            ->from($this->table, 'e')
            ->innerJoin('e', 'program_videos', 'pv', 'pv.id = e.video_id')
            ->where('e.video_id = :video_id')
            ->setParameter('video_id', $video_id)
            ->fetchAssociative();
        return $row ?: null;
    }
}
```

- [ ] **Step 3: Ověřit syntax + commit**

```bash
php -l src/Election/Repository/ElectionRepository2024.php
git add src/Election/Repository/ElectionRepository2024.php
git commit -m "feat: ElectionRepository2024"
```

---

## Task 6: Election2024PlaykitRepository

**Files:**
- Create: `src/Election/Repository/Election2024PlaykitRepository.php`

Referenční soubor: `polar/module/Election/src/Model/Election2024/Playkit/MariaDbSqlRepository.php`

Tabulky: `polar_electionskz2024_kzrkl`, `polar_electionskz2024_kzrk`, `polar_electionskz2024_results`, `polar_electionskz2024_results_kandid`, `polar_electionskz2024_results_okresy`, `polar_electionskz2024_results_obce`, `polar_electionskz2024_kzcoco`

- [ ] **Step 1: Přečíst celý zdrojový soubor**

```
read_file polar/module/Election/src/Model/Election2024/Playkit/MariaDbSqlRepository.php
```

- [ ] **Step 2: Vytvořit soubor** — stejná logika jako Election2025PlaykitRepository, ale:
  - `psrkl` → `kzrkl`, `psrk` → `kzrk`
  - název metod: `fetchKzrklAll()`, `findKzrklPostBy()`, `getKzrkByKstrana()`, `getResultsOkresyObceForWeb()`, `getResultsOkresyObceTotal()`, `getKzrcocoArrayByColumn()`, `getKzrcocoByColumn()`, `fetchAllZastupitelForWeb()`, `fetchAllKreslaForWeb()`, `getResultsTotal()`, `fetchKzrklForBootstrapSelect()`
  - tabulky prefix: `polar_electionskz2024_*`
  - výsledky: `polar_electionskz2024_results.KRAJ` → hodnota závisí na 2024 (zkontroluj v originálním souboru)

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

namespace App\Election\Repository;

use Doctrine\DBAL\Connection;

final class Election2024PlaykitRepository
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function fetchKzrklAll(): ?array
    {
        return $this->connection->createQueryBuilder()
            ->select('*')
            ->from('polar_electionskz2024_kzrkl')
            ->orderBy('LOWER(ZKRATKAK8) COLLATE utf8_czech_ci', 'ASC')
            ->fetchAllAssociative() ?: null;
    }

    public function findKzrklPostBy(string $column, int|string $value): ?array
    {
        $row = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('polar_electionskz2024_kzrkl')
            ->where($column . ' = :value')
            ->setParameter('value', $value)
            ->fetchAssociative();
        return $row ?: null;
    }

    public function getKzrkByKstrana(int $kstrana): ?array
    {
        $election = $this->connection->createQueryBuilder()
            ->select('p.KSTRANA', 'p.NAZEVCELK', 'p.NAZEV_STRK', 'p.ZKRATKAK8',
                     'r.HLASY', 'r.PROC_HLASU', 'r.MANDATY')
            ->from('polar_electionskz2024_kzrkl', 'p')
            ->leftJoin('p', 'polar_electionskz2024_results', 'r', 'r.KSTRANA = p.KSTRANA')
            ->where('p.KSTRANA = :kstrana')
            ->setParameter('kstrana', $kstrana)
            ->orderBy('r.HLASY', 'DESC')
            ->groupBy('p.id')
            ->fetchAssociative();

        if (!$election) {
            return null;
        }

        $kzrk = $this->connection->createQueryBuilder()
            ->select('k.PORCISLO', 'k.JMENO', 'k.PRIJMENI', 'k.TITULPRED', 'k.TITULZA',
                     'k.VEK', 'k.POVOLANI', 'k.BYDLISTEN', 'k.POCHLASU', 'k.POCPROC', 'k.MANDAT',
                     'rk.HLASY')
            ->from('polar_electionskz2024_kzrk', 'k')
            ->leftJoin('k', 'polar_electionskz2024_results_kandid', 'rk',
                       'rk.KSTRANA = k.KSTRANA AND rk.PORCISLO = k.PORCISLO')
            ->where('k.KSTRANA = :kstrana')
            ->setParameter('kstrana', $election['KSTRANA'])
            ->orderBy('k.POCHLASU', 'DESC')
            ->addOrderBy('k.PORCISLO', 'ASC')
            ->fetchAllAssociative();

        $election['kzrk'] = $kzrk ?: [];
        return $election;
    }

    public function getResultsOkresyObceForWeb(string $nuts_okres, ?int $obec_id): ?array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('p.*')
            ->from('polar_electionskz2024_kzrkl', 'p');

        if (!$obec_id) {
            $qb->innerJoin('p', 'polar_electionskz2024_results_okresy', 'r', 'r.KSTRANA = p.KSTRANA')
               ->where('r.NUTS_OKRES = :nuts_okres')
               ->setParameter('nuts_okres', $nuts_okres)
               ->orderBy('r.HLASY', 'DESC');
        } else {
            $qb->innerJoin('p', 'polar_electionskz2024_results_obce', 'r', 'r.KSTRANA = p.KSTRANA')
               ->where('r.NUTS_OKRES = :nuts_okres')
               ->andWhere('r.CIS_OBEC = :obec_id')
               ->setParameter('nuts_okres', $nuts_okres)
               ->setParameter('obec_id', $obec_id)
               ->orderBy('r.HLASY', 'DESC');
        }

        return $qb->fetchAllAssociative() ?: null;
    }

    public function getResultsOkresyObceTotal(string $nuts_okres, ?int $obec_id): ?array
    {
        if (!$obec_id) {
            $row = $this->connection->createQueryBuilder()
                ->select('*')
                ->from('polar_electionskz2024_results_okresy')
                ->where('NUTS_OKRES = :nuts_okres')
                ->setParameter('nuts_okres', $nuts_okres)
                ->setMaxResults(1)
                ->fetchAssociative();
        } else {
            $row = $this->connection->createQueryBuilder()
                ->select('*')
                ->from('polar_electionskz2024_results_obce')
                ->where('CIS_OBEC = :obec_id')
                ->setParameter('obec_id', $obec_id)
                ->setMaxResults(1)
                ->fetchAssociative();
        }
        return $row ?: null;
    }

    public function getKzrcocoArrayByColumn(string $column, int|string $value): ?array
    {
        return $this->connection->createQueryBuilder()
            ->select('*')
            ->from('polar_electionskz2024_kzcoco')
            ->where($column . ' = :value')
            ->setParameter('value', $value)
            ->orderBy('LOWER(NAZEVOBCE) COLLATE utf8_czech_ci')
            ->fetchAllAssociative() ?: null;
    }

    public function getKzrcocoByColumn(string $column, int|string $value): ?array
    {
        $row = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('polar_electionskz2024_kzcoco')
            ->where($column . ' = :value')
            ->setParameter('value', $value)
            ->fetchAssociative();
        return $row ?: null;
    }

    public function fetchAllZastupitelForWeb(): ?array
    {
        // Přečíst originální implementaci z MariaDbSqlRepository 2024 a přeložit do DBAL
        // (stejný vzor jako fetchAllMandatForWeb v 2025, ale pro krajské zastupitele)
        $elections = $this->connection->createQueryBuilder()
            ->select('p.KSTRANA', 'p.NAZEVCELK', 'p.NAZEV_STRK',
                     'r.HLASY', 'r.PROC_HLASU', 'r.MANDATY')
            ->from('polar_electionskz2024_kzrkl', 'p')
            ->leftJoin('p', 'polar_electionskz2024_results', 'r', 'r.KSTRANA = p.KSTRANA')
            ->where('r.MANDATY <> 0')
            ->orderBy('r.HLASY', 'DESC')
            ->groupBy('p.id')
            ->fetchAllAssociative();

        for ($i = 0, $iMax = count($elections); $i < $iMax; $i++) {
            $kzrk = $this->connection->createQueryBuilder()
                ->select('PORCISLO', 'JMENO', 'PRIJMENI', 'TITULPRED', 'TITULZA',
                         'VEK', 'POVOLANI', 'BYDLISTEN', 'POCHLASU', 'POCPROC', 'MANDAT')
                ->from('polar_electionskz2024_kzrk')
                ->where('MANDAT = :mandat')
                ->andWhere('KSTRANA = :kstrana')
                ->setParameter('mandat', 'A')
                ->setParameter('kstrana', $elections[$i]['KSTRANA'])
                ->orderBy('POCHLASU', 'DESC')
                ->addOrderBy('PORCISLO', 'ASC')
                ->fetchAllAssociative();
            $elections[$i]['kzrk'] = $kzrk ?: [];
        }
        return $elections ?: null;
    }

    public function fetchAllKreslaForWeb(): ?array
    {
        return $this->connection->createQueryBuilder()
            ->select('p.KSTRANA', 'p.NAZEVCELK', 'p.NAZEV_STRK', 'p.ZKRATKAK8',
                     'r.HLASY', 'r.PROC_HLASU', 'r.MANDATY')
            ->from('polar_electionskz2024_kzrkl', 'p')
            ->leftJoin('p', 'polar_electionskz2024_results', 'r', 'r.KSTRANA = p.KSTRANA')
            ->orderBy('r.MANDATY', 'DESC')
            ->addOrderBy('r.HLASY', 'DESC')
            ->groupBy('p.id')
            ->fetchAllAssociative() ?: null;
    }

    public function getResultsTotal(): ?array
    {
        $row = $this->connection->createQueryBuilder()
            ->select('PLATNE_HLASY', 'OKRSKY_ZPRAC_PROC', 'UCAST_PROC')
            ->from('polar_electionskz2024_results')
            ->setMaxResults(1)
            ->fetchAssociative();
        return $row ?: null;
    }

    public function fetchKzrklForBootstrapSelect(): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('polar_electionskz2024_kzrkl')
            ->orderBy('LOWER(ZKRATKAK8) COLLATE utf8_czech_ci', 'ASC')
            ->fetchAllAssociative();

        $data = [['value' => null, 'label' => null]];
        foreach ($rows as $item) {
            $data[] = ['value' => $item['ZKRATKAK8'], 'label' => $item['ZKRATKAK8']];
        }
        return $data;
    }
}
```

> **Důležité:** Před commit ověř metodu `fetchAllZastupitelForWeb()` oproti originálnímu `MariaDbSqlRepository.php` pro 2024 – JOIN podmínky pro výběr zastupitelů se mohou lišit od `fetchAllMandatForWeb()` 2025.

- [ ] **Step 3: Ověřit syntax + commit**

```bash
php -l src/Election/Repository/Election2024PlaykitRepository.php
git add src/Election/Repository/Election2024PlaykitRepository.php
git commit -m "feat: Election2024PlaykitRepository"
```

---

## Task 7: Election2020PlaykitRepository

**Files:**
- Create: `src/Election/Repository/Election2020PlaykitRepository.php`

Referenční soubor: `polar/module/Election/src/Model/Election2020/Playkit/MariaDbSqlRepository.php`

- [ ] **Step 1: Přečíst zdrojový soubor**

```
read_file polar/module/Election/src/Model/Election2020/Playkit/MariaDbSqlRepository.php
```

- [ ] **Step 2: Vytvořit soubor** — přeložit všechny metody z `MariaDbSqlRepository` do Doctrine DBAL

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

namespace App\Election\Repository;

use Doctrine\DBAL\Connection;

final class Election2020PlaykitRepository
{
    public function __construct(
        private Connection $connection,
    ) {}

    // Implementovat metody dle originálního MariaDbSqlRepository pro 2020
    // Typicky: fetchAllKreslaForWeb(), getResultsTotal()
    // Přečíst zdrojový soubor a přepsat 1:1 do QueryBuilder stylu
}
```

- [ ] **Step 3: Ověřit syntax + commit**

```bash
php -l src/Election/Repository/Election2020PlaykitRepository.php
git add src/Election/Repository/Election2020PlaykitRepository.php
git commit -m "feat: Election2020PlaykitRepository"
```

---

## Task 8: ElectionSettingRepository + ElectionSettingCommand (stub)

**Files:**
- Create: `src/Election/Repository/ElectionSettingRepository.php`
- Create: `src/Election/Repository/ElectionSettingCommand.php`

Referenční soubory: `polar/module/Election/src/Model/Setting/MariaDbSqlRepository.php`, `MariaDbSqlCommand.php`

Admin nastavení je v Laminas prakticky prázdné (metody zakomentovány). Stub postačuje.

- [ ] **Step 1: Vytvořit oba soubory**

```php
<?php
// src/Election/Repository/ElectionSettingRepository.php
declare(strict_types=1);
namespace App\Election\Repository;
use Doctrine\DBAL\Connection;
final class ElectionSettingRepository
{
    public function __construct(private Connection $connection) {}

    public function fetchSetting(): ?array
    {
        //$row = $this->connection->createQueryBuilder()...
        return null;
    }
}
```

```php
<?php
// src/Election/Repository/ElectionSettingCommand.php
declare(strict_types=1);
namespace App\Election\Repository;
use Doctrine\DBAL\Connection;
final class ElectionSettingCommand
{
    public function __construct(private Connection $connection) {}
}
```

- [ ] **Step 2: Ověřit syntax + commit**

```bash
php -l src/Election/Repository/ElectionSettingRepository.php
php -l src/Election/Repository/ElectionSettingCommand.php
git add src/Election/Repository/ElectionSettingRepository.php src/Election/Repository/ElectionSettingCommand.php
git commit -m "feat: ElectionSettingRepository + ElectionSettingCommand (stub)"
```

---

## Task 9: services.yaml — registrace

**Files:**
- Modify: `config/services.yaml`

- [ ] **Step 1: Přidat do services.yaml**

Přidat za ostatní controller/repository bloky:

```yaml
    App\Election\Controller\:
        resource: '../src/Election/Controller/'
        tags: ['controller.service_arguments']

    App\Election\Repository\Election2025PlaykitRepository:
        arguments:
            $connection: '@doctrine.dbal.playkit_connection'

    App\Election\Repository\Election2024PlaykitRepository:
        arguments:
            $connection: '@doctrine.dbal.playkit_connection'

    App\Election\Repository\Election2020PlaykitRepository:
        arguments:
            $connection: '@doctrine.dbal.playkit_connection'
```

Pozn.: `ElectionRepository2025`, `ElectionRepository2024`, `ElectionCommand2025`, `ElectionSettingRepository`, `ElectionSettingCommand` používají výchozí `doctrine.dbal.default_connection` → autowire stačí.

- [ ] **Step 2: Cache clear**

```bash
php bin/console cache:clear
```

- [ ] **Step 3: Commit**

```bash
git add config/services.yaml
git commit -m "feat: election services registrace"
```

---

## Task 10: Web2025Controller

**Files:**
- Create: `src/Election/Controller/Web/Web2025Controller.php`

Referenční soubor: `polar/module/Election/src/Controller/Web2025/WebListController.php`

- [ ] **Step 1: Přečíst celý zdrojový soubor (již přečten v analýze)**

- [ ] **Step 2: Vytvořit soubor**

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

namespace App\Election\Controller\Web;

use App\Application\View\PhtmlRenderer;
use App\Banner\Repository\BannerRepository;
use App\Election\Repository\Election2025PlaykitRepository;
use App\Election\Repository\ElectionRepository2025;
use App\News\Repository\NewsRepository;
use App\News\Repository\PlaykitRepository;
use App\News\Repository\ShowRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class Web2025Controller
{
    private array $colors = [
        /* všechny strany
        1  => '#FF4500', // Rebelové
        2  => '#cf2e2e', // Moravské zemské hnutí
        3  => '#000000', // Jasný Signál Nezávislých
        4  => '#0066CC', // VÝZVA 2025
        5  => '#008000', // SMS – Stát Má Sloužit
        6  => '#B45F06', // SPD
        7  => '#FF4500', // ČSSD
        8  => '#0033FF', // PŘÍSAHA
        9  => '#8B0000', // Levice
        10 => '#00008B', // Česká republika na 1. místě
        11 => '#00FF40', // SPOLU
        12 => '#C0C0C0', // ŠVÝCARSKÁ DEMOKRACIE
        13 => '#800080', // Urza.cz
        14 => '#2E8B57', // Hnutí občanů a podnikatelů
        15 => '#FFA500', // Hnutí Generace
        16 => '#707070', // Piráti
        17 => '#DAA520', // Koruna Česká
        18 => '#1E90FF', // Volt Česko
        19 => '#808080', // Volte Pravý Blok
        20 => '#00BFFF', // Motoristé sobě
        21 => '#4B0082', // Balbínova poetická strana
        22 => '#261060', // ANO 2011
        23 => '#E6007E', // STAROSTOVÉ A NEZÁVISLÍ STAN
        24 => '#00CED1', // Hnutí Kruh
        25 => '#FF0000', // Stačilo!
        26 => '#FF69B4', // Voluntia
        */

        22 => '#261060', // ANO 2011
        11 => '#00FF40', // SPOLU
        6  => '#B45F06', // SPD
        23 => '#E6007E', // STAROSTOVÉ A NEZÁVISLÍ STAN
        16 => '#707070', // Piráti
        25 => '#FF0000', // Stačilo!
        20 => '#00BFFF', // Motoristé sobě
        8  => '#0033FF', // PŘÍSAHA
        7  => '#FF4500', // ČSSD
        2  => '#cf2e2e', // Moravské zemské hnutí
    ];

    public function __construct(
        private ElectionRepository2025 $electionRepository,
        private Election2025PlaykitRepository $electionPlaykitRepository,
        private PlaykitRepository $playkitRepository,
        private NewsRepository $newsRepository,
        private ShowRepository $showRepository,
        private BannerRepository $bannerRepository,
        private PhtmlRenderer $renderer,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function index(Request $request): Response
    {
        $elections = null;
        $psrkl = null;
        try {
            $elections = $this->electionRepository->fetchAllForWeb();
            $studio_array = [];
            foreach ($elections as $key => $sel) {
                $psrkl_item = $this->electionPlaykitRepository->findPsrklPostBy('ZKRATKAK8', $sel['title']);
                if ($psrkl_item) {
                    $elections[$key]['KSTRANA'] = $psrkl_item['KSTRANA'];
                    $elections[$key]['NAZEV_STRK'] = $psrkl_item['NAZEV_STRK'];
                    $studio_array[$sel['id']] = $sel['title'];
                } else {
                    $elections[$key]['KSTRANA'] = null;
                    $elections[$key]['NAZEV_STRK'] = null;
                }
            }
            //$elections = null; $studio_array = []; // dočasně
            //var_dump($elections);

            $psrkl = $this->electionPlaykitRepository->fetchPsrklAll();
            foreach ($psrkl as $key => $value) {
                if (in_array($value['ZKRATKAK8'], $studio_array)) {
                    //$volby2025[] = $elections[array_search($value['ZKRATKAK8'], $studio_array)];
                    unset($psrkl[$key]);
                }
            }
            //var_dump($psrkl);
        } catch (\Exception $e) {
            return new RedirectResponse($this->urlGenerator->generate('news'));
            //var_dump($e->getMessage());
        }

        // Články k tématu volby
        $articles = null;
        $page = 1;
        try {
            $topic = $this->playkitRepository->getTopicIDByUrl('volby-2025');
            if ($topic) {
                $articles_ids = $this->playkitRepository->getArticlesIDsByTopicID((int)$topic['id']);
                //var_dump($articles_ids);
                if ($articles_ids) {
                    $page = $request->query->getInt('strana', 1);
                    $articles = $this->newsRepository->getPaginatorByTopic($articles_ids, $page, 20);
                    //var_dump($articles);
                }
            }
        } catch (\Exception $e) {
            //var_dump ($e->getMessage());
            return new RedirectResponse($this->urlGenerator->generate('news'));
        }
        // Pořady
        $shows = $this->showRepository->fetchAllForNews();

        // Zakázání zobrazení PR článku při prvním příchodu ze stránek seznam.cz
        $seznam = $request->query->get('utm_source');
        if ($seznam !== 'www.seznam.cz') {
            // PR články
            $pr = $this->newsRepository->getPrArticles(11);
        } else {
            $pr = null;
        }

        // Počasí
        $weather = $this->playkitRepository->getWeatherForNews('Ostrava');

        // Banner leaderboard a Mobilesticky pro layout
        $bannerLeaderboard = $this->bannerRepository->getLeaderboard();
        $bannerMobilesticky = $this->bannerRepository->getMobilesticky();

        return new Response($this->renderer->render('election/web2025/index', [
            'elections'      => $elections,
            'psrkl'          => $psrkl,
            'articles'       => $articles,
            'shows'          => $shows,
            'pr'             => $pr,
            'weather'        => $weather,
            'weather_region' => 'Ostrava',
            'page'           => $page,
            'pageTitle'      => 'Volby 2025',
            'metaDescription' => 'Která politická strana získá Vaše sympatie? TV Polar Vám představí jednotlivé volební programy a kandidátní listiny politických stran pro parlamentní volby 2025 Moravskoslezského kraje. Pojďme k volbám, je to náš kraj!',
            'metaKeywords'   => 'volby 2025, parlamentní volby, politická strana, volební program, kandidátní listina',
            'ogImage'        => '/img/web/volby/microformat_2025.png',
            'bannerLeaderboard'  => $bannerLeaderboard,
            'bannerMobilesticky' => $bannerMobilesticky,
        ]));
    }

    public function detail(Request $request, int $kstrana = 22): Response
    {
        if (!$kstrana) {
            $kstrana = 22;  // strana ANO
        }
        $election = $studio = null;

        try {
            $election = $this->electionPlaykitRepository->getPsrkByKstrana($kstrana);
            if (!$election) {
                return new RedirectResponse($this->urlGenerator->generate('election_2025'));
            }
            try {
                $studio_row = $this->electionRepository->findPostBy('title', $election['ZKRATKAK8']);
                if ($studio_row) {
                    $studio = $this->electionRepository->fetchStudioForWeb((int) $studio_row['video_id']);
                }
            } catch (\Exception $ex) {
                $studio = null;
            }
            $strany = $this->electionPlaykitRepository->fetchPsrklAll();
        } catch (\Exception $ex) {
            return new RedirectResponse($this->urlGenerator->generate('election_2025'));
            //var_dump($ex->getMessage());
        }

        // Články k tématu volby
        $articles = null;
        $page = 1;
        try {
            $topic = $this->playkitRepository->getTopicIDByUrl('volby-2025');
            if ($topic) {
                $articles_ids = $this->playkitRepository->getArticlesIDsByTopicID((int)$topic['id']);
                //var_dump($articles_ids);
                if ($articles_ids) {
                    $page = $request->query->getInt('strana', 1);
                    $articles = $this->newsRepository->getPaginatorByTopic($articles_ids, $page, 20);
                    //var_dump($articles);
                }
            }
        } catch (\Exception $e) {
            //var_dump ($e->getMessage());
            return new RedirectResponse($this->urlGenerator->generate('news'));
        }
        // Pořady
        $shows = $this->showRepository->fetchAllForNews();

        // Banner leaderboard a Mobilesticky pro layout
        $bannerLeaderboard = $this->bannerRepository->getLeaderboard();
        $bannerMobilesticky = $this->bannerRepository->getMobilesticky();

        $pageTitle = isset($election['ZKRATKAK8'])
            ? $election['ZKRATKAK8'] . ' | Kandidáti | Volby 2025'
            : 'Volby 2025';

        return new Response($this->renderer->render('election/web2025/detail', [
            'kstrana'            => $kstrana,
            'election'           => $election,
            'strany'             => $strany,
            'studio'             => $studio,
            'articles'           => $articles,
            'shows'              => $shows,
            'page'               => $page,
            'pageTitle'          => $pageTitle,
            'metaDescription'    => 'Která politická strana získá Vaše sympatie? TV Polar Vám představí jednotlivé volební programy a kandidátní listiny politických stran pro parlamentní volby 2025 Moravskoslezského kraje. Pojďme k volbám, je to náš kraj!',
            'metaKeywords'       => 'volby 2025, parlamentní volby, politická strana, volební program, kandidátní listina',
            'ogImage'            => '/img/web/volby/microformat_2025.png',
            'bannerLeaderboard'  => $bannerLeaderboard,
            'bannerMobilesticky' => $bannerMobilesticky,
        ]));
    }

    public function obec(Request $request, int $okres = 8106, ?int $obec = null): Response
    {
        $obec_id = $obec;

        $switch_data = match($okres) {
            8101 => ['Bruntál',       'CZ0801'],
            8105 => ['Opava',         'CZ0805'],
            8104 => ['Nový Jičín',    'CZ0804'],
            8106 => ['Ostrava-město', 'CZ0806'],
            8103 => ['Karviná',       'CZ0803'],
            8102 => ['Frýdek-Místek', 'CZ0802'],
            default => ['Ostrava-město', 'CZ0806'],
        };
        $okres_id    = ($switch_data[0] === 'Ostrava-město' && $okres !== 8106) ? 8106 : $okres;
        $okres_title = $switch_data[0];
        $nuts_okres  = $switch_data[1];

        $elections = $elections_results = $obce = $obec_data = null;
        try {
            $elections = $this->electionPlaykitRepository->getResultsOkresyObceForWeb($nuts_okres, $obec_id);
            $elections_results = $this->electionPlaykitRepository->getResultsOkresyObceTotal($nuts_okres, $obec_id);
            if ($elections_results) $elections_results = (array) $elections_results;

            for ($i = 0, $iMax = count($elections); $i < $iMax; $i++) {
                $elections[$i]['barva'] = $this->colors[$elections[$i]['KSTRANA']] ?? '#ccc';
            }
            $obce = $this->electionPlaykitRepository->getPscocoArrayByColumn('OKRES', $okres_id);
            if ($obec_id) {
                $obec_data = (array) $this->electionPlaykitRepository->getPscocoByColumn('OBEC', $obec_id);
            }
        } catch (\Exception $ex) {
            return new RedirectResponse($this->urlGenerator->generate('election_2025_obec', ['okres' => $okres_id]));
            //var_dump($ex->getMessage());
        }
        //var_dump($elections_results);

        // Články k tématu volby
        $articles = null;
        $page = 1;
        try {
            $topic = $this->playkitRepository->getTopicIDByUrl('volby-2025');
            if ($topic) {
                $articles_ids = $this->playkitRepository->getArticlesIDsByTopicID((int)$topic['id']);
                //var_dump($articles_ids);
                if ($articles_ids) {
                    $page = $request->query->getInt('strana', 1);
                    $articles = $this->newsRepository->getPaginatorByTopic($articles_ids, $page, 20);
                    //var_dump($articles);
                }
            }
        } catch (\Exception $e) {
            //var_dump ($e->getMessage());
            return new RedirectResponse($this->urlGenerator->generate('news'));
        }
        // Pořady
        $shows = $this->showRepository->fetchAllForNews();

        // Banner leaderboard a Mobilesticky pro layout
        $bannerLeaderboard = $this->bannerRepository->getLeaderboard();
        $bannerMobilesticky = $this->bannerRepository->getMobilesticky();

        $obec_title = isset($obec_data['NAZEVOBCE']) ? $obec_data['NAZEVOBCE'] . ' | ' : '';

        return new Response($this->renderer->render('election/web2025/obec', [
            'okres_id'           => $okres_id,
            'obec_id'            => $obec_id,
            'obce'               => $obce,
            'elections'          => $elections,
            'articles'           => $articles,
            'shows'              => $shows,
            'okres_title'        => $okres_title,
            'elections_results'  => $elections_results,
            'obec'               => $obec_data,
            'page'               => $page,
            'pageTitle'          => $obec_title . $okres_title . ' | Volby 2025',
            'metaDescription'    => 'Která politická strana získá Vaše sympatie? TV Polar Vám představí jednotlivé volební programy a kandidátní listiny politických stran pro parlamentní volby 2025 Moravskoslezského kraje. Pojďme k volbám, je to náš kraj!',
            'metaKeywords'       => 'volby 2025, parlamentní volby, politická strana, volební program, kandidátní listina',
            'ogImage'            => '/img/web/volby/microformat_2025.png',
            'bannerLeaderboard'  => $bannerLeaderboard,
            'bannerMobilesticky' => $bannerMobilesticky,
        ]));
    }

    public function poslanci(Request $request): Response
    {
        try {
            $elections = $this->electionPlaykitRepository->fetchAllMandatForWeb();
        } catch (\Exception $ex) {
            return new RedirectResponse($this->urlGenerator->generate('election_2025'));
        }

        //if ($kvrzcoco['KODZASTUP'] == 554821) $kvrzcoco['NAZEVOBCE'] = 'Ostrava';
        //if ($kvrzcoco['KODZASTUP'] == 505927) $kvrzcoco['NAZEVOBCE'] = 'Opava';

        // Články k tématu volby
        $articles = null;
        $page = 1;
        try {
            $topic = $this->playkitRepository->getTopicIDByUrl('volby-2025');
            if ($topic) {
                $articles_ids = $this->playkitRepository->getArticlesIDsByTopicID((int)$topic['id']);
                //var_dump($articles_ids);
                if ($articles_ids) {
                    $page = $request->query->getInt('strana', 1);
                    $articles = $this->newsRepository->getPaginatorByTopic($articles_ids, $page, 20);
                    //var_dump($articles);
                }
            }
        } catch (\Exception $e) {
            //var_dump ($e->getMessage());
            return new RedirectResponse($this->urlGenerator->generate('news'));
        }
        // Pořady
        $shows = $this->showRepository->fetchAllForNews();

        // Banner leaderboard a Mobilesticky pro layout
        $bannerLeaderboard = $this->bannerRepository->getLeaderboard();
        $bannerMobilesticky = $this->bannerRepository->getMobilesticky();

        return new Response($this->renderer->render('election/web2025/poslanci', [
            'elections'          => $elections,
            'articles'           => $articles,
            'shows'              => $shows,
            'page'               => $page,
            'pageTitle'          => 'Zvolení poslanci | Volby 2025',
            'metaDescription'    => 'Která politická strana získá Vaše sympatie? TV Polar Vám představí jednotlivé volební programy a kandidátní listiny politických stran pro parlamentní volby 2025 Moravskoslezského kraje. Pojďme k volbám, je to náš kraj!',
            'metaKeywords'       => 'volby 2025, volby do poslanecké sněmovny, politická strana, volební program, kandidátní listina',
            'ogImage'            => '/img/web/volby/microformat_2025.png',
            'bannerLeaderboard'  => $bannerLeaderboard,
            'bannerMobilesticky' => $bannerMobilesticky,
        ]));
    }

    public function kresla(Request $request): Response
    {
        try {
            $elections = $this->electionPlaykitRepository->fetchAllKreslaForWeb();
            $elections_results = (array) $this->electionPlaykitRepository->getResultsTotal();
            for ($i = 0, $iMax = count($elections); $i < $iMax; $i++) {
                $elections[$i]['barva'] = $this->colors[$elections[$i]['KSTRANA']] ?? '#ccc';
            }
        } catch (\Exception $ex) {
            return new RedirectResponse($this->urlGenerator->generate('election_2025'));
            //var_dump($ex->getMessage());
        }
        //var_dump($elections);

        // Články k tématu volby
        $articles = null;
        $page = 1;
        try {
            $topic = $this->playkitRepository->getTopicIDByUrl('volby-2025');
            if ($topic) {
                $articles_ids = $this->playkitRepository->getArticlesIDsByTopicID((int)$topic['id']);
                //var_dump($articles_ids);
                if ($articles_ids) {
                    $page = $request->query->getInt('strana', 1);
                    $articles = $this->newsRepository->getPaginatorByTopic($articles_ids, $page, 20);
                    //var_dump($articles);
                }
            }
        } catch (\Exception $e) {
            //var_dump ($e->getMessage());
            return new RedirectResponse($this->urlGenerator->generate('news'));
        }
        // Pořady
        $shows = $this->showRepository->fetchAllForNews();

        // Banner leaderboard a Mobilesticky pro layout
        $bannerLeaderboard = $this->bannerRepository->getLeaderboard();
        $bannerMobilesticky = $this->bannerRepository->getMobilesticky();

        return new Response($this->renderer->render('election/web2025/kresla', [
            'elections'          => $elections,
            'elections_results'  => $elections_results,
            'articles'           => $articles,
            'shows'              => $shows,
            'page'               => $page,
            'pageTitle'          => 'Rozdělení křesel | Volby 2025',
            'metaDescription'    => 'Která politická strana získá Vaše sympatie? TV Polar Vám představí jednotlivé volební programy a kandidátní listiny politických stran pro volby do poslanecké sněmovny 2025 Moravskoslezského kraje. Pojďme k volbám, je to náš kraj!',
            'metaKeywords'       => 'volby 2025, volby do poslanecké sněmovny, politická strana, volební program, kandidátní listina',
            'ogImage'            => '/img/web/volby/microformat_2025.png',
            'bannerLeaderboard'  => $bannerLeaderboard,
            'bannerMobilesticky' => $bannerMobilesticky,
        ]));
    }

    public function senat(Request $request): Response
    {
        // Banner leaderboard a Mobilesticky pro layout
        $bannerLeaderboard = $this->bannerRepository->getLeaderboard();
        $bannerMobilesticky = $this->bannerRepository->getMobilesticky();

        return new Response($this->renderer->render('election/web2025/senat', [
            'pageTitle'          => 'Senát | Volby 2025',
            'metaDescription'    => 'Která politická strana získá Vaše sympatie? TV Polar Vám představí jednotlivé volební programy a kandidátní listiny politických stran pro volby do poslanecké sněmovny 2025 Moravskoslezského kraje. Pojďme k volbám, je to náš kraj!',
            'metaKeywords'       => 'volby 2025, volby do poslanecké sněmovny, politická strana, volební program, kandidátní listina',
            'ogImage'            => '/img/web/volby/microformat_2025.png',
            'bannerLeaderboard'  => $bannerLeaderboard,
            'bannerMobilesticky' => $bannerMobilesticky,
        ]));
    }
}
```

- [ ] **Step 3: Ověřit syntax**

```bash
php -l src/Election/Controller/Web/Web2025Controller.php
```

- [ ] **Step 4: Commit**

```bash
git add src/Election/Controller/Web/Web2025Controller.php
git commit -m "feat: Web2025Controller"
```

---

## Task 11: Template web2025/index.phtml

**Files:**
- Create: `templates/election/web2025/index.phtml`

Referenční soubor: `polar/module/Election/view/election/web2025/web-list/index.phtml`

- [ ] **Step 1: Přečíst zdrojovou šablonu**

```
read_file polar/module/Election/view/election/web2025/web-list/index.phtml
```

- [ ] **Step 2: Vytvořit šablonu** — kopie 1:1 s těmito adaptacemi:
  - `$this->basePath(...)` → `$view->asset(...)`
  - `$this->url(...)` → `$view->path(...)`
  - `$this->translate(...)` → `$view->trans(...)`
  - `$this->partial(...)` → `$view->include(...)`
  - `$this->headTitle(...)`, `$this->headMeta(...)` → odstraněno (je v layoutu přes `$pageTitle` a `$metaDescription`)
  - Zachovat všechny komentáře z originálu

- [ ] **Step 3: Ověřit syntax**

```bash
php -l templates/election/web2025/index.phtml
```

- [ ] **Step 4: Commit**

```bash
git add templates/election/web2025/index.phtml
git commit -m "feat: template election/web2025/index"
```

---

## Task 12: Template web2025/detail.phtml

**Files:**
- Create: `templates/election/web2025/detail.phtml`

Referenční soubor: `polar/module/Election/view/election/web2025/web-list/detail.phtml`

- [ ] **Step 1: Přečíst zdrojovou šablonu + vytvořit** (stejný postup jako Task 11)

- [ ] **Step 2: Ověřit + commit**

```bash
php -l templates/election/web2025/detail.phtml
git add templates/election/web2025/detail.phtml
git commit -m "feat: template election/web2025/detail"
```

---

## Task 13: Template web2025/kresla.phtml

**Files:**
- Create: `templates/election/web2025/kresla.phtml`

Referenční soubor: `polar/module/Election/view/election/web2025/web-list/kresla.phtml`

- [ ] **Step 1: Přečíst + vytvořit + commit**

```bash
php -l templates/election/web2025/kresla.phtml
git add templates/election/web2025/kresla.phtml
git commit -m "feat: template election/web2025/kresla"
```

---

## Task 14: Template web2025/poslanci.phtml

**Files:**
- Create: `templates/election/web2025/poslanci.phtml`

Referenční soubor: `polar/module/Election/view/election/web2025/web-list/poslanci.phtml`

- [ ] **Step 1: Přečíst + vytvořit + commit**

```bash
php -l templates/election/web2025/poslanci.phtml
git add templates/election/web2025/poslanci.phtml
git commit -m "feat: template election/web2025/poslanci"
```

---

## Task 15: Template web2025/obec.phtml

**Files:**
- Create: `templates/election/web2025/obec.phtml`

Referenční soubor: `polar/module/Election/view/election/web2025/web-list/obec.phtml`

- [ ] **Step 1: Přečíst + vytvořit + commit**

```bash
php -l templates/election/web2025/obec.phtml
git add templates/election/web2025/obec.phtml
git commit -m "feat: template election/web2025/obec"
```

---

## Task 16: Template web2025/senat.phtml

**Files:**
- Create: `templates/election/web2025/senat.phtml`

Referenční soubor: `polar/module/Election/view/election/web2025/web-list/senat.phtml`

- [ ] **Step 1: Přečíst + vytvořit + commit**

```bash
php -l templates/election/web2025/senat.phtml
git add templates/election/web2025/senat.phtml
git commit -m "feat: template election/web2025/senat"
```

---

## Task 17: Web2024Controller

**Files:**
- Create: `src/Election/Controller/Web/Web2024Controller.php`

Referenční soubor: `polar/module/Election/src/Controller/Web2024/WebListController.php`

- [ ] **Step 1: Přečíst celý zdrojový soubor**

```
read_file polar/module/Election/src/Controller/Web2024/WebListController.php (celý)
```

- [ ] **Step 2: Vytvořit soubor** — stejný vzor jako `Web2025Controller`, ale:
  - namespace/class: `Web2024Controller`
  - `ElectionRepository2024` + `Election2024PlaykitRepository`
  - `$colors` array z 2024 controlleru
  - metoda `zastupitele()` místo `poslanci()`
  - topic URL: `'krajske-volby-2024'`
  - redirect route: `election_2024`
  - `ogImage`: `/img/web/volby/microformat_2024.png`
  - `pageTitle` prefix: `'Volby 2024'`
  - `psrkl` → `kzrkl`, `psrkl_item` → `kzrkl_item`, metody `fetchKzrklAll()`, `findKzrklPostBy()` atd.
  - template prefix: `election/web2024/`

- [ ] **Step 3: Ověřit syntax + commit**

```bash
php -l src/Election/Controller/Web/Web2024Controller.php
git add src/Election/Controller/Web/Web2024Controller.php
git commit -m "feat: Web2024Controller"
```

---

## Task 18: Templates web2024 (6 šablon)

Referenční soubory: `polar/module/Election/view/election/web2024/web-list/*.phtml`

Pro každou šablonu: přečíst originál → zkopírovat 1:1 → adaptovat `$this->*` → zkontrolovat → commit.

- [ ] **Task 18a: web2024/index.phtml**

```
read_file polar/module/Election/view/election/web2024/web-list/index.phtml
```
```bash
php -l templates/election/web2024/index.phtml
git add templates/election/web2024/index.phtml
git commit -m "feat: template election/web2024/index"
```

- [ ] **Task 18b: web2024/detail.phtml**

```
read_file polar/module/Election/view/election/web2024/web-list/detail.phtml
```
```bash
git add templates/election/web2024/detail.phtml
git commit -m "feat: template election/web2024/detail"
```

- [ ] **Task 18c: web2024/kresla.phtml**

```
read_file polar/module/Election/view/election/web2024/web-list/kresla.phtml
```
```bash
git add templates/election/web2024/kresla.phtml
git commit -m "feat: template election/web2024/kresla"
```

- [ ] **Task 18d: web2024/zastupitele.phtml**

```
read_file polar/module/Election/view/election/web2024/web-list/zastupitele.phtml
```
```bash
git add templates/election/web2024/zastupitele.phtml
git commit -m "feat: template election/web2024/zastupitele"
```

- [ ] **Task 18e: web2024/obec.phtml**

```
read_file polar/module/Election/view/election/web2024/web-list/obec.phtml
```
```bash
git add templates/election/web2024/obec.phtml
git commit -m "feat: template election/web2024/obec"
```

- [ ] **Task 18f: web2024/senat.phtml**

```
read_file polar/module/Election/view/election/web2024/web-list/senat.phtml
```
```bash
git add templates/election/web2024/senat.phtml
git commit -m "feat: template election/web2024/senat"
```

---

## Task 19: Web2020Controller + template

**Files:**
- Create: `src/Election/Controller/Web/Web2020Controller.php`
- Create: `templates/election/web2020/kresla.phtml`

Referenční soubory: `polar/module/Election/src/Controller/Web2020/WebListController.php`, `polar/module/Election/view/election/web2020/web-list/kresla.phtml`

- [ ] **Step 1: Přečíst oba zdrojové soubory**

- [ ] **Step 2: Vytvořit controller**

```php
<?php
/*
 * @project polar
 * ...
 */
declare(strict_types=1);

namespace App\Election\Controller\Web;

use App\Application\View\PhtmlRenderer;
use App\Election\Repository\Election2020PlaykitRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class Web2020Controller
{
    private array $colors = [
        45 => '#E63812',
        63 => '#8c0000',
        12 => '#f3c308',
        82 => '#C80000',
        67 => '#37583C',
        70 => '#11457e',
        50 => '#261060',
        29 => '#004494',
        19 => '#343434',
        22 => '#feca0a',
        16 => '#2175bb',
        79 => '#27205f',
        37 => '#cccccc',
        28 => '#0083CB',
        5  => '#cccccc',
        57 => '#cccccc',
        38 => '#EC461E',
        54 => '#84c4f0',
        81 => '#cccccc',
        14 => '#343434'
    ];

    public function __construct(
        private Election2020PlaykitRepository $electionPlaykitRepository,
        private PhtmlRenderer $renderer,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function index(): Response
    {
        return new RedirectResponse($this->urlGenerator->generate('election_2020_kresla'));
    }

    public function kresla(): Response
    {
        try {
            $elections = $this->electionPlaykitRepository->fetchAllKreslaForWeb();
            $elections_results = (array) $this->electionPlaykitRepository->getResultsTotal();
            for ($i = 0, $iMax = count($elections); $i < $iMax; $i++) {
                $elections[$i]['barva'] = $this->colors[$elections[$i]['KSTRANA']] ?? '#ccc';
            }
        } catch (\Exception $ex) {
            return new RedirectResponse($this->urlGenerator->generate('election_2024'));
        }

        return new Response($this->renderer->render('election/web2020/kresla', [
            'elections'         => $elections,
            'elections_results' => $elections_results,
            'pageTitle'         => 'Rozdělení křesel | Volby 2020',
        ]));
    }
}
```

- [ ] **Step 3: Vytvořit template** `templates/election/web2020/kresla.phtml` — kopie z originálu s adaptacemi.

- [ ] **Step 4: Ověřit syntax + commit**

```bash
php -l src/Election/Controller/Web/Web2020Controller.php
php -l templates/election/web2020/kresla.phtml
git add src/Election/Controller/Web/Web2020Controller.php templates/election/web2020/kresla.phtml
git commit -m "feat: Web2020Controller + template kresla"
```

---

## Task 20: Admin/ElectionListController

**Files:**
- Create: `src/Election/Controller/Admin/ElectionListController.php`

Referenční soubor: `polar/module/Election/src/Controller/Election2025/ElectionListController.php`

- [ ] **Step 1: Vytvořit soubor**

```php
<?php
/*
 * @project polar
 * ...
 */
declare(strict_types=1);

namespace App\Election\Controller\Admin;

use App\Application\View\PhtmlRenderer;
use App\Election\Repository\ElectionRepository2025;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ElectionListController
{
    public function __construct(
        private ElectionRepository2025 $electionRepository,
        private PhtmlRenderer $renderer,
    ) {}

    public function index(): Response
    {
        return new Response($this->renderer->renderWithAdminLayout('election/admin/index', [
            'pageTitle'     => 'Elections',
            'countElection' => $this->electionRepository->getCount(),
        ]));
    }

    public function list(Request $request): Response
    {
        // Flash messages zpracovány v šabloně přes session flash bag
        return new Response($this->renderer->renderWithAdminLayout('election/admin/list', [
            'pageTitle' => 'Elections',
        ]));
    }

    public function getList(Request $request): JsonResponse
    {
        $params = $request->query->all();
        $rows   = null;
        $total  = 0;
        $success = true;

        try {
            $rows  = $this->electionRepository->fetchForBootstrapTable($params);
            $total = $this->electionRepository->getCountForBootstrapTable($params);
        } catch (\Exception $e) {
            $success = $e->getMessage();
        }

        return new JsonResponse([
            'success' => $success,
            'rows'    => $rows,
            'total'   => $total,
        ]);
    }

    public function getElection(Request $request): JsonResponse
    {
        $success  = true;
        $message  = null;
        $election = null;

        try {
            $id       = $request->request->getInt('id');
            $election = $this->electionRepository->findPostBy('id', $id);
        } catch (\Exception $e) {
            $success = false;
            $message = $e->getMessage();
        }

        return new JsonResponse([
            'success'  => $success,
            'message'  => $message,
            'election' => $election,
        ]);
    }
}
```

- [ ] **Step 2: Ověřit syntax + commit**

```bash
php -l src/Election/Controller/Admin/ElectionListController.php
git add src/Election/Controller/Admin/ElectionListController.php
git commit -m "feat: Admin/ElectionListController"
```

---

## Task 21: Admin/ElectionWriteController

**Files:**
- Create: `src/Election/Controller/Admin/ElectionWriteController.php`

Referenční soubor: `polar/module/Election/src/Controller/Election2025/ElectionWriteController.php`

- [ ] **Step 1: Přečíst celý zdrojový soubor (addAction, editAction, deleteElectionAction, setOrderAction)**

- [ ] **Step 2: Vytvořit soubor**

```php
<?php
/*
 * @project polar
 * ...
 */
declare(strict_types=1);

namespace App\Election\Controller\Admin;

use App\Application\View\PhtmlRenderer;
use App\Election\Repository\Election2025PlaykitRepository;
use App\Election\Repository\ElectionCommand2025;
use App\Election\Repository\ElectionRepository2025;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ElectionWriteController
{
    public function __construct(
        private ElectionRepository2025 $electionRepository,
        private ElectionCommand2025 $electionCommand,
        private Election2025PlaykitRepository $electionPlaykitRepository,
        private PhtmlRenderer $renderer,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $logger,
    ) {}

    public function add(Request $request): Response|RedirectResponse
    {
        $title_options = $this->electionPlaykitRepository->fetchPsrklForBootstrapSelect();
        $error = null;

        if ($request->isMethod('POST')) {
            $post = $request->request->all();

            if (isset($post['cancel'])) {
                return new RedirectResponse($this->urlGenerator->generate('admin_election_list'));
            }

            try {
                $id = $this->electionCommand->insertPost([
                    'title'       => $post['title'] ?? '',
                    'description' => $post['description'] ?? '',
                    'video_id'    => !empty($post['video_id']) ? (int) $post['video_id'] : null,
                    'rank'        => (int) ($post['rank'] ?? 0),
                ]);

                $request->getSession()->getFlashBag()->add('success', 'Elections');
                $request->getSession()->getFlashBag()->add('success_msg', 'Election přidáno');

                // Log
                $this->logger->notice('ELECTION - Add election', [
                    'description' => 'OK',
                    'file' => __FILE__,
                ]);

                return new RedirectResponse($this->urlGenerator->generate('admin_election_list'));
            } catch (\Exception $e) {
                $error = $e->getMessage();

                // Log
                $this->logger->error('ELECTION - Add election', [
                    'description' => 'ERROR',
                    'file' => __FILE__,
                    'trace' => $e->getMessage(),
                ]);
            }
        }

        return new Response($this->renderer->renderWithAdminLayout('election/admin/add', [
            'pageTitle'     => 'Elections',
            'title_options' => $title_options,
            'error'         => $error,
        ]));
    }

    public function edit(Request $request, int $id): Response|RedirectResponse
    {
        if ($id === 0) {
            return new RedirectResponse($this->urlGenerator->generate('admin_election_add'));
        }

        try {
            $election = $this->electionRepository->findPostBy('id', $id);
        } catch (\Exception) {
            return new RedirectResponse($this->urlGenerator->generate('admin_election_list'));
        }

        $title_options = $this->electionPlaykitRepository->fetchPsrklForBootstrapSelect();
        $error = null;

        if ($request->isMethod('POST')) {
            $post = $request->request->all();

            if (isset($post['cancel'])) {
                return new RedirectResponse($this->urlGenerator->generate('admin_election_list'));
            }

            try {
                $this->electionCommand->updatePost([
                    'id'          => $id,
                    'title'       => $post['title'] ?? $election['title'],
                    'description' => $post['description'] ?? $election['description'],
                    'video_id'    => !empty($post['video_id']) ? (int) $post['video_id'] : null,
                    'rank'        => (int) ($post['rank'] ?? $election['rank']),
                ]);

                $request->getSession()->getFlashBag()->add('success', 'Elections');
                $request->getSession()->getFlashBag()->add('success_msg',
                    'Elections <strong>"' . htmlspecialchars($election['title']) . '"</strong> upraveno');

                // Log
                $this->logger->notice('ELECTION - Edit election', [
                    'description' => 'OK',
                    'file' => __FILE__,
                ]);

                return new RedirectResponse($this->urlGenerator->generate('admin_election_list'));
            } catch (\Exception $e) {
                $error = $e->getMessage();

                // Log
                $this->logger->error('ELECTION - Edit election', [
                    'description' => 'ERROR',
                    'file' => __FILE__,
                    'trace' => $e->getMessage(),
                ]);
            }
        }

        return new Response($this->renderer->renderWithAdminLayout('election/admin/edit', [
            'pageTitle'     => 'Elections',
            'election'      => $election,
            'title_options' => $title_options,
            'error'         => $error,
        ]));
    }

    public function deleteElection(Request $request): JsonResponse
    {
        $success     = true;
        $message     = null;
        $election_id = null;

        try {
            $election_id = $request->request->getInt('id');
            $election    = $this->electionRepository->findPostBy('id', $election_id);

            if ($election) {
                $this->electionCommand->deletePost($election);

                $elections = $this->electionRepository->fetchAll();
                $this->electionCommand->reorderAll($elections);

                // Log
                $this->logger->notice('ELECTION - Delete election', [
                    'description' => 'OK',
                    'file' => __FILE__,
                ]);
            } else {
                $success = false;
                $message = 'Cannot find election';

                // Log
                $this->logger->error('ELECTION - Delete election', [
                    'description' => 'ERROR',
                    'file' => __FILE__,
                    'trace' => $message,
                ]);
            }
        } catch (\Exception $e) {
            $success = false;
            $message = $e->getMessage();

            // Log
            $this->logger->error('ELECTION - Delete election', [
                'description' => 'ERROR',
                'file' => __FILE__,
                'trace' => $message,
            ]);
        }

        return new JsonResponse([
            'success'     => $success,
            'message'     => $message,
            'election_id' => $election_id,
        ]);
    }

    public function setOrder(Request $request): JsonResponse
    {
        $data    = $request->request->all('data');
        $success = true;
        $message = null;

        try {
            $rank = 1;
            foreach ($data as $item) {
                $election = $this->electionRepository->findPostBy('id', $item['id']);
                $this->electionCommand->updatePost(array_merge($election, ['rank' => $rank]));
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
}
```

- [ ] **Step 3: Ověřit syntax + commit**

```bash
php -l src/Election/Controller/Admin/ElectionWriteController.php
git add src/Election/Controller/Admin/ElectionWriteController.php
git commit -m "feat: Admin/ElectionWriteController"
```

---

## Task 22: Admin/SettingWriteController

**Files:**
- Create: `src/Election/Controller/Admin/SettingWriteController.php`

Referenční soubor: `polar/module/Election/src/Controller/Setting/SettingWriteController.php`

- [ ] **Step 1: Vytvořit soubor**

```php
<?php
/*
 * @project polar
 * ...
 */
declare(strict_types=1);

namespace App\Election\Controller\Admin;

use App\Application\View\PhtmlRenderer;
use App\Election\Repository\ElectionSettingCommand;
use App\Election\Repository\ElectionSettingRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class SettingWriteController
{
    public function __construct(
        private ElectionSettingRepository $settingRepository,
        private ElectionSettingCommand $settingCommand,
        private PhtmlRenderer $renderer,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function setting(Request $request): Response|RedirectResponse
    {
        try {
            //$setting = $this->settingRepository->fetchSetting();
        } catch (\Exception) {
            return new RedirectResponse($this->urlGenerator->generate('admin'));
        }

        //$identity = ...

        return new Response($this->renderer->renderWithAdminLayout('election/admin/setting', [
            'pageTitle' => 'Setting | Elections',
        ]));
    }

    public function jsonList(Request $request, string $action): JsonResponse
    {
        return new JsonResponse(['success' => true]);
    }

    public function jsonWrite(Request $request, string $action): JsonResponse
    {
        return new JsonResponse(['success' => true]);
    }
}
```

- [ ] **Step 2: Ověřit syntax + commit**

```bash
php -l src/Election/Controller/Admin/SettingWriteController.php
git add src/Election/Controller/Admin/SettingWriteController.php
git commit -m "feat: Admin/SettingWriteController"
```

---

## Task 23: Admin templates (5 šablon)

Referenční soubory: `polar/module/Election/view/election/election2025/`

Pro každou šablonu: přečíst originál → zkopírovat 1:1 → adaptovat → ověřit → commit.

**Adaptace navíc pro admin šablony:**
- `$this->layout('layout/admin')` → odstraněno (volá se `renderWithAdminLayout`)
- Flash zprávy: `$this->flashMessenger()->...` → `$app->getSession()->getFlashBag()->get('success')` (nebo zpracovat přes layout)

- [ ] **Task 23a: election/admin/index.phtml**

```
read_file polar/module/Election/view/election/election2025/election-list/index.phtml
```
```bash
git add templates/election/admin/index.phtml
git commit -m "feat: template election/admin/index"
```

- [ ] **Task 23b: election/admin/list.phtml**

```
read_file polar/module/Election/view/election/election2025/election-list/list.phtml
```
```bash
git add templates/election/admin/list.phtml
git commit -m "feat: template election/admin/list"
```

- [ ] **Task 23c: election/admin/add.phtml**

```
read_file polar/module/Election/view/election/election2025/election-write/add.phtml
```
```bash
git add templates/election/admin/add.phtml
git commit -m "feat: template election/admin/add"
```

- [ ] **Task 23d: election/admin/edit.phtml**

```
read_file polar/module/Election/view/election/election2025/election-write/edit.phtml
```
```bash
git add templates/election/admin/edit.phtml
git commit -m "feat: template election/admin/edit"
```

- [ ] **Task 23e: election/admin/setting.phtml**

```
read_file polar/module/Election/view/election/setting/setting-write/setting.phtml
```
```bash
git add templates/election/admin/setting.phtml
git commit -m "feat: template election/admin/setting"
```

---

## Task 24: Cache clear + ověření

- [ ] **Step 1: Cache clear**

```bash
php bin/console cache:clear
```

Očekáváno: `[OK] Cache for the "dev" environment (debug=true) was successfully cleared.`

- [ ] **Step 2: Ověřit routy**

```bash
php bin/console debug:router 2>&1 | Select-String "election"
```

Očekáváno: ~25 election rout.

- [ ] **Step 3: Ověřit PHP syntax všech souborů**

```bash
Get-ChildItem -Path "src/Election" -Recurse -Filter "*.php" | ForEach-Object { php -l $_.FullName }
```

Očekáváno: všechny `No syntax errors detected`.

- [ ] **Step 4: Commit**

```bash
git add .
git commit -m "feat: election module migration complete"
```

---

## Poznámky k ověření po spuštění

- **Web 2025 index** `/volby` – zkontrolovat výpis studií a článků
- **Web 2025 kresla** `/volby/kresla` – zkontrolovat graf křesel
- **Web 2025 obec** `/volby/okres/8106` – zkontrolovat výsledky okresu  
- **Admin election list** `/admin/volby/seznam` – zkontrolovat Bootstrap Table
- **Admin election add** `/admin/volby/seznam/pridat` – zkontrolovat select stran
- **Playkit spojení** – pokud volební data chybí, ověř `doctrine.dbal.playkit_connection` v `.env`
