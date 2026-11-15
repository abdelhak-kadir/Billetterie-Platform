# Journal des décisions — S1-03

Finalisation du 11 novembre 2026, à partir des propositions du 10 novembre.
Sources : `sujet-1-billetterie.md` et `00-demarche.md`, lus dans
`/home/kadir/Downloads/md/`. Le sujet fixe les besoins ; la démarche autorise à
trancher les ambiguïtés en notant les hypothèses. Les décisions ci-dessous
constituent une conception à implémenter, sans prétendre réaliser les Niveaux 2
et 3. Le modèle détaillé et ses scénarios se trouvent dans [MODELE.md](MODELE.md).

## D01 — Plan fixe et configurations alternatives

- **Date :** 11 novembre 2026.
- **Contexte :** le sujet exige 400 places numérotées regroupées en zones et
  réutilisables ; il demande de trancher les configurations alternatives.
- **Décision :** une configuration initiale fixe de 400 places. La proposition
  Parterre 300 (15 rangées de 20) / Balcon 100 (5 rangées de 20) est une hypothèse
  de démonstration. Les places gardent leur identité entre représentations.
- **Justification :** démarrer avec le plan demandé sans multiplier les variantes.
- **Conséquences :** pas de fosse debout initialement. Dès utilisation, le plan
  est figé. Une évolution introduira des configurations versionnées, choisies
  avant ouverture de la vente, avec leur propre plan et capacité ; elle ne
  modifiera pas les places ou inventaires historiques. Aucune entité de
  configuration alternative n'est créée dans S1-03.

## D02 — Inventaire indépendant par représentation

- **Date :** 11 novembre 2026.
- **Contexte :** le sujet veut réutiliser le plan et éviter les doubles ventes.
- **Décision :** InventairePlace lie une Place physique à une Representation ;
  disponibilité portée exclusivement par cette liaison. UNIQUE
  `(representation_id, place_id)` et UNIQUE `(zone_id, rangee, numero)` pour
  l'identification des places ; cohérence de salle vérifiée par l'application.
- **Justification :** vendre A1 pour R1 ne doit pas vendre A1 pour R2.
- **Conséquences :** toute nouvelle représentation reçoit atomiquement ses 400
  inventaires. Le compteur de capacité dérive du plan. Une contrainte d'unicité
  d'inventaire ne résout pas à elle seule la concurrence (D08).

## D03 — Montants et prix payé

- **Date :** 11 novembre 2026.
- **Contexte :** recettes et plusieurs tarifs sont exigés ; le sujet ne fixe pas
  la devise et pose la question du prix historique.
- **Décision :** conformément au cadrage S1-03, montants entiers en centimes de
  MAD, jamais en flottants. Chaque Billet conserve définitivement son
  `prix_paye_centimes`. Une invitation peut valoir zéro.
- **Justification :** calcul exact et historique indépendant du catalogue.
- **Conséquences :** 100 MAD = 10000 centimes, même si le tarif devient 120 MAD.
  Les remboursements sont enregistrés séparément sans réduire ce prix historique.
  Ventes brutes, remboursements confirmés, sommes encore dues et recette nette
  doivent être distingués ; annulation et sortie d'argent ne sont pas synonymes.

## D04 — Tarifs modifiables après vente

- **Date :** 11 novembre 2026.
- **Contexte :** le sujet demande plusieurs tarifs et le sort des billets déjà vendus.
- **Décision :** un Tarif appartient à une représentation et s'applique à toutes
  ses zones (hypothèse de périmètre). Son prix est modifiable pour les ventes
  futures ; un tarif utilisé est désactivé plutôt que supprimé. Les billets
  émis ne sont jamais recalculés. Plusieurs libellés d'une catégorie sont permis.
- **Justification :** permettre l'évolution du catalogue en protégeant les recettes passées.
- **Conséquences :** pas de prix par zone initialement. Hypothèse proposée pour
  le Niveau 2 : prix courant revérifié à la confirmation ; si le prix affiché a
  changé pendant la réservation, demander une nouvelle acceptation avant achat.
  La réservation ne garantit donc pas encore un prix : ce choix reste à valider.

## D05 — Paiement non abouti et réservation

- **Date :** 11 novembre 2026.
- **Contexte :** réservation temporaire exigée au Niveau 2 ; aucun délai ni
  prestataire de paiement n'est donné.
- **Décision :** associer une réservation à une commande et une échéance.
  Échec, abandon ou expiration libèrent ses places sous transaction. La durée
  sera configurable et reste à valider ; l'ancienne proposition de 10 minutes
  n'est pas retenue comme règle acquise. Le Niveau 1 utilisera des achats de
  démonstration, sans intégration de paiement externe réelle.
