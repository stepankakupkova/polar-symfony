# Identity Fix – Implementation Plan

> **For agentic workers:** REQUIRED: Use the `subagent-driven-development` agent (recommended) or `executing-plans` agent to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Do každé metody, která používá `$identity`, přidat chybějící definici `$identity = ...->getUser()`.

**Architecture:** V minulé session PowerShell regex odstranil `$identity = $security->getUser()` (nebo `$this->security->getUser()`) ze write controllerů, ale ponechal všechna místa kde se `$identity` používá pro logy. Výsledkem jsou undefined variable chyby. Plán opravuje soubory modul po modulu.

**Tech Stack:** PHP 8.5, Symfony 8, Symfony Security Bundle

---

## Vzory

**Vzor A** – Security injektovaná v konstruktoru (`private Security $security`):
```php
$identity = $this->security->getUser();
```
Soubory: `PageWriteController`, `PageSettingController`, `UserWriteController`

**Vzor B** – Security jako parametr metody (`Security $security`):
```php
$identity = $security->getUser();
```
Soubory: `SettingController`, `ProgramWriteController`, `ShowWriteController`, `ShowexWriteController`, `VideoWriteController`, `VideoexWriteController`

---

## Struktura souborů

| Soubor | Vzor | Metody k opravě |
|--------|------|-----------------|
| `src/Page/Controller/Admin/PageWriteController.php` | A | `add`, `edit`, `duplicatePage`, `deletePage`, `savePagesSort`, `uploadImage`, `setDefaultImage` |
| `src/Page/Controller/Admin/PageSettingController.php` | A | `setting` |
| `src/User/Controller/Admin/UserWriteController.php` | A | `add`, `edit`, `deleteUser` |
| `src/Program/Controller/Admin/SettingController.php` | B | `setting` |
| `src/Program/Controller/Admin/ProgramWriteController.php` | B | `add`, `edit`, `deleteProgram`, `exportShows` |
| `src/Program/Controller/Admin/ShowWriteController.php` | B | `add`, `edit`, `deleteShow`, `setDefaultImage` |
| `src/Program/Controller/Admin/ShowexWriteController.php` | B | `add`, `edit`, `deleteShow`, `setDefaultImage` |
| `src/Program/Controller/Admin/VideoWriteController.php` | B | `deleteVideo`, `loadVideos`, `createPreviewAction`, `uploadPreviewFromPc` |
| `src/Program/Controller/Admin/VideoexWriteController.php` | B | `edit`, `deleteVideo`, `loadVideos`, `setPart`, `deleteVideoPart`, `createPreviewAction` |

---

## Task 1: Page modul – PageWriteController

**Soubor:** `src/Page/Controller/Admin/PageWriteController.php`  
Vzor A: `$identity = $this->security->getUser();`

- [ ] **Step 1: Metoda `add`** – přidej za otevírací `{`:

```php
	public function add(Request $request): Response
	{
		$identity = $this->security->getUser();
		$lang = 'cs_CZ';
```

*(oldString: `public function add(Request $request): Response\n\t{\n\t\t$lang = 'cs_CZ';`)*

- [ ] **Step 2: Metoda `edit`** – přidej za otevírající `{`:

```php
	public function edit(Request $request, int $id = 0): Response
	{
		$identity = $this->security->getUser();
		$lang = 'cs_CZ';
```

- [ ] **Step 3: Metoda `duplicatePage`** – přidej za otevírající `{`:

```php
	public function duplicatePage(Request $request): JsonResponse
	{
		$identity = $this->security->getUser();
		$success = true;
```

- [ ] **Step 4: Metoda `deletePage`** – přidej za otevírající `{`:

```php
	public function deletePage(Request $request): JsonResponse
	{
		$identity = $this->security->getUser();
		$success = true;
```

- [ ] **Step 5: Protected metoda `savePagesSort`** – přidej za otevírající `{`:

```php
	protected function savePagesSort(array $data, ?int $parent = null, int $depth = 1, int $rank = 1, int $rankTotal = 1): int
	{
		$identity = $this->security->getUser();
		if ($data) {
```

- [ ] **Step 6: Metoda `uploadImage`** – přidej za otevírající `{`:

```php
	public function uploadImage(Request $request): JsonResponse
	{
		$identity = $this->security->getUser();
		$file = $request->files->get('file');
```

