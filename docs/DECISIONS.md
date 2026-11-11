# Journal des décisions

## 10 novembre 2026 — Hypothèses initiales

Ces décisions décrivent la cible ; leur présence ne signifie pas que les fonctionnalités sont implémentées.

| Question | Décision et justification |
| --- | --- |
| Configuration de salle | Une salle fixe de 400 places numérotées, réparties entre Parterre (300) et Balcon (100). Toutes les représentations réutilisent ce plan. Les fosses debout et configurations alternatives sont hors du premier périmètre. |
| Identification des places | Une place est identifiée par sa zone, sa rangée et son numéro. Le libellé public inclut ces trois éléments pour éviter les ambiguïtés. |
| Disponibilité | Une ligne d'inventaire par représentation et place physique, protégée par une contrainte unique SQL `(representation_id, place_id)`. Deux représentations ne partagent jamais leur état de vente. |
| Double vente | L'unicité des lignes d'inventaire est un socle, pas une protection suffisante contre deux achats simultanés. Le Niveau 2 ajoutera une transaction et un verrou de ligne avant toute vente. |
| Modification des tarifs | Le billet conserve son prix payé en centimes de MAD. Une modification du tarif affecte uniquement les ventes futures. Les tarifs utilisés sont désactivés plutôt que supprimés. |
| Tarification initiale | Tarifs par représentation, applicables à toutes ses zones. Plein, réduit, groupe et invitation sont disponibles. Les prix par zone sont différés. |
| Paiement non abouti | Réservation prévue de 10 minutes, puis libération de la place si le paiement échoue ou expire. Le Niveau 2 vérifiera l'expiration lors de l'achat et prévoira une commande de nettoyage. Aucun paiement externe réel au Niveau 1. |
| Annulation d'une représentation | Interdire les nouvelles ventes, annuler les billets et conserver commandes et prix. Les commandes payées deviennent à rembourser ; elles ne seront marquées remboursées qu'après confirmation du remboursement. Une commande peut concerner plusieurs représentations : seule la part concernée est remboursée. Ce workflow reste à implémenter au Niveau 2. |
| Qui peut vendre à quel tarif ? | Cible Niveau 3 : client en ligne au plein tarif ; guichetier au plein, réduit ou groupe avec vérification des conditions ; administrateur pour les invitations et la gestion des tarifs. Le Niveau 1 reste une démonstration locale sans gestion des rôles. |
| Historique | Une place libérée peut être revendue ; les anciens billets restent annulés ou remboursés. Un seul billet valide est autorisé pour une ligne d'inventaire. Les objets liés aux ventes ne sont pas supprimés. |
| Données de démonstration | Une première tranche salle + événement + deux représentations, puis plusieurs événements et commandes fictives. Les adresses utilisent le domaine `example.test`. |
| Dates | Stockage en UTC, affichage avec le fuseau `Africa/Casablanca`. |

## 10 novembre 2026 — État du dépôt et contraintes de session

Le dépôt existant pointe vers `git@github.com:abdelhak-kadir/Billetterie-Platform.git` et contient déjà le commit README `b2c14e8` (`first commit`). Cet historique est conservé : aucune réécriture pour faire artificiellement du squelette le premier commit.

Le prochain commit d'implémentation prévu est `chore: initialize Symfony 7.4 project`. Il devra contenir un véritable squelette généré et vérifié avant les changements métier.

La session actuelle ne dispose ni de PHP ni de Composer, ne peut accéder au démon Docker, ne résout ni GitHub ni Packagist, et `gh auth status` signale un jeton invalide. Elle ne peut donc générer le squelette via Composer, appliquer les migrations MySQL ou pousser les commits. Les documents ont été préparés dans une copie de travail accessible, sans commit afin de garder le squelette comme prochain commit d'implémentation.