- **Justification :** ne pas immobiliser des places après un achat non abouti.
- **Conséquences :** au Niveau 2, contrôle de l'échéance à chaque confirmation et
  nettoyage des réservations expirées. Vérifier le propriétaire avant de libérer
  une place ; une ancienne commande ne doit pas libérer une réservation nouvelle.
  Une confirmation tardive ne revend pas une place réattribuée : la régularisation
  financière reste à concevoir avec le futur moyen de paiement.

## D06 — Annulation et remboursements

- **Date :** 11 novembre 2026.
- **Contexte :** annulation de commande et remboursement exigés au Niveau 2 ;
  le sujet demande le sort des billets si une représentation est annulée.
- **Décision :** annuler une représentation interdit la vente, bloque ses
  inventaires et annule ses billets sans effacer les ventes. Prix positifs :
  remboursement intégral dû ; invitation gratuite : aucun montant dû.
  « Remboursé » ne sera enregistré qu'après confirmation effective.
- **Justification :** préserver les preuves de vente et distinguer invalidation
  du billet, dette et remboursement réellement réalisé.
- **Conséquences :** hypothèse : une commande peut couvrir plusieurs
  représentations ; seuls ses billets concernés sont annulés/remboursés, les
  autres restent valides. Une commande encore en attente touchée est annulée
  entièrement et ses autres réservations libérées pour éviter une confirmation
  partielle implicite. Les remboursements sont suivis par billet puis agrégés
  par commande, autorisant un remboursement partiel de la commande. Hypothèse :
  pas de remboursement partiel d'un billet. Une annulation volontaire de commande
  payée annulera tous ses billets et libérera les places encore vendables ;
  critères d'autorisation, délais et conditions commerciales restent à valider.
  Ces workflows seront implémentés et testés au Niveau 2.

## D07 — Accès aux tarifs réduit, groupe et invitation

- **Date :** 11 novembre 2026.
- **Contexte :** ces catégories sont exigées ; aucun âge, justificatif, seuil de
  groupe ou matrice de permissions n'est fixé. Les rôles relèvent du Niveau 3.
- **Décision :** hypothèse de départ pour le Niveau 3 : client en ligne au plein
  tarif ; guichetier au plein, réduit et groupe après vérification des conditions ;
  administrateur autorisé à émettre les invitations et gérer les tarifs.
- **Justification :** donner une responsabilité explicite aux avantages contrôlés.
- **Conséquences :** ne pas inventer de seuil de groupe ni de condition de réduction.
  Ces critères et permissions restent à valider avec le client ; accès en ligne
  au réduit/groupe à réexaminer. Aucun rôle ni contrôle d'accès n'est implémenté
  dans S1-03. Le futur back-office du Niveau 1 sera une démonstration locale,
  avant protection des accès au Niveau 3.

## D08 — Historique et protection contre les doubles ventes

- **Date :** 11 novembre 2026.
- **Contexte :** une place ne doit être vendue qu'une fois par représentation,
  y compris lors d'achats simultanés (Niveau 2), mais peut être revendue après annulation.
- **Décision :** plusieurs Billets historiques par inventaire ; au plus un valide.
  Un billet annulé ne redevient jamais valide. Prévoir un index UNIQUE sur une
  colonne générée nullable contenant l'identifiant d'inventaire uniquement quand
  le billet est valide. Prévoir transactions, verrous de lignes et revalidation
  des états pour toute réservation, confirmation, libération ou annulation.
- **Justification :** un UNIQUE sur l'inventaire empêche les doublons de lignes,
  mais ne sérialise pas les achats ; un UNIQUE inconditionnel sur le lien du
  billet empêcherait de conserver l'historique lors d'une revente.
- **Conséquences :** ancienne commande et ancien billet restent consultables ;
  la revente produit un nouveau billet. Confirmation répétée sans duplication,
  ordre de verrouillage stable et annulation de représentation concurrente à
  couvrir au Niveau 2. L'index généré a été validé sur MySQL 8 dans la migration
  S1-04 (colonne STORED, index UNIQUE) ; il ne remplace pas les verrous du Niveau 2.

## D09 — Suppressions et conservation de l'historique

- **Date :** 11 novembre 2026.
- **Contexte :** le suivi des ventes et recettes perd son sens si le catalogue
  ou les commandes effacent les ventes passées.
- **Décision :** aucune suppression destructive de Commande ou Billet émis ;
  désactivation des tarifs et annulation des représentations. Relations SQL
  avec suppression RESTRICT, sans cascade destructrice vers les ventes.
