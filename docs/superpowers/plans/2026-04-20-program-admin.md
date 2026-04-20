# Program modul — Administrace — Implementační plán

> **For agentic workers:** REQUIRED: Use the `subagent-driven-development` agent (recommended) or `executing-plans` agent to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Migrovat admin část modulu Program z Laminas do Symfony (1:1 kopie)

**Architecture:** Modul Program spravuje TV program (pořady), videa a nastavení. Admin má 5 submodulů: Program (vysílací program), Video (videa k pořadům), Videoex (mimořádná videa), Show/Showex (pořady/mimořádné pořady), Setting (nastavení). Každý má List controller (read, JSON endpointy) a Write controller (CRUD, file operace). Klíčová funkce `loadVideos()` přesouvá video soubory z nepublikovaných do publikovaných a vytváří náhledy.

**Tech Stack:** Symfony 8, PHP 8.5, Doctrine DBAL, PhtmlRenderer, Imagine, ffmpeg (exec)

**Důležité konstanty:**
- `PUBLIC_PATH` → `%app.PUBLIC_PATH%` (= `%kernel.project_dir%/public`)
- `LIGHT_PATH` → nový parametr `%app.LIGHT_PATH%` (cesta k video souborům na light serveru)

**DB tabulky:**
- `program` — vysílací program (propojení na video přes video_id)
- `program_videos` — videa k pořadům
- `program_shows` — pořady (regulérní)
- `program_shows_times` — vysílací časy pořadů
- `program_shows_categories` — kategorie pořadů
- `program2shows` — vazba program↔show (M:N)
- `special_videos` — mimořádná videa
- `special_videos_parts` — části mimořádných videí
- `special_shows` — mimořádné pořady
- `program_setting` — nastavení modulu

---

## Přehled souborů

### Nové soubory k vytvoření:

**Repositories:**
- `src/Program/Repository/VideoRepository.php` — CRUD program_videos
- `src/Program/Repository/VideoexRepository.php` — CRUD special_videos + special_videos_parts
- `src/Program/Repository/ShowRepository.php` — CRUD program_shows, program_shows_times, program_shows_categories
- `src/Program/Repository/ShowexRepository.php` — CRUD special_shows
- `src/Program/Repository/SettingRepository.php` — CRUD program_setting

**Controllers (Admin):**
- `src/Program/Controller/Admin/ProgramListController.php` — index, list, getList, getProgram, getUrl
- `src/Program/Controller/Admin/ProgramWriteController.php` — add, edit, deleteProgram, newton, exportShows
- `src/Program/Controller/Admin/VideoListController.php` — list, getList, getVideo
- `src/Program/Controller/Admin/VideoWriteController.php` — edit, deleteVideo, **loadVideos()**, createPreview, uploadPreviewFromPc
- `src/Program/Controller/Admin/VideoexListController.php` — list, getList, getListParts, getVideo, getVideoPart, getUrl
- `src/Program/Controller/Admin/VideoexWriteController.php` — edit, deleteVideo, **loadVideos()**, setPart, deleteVideoPart, createPreview
- `src/Program/Controller/Admin/ShowListController.php` — list, getList, getTimesList, getShow, getUrl, redactorImageManager, redactorFileManager
- `src/Program/Controller/Admin/ShowWriteController.php` — add, edit, deleteShow, setOrder, setTime, uploadImage, setDefaultImage, redactorImageUpload, redactorFileUpload
- `src/Program/Controller/Admin/ShowexListController.php` — list, getList, getShow, getUrl, redactorImageManager, redactorFileManager
- `src/Program/Controller/Admin/ShowexWriteController.php` — add, edit, deleteShow, setOrder, uploadImage, setDefaultImage, redactorImageUpload, redactorFileUpload
- `src/Program/Controller/Admin/SettingController.php` — index, setting

**Routes:**
- `config/routes/program_admin.yaml`

**Templates (admin):**
- `templates/program/admin/index.phtml`
- `templates/program/admin/program-list.phtml`
- `templates/program/admin/program-add.phtml`
- `templates/program/admin/program-edit.phtml`
- `templates/program/admin/newton.phtml`
- `templates/program/admin/video-list.phtml`
- `templates/program/admin/video-edit.phtml`
- `templates/program/admin/videoex-list.phtml`
- `templates/program/admin/videoex-edit.phtml`
- `templates/program/admin/show-list.phtml`
- `templates/program/admin/show-add.phtml`
- `templates/program/admin/show-edit.phtml`
- `templates/program/admin/showex-list.phtml`
- `templates/program/admin/showex-add.phtml`
- `templates/program/admin/showex-edit.phtml`
- `templates/program/admin/setting.phtml`

