# Organiser un événement et ses spectacles

Un **événement** regroupe un programme, par exemple « Festival Horizon ».
Une **représentation**, appelée **spectacle** dans les écrans de gestion, est
une séance réservable de ce programme : son nom, sa date, sa salle et son
inventaire sont propres. Le client choisit le spectacle auquel il souhaite
assister, puis ses places. Deux spectacles peuvent utiliser les mêmes places
physiques sans partager leur disponibilité.

## Créer la programmation

1. Se connecter comme administrateur et ouvrir **Mes événements → Créer un
   événement**, ou `/admin/evenements/creer`.
2. Renseigner le titre, le type, la description et éventuellement une affiche.
   Choisir la salle ; après un changement, charger ses rangées.
3. Donner un nom et un horaire futur à chaque spectacle. Le bouton **Ajouter
   un spectacle** permet d'en préparer plusieurs dans le même formulaire,
   jusqu'à douze par enregistrement.
4. Fixer les prix Standard, VIP et VVIP de chaque spectacle, en MAD.
   Les montants proposés, 100, 200 et 300 MAD, sont des exemples modifiables.
5. Répartir les rangées entre ces trois catégories. La proposition initiale
   est modifiable ; elle s'applique seulement aux nouveaux spectacles du formulaire.
6. Enregistrer : l'événement, les spectacles, leurs inventaires complets et
   leurs tarifs sont créés dans une même transaction.

L'événement s'ouvre ensuite sous forme de cartes de spectacles. Chaque carte
donne accès au plan, aux prix et aux ventes de ce spectacle. Le fil d'Ariane
permet de remonter à l'événement. **Ajouter des spectacles** utilise le même
formulaire sans recréer l'événement. Les anciens tableaux restent accessibles
dans **Outils avancés** pour les opérations détaillées.

## Catégorie de place et type de tarif

**Standard, VIP et VVIP** décrivent la place pour un spectacle. **Plein,
réduit, groupe et invitation** décrivent une offre commerciale et ses droits
d'accès. Ces deux notions sont distinctes. Le formulaire guidé prépare un
plein tarif par catégorie ; depuis le spectacle, on peut ajouter les autres
offres en sélectionnant aussi la catégorie de place concernée.

Le plan affiche la catégorie et son prix. Le récapitulatif additionne les prix
des sièges sélectionnés ; l'étape d'achat propose seulement les tarifs de leur
catégorie autorisés pour le rôle connecté. Le serveur contrôle aussi cette
correspondance sous verrou, indépendamment de l'affichage. Le paiement reste simulé.

## Installations existantes

La migration ajoute un nom facultatif et des catégories sans réaffecter les
anciens sièges ni modifier les prix, comptes ou ventes. Un ancien spectacle
sans nom spécifique affiche le titre de son événement ; il peut être renommé
depuis sa fiche. Ses tarifs historiques continuent à fonctionner.

Le jeu de fixtures reste identique : 400 places, trois événements, quatre
représentations et seize tarifs. Il n'attribue pas rétroactivement VIP/VVIP aux
anciennes représentations. Pour essayer les nouvelles catégories, créer un
événement ou ajouter des spectacles via le formulaire guidé. Ne pas recharger
les fixtures sur une base contenant des achats à conserver.

Voir [INSTALL.md](../INSTALL.md) pour les migrations et les comptes,
[MODELE.md](MODELE.md) pour les relations et [les tests](../tests/README.md)
pour la validation automatisée.
