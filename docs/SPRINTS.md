# Sprint status — 12 November 2026

This status is based on repository evidence. No existing S1–S2 task checklist
was found; the requested S1 scopes are recorded below.

| Scope | Status | Evidence / remaining work |
| --- | --- | --- |
| S1 Symfony skeleton | Committed | `a759a36` initializes Symfony 7.4. |
| S1-02 development environment | Implemented; verified | PHP 8.3 Dockerfile, MySQL 8.0 with a named persistent volume and authenticated SQL health check, Doctrine ORM and migrations configuration, example environment and setup instructions. Container startup, MySQL health and Doctrine connectivity were verified during S1-04/S1-05. |
| S1-04 entities and migrations | Implemented | Nine Doctrine entities with SQL constraints and a generated conditional unique index; one migration applied to the development and isolated test databases; 13 MySQL integration tests passing. |
| S1-05 demo data | Implemented and verified in Docker | Dedicated MySQL database, standard fixture loads, deterministic reload test. S1-05 tests pass in the standard suite; isolated `Demo Fixtures` suite (1 test, 16 175 assertions) passes. |
| S1-06 basic back-office | Implemented and verified in Docker | French Symfony/Twig admin at `/admin`, read-only physical plan, events, representations, tariffs, inventory, orders and tickets. Standard suite 28 tests / 130 assertions; Demo Fixtures 1 test / 16 175 assertions; Chrome desktop and mobile check. `/admin` returns 404 in `prod` until authentication exists. |
| Sprint 2 — Niveau 2 | Implemented and verified | Atomic reservations, locked confirmation, expiration, cancellation and simulated refunds; 49 standard tests / 255 assertions, plus isolated Demo Fixtures. |
| Sprint 3 — first admin product step | Implemented and verified | Shared chair map, inspect/select modes, real session switch, local multi-seat recap and S2 handoff; 55 tests / 296 assertions, Chrome 1440/390. See SEAT_MAP.md. |
| Niveau 3 remaining scope | Not implemented | Authentication/roles, public purchase journey and QR codes remain. |

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


## Sprint 2 — Niveau 2 (12 novembre 2026)

Branche `sprint-2-regles-metier` créée depuis `origin/sprint-1-modeliser` à
`6b62a5e` après fetch. Aucun reset, rebase, squash ou force-push. Le changement
préexistant de `config/reference.php` est conservé à l'identique et exclu des commits.

| Tâche | Statut | Preuve |
| --- | --- | --- |
| S2-01 — Règles | Implémenté et documenté | D11–D13, SPRINT2.md, 600 secondes configurables, horloge injectable UTC à la seconde, prix accepté figé |
| S2-02 — Réservation | Vérifié | Transaction mono-représentation multi-places, ordre de verrous déterministe, contrôle serveur et rollback de toute la demande en cas de conflit |
| S2-03 — Vente | Vérifié | Émission/commande/inventaire atomiques, confirmation rejouée sans nouveau billet, échec tardif sans effet sur une vente payée |
| S2-04 — Expiration | Vérifié | `app:reservations:expire`, récupération sans cron, échéance exclusive, nettoyage rejouable, protection du nouveau propriétaire |
| S2-05 — Annulation/remboursement | Vérifié | Prix payé conservé, remboursement simulé unique, invitation à zéro, revente, blocage après début/annulation, cas historique multi-représentations |
| S2-06 — Parcours local | Vérifié | Formulaire Horizon, paiement réussi/échoué, annulation, montants remboursés ; tests fonctionnels de parcours et refus CSRF |
| S2-07 — Validation | Vérifié | MySQL 8 / PHP 8.3.36, migration additive sur base isolée, deux processus concurrents réellement bloqués, validations Docker et fixtures séparées |

Résultats réellement observés :

- Base effective `billetterie_s2_test` lue avant migration et avant purges ; la
  migration `Version20261112010000` est additive (lignes acceptées + échéance).
- Composer strict, conteneur Symfony, 23 templates Twig et schéma Doctrine : OK.
- Suite standard dans Docker : **49 tests, 255 assertions**, aucun échec,
  d'abord sur `billetterie_s2_test`, puis avec la commande standard sur
  `billetterie_test` après sa migration. Les gardes des tests refusent toute
  autre base avant de vider des tables.
