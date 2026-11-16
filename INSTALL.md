# Installer Horizon et préparer la démonstration

Ce guide décrit l'installation locale avec Docker. Le [README](README.md)
présente l'application et le scénario à montrer à l'évaluateur.
Les paiements et remboursements sont simulés ; garder `APP_ENV=dev` pour les essayer.

## Prérequis

- Git, Docker Engine et Docker Compose v2 ou ultérieur, avec accès au moteur Docker.
- Le port 8080 libre et une connexion réseau pour construire l'image et installer les dépendances.
- Aucun PHP, Composer ou MySQL à installer sur la machine hôte.

L'image fournit PHP 8.3, Composer et les extensions nécessaires ; Compose lance
MySQL 8.0. Toutes les commandes suivantes se lancent à la racine du dépôt.

## Installation sur un clone neuf

```sh
git clone https://github.com/abdelhak-kadir/Billetterie-Platform.git
cd Billetterie-Platform
```

Si une autre copie utilise déjà Compose sur cette machine, choisir un nom de
projet distinct **avant le premier démarrage**, puis le conserver pour toutes
les commandes de cette session :

```sh
export COMPOSE_PROJECT_NAME=horizon-evaluation
```

Ce nom sépare les volumes et le réseau. Il ne change pas le port : utiliser
`HORIZON_HTTP_PORT` si une autre application écoute déjà sur 8080.
Un clone neuf ne garantit pas un volume neuf si le nom de projet a déjà servi.

```sh
docker compose up --build -d --wait
```

Compose attend MySQL sain, puis le conteneur app installe les dépendances de
`composer.lock`. En `dev`, il applique les migrations et appelle la commande
existante `app:demo:prepare --initialize-empty` avant de lancer le serveur.
`--wait` attend une réponse HTTP 200 du catalogue. Aucun fichier à copier, PHP,
Composer ou MySQL à installer sur l'hôte. `docker compose up` fonctionne aussi
au premier plan ; arrêter avec Ctrl+C conserve les volumes.

Un verrou de fichier protège les dépendances partagées et un verrou MySQL
sérialise migrations et préparation pour la même base, y compris entre clones.
Les deux commandes tournent dans des processus PHP distincts afin de repartir
d'un état Doctrine neuf après les DDL MySQL. En cas d'échec, le serveur ne démarre
pas : consulter `docker compose logs app`, corriger puis relancer Compose.

L’initialisation vérifie `SELECT DATABASE()` dans le même conteneur et le même
environnement `dev` que le web. Elle est interdite sur une base PHPUnit. Si les
tables métier sont vides, le chargement exclut `app_user` de la purge et refuse
les comptes autres que les trois adresses fictives. Un jeu déjà installé est
conservé, y compris ses achats et comptes. Une base non vide inconnue est
refusée : utilisez un projet Compose isolé et un volume neuf.

