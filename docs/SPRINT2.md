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