- **Justification :** conserver références, prix et associations après annulation.
- **Conséquences :** refus applicatif des suppressions d'objets déjà utilisés,
  plan figé et changement d'état explicite. La suppression d'un brouillon sans
  dépendances reste possible ; les liens historiques ne sont jamais réaffectés.

## D10 — Dates et données de démonstration

- **Date :** 11 novembre 2026.
- **Contexte :** le sujet demande des données réalistes et plusieurs événements,
  mais ne fixe ni fuseau ni nombre exact de représentations de démonstration.
- **Décision :** hypothèse technique : stockage UTC, affichage Africa/Casablanca.
  Hypothèse de démonstration future : commencer par un événement avec deux
  représentations pour prouver l'indépendance des inventaires, puis plusieurs
  événements et ventes ; utiliser des adresses `example.test`.
- **Justification :** comparer correctement les échéances et démontrer le modèle
  sans données personnelles réelles.
- **Conséquences :** aucun événement, compte ou fixture créé dans S1-03 ; la
  tranche d'un seul événement ne suffira pas à déclarer le Niveau 1 complet.

## Trace historique

Les propositions initiales du 10 novembre 2026 sont conservées dans l'historique
Git. Leur état de session (outils indisponibles, squelette encore prévu) décrivait
ce moment et ne constitue pas une décision métier actuelle. La présente version
remplace ces indications devenues périmées sans réécrire les commits précédents.

## D11 — S2-01 : réservation et prix acceptés (12 novembre 2026)

- **Contexte :** le Niveau 2 exige une réservation temporaire ; les décisions D04
  et D05 laissaient le prix durant l'attente et la durée à valider.
- **Décision :** hypothèse retenue : 600 secondes configurables par
  `RESERVATION_TTL_SECONDS`. Une commande nouvelle couvre une représentation,
  plusieurs places, chacune avec un tarif actif cohérent. Le prix est fixé au
  moment de réserver, conservé dans une ligne immuable `ReservationItem`, puis
  copié dans chaque billet. Cette décision remplace la proposition D04 de
  réactualisation du prix lors de la confirmation.
- **Justification :** l'acheteur confirme exactement le montant accepté ; le
  service peut vérifier l'ensemble des places sans résultat partiel.
- **Conséquences :** migration additive pour les lignes et l'échéance ; horloge
  injectable UTC. À `now >= échéance`, la commande est expirée et toutes ses
  réservations encore détenues sont libérées. L'heure est lue après les verrous.
  Un échec tardif ne modifie jamais une commande déjà payée. Une notification de
  succès sur commande expirée ne délivre aucun billet.

## D12 — S2 : transactions, annulations et simulation (12 novembre 2026)

- **Contexte :** unicité SQL, historique et concurrence doivent se compléter.
- **Décision :** verrouiller les représentations concernées, les commandes, les
  inventaires puis les tarifs, par identifiants croissants dans chaque groupe.
  Toutes les opérations de vente utilisent le même service transactionnel.
  Les lignes sont relues sous verrou ; l'index de billet valide reste conservé.
  Une réservation expirée peut être récupérée avant le passage du nettoyage.
- **Justification :** éviter double attribution et verrouillages incohérents ;
  garder le nettoyage et les notifications rejouables.
- **Conséquences :** une annulation en attente libère ses seules places. Une
  vente annulée invalide les billets, rembourse intégralement le prix payé de
  chaque billet positif, ne rembourse rien pour une invitation et conserve
  l'historique. Une place annulée redevient disponible seulement si sa
  représentation est encore programmée et non commencée, sinon elle est bloquée.
  Annuler une représentation bloque tout son inventaire, annule les commandes
  en attente concernées et rembourse ses billets vendus ; les billets d'autres
  représentations d'une commande historique restent valides. Une commande payée
  n'est annulée que lorsque tous ses billets le sont. Les remboursements sont
  simulés et enregistrés sur les billets dans la même transaction, sans appel
  réseau : un rejeu n'ajoute aucun montant. Pas de frais, seuil commercial,
  remboursement partiel d'un billet, compte ou paiement externe ajouté.

## D13 — S2 : intégration au back-office et héritage (12 novembre 2026)

- **Contexte :** le Sprint 1 contient déjà des ventes fictives et des formulaires
  protégés. Les nouveaux achats doivent garder les mêmes invariants.
- **Décision :** conserver les ventes existantes sans rétrocréer de lignes de
  réservation. Les nouvelles commandes sont mono-représentation, les commandes
  historiques multi-représentations gardent l'annulation partielle par événement.
  Les actions locales sont POST + CSRF sous `/admin`, toujours bloqué en production.
