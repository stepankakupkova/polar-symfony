# FlashMessenger — constructor injection ve všech controllerech

> **For agentic workers:** REQUIRED: Use the `subagent-driven-development` agent (recommended) or `executing-plans` agent to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Přesunout `FlashMessenger` ze všech method parametrů do konstruktoru, konzistentně s `PageWriteController` a `ProgramWriteController`.

**Architecture:** Každý controller dostane `private FlashMessenger $flashMessenger` v konstruktoru. Všechny volání `$flashMessenger->` se změní na `$this->flashMessenger->`. Z parametrů jednotlivých metod se `FlashMessenger` odstraní.

**Tech Stack:** PHP 8.5, Symfony 8, Doctrine DBAL

---

## Vzorový výsledek (reference)

Takto to má vypadat po opravě — viz `PageWriteController.php`:

```php
public function __construct(
    // ... ostatní parametry ...
    private FlashMessenger $flashMessenger,
) {}

public function add(
    Request $request,
    // BEZ FlashMessenger zde
): Response {
    // ...
    $this->flashMessenger->addMessage('success', ...);
}
```

---

## Část 1: ShowWriteController

**Soubor:** `src/Program/Controller/Admin/ShowWriteController.php`

Konstruktor má: `private string $PUBLIC_PATH, private Security $security`  
Metody k opravě: `add()`, `edit()`

- [ ] **Krok 1.1: Přidat FlashMessenger do konstruktoru**

```php
public function __construct(
    private string $PUBLIC_PATH,
    private Security $security,
    private FlashMessenger $flashMessenger,
) {}
```

- [ ] **Krok 1.2: Odebrat `FlashMessenger $flashMessenger,` z parametrů `add()`**

Původní:
```php
public function add(
    Request $request,
    PhtmlRenderer $renderer,
    FlashMessenger $flashMessenger,
    ShowRepository $showRepository,
```
Po opravě:
```php
public function add(
    Request $request,
    PhtmlRenderer $renderer,
    ShowRepository $showRepository,
```

- [ ] **Krok 1.3: Změnit `$flashMessenger->addMessage(` → `$this->flashMessenger->addMessage(` v metodě `add()`**

- [ ] **Krok 1.4: Odebrat `FlashMessenger $flashMessenger,` z parametrů `edit()`**

Původní:
```php
public function edit(
    Request $request,
    PhtmlRenderer $renderer,
    FlashMessenger $flashMessenger,
    ShowRepository $showRepository,
```
Po opravě:
```php
public function edit(
    Request $request,
    PhtmlRenderer $renderer,
    ShowRepository $showRepository,
```

- [ ] **Krok 1.5: Změnit `$flashMessenger->addMessage(` → `$this->flashMessenger->addMessage(` v metodě `edit()`**

- [ ] **Krok 1.6: Ověřit syntaxi**

```
php -l "c:\web\www\polar-symfony\src\Program\Controller\Admin\ShowWriteController.php"
```
Očekávaný výstup: `No syntax errors detected`

---

## Část 2: ShowexWriteController

**Soubor:** `src/Program/Controller/Admin/ShowexWriteController.php`

Konstruktor má: `private string $PUBLIC_PATH, private Security $security`  
Metody k opravě: `add()`, `edit()`

- [ ] **Krok 2.1: Přidat FlashMessenger do konstruktoru**

```php
public function __construct(
    private string $PUBLIC_PATH,
    private Security $security,
    private FlashMessenger $flashMessenger,
) {}
```

- [ ] **Krok 2.2: Odebrat `FlashMessenger $flashMessenger,` z parametrů `add()`**

Původní:
```php
public function add(
    Request $request,
    PhtmlRenderer $renderer,
    FlashMessenger $flashMessenger,
    ShowexRepository $showexRepository,
```
Po opravě:
```php
public function add(
    Request $request,
    PhtmlRenderer $renderer,
    ShowexRepository $showexRepository,
```

- [ ] **Krok 2.3: Změnit `$flashMessenger->addMessage(` → `$this->flashMessenger->addMessage(` v metodě `add()`**

