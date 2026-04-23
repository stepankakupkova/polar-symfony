# User Module – Implementační plán (ověření)

> **For agentic workers:** REQUIRED: Use the `subagent-driven-development` agent (recommended) or `executing-plans` agent to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ověřit a dopracovat User modul v polar-symfony tak, aby byl funkčně ekvivalentní s polarem (Laminas).

**Architecture:** Veškerý kód již existuje (controllers, repository, templates, routes). Plán je čistě verifikační — každý krok projde jeden soubor, porovná ho s originálním polarem a opraví případné odchylky.

**Tech Stack:** Symfony 8, PHP 8.5, Doctrine DBAL, phtml šablony, PhtmlRenderer, Symfony Security

---

## Stav před zahájením

| Soubor | Stav |
|---|---|
| `src/User/Repository/UserRepository.php` | existuje, neověřeno |
| `src/User/Controller/Admin/UserListController.php` | existuje, neověřeno |
| `src/User/Controller/Admin/UserWriteController.php` | existuje, neověřeno |
| `templates/user/admin/index.phtml` | existuje, neověřeno |
| `templates/user/admin/list.phtml` | existuje, neověřeno |
| `templates/user/admin/add.phtml` | existuje, neověřeno |
| `templates/user/admin/edit.phtml` | existuje, neověřeno |
| `templates/user/admin/userForm.phtml` | existuje, neověřeno |
| Routes v `config/routes/admin.yaml` | existují (sekce `# User`) |
| Admin menu v `templates/admin/layout.phtml` | ✅ hotovo |

---

## Krok 1: UserRepository

