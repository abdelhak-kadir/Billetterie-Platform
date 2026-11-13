# Billetterie-Platform

Horizon : billetterie Symfony 7.4 / PHP 8.3 / MySQL 8. Le Niveau 3 ajoute
comptes, achat client, vente au guichet, billets PDF avec QR signé et indicateurs.
Voir [le guide et les preuves du Niveau 3](docs/NIVEAU3.md).

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

The ticketing model and additive migrations, including account ownership, are available. Apply migrations with `docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction`.

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
chaque chargement et avant la suite `Demo Fixtures`.** La suite standard
`php bin/phpunit` utilise `billetterie_test` et ne touche pas à cette base
dédiée. Ne poursuivre que si cette base est dédiée et jetable :

```sh
s105 php bin/console doctrine:migrations:migrate --no-interaction
s105 php bin/console doctrine:fixtures:load --no-interaction
s105 php bin/console doctrine:schema:validate
# Suite isolée : purge uniquement la base dédiée et vérifie deux chargements.
s105 php bin/phpunit --testsuite "Demo Fixtures"
```

La suite standard se lance sans configuration spéciale et ne purge pas la base
dédiée ; elle utilise `billetterie_test`. Sur un volume neuf, créer et migrer
cette base une fois :

```sh
docker compose exec -T database sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot -e "CREATE DATABASE IF NOT EXISTS billetterie_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON billetterie_test.* TO '\''billetterie'\''@'\''%'\'';"'
docker compose exec app php bin/console --env=test doctrine:migrations:migrate --no-interaction
docker compose exec app php bin/phpunit
```

Le test de démonstration vérifie lui-même `SELECT DATABASE()` avant chaque purge
et refuse toute base autre que `billetterie_s105_verify_test`. Il appartient à la
suite PHPUnit séparée `Demo Fixtures` ; `php bin/phpunit` sans option n'exécute
que la suite standard. Le test compare deux jeux rechargés en remplaçant les
identifiants auto-incrémentés par des clés naturelles, y compris les
associations.

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

## S1-06 — Back-office minimal

Le back-office est accessible à **http://localhost:8080/admin** avec
`docker compose up -d`.

Depuis le Niveau 3, Symfony Security exige le rôle administrateur sur `/admin`.
Le garde local historique ne bloque plus les pages de gestion en production ;
les actions de paiement, vente et remboursement simulés restent interdites hors
des environnements `dev` et `test`.

### Fonctionnalités au terme du Sprint 1

Les nouvelles actions de vente simulée du Sprint 2 sont décrites plus bas.

- **Salles** : liste et détail en consultation seule, avec zones, rangées,
  numéros et pagination des places. Le plan physique n’est pas modifiable.
- **Événements** : liste filtrée, détail, création et modification du titre, de
  la description et du type. Aucune suppression destructive.
- **Représentations** : liste filtrée, détail, création et modification. La
  création passe par `RepresentationInventoryService` : une salle de 400 places
  produit exactement 400 lignes d’inventaire, dans la même transaction. Les
  compteurs disponibles/vendues/réservées/bloquées sont affichés. La salle est
  verrouillée dès que l’inventaire existe, y compris côté serveur.
- **Tarifs** : liste filtrée, détail, création, modification et désactivation.
  Les montants sont saisis et affichés en MAD, stockés en centimes. Les prix
  négatifs sont refusés, une invitation doit valoir 0 MAD, un tarif déjà utilisé
  ne peut pas changer de représentation et les billets émis conservent leur prix
  payé.
- **Inventaire** : consultation seule, paginée, avec filtres par représentation,
  zone, disponibilité et recherche (événement, zone, rangée ou numéro). Le
  scénario Parterre / A / 1 pour R1 et R2 se retrouve avec ces filtres.
- **Commandes et billets** : listes et détails en lecture seule, avec navigation
  commande ↔ billets, représentation, place, tarif, prix payé, canal et statut.
  Aucun formulaire ne permet de changer un statut, un prix payé ou une
  association historique.

### Protection et limites

- Validation serveur de tous les formulaires, protection CSRF des formulaires
  et de la désactivation d’un tarif, échappement Twig par défaut.
- Les actions interdites sont refusées côté serveur, pas seulement masquées.
- Ce jalon S1-06 n'ajoutait ni authentification ni vente ; ces fonctions sont
  désormais disponibles dans les Sprints 2 et 3 décrits ci-dessous.
- Aucune migration ni configuration Docker n’a été modifiée par S1-06.

### Vérifications

