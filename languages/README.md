# Translations / Traductions

This folder contains translation files for the Wycan Freemius Stats plugin.

## Available Languages / Langues disponibles

- **English (en_US)**: Default language / Langue par défaut
- **French (fr_FR)**: Included / Inclus

## How to compile translations / Comment compiler les traductions

To compile the `.po` file into a `.mo` file (required by WordPress), you can use one of these methods:

### Method 1: Using Poedit (Recommended)

1. Download and install [Poedit](https://poedit.net/)
2. Open the `.po` file in Poedit
3. Save the file (this will automatically generate the `.mo` file)

### Method 2: Using msgfmt command line

```bash
msgfmt wycan-freemius-stats-fr_FR.po -o wycan-freemius-stats-fr_FR.mo
```

### Method 3: Using WP-CLI

```bash
wp i18n make-mo languages/
```

## Adding new translations / Ajouter de nouvelles traductions

1. Copy `wycan-freemius-stats-fr_FR.po` to `wycan-freemius-stats-{locale}.po`
2. Translate all strings in the new file
3. Compile to `.mo` using one of the methods above
4. Upload both `.po` and `.mo` files to the `languages/` folder

## File structure / Structure des fichiers

- `wycan-freemius-stats-fr_FR.po`: French translation source file
- `wycan-freemius-stats-fr_FR.mo`: French compiled translation (generated)
- `wycan-freemius-stats.pot`: Template file for creating new translations (optional)

## Notes

The plugin will automatically load the correct translation based on your WordPress language settings (Settings > General > Site Language).