- [ ] **Krok 2.4: Odebrat `FlashMessenger $flashMessenger,` z parametrů `edit()`**

Původní:
```php
public function edit(
    Request $request,
    PhtmlRenderer $renderer,
    FlashMessenger $flashMessenger,
    ShowexRepository $showexRepository,
```
Po opravě:
```php
public function edit(
    Request $request,
    PhtmlRenderer $renderer,
    ShowexRepository $showexRepository,
```

- [ ] **Krok 2.5: Změnit `$flashMessenger->addMessage(` → `$this->flashMessenger->addMessage(` v metodě `edit()`**

- [ ] **Krok 2.6: Ověřit syntaxi**

```
php -l "c:\web\www\polar-symfony\src\Program\Controller\Admin\ShowexWriteController.php"
```
Očekávaný výstup: `No syntax errors detected`

---

## Část 3: SettingController

**Soubor:** `src/Program/Controller/Admin/SettingController.php`

Konstruktor má: `private string $PUBLIC_PATH, private Security $security`  
Metody k opravě: `setting()`

- [ ] **Krok 3.1: Přidat FlashMessenger do konstruktoru**

```php
public function __construct(
    private string $PUBLIC_PATH,
    private Security $security,
    private FlashMessenger $flashMessenger,
) {}
```

- [ ] **Krok 3.2: Odebrat `FlashMessenger $flashMessenger,` z parametrů `setting()`**

Původní:
```php
public function setting(
    Request $request,
    PhtmlRenderer $renderer,
    SettingRepository $settingRepository,
    FlashMessenger $flashMessenger,
    LoggerInterface $logger,
```
Po opravě:
```php
public function setting(
    Request $request,
    PhtmlRenderer $renderer,
    SettingRepository $settingRepository,
    LoggerInterface $logger,
```

- [ ] **Krok 3.3: Změnit všechna `$flashMessenger->addMessage(` → `$this->flashMessenger->addMessage(` v metodě `setting()` (jsou 3 výskyty)**

- [ ] **Krok 3.4: Ověřit syntaxi**

```
php -l "c:\web\www\polar-symfony\src\Program\Controller\Admin\SettingController.php"
```
Očekávaný výstup: `No syntax errors detected`

---

## Část 4: VideoexWriteController

**Soubor:** `src/Program/Controller/Admin/VideoexWriteController.php`

Konstruktor má: `private string $PUBLIC_PATH, private string $LIGHT_PATH, private string $LIGHT_URL, private Security $security`  
Metody k opravě: `edit()`

- [ ] **Krok 4.1: Přidat FlashMessenger do konstruktoru**

```php
public function __construct(
    private string $PUBLIC_PATH,
    private string $LIGHT_PATH,
    private string $LIGHT_URL,
    private Security $security,
    private FlashMessenger $flashMessenger,
) {}
```

- [ ] **Krok 4.2: Odebrat `FlashMessenger $flashMessenger,` z parametrů `edit()`**

Původní:
```php
public function edit(
    Request $request,
    PhtmlRenderer $renderer,
    FlashMessenger $flashMessenger,
    VideoexRepository $videoexRepository,
```
Po opravě:
```php
public function edit(
    Request $request,
    PhtmlRenderer $renderer,
    VideoexRepository $videoexRepository,
```

- [ ] **Krok 4.3: Změnit `$flashMessenger->addMessage(` → `$this->flashMessenger->addMessage(` v metodě `edit()`**

- [ ] **Krok 4.4: Ověřit syntaxi**

```
php -l "c:\web\www\polar-symfony\src\Program\Controller\Admin\VideoexWriteController.php"
```
Očekávaný výstup: `No syntax errors detected`

---

## Část 5: List controllery

Pět list controllerů — všechny mají `FlashMessenger` jen v metodě `list()` pro `getMessages()`.

### 5a: ShowListController

**Soubor:** `src/Program/Controller/Admin/ShowListController.php`

Konstruktor existuje: `private string $PUBLIC_PATH`

