<?php
/**
 * Plugin Name: Wycan Freemius Stats
 * Plugin URI: https://wycan.com
 * Description: Display the number of active installations of your plugins via the Freemius API
 * Version: 1.1.0
 * Author: Wycan
 * Author URI: https://wycan.com
 * License: GPL v2 or later
 * Text Domain: wycan-freemius-stats
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

class Wycan_Freemius_Stats {
    
    private static $instance = null;
    private $api_base_url = 'https://api.freemius.com/v1';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_shortcode('freemius_installs', array($this, 'installs_shortcode'));
        add_action('elementor/widgets/register', array($this, 'register_elementor_widget'));
    }
    
    public function load_textdomain() {
        load_plugin_textdomain('wycan-freemius-stats', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function add_admin_menu() {
        add_options_page(
            __('Freemius Stats', 'wycan-freemius-stats'),
            __('Freemius Stats', 'wycan-freemius-stats'),
            'manage_options',
            'wycan-freemius-stats',
            array($this, 'settings_page')
        );
    }
    
    public function register_settings() {
        register_setting('wycan_freemius_stats', 'wycan_freemius_products');
        
        add_settings_section(
            'wycan_freemius_main',
            __('Freemius Products Configuration', 'wycan-freemius-stats'),
            array($this, 'settings_section_callback'),
            'wycan-freemius-stats'
        );
        
        add_settings_field(
            'wycan_freemius_products',
            __('Products', 'wycan-freemius-stats'),
            array($this, 'products_field_callback'),
            'wycan-freemius-stats',
            'wycan_freemius_main'
        );
    }
    
    public function settings_section_callback() {
        echo '<p>' . esc_html__('Configure your Freemius products with their ID and API token.', 'wycan-freemius-stats') . '</p>';
        echo '<p><strong>' . esc_html__('To get your API token:', 'wycan-freemius-stats') . '</strong></p>';
        echo '<ol>';
        echo '<li>' . sprintf(
            /* translators: %s: Freemius Dashboard link */
            __('Go to %s', 'wycan-freemius-stats'),
            '<a href="https://dashboard.freemius.com" target="_blank">' . esc_html__('Freemius Dashboard', 'wycan-freemius-stats') . '</a>'
        ) . '</li>';
        echo '<li>' . esc_html__('Open the Settings page of your product', 'wycan-freemius-stats') . '</li>';
        echo '<li>' . esc_html__('Click on the "API Token" tab', 'wycan-freemius-stats') . '</li>';
        echo '<li>' . esc_html__('Copy the "API Bearer Authorization Token"', 'wycan-freemius-stats') . '</li>';
        echo '</ol>';
    }
    
    public function products_field_callback() {
        $products = get_option('wycan_freemius_products', array());
        ?>
        <div id="freemius-products-container">
            <?php
            if (empty($products)) {
                $this->render_product_row(0, array());
            } else {
                foreach ($products as $index => $product) {
                    $this->render_product_row($index, $product);
                }
            }
            ?>
        </div>
        <button type="button" class="button" id="add-product"><?php echo esc_html__('Add Product', 'wycan-freemius-stats'); ?></button>
        
        <script>
        jQuery(document).ready(function($) {
            let productIndex = <?php echo count($products); ?>;
            
            $('#add-product').on('click', function() {
                const template = `
                    <div class="product-row" style="margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; background: #f9f9f9;">
                        <h4><?php echo esc_js(__('Product', 'wycan-freemius-stats')); ?> ${productIndex + 1}</h4>
                        <p>
                            <label><?php echo esc_js(__('Product slug (for shortcode):', 'wycan-freemius-stats')); ?></label><br>
                            <input type="text" name="wycan_freemius_products[${productIndex}][slug]" style="width: 100%;" placeholder="<?php echo esc_attr__('my-plugin', 'wycan-freemius-stats'); ?>" />
                        </p>
                        <p>
                            <label><?php echo esc_js(__('Product name:', 'wycan-freemius-stats')); ?></label><br>
                            <input type="text" name="wycan_freemius_products[${productIndex}][name]" style="width: 100%;" placeholder="<?php echo esc_attr__('My Plugin', 'wycan-freemius-stats'); ?>" />
                        </p>
                        <p>
                            <label><?php echo esc_js(__('Freemius Product ID:', 'wycan-freemius-stats')); ?></label><br>
                            <input type="text" name="wycan_freemius_products[${productIndex}][product_id]" style="width: 100%;" placeholder="12345" />
                        </p>
                        <p>
                            <label><?php echo esc_js(__('API Bearer Token:', 'wycan-freemius-stats')); ?></label><br>
                            <input type="text" name="wycan_freemius_products[${productIndex}][api_token]" style="width: 100%;" placeholder="<?php echo esc_attr__('Bearer token...', 'wycan-freemius-stats'); ?>" />
                        </p>
                        <button type="button" class="button remove-product"><?php echo esc_js(__('Remove', 'wycan-freemius-stats')); ?></button>
                    </div>
                `;
                $('#freemius-products-container').append(template);
                productIndex++;
            });
            
            $(document).on('click', '.remove-product', function() {
                $(this).closest('.product-row').remove();
            });
        });
        </script>
        <?php
    }
    
    private function render_product_row($index, $product) {
        $slug = isset($product['slug']) ? esc_attr($product['slug']) : '';
        $name = isset($product['name']) ? esc_attr($product['name']) : '';
        $product_id = isset($product['product_id']) ? esc_attr($product['product_id']) : '';
        $api_token = isset($product['api_token']) ? esc_attr($product['api_token']) : '';
        ?>
        <div class="product-row" style="margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; background: #f9f9f9;">
            <h4><?php printf(esc_html__('Product %d', 'wycan-freemius-stats'), $index + 1); ?></h4>
            <p>
                <label><?php esc_html_e('Product slug (for shortcode):', 'wycan-freemius-stats'); ?></label><br>
                <input type="text" name="wycan_freemius_products[<?php echo $index; ?>][slug]" value="<?php echo $slug; ?>" style="width: 100%;" placeholder="<?php echo esc_attr__('my-plugin', 'wycan-freemius-stats'); ?>" />
            </p>
            <p>
                <label><?php esc_html_e('Product name:', 'wycan-freemius-stats'); ?></label><br>
                <input type="text" name="wycan_freemius_products[<?php echo $index; ?>][name]" value="<?php echo $name; ?>" style="width: 100%;" placeholder="<?php echo esc_attr__('My Plugin', 'wycan-freemius-stats'); ?>" />
            </p>
            <p>
                <label><?php esc_html_e('Freemius Product ID:', 'wycan-freemius-stats'); ?></label><br>
                <input type="text" name="wycan_freemius_products[<?php echo $index; ?>][product_id]" value="<?php echo $product_id; ?>" style="width: 100%;" placeholder="12345" />
            </p>
            <p>
                <label><?php esc_html_e('API Bearer Token:', 'wycan-freemius-stats'); ?></label><br>
                <input type="text" name="wycan_freemius_products[<?php echo $index; ?>][api_token]" value="<?php echo $api_token; ?>" style="width: 100%;" placeholder="<?php echo esc_attr__('Bearer token...', 'wycan-freemius-stats'); ?>" />
            </p>
            <button type="button" class="button remove-product"><?php esc_html_e('Remove', 'wycan-freemius-stats'); ?></button>
        </div>
        <?php
    }
    
    public function settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        if (isset($_GET['settings-updated'])) {
            add_settings_error('wycan_freemius_messages', 'wycan_freemius_message', __('Settings saved', 'wycan-freemius-stats'), 'updated');
        }
        
        settings_errors('wycan_freemius_messages');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('wycan_freemius_stats');
                do_settings_sections('wycan-freemius-stats');
                submit_button(__('Save Settings', 'wycan-freemius-stats'));
                ?>
            </form>
            
            <hr>
            
            <h2><?php esc_html_e('Usage', 'wycan-freemius-stats'); ?></h2>
            <h3><?php esc_html_e('Shortcode', 'wycan-freemius-stats'); ?></h3>
            <p><strong><?php esc_html_e('Method 1: With ACF field (recommended for dynamic templates)', 'wycan-freemius-stats'); ?></strong></p>
            <p><?php esc_html_e('Create an ACF field (e.g., "freemius_product_slug") in your product pages, then:', 'wycan-freemius-stats'); ?></p>
            <code>[freemius_installs acf_field="freemius_product_slug"]</code>
            
            <p><strong><?php esc_html_e('Method 2: With fixed slug', 'wycan-freemius-stats'); ?></strong></p>
            <code>[freemius_installs slug="my-plugin"]</code>
            
            <h4><?php esc_html_e('Available parameters:', 'wycan-freemius-stats'); ?></h4>
            <ul>
                <li><strong>acf_field</strong>: <?php esc_html_e('ACF field name containing the product slug (priority)', 'wycan-freemius-stats'); ?></li>
                <li><strong>slug</strong>: <?php esc_html_e('Hard-coded product slug (if acf_field is not used)', 'wycan-freemius-stats'); ?></li>
                <li><strong>format</strong>: <?php esc_html_e('"number" (default) or "text" (e.g., "1,234 active installations")', 'wycan-freemius-stats'); ?></li>
                <li><strong>active_only</strong>: <?php esc_html_e('"true" (default) or "false" to include inactive installations', 'wycan-freemius-stats'); ?></li>
            </ul>
            
            <h3><?php esc_html_e('PHP Function', 'wycan-freemius-stats'); ?></h3>
            <p><?php esc_html_e('In your templates:', 'wycan-freemius-stats'); ?></p>
            <code>&lt;?php echo wycan_get_freemius_installs('my-plugin'); ?&gt;</code>
            
            <h3><?php esc_html_e('Elementor Widget', 'wycan-freemius-stats'); ?></h3>
            <p><?php esc_html_e('A "Freemius Install Count" widget is available in Elementor.', 'wycan-freemius-stats'); ?></p>
        </div>
        <?php
    }
    
    public function get_install_count($product_slug, $active_only = true) {
        $products = get_option('wycan_freemius_products', array());
        $product = null;
        
        foreach ($products as $p) {
            if (isset($p['slug']) && $p['slug'] === $product_slug) {
                $product = $p;
                break;
            }
        }
        
        if (!$product || empty($product['product_id']) || empty($product['api_token'])) {
            return false;
        }
        
        $transient_key = 'freemius_count_' . $product_slug . '_' . ($active_only ? 'active' : 'all');
        $cached_count = get_transient($transient_key);
        
        if (false !== $cached_count) {
            return $cached_count;
        }
        
        $url = $this->api_base_url . '/products/' . $product['product_id'] . '/installs/count.json';
        
        if ($active_only) {
            $url .= '?is_active=true';
        }
        
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $product['api_token']
            ),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['count'])) {
            set_transient($transient_key, $data['count'], HOUR_IN_SECONDS);
            return $data['count'];
        }
        
        return false;
    }
    
    private function get_product_slug_from_acf($field_name) {
        if (!function_exists('get_field')) {
            return false;
        }
        
        $slug = get_field($field_name);
        
        if (empty($slug)) {
            return false;
        }
        
        return $slug;
    }
    
    public function installs_shortcode($atts) {
        $atts = shortcode_atts(array(
            'slug' => '',
            'acf_field' => '',
            'format' => 'number',
            'active_only' => 'true'
        ), $atts);
        
        $product_slug = '';
        
        if (!empty($atts['acf_field'])) {
            $product_slug = $this->get_product_slug_from_acf($atts['acf_field']);
            
            if (false === $product_slug) {
                return '<span class="freemius-error">' . sprintf(
                    /* translators: %s: ACF field name */
                    esc_html__('Error: Unable to retrieve ACF field "%s"', 'wycan-freemius-stats'),
                    esc_html($atts['acf_field'])
                ) . '</span>';
            }
        } elseif (!empty($atts['slug'])) {
            $product_slug = $atts['slug'];
        } else {
            return '<span class="freemius-error">' . esc_html__('Error: You must specify either "slug" or "acf_field"', 'wycan-freemius-stats') . '</span>';
        }
        
        $active_only = $atts['active_only'] === 'true';
        $count = $this->get_install_count($product_slug, $active_only);
        
        if (false === $count) {
            return '<span class="freemius-error">' . sprintf(
                /* translators: %s: product slug */
                esc_html__('Error: Unable to retrieve data for "%s"', 'wycan-freemius-stats'),
                esc_html($product_slug)
            ) . '</span>';
        }
        
        if ($atts['format'] === 'text') {
            return '<span class="freemius-install-count">' . sprintf(
                /* translators: %s: number of installations */
                _n('%s active installation', '%s active installations', $count, 'wycan-freemius-stats'),
                number_format_i18n($count)
            ) . '</span>';
        }
        
        return '<span class="freemius-install-count">' . number_format_i18n($count) . '</span>';
    }
    
    public function register_elementor_widget() {
        if (did_action('elementor/loaded')) {
            require_once plugin_dir_path(__FILE__) . 'includes/elementor-widget.php';
            \Elementor\Plugin::instance()->widgets_manager->register(new \Wycan_Freemius_Stats_Widget());
        }
    }
}

function wycan_get_freemius_installs($product_slug, $active_only = true) {
    $instance = Wycan_Freemius_Stats::get_instance();
    return $instance->get_install_count($product_slug, $active_only);
}

Wycan_Freemius_Stats::get_instance();
