# Sprint status — 11 November 2026

This status is based on repository evidence. No existing S1–S2 task checklist
was found; the requested S1 scopes are recorded below.

| Scope | Status | Evidence / remaining work |
| --- | --- | --- |
| S1 Symfony skeleton | Committed | `a759a36` initializes Symfony 7.4. |
| S1-02 development environment | Implemented; verified | PHP 8.3 Dockerfile, MySQL 8.0 with a named persistent volume and authenticated SQL health check, Doctrine ORM and migrations configuration, example environment and setup instructions. Container startup, MySQL health and Doctrine connectivity were verified during S1-04/S1-05. |
| S1-04 entities and migrations | Implemented | Nine Doctrine entities with SQL constraints and a generated conditional unique index; one migration applied to the development and isolated test databases; 13 MySQL integration tests passing. |
| S1-05 demo data | Implemented and verified in Docker | Dedicated MySQL database, standard fixture loads, deterministic reload test. S1-05 tests pass in the standard suite; isolated `Demo Fixtures` suite (1 test, 16 175 assertions) passes. |
| S1-06 basic back-office | Implemented and verified in Docker | French Symfony/Twig admin at `/admin`, read-only physical plan, events, representations, tariffs, inventory, orders and tickets. Standard suite 28 tests / 130 assertions; Demo Fixtures 1 test / 16 175 assertions; Chrome desktop and mobile check. `/admin` returns 404 in `prod` until authentication exists. |
| S1–S2 remaining business scope | Not implemented | Payment flow, Level 2 reservation/refund workflows, authentication/roles (Niveau 3) and QR codes remain. |

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
- `php bin/phpunit` sur la configuration standard : **13 tests, 35 assertions**,
  aucun échec (base `billetterie_test`, aucune purge de la base dédiée).
- `php bin/phpunit --testsuite "Demo Fixtures"` sur la base dédiée :
  **1 test, 16 175 assertions**, aucun échec.
- Le nouveau test charge deux fois les fixtures avec purge standard et compare
  toutes les lignes métier en remplaçant les identifiants techniques par des
  clés naturelles ; il contrôle quantités, catégories, canaux, prix, états,
  associations et invitation gratuite.
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

## S1-06 — Back-office minimal (11 novembre 2026)

Interface Symfony/Twig en français, sobre et responsive, accessible à
`/admin`. EasyAdmin n’a pas été retenu : les vues majoritairement en lecture
seule, les formulaires dédiés et les verrous métier demandaient un contrôle plus
direct que le CRUD générique.

Pages et actions livrées :

- tableau de bord avec compteurs et navigation ;
- salles, zones et places en consultation, avec pagination des places ;
- événements : liste filtrée, détail, création et modification ;
- représentations : liste filtrée avec compteurs, détail, création via
  `RepresentationInventoryService` et modification avec salle verrouillée ;
- tarifs : liste filtrée, détail, création, modification, désactivation CSRF,
  refus des montants négatifs, invitation à 0 MAD et verrouillage de la
  représentation d’un tarif déjà utilisé ;
- inventaire : pagination et filtres par représentation, zone, statut et
  recherche ;
- commandes et billets : liste et détail en lecture seule, navigation croisée.

Protections vérifiées : validation serveur, CSRF des formulaires et de la
désactivation, absence de routes génériques de modification pour l’inventaire,
les commandes et les billets, refus du changement de salle après inventaire,
conservation du prix payé après modification d’un tarif. En `APP_ENV=prod`,
`/admin` renvoie 404 tant que l’authentification du Niveau 3 n’existe pas.

Vérifications exécutées dans Docker (PHP 8.3.36, MySQL 8.0) :

- `composer validate --strict` : valide.
- `php bin/console lint:container` : OK.
- `php bin/console lint:twig templates` : 20 fichiers valides.
- `php bin/console doctrine:schema:validate` : mapping correct, schéma synchronisé.
- `php bin/phpunit` : **28 tests, 130 assertions**, aucun échec, sur
  `billetterie_test`.
- `php bin/phpunit --testsuite "Demo Fixtures"` : **1 test, 16 175 assertions**,
  aucun échec, sur `billetterie_s105_verify_test`.
- Vérification visuelle Chrome headless en 1440×1000 et 390×844 : tableau de
  bord, représentations, inventaire filtré A1/R1 et formulaire événement.

Aucune base utilisateur n’a été purgée. Aucune migration ni configuration Docker
n’a été modifiée. Les paiements, remboursements, réservations temporaires,
QR codes, rôles et authentification restent hors de S1-06.

## Redesign S1-06 — Horizon (11 novembre 2026)

Identité sombre/ambre centrée sur les événements, affiches originales locales,
bannière panoramique, catalogue et programmation par dates réelles. Inventaire,
commandes, billets et formulaires conservent les protections serveur de S1-06.
Aucun travail du Niveau 2, aucune modification des fixtures, des migrations ou de Docker.

Vérifications Docker : Composer strict, conteneur, 22 templates Twig et schéma
Doctrine valides ; **31 tests standard, 153 assertions**. La suite Demo Fixtures
reste séparée et n'a pas été modifiée. Contrôles Chrome headless desktop
1440×1000 et mobile 390×844 sur la base S1-05 dédiée : parcours catalogue/fiche/date,
retour depuis une représentation, salle, filtres A1/R1 et A1/R2, commandes,
billets, erreurs de formulaire, états vides et secours. Débordements corrigés,
aucun débordement global mesuré sur ces pages ; tableaux dans leurs régions
scrollables. Aucun chargement ni purge de base utilisateur.

Voir [DESIGN.md](DESIGN.md) pour les captures avant/après, les contrastes, les
sources des assets, la correction MIME du serveur PHP et les limites du contrôle.
