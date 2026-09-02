# Development Environment Baseline

Updated on 2026-09-01 after frontend and backend scaffolding.

## Current workstation

| Tool | Observed state |
| --- | --- |
| OS | Windows (`Microsoft Windows 10.0.26200` as reported by PowerShell), x64 |
| PowerShell | 7.6.5 |
| Git | 2.52.0.windows.1 |
| Node.js | 22.22.2 |
| npm | 10.9.7 |
| pnpm | 11.22.0 |
| Next.js | 16.3.3 |
| React | 19.2.8 |
| React DOM | 19.2.8 |
| Laragon | 8, installed at `C:\laragon` |
| PHP | Laragon PHP 8.3.30, ZTS x64; not on shell `PATH` |
| Composer | 2.9.4; available through Laragon and verified with Laragon PHP |
| Laravel | Framework 13.29.0, created from application skeleton 13.10.1; requires PHP `^8.3` |
| Docker CLI | 29.1.3 |
| Docker Compose | 2.40.3-desktop.1 |
| Docker daemon | Available (Docker Desktop must be started; it is not running by default) |
| PostgreSQL CLI | `psql` not on `PATH`; reachable inside the container with `docker exec` |

## PHP inspection

Installed PHP executable:

```text
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe
```

Loaded Laravel-relevant extensions include `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `mbstring`, `openssl`, `pcre`, `pdo`, `session`, `tokenizer`, and `xml`. Also observed as loaded: `bcmath`, `intl`, `sodium`, and `gd`.

`pdo_pgsql` is enabled in Laragon's `php.ini` and verified through `PDO::getAvailableDrivers()`. Before the one-line change, the original file was backed up to:

```text
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.ini.asenaki-20260901.bak
```

The non-PDO `pgsql` extension remains disabled because Laravel uses PDO. No Windows `PATH` or unrelated Laragon configuration was changed. The `zip` and `redis` extensions remain unloaded and are not currently required.

Composer's batch wrapper currently calls `php` from `PATH`, so it does not work directly in the present shell. Running `composer.phar` with the explicit Laragon PHP executable succeeded.

## Initial tooling choices

Use native Laragon PHP and Composer for initial backend development, as recorded in ADR 0001. Verify the chosen Laravel version supports PHP 8.3 before scaffolding, and enable `pdo_pgsql` only in a later approved environment step.

Use **pnpm** for the frontend. Both npm and pnpm are available, but pnpm provides efficient dependency storage, strict dependency resolution, and a straightforward committed lockfile (`pnpm-lock.yaml`). A pnpm workspace is not required unless a concrete multi-package frontend need emerges.

Package lock files must be committed once applications exist.

## Selected framework baseline

The stable package releases were verified against official framework documentation and package registries before scaffolding:

- Next.js 16.3.3 with React and React DOM 19.2.8
- Laravel Framework 13.29.0, which requires PHP `^8.3`
- Node.js 22.22.2 satisfies Next.js 16's Node.js 20.9 minimum
- PHP 8.3.30 satisfies Laravel 13's PHP requirement

Lock files are authoritative for the complete resolved dependency sets. Do not change framework versions without compatibility verification and an explicit dependency update.

## Local development commands

Run the frontend from a PowerShell terminal:

```powershell
cd frontend
pnpm dev
```

The frontend is then available at `http://localhost:3000`.

Set both frontend API base variables in `frontend/.env.local` for local development:

```text
NEXT_PUBLIC_API_BASE_URL=http://127.0.0.1:8000/api/v1
API_BASE_URL=http://127.0.0.1:8000/api/v1
```

`NEXT_PUBLIC_API_BASE_URL` is used by browser-only reporting, saved-locality, and dashboard interactions. `API_BASE_URL` is used by server-rendered public locality pages; it is not a secret and can use an environment-appropriate internal address in deployment.

Run the backend from a separate PowerShell terminal without changing the global `PATH`:

```powershell
cd backend
& 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' artisan serve --host=127.0.0.1 --port=8000
```

The API is then available at `http://127.0.0.1:8000`; its foundation health endpoint is `GET /api/v1/health`.

## Database commands

Populate `backend/.env` with real database credentials only when an approved PostgreSQL or Supabase database is available. Then run migrations and the canonical JSON-backed location seed from `backend/`:

```powershell
& 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' artisan migrate --seed
```

Run backend tests without external database credentials:

```powershell
& 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' artisan test
```

The suite defaults to in-memory SQLite (configured in `phpunit.xml`) for speed.

### Running the suite against real PostgreSQL

`phpunit.xml` does not force its `<env>` values, so process environment variables take
precedence. Start a disposable PostgreSQL container and point the suite at it:

```powershell
docker run -d --name achenaki-pg -e POSTGRES_PASSWORD=local_only `
  -e POSTGRES_USER=achenaki -e POSTGRES_DB=achenaki_test `
  -p 127.0.0.1:55432:5432 postgres:17-alpine

$env:DB_CONNECTION='pgsql'; $env:DB_HOST='127.0.0.1'; $env:DB_PORT='55432'
$env:DB_DATABASE='achenaki_test'; $env:DB_USERNAME='achenaki'
$env:DB_PASSWORD='local_only'; $env:DB_SSLMODE='prefer'; $env:DB_URL=''
& 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' artisan test
```

`tests/Feature/PostgresSchemaTest.php` covers CHECK-constraint naming and partial unique
indexes; it is skipped automatically on any other driver.

The full suite, canonical seeding (55 areas / 334 sub-areas), migration rollback, the three
rebuild commands, and concurrent multi-process writes have been verified against
PostgreSQL 17 locally. **Actual Supabase connectivity is still not verified**, because no
project credentials have been provided; managed pooling, TLS, and statement-timeout
behaviour remain untested.
