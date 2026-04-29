# Upload na ostrý server

## před nahráním (lokálně)

```bash
composer install --no-dev --optimize-autoloader
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod
```

## co nahrát

**Nahrát:**
- `src/`, `config/`, `templates/`, `public/`
- `vendor/` (po `composer install --no-dev`)
- `var/cache/prod/` (předgenerovaná lokálně výše)
- `bin/console`
- `.env` (základní bez hesel)

**Nenahrávat / nepřepisovat:**
- `.env.local` — na serveru je vlastní s produkčními hesly
- `var/cache/dev/`, `var/log/`
- `composer.json`, `composer.lock`, `symfony.lock`, ... a další soubory v root

## po nahrání

```bash
composer install
```
