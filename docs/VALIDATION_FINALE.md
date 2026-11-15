# Validation finale Horizon

État du 15 novembre 2026. Ce document distingue les résultats locaux, la
validation sur un runner neuf et la recette réelle de l’API Google.

## Socle et installation

- `main` reçoit le Sprint 3 par une fusion conservant son historique, puis les
  documents du modèle et des décisions sont actualisés (`b01f182`).
- Installation locale avec `./bin/setup-demo` sur un projet Compose distinct,
  port 8081 et volume neuf : OK. Trois connexions, achat A5 et PDF : OK.
- Relance du setup après l’achat : aucune purge ; même vente et PDF conservés.
- Suite avant intégration Sheets : 80 tests, 470 assertions ; Demo Fixtures :
  1 test, 16 175 assertions sur une base séparée.
- Le [workflow de livraison](https://github.com/abdelhak-kadir/Billetterie-Platform/actions/runs/43733119501)
  a réussi sur runner Ubuntu neuf. Son premier essai avait échoué sur un HTTP
  502 temporaire du téléchargement Composer ; sa relance a réussi. Le setup
  effectue désormais jusqu’à trois essais de téléchargement, puis échoue
  explicitement si l’installation ne peut pas se terminer.

## Suite intégrée et parcours navigateur

Le [workflow intégré avec Chromium](https://github.com/abdelhak-kadir/Billetterie-Platform/actions/runs/43739570517)
a réussi sur Ubuntu neuf, avec PHP 8.3.36 et MySQL 8 :

- Installation, trois connexions HTTP et achat A5 jusqu’au PDF ; relance du
  setup sans purge, avec la même vente et le même PDF conservés.
- **97 tests, 564 assertions** sur `billetterie_test`, dont les conflits
  MySQL entre deux processus et la protection de la commande Sheets en test.
- **Demo Fixtures : 1 test, 16 175 assertions** sur
  `billetterie_s105_verify_test`. Jeu initial : 1 salle, 400 places,
  3 événements, 4 représentations, 1 600 inventaires, 16 tarifs,
  4 commandes payées et 6 billets. A1 vendue sur R1, libre sur R2.
- Chromium, **1 440 × 1 000** et **390 × 844** : trois connexions par format,
  achats client A6/A7 et ventes guichet A8/A9, tarif réduit au guichet,
  paiement simulé, quatre PDF téléchargés, navigation au clavier et sélection
  mobile compacte. Chaque canal voit les ventes de l’autre sur R1 ; les mêmes
  places restent disponibles sur R2. Administration et indicateurs accessibles.
- Redémarrage des services en conservant les volumes : catalogue HTTP 200.

Les captures sont publiées dans l’artefact `horizon-browser-qa` du run pendant
14 jours. Aucun PDF privé ni cookie n’y est joint. La revue visuelle a relevé
un pied de page admin trop serré sur mobile : il a été corrigé dans `dad2185`
et le script contrôle aussi les limites de ce texte. Le
[nouveau workflow complet](https://github.com/abdelhak-kadir/Billetterie-Platform/actions/runs/43740241433)
est également réussi : mêmes quantités de tests et parcours, 18 captures.
Les rendus du plan client, du billet, du guichet et de l’administration ont été
inspectés ; la correction du pied de page est visible sur la capture mobile.

Captures conservées dans le dépôt : [plan client mobile](screenshots/final/mobile-client-plan.png),
[tarifs au guichet](screenshots/final/mobile-guichet-tarifs.png),
[administration mobile](screenshots/final/mobile-admin.png) et
[indicateurs desktop](screenshots/final/desktop-indicateurs.png).
Ces captures précèdent l’activation de Google sur l’instance locale ; la carte
Sheets y apparaît encore désactivée. La recette ci-dessous vérifie son état activé.

## Synchronisation Google Sheets

- Migration additive de la file : mapping et schéma MySQL validés.
- Tests ciblés : 16 tests, 92 assertions, client API simulé et MySQL réel.
- Composer strict, plateforme PHP, conteneur et 39 templates Twig valides.
- Les tests couvrent l’écriture stable, la prévention des doublons, la reprise
  après timeout, le remboursement, les révisions concurrentes, le verrou entre
  workers, le changement de destination et le rollback commun vente/file.
- Les sept billets de la démonstration isolée ont été examinés par le backfill,
  avec sept demandes conservées sans appel externe lorsque Sheets est désactivé.

### Recette Google réelle — 15 novembre 2026

Réalisée sur la feuille privée autorisée « Horizon Demo », via l’API officielle,
avec la clé de compte de service locale ignorée par Git. Aucun identifiant de
feuille, e-mail réel, jeton ou contenu de clé n’est publié dans le dépôt.

- Lecture des métadonnées Google : **HTTP 200**. L’onglet « Feuille 1 » existant
  est conservé ; les exports utilisent un nouvel onglet « Ventes Horizon ».
- `SELECT DATABASE()` confirme **`billetterie`** dans l’app web et le worker.
  Aucune base PHPUnit n’est utilisée, aucune fixture n’est rechargée.
- Sept billets historiques exportés ; les seize colonnes lues depuis Google
  correspondent aux projections MySQL, avec lecture des nombres en mode
  `UNFORMATTED_VALUE` pour éviter une comparaison avec du texte formaté.
- Achat client **A6/R1, 100 MAD**, puis vente guichet **A7/R1, 80 MAD** au tarif
  réduit : paiement simulé et PDF contrôlés par HTTP. Les deux nouvelles lignes
  apparaissent automatiquement, sans commande manuelle d’envoi après les ventes.
- Annulation/remboursement client : la ligne d’A6 est mise à jour automatiquement,
  sur la même référence et la même position. Révision 2, remboursement de
  10 000 centimes, net nul. A7 porte bien le canal `guichet`.
- Rejeu des neuf billets historiques : **zéro nouvel envoi** ; redémarrage du
  worker et relecture Google : **neuf références uniques**, dix lignes avec
  les en-têtes, aucun doublon. L’empreinte des valeurs de l’onglet initial est identique.
- Client et guichet voient A6 de nouveau libre sur R1, A7 vendue sur R1 ;
  A6 et A7 sont libres sur R2. L’admin indique la synchronisation activée,
  **9 synchronisés, 0 en attente, 0 en erreur**.

Après ces achats de recette : **7 commandes et 9 billets**, dont 8 valides et
1 annulé. Salle, places, événements, représentations, inventaires, tarifs et
trois comptes sont conservés. Les scénarios d’indisponibilité Google et de
timeout sont vérifiés avec l’adaptateur simulé, pas par une coupure réelle de l’API.

## Instance locale conservée

Le dépôt de travail est maintenant sur `main`. L’instance
`http://localhost:8080` a été mise à jour avec `./bin/setup-demo` après lecture
de `SELECT DATABASE()` : base MySQL `billetterie`. La migration de la file est
additive ; aucun chargement de fixtures ni aucune purge n’a été effectué.

Avant la recette Google, une comparaison des lignes métier et des comptes avant/après confirmait
leur conservation : **1 salle, 2 zones, 400 places, 3 événements,
4 représentations, 1 600 inventaires, 16 tarifs, 5 commandes, 7 billets et
3 comptes**. Les empreintes de comparaison restent locales.

Les trois connexions, le PDF existant d’A5, son indisponibilité au guichet sur
R1 et sa disponibilité sur R2 ont été revérifiés par HTTP après la mise à jour.
Le fichier généré local `config/reference.php` reste hors des commits et son
contenu préexistant est conservé à l’identique.

Le bilan cumulatif couvre maintenant les **niveaux 1 à 5** : socle et règles,
produit, Google réel et livraison Docker sur runner neuf. Les paiements et
remboursements restent simulés.

## Recherche, visuels et pages de compte — 15 novembre 2026

Correctifs préparés dans un projet Compose distinct, port 8082, sans toucher
à la démonstration existante ni à sa configuration Google :

- Les sélecteurs numériques vides des recherches admin signifient désormais
  « tous ». Les listes et les indicateurs acceptent les filtres vides ;
  les identifiants invalides ne provoquent pas d’exception de conversion.
- Page 404 Horizon avec retour au catalogue ; les erreurs 400, 403, 405, 410
  et 500 affichent aussi un message utilisable sans trace technique. Le code
  HTTP d’origine est conservé, y compris en mode debug.
- Visuel facultatif à la création et à la modification d’un événement :
  aperçu, publication dans les fiches et catalogues, conservation sans nouveau
  fichier et remplacement. JPG, PNG et WebP, 2 Mo maximum ; stockage hors du
  répertoire public, nom aléatoire, contrôle du contenu et refus des fichiers
  invalides ou trop lourds. Les droits et le CSRF restent appliqués.
- Connexion et inscription adaptées à l’ordinateur et au téléphone, champs
  accessibles, affichage/masquage du mot de passe et messages en français.
- **104 tests, 637 assertions** sous PHP 8.3.36/MySQL 8 ; Demo Fixtures :
  **1 test, 16 175 assertions**. Composer strict, conteneur et 41 templates Twig : OK.
- Le [workflow final de cette évolution](https://github.com/abdelhak-kadir/Billetterie-Platform/actions/runs/43761621494)
  a réussi : inscription effective, connexion refusée, mot de passe affichable,
  404, recherches sans sélection et création/publication d’un visuel, en plus
  des achats et PDF précédents, en 1 440 × 1 000 et 390 × 844.

La migration de cette évolution ajoute seulement `evenement.image_filename`,
nullable. Aucun changement du stock, des comptes, des ventes ou de Google Sheets
n’est nécessaire.

Mise à jour de `localhost:8080` : la colonne a été ajoutée avant le changement
des écrans, puis `./bin/setup-demo` a installé les dépendances et relancé le
worker. Aucune purge. La comparaison des valeurs préexistantes, hors nouvelle
colonne nullable, confirme la conservation des données ajoutées par l’utilisateur :
**1 salle, 2 zones, 400 places, 4 événements, 5 représentations,
2 000 inventaires, 17 tarifs, 12 commandes, 13 billets et 3 comptes**.

Les trois connexions, les pages de compte, la 404, les filtres vides et le
champ image ont ensuite été vérifiés par HTTP sur cette instance, sans ajouter
de données. Google affiche **13 synchronisés, 0 en attente, 0 en erreur**.
Le fichier local généré `config/reference.php` est conservé à l’identique.

Captures finales inspectées : [connexion ordinateur](screenshots/ui/desktop-connexion.png),
[connexion mobile](screenshots/ui/mobile-connexion.png),
[inscription mobile](screenshots/ui/mobile-inscription.png),
[404 mobile](screenshots/ui/mobile-404.png) et
[formulaire avec visuel](screenshots/ui/mobile-evenement-visuel.png).
