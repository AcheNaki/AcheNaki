# 0002: Database Ownership

Status: Accepted

## Context

AcheNaki? uses Supabase PostgreSQL as its planned managed database and Laravel as its authoritative REST API and writer. Schema changes must remain reviewable, testable, repeatable across environments, and recoverable without relying on undocumented dashboard actions.

Supabase can execute ad hoc SQL and expose direct client APIs, but using those as a parallel application backend or primary schema workflow would split validation and migration ownership.

## Decision

Laravel migrations own application schema evolution. Seeders own reproducible development/master-data loading where appropriate. Supabase provides managed PostgreSQL infrastructure.

The browser will not write raw application data directly to Supabase. Database credentials remain server-side. Any emergency or operational SQL change must be followed by an equivalent repository migration when it changes application schema.

Fast basic tests may use in-memory SQLite, but PostgreSQL-specific behavior must receive PostgreSQL integration coverage before production deployment.

## Consequences

- Schema history is versioned with the application and reproducible in local, staging, and production environments.
- Laravel validation and database constraints remain aligned under one backend boundary.
- Supabase dashboard SQL is not the normal schema-management workflow.
- Real Supabase credentials are not needed to develop and test database-independent behavior.
- SQLite tests provide speed but cannot certify PostgreSQL-specific behavior; an integration environment is still required.
- Deployment procedures must run reviewed Laravel migrations using secure server-side credentials.