**Soubory:**
- Ověřit: `c:\web\www\polar-symfony\src\User\Repository\UserRepository.php`
- Reference: `c:\web\www\polar\module\User\src\Model\User\` (MariaDbSqlRepository nebo podobné)

**Co hledat v polaru:**
- Přečti metody v polar User modelu (Model/User/)
- Porovnej signatury metod: `getCount()`, `fetchForBootstrapTable()`, `getCountForBootstrapTable()`, `findPostBy()`, `updatePost()`
- Zkontroluj, zda v polaru jsou další metody, které v symfony chybí

- [ ] Přečti `c:\web\www\polar\module\User\src\Model\User\` — zjisti, jaké soubory tam jsou
- [ ] Přečti polar repository/command soubory a vypiš seznam public metod
- [ ] Porovnej se symfony `UserRepository.php` — jsou všechny potřebné metody?
- [ ] Pokud chybí nějaká metoda důležitá pro web, přidej ji
- [ ] Pokud je vše OK, napiš "Krok 1 OK" a čekej na potvrzení

---

## Krok 2: UserListController

**Soubory:**
- Ověřit: `c:\web\www\polar-symfony\src\User\Controller\Admin\UserListController.php`
- Reference: `c:\web\www\polar\module\User\src\Controller\User\UserListController.php`

**Co porovnat:**
- Metoda `index()` — předává `countUsers` a `countUsersActive` do šablony
- Metoda `list()` — renderuje šablonu bez dat (flash zprávy zajistí PhtmlRenderer)
- Metoda `getList()` — JSON endpoint pro bootstrap-table
- Metoda `getUser()` — JSON endpoint pro get-user

- [ ] Přečti oba soubory
- [ ] Zkontroluj, zda `getList()` vrací stejný formát JSON jako polar (`success`, `rows`, `total`)
- [ ] Zkontroluj, zda `getUser()` vrací `user` objekt s `username` (spojení authorization + user)
- [ ] Případně oprav odchylky
- [ ] Napiš "Krok 2 OK" a čekej na potvrzení

---

## Krok 3: UserWriteController

**Soubory:**
- Ověřit: `c:\web\www\polar-symfony\src\User\Controller\Admin\UserWriteController.php`
- Reference: `c:\web\www\polar\module\User\src\Controller\User\UserWriteController.php`

**Co porovnat:**
- Metoda `add()` — POST handling, validace, vytvoření authorization + user, redirect
- Metoda `edit()` — načtení user+authorization, role, POST update, redirect
- Metoda `deleteUser()` — mazání adresáře, smazání authorization (kaskáda smaže user)
- Metoda `uploadImage()` — upload avatara, tmp složka vs user složka
- Privátní `validateForm()` — povinná pole, shoda hesel

- [ ] Přečti oba soubory
- [ ] Zkontroluj `validateForm()` — jsou kontrolovány stejná pole jako v polaru?
- [ ] Zkontroluj `add()` — pořadí operací: create authorization → find user (trigger) → update user
- [ ] Zkontroluj `edit()` — aktualizace authorization i user tabulky
- [ ] Zkontroluj `deleteUser()` — smaže authorization, kaskáda smaže user?
- [ ] Zkontroluj `uploadImage()` — bezpečnost (povolené MIME typy, validace přípony)
- [ ] Případně oprav odchylky
- [ ] Napiš "Krok 3 OK" a čekej na potvrzení

---

## Krok 4: index.phtml

**Soubory:**
- Ověřit: `c:\web\www\polar-symfony\templates\user\admin\index.phtml`
- Reference: `c:\web\www\polar\module\User\view\user\user\user-list\index.phtml`

**Poznámka:** Polar index.phtml obsahuje changelog widget a settings widget — ty jsou specifické pro Laminas a v symfony se vynechávají. Symfony verze záměrně zobrazuje jen základní statistiky (počty uživatelů).

- [ ] Přečti oba soubory
- [ ] Zkontroluj, zda symfony verze zobrazuje `$countUsers` a `$countUsersActive`
- [ ] Zkontroluj HTML strukturu — card s řádky, styly odpovídají adminu
- [ ] Pokud v polaru jsou na stránce další smysluplná data (ne jen changelog), přidej je i do symfony verze
- [ ] Napiš "Krok 4 OK" a čekej na potvrzení

---

## Krok 5: list.phtml

**Soubory:**
- Ověřit: `c:\web\www\polar-symfony\templates\user\admin\list.phtml`
- Reference: `c:\web\www\polar\module\User\view\user\user\user-list\list.phtml`

**Co porovnat:**
- Bootstrap-table konfigurace (data-url, data-sort-name, data-sort-order)
- Sloupce tabulky — počet a jména odpovídají JSON z `getList`
- JavaScript formátovací funkce (optionsFormatter, roleFormatter, imageFormatter, activeFormatter, datetimeFormatter)
- Delete dialog — kontrola, zda přihlášený uživatel nesmaže sám sebe

**Klíčové:** `$identity` je automaticky injektována PhtmlRendererem — není třeba ji předávat z controlleru.

- [ ] Přečti oba soubory
- [ ] Zkontroluj, zda tabulka má správné sloupce odpovídající JSON z repository
- [ ] Zkontroluj JS formátovací funkce — `roleFormatter` mapuje role na CZ texty?
- [ ] Zkontroluj URL endpointů v JS — `$view->path('admin_user_get_user')`, `$view->path('admin_user_delete')`
- [ ] Zkontroluj identitu: `$identity->getId()` a `$identity->getRole()` — funguje Symfony User?
- [ ] Případně oprav odchylky
- [ ] Napiš "Krok 5 OK" a čekej na potvrzení

---

## Krok 6: add.phtml

**Soubory:**
- Ověřit: `c:\web\www\polar-symfony\templates\user\admin\add.phtml`
- Reference: `c:\web\www\polar\module\User\view\user\user\user-write\add.phtml`

**Poznámka:** Polar add.phtml používá Laminas Form a file-upload plugin pro avatar. Symfony verze je záměrně zjednodušena — formulářová část přes `$view->include('user/admin/userForm')`, obrázek přes `<img id="avatarPreview">`.

- [ ] Přečti oba soubory
- [ ] Zkontroluj, zda `add.phtml` předává do `userForm` správné proměnné: `post`, `errors`, `identity`, `isEdit => false`
- [ ] Zkontroluj, zda zobrazuje avatar preview (`$post['image']`, fallback na `data/user/!default-user.png`)
- [ ] Zkontroluj, zda má upload skript (`addBodyScript` / `addInlineScript`) pro file upload
- [ ] Případně oprav odchylky
- [ ] Napiš "Krok 6 OK" a čekej na potvrzení

---

## Krok 7: edit.phtml

**Soubory:**
- Ověřit: `c:\web\www\polar-symfony\templates\user\admin\edit.phtml`
- Reference: `c:\web\www\polar\module\User\view\user\user\user-write\edit.phtml`

**Poznámka:** Polar edit.phtml je velmi podobný add.phtml (stejná forma, jiný form action). Symfony verze opět záměrně zjednodušena.

- [ ] Přečti oba soubory
- [ ] Zkontroluj, zda `edit.phtml` předává do `userForm` správné proměnné: `post`, `errors`, `identity`, `isEdit => true`, `currentRole`
- [ ] Zkontroluj, zda zobrazuje aktuální avatar (`$post['image']`)
- [ ] Zkontroluj, zda má upload skript pro file upload (jako add.phtml)
- [ ] Zkontroluj, zda formulář předává `user_id` pro upload (pro správné uložení do složky)
- [ ] Případně oprav odchylky
- [ ] Napiš "Krok 7 OK" a čekej na potvrzení

---

## Krok 8: userForm.phtml

**Soubory:**
- Ověřit: `c:\web\www\polar-symfony\templates\user\admin\userForm.phtml`
- Reference: `c:\web\www\polar\module\User\view\user\user\user-write\userForm.php`

**Co porovnat:**
- Pole formuláře: username (email), password, password2, role (select), active (checkbox), first_name, last_name, image (hidden)
- Validační třídy (`is-invalid`, `invalid-feedback`)
- Omezení rolí pro non-owner (`owner` role není vidět pro adminy)
- Tlačítka: Submit a Cancel

- [ ] Přečti oba soubory
- [ ] Zkontroluj, zda jsou všechna pole formuláře přítomna
- [ ] Zkontroluj podmínku pro zobrazení role `owner` — jen pro `$identity` s rolí owner
- [ ] Zkontroluj tlačítka — je Cancel button (`name="cancel"` pro redirect v controlleru)?
- [ ] Zkontroluj hidden pole `image` — předává se aktuální hodnota obrázku?
- [ ] Případně oprav odchylky
- [ ] Napiš "Krok 8 OK" a čekej na potvrzení

---

## Krok 9: Routes — ověření

**Soubory:**
- Ověřit: `c:\web\www\polar-symfony\config\routes\admin.yaml` (sekce `# User`)
- Reference: `c:\web\www\polar\module\User\config\` (module config)

- [ ] Přečti sekci `# User` v `admin.yaml`
- [ ] Přečti polar User module config (routes)
- [ ] Zkontroluj, zda jsou všechny routy přítomny: index, list, get-list, get-user, add, edit, delete, upload-image
- [ ] Zkontroluj HTTP metody (GET/POST) u JSON endpointů
- [ ] Případně doplň chybějící routy
- [ ] Napiš "Krok 9 OK" a čekej na potvrzení