- [ ] **Step 7: Metoda `setDefaultImage`** – přidej za otevírající `{`:

```php
	public function setDefaultImage(Request $request): JsonResponse
	{
		$identity = $this->security->getUser();
		$success = true;
```

- [ ] **Step 8: Ověř PHP syntax:**

```
php -l src/Page/Controller/Admin/PageWriteController.php
```
Očekávaný výstup: `No syntax errors detected`

- [ ] **Step 9: Commit:**
```
git add src/Page/Controller/Admin/PageWriteController.php
git commit -m "fix: add missing identity assignment in PageWriteController"
```

---

## Task 2: Page modul – PageSettingController

**Soubor:** `src/Page/Controller/Admin/PageSettingController.php`  
Vzor A: `$identity = $this->security->getUser();`

- [ ] **Step 1: Metoda `setting`** – přidej za otevírající `{`:

```php
	public function setting(Request $request): Response
	{
		$identity = $this->security->getUser();
		try {
			$setting = $this->settingRepository->fetchSetting();
```

- [ ] **Step 2: Ověř PHP syntax:**

```
php -l src/Page/Controller/Admin/PageSettingController.php
```

- [ ] **Step 3: Commit:**
```
git add src/Page/Controller/Admin/PageSettingController.php
git commit -m "fix: add missing identity assignment in PageSettingController"
```

---

## Task 3: User modul – UserWriteController

**Soubor:** `src/User/Controller/Admin/UserWriteController.php`  
Vzor A: `$identity = $this->security->getUser();`  
Pozn.: Metody mají `/** @var User $identity */` komentář – přidej TĚSNĚ za něj.

- [ ] **Step 1: Metoda `add`** – přidej za `/** @var User $identity */`:

```php
	{
		/** @var User $identity */
		$identity = $this->security->getUser();
		if ($request->isMethod('POST')) {
```

- [ ] **Step 2: Metoda `edit`** – přidej za `/** @var User $identity */`:

```php
	{
		/** @var User $identity */
		$identity = $this->security->getUser();
		$user = $this->userRepository->findPostBy('id', $id);
```

- [ ] **Step 3: Metoda `deleteUser`** – přidej za otevírající `{`:

```php
	public function deleteUser(Request $request): JsonResponse
	{
		$identity = $this->security->getUser();
		try {
			$userId = $request->request->getInt('id');
```

- [ ] **Step 4: Ověř PHP syntax:**

```
php -l src/User/Controller/Admin/UserWriteController.php
```

- [ ] **Step 5: Commit:**
```
git add src/User/Controller/Admin/UserWriteController.php
git commit -m "fix: add missing identity assignment in UserWriteController"
```

---

## Task 4: Program modul – SettingController

**Soubor:** `src/Program/Controller/Admin/SettingController.php`  
Vzor B: `$identity = $security->getUser();`  
Metoda má `/** @var User $identity */` komentář – přidej za něj.

- [ ] **Step 1: Metoda `setting`** – přidej za `/** @var User $identity */`:

```php
	{
		/** @var User $identity */
		$identity = $security->getUser();
		try {
			$setting = $settingRepository->fetchSetting();
```

- [ ] **Step 2: Ověř PHP syntax:**

```
php -l src/Program/Controller/Admin/SettingController.php
```

- [ ] **Step 3: Commit:**
```
git add src/Program/Controller/Admin/SettingController.php
git commit -m "fix: add missing identity assignment in Program/SettingController"
```

---

## Task 5: Program modul – ProgramWriteController

**Soubor:** `src/Program/Controller/Admin/ProgramWriteController.php`  
Vzor B: `$identity = $security->getUser();`

- [ ] **Step 1: Metoda `add`** – přidej za otevírající `{`:

```php
	): Response
	{
		$identity = $security->getUser();
		// Videa
		$videoOptions = $videoRepository->fetchForBootstrapSelect(200);
```

*(oldString hledej přes: `): Response\n\t{\n\t\t// Videa` – v metodě `add`, cca řádek 47)*

- [ ] **Step 2: Metoda `edit`** – přidej za otevírající `{`:

```php
	): Response
	{
		$identity = $security->getUser();
		$program_id = (int) $request->attributes->get('id', 0);
```

