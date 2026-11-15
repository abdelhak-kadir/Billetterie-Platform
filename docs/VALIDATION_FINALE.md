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

## Synchronisation Google Sheets

- Migration additive de la file : mapping et schéma MySQL validés.
- Tests ciblés : 16 tests, 92 assertions, client API simulé et MySQL réel.
- Suite intégrée : 96 tests, 562 assertions ; Demo Fixtures : 1 test,
  16 175 assertions. Composer strict, plateforme PHP, conteneur et 39 templates
  Twig valides. Une relance finale sera consignée sur le commit intégré.
- Les sept billets de la démonstration isolée ont été examinés par le backfill,
  avec sept demandes conservées sans appel externe lorsque Sheets est désactivé.
- Recette Google réelle : **en attente d’une feuille autorisée et d’une clé de
  compte de service**. Aucune réussite réelle de synchronisation n’est annoncée
  sur la seule base des tests simulés.

Le bilan cumulatif reste limité au niveau 3 tant que la recette externe n’a
pas été observée. Docker et son guide sont prêts indépendamment de ce statut.
Les résultats de la suite intégrée et du workflow final seront ajoutés après
leur exécution.
