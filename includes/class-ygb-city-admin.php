<?php
/**
 * YGB City Admin Class - Versión 2.4.1
 * Gestión de provincias, municipios, ajustes, importación/exportación y logs
 * 
 * @package YGB_City
 * @version 2.4.1
 * @author Tu Nombre
 * @license GPL-2.0+
 */

if (!defined('ABSPATH')) {
    exit; // Salir si se accede directamente
}

class YGB_City_Admin {
    
    /**
     * Capacidad requerida para acceder al admin
     *
     * @var string
     */
    private $capability = 'manage_options';
    
    /**
     * Tamaño máximo de archivo para importación (5 MB)
     *
     * @var int
     */
    private $max_file_size = 5242880;
    
    /**
     * Constructor - Registra todos los hooks
     */
    public function __construct() {
        // Menú y scripts
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Procesamiento de formularios
        add_action('admin_init', array($this, 'handle_settings_save'));
        add_action('admin_init', array($this, 'handle_import_export'));
        
        // AJAX handlers
        add_action('wp_ajax_ygb_add_province', array($this, 'ajax_add_province'));
        add_action('wp_ajax_ygb_add_city', array($this, 'ajax_add_city'));
        add_action('wp_ajax_ygb_update_cost', array($this, 'ajax_update_cost'));
        add_action('wp_ajax_ygb_delete_city', array($this, 'ajax_delete_city'));
        add_action('wp_ajax_ygb_delete_province', array($this, 'ajax_delete_province'));
        add_action('wp_ajax_ygb_clear_logs', array($this, 'ajax_clear_logs'));
        
        // Seguridad
        add_action('admin_init', array($this, 'prevent_direct_access'));
    }
    
    /**
     * Prevenir acceso directo a páginas del plugin sin permisos
     *
     * @return void
     */
    public function prevent_direct_access() {
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }
        