Pozor: v souboru jsou dvě `): Response\n\t{\n\t\t$program_id` – druhá je v `edit`. Použij kontextový řetězec obsahující `$urlGenerator,\n\t): Response`.

- [ ] **Step 3: Metoda `deleteProgram`** – přidej za otevírající `{`:

```php
	): JsonResponse
	{
		$identity = $security->getUser();
		$success = true;
		$message = null;
		$program_id = null;
```

- [ ] **Step 4: Metoda `exportShows`** – přidej za otevírající `{`:

```php
	): JsonResponse
	{
		$identity = $security->getUser();
		$success = false;
		$message = null;
```

- [ ] **Step 5: Ověř PHP syntax:**

```
php -l src/Program/Controller/Admin/ProgramWriteController.php
```

- [ ] **Step 6: Commit:**
```
git add src/Program/Controller/Admin/ProgramWriteController.php
git commit -m "fix: add missing identity assignment in ProgramWriteController"
```

---

## Task 6: Program modul – ShowWriteController

**Soubor:** `src/Program/Controller/Admin/ShowWriteController.php`  
Vzor B: `$identity = $security->getUser();`

Všechny opravované metody mají `Security $security,` jako parametr a hned za `): Response\n\t{\n\t\t` nebo `): JsonResponse\n\t{\n\t\t` začínají tělem.

- [ ] **Step 1: Metoda `add`** – přidej za otevírající `{`:

```php
	): Response
	{
		$identity = $security->getUser();
		$setting = $settingRepository->fetchSetting();
		$categories = $showRepository->fetchCategoryForBootstrapSelect();
```

- [ ] **Step 2: Metoda `edit`** – přidej za otevírající `{`. Zkontroluj přesný text z řádku ~175 (kde `edit` metoda otevírá tělo). Vzor:

```php
	): Response
	{
		$identity = $security->getUser();
		$show_id = (int) $request->attributes->get('id', 0);
```

*(Ověř přesný první řádek těla metody `edit` v souboru před editací.)*

- [ ] **Step 3: Metoda `deleteShow`** – přidej za otevírající `{`:

```php
	): JsonResponse
	{
		$identity = $security->getUser();
		$success = true;
```

*(Ověř přesný první řádek těla – může být `$success = true;` nebo jiný.)*

- [ ] **Step 4: Metoda `setDefaultImage`** – přidej za otevírající `{`. Vzor (ověř z aktuálního souboru):

```php
	): JsonResponse
	{
		$identity = $security->getUser();
		$success = true;
```

- [ ] **Step 5: Ověř PHP syntax:**

```
php -l src/Program/Controller/Admin/ShowWriteController.php
```

- [ ] **Step 6: Commit:**
```
git add src/Program/Controller/Admin/ShowWriteController.php
git commit -m "fix: add missing identity assignment in ShowWriteController"
```

---

## Task 7: Program modul – ShowexWriteController

**Soubor:** `src/Program/Controller/Admin/ShowexWriteController.php`  
Vzor B: `$identity = $security->getUser();`  
Metody: `add` (line 41), `edit` (line 160), `deleteShow` (line 255), `setDefaultImage` (line 438)

Přesný postup stejný jako Task 6 – pro každou metodu zkontroluj první řádek těla z aktuálního souboru a přidej `$identity = $security->getUser();` za otevírající `{`.

- [ ] **Step 1: Metoda `add`** – přidej za otevírající `{`:

```php
	): Response
	{
		$identity = $security->getUser();
		$setting = $settingRepository->fetchSetting();
		$categories = $showexRepository->fetchCategoryForBootstrapSelect();
```

- [ ] **Step 2: Metoda `edit`** – přidej za otevírající `{`. Ověř přesný první řádek těla z aktuálního souboru (cca řádek 175).

- [ ] **Step 3: Metoda `deleteShow`** – přidej za otevírající `{` – ověř přesný první řádek těla.

- [ ] **Step 4: Metoda `setDefaultImage`** – přidej za otevírající `{` – ověř přesný první řádek těla.

- [ ] **Step 5: Ověř PHP syntax:**

```
php -l src/Program/Controller/Admin/ShowexWriteController.php
```

- [ ] **Step 6: Commit:**
```
git add src/Program/Controller/Admin/ShowexWriteController.php
git commit -m "fix: add missing identity assignment in ShowexWriteController"
```

