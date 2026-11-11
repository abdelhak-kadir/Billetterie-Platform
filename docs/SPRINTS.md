# Sprint status — 11 November 2026

This status is based on repository evidence. No existing S1–S2 task checklist
was found; the requested S1 scopes are recorded below.

| Scope | Status | Evidence / remaining work |
| --- | --- | --- |
| S1 Symfony skeleton | Committed | `a759a36` initializes Symfony 7.4. |
| S1-02 development environment | Implemented; verified | PHP 8.3 Dockerfile, MySQL 8.0 with a named persistent volume and authenticated SQL health check, Doctrine ORM and migrations configuration, example environment and setup instructions. Container startup, MySQL health and Doctrine connectivity were verified during S1-04/S1-05. |
| S1-04 entities and migrations | Implemented | Nine Doctrine entities with SQL constraints and a generated conditional unique index; one migration applied to the development and isolated test databases; 13 MySQL integration tests passing. |
| S1-05 demo data | Implemented and verified in Docker | Dedicated MySQL database, standard fixture loads, deterministic reload test and full suite passing (14 tests, 16 210 assertions). |
| S1–S2 remaining business scope | Not implemented | Back-office, payment flow and Level 2 reservation/refund workflows remain. |

## S1-02 verification

Passed: Composer strict validation, installed host platform requirements, Symfony
YAML and service-container lint, Doctrine mapping discovery (no entities, as
expected), Compose configuration validation and Git whitespace checks.

Docker startup was initially blocked because `/var/run/docker.sock` required
elevated access. The socket was later made accessible through a user-applied ACL;
PHP 8.3 container startup, MySQL 8.0 health, Doctrine connectivity and the S1-05
fixture/test workflow were then verified in Docker. Host PHP 8.5 results are not
used as proof of PHP 8.3 compatibility.

Run the README verification commands with Docker access. To check persistence
without adding business data, initialize migration metadata using
`docker compose exec app php bin/console doctrine:migrations:sync-metadata-storage`,
recreate services with `docker compose down` then `docker compose up -d --wait`,
and confirm the `doctrine_migration_versions` table still exists using
`docker compose exec app php bin/console dbal:run-sql 'SHOW TABLES'`.

## S1-05 — Données de démonstration (11 novembre 2026)

Implémentation et validation Docker/MySQL réalisées sur une base dédiée. Doctrine Fixtures
Bundle 4.3.1 et data-fixtures 2.2.1 ajoutés en require-dev, plateforme Composer
PHP 8.3 conservée. Une fixture ordonnée génère le plan, les événements et les
représentations via le service existant, puis les tarifs et les ventes.

Jeu chargé puis recompté en SQL dans `billetterie_s105_verify_test` : 1 salle, 2 zones, 400 places,
3 événements, 4 représentations, 1 600 inventaires, 16 tarifs, 4 commandes payées,
6 billets valides dont une invitation gratuite ; A1 vendue sur R1 et disponible
sur R2. Clients en `example.com`, conformément au cadrage S1-05 (ce choix remplace
pour ce jeu la proposition `example.test` du document de décisions).

`Commande` accepte désormais une date de création optionnelle afin de rendre
les données reproductibles sans réflexion ni écriture SQL contournant le modèle.
Le comportement par défaut et le schéma restent inchangés. Aucune migration,
configuration Docker ou fonctionnalité de paiement ajoutée.

Vérifications réussies dans Docker (PHP 8.3.36, MySQL 8.0) :

- `composer validate --strict`.
- `php bin/console lint:container`.
- `php bin/console doctrine:schema:validate` sur la base dédiée : mapping et schéma synchronisés.
- `php bin/phpunit` : **14 tests, 16 210 assertions**, aucun échec.
- Le nouveau test charge deux fois les fixtures avec purge standard et compare
  toutes les lignes métier hors identifiants techniques ; il contrôle les
  quantités, catégories, canaux, prix, états, associations et invitation gratuite.
- Chargement CLI standard effectué avant les tests puis après la suite pour
  laisser le jeu de démonstration en place. Comptages finaux : 1 / 400 / 3 / 4 /
  1 600 / 16 / 4 / 6 (salles, places, événements, représentations, inventaires,
  tarifs, commandes, billets). Six inventaires vendus, 1 594 disponibles.
- Requête SQL finale : Parterre / A / 1 vendue au concert du 1 décembre 2026
  (R1), disponible au concert du 2 décembre (R2).

Avant les purges, `SELECT DATABASE()` a confirmé exactement
`billetterie_s105_verify_test`. Aucune purge de la base utilisateur n'a été
réalisée. Le test refuse également toute autre base avant sa première purge et
chaque rechargement. Le blocage initial d'accès au socket Docker a été résolu
par une autorisation ACL appliquée manuellement par l'utilisateur ; les résultats
ci-dessus ont ensuite été observés réellement. Les vérifications hôte PHP 8.5
ne sont pas utilisées comme preuve de compatibilité PHP 8.3.

Le README documente le chargement, la purge, les dates UTC, les prix fictifs,
la répartition de démonstration et le scénario A1 / R1 / R2. Les workflows de
paiement, de concurrence et le back-office restent hors de S1-05.