- [ ] **Krok 5a.1: Přidat FlashMessenger do konstruktoru**

```php
public function __construct(
    private string $PUBLIC_PATH,
    private FlashMessenger $flashMessenger,
) {}
```

- [ ] **Krok 5a.2: Odebrat `FlashMessenger $flashMessenger,` z parametrů `list()`**

Původní:
```php
public function list(
    PhtmlRenderer $renderer,
    FlashMessenger $flashMessenger,
): Response
```
Po opravě:
```php
public function list(
    PhtmlRenderer $renderer,
): Response
```

- [ ] **Krok 5a.3: Změnit `$flashMessenger->getMessages()` → `$this->flashMessenger->getMessages()` v metodě `list()`**

### 5b: ShowexListController

**Soubor:** `src/Program/Controller/Admin/ShowexListController.php`

Konstruktor existuje: `private string $PUBLIC_PATH`

- [ ] **Krok 5b.1: Přidat FlashMessenger do konstruktoru**

```php
public function __construct(
    private string $PUBLIC_PATH,
    private FlashMessenger $flashMessenger,
) {}
```

- [ ] **Krok 5b.2: Odebrat `FlashMessenger $flashMessenger,` z parametrů `list()` a nahradit `$flashMessenger->` za `$this->flashMessenger->`**

### 5c: VideoListController

**Soubor:** `src/Program/Controller/Admin/VideoListController.php`

Konstruktor neexistuje — přidat ho.

- [ ] **Krok 5c.1: Přidat konstruktor před metodu `list()`**

```php
public function __construct(
    private FlashMessenger $flashMessenger,
) {}

public function list(
    PhtmlRenderer $renderer,
    SettingRepository $settingRepository,
    Security $security,
): Response
```

- [ ] **Krok 5c.2: Odebrat `FlashMessenger $flashMessenger,` z parametrů `list()`**

- [ ] **Krok 5c.3: Změnit `$flashMessenger->getMessages()` → `$this->flashMessenger->getMessages()`**

### 5d: VideoexListController

**Soubor:** `src/Program/Controller/Admin/VideoexListController.php`

Konstruktor neexistuje — přidat ho.

- [ ] **Krok 5d.1: Přidat konstruktor před metodu `list()`**

```php
public function __construct(
    private FlashMessenger $flashMessenger,
) {}

public function list(
    PhtmlRenderer $renderer,
    SettingRepository $settingRepository,
): Response
```

- [ ] **Krok 5d.2: Odebrat `FlashMessenger $flashMessenger,` z parametrů `list()`**

- [ ] **Krok 5d.3: Změnit `$flashMessenger->getMessages()` → `$this->flashMessenger->getMessages()`**

### 5e: ProgramListController

**Soubor:** `src/Program/Controller/Admin/ProgramListController.php`

Konstruktor neexistuje — přidat ho. Pozor: metoda `list()` injektuje i `Security $security` — ta zůstane v metodě (není potřeba v konstruktoru, nepoužívá se nikde jinde v třídě).

- [ ] **Krok 5e.1: Přidat konstruktor před metodu `index()`**

```php
public function __construct(
    private FlashMessenger $flashMessenger,
) {}

public function index(
```

- [ ] **Krok 5e.2: Odebrat `FlashMessenger $flashMessenger,` z parametrů `list()`**

- [ ] **Krok 5e.3: Změnit `$flashMessenger->getMessages()` → `$this->flashMessenger->getMessages()`**

- [ ] **Krok 5.4: Ověřit syntaxi všech 5 list controllerů**

```
php -l "c:\web\www\polar-symfony\src\Program\Controller\Admin\ShowListController.php"
php -l "c:\web\www\polar-symfony\src\Program\Controller\Admin\ShowexListController.php"
php -l "c:\web\www\polar-symfony\src\Program\Controller\Admin\VideoListController.php"
php -l "c:\web\www\polar-symfony\src\Program\Controller\Admin\VideoexListController.php"
php -l "c:\web\www\polar-symfony\src\Program\Controller\Admin\ProgramListController.php"
```
Každý: `No syntax errors detected`
