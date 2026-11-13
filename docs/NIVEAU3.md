# Niveau 3 — Horizon

Livraison sur `sprint-3-produit`, depuis le Sprint 2 vérifié à `8e62b5e`, puis
le plan admin `8a82262`. Comptes, achats client, guichet, PDF/QR et indicateurs
réutilisent le modèle et les transactions du Sprint 2. Aucun travail des
Niveaux 4 et 5 n'est inclus.

## Comptes, droits et historique

Symfony Security 7.4 utilise un fournisseur Doctrine, les mots de passe hachés,
une session et des formulaires de connexion/déconnexion protégés par CSRF.
L'inscription publique accepte uniquement email et mot de passe (12 caractères
minimum) et crée un client. Un champ rôle ajouté à la requête est refusé.

| Profil | Accès | Catégories de tarifs |
| --- | --- | --- |
| Anonyme | Catalogue, fiches, plan sans données personnelles ; connexion/inscription avant réservation | Aucune réservation |
| Client | Ses seules commandes et leurs billets, achat en ligne | Plein |
| Guichetier | Espace de vente et consultation d'une commande/billet pour son travail ; pas de gestion ou d'indicateurs | Plein, réduit, groupe |
| Administrateur | Gestion, indicateurs et opérations du personnel | Plein, réduit, groupe, invitation |

Le personnel vérifie les conditions des tarifs réduit/groupe. Le sujet ne
fixant aucun âge, justificatif ou seuil, aucune condition commerciale n'est
inventée. Cette matrice explicite reprend D07/D14. Le contrôle est effectué
à l'affichage, dans le formulaire et dans `TicketingService::reserve()` après
verrou du tarif. Le prix et le canal ne proviennent jamais d'un montant ou canal
fourni par le navigateur.

La migration additive `Version20261112020000` crée `app_user` et une relation
nullable `commande.owner_id`, avec suppression `RESTRICT`. Une commande en
ligne est liée au compte authentifié ; une vente au guichet peut garder un
email de destinataire sans propriétaire. Les anciennes commandes ne sont pas
rattachées automatiquement à un compte ayant le même email. `OrderVoter`
contrôle aussi les accès directs aux commandes, billets, téléchargements et
transitions ; les listes de gestion et les indicateurs exigent `ROLE_ADMIN`.

Les pages admin sont désormais protégées par Security en production, à la
place de l'ancien refus global local. Toutes les ventes, paiements et
remboursements de cette démonstration restent interdits hors `dev`/`test`,
y compris par POST direct. Le nettoyage des réservations reste celui du
Sprint 2. Aucune intégration bancaire réelle n'est ajoutée.

## Parcours client et guichet

- `/` → fiche événement → représentation → plan à 400 fauteuils.
- Sélection locale de places libres, sans immobilisation du stock. Dates réelles,
  avertissement avant abandon, mêmes zones, fauteuils, légende et clavier que
  dans l'admin. La projection publique ne contient ni email, propriétaire,
  référence de commande, détail de réservation ou donnée de paiement.
- Continuer vers `/achat/{id}` : si nécessaire, connexion/inscription. La cible
  interne, avec les identifiants sélectionnés, est conservée en session pendant
  l'identification, puis revalidée. Le lien de connexion/inscription du plan
  préserve aussi la sélection locale. Aucune URL de retour externe n'est acceptée.
- Choix d'un tarif autorisé par siège, récapitulatif des identifiants complets et
  total en MAD. Le prix affiché est indicatif jusqu'à la réservation ; le
  récapitulatif serveur de la commande présente ensuite le prix accepté exact.
- POST + CSRF : réservation temporaire atomique via `TicketingService`. L'écran
  affiche le statut et l'échéance serveur, distincts de la sélection locale.
- Paiement simulé réussi/échoué ou abandon ; confirmation et accès aux billets.
  L'échéance est contrôlée sous verrou, y compris en cas de clic tardif.

Le guichetier ouvre `/guichet`, choisit une représentation et utilise le même
plan, puis le même contrôleur de réservation en mode guichet. Un total estimé
au plein tarif accompagne la sélection ; après choix des tarifs, le total est
mis à jour dans le récapitulatif permanent. Sur mobile, celui-ci reste en tête
pendant le défilement, avec la liste des sièges repliable. Le serveur impose
`guichet`, demande l'email du destinataire, vérifie les catégories et laisse
le même service réserver/confirmer. L'ancienne simulation admin impose elle
aussi ce canal au lieu d'un champ modifiable.

Un conflit ne réserve aucune partie du groupe : message, relecture du plan et
conservation des seules places encore libres de la représentation. Les refus
CSRF, les tentatives de tarif non autorisé et les références étrangères ne
créent pas de commande. Une expiration ou un paiement échoué n'émet aucun billet.
Le plan est un instantané ; il ne fait pas de polling et n'anticipe pas les
expirations en inventant un état libre côté navigateur.

Le composant Twig/JS de [SEAT_MAP.md](SEAT_MAP.md) reste commun aux trois écrans.
`seat-map-dates.js` partage le dialogue ; `seat-plan.js` et `seat-map-admin.js`
adaptent les événements aux routes respectives. Les autorisations et actions
restent dans les contrôleurs, pas dans le composant.

