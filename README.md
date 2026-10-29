# Wycan Freemius Stats

Extension WordPress interne de Wycan. Elle affiche sur wycan.fr le nombre d'installations et la dernière version publiée de nos plugins, lues depuis l'API Freemius : shortcodes `[freemius_installs]` et `[freemius_version]`, widget Elementor « Freemius Install Count », fonction PHP `wycan_get_freemius_installs()`.

Textes de l'administration en anglais avec traduction française incluse. Les textes publics des compteurs (`format="text"`, messages d'erreur) restent volontairement en anglais : ils sont identiques à la version 1.x pour ne pas modifier les pages existantes.

## Prérequis

- WordPress : voir `Requires at least` dans l'en-tête du plugin (version testée : celle de wycan.fr).
- PHP : voir `Requires PHP` (le code utilise la syntaxe de cette version).
- Elementor pour le widget (version testée dans `Elementor tested up to`), ACF pour la lecture d'un slug par champ. Ni l'un ni l'autre n'est obligatoire pour les shortcodes avec `slug`.
- Extension `sodium` (native ou polyfill fourni par WordPress) pour le chiffrement des jetons.

## Installation et mise à jour

1. Télécharger l'archive de la release GitHub (jamais le « Download ZIP » du dépôt).
2. Déposer le dossier dans `wp-content/plugins/` **en remplaçant le dossier existant, sous le même nom** (sur wycan.fr : `wycan-plugins-stats`). Deux copies actives déclareraient les mêmes classes et provoqueraient une erreur fatale.
3. Activer l'extension, puis ouvrir Réglages > Freemius Stats.

À la première exécution de la 2.0.0, les jetons enregistrés par la 1.x sont chiffrés automatiquement, les lignes vides retirées, l'option sortie du chargement automatique et les anciens transitoires supprimés. La migration est idempotente.

Retour arrière vers la 1.x : possible, mais les jetons chiffrés ne seront pas lisibles par l'ancienne version. Il faut alors les ressaisir.

## Configuration

Réglages > Freemius Stats > Produits.

1. **Ajouter un produit**, coller l'identifiant Freemius et le jeton API du produit.
   - Identifiant : tableau de bord Freemius, produit, Settings (en haut de la page).
   - Jeton : tableau de bord Freemius, produit, Settings, onglet « API & Keys », « API Bearer Authorization Token ».
2. **Tester la connexion** : le nom et le slug sont remplis depuis Freemius, le nombre d'installations s'affiche. Les erreurs sont distinguées : jeton refusé, produit introuvable, API injoignable, réponse invalide.
3. **Enregistrer** : les valeurs sont synchronisées immédiatement et la carte affiche l'état du produit.

Chaque carte montre l'état (Connecté, Jamais synchronisé, Jeton manquant, Jeton à ressaisir, Jeton refusé, Produit introuvable, API injoignable), les installations actives et totales, la dernière version et la date de synchronisation.

**Ordre des produits.** Le premier produit de la liste est le produit par défaut des widgets Elementor en mode « Produit fixe » qui n'ont jamais reçu de choix explicite. Le retirer déclenche un avertissement. Les produits ne se réordonnent pas.

**Slug.** Le slug sert aux shortcodes et au champ ACF. Le changer casse les pages qui utilisent l'ancien.

## Sécurité des jetons

Un jeton de produit Freemius donne aussi des droits d'écriture sur ce produit (abonnements, clients, paiements). Le plugin le traite comme un mot de passe :

- chiffré en base (libsodium secretbox) avec une clé dérivée des clés de sécurité `AUTH_KEY` et `SECURE_AUTH_KEY` de `wp-config.php` ;
- jamais renvoyé au navigateur : le champ est masqué, vide à l'affichage, et seuls les quatre derniers caractères sont indiqués ;
- champ laissé vide = jeton conservé ; case « Supprimer le jeton enregistré » pour l'effacer ;
- option `wycan_freemius_products` hors chargement automatique.

Limites : un export de la base seul ne révèle pas les jetons ; une copie de la base **et** de `wp-config.php` les révèle. Si les clés de sécurité sont régénérées, les jetons passent à l'état « Jeton à ressaisir » : les dernières valeurs connues restent affichées, il suffit de recoller les jetons.

Alternative : définir une constante par produit dans `wp-config.php`, prioritaire sur le jeton enregistré :

```php
define( 'WFS_TOKEN_12345', 'jeton du produit 12345' );
```

## Utilisation

L'onglet Utilisation de la page de réglages reprend tout ceci avec les shortcodes prêts à copier pour chaque produit.

### Shortcode `[freemius_installs]`

| Attribut | Rôle | Défaut |
| --- | --- | --- |
| `acf_field` | nom du champ ACF de la page courante qui contient le slug du produit ; prioritaire sur `slug` | |
| `slug` | slug du produit | |
| `format` | `number` (nombre seul) ou `text` (« 1 234 active installations ») | `number` |
| `active_only` | `true` (installations actives) ou `false` (toutes) ; toute valeur autre que `true` vaut `false` | `true` |

```
[freemius_installs slug="mon-plugin"]
[freemius_installs acf_field="freemius_product_slug" format="text" active_only="false"]
```

Rendu : `<span class="freemius-install-count">1 234</span>`. En cas d'erreur : `<span class="freemius-error">Error: …</span>`, visible par tous (comportement figé de la 1.x).

### Shortcode `[freemius_version]`

