# Horizon

## Présentation

Horizon est une billetterie pour une salle de spectacle de 400 places, réalisée
avec Symfony 7.4, PHP 8.3 et MySQL 8. Les clients choisissent leurs places et
obtiennent leurs billets ; les guichetiers vendent sur le même inventaire.
Les administrateurs gèrent la programmation, les tarifs et le suivi des ventes.

## Démarrage rapide

Prérequis : Git, Docker Engine et Docker Compose v2 ou ultérieur. Depuis la
racine d'un clone neuf, avec un volume MySQL neuf réservé à la démonstration :

```sh
cp .env.example .env
docker compose up --build -d --wait
docker compose exec -T app composer install --no-interaction
docker compose exec -T app php bin/console dbal:run-sql 'SELECT DATABASE() AS target_database'
```

Vérifier que la base affichée est `billetterie` et qu'elle est jetable.
**Le chargement des fixtures ci-dessous efface les données de cette base.**

```sh
docker compose exec -T app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec -T app php bin/console doctrine:fixtures:load --no-interaction
docker compose exec -T app php bin/console app:demo:users
```

Ouvrir **[http://localhost:8080](http://localhost:8080)**.
Voir [INSTALL.md](INSTALL.md) pour la configuration complète, les migrations,
les comptes de démonstration, l'isolation des données et le dépannage.

## Démonstration

1. Se connecter avec le compte client indiqué dans [INSTALL.md](INSTALL.md#comptes-de-démonstration).
   Ouvrir **Concert Horizon**, puis la représentation du **1er décembre 2026 (R1)**.
2. Sélectionner une place libre, par exemple **Parterre / A / 4** après un
   chargement neuf. Continuer, choisir le plein tarif et cliquer sur
   **Réserver ces places**, puis **Simuler un paiement réussi**.
3. Depuis la commande confirmée, ouvrir le billet et télécharger son **PDF avec QR code**.
4. Dans une autre session de navigateur, se connecter comme guichetier. Ouvrir
   la même représentation et actualiser le plan : la place achetée est vendue
   et ne peut plus être sélectionnée. Elle reste libre pour la représentation
   du **2 décembre (R2)** si aucun autre achat ne l'a prise sur cette date.

Accès : [espace client](http://localhost:8080/compte),
[guichet](http://localhost:8080/guichet),
[administration](http://localhost:8080/admin) et
[indicateurs](http://localhost:8080/admin/indicateurs).
Le catalogue est public ; chaque espace demande le rôle correspondant.
A1 est déjà vendue sur R1 dans le jeu initial : choisir une autre place pour l'achat.

## Fonctionnalités et niveaux atteints

| Niveau | Fonctionnalités et état |
| --- | --- |
| 1 — Modéliser | Vérifié : salle et places, événements, représentations, tarifs, migrations, données de démonstration et administration. |
| 2 — Règles métier | Vérifié : inventaire par représentation, réservation temporaire, protection contre les doubles ventes concurrentes, annulation et remboursement simulé. |
| 3 — Produit | Vérifié : trois rôles, achat client et vente au guichet sur plan, billets PDF avec QR signé, remplissage et recettes. |
| 4 — Google Sheets | Non implémenté : aucune synchronisation automatique des ventes ou remboursements, aucun appel réel vérifié. |
| 5 — Livraison | Docker et guide d'installation disponibles ; niveau non atteint, car le niveau 4 manque et l'initialisation reste manuelle. |

Les niveaux sont **cumulatifs** : le niveau atteint est **3**, avec les niveaux
1 et 2. **Paiements et remboursements sont simulés**, sans mouvement bancaire.
Le QR permet un contrôle authentifié par le personnel ; il ne gère pas une entrée
à usage unique. Les [preuves et limites du niveau 3](docs/NIVEAU3.md) détaillent
les vérifications réalisées.

## Tests et documentation

Après la [préparation de la base de tests](tests/README.md#suite-principale) :

```sh
docker compose exec -T app php bin/phpunit
```

- [Tests et suite séparée Demo Fixtures](tests/README.md) : bases jetables, commandes et contrôles de concurrence.
- [Modèle de données](docs/MODELE.md) et [décisions métier](docs/DECISIONS.md).
- [Suivi des sprints](docs/SPRINTS.md) et [validation des parcours du niveau 3](docs/NIVEAU3.md).
- [Index de la documentation](docs/README.md) : règles, plan de salle et interface.
