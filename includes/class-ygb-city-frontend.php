<?php
/**
 * YGB City Frontend Class - Versión 2.4.0 con rate limiting mejorado
 * @version 2.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class YGB_City_Frontend {
    
    private $max_ajax_attempts = 10;
    private $ajax_timeout = 60;
    
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('woocommerce_checkout_fields', array($this, 'modify_checkout_fields'));
        add_action('woocommerce_cart_calculate_fees', array($this, 'add_shipping_fee'));
        add_action('wp_ajax_update_shipping_cost', array($this, 'ajax_update_shipping_cost'));
        add_action('wp_ajax_nopriv_update_shipping_cost', array($this, 'ajax_update_shipping_cost'));
        
        add_action('wp_loaded', array($this, 'save_city_selection'));
        
        add_action('woocommerce_checkout_create_order', array($this, 'save_city_to_order'), 10, 2);
        
        add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'display_city_in_admin_order'), 10, 1);
        
        add_filter('woocommerce_order_formatted_billing_address', array($this, 'add_city_to_formatted_address'), 10, 2);
        add_filter('woocommerce_order_formatted_shipping_address', array($this, 'add_city_to_formatted_address'), 10, 2);
        
        add_action('woocommerce_order_details_after_order_table', array($this, 'display_city_in_order_details'), 10, 1);
        
        add_action('woocommerce_checkout_update_order_meta', array($this, 'save_city_meta'), 10, 2);
        
        add_action('init', array($this, 'init_woocommerce_session'));
        
        // Excluir página de checkout de caché (compatibilidad con plugins de caché)
        add_filter('nocache_headers', array($this, 'add_cache_headers'));
        add_action('wp_head', array($this, 'add_no_cache_meta'));
    }
    
    public function init_woocommerce_session() {
        if (function_exists('WC') && WC()->session && !WC()->session->has_session()) {
            WC()->session->set_customer_session_cookie(true);
        }
    }
    
    /**
     * Obtener IP real del cliente (con soporte para proxy/CDN)
     */
    private function get_client_ip() {
        return YGB_City_Database::get_client_ip();
    }
    
    /**
     * Rate limiting mejorado con IP real
     */
    private function check_rate_limit() {
        $ip = $this->get_client_ip();
        $transient_key = 'ygb_city_ajax_limit_' . md5($ip);
        $attempts = get_transient($transient_key);
        
        if ($attempts !== false && $attempts >= $this->max_ajax_attempts) {
            return false;
        }
        
        return true;
    }
    
    private function increment_rate_limit() {
        $ip = $this->get_client_ip();
        $transient_key = 'ygb_city_ajax_limit_' . md5($ip);
        $attempts = get_transient($transient_key);
        
        if ($attempts === false) {
            set_transient($transient_key, 1, $this->ajax_timeout);
        } else {
            set_transient($transient_key, $attempts + 1, $this->ajax_timeout);
        }
    }
    
    /**
     * Añadir headers para evitar caché en checkout
     */
    public function add_cache_headers($headers) {
        if (is_checkout()) {
            $headers['Cache-Control'] = 'no-cache, no-store, must-revalidate, max-age=0';
            $headers['Pragma'] = 'no-cache';
            $headers['Expires'] = 'Wed, 11 Jan 1984 05:00:00 GMT';
        }
        return $headers;
    }
    
    /**
     * Añadir meta tag para evitar caché en checkout
     */
    public function add_no_cache_meta() {
        if (is_checkout()) {
            echo '<meta name="robots" content="noindex, nofollow">' . "\n";
            echo '<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">' . "\n";
            echo '<meta http-equiv="Pragma" content="no-cache">' . "\n";
            echo '<meta http-equiv="Expires" content="0">' . "\n";
        }
    }
    
    public function enqueue_frontend_scripts() {
        if (is_checkout()) {
            wp_enqueue_script('ygb-city-checkout', YGB_CITY_PLUGIN_URL . 'assets/js/ygb-city-checkout.js', array('jquery'), YGB_CITY_VERSION, true);
            
            $checkout_nonce = wp_create_nonce('ygb_checkout_save_city');
            
            wp_localize_script('ygb-city-checkout', 'ygb_frontend', array(
                'ajax_url' => esc_url_raw(admin_url('admin-ajax.php')),
                'nonce' => wp_create_nonce('ygb_frontend_nonce'),
                'checkout_nonce' => $checkout_nonce,
                'provinces' => $this->get_provinces_json_public(),
                'select_city' => esc_html__('Selecciona un municipio', 'ygb-city'),
                'no_cities' => esc_html__('No hay municipios disponibles', 'ygb-city'),
                'select_province_error' => esc_html__('Por favor, selecciona una provincia', 'ygb-city'),
                'select_city_error' => esc_html__('Por favor, selecciona un municipio', 'ygb-city')
            ));
        }
    }
    
    /**
     * Obtener JSON de provincias SOLO con datos públicos (sin costos)
     */
    private function get_provinces_json_public() {
        $provinces = YGB_City_Database::get_provinces();
        $data = array();
        
        foreach ($provinces as $province) {
            $cities = YGB_City_Database::get_cities_by_province_public($province->id);
            $data[] = array(
                'id' => $province->id,
                'name' => $province->name,
                'code' => $province->code,
                'cities' => $cities
            );
        }
        
        return wp_json_encode($data);
    }
    
    public function modify_checkout_fields($fields) {
        if (get_option('ygb_city_enabled', 'yes') !== 'yes') {
            return $fields;
        }
        
        if (isset($fields['billing']['billing_city'])) {
            $fields['billing']['billing_city']['type'] = 'hidden';
            $fields['billing']['billing_city']['required'] = false;
            $fields['billing']['billing_city']['class'] = array('hidden');
        }
        
        if (isset($fields['billing']['billing_state'])) {
            $fields['billing']['billing_state']['type'] = 'hidden';
            $fields['billing']['billing_state']['required'] = false;
            $fields['billing']['billing_state']['class'] = array('hidden');
        }
        
        $fields['billing']['ygb_city_nonce'] = array(
            'type' => 'hidden',
            'default' => wp_create_nonce('ygb_checkout_save_city'),
            'required' => false,
            'priority' => 1
        );
        
        $fields['billing']['ygb_province'] = array(
            'type' => 'select',
            'label' => esc_html__('Provincia', 'ygb-city'),
            'required' => true,
            'class' => array('form-row-wide'),
            'options' => array('' => esc_html__('Selecciona una provincia', 'ygb-city')) + $this->get_provinces_options(),
            'priority' => 95,
            'clear' => true
        );
        
        $fields['billing']['ygb_city'] = array(
            'type' => 'select',
            'label' => esc_html__('Municipio', 'ygb-city'),
            'required' => true,
            'class' => array('form-row-wide'),
            'options' => array('' => esc_html__('Primero selecciona una provincia', 'ygb-city')),
            'priority' => 96,
            'clear' => true
        );
        
        return $fields;
    }
    
    private function get_provinces_options() {
        $provinces = YGB_City_Database::get_provinces();
        $options = array();
        
        foreach ($provinces as $province) {
            $options[$province->id] = esc_html($province->name);
        }
        
        return $options;
    }
    
    public function add_shipping_fee($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        
        if (get_option('ygb_city_enabled', 'yes') !== 'yes') {
            return;
        }
        
        $city_id = null;
        if (function_exists('WC') && WC()->session) {
            $city_id = WC()->session->get('ygb_selected_city');
        }
        
        if ($city_id) {
            $city_id = absint($city_id);
            $cost = YGB_City_Database::get_shipping_cost($city_id);
            
            if ($cost !== null && $cost > 0) {
                $cart->add_fee(esc_html__('Costo de envío', 'ygb-city'), $cost);
            } elseif ($cost === null) {
                $default_cost = floatval(get_option('ygb_city_default_cost', '5.00'));
                if ($default_cost > 0) {
                    $cart->add_fee(esc_html__('Costo de envío', 'ygb-city'), $default_cost);
                }
            }
        }
    }
    
    public function ajax_update_shipping_cost() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_send_json_error(esc_html__('Método no permitido', 'ygb-city'), 405);
        }
        
        if (!check_ajax_referer('ygb_frontend_nonce', 'nonce', false)) {
            wp_send_json_error(esc_html__('Nonce inválido', 'ygb-city'), 403);
        }
        
        if (!$this->check_rate_limit()) {
            wp_send_json_error(esc_html__('Demasiados intentos. Por favor, espera un momento.', 'ygb-city'), 429);
        }
        $this->increment_rate_limit();
        
        $city_id = isset($_POST['city_id']) ? absint($_POST['city_id']) : 0;
        
        if ($city_id <= 0) {
            wp_send_json_error(esc_html__('ID de municipio no válido', 'ygb-city'), 400);
        }
        
        $cost = YGB_City_Database::get_shipping_cost($city_id);
        
        if ($cost !== null) {
            if (function_exists('WC') && WC()->session) {
                WC()->session->set('ygb_selected_city', $city_id);
            }
            
            $city_data = YGB_City_Database::get_city_data($city_id);
            
            wp_send_json_success(array(
                'cost' => $cost,
                'cost_formatted' => wc_price($cost),
                'city_name' => $city_data ? esc_html($city_data->city_name) : '',
                'province_name' => $city_data ? esc_html($city_data->province_name) : '',
                'province_code' => $city_data ? esc_html($city_data->province_code) : ''
            ));
        } else {
            wp_send_json_error(esc_html__('Municipio no encontrado', 'ygb-city'), 404);
        }
    }
    
    /**
     * Guardar selección de municipio con verificación CSRF OBLIGATORIA
     */
    public function save_city_selection() {
        if (!isset($_POST['ygb_city_nonce']) || !isset($_POST['ygb_city'])) {
            return;
        }
        
        if (!wp_verify_nonce(wp_unslash($_POST['ygb_city_nonce']), 'ygb_checkout_save_city')) {
            YGB_City_Database::log_activity('Nonce inválido en save_city_selection', 'security');
            wp_die(esc_html__('Error de seguridad: nonce inválido', 'ygb-city'), 403);
        }
        
        $city_id = absint(wp_unslash($_POST['ygb_city']));
        if ($city_id > 0 && function_exists('WC') && WC()->session) {
            WC()->session->set('ygb_selected_city', $city_id);
        }
    }
    
    public function save_city_to_order($order, $data) {
        if (isset($_POST['ygb_province']) && isset($_POST['ygb_city'])) {
            $province_id = absint(wp_unslash($_POST['ygb_province']));
            $city_id = absint(wp_unslash($_POST['ygb_city']));
            
            $city_data = YGB_City_Database::get_city_data($city_id);
            
            if ($city_data) {
                $order->update_meta_data('_ygb_province_id', $city_data->province_id);
                $order->update_meta_data('_ygb_province_name', $city_data->province_name);
                $order->update_meta_data('_ygb_province_code', $city_data->province_code);
                $order->update_meta_data('_ygb_city_id', $city_data->city_id);
                $order->update_meta_data('_ygb_city_name', $city_data->city_name);
                $order->update_meta_data('_ygb_shipping_cost', $city_data->shipping_cost);
                
                $order->set_billing_city($city_data->city_name);
                $order->set_billing_state($city_data->province_name);
            }
        }
    }
    
    public function save_city_meta($order_id, $data) {
        if (isset($_POST['ygb_province']) && isset($_POST['ygb_city'])) {
            $province_id = absint(wp_unslash($_POST['ygb_province']));
            $city_id = absint(wp_unslash($_POST['ygb_city']));
            
            $city_data = YGB_City_Database::get_city_data($city_id);
            
            if ($city_data) {
                update_post_meta($order_id, '_ygb_province_id', $province_id);
                update_post_meta($order_id, '_ygb_province_name', $city_data->province_name);
                update_post_meta($order_id, '_ygb_province_code', $city_data->province_code);
                update_post_meta($order_id, '_ygb_city_id', $city_id);
                update_post_meta($order_id, '_ygb_city_name', $city_data->city_name);
                update_post_meta($order_id, '_ygb_shipping_cost', $city_data->shipping_cost);
            }
        }
    }
    
    public function display_city_in_admin_order($order) {
        $province_name = $order->get_meta('_ygb_province_name');
        $city_name = $order->get_meta('_ygb_city_name');
        $shipping_cost = $order->get_meta('_ygb_shipping_cost');
        
        if ($province_name || $city_name) {
            echo '<div class="ygb-city-order-data">';
            echo '<h4>' . esc_html__('Información de envío YGB', 'ygb-city') . '</h4>';
            
            if ($province_name) {
                echo '<p><strong>' . esc_html__('Provincia:', 'ygb-city') . '</strong> ' . esc_html($province_name) . '</p>';
            }
            if ($city_name) {
                echo '<p><strong>' . esc_html__('Municipio:', 'ygb-city') . '</strong> ' . esc_html($city_name) . '</p>';
            }
            if ($shipping_cost !== '') {
                echo '<p><strong>' . esc_html__('Costo de envío aplicado:', 'ygb-city') . '</strong> ' . wp_kses_post(wc_price($shipping_cost)) . '</p>';
            }
            echo '</div>';
        }
    }
    
    public function display_city_in_order_details($order) {
        $province_name = $order->get_meta('_ygb_province_name');
        $city_name = $order->get_meta('_ygb_city_name');
        
        if ($province_name || $city_name) {
            echo '<div class="ygb-shipping-details">';
            echo '<h3>' . esc_html__('Detalles de envío', 'ygb-city') . '</h3>';
            echo '<p><strong>' . esc_html__('Provincia:', 'ygb-city') . '</strong> ' . esc_html($province_name) . '</p>';
            echo '<p><strong>' . esc_html__('Municipio:', 'ygb-city') . '</strong> ' . esc_html($city_name) . '</p>';
            echo '</div>';
        }
    }
    
    public function add_city_to_formatted_address($address, $order) {
        $province_name = $order->get_meta('_ygb_province_name');
        $city_name = $order->get_meta('_ygb_city_name');
        
        if ($city_name) {
            $address['city'] = $city_name;
        }
        
        if ($province_name) {
            $address['state'] = $province_name;
        }
        
        return $address;
    }
}