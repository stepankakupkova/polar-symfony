# Upload na ostrý server

## před nahráním (lokálně)

```bash
composer install --no-dev --optimize-autoloader
```

## co nahrát

**Nahrát:**
- `src/`, `config/`, `templates/`, `public/`
- `vendor/`
- `bin/console`
- `.env` (základní bez hesel)

**Nenahrávat / nepřepisovat:**
- `.env.local` — na serveru je vlastní s produkčními hesly
- `.vscode/` — lokální nastavení VS Code
- `var/` (cache ani logy)
- `composer.json`, `composer.lock`, `symfony.lock`, ... a další soubory v root

## po nahrání (lokálně — obnovit dev prostředí)

```bash
composer install
```

## po nahrání (na serveru přes FTP)

- smaž složku `var/cache/prod/` (Symfony ji automaticky vygeneruje při prvním requestu)
