# Démonstration : choisir les spectacles d'un concert

Ouvrir **Horizon Live** dans le catalogue. Le concert contient deux spectacles
réservables le **même soir**, dans la même salle ; le client choisit son spectacle
avant de sélectionner ses places.

| Spectacle | Horaire à Casablanca | Standard | VIP | VVIP |
| --- | --- | ---: | ---: | ---: |
| Première partie | 1er décembre 2026, 20 h | 100 MAD | 200 MAD | 300 MAD |
| Concert principal | 1er décembre 2026, 21 h 30 | 120 MAD | 240 MAD | 360 MAD |

Le réduit vaut 80 % du plein tarif et le groupe 60 % ; l'invitation vaut 0 MAD.
Le client utilise le plein tarif ; le guichetier peut aussi choisir réduit ou
groupe ; l'administrateur dispose de l'invitation. Les paiements sont simulés.
Les dates sont fixes : tester l'achat avant le début du spectacle.

## Jeu neuf

Le démarrage Compose installe la salle de 400 places, trois événements, quatre
spectacles **nommés**, 1 600 inventaires, 12 catégories de places et **48 tarifs**.
Six billets fictifs présentent déjà des ventes sur les quatre spectacles.

Parterre A/B est VVIP, C/D/E est VIP ; les autres rangées et le Balcon sont
Standard. Les inventaires restent indépendants : A1 est vendue pour Première
partie et libre pour Concert principal. Acheter A5 pour Première partie donne
un billet VVIP de 300 MAD ; A5 reste libre au concert principal, à 360 MAD.

## Base contenant déjà l'ancien jeu

Depuis le clone, conserver le même nom de projet Compose et le même port :

```sh
git pull --ff-only
docker compose up --build -d --wait --force-recreate
```

La migration ajoute seulement une clé de provenance facultative aux événements.
Le démarrage reconnaît les commandes fictives de l'ancien jeu et ajoute **une
seule fois** Horizon Live : deux spectacles, 800 inventaires, six catégories de
places et 24 tarifs supplémentaires. Il réutilise les places physiques de la
salle existante.

Les anciens événements, noms, dates, prix, commandes, billets et comptes sont
conservés. Ce complément ne crée pas de nouvelle vente fictive : les six billets
initiaux restent rattachés à leur programmation historique. Le nouveau scénario
est accessible depuis **Horizon Live**, et les anciennes ventes restent consultables.

Aucun `doctrine:fixtures:load`, `down --volumes` ou nettoyage de base n'est
nécessaire. Une base inconnue ou PHPUnit reste refusée ; les comptes existants
ne changent pas de mot de passe. La création s'appuie sur le même service que
le formulaire administrateur, dans une transaction. Sa clé de provenance unique
évite les doublons même si l'administrateur renomme ensuite le programme.

## Vérification

La suite de fixtures recharge deux fois le jeu dans sa base PHPUnit dédiée et
compare les associations, noms, horaires, catégories, prix et ventes. Les tests
contrôlent aussi un achat VVIP, le stock indépendant du second spectacle et la
conservation d'un programme modifié par l'administrateur.

Une recette distincte part d'un ancien jeu avec achat et PDF existants. Les
lignes historiques sont comparées avant/après le complément ; l'ancien billet
reste téléchargeable et un achat dans Horizon Live fonctionne. Une seconde
préparation doit laisser toutes les lignes identiques, sans doublon.

Voir [INSTALL.md](../INSTALL.md) pour les comptes et [tests/README.md](../tests/README.md)
pour les bases jetables. Les captures du [README](../README.md#captures-décran)
montrent la nouvelle programmation et les catégories réellement chargées.

Résultats observés le 16 novembre 2026 dans des projets Compose distincts :
**130 tests, 832 assertions** ; suite Demo Fixtures : **1 test, 16 511 assertions**.
Les parcours Chrome ordinateur et mobile ont réussi avec les deux spectacles
nommés, achat et PDF. Les sept captures du README ont été actualisées à partir
de ces données. La comparaison de l'ancien jeu avant/après confirme la
conservation de ses cinq commandes, sept billets et trois comptes ; l'achat
dans le nouveau programme et le second passage sans doublon réussissent aussi.

La suite de fixtures évite les lectures répétées par siège et désactive les
backtraces SQL de test pour rester dans la limite mémoire PHP standard. Le
raccourci de tests utilise l'UID/GID de l'application afin de ne pas créer de
dossiers d'uploads appartenant à root et de laisser les images de démo utilisables.

Mise à jour de l'instance locale `localhost:8080` : `SELECT DATABASE()` confirme
`billetterie`. Après migration additive puis démarrage normal, les quatre
commandes, six billets et trois comptes préexistants sont identiques. Le
complément porte le total à quatre événements, six spectacles, 2 400 inventaires,
40 tarifs et six catégories de places. Les trois connexions, les deux spectacles
Horizon Live et leurs prix VVIP (300/360 MAD) sont vérifiés par HTTP sans achat
supplémentaire. Le fichier généré local `config/reference.php` reste inchangé.
