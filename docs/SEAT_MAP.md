# Horizon — Plan de salle admin

12 novembre 2026. Première étape du Sprint 3, sur `sprint-3-produit`, créée
depuis le Sprint 2 vérifié à `8e62b5e`. Cette livraison concerne le back-office
local ; les parcours client et guichetier restent à construire.

## Parcours livré

Les séances d'une fiche événement et les lignes de programmation ouvrent
directement `/admin/representations/{id}/plan`. Le panneau sombre présente
l'affiche Horizon, l'événement, la salle, la date et l'heure en
`Africa/Casablanca`. Il conserve les liens vers le détail et l'inventaire en
tableau. Sur mobile, il devient un résumé avec des accès au plan et à la sélection.

Le plan clair affiche tous les inventaires de la représentation, les zones,
les rangées et les numéros. Les 400 places de démonstration comprennent 300
places au Parterre (A–O, 1–20) et 100 au Balcon (A–E, 1–20). Le tri par zone,
rangée et numéro reste identique entre représentations. Les emplacements
absents d'une rangée gardent une colonne vide. Le modèle ne décrit pas les
allées, la position exacte de la scène ou les distances : le rendu porte
explicitement la mention « plan schématique » et une orientation indicative.

Les cinq captures Pathé fournies ont guidé la composition : résumé sombre,
surface de travail claire, fauteuils ambre, sélection verte et panneau de dates.
Les visuels Horizon existants sont conservés ; les fauteuils sont des SVG
originaux. Aucun asset Pathé n'a été ajouté.

- **Inspecter** : chaque siège annonce sa zone, sa rangée, son numéro et son état.
- **Sélectionner** : seules les places libres peuvent être ajoutées ou retirées.
  Le récapitulatif « Sélectionnées » propose le retrait individuel et le vidage.
  Il reste visible pendant la sélection et n'annonce aucune réservation acquise.
- **Dates et représentations** : seules les représentations du même événement
  issues de la base sont proposées, avec leurs date, heure, salle et statut.
  Fermer ou presser Échap conserve la séance active et la sélection. Changer
  de séance avec une sélection demande confirmation, puis charge un nouveau plan.
- **Continuer vers la réservation** : ouvre le formulaire du Sprint 2 avec les
  inventaires présélectionnés. L'email fictif, le canal et le tarif sont encore
  choisis dans ce formulaire avant sa soumission protégée par CSRF.

La sélection ne vit que dans le composant de la page ; elle n'est pas conservée
dans un cookie, une session de réservation ou un stockage navigateur. Recharger
ou quitter la page la perd. Un retour de conflit serveur peut la réamorcer avec
les seuls inventaires encore libres de la représentation.

## Disponibilité et sécurité métier

`SeatMapView` lit l'inventaire réel sans créer de commande ni modifier d'état.
Le compteur « libres » compte uniquement les lignes `disponible`. Le plan
n'anticipe pas les expirations : une réservation expirée mais non encore
libérée reste affichée réservée jusqu'à un nouveau chargement après traitement
par le service ou la commande de nettoyage existante du Sprint 2.

Le clic ne garantit donc pas la place. `TicketingService::reserve()` reste
l'autorité à la soumission : représentation, échéance, tarif et chaque
inventaire sont relus et contrôlés sous verrou dans la transaction existante.
Les choix du formulaire sont limités à la représentation active. Les paramètres
GET sont validés, dédupliqués et limités à 400 identifiants positifs ; seuls les
inventaires disponibles appartenant au plan peuvent être préselectionnés.

Si un siège devient indisponible avant l'ouverture du formulaire, le contrôleur
renvoie au plan. S'il change entre l'ouverture et la soumission du formulaire,
un conflit du service renvoie au plan avec un message et des états relus ; les
places encore libres restent sélectionnées, les autres sont retirées. Aucune
réservation partielle n'est créée. Les erreurs CSRF et de formulaire conservent
leur traitement Symfony. Une séance annulée, terminée ou déjà commencée reste
consultable en inspection seulement.

Les protections locales de `/admin`, notamment le refus en production tant que
l'authentification n'existe pas, sont inchangées. Aucun rôle, paiement externe,
QR code, migration ou changement de fixtures n'est ajouté.

## Contrat du composant partagé

Le composant Twig `templates/components/seat_map/_map.html.twig` reçoit :

| Entrée | Contrat |
| --- | --- |
| `instanceId` | Identifiant unique dans la page, pour les titres, groupes radio et liens internes. |
| `seatMap` | Projection `SeatMapView::build(representation, zoneId?)` : zones ordonnées, rangées, numéros, sièges et compteurs. Les identifiants sont ceux des **inventaires**, propres à chaque représentation. |
| `selectable` | Autorise l'interface de sélection ; `false` impose l'inspection. Ce paramètre ne remplace jamais une autorisation serveur. |
| `initialMode` | `inspect` ou `select`. |
| `selected` | Liste d'identifiants préalablement validés côté serveur, libres et appartenant à cette représentation. Vide pour une simple inspection. |

Inclure la feuille `public/admin/seat-map.css`, importer `mountSeatMaps()` depuis
`public/admin/seat-map.js`, puis appeler cette fonction une fois le DOM présent.
Le composant Twig contient la légende, les sièges et le récapitulatif ; la classe
JavaScript `SeatMap` gère uniquement leur état local et le clavier. Les événements
remontent avec `bubbles: true` :

