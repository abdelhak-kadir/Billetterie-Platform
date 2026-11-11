# Billetterie-Platform

## Setup

Requirements: Docker Engine with Compose v2 and permission to access the Docker daemon.
The development container runs PHP 8.3 with PDO MySQL, Intl, Zip and OPcache.

1. Copy the example environment file:

   ```sh
   cp .env.example .env
   ```

2. Build and start the application and MySQL 8.0:

   ```sh
   docker compose up --build -d --wait
   ```

   The application is available at http://localhost:8080. MySQL data is
   persisted in the `database-data` Docker volume and the application waits
   for the database health check before starting.

3. Install dependencies:

   ```sh
   docker compose exec app composer install
   ```

4. Verify the runtime and the Doctrine database connection:

   ```sh
   docker compose ps
   docker compose exec app php -v
   docker compose exec app php bin/console dbal:run-sql 'SELECT VERSION() AS version, DATABASE() AS database_name, 1 AS connected'
   docker compose exec app php bin/console doctrine:migrations:status
   ```

There are no business entities, fixtures or migration classes yet. Once migrations
exist, apply them with `docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction`.

The credentials in `.env.example` are for local development. Keep `MYSQL_DATABASE`,
`MYSQL_USER`, `MYSQL_PASSWORD` and `DATABASE_URL` consistent when changing them;
URL-encode credentials in `DATABASE_URL`. An existing `.env.local` can override
Symfony's connection settings. MySQL initialization variables only apply to an
empty data volume. The database hostname `database` resolves inside Compose;
run the database commands through the app container.

On Linux, if your user ID differs from 1000, build with
`UID=$(id -u) GID=$(id -g) docker compose build app` before starting services.

To stop the services without removing persistent data:

```sh
docker compose down
```

`docker compose down --volumes` deletes database data and the Composer cache.

See [sprint status](docs/SPRINTS.md), [design decisions](docs/DECISIONS.md) and
the [planned data model](docs/MODELE.md).