**Modifikované soubory:**
- `config/services.yaml` — přidání LIGHT_PATH parametru + Program controller bindings

---

## Task 1: Konfigurace — parametry a services

**Files:**
- Modify: `config/services.yaml`
- Modify: `.env`

- [ ] **Step 1: Přidat LIGHT_PATH parametr do `.env`**

```
LIGHT_PATH=/mnt/light/
```

- [ ] **Step 2: Přidat LIGHT_PATH do services.yaml parametrů**

Do sekce `parameters:` přidat:
```yaml
app.LIGHT_PATH: '%env(LIGHT_PATH)%'
```

- [ ] **Step 3: Přidat Program controller binding do services.yaml**

```yaml
App\Program\Controller\:
    resource: '../src/Program/Controller/'
    tags: ['controller.service_arguments']
    bind:
        $PUBLIC_PATH: '%app.PUBLIC_PATH%'
        $LIGHT_PATH: '%app.LIGHT_PATH%'
```

- [ ] **Step 4: Přidat LIGHT_PATH do `.env.local` se skutečnou hodnotou**

- [ ] **Step 5: Cache clear + ověřit**

```bash
php bin/console cache:clear
```

- [ ] **Step 6: Commit**

```bash
git add config/services.yaml .env
git commit -m "feat(program): add LIGHT_PATH parameter and Program controller binding"
```

---

## Task 2: SettingRepository

**Files:**
- Create: `src/Program/Repository/SettingRepository.php`

- [ ] **Step 1: Vytvořit SettingRepository**

```php
<?php

namespace App\Program\Repository;

use Doctrine\DBAL\Connection;

final class SettingRepository
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function fetchSetting(): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('program_setting')
            ->executeQuery()
            ->fetchAllAssociative();

        $setting = [];
        foreach ($rows as $row) {
            $setting[$row['name']] = $row['value'];
        }
        return $setting;
    }

    public function updateSetting(string $name, string $value): void
    {
        $this->connection->createQueryBuilder()
            ->update('program_setting')
            ->set('value', ':value')
            ->where('name = :name')
            ->setParameter('value', $value)
            ->setParameter('name', $name)
            ->executeStatement();
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Program/Repository/SettingRepository.php
git commit -m "feat(program): add SettingRepository"
```

---

## Task 3: ProgramRepository (rozšíření existujícího)

**Files:**
- Modify: `src/Program/Repository/ProgramRepository.php`

- [ ] **Step 1: Přidat admin metody do ProgramRepository**

Přidat metody:
- `fetchForBootstrapTable(array $params): array` — parametrizovaný dotaz s LIMIT/OFFSET/ORDER/SEARCH
- `getCountForBootstrapTable(array $params): int` — počet záznamů
- `findPostBy(string $column, mixed $value): ?array` — jeden záznam
- `findPostsBy(string $column, mixed $value): array` — více záznamů
- `insertPost(array $data): int` — vytvoření záznamu, vrací ID
- `updatePost(int $id, array $data): void`
- `deletePost(int $id): void`
- `getCount(): int` — celkový počet programů
- `getCountPremieres(): int` — počet premiér
- `generateUrl(string $title, string $date, string $time): string` — generování URL slugu

(Přesný kód metod bude převzat 1:1 z Laminas MariaDbSqlRepository/Command, přepsáno na DBAL QueryBuilder.)

- [ ] **Step 2: Commit**

```bash
git add src/Program/Repository/ProgramRepository.php
git commit -m "feat(program): add admin methods to ProgramRepository"
```

---

## Task 4: VideoRepository

**Files:**
- Create: `src/Program/Repository/VideoRepository.php`

- [ ] **Step 1: Vytvořit VideoRepository**

Metody:
- `fetchForBootstrapTable(array $params): array`
- `getCountForBootstrapTable(array $params): int`
- `findPostBy(string $column, mixed $value): ?array`
- `insertPost(array $data): int`
- `deletePost(int $id): void`
- `getCount(): int`

Tabulka: `program_videos`

- [ ] **Step 2: Commit**

```bash
git add src/Program/Repository/VideoRepository.php
git commit -m "feat(program): add VideoRepository"
```