Ouvrir **[http://localhost:8080](http://localhost:8080)**. Pour conserver une
instance déjà en service, installer le clone de démo avec un autre nom et port :

```sh
COMPOSE_PROJECT_NAME=horizon-evaluation HORIZON_HTTP_PORT=8081 docker compose up --build -d --wait
```

L’URL devient `http://localhost:8081`. Conserver les mêmes variables pour toutes
les commandes Compose de cette installation, par exemple avec `export` dans
le terminal. `app:demo:prepare` et `app:demo:users` peuvent être relancées sans
purger un jeu reconnu ni changer les mots de passe existants.

Pour une exécution détaillée ou une installation existante, les commandes de
migration restent décrites ci-dessous. Le chargement manuel de fixtures purge
la base ciblée : il n’est pas une mise à jour d’une base contenant des données.

`./bin/setup-demo` reste un raccourci : il délègue l'installation à Compose,
vérifie le schéma et le catalogue, puis active le worker Sheets si la configuration
Google est prête. Il ne duplique ni Composer, ni migrations, ni fixtures.

## Désactiver l'initialisation automatique

```sh
HORIZON_AUTO_DEMO=0 docker compose up --build -d --wait
```

Les dépendances restent installées ; aucune migration ni fixture automatique
n'est lancée. La base doit alors être préparée séparément pour que le catalogue
réponde 200. Ce drapeau est une variable **Compose**, à exporter dans le terminal ou à
placer dans `.env` ; `.env.local` configure Symfony et ne définit pas ce drapeau.
L'environnement Symfony effectif est lu depuis sa configuration habituelle.
Hors `dev`, le démarrage n'exécute jamais les migrations ou fixtures de démo ;
en `dev`, une base nommée `test`, terminée par `_test` ou `_testN` est refusée
avant les migrations. Les bases PHPUnit restent réservées aux tests automatisés.

## Configuration

Les valeurs de `.env.example` sont des exemples publics pour une démonstration
locale. Aucun identifiant réel n'est nécessaire.

| Variable | Utilisation |
| --- | --- |
| `APP_ENV` | `dev` active les ventes et paiements de démonstration ; `prod` les interdit. |
| `HORIZON_AUTO_DEMO` | Variable Compose, `1` par défaut ; `0` désactive les migrations et données automatiques au démarrage. |
| `HORIZON_HTTP_PORT` | Variable Compose, `8080` par défaut ; permet un autre port sans modifier le fichier. |
| `APP_SECRET` | Signature des QR et protections Symfony. Remplacer la valeur d'exemple pour tout autre usage ; une rotation invalide les anciennes signatures QR. |
| `MYSQL_DATABASE`, `MYSQL_USER`, `MYSQL_PASSWORD`, `MYSQL_ROOT_PASSWORD` | Initialisation de MySQL sur un volume vide. |
| `DATABASE_URL` | Connexion Symfony ; doit correspondre à la base et au compte MySQL. Le nom d'hôte `database` fonctionne dans Compose. |
| `RESERVATION_TTL_SECONDS` | Durée des réservations, 600 secondes par défaut. |

Si les identifiants changent, encoder les caractères spéciaux dans
`DATABASE_URL`. Un `.env.local` ou une variable d'environnement peut remplacer
la configuration Symfony ; vérifier la base effective avec `SELECT DATABASE()`.
Ne pas commiter `.env.local`, une clé privée ou un mot de passe réel.
Changer les variables MySQL ne reconfigure pas un volume déjà initialisé.

## Migrations sur une installation existante

Les migrations ajoutent le schéma et ses évolutions sans recharger la démonstration.
Identifier la base avant de les appliquer :

```sh
docker compose exec -T app php bin/console dbal:run-sql 'SELECT DATABASE() AS target_database'
docker compose exec -T app php bin/console doctrine:migrations:status
docker compose exec -T app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec -T app php bin/console doctrine:schema:validate
```

Ne pas recharger les fixtures pour mettre à jour une installation existante.
La commande `app:demo:users` peut être relancée : elle conserve les comptes
déjà présents, sans modifier leur rôle ni leur mot de passe.

## Comptes de démonstration

Le démarrage crée ces comptes **après** les fixtures avec `app:demo:users`.
Ce sont des comptes fictifs publics, réservés à la démonstration locale.

| Profil | Adresse | Mot de passe de démonstration | Accès |
| --- | --- | --- | --- |
| Client | `client@example.com` | `HorizonDemo2026!` | [Mes commandes](http://localhost:8080/compte) |
| Guichetier | `guichet@example.com` | `HorizonDemo2026!` | [Guichet](http://localhost:8080/guichet) |
| Administrateur | `admin@example.com` | `HorizonDemo2026!` | [Administration](http://localhost:8080/admin) |

La [connexion](http://localhost:8080/connexion) utilise la même page pour les
trois rôles. L'inscription publique crée uniquement un client.
Utiliser des sessions de navigateur distinctes pour comparer client et guichet.

## Données de démonstration

Le jeu contient une salle de 400 places, trois événements, quatre
représentations, seize tarifs et six billets déjà vendus. Les tarifs fictifs
sont plein 100 MAD, réduit 80 MAD, groupe 60 MAD et invitation 0 MAD.
Le client achète au plein tarif ; le guichetier dispose aussi du réduit et du
groupe ; l'invitation est réservée à l'administrateur.

Le **Concert Horizon** se joue le **1er décembre 2026 (R1)** et le **2 décembre
2026 (R2)** à 19 h UTC, affichées dans le fuseau `Africa/Casablanca`. Ces dates
sont fixes : le parcours d'achat doit être testé avant le début des séances.
Parterre / A / 1 est déjà vendue sur R1 et libre sur R2 ; A4 est libre sur les
deux après un chargement neuf. R1 et R2 désignent les dates du jeu, pas des
identifiants SQL à saisir dans une URL.

Suivre le [scénario du README](README.md#démonstration-en-deux-minutes) pour acheter et comparer
les disponibilités. Le [guide du niveau 3](docs/NIVEAU3.md) explique les droits,
le contrôle des QR, les indicateurs et les limites. Pour contrôler deux
rechargements identiques sans toucher au jeu utilisé dans le navigateur,
suivre la [suite Demo Fixtures](tests/README.md#suite-demo-fixtures).

## Rechargement manuel des données

Pour repartir de zéro, préférer un **nouveau projet Compose avec un volume
neuf**. Sur une base déjà utilisée, un redémarrage conserve volontairement les
achats ; `app:demo:prepare` ne les efface pas.

Le chargement ci-dessous supprime les données métier et reste réservé à une
base de démonstration dédiée et jetable, sans configuration Google réelle.
Il conserve les comptes, dont les mots de passe existants. Arrêter les acteurs
puis vérifier à nouveau le nom effectif avant la commande de purge :

```sh
docker compose --profile sheets stop app sheets-worker
docker compose run --rm --no-deps app php bin/console dbal:run-sql 'SELECT DATABASE() AS target_database'
# Continuer uniquement si cette base est bien celle de la démo jetable.
docker compose run --rm --no-deps app php bin/console doctrine:fixtures:load --purge-exclusions=app_user --no-interaction
docker compose run --rm --no-deps app php bin/console app:demo:users
docker compose up -d --wait
```

Conserver le nom de projet et le port choisis pour toutes ces commandes.
Ne pas utiliser cette procédure sur une base contenant des données à garder.

Pour essayer la démonstration sans toucher à une base existante, utiliser un
autre clone et un nom de projet Compose neuf, comme décrit ci-dessus.

## Google Sheets (facultatif pour le lancement)

La configuration, les colonnes, le worker automatique et les commandes de
reprise sont décrits dans [GOOGLE_SHEETS.md](docs/GOOGLE_SHEETS.md). Une clé
réelle reste dans `var/credentials/` et les réglages dans `.env.local`. Relancer
`./bin/setup-demo` après configuration pour démarrer ou arrêter le worker.
Les ventes continuent en cas de panne Google. Sans identifiants, l’application
et les tests restent utilisables. La recette sur une feuille Google réelle
est consignée dans [VALIDATION_FINALE.md](docs/VALIDATION_FINALE.md).

## Visuels des événements

Dans **Administration → Créer un événement** ou **Modifier**, le
champ « Visuel de l’événement » accepte un fichier JPG, PNG ou WebP de 2 Mo
maximum. Un aperçu s’affiche avant l’enregistrement. Sans nouveau fichier,
le visuel actuel est conservé ; sans visuel personnalisé, l’affiche de secours
reste disponible. L’image enregistrée apparaît dans les catalogues et les fiches.

Les fichiers sont conservés dans `var/uploads/events/<environnement>/`, hors du
répertoire public et de Git. Sauvegarder ce dossier avec la base pour conserver
les visuels ; un redémarrage Compose ne l’efface pas. La migration ajoutant
`evenement.image_filename` est appliquée par `./bin/setup-demo`.

## Événements, spectacles et catégories de places

Le [formulaire guidé](docs/EVENEMENTS.md) rassemble l'événement, ses spectacles
nommés, la salle, les catégories Standard/VIP/VVIP et leurs prix. L'administration
présente ensuite les événements et leurs spectacles sous forme de cartes.
La migration correspondante est additive : les anciennes représentations
gardent leur inventaire et leurs tarifs. Les fixtures initiales restent
inchangées ; créer un nouveau spectacle pour essayer les catégories.

## Tests et entretien

La [documentation des tests](tests/README.md) décrit la création des bases
jetables et les suites à lancer. La suite principale inclut les tests de
concurrence MySQL ; Demo Fixtures est séparée.

Pour nettoyer les réservations expirées :

```sh
docker compose exec -T app php bin/console app:reservations:expire
```

Cette commande est rejouable ; elle peut être planifiée régulièrement. Une
nouvelle réservation peut aussi récupérer une place dont la réservation a expiré.
Voir les [règles de réservation](docs/SPRINT2.md) pour les transitions exactes.

## Arrêt et dépannage

```sh
docker compose down
```

Les données MySQL restent dans le volume du projet. `docker compose down --volumes`
supprime ces données et le cache Composer : réserver cette option à un projet jetable.

| Symptôme | Vérification ou solution |
| --- | --- |
| Accès Docker refusé | Vérifier `docker info` et les droits d'accès au moteur ; aucune commande applicative ne corrige les droits de la machine. |
| Port 8080 déjà utilisé | Définir `HORIZON_HTTP_PORT=8081`, puis utiliser `http://localhost:8081`. |
| Écriture refusée dans le clone sous Linux | Le point d'entrée prend automatiquement l'UID/GID du propriétaire du clone. Vérifier les droits du dossier et les éventuelles restrictions du système de fichiers ; seuls `var/`, `vendor/` et le cache Composer sont réattribués. |
| Erreur MySQL | Consulter `docker compose logs database`, comparer `DATABASE_URL` aux variables MySQL et vérifier les surcharges de `.env.local`. Ne pas supprimer un volume contenant des données utiles. |
| App arrêtée ou non saine | Lire `docker compose logs app`. Le message final nomme l'étape échouée ; Composer est réessayé trois fois. Après correction, relancer `docker compose up --build -d --wait`. |
| Catalogue vide | Les migrations ne créent pas de programmation. Vérifier la base ciblée et préparer les fixtures uniquement dans une installation jetable. |
| Connexion de démonstration impossible | Créer les comptes après les fixtures. Un compte déjà présent garde son mot de passe ; la commande ne le réinitialise pas. |
| Vente désactivée ou séance indisponible | Garder `APP_ENV=dev` et choisir une représentation programmée, non commencée. Les dates des fixtures ne se décalent pas automatiquement. |

## Vérification de ce guide

Le démarrage Compose seul, les trois connexions, l'achat simulé et sa conservation
après recréation des conteneurs sont contrôlés sur une installation isolée.
Les résultats réellement observés sont consignés dans le
[bilan de démarrage Docker](docs/DOCKER_STARTUP.md). Les commandes de tests sont
centralisées dans [tests/README.md](tests/README.md).
