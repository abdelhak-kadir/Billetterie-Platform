# Google Sheets : configuration et reprise

L’application enregistre les ventes et remboursements confirmés dans MySQL,
puis les projette automatiquement dans un onglet de la direction. Aucun e-mail,
nom de client, propriétaire, mot de passe, jeton ou détail bancaire n’est exporté.
Les paiements et remboursements de la démonstration restent simulés.

## Préparer une feuille autorisée

1. Créer un projet Google Cloud et y activer **Google Sheets API**.
2. Créer un compte de service et télécharger sa clé JSON. Aucun rôle général
   de propriétaire/éditeur du projet Cloud n’est nécessaire pour cette feuille.
3. Créer une feuille de test privée et la partager comme **Éditeur** avec le
   `client_email` du compte de service. Ne pas activer de délégation de domaine.
4. Placer la clé dans `var/credentials/google-sheets.json`, dossier ignoré par
   Git et exclu du contexte de construction Docker. Limiter ses droits locaux
   au compte exécutant l’application. Ne jamais copier son contenu dans un commit.
5. Copier l’identifiant entre `/d/` et `/edit` dans l’URL de la feuille.

Dans `.env.local` (ignoré par Git), configurer uniquement cette installation :

```dotenv
SHEETS_ENABLED=1
GOOGLE_SHEETS_ID=identifiant_de_la_feuille_autorisee
GOOGLE_SHEETS_TAB="Ventes Horizon"
GOOGLE_APPLICATION_CREDENTIALS=/app/var/credentials/google-sheets.json
```

Le chemin est celui vu **dans le conteneur**. La clé située dans le `var/` de
la copie de travail est accessible par son montage `/app`. Ne pas utiliser la
même combinaison feuille/onglet pour deux bases ou clones distincts.

```sh
./bin/setup-demo
```

Après installation, le script démarre le service `sheets-worker` et inclut les
billets historiques déjà confirmés. Il relit la configuration à chaque relance.
Un worker tourne ensuite toutes les cinq secondes, sans action manuelle après
chaque achat. Aucune clé Google n’est nécessaire pour installer et tester le
reste de l’application ; `SHEETS_ENABLED=0` est la valeur par défaut.

Pour désactiver les exports, remettre `SHEETS_ENABLED=0` puis relancer le setup :
le worker est arrêté. Les demandes restent en MySQL pour une activation ultérieure.

## Colonnes et signification

Une ligne correspond à un billet, identifié par sa référence stable. Les
horaires sont des textes ISO 8601 UTC ; les montants sont des **entiers en
centimes de MAD**. La recette nette peut être calculée directement, sans lire
le tarif courant. Les valeurs sont écrites en mode `RAW`, donc un titre qui
commence par `=` ne devient pas une formule.

| Colonne | Valeur |
| --- | --- |
| `reference_billet` | Clé métier stable, identique après remboursement. |
| `reference_commande` | Référence de la commande confirmée. |
| `evenement`, `representation_utc` | Programme et date de la séance. |
| `salle`, `zone`, `rangee`, `place` | Position physique du billet. |
| `tarif`, `canal` | Libellé du tarif et canal `en_ligne` ou `guichet`. |
| `prix_paye_centimes` | Prix historique conservé. |
| `remboursement_centimes` | Montant remboursé enregistré pour ce billet. |
| `net_centimes` | Prix payé moins remboursement. |
| `statut_billet` | `valide` ou `annule`. |
| `vente_utc`, `remboursement_utc` | Confirmation et remboursement ; date vide si sans objet. |

L’onglet est une projection gérée par Horizon : ne pas insérer, supprimer ou
trier physiquement ses lignes. Utiliser des vues filtrées ou un autre onglet
pour les analyses. Une ligne déplacée ou des en-têtes étrangers sont détectés
avant écrasement ; la reprise peut utiliser un nouvel onglet vide.

## Garanties et reprise

- La file `sheets_sync_job` est écrite dans la **même transaction MySQL** que
  l’émission ou l’annulation du billet. Un rollback annule aussi la demande.
- Une référence de billet possède une seule demande durable. Une révision
  augmente seulement quand la projection change. Un backfill identique ne
  réinitialise ni l’envoi réussi, ni son calendrier de reprise.
- La ligne cible dépend de l’identifiant SQL durable, jamais d’un `append`.
  Après un timeout ambigu, le rejeu écrit la même plage, sans nouvelle ligne.
- Un verrou MySQL par base/destination sérialise les workers. Aucun verrou de
  ligne n’est maintenu pendant l’appel HTTP ; les achats restent indépendants.
- Si un remboursement arrive pendant un envoi, le succès de l’ancienne révision
  ne valide pas la nouvelle. Le worker envoie ensuite le nouvel état.
- Les erreurs restent visibles, avec nombre d’essais et prochaine tentative.
  Le délai commence à 5 secondes, augmente et est plafonné à une heure.
  Une panne Google n’annule jamais une vente déjà confirmée.
- Un changement de feuille ou d’onglet déclenche la reprojection des demandes
  déjà envoyées. Garder un seul worker/destination configuré par installation.

L’admin voit les compteurs et la dernière erreur contrôlée dans
`/admin/indicateurs`. Les erreurs n’incluent pas les réponses OAuth ou les clés.

```sh
# Compteurs et dernière erreur, sans appel à Google.
docker compose exec -T app php bin/console app:sheets:sync --status
# Reprise immédiate, sinon les essais continuent automatiquement.
docker compose exec -T app php bin/console app:sheets:sync --retry-failed
# Inclure ou revérifier les billets historiques ; aucun doublon si inchangés.
docker compose exec -T app php bin/console app:sheets:sync --enqueue-existing
# Cycle de vie du worker lorsque la configuration change.
docker compose --profile sheets logs --tail=50 sheets-worker
./bin/setup-demo
```

En cas de 403, vérifier l’activation de l’API et le partage avec le compte de
service. En cas de 401, vérifier la clé ; le jeton en cache est invalidé. Un 429
ou une indisponibilité déclenche la reprise différée. Si l’onglet a été déplacé,
choisir un nouvel onglet vide puis relancer le setup. Les autres onglets de la
feuille ne sont pas modifiés par cette opération.

## Tests et vérification réelle

Les tests couvrent le mapping sans données personnelles, le rollback SQL tardif,
la panne après écriture, le rejeu sans doublon, le remboursement, une révision
concurrente, la désactivation, le changement de destination et l’exclusion d’un
second worker. L’adaptateur HTTP vérifie les plages stables, `RAW`, l’absence
d’`append`, les en-têtes/lignes étrangers, l’extension de grille et les erreurs.

```sh
docker compose exec -T app php bin/phpunit --filter 'SheetsSyncTest|GoogleSheetsClientTest'
```

La commande de worker refuse les bases et l’environnement PHPUnit. Les tests
appellent directement un adaptateur simulé. La recette Google réelle du
15 novembre 2026 a aussi réussi sur une feuille autorisée : vente client,
vente au guichet, remboursement sur la même ligne et relance sans doublon.
Résultat : neuf billets exportés, aucune attente ni erreur. Les contrôles
de panne restent des scénarios simulés ; les résultats effectivement observés
sont consignés dans [VALIDATION_FINALE.md](VALIDATION_FINALE.md).

Références officielles : [identifiants Google](https://developers.google.com/workspace/guides/create-credentials),
[activation de l’API](https://developers.google.com/workspace/guides/enable-apis),
[authentification serveur](https://developers.google.com/identity/protocols/oauth2/service-account),
[écriture d’une plage](https://developers.google.com/workspace/sheets/api/reference/rest/v4/spreadsheets.values/update).
