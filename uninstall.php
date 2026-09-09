<?php
/**
 * YGB City Shipping - Uninstall
 * 
 * Limpieza completa de datos al desinstalar el plugin
 * 
 * @package YGB_City
 * @version 2.4.8
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Verificar si el usuario confirmó la eliminación de datos
// Por defecto, NO eliminamos datos automáticamente para prevenir pérdida accidental.
// Opción 1: Constante en wp-config.php
// Opción 2: Opción de base de datos (para poder cambiarlo desde el admin)
$should_cleanup = defined('YGB_CITY_CLEANUP_ON_UNINSTALL') && YGB_CITY_CLEANUP_ON_UNINSTALL === true;

// Si no está definida la constante, comprobamos la opción de base de datos
if (!$should_cleanup) {
    $should_cleanup = (bool) get_option('ygb_city_cleanup_on_uninstall', false);
}

if (!$should_cleanup) {
    // Salir sin eliminar nada
    return;
}

global $wpdb;

// Eliminar tablas del plugin
$tables = array(
    $wpdb->prefix . 'ygb_provinces',
    $wpdb->prefix . 'ygb_cities',
    $wpdb->prefix . 'ygb_activity_logs'
);

foreach ($tables as $table) {
    $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $table));
}

// Eliminar opciones del plugin
$options = array(
    'ygb_city_enabled',
    'ygb_city_default_cost',
    'ygb_city_version',
    'ygb_city_activity_log',
    'ygb_city_cleanup_on_uninstall' // Eliminamos también nuestra propia opción
);

foreach ($options as $option) {
    delete_option($option);
}

// Eliminar transients
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
        '_transient_ygb_city_%'
    )
);
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
        '_transient_timeout_ygb_city_%'
    )
);

// Limpiar caché
if (function_exists('wc_delete_product_transients')) {
    wc_delete_product_transients();
}
if (function_exists('wc_delete_shop_order_transients')) {
    wc_delete_shop_order_transients();
}
wp_cache_flush();

if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
    error_log('YGB City Shipping: Plugin desinstalado y datos limpiados (v2.4.8)');
}