- **Justification :** démontrer le cycle métier sans ajouter de paiement public,
  rôle ou compte ; éviter de réécrire l'historique et les données S1-05.
- **Conséquences :** le formulaire local applique un tarif aux places sélectionnées,
  le service accepte un tarif par ligne. Un tarif référencé par une ligne acceptée
  devient non réaffectable, avec relecture sous verrou à la modification. La
  suite Demo Fixtures reste séparée. Les migrations sont additives ; les tests
  refusent de vider une base autre que les bases de tests dédiées.

Précision D12 : pour cette simulation locale, l'annulation intégrale d'une commande
payée est acceptée sans délai ni frais, même après le début. Aucune place n'est
remise en vente après le début ou sur une représentation annulée/terminée. Cette
hypothèse ne constitue pas une politique commerciale pour le futur produit.

## D14 — Niveau 3 : identité, propriété et tarifs (12 novembre 2026)

- **Contexte :** les ventes historiques n'ont pas de compte ; D07 proposait une
  matrice de droits sans critères commerciaux détaillés.
- **Décision :** inscription publique client uniquement. Le client achète au
  plein tarif et consulte ses commandes liées par clé étrangère, jamais par
  simple égalité d'email. Le guichetier vend au plein, réduit et groupe ;
  l'administrateur peut aussi émettre une invitation. Les catégories autorisées
  sont revérifiées dans la transaction de réservation après verrou du tarif.
- **Justification :** éviter l'appropriation d'un historique et les contournements
  de prix par requête modifiée. Aucun âge, justificatif ni seuil de groupe n'est
  ajouté : la vérification des conditions reste une responsabilité du personnel.
- **Conséquences :** table de comptes et propriétaire nullable ajoutés par
  migration additive. Les anciennes commandes restent sans propriétaire et
  consultables par le personnel. Le personnel peut consulter une commande et
  ses billets pour son travail ; les listes de gestion et indicateurs sont
  réservés à l'administrateur. Les canaux sont déterminés par les contrôleurs.
  Le garde local de l'administration cède la place à Symfony Security après
  tests des rôles ; les paiements, ventes et remboursements simulés restent
  réservés aux environnements `dev` et `test`. Pas d'intégration bancaire réelle.

## D15 — Billet PDF et QR signé (12 novembre 2026)

- **Contexte :** le Niveau 3 demande un billet téléchargeable avec QR, sans
  définir de politique de contrôle d'entrée.
- **Décision :** PDF A5 paysage généré à la demande après paiement confirmé,
  accessible au propriétaire ou au personnel. QR contenant une référence opaque
  et un HMAC SHA-256, sans email ni détail de commande. Le lien du QR conduit
  à un contrôle authentifié réservé au personnel, avec relecture de l'état.
- **Justification :** un fichier déjà téléchargé ne peut pas être rappelé ;
  c'est l'état courant du billet qui décide de sa validité.
- **Conséquences :** billet annulé conservé en HTML mais téléchargement refusé
  par 410 ; une ancienne copie reste invalide. Le QR n'accorde aucun droit de
  téléchargement et ne marque pas une entrée. Pas de contrôle d'usage unique ou
  de scanner caméra ajouté. Une rotation d'`APP_SECRET` invalide les anciennes
  signatures ; le propriétaire peut générer un nouveau PDF si son billet est
  toujours valide. La clé réelle reste hors Git. Dompdf ne charge aucune ressource
  réseau ; SVG du QR généré localement, HTML échappé, PDF privé sans cache.

## D16 — Indicateurs et filtres (12 novembre 2026)

- **Contexte :** le remplissage et les recettes ne doivent pas confondre stock
  courant, historique de revente et remboursements.
- **Décision :** capacité = inventaires distincts de chaque représentation/zone,
  y compris bloqués/réservés ; vendues = inventaires vendus avec billet valide.
  Pourcentage = vendues / capacité. Brut = somme des prix payés des billets
  émis par commandes ayant été payées ; remboursé = montants effectivement
  enregistrés sur ces billets ; net = brut moins remboursé.
- **Justification :** l'historique peut contenir plusieurs billets d'une même
  place ; un changement de prix courant ne change pas les recettes passées.
- **Conséquences :** invitations comptées dans le remplissage pour zéro recette.
  Annulations conservées dans le brut ; remboursements déduits du net. Une
  représentation annulée garde sa capacité et son historique financier, avec
  zéro place vendue courante. Le filtre canal restreint les ventes et recettes,
  sans réduire la capacité ; le filtre zone restreint aussi le dénominateur.
  Les libellés de tarifs affichés sont les libellés actuels, les montants sont
  historiques. Aucun regroupement par date de mouvement comptable n'est ajouté.