---

## Task 5: VideoexRepository

**Files:**
- Create: `src/Program/Repository/VideoexRepository.php`

- [ ] **Step 1: Vytvořit VideoexRepository**

Metody:
- `fetchForBootstrapTable(array $params): array`
- `getCountForBootstrapTable(array $params): int`
- `findPostBy(string $column, mixed $value): ?array`
- `insertPost(array $data): int`
- `updatePost(int $id, array $data): void`
- `deletePost(int $id): void`
- `getCount(): int`
- `fetchPartsForBootstrapTable(int $videoId, array $params): array`
- `getCountPartsForBootstrapTable(int $videoId, array $params): int`
- `findPartBy(string $column, mixed $value): ?array`
- `insertPostPart(int $videoId, int $secFrom, int $secTo, string $title): void`
- `updatePostPart(int $partId, int $videoId, int $secFrom, int $secTo, string $title): void`
- `deletePostPart(int $partId): void`

Tabulky: `special_videos`, `special_videos_parts`

- [ ] **Step 2: Commit**

```bash
git add src/Program/Repository/VideoexRepository.php
git commit -m "feat(program): add VideoexRepository"
```

---

## Task 6: ShowRepository

**Files:**
- Create: `src/Program/Repository/ShowRepository.php`

- [ ] **Step 1: Vytvořit ShowRepository**

Metody:
- `fetchForBootstrapTable(array $params): array`
- `getCountForBootstrapTable(array $params): int`
- `fetchTimesForBootstrapTable(int $showId, array $params): array`
- `getCountTimesForBootstrapTable(int $showId, array $params): int`
- `findPostBy(string $column, mixed $value): ?array`
- `insertPost(array $data): int`
- `updatePost(int $id, array $data): void`
- `deletePost(int $id): void`
- `getCount(): int`
- `fetchCategories(): array`
- `setTime(int $showId, string $day, string $time): void`
- `deleteTime(int $showId, string $day): void`
- `fetchForBootstrapSelect(int $limit): array` — pro select dropdown

Tabulky: `program_shows`, `program_shows_times`, `program_shows_categories`

- [ ] **Step 2: Commit**

```bash
git add src/Program/Repository/ShowRepository.php
git commit -m "feat(program): add ShowRepository"
```

---

## Task 7: ShowexRepository

**Files:**
- Create: `src/Program/Repository/ShowexRepository.php`

- [ ] **Step 1: Vytvořit ShowexRepository**

Metody (analogicky k ShowRepository, zjednodušené):
- `fetchForBootstrapTable(array $params): array`
- `getCountForBootstrapTable(array $params): int`
- `findPostBy(string $column, mixed $value): ?array`
- `insertPost(array $data): int`
- `updatePost(int $id, array $data): void`
- `deletePost(int $id): void`
- `getCount(): int`
- `fetchCategories(): array`
- `fetchForBootstrapSelect(int $limit): array`

Tabulka: `special_shows`

- [ ] **Step 2: Commit**

```bash
git add src/Program/Repository/ShowexRepository.php
git commit -m "feat(program): add ShowexRepository"
```

---

## Task 8: SettingController (admin)

**Files:**
- Create: `src/Program/Controller/Admin/SettingController.php`
- Create: `templates/program/admin/setting-index.phtml`
- Create: `templates/program/admin/setting.phtml`

- [ ] **Step 1: Vytvořit SettingController**

Metody:
- `index()` — zobrazí nastavení (read-only dashboard s počty)
- `setting()` — formulář nastavení s POST uložením + Imagine generováním default obrázku

DI: `Logger`, `SettingRepository`, `ProgramRepository`, `VideoRepository`, `VideoexRepository`, `ShowRepository`, `ShowexRepository`, `PhtmlRenderer`, `Security`, `$PUBLIC_PATH`

- [ ] **Step 2: Vytvořit šablony (kopie z Laminas)**

- [ ] **Step 3: Commit**

```bash
git add src/Program/Controller/Admin/SettingController.php templates/program/admin/setting-index.phtml templates/program/admin/setting.phtml
git commit -m "feat(program): add admin SettingController"
```

---

## Task 9: ProgramListController (admin)

**Files:**
- Create: `src/Program/Controller/Admin/ProgramListController.php`
- Create: `templates/program/admin/index.phtml`
- Create: `templates/program/admin/program-list.phtml`

- [ ] **Step 1: Vytvořit ProgramListController**

