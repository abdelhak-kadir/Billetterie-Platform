# Horizon — Redesign S1-06

11 novembre 2026. Identité de présentation pour la salle culturelle, sur le
back-office Symfony/Twig existant. Aucun ajout du Niveau 2.

## Composition et identité

Les pages de référence [accueil Pathé](https://www.pathe.ma/fr),
[catalogue](https://www.pathe.ma/fr/films),
[fiche et séances](https://www.pathe.ma/fr/films/aya-et-hadi-56377) et
[cinéma](https://www.pathe.ma/fr/cinemas/cinema-pathe-californie) ont été consultées
pour étudier la progression catalogue → fiche → dates. Aucun logo, texte,
affiche ou fichier de ces sites n'a été réutilisé.

Horizon utilise un fond encre `#0B1016`, des surfaces `#171E27`, un texte ivoire
`#F5F5F2`, un secondaire `#AEB7C2` et un accent ambre `#F4B942`. Les titres en
Barlow Condensed contrastent avec les informations en police système. La police
est servie localement, avec sa licence OFL dans `public/admin/fonts/`.
Espacements principalement de 8, 16, 24, 32, 40, 48 et 56 pixels, angles discrets,
aucun carrousel ou contrôle factice.

Trois compositions originales générées pour ce projet : concert sous une
lumière ambre, théâtre aux rideaux rouges, conférence sous forme de sculpture
lumineuse. Les fichiers WebP locaux représentent environ 363 Kio au total.
Chaque composition est cadrée en affiche 2:3 et en panorama par `object-fit` /
`object-position`, sans doubler les fichiers. Titres et catégories restent du
texte HTML. Les images sont décoratives pour les lecteurs d'écran : le titre
accessible est celui de la fiche ou du lien de catalogue.

## Présentation et données

- Accueil : événement réel en bannière, trois affiches au maximum, programmation
  réelle (huit occurrences au maximum), puis repères de gestion secondaires.
  Les liens vers les listes complètes restent disponibles.
- Catalogue : filtres GET et pagination conservés, prochaine représentation
  programmée et future recherchée en base ; état explicite si aucune n'existe.
- Fiche : description courte, texte complet via `details` si nécessaire, action
  administrative identifiée, dates uniquement issues des représentations.
  `?date=YYYY-MM-DD` utilise Africa/Casablanca ; une valeur inconnue revient à
  la première date existante. La date sélectionnée porte `aria-current="date"`.
  Le détail de représentation renvoie à cette date de l'événement.
- Salle : capacité et zones réelles ; plan en tableau paginé. Représentation :
  même visuel, statut, compteurs, accès direct aux tarifs et à l'inventaire filtré.
- Inventaire : filtres actifs, résultat total, réinitialisation, pagination
  conservant la requête. Tableau seul, sans vente ni faux plan de sièges.
- Commandes et billets : références monospace, montants alignés et distinction
  explicite entre prix payé historique et prix du tarif actuel.

`EventPresentationExtension` associe les titres exacts des trois événements de
S1-05 à leurs visuels. Les nouveaux événements ou un titre renommé reçoivent
`fallback.svg`, une composition architecturale originale ; ajouter une entrée
à ce mapping pour une nouvelle affiche. Aucun identifiant SQL n'est codé en dur,
aucun champ image ni migration ajouté. Les quantités et relations S1-05 ne changent pas.
La désignation « Horizon » est l'identité graphique, pas une modification des données.

Le serveur PHP existant envoyait les fichiers CSS/images avec un type MIME HTML.
Un traitement limité à `cli-server` dans `public/index.php` rend les fichiers
existants au serveur statique après vérification de leur chemin canonique sous
`public/`. Cette correction est nécessaire au fonctionnement des assets locaux ;
Docker, les migrations et les dépendances restent inchangés.

## Accessibilité

Navigation clavier native, lien d'évitement, focus ambre de 3 px, contrôles de
44 px minimum, aucune modale de filtres et aucun nouveau JavaScript. Les
statuts portent toujours un libellé. Les tableaux sont des régions défilantes
avec `tabindex="0"` ; la navigation mobile défile dans son propre conteneur.
Les animations de survol et le défilement animé sont désactivés sous
`prefers-reduced-motion: reduce`. Les formulaires et leurs erreurs restent
rendus par Symfony, avec labels visibles, CSRF et validation serveur.

Contrastes calculés selon la luminance relative sRGB sur les couleurs réellement
utilisées des surfaces unies :

| Texte / fond | Ratio |
| --- | --- |
| Ivoire / fond encre | 17,48:1 |
| Secondaire / surface de gestion | 8,27:1 |
| Encre / bouton ambre | 10,79:1 |
| Lien ambre / surface de gestion | 9,48:1 |
| Statut disponible / fond vert sombre | 8,73:1 |
| Statut en attente / fond ambre sombre | 8,54:1 |
| Erreur ou statut vendu / fond rouge sombre | 8,46:1 |
| Statut neutre / fond gris sombre | 9,13:1 |

Les textes superposés aux images sont protégés par un fondu sombre horizontal
sur desktop et vertical sur mobile. Leur lisibilité a été inspectée sur les
cadrages des trois visuels et du secours ; ces ratios ne prétendent pas mesurer
chaque pixel des photographies. Une nouvelle image exige une nouvelle inspection.

## Vérifications réalisées

Docker PHP 8.3.36 et MySQL 8.0 : Composer strict, lint du conteneur, lint des
22 templates Twig et validation du schéma réussis. Suite standard :
**31 tests, 153 assertions**, dont trois tests de présentation couvrant les dates,
le retour depuis un détail, le secours, les prochaines dates et les états vides.
La suite Demo Fixtures reste séparée et inchangée ; aucune dépendance des fixtures
n'est modifiée par ce redesign.

Chrome headless a été utilisé avec l'accord explicite de l'utilisateur, le
navigateur connecté étant indisponible. Contrôles en **1440×1000** et **390×844** :
accueil, catalogue, fiche, sélection de deuxième date et retour, représentation,
salle, filtres d'inventaire A1/R1 (vendue) puis A1/R2 (disponible), commandes,
billets et détail, formulaire soumis vide et erreurs serveur, recherche sans
résultat. Un événement non référencé de la base de tests vérifie le secours.
Images chargées sans erreur, largeur globale égale à celle du viewport sur ces
pages ; le focus du lien d'évitement est visible. Les captures ont été inspectées
et le débordement du libellé de fuseau horaire ainsi qu'un titre mobile ont été corrigés.

Les données S1-05 proviennent de `billetterie_s105_verify_test`, déjà chargée avant
ce travail, consultée via un serveur d'aperçu temporaire en environnement test.
La suite standard utilise `billetterie_test`. Aucune purge ni écriture métier
sur la base utilisateur ; aucun nouveau chargement de fixtures. La recherche
existante `A1` correspond aussi à A10–A19 : A1 est la première ligne triée, sans
changement de la sémantique du filtre.

## Captures et limites

Les captures avant utilisent les templates du commit `cf46c06`, rendus avec les
mêmes compteurs de la base S1-05, sans rétablir d'anciens fichiers dans le dépôt.
Les captures après montrent le nouveau rendu sur cette même base dédiée.

- [Avant desktop](screenshots/redesign/avant-desktop.png) / [après desktop](screenshots/redesign/accueil-desktop.png).
- [Avant mobile](screenshots/redesign/avant-mobile.png) / [après mobile](screenshots/redesign/accueil-mobile.png).
- [Catalogue](screenshots/redesign/catalogue-desktop.png), [programmation mobile](screenshots/redesign/programmation-mobile.png).
- [Inventaire R1](screenshots/redesign/inventaire-r1.png), [erreurs du formulaire](screenshots/redesign/formulaire-erreurs-mobile.png), [secours](screenshots/redesign/secours-mobile.png).

Le travail n'est pas un audit complet WCAG ou lecteur d'écran. Il n'ajoute aucun
compte, rôle, paiement, réservation, remboursement ou contrôle de concurrence.
Le blocage de `/admin` en production et les restrictions métier restent en place.
La modification préexistante de `config/reference.php` est exclue du commit.
