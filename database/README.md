# Database SPARTA (MariaDB)

## One-time import

Run once, into an empty database. Nothing here drops a database or a table.

```bash
mariadb -u sparta -p -e "CREATE DATABASE IF NOT EXISTS sparta
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mariadb -u sparta -p sparta < database/schema.sql
mariadb -u sparta -p sparta < database/routines.sql
php database/seeders/seed.php
```

`schema.sql` uses `CREATE TABLE IF NOT EXISTS`, so a second import is a no-op for
existing tables. Later column/index changes belong in `database/migrations/`, not
in `schema.sql`. `routines.sql` uses `CREATE OR REPLACE`, so it can be re-applied.

Upgrade an existing database with the migrations, in order:

```bash
mariadb -u sparta -p sparta < database/migrations/001_add_alat_foto.sql
mariadb -u sparta -p sparta < database/migrations/002_add_pengaturan.sql
mariadb -u sparta -p sparta < database/migrations/003_add_login_attempts.sql
```

## Connection contract

`config/database.php` returns `['dsn', 'username', 'password']` and `bootstrap/app.php`
returns `['db' => PDO]`. Environment variables: `DB_HOST`, `DB_PORT`, `DB_NAME`
(default `sparta`), `DB_USER`, `DB_PASSWORD`.

## Seed accounts

| Username | Password | Role |
|---|---|---|
| `admin` | `admin123` | admin |
| `petugas` | `petugas123` | petugas |
| `peminjam` | `peminjam123` | peminjam |

Passwords are hashed at runtime with `password_hash()`. The seeder skips any
existing username, so real accounts are never overwritten. Demo only, local use.

## Checks

```bash
php tests/database/schema_test.php   # exit 0 pass, 1 fail, 2 no MariaDB
```
