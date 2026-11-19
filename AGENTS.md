# AGENTS.md

Server-rendered PHP 8.2+ MVC over MariaDB. No Composer: `app/autoload.php` maps `App\Foo` → `app/Foo.php`. UI copy, comments in views, and docs are Indonesian. Specs: `docs/PRD-SPARTA.md` (requirements), `docs/DESIGN.md` (UI rules), `database/README.md` (DB). `.codegraph/` is indexed and gitignored; use `codegraph explore "<question>"` before grepping.

## Commands

```sh
npm run build                          # rebuild public/assets/css/app.css (tracked; run after adding/changing classes in views)
php -S 127.0.0.1:8000 -t public public/router.php   # dev server; docroot MUST be public/
php database/seeders/seed.php          # seed local DB (Docker uses database/seeders/seed.sql instead)
php tests/http_returns_test.php        # one test = one file, no runner
```

Test order: `database_config`, `security`, `database/schema`, `http_auth`, `http_master_data`, `http_users`, `http_loans`, `http_returns`, `http_backoffice`. HTTP tests start their own PHP server and need the PHP `curl` extension. Exit `2` = skipped (DB unreachable/non-disposable), not pass. Locally the fixtures refuse anything but `127.0.0.1:33306`; add `SPARTA_ALLOW_NONDISPOSABLE_DB=1` to run against `3306`. Each test owns a prefix (`IRT-`, `TST-`, `ITLN-`, …) and deletes only those rows — never aim them at real data.

## Where a change must land

- New endpoint: `app/routes.php` + instantiate the controller in `app/bootstrap.php` (manual DI container, no autowiring).
- RBAC and CSRF are enforced centrally in `app/Core/App.php`: routes are private unless `public: true`, `roles` list is checked there, every POST needs `_token`. Controllers therefore only validate input and redirect.
- View pattern: `ob_start()` → markup → `$content = ob_get_clean()` → `require dirname(__DIR__, 2) . '/layouts/app.php'`. Menus/`$activeNav` live in `app/Views/layouts/app.php`.
- Always escape with `e()`; icons via `icon('lucide-name')`, allowlisted from `public/assets/icons/lucide/` (no CDN).

## Domain rules not obvious from names

- Stock: only `kondisi = 'baik'` restores `stok_tersedia`; `rusak_ringan|rusak_berat|hilang` restore 0 (`Pengembalian::restoredStock`). Admin corrections apply the delta and must keep `0 <= stok_tersedia <= stok_total`.
- Fine tariff lives in table `pengaturan` (`tarif_denda`, Rp/day/unit) via `App\Models\Pengaturan`; `pengembalian.denda` rows are historical and never recalculated. Late days use calendar `diff()`, never seconds.
- `database/schema.sql` is the single source for fresh installs; existing DBs get `database/migrations/NNN_*.sql`. `database/routines.sql` (fine function, report procedure, audit trigger) must be imported after schema and needs DELIMITER-aware clients.
- Admin mutations go through `ActivityLog::record`; loan status audit is a DB trigger.
- `config/database.php` auto-loads `.env`; real env wins. Compose interpolates `DB_NAME/DB_USER/DB_PASSWORD` from `.env` but pins `DB_HOST=db`.

## Gotchas

- Do not weaken `location ^~ /uploads/` in `docker/nginx/default.conf`: its nested regex deny for `php*|phtml|phar|…` replaces the global PHP handler, otherwise uploads leak source or execute.
- `.env` and `config/database.local.php` are gitignored and hold real credentials; never commit or print them.
- Docker: image is `ukk_sparta-app` (dir-derived). If `apk` TLS fails during build (bridge network blocks Alpine CDN), build with `DOCKER_BUILDKIT=0 docker build --network host -t ukk_sparta-app .` then `docker compose up -d --no-build`. Runtime is PHP 8.5 (`php85`/`php-fpm85` paths, `/etc/php85/…`).
- Demo logins: `admin/admin123`, `petugas/petugas123`, `peminjam/peminjam123`.
- Timezone is pinned to `Asia/Jakarta` in `app/bootstrap.php`.