## PDF et QR

Un billet valide provenant d'une commande confirmée peut être téléchargé par
son propriétaire ou le personnel sur `/billets/{id}/pdf`. Le PDF A5 paysage
présente événement, date/heure/fuseau, salle, zone, rangée, siège, référence et
prix réellement payé. Dompdf 3.1.6 et Endroid QR Code 6.0.9 fonctionnent dans
PHP 8.3 sans modification du Dockerfile. Les ressources réseau, PHP et JavaScript
sont désactivées dans Dompdf ; aucune page PDF privée n'est déposée dans `public/`.

Le QR mène à `/guichet/controle` avec une référence opaque signée par HMAC
SHA-256. Il ne contient ni email ni détails de commande. Cette route exige un
compte personnel autorisé, vérifie la signature et relit l'état du billet.
Elle ne marque pas une entrée : contrôle d'usage unique/scanner caméra hors
périmètre. Le code n'autorise aucun téléchargement public.

Après annulation, l'historique HTML reste accessible et le PDF répond **410**
après contrôle des droits. Un ancien PDF ne redevient pas valide : le contrôle
en ligne montre l'annulation. Changer `APP_SECRET` invalide les anciennes
signatures ; garder la vraie clé hors Git. Une nouvelle copie peut être
générée pour un billet encore valide. Définir une clé propre à l'environnement
avant un usage autre que cette démonstration locale.

## Indicateurs

`/admin/indicateurs` filtre par représentation, zone et canal. Les agrégations
séparent inventaire et billets pour qu'une revente ne multiplie pas la capacité.

- Capacité : nombre d'inventaires distincts, y compris places réservées/bloquées.
- Vendues : places vendues avec billet valide. Remplissage : vendues / capacité,
  avec numérateur et dénominateur visibles par représentation et zone.
- Encaissé brut : somme des prix payés des billets d'une commande ayant été payée,
  même si ensuite annulée. Aucun prix courant de tarif n'est utilisé.
- Remboursé : somme des remboursements enregistrés sur ces billets. Net : brut
  moins remboursé. Ce sont ici des mouvements **simulés**.
- Invitation : billet compté dans le remplissage, zéro MAD de recette.
- Annulation : billet conservé dans le brut, remboursement déduit, place retirée
  du remplissage courant ; capacité conservée même après annulation d'une séance.
- Filtre canal : ventes et recettes du canal, capacité physique inchangée.
  Filtre zone : capacité et ventes limitées à cette zone. Les titres/libellés sont
  les textes actuels du catalogue, les montants restent historiques.

## Préparer une démonstration isolée

La base utilisateur n'est jamais purgée. Pour un jeu jetable complet, utiliser
les identifiants locaux par défaut ci-dessous ou adapter le compte à son Compose :

```sh
docker compose exec -T database sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot -e "CREATE DATABASE IF NOT EXISTS billetterie_s3_demo_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON billetterie_s3_demo_test.* TO '\''billetterie'\''@'\''%'\'';"'
s3() {
  docker compose exec -T -e APP_ENV=test \
    -e 'DATABASE_URL=mysql://billetterie:billetterie@database:3306/billetterie_s3_demo?serverVersion=8.0&charset=utf8mb4' \
    app "$@"
}
s3 php bin/console dbal:run-sql 'SELECT DATABASE() AS target_database'
```

Continuer **uniquement** si le résultat vaut `billetterie_s3_demo_test` et si
cette base est jetable. Exécuter les étapes séquentiellement en attendant leur
fin : un chargement standard **purge** la base ciblée. Ne pas utiliser `--append`.

```sh
s3 php bin/console doctrine:migrations:migrate --no-interaction
s3 php bin/console doctrine:fixtures:load --no-interaction
s3 php bin/console app:demo:users
s3 php bin/console doctrine:schema:validate
```

Le jeu initial reste celui de S1-05 : 1 salle, 400 places, 3 événements,
4 représentations, 1 600 inventaires, 16 tarifs, 4 commandes et 6 billets,
dont une invitation gratuite. Les comptes sont créés séparément, sans modifier
les fixtures ni les comptes existants. Mot de passe public de démonstration
`HorizonDemo2026!` pour `client@example.com`, `guichet@example.com`,
`admin@example.com`. Une nouvelle inscription ne crée jamais du personnel.

Pour afficher ce jeu via le serveur habituel, choisir explicitement cette base
dans sa configuration locale **en préservant les autres réglages** : en `dev`,
`DATABASE_URL` doit alors viser `billetterie_s3_demo_test` (sans suffixe ajouté
par Doctrine en dev). Ne pas commiter `.env.local`. Le travail de validation
n'a pas changé la configuration locale de l'utilisateur ; son catalogue peut
rester vide si sa propre base ne contient pas de programmation.