| Événement | Données `event.detail` | Usage |
| --- | --- | --- |
| `seatmap:selectionchange` | `{ids: string[], mode: 'inspect' \| 'select'}` | Mettre à jour une présentation propre à l'écran. |
| `seatmap:continue` | `{ids: string[]}` | Demander la suite du parcours à l'écran hôte. |

Le composant ne connaît aucune route, autorisation, requête réseau, tarification
ou action de vente. `seat-map-admin.js` constitue l'adaptateur actuel : il ouvre
le panneau de dates et traduit `continue` en navigation GET vers le formulaire
admin. La vue tableau réutilise le même composant en inspection, avec son filtre
de zone, et conserve recherche, statuts et pagination du tableau.

Pour le futur **client**, fournir la même projection et un adaptateur vers le
parcours public, avec ses propres autorisations, tarif et validation serveur.
Pour le futur **guichetier**, fournir la projection et un adaptateur vers son
écran de vente autorisé. Le canal devra être fixé ou contrôlé côté serveur selon
ses droits. Ne pas réutiliser les routes locales admin comme accès public.
Ces deux adaptateurs et leurs autorisations ne sont pas implémentés ici.

## Accessibilité et contrôles visuels

La légende reste près du plan : libre ambre, sélectionné vert avec coche,
réservé avec horloge, vendu avec croix, bloqué avec tiret. Chaque bouton expose
le nom complet et l'état, `aria-pressed` et, en sélection, `aria-disabled` pour
les sièges indisponibles. Ceux-ci restent inspectables au clavier.

Un seul siège figure dans l'ordre de tabulation. Les flèches gauche/droite
parcourent la rangée, haut/bas la rangée voisine en conservant le numéro si
possible ; Début/Fin vont aux extrémités. Entrée/Espace activent le bouton.
Le focus bleu de 3 px est visible sur la surface claire ; les boutons de retrait
possèdent un nom complet. Le dialogue natif retient le focus, se ferme par Échap
et rend le focus à son bouton d'ouverture. Le plan défile dans une région nommée ;
sa marge de défilement tient compte de la hauteur du récapitulatif collant.
À 390 px, les cibles des sièges mesurent 44 × 46 px.

Chrome headless, autorisé par l'utilisateur, a contrôlé les pages en 1440 × 1000
et 390 × 844 sur la base déjà chargée `billetterie_s105_verify_test`, en lecture
seule : A1 vendue sur R1 (concert du 1 décembre 2026), libre sur R2 (2 décembre),
refus local de sélection sur R1, deux sièges sélectionnés sur R2, retrait,
vidage, flèches/Début/Fin/Entrée, focus, annulation et confirmation du changement
de séance, transfert des deux sièges vers le formulaire. Des événements tactiles
réels Chrome ont ajouté puis retiré A1 sur mobile. La dernière rangée du Balcon
reste atteignable sans être masquée par le récapitulatif. La largeur globale
mesurée vaut exactement 1440 puis 390 px ; les images se chargent correctement.
Le formulaire n'a pas été soumis sur cette base de démonstration.

Captures inspectées :

- [Plan R1 — ordinateur](screenshots/sprint3/plan-r1-desktop.png)
- [Résumé R1 — mobile](screenshots/sprint3/plan-r1-mobile.png)
- [Sélection R2 — ordinateur](screenshots/sprint3/selection-r2-desktop.png)
- [Sélection R2 — mobile](screenshots/sprint3/selection-r2-mobile.png)
- [Confirmation du changement de date — mobile](screenshots/sprint3/dates-warning-mobile.png)
- [Formulaire Sprint 2 prérempli — ordinateur](screenshots/sprint3/reservation-prefilled-desktop.png)

Ces contrôles ne constituent pas une certification avec lecteur d'écran ni une
validation de tous les navigateurs. L'état du plan est un instantané, sans
rafraîchissement temps réel ; toute réservation reste soumise au serveur.

## Vérifications Docker

PHP 8.3.36 / MySQL 8.0, le 12 novembre 2026 :

```sh
docker compose exec -T app composer validate --strict
docker compose exec -T app php bin/console lint:container
docker compose exec -T app php bin/console lint:twig templates
docker compose exec -T app php bin/console doctrine:schema:validate
docker compose exec -T app php bin/console --env=test dbal:run-sql 'SELECT DATABASE() AS test_database'
docker compose exec -T app php bin/phpunit
```

Composer, conteneur, les **25 templates Twig** et le schéma sont valides.
`SELECT DATABASE()` a confirmé `billetterie_test` avant PHPUnit :
**55 tests, 296 assertions**, aucun échec. Les six tests `SeatMapTest` couvrent
les 400 sièges, la stabilité physique et A1 indépendant, le préremplissage sans
écriture GET puis la réservation multi-places, le conflit serveur sans résultat
partiel, le rejet d'identifiants invalides et des écritures sur la route du plan,
le refus d'une sélection étrangère et l'inspection d'une séance annulée.
La suite conserve les tests métier, concurrence, formulaires et CSRF du Sprint 2.
La suite `Demo Fixtures` n'a pas été relancée : les fixtures et leurs dépendances
sont inchangées. Aucune purge ou écriture métier sur la base utilisateur ou sur
la base de démonstration n'a été nécessaire pour cette étape.
