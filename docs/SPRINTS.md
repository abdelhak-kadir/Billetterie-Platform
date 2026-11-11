# Sprint status — 11 November 2026

This status is based on repository evidence. No existing S1–S2 task checklist
was found; only the requested S1-02 and S1-04 scopes are recorded.

| Scope | Status | Evidence / remaining work |
| --- | --- | --- |
| S1 Symfony skeleton | Committed | `a759a36` initializes Symfony 7.4. |
| S1-02 development environment | Implemented; runtime verification blocked | PHP 8.3 Dockerfile, MySQL 8.0 with a named persistent volume and authenticated SQL health check, Doctrine ORM and migrations configuration, example environment and setup instructions. |
| S1-04 entities and migrations | Implemented | Nine Doctrine entities with SQL constraints and a generated conditional unique index; one migration applied to the development and isolated test databases; 13 MySQL integration tests passing. |
| S1–S2 remaining business scope | Not implemented | Demo fixtures (S1-05), back-office, payment flow and Level 2 reservation/refund workflows remain. |

## S1-02 verification

Passed: Composer strict validation, installed host platform requirements, Symfony
YAML and service-container lint, Doctrine mapping discovery (no entities, as
expected), Compose configuration validation and Git whitespace checks.

Docker startup was attempted but access to `/var/run/docker.sock` was denied,
including an escalated attempt. `sudo -n` requires interactive authentication.
Consequently PHP 8.3 container startup, MySQL health, persistence across service
recreation and Doctrine connectivity have not been verified. Host PHP is 8.5;
host checks do not substitute for container checks. A host migrations-status
attempt failed because the Compose hostname `database` is unavailable there.

Run the README verification commands with Docker access. To check persistence
without adding business data, initialize migration metadata using
`docker compose exec app php bin/console doctrine:migrations:sync-metadata-storage`,
recreate services with `docker compose down` then `docker compose up -d --wait`,
and confirm the `doctrine_migration_versions` table still exists using
`docker compose exec app php bin/console dbal:run-sql 'SHOW TABLES'`.