```sh
docker compose exec app composer validate --strict
docker compose exec app php bin/console lint:container
docker compose exec app php bin/console lint:twig templates
docker compose exec app php bin/console doctrine:schema:validate
docker compose exec app php bin/phpunit
s105 php bin/phpunit --testsuite "Demo Fixtures"
```

La suite standard couvre les tests S1-04, les tests fonctionnels S1-06 et les
tests unitaires du back-office. Elle utilise `billetterie_test` et ne purge pas
la base dédiée des fixtures. La vérification visuelle a été effectuée avec
Chrome headless en 1440×1000 et 390×844 sur le tableau de bord, les
représentations, l’inventaire filtré A1/R1 et un formulaire d’événement.

### Identité visuelle Horizon

Le redesign S1-06 conserve les fonctionnalités et protections existantes :
affiches originales locales, catalogue, dates réelles et inventaire de gestion.
Les choix, licences, limites, contrôles d'accessibilité et captures avant/après
sont décrits dans [docs/DESIGN.md](docs/DESIGN.md).
Les visuels des trois événements S1-05 sont associés à leurs titres dans
`EventPresentationExtension` ; un visuel de secours est prévu pour tout nouveau
programme. Aucun service d'image externe n'est appelé à l'affichage.

## Sprint 3 — Plan de salle admin

Depuis une fiche événement ou la liste des représentations, ouvrir la séance
pour accéder à son **Plan de salle**. Le détail et l'inventaire en tableau restent
accessibles depuis le panneau de résumé. Activer **Sélectionner**, choisir des
places libres puis **Continuer vers la réservation** : le formulaire du Sprint 2
reçoit les sièges choisis et revalide la réservation au moment du POST protégé
par CSRF. Une sélection sur le plan reste locale jusqu'à ce POST.

Le panneau **Dates et représentations** propose les séances réelles du même
événement et demande confirmation avant d'effacer une sélection. Le plan
schématique contient les 400 places de démonstration, Parterre et Balcon, avec
défilement contenu et navigation par flèches. Sur les fixtures existantes,
Parterre / A / 1 est vendue sur le concert du 1 décembre 2026 (R1), libre sur
celui du 2 décembre (R2). Choisir **Concert Horizon** dans le catalogue puis ces
deux dates permet de retrouver ce scénario, sans dépendre des identifiants SQL.

Voir [le contrat du composant partagé, les limites et les captures](docs/SEAT_MAP.md).
Ce premier jalon admin est maintenant réutilisé dans les parcours client et
guichetier du Niveau 3, décrits dans [NIVEAU3.md](docs/NIVEAU3.md).

### Comptes et démonstration du Niveau 3

Après les migrations, créer les comptes fictifs sans purge :

```sh
docker compose exec -T app php bin/console app:demo:users
```

| Compte de démonstration | Rôle | Mot de passe de démonstration |
| --- | --- | --- |
| `client@example.com` | Client | `HorizonDemo2026!` |
| `guichet@example.com` | Guichetier | `HorizonDemo2026!` |
| `admin@example.com` | Administrateur | `HorizonDemo2026!` |

La commande est réservée à `dev`/`test` et conserve les comptes préexistants
sans changer leurs mots de passe ou rôles. Ces identifiants publics servent
uniquement à la démonstration. L'inscription publique crée exclusivement un client.

Ouvrir `/` pour le catalogue, `/guichet` pour la vente rapide et
`/admin/indicateurs` pour le remplissage et les recettes. Choisir une place
**initialement libre** de R1, acheter en ligne et simuler un paiement réussi.
La place devient vendue au guichet et dans l'admin pour R1 ; la même place reste
libre sur R2. A1 reste le scénario historique S1-05, déjà vendue sur R1 : utiliser
par exemple A4 si elle est encore libre. Télécharger le PDF depuis la commande.
Le QR signé ouvre un contrôle réservé au personnel ; une annulation rend le
billet invalide et bloque les nouveaux téléchargements (410).

Les achats de validation ont été effectués sur `billetterie_s3_demo_test`,
distincte de la base utilisateur. Aucune fixture n'a été chargée dans cette
dernière ; si elle est vide, le catalogue y affiche un état vide. Le guide
[Niveau 3](docs/NIVEAU3.md) explique comment préparer et afficher le jeu isolé.
Les montants encaissés, remboursés et nets sont des montants de **simulation**.

## Sprint 2 — Réservations, ventes et remboursements simulés

Branche : `sprint-2-regles-metier`, issue du Sprint 1 distant `6b62a5e`.
Les règles et transitions sont détaillées dans [docs/SPRINT2.md](docs/SPRINT2.md)
et les décisions D11–D13 de [docs/DECISIONS.md](docs/DECISIONS.md).

