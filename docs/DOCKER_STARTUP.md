# Recette du démarrage Docker — 16 novembre 2026

Cette évolution reprend les changements utiles de la copie préparée
`horizon-docker-startup` : lancement de l'initialisation par Compose, attente
de MySQL et simplification de `bin/setup-demo`. Elle ajoute les verrous,
un contrôle HTTP et l'exclusion des bases de tests avant les migrations.
Le point d'entrée adapte l'utilisateur du processus au propriétaire du clone,
y compris sur un runner Linux dont l'UID diffère de 1000 ; seuls les répertoires
générés sont préparés avec les privilèges nécessaires.
Le fichier Symfony généré `config/reference.php` reste local, inchangé.

## Démarrage et base cible

Contrôles locaux depuis des clones Git propres, sans `vendor/`, `var/`,
`.env.local` ni identifiants Google. Première recette : projet Compose distinct
`horizon-startup-clean`, port 8084 et volume MySQL neuf :

```sh
COMPOSE_PROJECT_NAME=horizon-startup-clean HORIZON_HTTP_PORT=8084 docker compose up --build -d --wait
```

Aucune commande Composer, migration, fixture ou création de compte n'a été
lancée manuellement avant cette commande. Le log identifie la base Symfony
par `SELECT DATABASE()` **avant les migrations** : `billetterie`, dans le
conteneur app de ce projet isolé. L'interface et les commandes Symfony utilisent
les mêmes fichiers d'environnement. Le catalogue répond HTTP 200 après
initialisation ; le worker Google reste désactivé.

La version finale du démarrage (`358785e`) a ensuite été vérifiée sur un autre
clone et volume neufs, projet `horizon-startup-uid`, port 8087. Ce clone appartient
à l'UID/GID **1001**, comme cas de contrôle des permissions Linux : le serveur
HTTP prend bien cet UID, les trois connexions et l'achat A5 fonctionnent, puis
les empreintes métier restent identiques après recréation des conteneurs.

| Donnée | Quantité réellement vérifiée après le premier lancement |
| --- | ---: |
| Salle / zones | 1 / 2 |
| Places physiques | 400 |
| Événements / représentations | 3 / 4 |
| Inventaires | 1 600 |
| Tarifs | 16 |
| Commandes / billets fictifs | 4 / 6 |
| Comptes de démonstration | 3 |

Un deuxième clone et volume neufs, projet `horizon-startup-foreground`, port
8085, ont aussi été lancés avec **`docker compose up`**, sans `--build` ni
script préalable. Construction, initialisation et catalogue HTTP 200 : OK.
L'instance existante sur le port 8080 et sa configuration Google sont conservées.

## Achat et conservation

- Connexions réelles des trois comptes documentés dans [INSTALL.md](../INSTALL.md#comptes-de-démonstration),
  avec accès à `/compte`, `/guichet` et `/admin` : OK.
- Recherche de **Concert Horizon**, achat de **Parterre / A / 5** sur le
  **1er décembre (R1)**, paiement simulé et téléchargement du PDF : OK.
- A5 vendue et non sélectionnable au guichet et dans l'administration sur R1,
  libre sur le **2 décembre (R2)** : OK.
- Recréation avec `docker compose up --build -d --wait --force-recreate`, sans
  suppression du volume : **5 commandes et 7 billets** conservés. Le même billet
  reste téléchargeable après reconnexion.
- Empreintes de toutes les lignes métier et des comptes identiques avant/après
  la recréation, y compris les mots de passe hachés. Les comptes existants n'ont
  pas été réinitialisés et les fixtures n'ont pas été rechargées.

## Garde-fous et tests

- Deux processus réels `app:demo:bootstrap` observés simultanément dans l'état
  MySQL **User lock**, puis terminés avec succès après libération du verrou.
  Aucune différence dans les lignes métier après les deux passages.
- `HORIZON_AUTO_DEMO=0` testé après recréation : catalogue sain, aucune migration
  ou fixture appelée, empreintes identiques. Retour au mode automatique : OK.
- `app:demo:bootstrap --env=prod` ignore l'initialisation ; une connexion en
  `dev` à `billetterie_test` est refusée avant les migrations : OK.
- `bin/setup-demo` vérifié comme raccourci Compose avec Google désactivé : OK.
- `docker compose config --quiet`, syntaxe POSIX des scripts, lint PHP du nouveau
  code et injection des services : OK.
- Suite principale sur `billetterie_test` : **120 tests, 771 assertions**.
  Demo Fixtures sur `billetterie_s105_verify_test` : **1 test, 16 175 assertions**.
  Ces bases restent séparées de la base du navigateur.

Le premier essai a révélé un état de transaction Doctrine conservé après les
DDL MySQL. La version retenue lance migrations et préparation dans deux
processus PHP distincts, sous le verrou gardé par le parent. Le contrôle a été
repris sur un autre volume neuf ; aucun chargement de secours ni purge d'une
base existante n'a été utilisé.
Le premier run sur un runner neuf a aussi révélé un refus d'écriture avec un
UID différent de 1000. L'adaptation automatique au propriétaire du clone corrige
ce cas ; le contrôle UID 1001 ci-dessus vérifie le correctif sans réglage préalable.

## Captures du README

Chrome local automatisé avec Playwright, ordinateur 1 440 × 1 000 et mobile
390 × 844. Les parcours complets passent : trois rôles, achats client/guichet,
PDF, clavier, inventaires R1/R2, pages de compte, création de spectacles nommés
et prix VIP/VVIP. Les captures de documentation sont recadrées directement
par le navigateur ; le PDF fictif est rendu par Poppler.

| Capture | Contenu |
| --- | --- |
| [catalogue.png](screenshots/reviewer/catalogue.png) | Catalogue public et ses trois événements initiaux. |
| [plan-client.png](screenshots/reviewer/plan-client.png) | A6 sélectionnée et récapitulatif avant réservation. |
| [guichet-plan.png](screenshots/reviewer/guichet-plan.png) | A8 sélectionnée ; A5 et A6 déjà vendues sur R1. |
| [guichet-vente.png](screenshots/reviewer/guichet-vente.png) | Récapitulatif au réduit, 80 MAD, avec destinataire fictif. |
| [admin-indicateurs.png](screenshots/reviewer/admin-indicateurs.png) | 9 places vendues, 680 MAD de recettes après les achats de contrôle. |
| [billet-pdf.png](screenshots/reviewer/billet-pdf.png) | PDF fictif de la place A6 avec QR local, sans identité personnelle. |

Les six vues ont été inspectées. Les adresses affichées sont des adresses
fictives `example.com`, sans secret ni destination Google. Le QR appartient
uniquement à cette installation jetable ; aucune clé réelle n'y est utilisée.
Aucun blocage de capture restant.
