# Playkit → Navigation Regions pro Symfony — Implementační plán

> **For agentic workers:** REQUIRED: Use the `subagent-driven-development` agent (recommended) or `executing-plans` agent to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Playkit admin bude při přidání/změně města nebo regionu generovat `config/news_navigation.php` pro polar-symfony (místo starého Laminas configu).

**Architecture:** V polar-symfony se sekce `regions` v `navigation.php` přesune do samostatného souboru `news_navigation.php`, který je generovaný Playkitem. V Playkitu se stará Laminas generace zakomentuje a přibyde nová generace pro Symfony formát.

**Tech Stack:** PHP 8.x, Laminas MVC (Playkit), Symfony 8 (polar-symfony), plain PHP arrays

---

## Přehled souborů

| Soubor | Akce | Popis |
|--------|------|-------|
| `c:\web\www\polar-symfony\config\navigation.php` | Upravit | `regions` klíč nahradit `require 'news_navigation.php'` |
| `c:\web\www\polar-symfony\config\news_navigation.php` | Vytvořit (seed) | Počáteční obsah z aktuálního `navigation.php`, pak přepisován Playkitem |
| `c:\web\www\playkit\module\Polar\src\Controller\Polar\PolarWriteController.php` | Upravit | Zakomentovat Laminas generaci, přidat Symfony generaci |

---

## Proč NE `news_generated.yaml`

Symfony routes pro zprávy jsou wildcardové:
- `/zpravy/{url}` → region
- `/zpravy/{url}/{city_url}` → město

Regiony/města **nemusí být vyjmenovány v routes**. YAML generovat nemusíme.
Stačí generovat `news_navigation.php` pro navigační menu.

---

### Task 1: Vytvoření `news_navigation.php` (seed souboru)

**Files:**
- Create: `c:\web\www\polar-symfony\config\news_navigation.php`
- Modify: `c:\web\www\polar-symfony\config\navigation.php`

- [ ] **Krok 1: Vytvoř `config/news_navigation.php` se stávajícím obsahem**

Vezmi celý obsah klíče `'regions' => [...]` z `navigation.php` a přesuň ho do nového souboru:

```php
<?php

/**
 * POZOR: Tento soubor je generovaný administrací Playkit.
 * Neupravuj ručně — změny budou přepsány.
 * Zdroj: Playkit → Polar → setConfigNavigationAction()
 */
return [
    [
        'id'     => 'region-all',
        'label'  => 'MS kraj',
        'url'    => '/zpravy',
        'cities' => [],
    ],
    [
        'id'    => 'region-ostrava',
        'label' => 'Ostrava',
        'url'   => '/zpravy/ostrava',
        'cities' => [
            ['label' => 'Ostrava-město',           'url' => '/zpravy/ostrava/ostrava-mesto'],
            ['label' => 'Ostrava-Centrum',         'url' => '/zpravy/ostrava/ostrava-centrum'],
            ['label' => 'Ostrava-Poruba',          'url' => '/zpravy/ostrava/ostrava-poruba'],
            ['label' => 'Ostrava-Vítkovice',       'url' => '/zpravy/ostrava/ostrava-vitkovice'],
            ['label' => 'Ostrava-Slezská Ostrava', 'url' => '/zpravy/ostrava/slezska-ostrava'],
            ['label' => 'Ostrava-Jih',             'url' => '/zpravy/ostrava/ostrava-jih'],
            ['label' => 'Ostrava-Mariánské hory',  'url' => '/zpravy/ostrava/ostrava-marianske-hory'],
            ['label' => 'Ostrava-Svinov',          'url' => '/zpravy/ostrava/ostrava-svinov'],
        ],
    ],
    [
        'id'    => 'region-karvinsko',
        'label' => 'Karvinsko',
        'url'   => '/zpravy/karvinsko',
        'cities' => [
            ['label' => 'Havířov',      'url' => '/zpravy/karvinsko/havirov'],
            ['label' => 'Karviná',      'url' => '/zpravy/karvinsko/karvina'],
            ['label' => 'Horní Suchá',  'url' => '/zpravy/karvinsko/horni-sucha'],
            ['label' => 'Rychvald',     'url' => '/zpravy/karvinsko/rychvald'],
            ['label' => 'Stonava',      'url' => '/zpravy/karvinsko/stonava'],
            ['label' => 'Těrlicko',     'url' => '/zpravy/karvinsko/terlicko'],
            ['label' => 'Dolní Lutyně', 'url' => '/zpravy/karvinsko/dolni-lutyne'],
            ['label' => 'Český Těšín',  'url' => '/zpravy/karvinsko/cesky-tesin'],
            ['label' => 'Orlová',       'url' => '/zpravy/karvinsko/orlova'],
            ['label' => 'Albrechtice',  'url' => '/zpravy/karvinsko/albrechtice'],
        ],
    ],
    [
        'id'    => 'region-frydeckomistecko',
        'label' => 'Frýdeckomístecko',
        'url'   => '/zpravy/frydeckomistecko',
        'cities' => [
            ['label' => 'Jablunkov',               'url' => '/zpravy/frydeckomistecko/jablunkov'],
            ['label' => 'Nošovice',                'url' => '/zpravy/frydeckomistecko/nosovice'],
            ['label' => 'Palkovice',               'url' => '/zpravy/frydeckomistecko/palkovice'],
            ['label' => 'Frýdek-Místek',           'url' => '/zpravy/frydeckomistecko/frydek-mistek'],
            ['label' => 'Čeladná',                 'url' => '/zpravy/frydeckomistecko/celadna'],
            ['label' => 'Frýdlant nad Ostravicí',  'url' => '/zpravy/frydeckomistecko/frydlant-nad-ostravici'],
        ],
    ],
    [
        'id'    => 'region-opavsko',
        'label' => 'Opavsko',
        'url'   => '/zpravy/opavsko',
        'cities' => [
            ['label' => 'Ludgeřovice', 'url' => '/zpravy/opavsko/ludgerovice'],
            ['label' => 'Opava',       'url' => '/zpravy/opavsko/opava'],
        ],
    ],
    [
        'id'    => 'region-novojicinsko',
        'label' => 'Novojičínsko',
        'url'   => '/zpravy/novojicinsko',
        'cities' => [
            ['label' => 'Studénka',   'url' => '/zpravy/novojicinsko/studenka'],
            ['label' => 'Nový Jičín', 'url' => '/zpravy/novojicinsko/novy-jicin'],
        ],
    ],
    [
        'id'    => 'region-bruntalsko',
        'label' => 'Bruntálsko',
        'url'   => '/zpravy/bruntalsko',
        'cities' => [
            ['label' => 'Bruntál', 'url' => '/zpravy/bruntalsko/bruntal'],
            ['label' => 'Krnov',   'url' => '/zpravy/bruntalsko/krnov'],
        ],
    ],
];
```

