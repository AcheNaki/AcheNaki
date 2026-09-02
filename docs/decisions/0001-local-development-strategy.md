# 0001: Local Development Strategy

Status: Accepted for initial scaffolding

## Context

The Windows workstation has Laragon 8 with PHP 8.3.30 and Composer 2.9.4. PHP is not currently on the shell `PATH`, but Composer works when its PHAR is invoked with Laragon's PHP executable. Core Laravel PHP extensions are loaded. The bundled `pdo_pgsql` extension is disabled in `php.ini` but was successfully loaded in a read-only command-line test.

Docker CLI and Compose are installed, but the Docker daemon was not running during inspection and the sandbox could not read the user's Docker configuration.

Exact Laravel compatibility must still be verified from official documentation before scaffolding.

## Options considered

### Native Laragon PHP and Composer

Uses the existing Windows PHP distribution. It has the lowest immediate setup and runtime overhead, provided the selected Laravel version supports PHP 8.3 and PostgreSQL PDO is enabled before database work.

### Docker-based Laravel development

Provides a reproducible runtime but adds daemon, image, networking, and Windows filesystem complexity that is not yet justified for this modular monolith.

### Another local PHP installation

Could provide a different PHP version or isolation, but would duplicate a working installation without current evidence of a compatibility need.

## Decision

Use native Laragon PHP and Composer for initial Laravel development, subject to official Laravel compatibility verification immediately before scaffolding.

Until a PATH strategy is explicitly approved, tooling may invoke Laragon's `php.exe` and `composer.phar` by explicit path. Enable `pdo_pgsql` later as a separate, approved environment-configuration step before PostgreSQL connectivity is attempted.

Revisit this decision if the chosen framework version is incompatible, native extension behavior proves unreliable, or team reproducibility becomes a concrete problem.

## Consequences

- Initial backend setup remains simple and uses already-installed tooling.
- The development and Render production PHP versions/extensions must be kept compatible.
- `pdo_pgsql` is available but still requires intentional enablement; no configuration was changed by this decision.
- Developers need a documented invocation or approved PATH setup.
- Docker remains an available fallback, not a current requirement.

