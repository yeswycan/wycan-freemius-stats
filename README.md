# Wycan Freemius Stats

WordPress plugin to display the number of active installations of your plugins via the Freemius API.

**Languages:** English (default), French (included)

## Installation

1. Download the `wycan-plugins-stats` folder
2. Place it in `/wp-content/plugins/`
3. Activate the plugin in WordPress
4. Go to **Settings > Freemius Stats** to configure your products

## Configuration

### Get your Freemius API Token

1. Log in to [Freemius Dashboard](https://dashboard.freemius.com)
2. Select your product
3. Go to **Settings**
4. Click on the **API Token** tab
5. Copy the **API Bearer Authorization Token**

### Configure your products

In **Settings > Freemius Stats**, add your products with:
- **Slug**: Unique identifier for the shortcode (e.g., `my-plugin`)
- **Name**: Product display name
- **Product ID**: Product ID in Freemius
- **API Token**: The Bearer token copied previously

## Usage

### 1. Shortcode (ACF / Elementor)

#### Method 1: With ACF field (recommended for dynamic templates)

**This method is ideal if you have a single template that generates all your product pages.**

1. Create an ACF field in your field group (e.g., `freemius_product_slug`)
2. Fill in the Freemius product slug in this field for each product page
3. Use the shortcode in your template:

```
[freemius_installs acf_field="freemius_product_slug"]
```

The plugin will automatically retrieve the slug from the ACF field of the current page.

#### Method 2: With fixed slug

For a specific page with a fixed product:

```
[freemius_installs slug="my-plugin"]
```

#### With options
```
[freemius_installs acf_field="freemius_product_slug" format="text" active_only="true"]
```

#### Available parameters

- **acf_field**: The ACF field name containing the product slug (takes priority over `slug`)
- **slug**: The hard-coded product slug (used if `acf_field` is not specified)
- **format**: 
  - `number` (default): Displays only the number (e.g., "1,234")
  - `text`: Displays the full text (e.g., "1,234 active installations")
- **active_only**:
  - `true` (default): Counts only active installations
  - `false`: Counts all installations

### 2. PHP Function

In your WordPress templates:

```php
<?php
$count = wycan_get_freemius_installs('my-plugin');
if ($count !== false) {
    echo number_format_i18n($count) . ' active installations';
}
?>
```

With parameter to include inactive installations:

```php
<?php
$count = wycan_get_freemius_installs('my-plugin', false);
?>
```

### 3. Elementor Widget

1. Edit your page with Elementor
2. Search for the **"Freemius Install Count"** widget
3. Drag and drop the widget into your page
4. Configure:
   - **Product source**:
     - **ACF field (dynamic)**: Retrieves the slug from an ACF field (ideal for templates)
     - **Fixed product**: Select a specific product
   - If you choose "ACF field", enter the field name (e.g., `freemius_product_slug`)
   - Choose the display format
   - Add text before/after (optional)
   - Customize the style (color, typography, alignment)

## Usage Examples

### Dynamic template with ACF (main use case)

**Scenario**: You have a Custom Post Type "Plugins" with a single template that displays all your product pages.

1. **Create an ACF field** in your field group:
   - Field name: `freemius_product_slug`
   - Type: Text
   - Associated with your "Plugins" CPT

2. **In each product page**, fill in the corresponding Freemius slug (e.g., `wycan-seo-plugin`)

3. **In your Elementor template** (or PHP template), add:
   - **With Elementor widget**: Add the "Freemius Install Count" widget, choose "ACF field (dynamic)" and enter `freemius_product_slug`
   - **With shortcode**: `[freemius_installs acf_field="freemius_product_slug"]`

The installation count will automatically display for each product!

### In a custom PHP template

```php
<div class="plugin-stats">
    <h3>Number of users</h3>
    <p class="install-count">
        <?php echo do_shortcode('[freemius_installs acf_field="freemius_product_slug"]'); ?>
    </p>
</div>
```

### In Elementor with fixed slug

1. Add a **Shortcode** or **HTML** widget
2. Insert: `[freemius_installs slug="my-plugin"]`
3. Or use the dedicated **Freemius Install Count** widget in "Fixed product" mode

### Conditional display

```php
<?php
$count = wycan_get_freemius_installs('my-plugin');
if ($count !== false && $count > 1000) {
    echo 'More than ' . number_format_i18n($count) . ' satisfied users!';
}
?>
```

## Cache

Data is cached for **1 hour** to optimize performance and avoid overloading the Freemius API.

To force cache refresh, you can use:

```php
delete_transient('freemius_count_my-plugin_active');
```

## Troubleshooting

### The count doesn't display

1. Check that the product slug is correct
2. Check that the Product ID and API Token are correct in the settings
3. Check WordPress error logs
4. Test the API manually with curl:

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://api.freemius.com/v1/products/YOUR_PRODUCT_ID/installs/count.json?is_active=true"
```

### Error message "Unable to retrieve data"

- Check your internet connection
- Check that the API Token is valid
- Check that the Product ID is correct

## Freemius API

This plugin uses the following Freemius API endpoint:

```
GET /v1/products/{product_id}/installs/count.json
```

Documentation: [https://docs.freemius.com/api/installations](https://docs.freemius.com/api/installations)

## Translations

The plugin is fully translatable and includes:
- **English** (default)
- **French** (included)

To compile the French translation:
1. Install [Poedit](https://poedit.net/) (free)
2. Open `languages/wycan-freemius-stats-fr_FR.po`
3. Save the file (Cmd/Ctrl+S) to generate the `.mo` file

Other languages can be added by translating the `.po` file.


## License

GPL v2 or later