Metody:
- `index()` → Response — dashboard s počítadly (programy, premiéry, videa, pořady)
- `list()` → Response — bootstrap tabulka programů
- `getList()` → JsonResponse — AJAX pro bootstrap-table
- `getProgram()` → JsonResponse — detail jednoho záznamu
- `getUrl()` → JsonResponse — generování URL z title+date+time

DI: `Logger`, `ProgramRepository`, `VideoRepository`, `ShowRepository`, `SettingRepository`, `PhtmlRenderer`, `Security`

- [ ] **Step 2: Vytvořit šablony (kopie z Laminas)**

- [ ] **Step 3: Commit**

```bash
git add src/Program/Controller/Admin/ProgramListController.php templates/program/admin/index.phtml templates/program/admin/program-list.phtml
git commit -m "feat(program): add admin ProgramListController"
```

---

## Task 10: ProgramWriteController (admin)

**Files:**
- Create: `src/Program/Controller/Admin/ProgramWriteController.php`
- Create: `templates/program/admin/program-add.phtml`
- Create: `templates/program/admin/program-edit.phtml`
- Create: `templates/program/admin/newton.phtml`

- [ ] **Step 1: Vytvořit ProgramWriteController**

Metody:
- `add()` → Response — formulář přidání programu (POST → insert)
- `edit()` → Response — formulář editace programu (POST → update)
- `deleteProgram()` → JsonResponse — smazání záznamu
- `newton()` → Response — Newton export/import stránka
- `exportShows()` → JsonResponse — export pořadů

DI: `Logger`, `FlashMessenger`, `ProgramRepository`, `VideoRepository`, `ShowRepository`, `SettingRepository`, `PhtmlRenderer`, `Security`, `UrlGeneratorInterface`

- [ ] **Step 2: Vytvořit šablony**

- [ ] **Step 3: Commit**

```bash
git add src/Program/Controller/Admin/ProgramWriteController.php templates/program/admin/program-add.phtml templates/program/admin/program-edit.phtml templates/program/admin/newton.phtml
git commit -m "feat(program): add admin ProgramWriteController"
```

---

## Task 11: VideoListController (admin)

**Files:**
- Create: `src/Program/Controller/Admin/VideoListController.php`
- Create: `templates/program/admin/video-list.phtml`

- [ ] **Step 1: Vytvořit VideoListController**

Metody:
- `list()` → Response — bootstrap tabulka videí
- `getList()` → JsonResponse — AJAX data
- `getVideo()` → JsonResponse — detail videa

DI: `VideoRepository`, `PhtmlRenderer`, `Security`

- [ ] **Step 2: Vytvořit šablonu**

- [ ] **Step 3: Commit**

```bash
git add src/Program/Controller/Admin/VideoListController.php templates/program/admin/video-list.phtml
git commit -m "feat(program): add admin VideoListController"
```

---

## Task 12: VideoWriteController + loadVideos() (admin) ⭐

**Files:**
- Create: `src/Program/Controller/Admin/VideoWriteController.php`
- Create: `templates/program/admin/video-edit.phtml`

- [ ] **Step 1: Vytvořit VideoWriteController**

Metody:
- `edit()` → Response — formulář editace videa
- `deleteVideo()` → JsonResponse — smazání videa + souborů + odpojení od programu
- `loadVideos()` → JsonResponse — **KRITICKÁ FUNKCE** (1:1 kopie z Laminas)
- `createPreview()` → JsonResponse — vytvoření náhledu z videa (ffmpeg)
- `uploadPreviewFromPc()` → JsonResponse — nahrání JPG náhledu z PC

**loadVideos() logika (1:1):**
1. Čte `LIGHT_PATH/porady/nepublikovano/` — hledá páry `_hq.mp4` + `_lq.mp4`
2. Pro každý pár: najde matching program podle `file` sloupce
3. Přesune soubory do `LIGHT_PATH/porady/publikovano/YYYY/MM/DD/`
4. Vytvoří 2 JPG náhledy (460x259 + 260x146) přes ffmpeg
5. Vloží záznam do `program_videos`
6. Propojí video_id s programem(y)
7. Aktualizuje `program_setting.video_update_date`

Private helpers:
- `createPreview(string $file, int $sec): void` — ffmpeg exec
- `createPreviewsFromUpload(string $file, string $target): void` — Imagine resize
- `getDurationFromLight(string $file): ?int` — ffprobe exec