        $current_screen = get_current_screen();
        if ($current_screen && strpos($current_screen->id, 'ygb-city') !== false) {
            if (!current_user_can($this->capability)) {
                wp_die(
                    esc_html__('No tienes permisos para acceder a esta página.', 'ygb-city'),
                    esc_html__('Acceso Denegado', 'ygb-city'),
                    array('response' => 403)
                );
            }
        }
    }
    
    /**
     * Añadir menú al panel de administración
     *
     * @return void
     */
    public function add_admin_menu() {
        add_menu_page(
            __('YGB City Shipping', 'ygb-city'),
            __('YGB City', 'ygb-city'),
            $this->capability,
            'ygb-city',
            array($this, 'render_admin_page'),
            'dashicons-location-alt',
            30
        );
    }
    
    /**
     * Procesar formulario de guardado de ajustes
     *
     * @return void
     */
    public function handle_settings_save() {
        if (!isset($_POST['ygb_save_settings'])) {
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_die(
                esc_html__('Método no permitido.', 'ygb-city'),
                esc_html__('Error', 'ygb-city'),
                array('response' => 405)
            );
        }
        
        if (!wp_verify_nonce(wp_unslash($_POST['_wpnonce'] ?? ''), 'ygb_save_settings')) {
            wp_die(
                esc_html__('Acción no permitida.', 'ygb-city'),
                esc_html__('Error de Seguridad', 'ygb-city'),
                array('response' => 403)
            );
        }
        
        if (!current_user_can($this->capability)) {
            wp_die(
                esc_html__('No tienes permisos.', 'ygb-city'),
                esc_html__('Acceso Denegado', 'ygb-city'),
                array('response' => 403)
            );
        }
        
        // Guardar ajustes
        $enabled = isset($_POST['enabled']) ? 'yes' : 'no';
        update_option('ygb_city_enabled', $enabled);
        
        $default_cost = isset($_POST['default_cost']) ? floatval(wp_unslash($_POST['default_cost'])) : 5.00;
        if ($default_cost >= 0) {
            update_option('ygb_city_default_cost', $default_cost);
        }
        
        YGB_City_Database::log_activity(
            sprintf('Ajustes guardados - Activado: %s, Costo default: %s', $enabled, $default_cost),
            'admin'
        );
        
        wp_safe_redirect(add_query_arg(array(
            'page' => 'ygb-city',
            'tab' => 'settings',
            'settings-updated' => 'true'
        ), admin_url('admin.php')));
        exit;
    }
    
    /**
     * Procesar importación, exportación y limpieza de datos
     *
     * @return void
     */
    public function handle_import_export() {
        // Exportar datos
        if (isset($_POST['ygb_export'])) {
            $this->handle_export();
        }
        
        // Importar datos
        if (isset($_POST['ygb_import'])) {
            $this->handle_import();
        }
        
        // Limpiar datos
        if (isset($_POST['ygb_clear'])) {
            $this->handle_clear_data();
        }
    }
    
    /**
     * Validar solicitud de importación/exportación
     *
     * @param string $nonce_action Acción de nonce a verificar
     * @return bool|void Devuelve true si es válido, o muere con error
     */
    private function validate_import_export_request($nonce_action) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_die(esc_html__('Método no permitido.', 'ygb-city'), esc_html__('Error', 'ygb-city'), array('response' => 405));
        }
        
        if (!wp_verify_nonce(wp_unslash($_POST['_wpnonce'] ?? ''), $nonce_action)) {
            wp_die(esc_html__('Acción no permitida.', 'ygb-city'), esc_html__('Error de Seguridad', 'ygb-city'), array('response' => 403));
        }
        
        if (!current_user_can($this->capability)) {
            wp_die(esc_html__('No tienes permisos.', 'ygb-city'), esc_html__('Acceso Denegado', 'ygb-city'), array('response' => 403));
        }
        
        return true;
    }
    
    /**
     * Manejar exportación de datos
     *
     * @return void
     */
    private function handle_export() {
        $this->validate_import_export_request('ygb_export_action');
        
        $export_type = isset($_POST['export_type']) ? sanitize_text_field(wp_unslash($_POST['export_type'])) : '';
        $csv_data = false;
        $filename = '';
        
        switch ($export_type) {
            case 'full':
                $csv_data = YGB_City_Database::export_to_csv();
                $filename = 'ygb_shipping_data_' . wp_date('Y-m-d') . '.csv';
                break;
            case 'template':
                $csv_data = YGB_City_Database::export_template();
                $filename = 'ygb_import_template.csv';
                break;
            default:
                wp_die(esc_html__('Tipo de exportación no válido.', 'ygb-city'));
        }
        
        if ($csv_data !== false && !empty($csv_data)) {
            // Limpiar buffers
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');
            header('Pragma: no-cache');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('X-Content-Type-Options: nosniff');
            
            echo $csv_data;
            exit;
        } else {
            wp_safe_redirect(add_query_arg(array(
                'page' => 'ygb-city',
                'tab' => 'import-export',
                'export-error' => 'no-data'
            ), admin_url('admin.php')));
            exit;
        }
    }
    
    /**
     * Manejar importación de datos
     *
     * @return void
     */
    private function handle_import() {
        $this->validate_import_export_request('ygb_import_action');
        
        if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            wp_safe_redirect(add_query_arg(array(
                'page' => 'ygb-city',
                'tab' => 'import-export',
                'import-result' => 'error',
                'error' => 'file'
            ), admin_url('admin.php')));
            exit;
        }
        
        if ($_FILES['import_file']['size'] > $this->max_file_size) {
            wp_safe_redirect(add_query_arg(array(
                'page' => 'ygb-city',
                'tab' => 'import-export',
                'import-result' => 'error',
                'error' => 'file-size'
            ), admin_url('admin.php')));
            exit;
        }
        
        $upload_overrides = array(
            'test_form' => false,
            'mimes' => array('csv' => 'text/csv', 'text/plain' => 'text/plain'),
            'max_size' => $this->max_file_size,
            'test_type' => true
        );
        
        $uploaded_file = wp_handle_upload($_FILES['import_file'], $upload_overrides);
        
        if (isset($uploaded_file['error'])) {
            wp_safe_redirect(add_query_arg(array(
                'page' => 'ygb-city',
                'tab' => 'import-export',
                'import-result' => 'error',
                'error' => 'upload'
            ), admin_url('admin.php')));
            exit;
        }
        
        // Validación adicional: verificar que el archivo sea realmente CSV
        $file_info = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($file_info, $uploaded_file['file']);
        finfo_close($file_info);
        
        $allowed_mime_types = array('text/csv', 'text/plain', 'application/vnd.ms-excel');
        if (!in_array($mime_type, $allowed_mime_types, true)) {
            wp_delete_file($uploaded_file['file']);
            wp_safe_redirect(add_query_arg(array(
                'page' => 'ygb-city',
                'tab' => 'import-export',
                'import-result' => 'error',
                'error' => 'invalid-mime'
            ), admin_url('admin.php')));
            exit;
        }
        
        $overwrite = isset($_POST['overwrite']);
        $file = $uploaded_file['file'];
        
        $result = YGB_City_Database::import_from_csv($file, $overwrite);
        
        wp_delete_file($file);
        
        $redirect_args = array(
            'page' => 'ygb-city',
            'tab' => 'import-export',
            'import-result' => $result['success'] ? 'success' : 'error',
            'imported' => $result['imported'],
            'errors' => $result['errors']
        );
        
        if (!empty($result['errors_list'])) {
            set_transient('ygb_import_errors', $result['errors_list'], 3600);
        }
        
        wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
        exit;
    }
    
    /**
     * Manejar limpieza de datos
     *
     * @return void
     */
    private function handle_clear_data() {
        $this->validate_import_export_request('ygb_clear_action');
        
        if (isset($_POST['confirm_clear']) && $_POST['confirm_clear'] === 'yes') {
            YGB_City_Database::clear_all_data();
            
            wp_safe_redirect(add_query_arg(array(
                'page' => 'ygb-city',
                'tab' => 'import-export',
                'cleared' => 'true'
            ), admin_url('admin.php')));
            exit;
        }
    }
    
    /**
     * Verificar si la tabla de logs existe
     *
     * @return bool
     */
    private function logs_table_exists() {
        global $wpdb;
        $table_logs = $wpdb->prefix . 'ygb_activity_logs';
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_logs)) === $table_logs;
    }
    
    /**
     * AJAX: Limpiar logs
     *
     * @return void
     */
    public function ajax_clear_logs() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_send_json_error(esc_html__('Método no permitido', 'ygb-city'), 405);
        }
        
        if (!check_ajax_referer('ygb_admin_nonce', 'nonce', false)) {
            wp_send_json_error(esc_html__('Nonce inválido.', 'ygb-city'), 403);
        }
        
        if (!current_user_can($this->capability)) {
            wp_send_json_error(esc_html__('No tienes permisos.', 'ygb-city'), 403);
        }
        
        // Verificar si la tabla existe antes de truncar
        if (!$this->logs_table_exists()) {
            wp_send_json_error(esc_html__('La tabla de logs no existe.', 'ygb-city'), 404);
        }
        
        global $wpdb;
        $table_logs = $wpdb->prefix . 'ygb_activity_logs';
        
        $result = $wpdb->query($wpdb->prepare('TRUNCATE TABLE %i', $table_logs));
        
        if ($result !== false) {
            YGB_City_Database::log_activity('Logs limpiados por usuario ID: ' . get_current_user_id(), 'admin');
            wp_send_json_success(array('message' => esc_html__('Logs eliminados correctamente.', 'ygb-city')));
        } else {
            wp_send_json_error(esc_html__('Error al eliminar los logs.', 'ygb-city'), 500);
        }
    }
    
    /**
     * Renderizar página de administración
     *
     * @return void
     */
    public function render_admin_page() {
        if (!current_user_can($this->capability)) {
            wp_die(
                esc_html__('No tienes permisos.', 'ygb-city'),
                esc_html__('Acceso Denegado', 'ygb-city'),
                array('response' => 403)
            );
        }
        
        $active_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'provinces';
        
        // Mostrar mensajes de notificación
        $this->display_admin_notices();
        
        // Si es pestaña de logs, mostrar template diferente
        if ($active_tab === 'logs') {
            $this->render_logs_page();
            return;
        }
        
        // Datos para las otras pestañas
        $provinces = YGB_City_Database::get_provinces();
        $all_cities = YGB_City_Database::get_all_cities();
        $stats = YGB_City_Database::get_stats();
        
        include YGB_CITY_PLUGIN_DIR . 'templates/admin-page.php';
    }
    
    /**
     * Mostrar notificaciones administrativas
     *
     * @return void
     */
    private function display_admin_notices() {
        if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
            echo '<div class="notice notice-success is-dismissible"><p>' . 
                 esc_html__('✅ Ajustes guardados correctamente', 'ygb-city') . 
                 '</p></div>';
        }
        
        if (isset($_GET['export-error']) && $_GET['export-error'] === 'no-data') {
            echo '<div class="notice notice-warning is-dismissible"><p>' . 
                 esc_html__('⚠️ No hay datos para exportar.', 'ygb-city') . 
                 '</p></div>';
        }
        
        if (isset($_GET['import-result'])) {
            if ($_GET['import-result'] === 'success') {
                $imported = isset($_GET['imported']) ? intval($_GET['imported']) : 0;
                $errors = isset($_GET['errors']) ? intval($_GET['errors']) : 0;
                echo '<div class="notice notice-success is-dismissible"><p>' . 
                     sprintf(
                         esc_html__('✅ Importación completada. Importados: %d | Errores: %d', 'ygb-city'),
                         $imported,
                         $errors
                     ) . 
                     '</p></div>';
                
                $errors_list = get_transient('ygb_import_errors');
                if (!empty($errors_list) && is_array($errors_list)) {
                    echo '<div class="notice notice-warning"><p><strong>' . 
                         esc_html__('Detalles de errores:', 'ygb-city') . 
                         '</strong></p><ul>';
                    foreach ($errors_list as $error) {
                        echo '<li>' . esc_html($error) . '</li>';
                    }
                    echo '</ul></div>';
                    delete_transient('ygb_import_errors');
                }
            } else {
                $error_msg = '';
                if (isset($_GET['error'])) {
                    switch ($_GET['error']) {
                        case 'file-size':
                            $error_msg = __('El archivo es demasiado grande. El límite es de 5 MB.', 'ygb-city');
                            break;
                        case 'upload':
                            $error_msg = __('Error al subir el archivo.', 'ygb-city');
                            break;
                        default:
                            $error_msg = __('Verifica el formato del archivo.', 'ygb-city');
                    }
                }
                echo '<div class="notice notice-error is-dismissible"><p>' . 
                     sprintf(
                         esc_html__('❌ Error en la importación. %s', 'ygb-city'),
                         esc_html($error_msg)
                     ) . 
                     '</p></div>';
            }
        }
        
        if (isset($_GET['cleared']) && $_GET['cleared'] === 'true') {
            echo '<div class="notice notice-warning is-dismissible"><p>' . 
                 esc_html__('⚠️ Todos los datos han sido eliminados.', 'ygb-city') . 
                 '</p></div>';
        }
    }
    
    /**
     * Renderizar página de logs
     *
     * @return void
     */
    private function render_logs_page() {
        $logs = YGB_City_Database::get_activity_logs(200);
        $table_exists = $this->logs_table_exists();
        ?>
        <div class="wrap ygb-city-admin">
            <h1><?php echo esc_html__('YGB City Shipping - Logs de Actividad', 'ygb-city'); ?></h1>
            
            <div class="nav-tab-wrapper">
                <a href="?page=ygb-city&tab=provinces" class="nav-tab"><?php echo esc_html__('Provincias', 'ygb-city'); ?></a>
                <a href="?page=ygb-city&tab=cities" class="nav-tab"><?php echo esc_html__('Municipios', 'ygb-city'); ?></a>
                <a href="?page=ygb-city&tab=settings" class="nav-tab"><?php echo esc_html__('Ajustes', 'ygb-city'); ?></a>
                <a href="?page=ygb-city&tab=import-export" class="nav-tab"><?php echo esc_html__('Importar/Exportar', 'ygb-city'); ?></a>
                <a href="?page=ygb-city&tab=logs" class="nav-tab nav-tab-active"><?php echo esc_html__('Logs', 'ygb-city'); ?></a>
            </div>
            
            <div class="tab-content active">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2><?php echo esc_html__('Registro de Actividad', 'ygb-city'); ?></h2>
                    <?php if ($table_exists && !empty($logs)): ?>
                        <button type="button" id="ygb-clear-logs" class="button button-secondary">
                            <?php echo esc_html__('Limpiar Logs', 'ygb-city'); ?>
                        </button>
                    <?php endif; ?>
                </div>
                
                <?php if (!$table_exists): ?>
                    <div class="notice notice-warning">
                        <p><?php echo esc_html__('⚠️ La tabla de logs no existe. Los logs se crearán automáticamente cuando se realice la primera actividad.', 'ygb-city'); ?></p>
                    </div>
                <?php elseif (empty($logs)): ?>
                    <div class="notice notice-info">
                        <p><?php echo esc_html__('No hay registros de actividad disponibles.', 'ygb-city'); ?></p>
                    </div>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th width="180"><?php echo esc_html__('Fecha', 'ygb-city'); ?></th>
                                <th width="100"><?php echo esc_html__('Tipo', 'ygb-city'); ?></th>
                                <th width="100"><?php echo esc_html__('Usuario ID', 'ygb-city'); ?></th>
                                <th width="150"><?php echo esc_html__('IP', 'ygb-city'); ?></th>
                                <th><?php echo esc_html__('Detalles', 'ygb-city'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?php echo esc_html($log->created_at); ?></td>
                                    <td>
                                        <span class="ygb-log-type ygb-log-type-<?php echo esc_attr($log->action); ?>">
                                            <?php echo esc_html($log->action); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $log->user_id ? esc_html($log->user_id) : '-'; ?></td>
                                    <td><?php echo esc_html($log->user_ip); ?></td>
                                    <td><?php echo esc_html($log->details); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <p class="description" style="margin-top: 10px;">
                        <?php echo esc_html__('Se muestran los últimos 200 registros. El sistema mantiene automáticamente un máximo de 1000 registros.', 'ygb-city'); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        
        <style>
            .ygb-log-type {
                display: inline-block;
                padding: 2px 8px;
                border-radius: 3px;
                font-size: 11px;
                font-weight: bold;
            }
            .ygb-log-type-admin {
                background: #d4edda;
                color: #155724;
            }
            .ygb-log-type-security {
                background: #f8d7da;
                color: #721c24;
            }
            .ygb-log-type-error {
                background: #fff3cd;
                color: #856404;
            }
            .ygb-log-type-info {
                background: #d1ecf1;
                color: #0c5460;
            }
        </style>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#ygb-clear-logs').on('click', function() {
                if (!confirm('<?php echo esc_js(__('¿Estás seguro de que quieres eliminar todos los logs?', 'ygb-city')); ?>')) {
                    return;
                }
                
                var $button = $(this);
                var originalText = $button.text();
                
                $.ajax({
                    url: ygb_admin.ajax_url,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'ygb_clear_logs',
                        nonce: ygb_admin.nonce
                    },
                    beforeSend: function() {
                        $button.prop('disabled', true);
                        $button.text('<?php echo esc_js(__('Limpiando...', 'ygb-city')); ?>');
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.data || '<?php echo esc_js(__('Error al limpiar los logs', 'ygb-city')); ?>');
                            $button.prop('disabled', false);
                            $button.text(originalText);
                        }
                    },
                    error: function() {
                        alert('<?php echo esc_js(__('Error de conexión', 'ygb-city')); ?>');
                        $button.prop('disabled', false);
                        $button.text(originalText);
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Cargar scripts y estilos en el admin
     *
     * @param string $hook Hook de la página actual
     * @return void
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'toplevel_page_ygb-city') {
            return;
        }
        
        wp_enqueue_style('ygb-city-admin', YGB_CITY_PLUGIN_URL . 'assets/css/ygb-city-admin.css', array(), YGB_CITY_VERSION);
        wp_enqueue_script('ygb-city-admin', YGB_CITY_PLUGIN_URL . 'assets/js/ygb-city-admin.js', array('jquery'), YGB_CITY_VERSION, true);
        
        wp_localize_script('ygb-city-admin', 'ygb_admin', array(
            'ajax_url' => esc_url_raw(admin_url('admin-ajax.php')),
            'nonce' => wp_create_nonce('ygb_admin_nonce'),
            'ajax_error' => esc_html__('Error de conexión. Por favor, recarga la página.', 'ygb-city')
        ));
    }
    
    /**
     * AJAX: Añadir provincia
     *
     * @return void
     */
    public function ajax_add_province() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_send_json_error(esc_html__('Método no permitido', 'ygb-city'), 405);
        }
        
        if (!check_ajax_referer('ygb_admin_nonce', 'nonce', false)) {
            wp_send_json_error(esc_html__('Nonce inválido.', 'ygb-city'), 403);
        }
        
        if (!current_user_can($this->capability)) {
            wp_send_json_error(esc_html__('No tienes permisos.', 'ygb-city'), 403);
        }
        
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $code = isset($_POST['code']) ? sanitize_text_field(wp_unslash($_POST['code'])) : '';
        
        if (empty($name) || empty($code)) {
            wp_send_json_error(esc_html__('Nombre y código son requeridos.', 'ygb-city'), 400);
        }
        
        if (strlen($name) > 100 || strlen($code) > 50) {
            wp_send_json_error(esc_html__('Nombre máximo 100 caracteres, código máximo 50.', 'ygb-city'), 400);
        }
        
        $result = YGB_City_Database::add_province($name, $code);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => esc_html__('Provincia añadida correctamente.', 'ygb-city'),
                'id' => $result
            ));
        } else {
            wp_send_json_error(esc_html__('Error al añadir la provincia.', 'ygb-city'), 500);
        }
    }
    
    /**
     * AJAX: Eliminar provincia
     *
     * @return void
     */
    public function ajax_delete_province() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_send_json_error(esc_html__('Método no permitido', 'ygb-city'), 405);
        }
        
        if (!check_ajax_referer('ygb_admin_nonce', 'nonce', false)) {
            wp_send_json_error(esc_html__('Nonce inválido.', 'ygb-city'), 403);
        }
        
        if (!current_user_can($this->capability)) {
            wp_send_json_error(esc_html__('No tienes permisos.', 'ygb-city'), 403);
        }
        
        $province_id = isset($_POST['province_id']) ? absint($_POST['province_id']) : 0;
        
        if ($province_id <= 0) {
            wp_send_json_error(esc_html__('ID de provincia no válido.', 'ygb-city'), 400);
        }
        
        // Verificar si tiene municipios asociados
        $cities = YGB_City_Database::get_cities_by_province($province_id);
        if (!empty($cities)) {
            wp_send_json_error(esc_html__('No se puede eliminar: esta provincia tiene municipios asociados.', 'ygb-city'), 400);
        }
        
        $result = YGB_City_Database::delete_province($province_id);
        
        if ($result) {
            wp_send_json_success(array('message' => esc_html__('Provincia eliminada correctamente.', 'ygb-city')));
        } else {
            wp_send_json_error(esc_html__('Error al eliminar la provincia.', 'ygb-city'), 500);
        }
    }
    
    /**
     * AJAX: Añadir municipio
     *
     * @return void
     */
    public function ajax_add_city() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_send_json_error(esc_html__('Método no permitido', 'ygb-city'), 405);
        }
        
        if (!check_ajax_referer('ygb_admin_nonce', 'nonce', false)) {
            wp_send_json_error(esc_html__('Nonce inválido.', 'ygb-city'), 403);
        }
        
        if (!current_user_can($this->capability)) {
            wp_send_json_error(esc_html__('No tienes permisos.', 'ygb-city'), 403);
        }
        
        $province_id = isset($_POST['province_id']) ? absint($_POST['province_id']) : 0;
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $cost = isset($_POST['cost']) ? floatval(wp_unslash($_POST['cost'])) : 0;
        
        if ($province_id <= 0 || empty($name)) {
            wp_send_json_error(esc_html__('Provincia y nombre son requeridos.', 'ygb-city'), 400);
        }
        
        if (strlen($name) > 100) {
            wp_send_json_error(esc_html__('El nombre no puede exceder 100 caracteres.', 'ygb-city'), 400);
        }
        
        if ($cost < 0) {
            $cost = 0;
        }
        
        $result = YGB_City_Database::add_city($province_id, $name, $cost);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => esc_html__('Municipio añadido correctamente.', 'ygb-city'),
                'id' => $result
            ));
        } else {
            wp_send_json_error(esc_html__('Error al añadir el municipio.', 'ygb-city'), 500);
        }
    }
    
    /**
     * AJAX: Actualizar costo de envío
     *
     * @return void
     */
    public function ajax_update_cost() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_send_json_error(esc_html__('Método no permitido', 'ygb-city'), 405);
        }
        
        if (!check_ajax_referer('ygb_admin_nonce', 'nonce', false)) {
            wp_send_json_error(esc_html__('Nonce inválido.', 'ygb-city'), 403);
        }
        
        if (!current_user_can($this->capability)) {
            wp_send_json_error(esc_html__('No tienes permisos.', 'ygb-city'), 403);
        }
        
        $city_id = isset($_POST['city_id']) ? absint($_POST['city_id']) : 0;
        $cost = isset($_POST['cost']) ? floatval(wp_unslash($_POST['cost'])) : 0;
        
        if ($city_id <= 0) {
            wp_send_json_error(esc_html__('ID de municipio no válido.', 'ygb-city'), 400);
        }
        
        if ($cost < 0) {
            $cost = 0;
        }
        
        $result = YGB_City_Database::update_city_cost($city_id, $cost);
        
        if ($result !== false) {
            wp_send_json_success(array('message' => esc_html__('Costo actualizado correctamente.', 'ygb-city')));
        } else {
            wp_send_json_error(esc_html__('Error al actualizar el costo.', 'ygb-city'), 500);
        }
    }
    
    /**
     * AJAX: Eliminar municipio
     *
     * @return void
     */
    public function ajax_delete_city() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_send_json_error(esc_html__('Método no permitido', 'ygb-city'), 405);
        }
        
        if (!check_ajax_referer('ygb_admin_nonce', 'nonce', false)) {
            wp_send_json_error(esc_html__('Nonce inválido.', 'ygb-city'), 403);
        }
        
        if (!current_user_can($this->capability)) {
            wp_send_json_error(esc_html__('No tienes permisos.', 'ygb-city'), 403);
        }
        
        $city_id = isset($_POST['city_id']) ? absint($_POST['city_id']) : 0;
        
        if ($city_id <= 0) {
            wp_send_json_error(esc_html__('ID de municipio no válido.', 'ygb-city'), 400);
        }
        
        $result = YGB_City_Database::delete_city($city_id);
        
        if ($result) {
            wp_send_json_success(array('message' => esc_html__('Municipio eliminado correctamente.', 'ygb-city')));
        } else {
            wp_send_json_error(esc_html__('Error al eliminar el municipio.', 'ygb-city'), 500);
        }
    }
}