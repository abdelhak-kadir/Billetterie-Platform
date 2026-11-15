# Tests Horizon

Ce dossier vérifie les règles métier et les parcours de l'application avec PHPUnit.
`Unit/` contient les tests sans base, `Integration/` les tests MySQL et de
concurrence, `Functional/` les parcours HTTP Symfony. `Support/` fournit
l'horloge de test et les processus utilisés pour la concurrence.

Installer d'abord l'application selon [INSTALL.md](../INSTALL.md). Exécuter les
commandes suivantes depuis la racine du dépôt, dans le même projet Compose.
Elles utilisent les identifiants fictifs de `.env.example` ; adapter le compte
et l'URL si la configuration locale a changé.

## Suite principale

Sur un volume neuf, créer la base de tests et accorder les droits au compte
local de démonstration :

```sh
docker compose exec -T database sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot -e "CREATE DATABASE IF NOT EXISTS billetterie_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON billetterie_test.* TO '\''billetterie'\''@'\''%'\'';"'
docker compose exec -T app php bin/console --env=test dbal:run-sql 'SELECT DATABASE() AS target_database'
```

Continuer uniquement si la base effective est **`billetterie_test`** et si elle
est jetable : les tests vident les tables pour préparer leurs scénarios.

```sh
docker compose exec -T app php bin/console --env=test doctrine:migrations:migrate --no-interaction
docker compose exec -T app php bin/phpunit
```

La suite principale inclut les achats client et guichet, les droits, les
billets, les indicateurs et les courses concurrentes sur MySQL. Elle exclut
`Demo Fixtures` par la configuration de [phpunit.dist.xml](../phpunit.dist.xml).
Après une évolution du schéma, relancer les migrations avant les tests.

Pour isoler les tests de concurrence :

```sh
docker compose exec -T app php bin/phpunit --filter ConcurrencyTest
```

Ces tests lancent deux processus PHP et vérifient leur attente sur les verrous
MySQL. Voir [SPRINT2.md](../docs/SPRINT2.md#contrôle-de-concurrence) pour le protocole.
Ne pas lancer les suites en parallèle sur une même base.

## Suite Demo Fixtures

Cette suite charge deux fois le jeu complet et compare les données et leurs
associations. Elle **purge sa base dédiée** à chaque chargement et refuse toute
base autre que `billetterie_s105_verify_test`.

Créer cette base puis définir le raccourci `s105` dans le terminal courant :

```sh
docker compose exec -T database sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot -e "CREATE DATABASE IF NOT EXISTS billetterie_s105_verify_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON billetterie_s105_verify_test.* TO '\''billetterie'\''@'\''%'\'';"'
s105() {
  docker compose exec -T -e APP_ENV=test \
    -e 'DATABASE_URL=mysql://billetterie:billetterie@database:3306/billetterie_s105_verify?serverVersion=8.0&charset=utf8mb4' \
    app "$@"
}
s105 php bin/console dbal:run-sql 'SELECT DATABASE() AS target_database'
```

Doctrine ajoute `_test` au nom de base en environnement `test`. Continuer
uniquement si le résultat est exactement **`billetterie_s105_verify_test`**
et si cette base est jetable.

```sh
s105 php bin/console doctrine:migrations:migrate --no-interaction
s105 php bin/phpunit --testsuite "Demo Fixtures"
s105 php bin/console doctrine:schema:validate
```

Le test effectue lui-même les deux chargements standards. Il vérifie notamment
une salle, 400 places, trois événements, quatre représentations, 1 600
inventaires, seize tarifs, quatre commandes et six billets. Il contrôle aussi
A1 vendue sur R1 et libre sur R2. Voir les [données de démonstration](../INSTALL.md#données-de-démonstration)
pour les dates et tarifs. Ne pas utiliser `--append` pour contourner un conflit
de fixtures ; ne pas lancer cette suite sur la base utilisée dans le navigateur.

## Vérification des commandes documentées

Vérification du 14 novembre 2026 : clone local de la version `d9db557`, sans `vendor/`
ni `.env.local`, un projet Compose `horizon-readme-check` et un volume MySQL
créé pour ce contrôle. Les modifications applicatives locales du dépôt de
travail ne sont pas incluses dans ce clone.

Résultats observés avec PHP 8.3.36 et MySQL 8.0 :

- Construction et démarrage Compose, installation depuis `composer.lock`,
  trois migrations, fixtures, trois comptes et validation du schéma : OK.
- Suite principale : **71 tests, 429 assertions**, dont les tests de concurrence MySQL.
- Demo Fixtures : **1 test, 16 175 assertions**, puis schéma valide sur sa base dédiée.
- Parcours HTTP avec sessions distinctes : connexion des trois rôles, achat de
  Parterre / A / 4 sur R1, paiement simulé et PDF téléchargé. Le plan du guichet
  refuse cette place vendue sur R1 et la montre libre sur R2 ; l'admin la montre
  aussi vendue sur R1 et les indicateurs sont accessibles.

Les blocs de préparation et d'exécution des tests ont été extraits de ce
document et exécutés tels quels, avec le nom de projet Compose de vérification.
Les bases ont été identifiées avant les chargements ou tests qui les vident.
Les liens relatifs, les ancres et la syntaxe des blocs shell ont été contrôlés.

Ce contrôle utilise des requêtes HTTP ; il ne renouvelle pas la validation
visuelle. Les captures et contrôles navigateur antérieurs restent dans
[NIVEAU3.md](../docs/NIVEAU3.md#résultats-observés). Le clone et les volumes
de vérification sont jetables ; aucune base de travail existante n'a été purgée.