| Attribut | Rôle | Défaut |
| --- | --- | --- |
| `acf_field` | même règle que ci-dessus | |
| `slug` | slug du produit | |
| `field` | `version`, `date` (format de date du site), `requires` (WordPress minimal), `tested` (WordPress testé) | `version` |

```
[freemius_version slug="mon-plugin"]
[freemius_version acf_field="freemius_product_slug" field="date"]
```

Seules les versions publiées sont lues, jamais les bêtas. Rendu : `<span class="freemius-version">1.4.2</span>`. En cas d'erreur, les visiteurs ne voient rien ; les utilisateurs capables d'éditer voient la raison. Utilisable dans le widget « Éditeur de texte » d'Elementor.

### Widget Elementor « Freemius Install Count »

Catégorie Général. Source : champ ACF (dynamique, par défaut, champ `freemius_product_slug`) ou produit fixe. Format nombre ou texte, installations actives seulement (par défaut oui), texte avant et après, couleur, typographie, alignement.

Rendu : `<div class="freemius-install-count-wrapper"><span class="prefix">Plus de </span><span class="freemius-install-count">1 234</span><span class="suffix"> utilisateurs</span></div>`.

### Fonction PHP

```php
$count = wycan_get_freemius_installs( 'mon-plugin' );        // actives
$count = wycan_get_freemius_installs( 'mon-plugin', false ); // toutes
if ( false !== $count ) {
	echo number_format_i18n( $count );
}
```

La classe `Wycan_Freemius_Stats` de la 1.x reste disponible (`Wycan_Freemius_Stats::get_instance()->get_install_count( $slug, $active_only )`).

## Rafraîchissement des valeurs

- Les pages publiques n'appellent jamais Freemius : elles lisent la dernière valeur connue, stockée dans l'option `wycan_freemius_counts`.
- Une tâche WP-Cron horaire (`wfs_sync_all`) resynchronise tous les produits : installations actives, totales et dernière version. Une valeur servie alors qu'elle a plus d'une heure déclenche en plus un rafraîchissement en arrière-plan, sans attente pour la page.
- L'enregistrement d'un produit nouveau ou dont le jeton change le synchronise immédiatement.
- Premier affichage d'un produit sans aucune valeur : un appel synchrone de 5 secondes au plus, puis 5 minutes sans nouvel essai en cas d'échec.
- En cas de panne Freemius, la dernière valeur reste affichée et l'erreur apparaît sur la carte du produit.

Avec un cache de pages (LiteSpeed), le compteur affiché suit le cycle de ce cache : un décalage de quelques heures est sans conséquence.

## Dépannage

| Symptôme | Cause probable | Action |
| --- | --- | --- |
| `Error: Unable to retrieve data for "x"` | slug inconnu, identifiant manquant, premier affichage sans valeur et API en panne | vérifier la carte du produit, tester la connexion |
| État « Jeton refusé » | jeton d'un autre produit, ou révoqué | recoller le jeton du bon produit |
| État « Produit introuvable » | identifiant erroné | corriger l'identifiant |
| État « Jeton à ressaisir » | clés de sécurité WordPress changées | recoller le jeton, ou définir la constante `WFS_TOKEN_{id}` |
| Valeurs qui ne bougent pas | WP-Cron inactif (`DISABLE_WP_CRON`) sans cron système | configurer un cron système sur `wp-cron.php`, ou enregistrer la page Produits pour forcer une synchronisation |
| Widget qui affiche le mauvais produit | premier produit de la liste supprimé | choisir explicitement le produit dans le widget |

## Désinstallation

La suppression de l'extension efface les options `wycan_freemius_products`, `wycan_freemius_counts` et `wycan_freemius_stats_version`, les anciens transitoires `freemius_count_*` et les tâches planifiées. Une constante `WFS_TOKEN_*` dans `wp-config.php` reste à retirer à la main.

## Développement

```
wycan-freemius-stats.php   en-tête, constantes, chargeur PSR-4, amorçage, façade 1.x
uninstall.php
src/                       espace de noms Wycan\FreemiusStats\
  Admin/                   page de réglages (trame Wycan), test de connexion
  Api/                     client Freemius
  Counts/                  valeurs durables, synchronisation, planification
  Front/                   shortcodes et widget (rendu figé)
  Products/                option produits et nettoyage
  Security/                chiffrement et résolution des jetons
  Upgrade/                 migrations versionnées
assets/admin/              CSS et JS de la page de réglages, sans build
assets/fonts/Satoshi/      police servie par le plugin
languages/                 .pot, .po et .mo français
tests/                     instantanés HTML et scripts de test (exclus de l'archive)
```

Traductions : `.pot` généré par `wp i18n make-pot`, `.mo` compilé par `msgfmt` (`build.sh` le recompile).

Tests : `tests/capture-snapshots.php` capture le HTML du front pour toutes les combinaisons du shortcode et du widget, avec des réponses Freemius simulées ; `tests/snapshots/v1.1.0/` contient la référence capturée sur la 1.1.0 et `tests/compare-snapshots.sh` compare. `tests/test-migration.php` et `tests/test-sync.php` couvrent la migration, le nettoyage de l'option, les jetons, la synchronisation et le shortcode de version. Aucun jeton réel n'est utilisé.

Archive : `./build.sh [nom-du-dossier]` produit `dist/<dossier>-<version>.zip` (défaut : `wycan-plugins-stats`, le dossier installé sur wycan.fr).

## Support

Pour toute question, contacter Wycan.

## Licence

GPL v2 ou ultérieure.