- [ ] **Krok 2: Uprav `navigation.php` — nahraď inline `regions` pole require-em**

Najdi v `navigation.php` blok:
```php
    'regions' => [
        [
            'id'     => 'region-all',
            ...celý blok až po poslední ],
        ],
    ],
```

A nahraď ho:
```php
    'regions' => require __DIR__ . '/news_navigation.php',
```

- [ ] **Krok 3: Ověř, že web stále funguje**

Otevři v prohlížeči homepage polar-symfony a zkontroluj, že menu s regiony se zobrazuje stejně jako před změnou.

- [ ] **Krok 4: Commit**

```bash
git add config/navigation.php config/news_navigation.php
git commit -m "feat: regions navigation extracted to generated file"
```

---

### Task 2: Úprava Playkit — nové generování pro Symfony

**Files:**
- Modify: `c:\web\www\playkit\module\Polar\src\Controller\Polar\PolarWriteController.php`
  - Metoda `setConfigNavigationAction()`, přibližně řádky 382–488

**Formát generovaného souboru `news_navigation.php`:**

```php
<?php

/**
 * POZOR: Tento soubor je generovaný administrací Playkit.
 * Neupravuj ručně — změny budou přepsány.
 * Zdroj: Playkit → Polar → setConfigNavigationAction()
 */
return [
    [
        'id'     => 'region-all',
        'label'  => 'MS kraj',
        'url'    => '/zpravy',
        'cities' => [],
    ],
    [
        'id'    => 'region-ostrava',
        'label' => 'Ostrava',
        'url'   => '/zpravy/ostrava',
        'cities' => [
            ['label' => 'Ostrava-město', 'url' => '/zpravy/ostrava/ostrava-mesto'],
            ...
        ],
    ],
    ...
];
```

- [ ] **Krok 1: V `setConfigNavigationAction()` najdi sekci zápisu souboru a zakomentuj Laminas generaci**

Najdi blok (přibližně řádky 466–475):
```php
            $dir = PUBLIC_PATH . '/data/polar/news/export/web/';
            $file = 'news.config.php';
            file_put_contents($dir . $file, print_r($navigation, true));
            // Kopírování do webu polar.cz
            copy($dir . $file, PUBLIC_PATH . '/../../polar.cz/module/News/config/' . $file);

            // Smazaní Cache
            $cacheFile = PUBLIC_PATH . '/../../polar.cz/data/cache/module-config-cache.application.config.cache.php';
            if (file_exists($cacheFile)) {
                @unlink($cacheFile);
            }
```

Zakomentuj celý blok:
```php
            // === LAMINAS (polar.cz) — zachováno pro referenci, generuje se pro Symfony níže ===
            // $dir = PUBLIC_PATH . '/data/polar/news/export/web/';
            // $file = 'news.config.php';
            // file_put_contents($dir . $file, print_r($navigation, true));
            // // Kopírování do webu polar.cz
            // copy($dir . $file, PUBLIC_PATH . '/../../polar.cz/module/News/config/' . $file);
            //
            // // Smazaní Cache
            // $cacheFile = PUBLIC_PATH . '/../../polar.cz/data/cache/module-config-cache.application.config.cache.php';
            // if (file_exists($cacheFile)) {
            //     @unlink($cacheFile);
            // }
            // === konec LAMINAS ===
```