- `ConcurrencyTest` : **2 tests, 29 assertions**. Les deux workers sont observés
  dans `SHOW FULL PROCESSLIST` en `FOR UPDATE` avant la libération de la
  barrière. Course A1/R1 : une seule commande obtient la réservation ; une
  seule vente et un seul billet sont ensuite confirmés en base. R2 reste
  disponible. Course expiration/confirmation à l'échéance : commande expirée,
  zéro billet, aucune place vendue.
- Suite `Demo Fixtures` relancée séparément, avec le wrapper S1-05 et sa base
  `billetterie_s105_verify_test` confirmée : **1 test, 16 175 assertions**, OK.
  Les quantités et relations des fixtures sont conservées.
- Base locale utilisateur `billetterie` identifiée avant migration : migration
  additive appliquée, schéma validé. Aucune purge ou réécriture des ventes de
  cette base ; aucun rechargement de fixtures dans cette base.

Le premier essai groupé de préparation a été refusé par la revue automatique,
qui n'avait pas établi le suffixe `_test`. Les étapes ont été séparées et
`SELECT DATABASE()` a ensuite confirmé la base autorisée avant la migration.
Ce blocage est résolu ; aucun succès de migration n'a été annoncé sur la seule
base de la commande refusée.

Les erreurs 403/404/405 affichées pendant PHPUnit correspondent aux tests des
protections attendues ; la suite se termine sans échec. La simulation ne
contacte aucun prestataire bancaire. Les comptes, rôles, QR codes et parcours
public du Niveau 3 ne sont pas réalisés. Aucun Dockerfile ni migration Sprint 1
n'a été modifié. Voir README.md et SPRINT2.md pour les commandes et limites.

## Sprint 3 — Première étape produit admin (12 novembre 2026)

Branche `sprint-3-produit` créée depuis `origin/sprint-2-regles-metier` à
`8e62b5e`, après fetch et vérification. Aucun historique réinitialisé. Le diff
préexistant de `config/reference.php` reste inchangé et exclu du commit.

Livré : écran de représentation avec résumé sombre et plan clair, 400 fauteuils
schématiques regroupés par zone, cinq états avec symboles, inspection et
sélection locale, récapitulatif et retrait multiple, panneau des vraies dates
avec confirmation avant abandon de sélection. Les liens événement/programmation
ouvrent directement le plan ; détail, tableau, filtres et pagination restent
disponibles. Le composant Twig/JS commun sert aussi l'inspection de l'inventaire.

La sélection rejoint le formulaire de réservation simulée du Sprint 2. Le POST
CSRF et `TicketingService` gardent la validation sous verrou et l'atomicité.
Un conflit renvoie au plan actualisé, conserve les seules places libres et
n'effectue aucune réservation partielle. Aucune règle commerciale, entité,
migration, fixture, dépendance ou configuration Docker n'est modifiée.

Résultats vérifiés :

- Docker PHP 8.3.36 : Composer strict, conteneur, 25 templates Twig et schéma OK.
- `billetterie_test` confirmée avant la suite : **55 tests, 296 assertions**, OK,
  dont six tests du plan et les tests métier/concurrence/CSRF existants.
- Chrome 1440 × 1000 et 390 × 844, sur les fixtures existantes de la base dédiée
  `billetterie_s105_verify_test` : A1 vendue sur R1, libre et sélectionnable sur
  R2 ; multi-sélection, retrait, vidage, changement de date confirmé/annulé et
  préremplissage du formulaire vérifiés. Aucun POST de vente sur ces fixtures.
- Flèches, Début/Fin, Entrée, retour du focus après Échap et gestes tactiles
  d'ajout/retrait contrôlés. Cibles mobiles 44 × 46 px ; pas de débordement global.
- Captures inspectées et documentation de réutilisation : [SEAT_MAP.md](SEAT_MAP.md).

La suite isolée Demo Fixtures n'a pas été relancée pour cette étape ; elle est
inchangée. Aucune base utilisateur ni base de démonstration n'a été purgée.
Cette étape ne clôt pas tout le Sprint 3 : comptes, rôles, parcours client et
guichetier dédiés, QR codes et paiement externe restent hors de ce livrable.