Une réservation couvre plusieurs places d'une seule représentation, pour
**600 secondes configurables** via `RESERVATION_TTL_SECONDS`. Elle expire dès
`now >= échéance` (secondes UTC). Les prix acceptés sont figés à la réservation,
conservés sur les lignes et copiés dans les billets ; annulation et remboursement
utilisent le prix réellement payé, jamais le prix courant du catalogue.

Après installation des dépendances existantes, appliquer la nouvelle migration
additive (aucune purge ni rechargement des fixtures nécessaire) :

```sh
docker compose exec app php bin/console dbal:run-sql 'SELECT DATABASE() AS target_database'
# Vérifier que la connexion est bien celle que vous souhaitez faire évoluer.
docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction
```

### Démonstration locale

1. Ouvrir `/admin/representations`, puis une représentation programmée et future.
2. Choisir **Réserver (simulation)**, saisir `client@example.com`, sélectionner
   plusieurs places et un tarif. La disponibilité est revérifiée sous verrou :
   une seule place indisponible fait échouer toute la demande.
3. La commande affiche ses places, les prix acceptés et son échéance exacte.
   **Simuler un paiement réussi** émet les billets et vend les places atomiquement.
   Un rejeu conserve les billets déjà émis. **Simuler un paiement échoué** libère
   uniquement les places encore détenues par cette commande.
4. **Annuler la réservation** libère ses places. Après paiement, **Annuler et
   rembourser (simulation)** conserve les billets annulés et affiche le montant
   remboursé. Une invitation à zéro ne crée aucun remboursement ; les appels
   répétés n'ajoutent pas de montant. Une place ne revient à la vente que si la
   représentation est encore programmée et non commencée.
5. Depuis le détail d'une représentation, ouvrir **Annulation de la représentation**
   pour invalider ses billets, simuler les remboursements et bloquer ses places.
   Les autres représentations d'une commande historique restent indépendantes.

Le Sprint 2 n'ajoutait aucun compte ni QR : ces fonctions sont maintenant
livrées au Niveau 3. Aucun paiement ou remboursement bancaire réel n'est ajouté.
Les actions restent protégées par CSRF et validation serveur ; la simulation
est désactivée en production. La modification d'un tarif après réservation ou
vente ne change pas le prix accepté ni le prix payé. Un tarif déjà référencé
par une réservation ou un billet ne peut plus changer de représentation.

### Nettoyer les réservations expirées

```sh
docker compose exec app php bin/console app:reservations:expire
```

Planifier cette commande régulièrement (par exemple chaque minute) sur la base
appropriée. Elle est rejouable et ne touche pas aux commandes payées. Même sans
ce nettoyage, une nouvelle réservation peut récupérer une place expirée. Une
notification de succès arrivée à l'échéance ou après ne crée aucun billet.

### Vérifications isolées du Sprint 2

Pour ne jamais purger la base utilisateur, préparer `billetterie_s2_test`,
puis employer ce wrapper. Doctrine ajoute `_test` à l'URL en environnement test :

```sh
docker compose exec -T database sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot -e "CREATE DATABASE IF NOT EXISTS billetterie_s2_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON billetterie_s2_test.* TO '\''billetterie'\''@'\''%'\'';"'
s2() {
  docker compose exec -T -e APP_ENV=test \
    -e 'DATABASE_URL=mysql://billetterie:billetterie@database:3306/billetterie_s2?serverVersion=8.0&charset=utf8mb4' \
    app "$@"
}
s2 php bin/console dbal:run-sql 'SELECT DATABASE() AS target_database'
# Poursuivre uniquement si le résultat est billetterie_s2_test.
s2 php bin/console doctrine:migrations:migrate --no-interaction
s2 composer validate --strict
s2 php bin/console lint:container
s2 php bin/console lint:twig templates
s2 php bin/console doctrine:schema:validate
s2 php bin/phpunit
s2 php bin/phpunit --filter ConcurrencyTest
```

Les bases de tests sont jetables : la suite standard vide uniquement
`billetterie_test` ou `billetterie_s2_test`, avec garde avant toute purge.
La suite `Demo Fixtures` reste exclue par défaut ; pour la relancer, utiliser
le wrapper `s105` documenté ci-dessus après avoir appliqué les migrations sur
sa base dédiée. Le changement de modèle ne demande pas de modifier ses données.
Ne pas utiliser `--append` pour corriger un problème de chargement.