- [ ] **Krok 2: Přidej generování Symfony souboru `news_navigation.php` ihned za zakomentovaný blok**

```php
            // === SYMFONY (polar-symfony) ===
            $symfonyRegions = "<?php\n\n";
            $symfonyRegions .= "/**\n";
            $symfonyRegions .= " * POZOR: Tento soubor je generovaný administrací Playkit.\n";
            $symfonyRegions .= " * Neupravuj ručně — změny budou přepsány.\n";
            $symfonyRegions .= " * Zdroj: Playkit → Polar → setConfigNavigationAction()\n";
            $symfonyRegions .= " */\n";
            $symfonyRegions .= "return [\n";

            // region-all (statický)
            $symfonyRegions .= "\t[\n";
            $symfonyRegions .= "\t\t'id'     => 'region-all',\n";
            $symfonyRegions .= "\t\t'label'  => 'MS kraj',\n";
            $symfonyRegions .= "\t\t'url'    => '/zpravy',\n";
            $symfonyRegions .= "\t\t'cities' => [],\n";
            $symfonyRegions .= "\t],\n";

            if ($regions) {
                foreach ($regions as $region) {
                    $regionUrl = '/zpravy/' . $region['url'];
                    $symfonyRegions .= "\t[\n";
                    $symfonyRegions .= "\t\t'id'    => 'region-" . $region['url'] . "',\n";
                    $symfonyRegions .= "\t\t'label' => '" . addslashes($region['region']) . "',\n";
                    $symfonyRegions .= "\t\t'url'   => '" . $regionUrl . "',\n";
                    $symfonyRegions .= "\t\t'cities' => [\n";

                    $cities = $this->cityRepository->getAllPostsByRegionId($region['id']);
                    if ($cities) {
                        foreach ($cities as $city) {
                            $cityUrl = $regionUrl . '/' . $city['url'];
                            $symfonyRegions .= "\t\t\t['label' => '" . addslashes($city['city']) . "', 'url' => '" . $cityUrl . "'],\n";
                        }
                    }

                    $symfonyRegions .= "\t\t],\n";
                    $symfonyRegions .= "\t],\n";
                }
            }

            $symfonyRegions .= "];\n";

            $symfonyDir = PUBLIC_PATH . '/../../polar-symfony/config/';
            file_put_contents($symfonyDir . 'news_navigation.php', $symfonyRegions);
            // === konec SYMFONY ===
```

> **Poznámka k cestě:** `PUBLIC_PATH` v Playkit ukazuje na `playkit/public`. Cesta `PUBLIC_PATH . '/../../polar-symfony/config/'` předpokládá, že `playkit` a `polar-symfony` jsou ve stejné složce (`www/`). Ověř tuto cestu před spuštěním — pokud se liší, uprav ji.

- [ ] **Krok 3: Ověř, že `$regions` je dostupné**

Proměnná `$regions` je definována dřív v metodě (čtení z DB pro Laminas generaci). Pokud jsi zakomentoval celý `$regions` blok nebo se iteruje jen uvnitř `if ($regions)` — zkontroluj, že dotaz `$this->regionRepository->getAllPostsForMenu()` je stále aktivní (nezakomentovaný).

Relevantní část, která musí zůstat aktivní:
```php
$regions = $this->regionRepository->getAllPostsForMenu();
```

- [ ] **Krok 4: Otestuj v Playkit admin**

1. Přihlaš se do Playkit adminu
2. Spusť akci, která volá `setConfigNavigationAction()` (přidej nebo uprav město/region)
3. Zkontroluj, že soubor `c:\web\www\polar-symfony\config\news_navigation.php` byl přepsán
4. Zkontroluj obsah souboru — správná PHP syntax, správné URL

- [ ] **Krok 5: Ověř polar-symfony menu**

Otevři polar-symfony v prohlížeči a zkontroluj, že navigace s regiony a městy odpovídá novému obsahu.

- [ ] **Krok 6: Commit v Playkit**

```bash
git add module/Polar/src/Controller/Polar/PolarWriteController.php
git commit -m "feat: generate news_navigation.php for polar-symfony, keep laminas generation commented"
```

---

## Poznámky

- **Dvojité čtení DB**: V Task 2 jsou `$cities` čteny znovu pro Symfony generaci. Pokud je Laminas generace zakomentována celá (včetně `foreach`), je toto první čtení. Pokud Laminas `foreach` zůstane aktivní, DB se dotazuje dvakrát — to je v pořádku, jde o admin akci spouštěnou ručně.
- **`addslashes()`**: Používáme pro escapování labelů v generovaném PHP kódu (jména měst mohou obsahovat apostrofy, ale v praxi ne — pro jistotu tam je).
- **Cache Symfony**: Symfony routes cache není třeba mazat — wildcard routes jsou statické a nemění se.
