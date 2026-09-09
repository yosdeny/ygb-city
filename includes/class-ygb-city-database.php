<?php
/**
 * YGB City Database Class - Versión 2.5.1 con corrección de provincia ID
 * @version 2.5.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class YGB_City_Database {
    
    private static function get_provinces_table() {
        global $wpdb;
        return $wpdb->prefix . 'ygb_provinces';
    }
    
    private static function get_cities_table() {
        global $wpdb;
        return $wpdb->prefix . 'ygb_cities';
    }
    
    public static function create_tables() {
        global $wpdb;
        
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $table_provinces = self::get_provinces_table();
        $sql_provinces = "CREATE TABLE IF NOT EXISTS {$table_provinces} (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            code varchar(50) NOT NULL,
            active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY code (code)
        ) {$charset_collate};";
        
        $table_cities = self::get_cities_table();
        $sql_cities = "CREATE TABLE IF NOT EXISTS {$table_cities} (
            id int(11) NOT NULL AUTO_INCREMENT,
            province_id int(11) NOT NULL,
            name varchar(100) NOT NULL,
            shipping_cost decimal(10,2) NOT NULL DEFAULT 0.00,
            active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_city (province_id, name),
            KEY province_id (province_id)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        try {
            dbDelta($sql_provinces);
            dbDelta($sql_cities);
            self::create_logs_table();
            return true;
        } catch (Exception $e) {
            self::log_activity('DB Error: ' . $e->getMessage(), 'error');
            return false;
        }
    }
    
    private static function create_logs_table() {
        global $wpdb;
        $table_logs = $wpdb->prefix . 'ygb_activity_logs';
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS {$table_logs} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            action varchar(50) NOT NULL,
            user_id bigint(20) DEFAULT NULL,
            user_ip varchar(45) DEFAULT NULL,
            details text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY action (action),
            KEY created_at (created_at)
        ) {$charset_collate};";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    public static function log_activity($message, $type = 'info') {
        global $wpdb;
        $table_logs = $wpdb->prefix . 'ygb_activity_logs';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_logs)) !== $table_logs) {
            self::create_logs_table();
        }
        
        $user_id = get_current_user_id() ?: null;
        $user_ip = self::get_client_ip();
        
        $wpdb->insert(
            $table_logs,
            array(
                'action' => sanitize_text_field($type),
                'user_id' => $user_id,
                'user_ip' => $user_ip,
                'details' => sanitize_textarea_field($message),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%d', '%s', '%s', '%s')
        );
        
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table_logs} 
                 WHERE id NOT IN (
                     SELECT id FROM (
                         SELECT id FROM {$table_logs} 
                         ORDER BY created_at DESC 
                         LIMIT %d
                     ) AS t
                 )",
                1000
            )
        );
        
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log("YGB City [{$type}]: {$message}");
        }
    }
    
    public static function get_client_ip() {
        $ip = '0.0.0.0';
        if (function_exists('WC') && method_exists('WC_Geolocation', 'get_ip_address')) {
            $ip = \WC_Geolocation::get_ip_address();
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']));
            $ip = explode(',', $ip)[0];
        } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_CLIENT_IP']));
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }
    
    public static function get_provinces() {
        global $wpdb;
        $table = self::get_provinces_table();
        $provinces = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} WHERE active = %d ORDER BY name ASC", 1)
        );
        return $provinces ? array_map(function($province) {
            return (object) array(
                'id' => intval($province->id),
                'name' => esc_html($province->name),
                'code' => esc_html($province->code),
                'active' => intval($province->active)
            );
        }, $provinces) : array();
    }
    
    public static function get_province($province_id) {
        global $wpdb;
        $table = self::get_provinces_table();
        $province_id = absint($province_id);
        if ($province_id <= 0) return null;
        $province = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $province_id));
        if ($province) {
            $province->id = intval($province->id);
            $province->name = esc_html($province->name);
            $province->code = esc_html($province->code);
        }
        return $province;
    }
    
    public static function get_province_by_code($code) {
        global $wpdb;
        $table = self::get_provinces_table();
        $code = sanitize_text_field($code);
        if (empty($code)) return null;
        $province = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE code = %s AND active = %d", $code, 1));
        if ($province) {
            $province->id = intval($province->id);
            $province->name = esc_html($province->name);
            $province->code = esc_html($province->code);
        }
        return $province;
    }
    
    public static function get_cities_by_province_public($province_id) {
        global $wpdb;
        $table = self::get_cities_table();
        $province_id = absint($province_id);
        if ($province_id <= 0) return array();
        $cities = $wpdb->get_results(
            $wpdb->prepare("SELECT id, name FROM {$table} WHERE province_id = %d AND active = %d ORDER BY name ASC", $province_id, 1)
        );
        return $cities ? array_map(function($city) {
            return (object) array('id' => intval($city->id), 'name' => esc_html($city->name));
        }, $cities) : array();
    }
    
    public static function get_cities_by_province($province_id) {
        global $wpdb;
        $table = self::get_cities_table();
        $province_id = absint($province_id);
        if ($province_id <= 0) return array();
        $cities = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} WHERE province_id = %d AND active = %d ORDER BY name ASC", $province_id, 1)
        );
        return $cities ? array_map(function($city) {
            return (object) array(
                'id' => intval($city->id),
                'province_id' => intval($city->province_id),
                'name' => esc_html($city->name),
                'shipping_cost' => floatval($city->shipping_cost),
                'active' => intval($city->active)
            );
        }, $cities) : array();
    }
    
    public static function get_all_cities() {
        global $wpdb;
        $table_cities = self::get_cities_table();
        $table_provinces = self::get_provinces_table();
        $cities = $wpdb->get_results(
            "SELECT c.*, p.name as province_name 
             FROM {$table_cities} c 
             LEFT JOIN {$table_provinces} p ON c.province_id = p.id 
             WHERE c.active = 1 
             ORDER BY p.name ASC, c.name ASC"
        );
        return $cities ? array_map(function($city) {
            return (object) array(
                'id' => intval($city->id),
                'province_id' => intval($city->province_id),
                'province_name' => esc_html($city->province_name),
                'name' => esc_html($city->name),
                'shipping_cost' => floatval($city->shipping_cost),
                'active' => intval($city->active)
            );
        }, $cities) : array();
    }
    
    public static function get_shipping_cost($city_id) {
        global $wpdb;
        $table = self::get_cities_table();
        $city_id = absint($city_id);
        if ($city_id <= 0) return null;
        $cost = $wpdb->get_var($wpdb->prepare("SELECT shipping_cost FROM {$table} WHERE id = %d AND active = %d", $city_id, 1));
        return $cost !== null ? floatval($cost) : null;
    }
    
    public static function get_city_data($city_id) {
        global $wpdb;
        $table_cities = self::get_cities_table();
        $table_provinces = self::get_provinces_table();
        $city_id = absint($city_id);
        if ($city_id <= 0) return null;
        $city_data = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT c.id as city_id, c.name as city_name, c.shipping_cost,
                        p.id as province_id, p.name as province_name, p.code as province_code
                 FROM {$table_cities} c
                 LEFT JOIN {$table_provinces} p ON c.province_id = p.id
                 WHERE c.id = %d AND c.active = 1",
                $city_id
            )
        );
        if ($city_data) {
            $city_data->city_id = intval($city_data->city_id);
            $city_data->province_id = intval($city_data->province_id);
            $city_data->shipping_cost = floatval($city_data->shipping_cost);
        }
        return $city_data;
    }
    
    public static function add_province($name, $code) {
        global $wpdb;
        $table = self::get_provinces_table();
        $name = sanitize_text_field(trim(wp_unslash($name)));
        $code = sanitize_text_field(trim(wp_unslash($code)));
        if (empty($name) || empty($code)) return false;
        if (strlen($name) > 100 || strlen($code) > 50) return false;
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE code = %s", $code));
        if ($exists) {
            return intval($exists);
        }
        $result = $wpdb->insert($table, array('name' => $name, 'code' => $code, 'active' => 1), array('%s', '%s', '%d'));
        if ($result) {
            $insert_id = intval($wpdb->insert_id);
            self::log_activity("Provincia añadida: {$name} ({$code}) con ID: {$insert_id}", 'admin');
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("YGB City add_province: Insertada provincia '{$name}' con ID {$insert_id}");
            }
            return $insert_id;
        }
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("YGB City add_province ERROR: " . $wpdb->last_error);
        }
        return false;
    }
    
    /**
     * Añadir municipio con manejo robusto de duplicados
     */
    public static function add_city($province_id, $name, $shipping_cost) {
        global $wpdb;
        $table = self::get_cities_table();
        
        $province_id = absint($province_id);
        $name = sanitize_text_field(trim(wp_unslash($name)));
        $shipping_cost = floatval($shipping_cost);
        
        if ($province_id <= 0 || empty($name)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("YGB City add_city: province_id inválido ({$province_id}) o nombre vacío");
            }
            return false;
        }
        if (strlen($name) > 100) {
            return false;
        }
        if ($shipping_cost < 0) {
            $shipping_cost = 0;
        }
        
        // Verificar si ya existe (con COLLATE para manejar acentos)
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE province_id = %d AND name COLLATE utf8mb4_unicode_ci = %s",
            $province_id,
            $name
        ));
        if ($existing) {
            return intval($existing);
        }
        
        $result = $wpdb->insert(
            $table,
            array(
                'province_id' => $province_id,
                'name' => $name,
                'shipping_cost' => $shipping_cost,
                'active' => 1
            ),
            array('%d', '%s', '%f', '%d')
        );
        
        if ($result) {
            self::log_activity("Municipio añadido: {$name} (Provincia ID: {$province_id})", 'admin');
            return intval($wpdb->insert_id);
        } else {
            // Si falla por duplicado, intentar recuperar el ID existente
            if (strpos($wpdb->last_error, 'Duplicate entry') !== false) {
                $existing_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$table} WHERE province_id = %d AND name COLLATE utf8mb4_unicode_ci = %s",
                    $province_id,
                    $name
                ));
                if ($existing_id) {
                    return intval($existing_id);
                }
            }
            // Log del error real
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("YGB City add_city ERROR: " . $wpdb->last_error . " | Datos: province_id={$province_id}, name='{$name}', cost={$shipping_cost}");
            }
            self::log_activity("Error al insertar municipio '{$name}': " . $wpdb->last_error, 'error');
            return false;
        }
    }
    
    public static function update_city_cost($city_id, $shipping_cost) {
        global $wpdb;
        $table = self::get_cities_table();
        $city_id = absint($city_id);
        $shipping_cost = floatval($shipping_cost);
        if ($city_id <= 0) return false;
        if ($shipping_cost < 0) $shipping_cost = 0;
        $result = $wpdb->update($table, array('shipping_cost' => $shipping_cost), array('id' => $city_id), array('%f'), array('%d'));
        if ($result !== false) {
            self::log_activity("Costo actualizado para municipio ID: {$city_id} → {$shipping_cost}", 'admin');
        }
        return $result !== false;
    }
    
    public static function delete_city($city_id) {
        global $wpdb;
        $table = self::get_cities_table();
        $city_id = absint($city_id);
        if ($city_id <= 0) return false;
        $result = $wpdb->delete($table, array('id' => $city_id), array('%d'));
        if ($result !== false) {
            self::log_activity("Municipio eliminado ID: {$city_id}", 'admin');
        }
        return $result !== false;
    }
    
    public static function delete_province($province_id) {
        global $wpdb;
        $table = self::get_provinces_table();
        $province_id = absint($province_id);
        if ($province_id <= 0) return false;
        $result = $wpdb->delete($table, array('id' => $province_id), array('%d'));
        if ($result !== false) {
            self::log_activity("Provincia eliminada ID: {$province_id}", 'admin');
        }
        return $result !== false;
    }
    
    public static function get_stats() {
        global $wpdb;
        $table_provinces = self::get_provinces_table();
        $table_cities = self::get_cities_table();
        return array(
            'total_provinces' => intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table_provinces} WHERE active = %d", 1))),
            'total_cities' => intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table_cities} WHERE active = %d", 1))),
            'avg_shipping_cost' => floatval($wpdb->get_var($wpdb->prepare("SELECT AVG(shipping_cost) FROM {$table_cities} WHERE active = %d", 1))),
            'min_shipping_cost' => floatval($wpdb->get_var($wpdb->prepare("SELECT MIN(shipping_cost) FROM {$table_cities} WHERE active = %d", 1))),
            'max_shipping_cost' => floatval($wpdb->get_var($wpdb->prepare("SELECT MAX(shipping_cost) FROM {$table_cities} WHERE active = %d", 1)))
        );
    }
    
    private static function csv_escape($value) {
        $value = str_replace('"', '""', $value);
        $first_char = substr($value, 0, 1);
        if (in_array($first_char, array('=', '+', '-', '@'))) {
            $value = "'" . $value;
        }
        return '"' . $value . '"';
    }
    
    public static function export_to_csv() {
        global $wpdb;
        $table_cities = self::get_cities_table();
        $table_provinces = self::get_provinces_table();
        $results = $wpdb->get_results(
            "SELECT p.code as province_code, p.name as province_name, 
                    c.name as city_name, c.shipping_cost 
             FROM {$table_cities} c 
             LEFT JOIN {$table_provinces} p ON c.province_id = p.id 
             WHERE c.active = 1 
             ORDER BY p.name ASC, c.name ASC",
            ARRAY_A
        );
        if (empty($results)) return false;
        $csv = "\xEF\xBB\xBF";
        $csv .= "Provincia Código,Provincia Nombre,Municipio,Costo de Envío (€)\n";
        $rows = array();
        foreach ($results as $row) {
            $rows[] = implode(',', array(
                self::csv_escape($row['province_code']),
                self::csv_escape($row['province_name']),
                self::csv_escape($row['city_name']),
                self::csv_escape(number_format(floatval($row['shipping_cost']), 2))
            ));
        }
        $csv .= implode("\n", $rows);
        self::log_activity("Exportación CSV completada - " . count($results) . " registros", 'admin');
        return $csv;
    }
    
    public static function export_template() {
        $csv = "\xEF\xBB\xBF";
        $csv .= "Provincia Código,Provincia Nombre,Municipio,Costo de Envío (€)\n";
        $csv .= self::csv_escape('M') . ',' . self::csv_escape('Madrid') . ',' . self::csv_escape('Madrid Capital') . ',5.00' . "\n";
        $csv .= self::csv_escape('M') . ',' . self::csv_escape('Madrid') . ',' . self::csv_escape('Alcalá de Henares') . ',6.00' . "\n";
        $csv .= self::csv_escape('B') . ',' . self::csv_escape('Barcelona') . ',' . self::csv_escape('Barcelona Capital') . ',5.50';
        return $csv;
    }
    
    public static function import_from_csv($file_path, $overwrite = false) {
        global $wpdb;

        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $credentials = request_filesystem_credentials('', '', false, false, null);
        if (!WP_Filesystem($credentials)) {
            return array(
                'success' => false,
                'imported' => 0,
                'errors' => 0,
                'errors_list' => array('No se pudo inicializar el sistema de archivos')
            );
        }

        global $wp_filesystem;

        if (!$wp_filesystem->exists($file_path) || !$wp_filesystem->is_readable($file_path)) {
            return array(
                'success' => false,
                'imported' => 0,
                'errors' => 0,
                'errors_list' => array('El archivo no existe o no se puede leer')
            );
        }

        $mime_type = 'text/csv';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file_path);
            finfo_close($finfo);
        } elseif (function_exists('mime_content_type')) {
            $mime_type = mime_content_type($file_path);
        }

        $allowed_mimes = array('text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel');
        if (!in_array($mime_type, $allowed_mimes)) {
            return array(
                'success' => false,
                'imported' => 0,
                'errors' => 0,
                'errors_list' => array('El archivo no es un CSV válido (MIME: ' . $mime_type . ')')
            );
        }

        $file_info = pathinfo($file_path);
        if (strtolower($file_info['extension'] ?? '') !== 'csv') {
            return array(
                'success' => false,
                'imported' => 0,
                'errors' => 0,
                'errors_list' => array('Solo se permiten archivos con extensión .csv')
            );
        }

        $content = $wp_filesystem->get_contents($file_path);
        if ($content === false) {
            return array(
                'success' => false,
                'imported' => 0,
                'errors' => 0,
                'errors_list' => array('No se pudo leer el archivo')
            );
        }

        $lines = explode("\n", $content);
        if (empty($lines)) {
            return array(
                'success' => false,
                'imported' => 0,
                'errors' => 0,
                'errors_list' => array('El archivo CSV está vacío')
            );
        }

        $imported = 0;
        $errors = 0;
        $errors_list = array();
        $line_number = 0;

        $raw_header = str_getcsv(array_shift($lines));
        $line_number++;

        if ($raw_header === false || empty($raw_header)) {
            return array(
                'success' => false,
                'imported' => 0,
                'errors' => 0,
                'errors_list' => array('El archivo CSV tiene un formato inválido')
            );
        }

        if (!empty($raw_header[0])) {
            $bom = pack('H*','EFBBBF');
            $raw_header[0] = preg_replace('/^' . preg_quote($bom, '/') . '/', '', $raw_header[0]);
            $raw_header[0] = trim($raw_header[0]);
        }

        $cleaned_header = array_map(function($item) {
            return trim($item, " \t\n\r\0\x0B\"");
        }, $raw_header);

        if (count($cleaned_header) < 4) {
            return array(
                'success' => false,
                'imported' => 0,
                'errors' => 0,
                'errors_list' => array('El CSV debe tener al menos 4 columnas')
            );
        }

        $col_code = 0;
        $col_name = 1;
        $col_city = 2;
        $col_cost = 3;

        foreach ($cleaned_header as $index => $header_name) {
            $header_lower = strtolower($header_name);
            if (strpos($header_lower, 'cod') !== false || strpos($header_lower, 'code') !== false) {
                $col_code = $index;
            } elseif (strpos($header_lower, 'provincia') !== false || strpos($header_lower, 'nombre') !== false) {
                $col_name = $index;
            } elseif (strpos($header_lower, 'municipio') !== false || strpos($header_lower, 'ciudad') !== false) {
                $col_city = $index;
            } elseif (strpos($header_lower, 'costo') !== false || strpos($header_lower, 'cost') !== false) {
                $col_cost = $index;
            }
        }

        $wpdb->query('START TRANSACTION');

        try {
            foreach ($lines as $line) {
                if (preg_match('/^\s*$/', $line)) {
                    continue;
                }

                $line_number++;
                $data = str_getcsv($line);
                $data = array_pad($data, 4, '');

                $province_code = trim(preg_replace('/[\x00-\x1F\x7F\xA0\x{200B}]/u', '', $data[$col_code] ?? ''), " \t\n\r\0\x0B\"");
                $province_name = trim(preg_replace('/[\x00-\x1F\x7F\xA0\x{200B}]/u', '', $data[$col_name] ?? ''), " \t\n\r\0\x0B\"");
                $city_name     = trim(preg_replace('/[\x00-\x1F\x7F\xA0\x{200B}]/u', '', $data[$col_city] ?? ''), " \t\n\r\0\x0B\"");
                $cost_raw      = trim(preg_replace('/[\x00-\x1F\x7F\xA0\x{200B}]/u', '', $data[$col_cost] ?? ''), " \t\n\r\0\x0B\"");

                $province_code = sanitize_text_field($province_code);
                $province_name = sanitize_text_field($province_name);
                $city_name     = sanitize_text_field($city_name);

                if (empty($province_code) || empty($province_name) || empty($city_name)) {
                    $errors++;
                    $errors_list[] = "Línea {$line_number}: Datos incompletos (código, provincia o municipio vacío)";
                    continue;
                }

                $cost_raw = str_replace(',', '.', $cost_raw);
                if (!is_numeric($cost_raw) && $cost_raw !== '') {
                    $errors++;
                    $errors_list[] = "Línea {$line_number}: Costo no válido ('{$cost_raw}')";
                    continue;
                }

                $shipping_cost = floatval($cost_raw);

                $result = self::import_single_row($province_code, $province_name, $city_name, $shipping_cost, $overwrite);

                if ($result !== false) {
                    $imported++;
                } else {
                    $errors++;
                    $error_msg = "Línea {$line_number}: Error al importar el municipio '{$city_name}'";
                    $errors_list[] = $error_msg;
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("YGB City import falló fila {$line_number}: " . $wpdb->last_error . " | Datos: province_code='{$province_code}', city='{$city_name}', cost={$shipping_cost}");
                    }
                }
            }

            $wpdb->query('COMMIT');

            $success = ($errors === 0);
            $message = $success
                ? "Importación CSV completada exitosamente - Importados: {$imported}"
                : "Importación CSV completada con errores - Importados: {$imported}, Errores: {$errors}";

            self::log_activity($message, $success ? 'admin' : 'error');

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            self::log_activity('Import Error (crítico): ' . $e->getMessage(), 'error');
            return array(
                'success' => false,
                'imported' => $imported,
                'errors' => $errors + 1,
                'errors_list' => array_merge($errors_list, array('Excepción crítica: ' . $e->getMessage()))
            );
        }

        return array(
            'success' => ($errors === 0),
            'imported' => $imported,
            'errors' => $errors,
            'errors_list' => $errors_list
        );
    }
    
    private static function import_single_row($province_code, $province_name, $city_name, $shipping_cost, $overwrite) {
        global $wpdb;
        $table_provinces = self::get_provinces_table();
        $table_cities = self::get_cities_table();
        
        // Primero, intentar obtener la provincia por código
        $province = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id FROM {$table_provinces} WHERE code = %s",
                $province_code
            )
        );
        
        if (!$province) {
            // Si no existe, crearla
            $province_id = self::add_province($province_name, $province_code);
            if (!$province_id) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("YGB City import_single_row: No se pudo crear la provincia '{$province_name}'");
                }
                return false;
            }
            // ✅ Verificar que la provincia realmente se creó y obtener su ID
            $verify = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$table_provinces} WHERE code = %s",
                    $province_code
                )
            );
            if (!$verify) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("YGB City import_single_row: La provincia se creó pero no se encuentra en la BD");
                }
                return false;
            }
            $province_id = intval($verify);
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("YGB City import_single_row: Provincia creada con ID {$province_id} para '{$province_name}'");
            }
        } else {
            $province_id = intval($province->id);
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("YGB City import_single_row: Provincia existente con ID {$province_id} para '{$province_name}'");
            }
        }
        
        // Verificar que province_id es válido
        if ($province_id <= 0) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("YGB City import_single_row: province_id inválido ({$province_id}) para '{$city_name}'");
            }
            return false;
        }
        
        // Verificar existencia del municipio (con COLLATE)
        $existing_city = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, shipping_cost FROM {$table_cities} WHERE province_id = %d AND name COLLATE utf8mb4_unicode_ci = %s",
                $province_id,
                $city_name
            )
        );
        
        if ($existing_city) {
            if ($overwrite) {
                $result = self::update_city_cost($existing_city->id, $shipping_cost);
                return $result !== false ? 'updated' : false;
            }
            return 'duplicate';
        }
        
        $city_id = self::add_city($province_id, $city_name, $shipping_cost);
        if ($city_id === false) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("YGB City import_single_row: Error al insertar '{$city_name}' con province_id {$province_id}");
            }
        }
        return $city_id !== false ? $city_id : false;
    }
    
    /**
     * Limpiar todos los datos con fallback a DELETE si TRUNCATE falla
     */
    public static function clear_all_data() {
        global $wpdb;
        
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        $tables = array(
            self::get_cities_table(),
            self::get_provinces_table(),
            $wpdb->prefix . 'ygb_activity_logs'
        );
        
        $all_cleared = true;
        foreach ($tables as $table) {
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
                continue;
            }
            
            $result = $wpdb->query($wpdb->prepare('TRUNCATE TABLE %i', $table));
            if ($result === false) {
                $result = $wpdb->query($wpdb->prepare('DELETE FROM %i', $table));
                if ($result === false) {
                    $all_cleared = false;
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("YGB City: No se pudo limpiar la tabla {$table}");
                    }
                } else {
                    $wpdb->query($wpdb->prepare('ALTER TABLE %i AUTO_INCREMENT = 1', $table));
                }
            }
        }
        
        if ($all_cleared) {
            self::log_activity("TODOS LOS DATOS ELIMINADOS por usuario ID: " . get_current_user_id(), 'admin');
            return true;
        } else {
            self::log_activity("Error al limpiar algunas tablas", 'error');
            return false;
        }
    }
}