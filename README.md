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

The nine business entities and the S1-04 migration are available. Apply migrations with `docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction`.

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

## S1-05 — Données de démonstration

Doctrine Fixtures est une dépendance de développement (installer avec les
require-dev). **Un chargement standard purge toutes les tables métier de la
base ciblée. Ne jamais lancer cette commande sur la base utilisateur ou de
production.** Ne pas employer `--append` pour contourner les doublons.

Pour une vérification isolée, préparer uniquement `billetterie_s105_verify_test`.
Exemple avec les identifiants de développement par défaut du Compose ; adapter
le compte et l'URL si vous les avez changés, sans commiter de secret :

```sh
docker compose exec -T database sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot -e "CREATE DATABASE IF NOT EXISTS billetterie_s105_verify_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON billetterie_s105_verify_test.* TO '\''billetterie'\''@'\''%'\'';"'

# En environnement test, Doctrine ajoute _test au nom de cette URL.
s105() {
  docker compose exec -T -e APP_ENV=test \
    -e 'DATABASE_URL=mysql://billetterie:billetterie@database:3306/billetterie_s105_verify?serverVersion=8.0&charset=utf8mb4' \
    app "$@"
}
s105 php bin/console dbal:run-sql 'SELECT DATABASE() AS target_database'
```

**Vérifier que le résultat est exactement `billetterie_s105_verify_test` avant
chaque chargement et avant PHPUnit** (les tests S1-04 vident également les tables).
Ne poursuivre que si cette base est dédiée et jetable :

```sh
s105 php bin/console doctrine:migrations:migrate --no-interaction
s105 php bin/console doctrine:fixtures:load --no-interaction
s105 php bin/console doctrine:schema:validate
s105 php bin/phpunit
# Vérifie le jeu complet et deux chargements avec purge standard :
s105 php bin/phpunit --filter DemoFixturesTest
```

Le test de démonstration vérifie lui-même `SELECT DATABASE()` avant chaque purge
et refuse toute base autre que `billetterie_s105_verify_test`. Il compare deux
jeux rechargés sans tenir compte des identifiants auto-incrémentés. Le reste de
la suite utilise aussi cette base dédiée via la fonction `s105` ci-dessus.

Jeu attendu (données fictives, aucune condition d'éligibilité ajoutée) :

- Une salle, Parterre A–O × 20 = 300 places et Balcon A–E × 20 = 100 places.
  Cette répartition est un choix de démonstration, pas une exigence du sujet.
- Trois événements (concert, théâtre, conférence), quatre représentations et
  exactement 1 600 inventaires générés par `RepresentationInventoryService`.
- R1 : Concert Horizon, 1 décembre 2026 à 19h UTC ; R2 : même concert,
  2 décembre à 19h UTC ; R3 : théâtre, 3 décembre à 19h UTC ; R4 : conférence,
  4 décembre à 17h UTC. Toutes les dates sont explicites en UTC, à afficher en
  `Africa/Casablanca` ; elles ne dépendent pas du jour de chargement.
- Seize tarifs : par représentation, plein 10 000, réduit 8 000, groupe 6 000,
  invitation 0 centimes de MAD (100, 80, 60 et 0 MAD). Prix fictifs uniformes.
- Quatre commandes payées `DEMO-CMD-R1` à `DEMO-CMD-R4`, six billets valides,
  six inventaires vendus et 1 594 disponibles. Une commande en ligne et trois
  au guichet ; quatre clients fictifs `client1@example.com` à `client4@example.com`.
  Une invitation gratuite sur R4. Ventes datées du 10 novembre 2026 à
  12h00–12h03 UTC (création, confirmation et émission identiques).
- Les ventes immédiates passent par `reserver()`, `vendre()` et `marquerPayee()`.
  L'échéance intermédiaire d'une minute est purement technique pour ces ventes
  déjà confirmées ; elle ne définit aucune durée commerciale de réservation.

Pour retrouver A1 : `DEMO-BIL-R1-1` vend Parterre / A / 1 sur R1 ; le même siège
physique reste disponible sur R2. Les codes R1/R2 sont des références de fixture
et des dates, pas des identifiants SQL garantis. Requête sur la base dédiée :

```sh
s105 php bin/console dbal:run-sql "SELECT e.titre, r.debut, z.nom, p.rangee, p.numero, i.statut FROM inventaire_place i JOIN representation r ON r.id=i.representation_id JOIN evenement e ON e.id=r.evenement_id JOIN place p ON p.id=i.place_id JOIN zone z ON z.id=p.zone_id WHERE e.titre='Concert Horizon' AND z.nom='Parterre' AND p.rangee='A' AND p.numero=1 ORDER BY r.debut"
```

Après la suite complète, les autres tests peuvent avoir remplacé le jeu de
fixtures. Vérifier à nouveau la base ciblée puis relancer le chargement standard
pour laisser le jeu de démonstration en place.
