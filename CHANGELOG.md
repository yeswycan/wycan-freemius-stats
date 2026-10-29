# Journal des modifications

## 2.0.0 (2026-10-29)

Reconstruction complète sous le même slug, le même shortcode, le même widget et la même option. Le HTML public du shortcode `[freemius_installs]` et du widget est identique à la 1.1.0 (vérifié par 78 instantanés).

### Ajouts

- Shortcode `[freemius_version]` : dernière version publiée d'un produit, sa date, le WordPress minimal et testé.
- Page de réglages en trame Wycan : une carte par produit avec état, installations actives et totales, version et date de synchronisation ; test de connexion avant enregistrement ; ajout d'un produit en deux champs, nom et slug remplis depuis Freemius ; section Utilisation avec les shortcodes prêts à copier.
- Valeurs durables et synchronisation en arrière-plan : les pages n'appellent plus Freemius ; tâche horaire, rafraîchissement programmé au delà d'une heure, synchronisation immédiate à l'enregistrement, cache négatif de 5 minutes.
- Jetons chiffrés en base, jamais renvoyés au navigateur, conservés si le champ est laissé vide, case de suppression, constante `WFS_TOKEN_{id}` facultative.
- Validation stricte à l'enregistrement : slug unique, identifiant entier positif, lignes vides ignorées, ordre conservé.
- Migration automatique et idempotente depuis la 1.x.
- Traduction française opérationnelle (`.po` réparé, `.mo` livré), fichier `.pot`.
- `uninstall.php`, `.distignore`, `build.sh`, en-tête complet (planchers WordPress, PHP, Elementor, `Update URI`).

### Modifications

- Les transitoires `freemius_count_*` sont abandonnés au profit de l'option `wycan_freemius_counts`.
- L'option `wycan_freemius_products` n'est plus chargée automatiquement ; une option corrompue est ramenée à une liste vide.
- Une panne Freemius affiche la dernière valeur connue au lieu d'un message d'erreur.
- README en français.

### Compatibilité

- Retour arrière vers la 1.x possible, en ressaisissant les jetons (chiffrés par la 2.0.0).

## 1.1.0 (2026-03-06)

- Shortcode `[freemius_installs]`, widget Elementor « Freemius Install Count », fonction `wycan_get_freemius_installs()`, cache d'une heure par transitoire.
