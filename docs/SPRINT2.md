# Règles du Sprint 2

12 novembre 2026. Les durées et remboursements simulés sont des hypothèses de
mise en œuvre documentées par D11/D12, pas des conditions commerciales du sujet.

| Objet | Transitions |
| --- | --- |
| Commande | en attente → payée / échouée / expirée / annulée ; payée → annulée lorsque tous ses billets sont annulés ; états terminaux non réactivables |
| Inventaire | disponible → réservée → vendue ; réservée → disponible si échec, expiration ou abandon du propriétaire ; vendue → disponible après annulation si la représentation reste vendable ; sinon bloquée |
| Billet | émission valide → annulé ; aucun retour à valide ; prix payé immuable |
| Remboursement | sans objet → à rembourser → remboursé pour un billet positif annulé ; invitation à zéro sans dette |

L'échéance est exclusive : confirmation permise uniquement avant, jamais à la
seconde exacte d'expiration. La réservation multi-places est atomique. Le
nettoyage est rejouable et ne libère que les places encore liées à la commande.
Une confirmation répétée d'une vente payée retourne ses billets existants ; un
échec tardif est sans effet. Une revente après annulation crée un nouveau billet.
Le prix accepté à la réservation est indépendant du tarif modifié ensuite.

## Verrouillage et limites assumées

`TicketingService` démarre des transactions courtes en READ COMMITTED. Cette
isolation ne vaut que pour la transaction suivante ; aucune configuration
MySQL globale n'est changée. Les lectures déterminantes sont rafraîchies sous
`PESSIMISTIC_WRITE`, y compris si l'ORM avait déjà chargé l'objet. Ordre :
représentations concernées, commandes, inventaires, tarifs ; identifiants
croissants dans chaque groupe. Les opérations d'une même représentation sont
sérialisées sur sa ligne, choix conservateur adapté à une salle de 400 places.
Deux représentations différentes ne partagent pas leur inventaire.

Les nouvelles commandes sont limitées à une représentation. Les ventes
historiques multi-représentations sont prises en charge : annuler R1 invalide
et rembourse uniquement les billets R1, puis annuler toute la commande traite
le reste sans rembourser R1 une seconde fois. Une ancienne réservation ou
annulation ne libère jamais les places d'un nouveau propriétaire.

Les échéances sont persistées et comparées à la seconde UTC, comme les DATETIME
MySQL du projet. L'horloge injectable est arrondie à cette même précision. La
durée doit être strictement positive. Échec, annulation ou confirmation à
l'échéance enregistrent l'expiration avant toute autre transition d'une commande
en attente. Une représentation annulée bloque ensuite toutes ses places.
Les lignes acceptées restent conservées même après expiration.

Les tarifs sont revérifiés lors de la confirmation : s'ils ont été désactivés
ou déplacés par un autre outil, la vente n'est pas confirmée. Le changement de
prix seul ne modifie pas l'offre acceptée. Le back-office protège aussi la
représentation d'un tarif dès sa première utilisation dans une réservation,
y compris si le formulaire de modification avait été ouvert avant celle-ci.

Les conflits rendent une erreur métier sans réservation ou émission partielle.
Après annulation d'une transaction, les objets ORM sont détachés pour éviter de
réutiliser un état en mémoire annulé. En cas de timeout/deadlock, le client peut
réessayer : aucune notification bancaire réelle n'a eu lieu. Il n'existe pas
d'identifiant de paiement externe ni de remboursement réseau dans ce sprint.

## Contrôle de concurrence

`ConcurrencyTest` démarre deux processus PHP distincts, chacun avec sa connexion
et son EntityManager. Une troisième connexion retient la ligne représentation.
Le test observe les deux requêtes `FOR UPDATE` simultanément dans
`SHOW FULL PROCESSLIST` avant de libérer la barrière, puis vérifie la base.
Ce n'est pas un test composé de deux appels séquentiels sur un même EntityManager.
Les délais courts de synchronisation des processus n'attendent pas les dix
minutes métier : l'expiration est pilotée par `FrozenClock`.
