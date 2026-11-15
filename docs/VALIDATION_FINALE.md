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

## Synchronisation Google Sheets

- Migration additive de la file : mapping et schéma MySQL validés.
- Tests ciblés : 16 tests, 92 assertions, client API simulé et MySQL réel.
- Composer strict, plateforme PHP, conteneur et 39 templates Twig valides.
- Les tests couvrent l’écriture stable, la prévention des doublons, la reprise
  après timeout, le remboursement, les révisions concurrentes, le verrou entre
  workers, le changement de destination et le rollback commun vente/file.
- Les sept billets de la démonstration isolée ont été examinés par le backfill,
  avec sept demandes conservées sans appel externe lorsque Sheets est désactivé.
- Recette Google réelle : **en attente d’une feuille autorisée et d’une clé de
  compte de service**. Aucune réussite réelle de synchronisation n’est annoncée
  sur la seule base des tests simulés.

## Instance locale conservée

Le dépôt de travail est maintenant sur `main`. L’instance
`http://localhost:8080` a été mise à jour avec `./bin/setup-demo` après lecture
de `SELECT DATABASE()` : base MySQL `billetterie`. La migration de la file est
additive ; aucun chargement de fixtures ni aucune purge n’a été effectué.

Une comparaison de toutes les lignes métier et des comptes avant/après confirme
leur conservation : **1 salle, 2 zones, 400 places, 3 événements,
4 représentations, 1 600 inventaires, 16 tarifs, 5 commandes, 7 billets et
3 comptes**. Les empreintes de comparaison restent locales.

Les trois connexions, le PDF existant d’A5, son indisponibilité au guichet sur
R1 et sa disponibilité sur R2 ont été revérifiés par HTTP après la mise à jour.
Le fichier généré local `config/reference.php` reste hors des commits et son
contenu préexistant est conservé à l’identique.

Le bilan cumulatif reste limité au niveau 3 tant que la recette externe n’a
pas été observée. Docker et son guide sont prêts indépendamment de ce statut.