DI: `Logger`, `ProgramRepository`, `VideoRepository`, `SettingRepository`, `PhtmlRenderer`, `Security`, `$PUBLIC_PATH`, `$LIGHT_PATH`

- [ ] **Step 2: Vytvořit šablonu video-edit**

- [ ] **Step 3: Commit**

```bash
git add src/Program/Controller/Admin/VideoWriteController.php templates/program/admin/video-edit.phtml
git commit -m "feat(program): add admin VideoWriteController with loadVideos()"
```

---

## Task 13: VideoexListController (admin)

**Files:**
- Create: `src/Program/Controller/Admin/VideoexListController.php`
- Create: `templates/program/admin/videoex-list.phtml`

- [ ] **Step 1: Vytvořit VideoexListController**

Metody:
- `list()` → Response
- `getList()` → JsonResponse
- `getListParts()` → JsonResponse — části videa
- `getVideo()` → JsonResponse
- `getVideoPart()` → JsonResponse
- `getUrl()` → JsonResponse

DI: `VideoexRepository`, `PhtmlRenderer`, `Security`

- [ ] **Step 2: Vytvořit šablonu**

- [ ] **Step 3: Commit**

```bash
git add src/Program/Controller/Admin/VideoexListController.php templates/program/admin/videoex-list.phtml
git commit -m "feat(program): add admin VideoexListController"
```

---

## Task 14: VideoexWriteController + loadVideos() (admin) ⭐

**Files:**
- Create: `src/Program/Controller/Admin/VideoexWriteController.php`
- Create: `templates/program/admin/videoex-edit.phtml`

- [ ] **Step 1: Vytvořit VideoexWriteController**

Metody:
- `edit()` → Response — editace mimořádného videa (form + POST)
- `deleteVideo()` → JsonResponse
- `loadVideos()` → JsonResponse — **KRITICKÁ FUNKCE** (1:1 kopie)
- `setPart()` → JsonResponse — přidání/úprava části videa
- `deleteVideoPart()` → JsonResponse
- `createPreview()` → JsonResponse

**loadVideos() rozdíly oproti Video:**
- Zdrojový adresář: `LIGHT_PATH/mimoradne/nepublikovano/`
- Hledá jen `_lq.mp4` (bez HQ)
- Cílový adresář: `LIGHT_PATH/mimoradne/publikovano/YYYY/MM/DD/`
- Náhledy do: `PUBLIC_PATH/data/mimoradne/thumbs/`
- Nenaváže na program — jen insert do `special_videos`
- Default sec pro náhled: 10 (ne 55)
- Ukládá duration jako `gmdate("H:i:s", sekund)` + `duration_sec` jako integer

DI: `Logger`, `FlashMessenger`, `ProgramRepository`, `VideoexRepository`, `ShowexRepository`, `SettingRepository`, `PhtmlRenderer`, `Security`, `UrlGeneratorInterface`, `$PUBLIC_PATH`, `$LIGHT_PATH`

- [ ] **Step 2: Vytvořit šablonu videoex-edit**

- [ ] **Step 3: Commit**

```bash
git add src/Program/Controller/Admin/VideoexWriteController.php templates/program/admin/videoex-edit.phtml
git commit -m "feat(program): add admin VideoexWriteController with loadVideos()"
```

---

## Task 15: ShowListController (admin)

**Files:**
- Create: `src/Program/Controller/Admin/ShowListController.php`
- Create: `templates/program/admin/show-list.phtml`

- [ ] **Step 1: Vytvořit ShowListController**

Metody:
- `list()` → Response
- `getList()` → JsonResponse
- `getTimesList()` → JsonResponse — vysílací časy
- `getShow()` → JsonResponse
- `getUrl()` → JsonResponse
- `redactorImageManager()` → JsonResponse — správa obrázků (skenuje filesystem)
- `redactorFileManager()` → JsonResponse — správa souborů

DI: `ShowRepository`, `PhtmlRenderer`, `Security`, `$PUBLIC_PATH`

- [ ] **Step 2: Vytvořit šablonu**

- [ ] **Step 3: Commit**

```bash
git add src/Program/Controller/Admin/ShowListController.php templates/program/admin/show-list.phtml
git commit -m "feat(program): add admin ShowListController"
```

---

## Task 16: ShowWriteController (admin)