---

## Task 8: Program modul – VideoWriteController

**Soubor:** `src/Program/Controller/Admin/VideoWriteController.php`  
Vzor B: `$identity = $security->getUser();`  
Metody: `deleteVideo` (line 72), `loadVideos` (line 145), `createPreviewAction` (line 320), `uploadPreviewFromPc` (line 390)

Pozn.: `createPreview` je private metoda, dostává `$identity` jako parametr `mixed $identity` – **NEopravovat**.

- [ ] **Step 1: Metoda `deleteVideo`** – přidej za otevírající `{`:

```php
	): JsonResponse
	{
		$identity = $security->getUser();
		$success = true;
```

*(Ověř přesný první řádek těla z aktuálního souboru cca řádek 79.)*

- [ ] **Step 2: Metoda `loadVideos`** – přidej za otevírající `{`. Ověř první řádek těla (cca řádek 157).

- [ ] **Step 3: Metoda `createPreviewAction`** – přidej za otevírající `{`. Ověř první řádek těla (cca řádek 326).

- [ ] **Step 4: Metoda `uploadPreviewFromPc`** – přidej za otevírající `{`. Ověř první řádek těla (cca řádek 396).

- [ ] **Step 5: Ověř PHP syntax:**

```
php -l src/Program/Controller/Admin/VideoWriteController.php
```

- [ ] **Step 6: Commit:**
```
git add src/Program/Controller/Admin/VideoWriteController.php
git commit -m "fix: add missing identity assignment in VideoWriteController"
```

---

## Task 9: Program modul – VideoexWriteController

**Soubor:** `src/Program/Controller/Admin/VideoexWriteController.php`  
Vzor B: `$identity = $security->getUser();`  
Metody: `edit` (line 37), `deleteVideo` (line 117), `loadVideos` (line 190), `setPart` (line 354), `deleteVideoPart` (line 413), `createPreviewAction` (line 457)

Pozn.: `createPreview` (private, line 531) dostává `$identity` jako param – **NEopravovat**.

- [ ] **Step 1: Metoda `edit`** – přidej za otevírající `{`:

```php
	): Response
	{
		$identity = $security->getUser();
		$video_id = (int) $request->attributes->get('id', 0);
```

- [ ] **Step 2–6: Metody `deleteVideo`, `loadVideos`, `setPart`, `deleteVideoPart`, `createPreviewAction`** – pro každou ověř první řádek těla z aktuálního souboru a přidej `$identity = $security->getUser();`.

- [ ] **Step 7: Ověř PHP syntax:**

```
php -l src/Program/Controller/Admin/VideoexWriteController.php
```

- [ ] **Step 8: Commit:**
```
git add src/Program/Controller/Admin/VideoexWriteController.php
git commit -m "fix: add missing identity assignment in VideoexWriteController"
```

---

## Task 10: Závěrečné ověření

- [ ] **Step 1: PHP lint všech opravených souborů najednou:**

```
php -l src/Page/Controller/Admin/PageWriteController.php
php -l src/Page/Controller/Admin/PageSettingController.php
php -l src/User/Controller/Admin/UserWriteController.php
php -l src/Program/Controller/Admin/SettingController.php
php -l src/Program/Controller/Admin/ProgramWriteController.php
php -l src/Program/Controller/Admin/ShowWriteController.php
php -l src/Program/Controller/Admin/ShowexWriteController.php
php -l src/Program/Controller/Admin/VideoWriteController.php
php -l src/Program/Controller/Admin/VideoexWriteController.php
```

- [ ] **Step 2: Zkontroluj, že žádný soubor už neobsahuje `$identity` bez definice:**

```powershell
# Hledej metody co NEMAJÍ $identity = ... ale MAJÍ $identity?-> nebo $identity->
# (Rychlá vizuální kontrola)
Select-String -Path "src\*\Controller\Admin\*.php" -Pattern "\`$identity" | Where-Object { $_.Line -notmatch "=\s*\`$.*->getUser\(\)" -and $_.Line -notmatch "@var" } | Select-Object Path, LineNumber, Line
```

- [ ] **Step 3: Ověř Symfony container (žádné chyby DI):**

```
php bin/console cache:clear
```

Očekávaný výstup: bez error.