Scénario : client → Concert Horizon → R1 (1 décembre 2026) → deux places
initialement libres, par exemple A4/A5 après chargement neuf → réservation →
paiement réussi → PDF. Vérifier les mêmes places vendues au guichet et dans
l'admin pour R1 et libres sur R2 (2 décembre). A1 est déjà vendue sur R1 dans
les fixtures, donc ne pas la prendre comme nouvel achat. Tester ensuite une
vente au guichet avec réduit/groupe, un paiement échoué et une annulation.

## Résultats observés

Docker PHP **8.3.36** / MySQL **8.0**, validation finale du 13 novembre 2026 (Africa/Casablanca) :

- `composer validate --strict`, `composer check-platform-reqs`, `lint:container`,
  `lint:twig templates` (**39 templates**) : OK.
- Mapping et schéma Doctrine synchronisés, sur les bases isolées puis après la
  migration additive locale. Aucune migration historique modifiée.
- Suite standard : **71 tests, 429 assertions**, sans échec sur `billetterie_test`,
  base confirmée avant exécution. Tests de concurrence Sprint 2 inclus : les
  vrais workers et verrous MySQL restent utilisés.
- Après le dernier ajustement du garde de simulation admin : **15 tests ciblés,
  93 assertions**, sans échec (plan, droits et vente admin).
- Suite séparée Demo Fixtures, après migration de `billetterie_s105_verify_test`
  confirmée : **1 test, 16 175 assertions**, sans échec, deux rechargements standard.
- Tests S3 : rôles, propriété et anciennes ventes, inscription/CSRF, tarifs
  forgés et contrôlés sous verrou, achat multi-places, connexion avec sélection,
  conflit entre canaux sans résultat partiel, échec/expiration, téléchargement,
  signature QR et annulation, prix historiques/revente/remboursements et filtres.
- Chrome headless autorisé : achats réels de démonstration sur la seule base
  `billetterie_s3_demo_test` en **1440 × 1000** et **390 × 844**, connexion réelle,
  choix multiples, tarifs, récapitulatif, paiement simulé, téléchargement PDF,
  contrôle croisé des états client/guichet/admin et indépendance de R2. Aucun
  débordement global mesuré sur catalogue, fiche, plan, formulaire et indicateurs.
- Contrôle supplémentaire en lecture seule des plans guichet et admin : clavier,
  gestes tactiles mobiles, fermeture/annulation du changement de date et
  **387 places sélectionnées localement** sur les 400. Liste du récapitulatif
  contenue à 88 px, dernier siège non masqué par le récapitulatif, aucun
  débordement global aux deux tailles. Le défilement de la fenêtre a été ajusté
  après ce contrôle pour préserver le focus même avec une grande sélection.
- Deux PDF téléchargés, une page chacun ; rendu Poppler inspecté, texte vérifié
  par pypdf, QR effectivement décodé depuis la page rendue par ZXing-C++. Aucun
  email ou détail de commande dans le contenu décodé.
- Base locale `billetterie` identifiée avant migration : compteurs commandes,
  billets et inventaires **0 / 0 / 0 avant et après**. Aucune purge, aucun chargement
  de fixtures sur cette base ; trois comptes fictifs ajoutés par la commande
  idempotente. `config/reference.php` préexistant préservé et exclu des commits.

Les achats Chrome ajoutent des ventes au jeu initial de la base S3 jetable ;
les captures représentent différentes étapes, pas un chargement neuf identique.
Le jeu S1-05 et sa suite dédiée restent inchangés.

## Captures et limites

Captures de démonstration inspectées :

- [Catalogue ordinateur](screenshots/niveau3/catalogue-desktop.png)
- [Fiche événement mobile](screenshots/niveau3/event-mobile.png)
- [Plan client ordinateur](screenshots/niveau3/client-plan-desktop.png)
- [Récapitulatif client mobile](screenshots/niveau3/client-checkout-mobile.png)
- [Vente au guichet mobile](screenshots/niveau3/counter-checkout-mobile.png)
- [Indicateurs administrateur](screenshots/niveau3/admin-reports-desktop.png)
- [Grande sélection admin mobile](screenshots/niveau3/admin-large-selection-mobile.png)
- [Rendu du billet PDF](screenshots/niveau3/ticket-desktop.png)
- [Mesures navigateur et décodage PDF](screenshots/niveau3/verification.json)

Les contrôles clavier/toucher utilisent le même composant dans les trois écrans ;
ils ne constituent pas une certification lecteur d'écran ou multi-navigateur.

L'email n'est pas vérifié par un envoi et il n'y a pas de récupération de mot de
passe, de prestataire bancaire, de scanner d'entrée à usage unique, de synchronisation
Google Sheets ou de livraison Niveau 5. Les conditions commerciales réduit/groupe
et une future politique d'entrée restent à définir ; aucune n'est ajoutée en silence.

Documentation technique consultée : [Symfony Security 7.4](https://symfony.com/doc/7.4/security.html),
[Dompdf](https://github.com/dompdf/dompdf), [Endroid QR](https://github.com/endroid/qr-code)
et [décodage ZXing-C++](https://github.com/zxing-cpp/zxing-cpp/blob/master/wrappers/python/README.md).