**Files:**
- Create: `src/Program/Controller/Admin/ShowWriteController.php`
- Create: `templates/program/admin/show-add.phtml`
- Create: `templates/program/admin/show-edit.phtml`

- [ ] **Step 1: Vytvořit ShowWriteController**

Metody:
- `add()` → Response — formulář přidání pořadu (kategorie dropdown, checkbox fields)
- `edit()` → Response — formulář editace pořadu
- `deleteShow()` → JsonResponse
- `setOrder()` → JsonResponse — řazení pořadů
- `setTime()` → JsonResponse — nastavení vysílacího času
- `uploadImage()` → JsonResponse — nahrání obrázku (resize + thumbnail via Imagine)
- `setDefaultImage()` → JsonResponse — vrácení na default obrázek
- `redactorImageUpload()` → JsonResponse — upload obrázku v WYSIWYG editoru
- `redactorFileUpload()` → JsonResponse — upload souboru v WYSIWYG editoru

DI: `Logger`, `FlashMessenger`, `ShowRepository`, `SettingRepository`, `PhtmlRenderer`, `Security`, `UrlGeneratorInterface`, `$PUBLIC_PATH`

- [ ] **Step 2: Vytvořit šablony**

- [ ] **Step 3: Commit**

```bash
git add src/Program/Controller/Admin/ShowWriteController.php templates/program/admin/show-add.phtml templates/program/admin/show-edit.phtml
git commit -m "feat(program): add admin ShowWriteController"
```

---

## Task 17: ShowexListController (admin)

**Files:**
- Create: `src/Program/Controller/Admin/ShowexListController.php`
- Create: `templates/program/admin/showex-list.phtml`

- [ ] **Step 1: Vytvořit ShowexListController** (analogicky k ShowListController)

- [ ] **Step 2: Vytvořit šablonu**

- [ ] **Step 3: Commit**

```bash
git add src/Program/Controller/Admin/ShowexListController.php templates/program/admin/showex-list.phtml
git commit -m "feat(program): add admin ShowexListController"
```

---

## Task 18: ShowexWriteController (admin)

**Files:**
- Create: `src/Program/Controller/Admin/ShowexWriteController.php`
- Create: `templates/program/admin/showex-add.phtml`
- Create: `templates/program/admin/showex-edit.phtml`

- [ ] **Step 1: Vytvořit ShowexWriteController** (analogicky k ShowWriteController, bez setTime)

Path pro obrázky: `data/mimoradne/show/` (místo `data/program/show/`)

- [ ] **Step 2: Vytvořit šablony**

- [ ] **Step 3: Commit**

```bash
git add src/Program/Controller/Admin/ShowexWriteController.php templates/program/admin/showex-add.phtml templates/program/admin/showex-edit.phtml
git commit -m "feat(program): add admin ShowexWriteController"
```

---

## Task 19: Admin routes

**Files:**
- Create: `config/routes/program_admin.yaml`
- Modify: `config/routes.yaml` (import)

- [ ] **Step 1: Vytvořit kompletní routy pro admin Program**

Všechny admin routy modulu Program — ProgramList/Write, VideoList/Write, VideoexList/Write, ShowList/Write, ShowexList/Write, Setting.

Vzor route názvů: `admin_program_*`, `admin_program_video_*`, `admin_program_videoex_*`, `admin_program_show_*`, `admin_program_showex_*`, `admin_program_setting_*`

- [ ] **Step 2: Přidat import do routes.yaml**

```yaml
program_admin:
    resource: 'routes/program_admin.yaml'
```

- [ ] **Step 3: Cache clear + ověřit**

```bash
php bin/console cache:clear
php bin/console debug:router | findstr program
```

- [ ] **Step 4: Commit**

```bash
git add config/routes/program_admin.yaml config/routes.yaml
git commit -m "feat(program): add admin routes"
```

---

## Task 20: Smoke test celé admin části

- [ ] **Step 1: Cache clear**

```bash
php bin/console cache:clear
```

- [ ] **Step 2: Ověřit routování**

```bash
php bin/console debug:router | findstr program
```

Očekávaný výstup: všechny admin_program_* routy

- [ ] **Step 3: Ověřit že žádné soubory nemají syntax error**

```bash
php -l src/Program/Controller/Admin/VideoWriteController.php
php -l src/Program/Controller/Admin/VideoexWriteController.php
```

- [ ] **Step 4: Commit finální**

```bash
git add -A
git commit -m "feat(program): complete admin module migration"
```