---

## Krok 10: AuthorizationRepository — ověření závislostí

**Soubory:**
- Ověřit: `c:\web\www\polar-symfony\src\Authorization\Repository\AuthorizationRepository.php`

**Proč:** `UserWriteController` a `UserListController` závisí na `AuthorizationRepository` — metody `insertPost`, `updatePost`, `deletePost`, `findPostBy`.

- [ ] Přečti `AuthorizationRepository.php`
- [ ] Zkontroluj, zda existují metody: `findPostBy()`, `insertPost()`, `updatePost()`, `deletePost()`
- [ ] Zkontroluj `insertPost()` — vrací `int` (id nového záznamu)?
- [ ] Zkontroluj, zda mazání authorization kaskádně smaže user (FK constraint v DB) nebo je třeba explicitní mazání
- [ ] Případně doplň chybějící metody
- [ ] Napiš "Krok 10 OK" a čekej na potvrzení

---

## Krok 11 (volitelný): Setting controller stub

**Polar stav:** `SettingListController` renderuje prázdnou stránku, `SettingWriteController` je zcela prázdný, `setting-list/index.phtml` je prázdný placeholder.

**Rozhodnutí:** Pokud Setting URL v adminu existuje (v menu nebo odkazu), vytvoříme stub. Pokud nikde není odkazováno, přeskočíme.

- [ ] Zkontroluj, zda v polar adminu je odkaz na User Settings (v polaru to je v `dashboard/widget.phtml` → `admin/user/setting`)
- [ ] Zkontroluj, zda v `polar-symfony` někde odkazujeme na Setting stránku
- [ ] Pokud ANO: vytvoř `src/User/Controller/Admin/SettingController.php` se stubem, šablonu, route
- [ ] Pokud NE: přeskoč krok
- [ ] Napiš "Krok 11 OK/přeskočeno" a čekej na potvrzení

---

## Definice hotovo

User modul je hotový, když:
- [ ] Všechny ověřovací kroky jsou schváleny
- [ ] Stránka `/admin/users` se zobrazí bez chyby
- [ ] Stránka `/admin/users/list` zobrazí bootstrap-table s uživateli
- [ ] Přidat uživatele funguje (POST → redirect → flash zpráva)
- [ ] Upravit uživatele funguje
- [ ] Smazat uživatele funguje (JSON endpoint)
- [ ] Upload avatara funguje
