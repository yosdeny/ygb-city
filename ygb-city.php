<?php
/**
 * Plugin Name: YGB City Shipping
 * Plugin URI: https://tusitio.com
 * Description: Sistema de envío por provincia y municipio con costos personalizados
 * Version: 2.5.0
 * Author: YGB
 * Author URI: https://tusitio.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ygb-city
 * Domain Path: /languages
 * Requires at least: 7.0
 * Requires PHP: 8.0
 * WC requires at least: 5.0
 * WC tested up to: 8.5
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit; // Salir si se accede directamente
}

// Definir constantes de seguridad
define('YGB_CITY_VERSION', '2.5.0');
define('YGB_CITY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('YGB_CITY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('YGB_CITY_FILE', __FILE__);
define('YGB_CITY_BASENAME', plugin_basename(__FILE__));

/**
 * Verificar que WooCommerce está activo
 *
 * @return bool
 */
function ygb_city_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'ygb_city_missing_woocommerce_notice');
        return false;
    }
    return true;
}

/**
 * Mostrar aviso de WooCommerce faltante
 *
 * @return void
 */
function ygb_city_missing_woocommerce_notice() {
    ?>
    <div class="error notice is-dismissible">
        <p>
            <strong><?php echo esc_html__('YGB City Shipping', 'ygb-city'); ?></strong>
            <?php echo esc_html__('requiere que WooCommerce esté instalado y activado.', 'ygb-city'); ?>
        </p>
    </div>
    <?php
}

/**
 * Inicializar plugin con verificación de seguridad
 *
 * @return void
 */
function ygb_city_init() {
    if (!ygb_city_check_woocommerce()) {
        return;
    }
    
    // Cargar archivos necesarios con verificación de existencia
    $required_files = array(
        'includes/class-ygb-city-database.php',
        'includes/class-ygb-city-admin.php',
        'includes/class-ygb-city-frontend.php'
    );
    
    foreach ($required_files as $file) {
        $file_path = YGB_CITY_PLUGIN_DIR . $file;
        if (file_exists($file_path)) {
            require_once $file_path;
        } else {
            add_action('admin_notices', function() use ($file) {
                echo '<div class="error"><p><strong>YGB City Shipping</strong> Error: Archivo requerido no encontrado: ' . esc_html($file) . '</p></div>';
            });
            return;
        }
    }
    
    // Instanciar clases solo si existen
    if (class_exists('YGB_City_Database')) {
        new YGB_City_Database();
    }
    if (class_exists('YGB_City_Admin')) {
        new YGB_City_Admin();
    }
    if (class_exists('YGB_City_Frontend')) {
        new YGB_City_Frontend();
    }
    
    // Cargar traducciones
    load_plugin_textdomain('ygb-city', false, dirname(YGB_CITY_BASENAME) . '/languages');
}

add_action('plugins_loaded', 'ygb_city_init');

/**
 * Activar plugin con manejo de errores
 *
 * @return void
 */
function ygb_city_activate() {
    // Verificar WooCommerce antes de activar
    if (!class_exists('WooCommerce')) {
        deactivate_plugins(YGB_CITY_BASENAME);
        wp_die(
            esc_html__('YGB City Shipping requiere WooCommerce. Por favor, instala y activa WooCommerce primero.', 'ygb-city'),
            esc_html__('Error de Activación', 'ygb-city'),
            array('response' => 400, 'back_link' => true)
        );
    }
    
    require_once YGB_CITY_PLUGIN_DIR . 'includes/class-ygb-city-database.php';
    
    if (class_exists('YGB_City_Database')) {
        $result = YGB_City_Database::create_tables();
        if (!$result) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('YGB City: Error al crear tablas en la base de datos');
            }
            set_transient('ygb_city_activation_error', 'Error al crear las tablas en la base de datos', 60);
        }
    }
    
    // Opciones por defecto con validación
    if (get_option('ygb_city_enabled') === false) {
        add_option('ygb_city_enabled', 'yes');
    }
    if (get_option('ygb_city_default_cost') === false) {
        add_option('ygb_city_default_cost', '5.00');
    }
    add_option('ygb_city_version', YGB_CITY_VERSION);
    
    // Limpiar caché de permalinks
    flush_rewrite_rules();
    
    // Registrar log de activación
    if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        error_log('YGB City Shipping: Plugin activado versión ' . YGB_CITY_VERSION);
    }
}

register_activation_hook(__FILE__, 'ygb_city_activate');

/**
 * Desactivar plugin
 *
 * @return void
 */
function ygb_city_deactivate() {
    // Limpiar datos de sesión de WooCommerce si existen
    if (function_exists('WC') && WC()->session) {
        WC()->session->__unset('ygb_selected_city');
    }
    
    // Limpiar caché
    wp_cache_flush();
    flush_rewrite_rules();
    
    // Registrar log de desactivación (sin eliminar datos)
    if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        error_log('YGB City Shipping: Plugin desactivado - Los datos permanecen intactos');
    }
}

register_deactivation_hook(__FILE__, 'ygb_city_deactivate');

/**
 * Mostrar errores de activación si existen
 *
 * @return void
 */
function ygb_city_activation_notice() {
    $error = get_transient('ygb_city_activation_error');
    if ($error) {
        echo '<div class="error"><p><strong>YGB City Shipping:</strong> ' . esc_html($error) . '</p></div>';
        delete_transient('ygb_city_activation_error');
    }
}

add_action('admin_notices', 'ygb_city_activation_notice');

/**
 * Enlazar a página de ajustes desde plugins list
 *
 * @param array $links Enlaces existentes
 * @return array
 */
function ygb_city_action_links($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=ygb-city') . '">' . esc_html__('Ajustes', 'ygb-city') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}

add_filter('plugin_action_links_' . YGB_CITY_BASENAME, 'ygb_city_action_links');

/**
 * Declarar compatibilidad con High Performance Order Storage (HPOS) de WooCommerce
 *
 * @return void
 */
function ygb_city_declare_hpos_compatibility() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
}

add_action('before_woocommerce_init', 'ygb_city_declare_hpos_compatibility');