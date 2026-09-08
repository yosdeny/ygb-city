<?php
/**
 * Admin Page Template
 * 
 * @package YGB_City
 * @version 2.4.1
 */

if (!defined('ABSPATH')) {
    exit;
}

$provinces = YGB_City_Database::get_provinces();
$all_cities = YGB_City_Database::get_all_cities();
$stats = YGB_City_Database::get_stats();
$active_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'provinces';
?>

<div class="wrap ygb-city-admin">
    <h1><?php echo esc_html__('YGB City Shipping - Configuración', 'ygb-city'); ?></h1>
    
    <div class="nav-tab-wrapper">
        <a href="?page=ygb-city&tab=provinces" class="nav-tab <?php echo $active_tab === 'provinces' ? 'nav-tab-active' : ''; ?>">
            <?php echo esc_html__('Provincias', 'ygb-city'); ?>
        </a>
        <a href="?page=ygb-city&tab=cities" class="nav-tab <?php echo $active_tab === 'cities' ? 'nav-tab-active' : ''; ?>">
            <?php echo esc_html__('Municipios', 'ygb-city'); ?>
        </a>
        <a href="?page=ygb-city&tab=settings" class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
            <?php echo esc_html__('Ajustes', 'ygb-city'); ?>
        </a>
        <a href="?page=ygb-city&tab=import-export" class="nav-tab <?php echo $active_tab === 'import-export' ? 'nav-tab-active' : ''; ?>">
            <?php echo esc_html__('Importar/Exportar', 'ygb-city'); ?>
        </a>
        <a href="?page=ygb-city&tab=logs" class="nav-tab <?php echo $active_tab === 'logs' ? 'nav-tab-active' : ''; ?>">
            <?php echo esc_html__('Logs', 'ygb-city'); ?>
        </a>
    </div>
    
    <!-- Provincias -->
    <div id="provinces" class="tab-content" style="display: <?php echo $active_tab === 'provinces' ? 'block' : 'none'; ?>">
        <h2><?php echo esc_html__('Gestionar Provincias', 'ygb-city'); ?></h2>
        
        <form id="add-province-form" class="ygb-form">
            <table class="form-table">
                <tr>
                    <th><label for="province_name"><?php echo esc_html__('Nombre de la provincia', 'ygb-city'); ?></label></th>
                    <td>
                        <input type="text" id="province_name" name="province_name" required maxlength="100" style="width: 300px;">
                        <p class="description"><?php echo esc_html__('Ej: Madrid, Barcelona, Valencia', 'ygb-city'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="province_code"><?php echo esc_html__('Código', 'ygb-city'); ?></label></th>
                    <td>
                        <input type="text" id="province_code" name="province_code" required maxlength="50" style="width: 150px;">
                        <p class="description"><?php echo esc_html__('Código identificador (Ej: M, B, V)', 'ygb-city'); ?></p>
                    </td>
                </tr>
            </table>
            <button type="submit" class="button button-primary"><?php echo esc_html__('Añadir Provincia', 'ygb-city'); ?></button>
        </form>
        
        <h3><?php echo esc_html__('Provincias existentes', 'ygb-city'); ?></h3>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th width="50"><?php echo esc_html__('ID', 'ygb-city'); ?></th>
                    <th><?php echo esc_html__('Nombre', 'ygb-city'); ?></th>
                    <th width="100"><?php echo esc_html__('Código', 'ygb-city'); ?></th>
                    <th width="100"><?php echo esc_html__('Municipios', 'ygb-city'); ?></th>
                    <th width="120"><?php echo esc_html__('Acciones', 'ygb-city'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($provinces)): ?>
                <tr>
                    <td colspan="5"><?php echo esc_html__('No hay provincias registradas. Añade la primera usando el formulario.', 'ygb-city'); ?></td>
                </tr>
                <?php else: ?>
                    <?php foreach ($provinces as $province): 
                        $cities_count = count(YGB_City_Database::get_cities_by_province($province->id));
                    ?>
                    <tr data-province-id="<?php echo esc_attr($province->id); ?>">
                        <td><?php echo intval($province->id); ?></td>
                        <td><?php echo esc_html($province->name); ?></td>
                        <td><?php echo esc_html($province->code); ?></td>
                        <td><?php echo intval($cities_count); ?> <?php echo esc_html__('municipio(s)', 'ygb-city'); ?></td>
                        <td>
                            <?php if ($cities_count === 0): ?>
                                <button type="button" class="button button-small delete-province"><?php echo esc_html__('Eliminar', 'ygb-city'); ?></button>
                            <?php else: ?>
                                <span class="description" style="color: #856404;"><?php echo esc_html__('Tiene municipios', 'ygb-city'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Municipios -->
    <div id="cities" class="tab-content" style="display: <?php echo $active_tab === 'cities' ? 'block' : 'none'; ?>">
        <h2><?php echo esc_html__('Gestionar Municipios', 'ygb-city'); ?></h2>
        
        <form id="add-city-form" class="ygb-form">
            <table class="form-table">
                <tr>
                    <th><label for="province_id"><?php echo esc_html__('Provincia', 'ygb-city'); ?></label></th>
                    <td>
                        <select id="province_id" name="province_id" required style="width: 300px;">
                            <option value=""><?php echo esc_html__('Selecciona provincia', 'ygb-city'); ?></option>
                            <?php foreach ($provinces as $province): ?>
                                <option value="<?php echo esc_attr($province->id); ?>"><?php echo esc_html($province->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="city_name"><?php echo esc_html__('Municipio', 'ygb-city'); ?></label></th>
                    <td>
                        <input type="text" id="city_name" name="city_name" required maxlength="100" style="width: 300px;">
                        <p class="description"><?php echo esc_html__('Nombre del municipio', 'ygb-city'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="shipping_cost"><?php echo esc_html__('Costo de envío', 'ygb-city'); ?></label></th>
                    <td>
                        <input type="number" id="shipping_cost" name="shipping_cost" step="0.01" min="0" required style="width: 150px;">
                        <span>€</span>
                        <p class="description"><?php echo esc_html__('Costo de envío para este municipio', 'ygb-city'); ?></p>
                    </td>
                </tr>
            </table>
            <button type="submit" class="button button-primary"><?php echo esc_html__('Añadir Municipio', 'ygb-city'); ?></button>
        </form>
        
        <h3><?php echo esc_html__('Municipios existentes', 'ygb-city'); ?></h3>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th width="50"><?php echo esc_html__('ID', 'ygb-city'); ?></th>
                    <th><?php echo esc_html__('Provincia', 'ygb-city'); ?></th>
                    <th><?php echo esc_html__('Municipio', 'ygb-city'); ?></th>
                    <th width="150"><?php echo esc_html__('Costo de envío', 'ygb-city'); ?></th>
                    <th width="120"><?php echo esc_html__('Acciones', 'ygb-city'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($all_cities)): ?>
                <tr>
                    <td colspan="5"><?php echo esc_html__('No hay municipios registrados. Añade el primero usando el formulario.', 'ygb-city'); ?></td>
                </tr>
                <?php else: ?>
                    <?php foreach ($all_cities as $city): ?>
                    <tr data-city-id="<?php echo esc_attr($city->id); ?>">
                        <td><?php echo intval($city->id); ?></td>
                        <td><?php echo esc_html($city->province_name); ?></td>
                        <td><?php echo esc_html($city->name); ?></td>
                        <td>
                            <input type="number" class="shipping-cost" value="<?php echo esc_attr($city->shipping_cost); ?>" step="0.01" min="0" style="width: 100px;">
                            <span>€</span>
                        </td>
                        <td>
                            <button type="button" class="button button-small update-cost"><?php echo esc_html__('Actualizar', 'ygb-city'); ?></button>
                            <button type="button" class="button button-small delete-city"><?php echo esc_html__('Eliminar', 'ygb-city'); ?></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Ajustes -->
    <div id="settings" class="tab-content" style="display: <?php echo $active_tab === 'settings' ? 'block' : 'none'; ?>">
        <h2><?php echo esc_html__('Ajustes Generales', 'ygb-city'); ?></h2>
        
        <form method="post" action="">
            <?php wp_nonce_field('ygb_save_settings'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php echo esc_html__('Activar sistema', 'ygb-city'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="enabled" value="1" <?php checked(get_option('ygb_city_enabled', 'yes'), 'yes'); ?>>
                            <?php echo esc_html__('Habilitar envíos por municipio', 'ygb-city'); ?>
                        </label>
                        <p class="description"><?php echo esc_html__('Si está desactivado, se usará el método de envío estándar de WooCommerce', 'ygb-city'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Costo por defecto', 'ygb-city'); ?></th>
                    <td>
                        <input type="number" 
                               name="default_cost" 
                               value="<?php echo esc_attr(get_option('ygb_city_default_cost', '5.00')); ?>" 
                               step="0.01" 
                               min="0"
                               style="width: 150px;">
                        <span>€</span>
                        <p class="description"><?php echo esc_html__('Costo aplicado cuando no se encuentra el municipio seleccionado', 'ygb-city'); ?></p>
                    </td>
                </tr>
                <?php if ($stats['total_provinces'] > 0 || $stats['total_cities'] > 0): ?>
                <tr>
                    <th scope="row"><?php echo esc_html__('Estadísticas', 'ygb-city'); ?></th>
                    <td>
                        <ul>
                            <li><strong><?php echo esc_html__('Provincias:', 'ygb-city'); ?></strong> <?php echo intval($stats['total_provinces']); ?></li>
                            <li><strong><?php echo esc_html__('Municipios:', 'ygb-city'); ?></strong> <?php echo intval($stats['total_cities']); ?></li>
                            <?php if ($stats['avg_shipping_cost']): ?>
                            <li><strong><?php echo esc_html__('Costo promedio:', 'ygb-city'); ?></strong> <?php echo wp_kses_post(wc_price($stats['avg_shipping_cost'])); ?></li>
                            <li><strong><?php echo esc_html__('Costo mínimo:', 'ygb-city'); ?></strong> <?php echo wp_kses_post(wc_price($stats['min_shipping_cost'])); ?></li>
                            <li><strong><?php echo esc_html__('Costo máximo:', 'ygb-city'); ?></strong> <?php echo wp_kses_post(wc_price($stats['max_shipping_cost'])); ?></li>
                            <?php endif; ?>
                        </ul>
                    </td>
                </tr>
                <?php endif; ?>
            </table>
            
            <p class="submit">
                <button type="submit" name="ygb_save_settings" class="button button-primary">
                    <?php echo esc_html__('Guardar Ajustes', 'ygb-city'); ?>
                </button>
            </p>
        </form>
    </div>
    
    <!-- Importar/Exportar -->
    <div id="import-export" class="tab-content" style="display: <?php echo $active_tab === 'import-export' ? 'block' : 'none'; ?>">
        <h2><?php echo esc_html__('Importar / Exportar Datos', 'ygb-city'); ?></h2>
        
        <div class="ygb-export-section" style="background: #f9f9f9; padding: 20px; margin-bottom: 30px; border: 1px solid #ddd; border-radius: 4px;">
            <h3>📤 <?php echo esc_html__('Exportar Datos', 'ygb-city'); ?></h3>
            <p><?php echo esc_html__('Exporta tus datos de envío a un archivo CSV.', 'ygb-city'); ?></p>
            
            <form method="post" action="">
                <?php wp_nonce_field('ygb_export_action'); ?>
                <select name="export_type" style="width: 200px;">
                    <option value="full"><?php echo esc_html__('Exportar todos los datos (provincias + municipios)', 'ygb-city'); ?></option>
                    <option value="template"><?php echo esc_html__('Descargar plantilla de importación', 'ygb-city'); ?></option>
                </select>
                <button type="submit" name="ygb_export" class="button button-primary"><?php echo esc_html__('Exportar CSV', 'ygb-city'); ?></button>
            </form>
        </div>
        
        <div class="ygb-import-section" style="background: #f9f9f9; padding: 20px; margin-bottom: 30px; border: 1px solid #ddd; border-radius: 4px;">
            <h3>📥 <?php echo esc_html__('Importar Datos', 'ygb-city'); ?></h3>
            <p><?php echo esc_html__('Importa datos desde un archivo CSV. El archivo debe tener el siguiente formato:', 'ygb-city'); ?></p>
            <pre style="background: #fff; padding: 10px; border: 1px solid #ddd; overflow-x: auto;">Provincia Código,Provincia Nombre,Municipio,Costo de Envío (€)
M,Madrid,Madrid Capital,5.00
B,Barcelona,Barcelona Capital,5.50</pre>
            
            <form method="post" action="" enctype="multipart/form-data">
                <?php wp_nonce_field('ygb_import_action'); ?>
                <table class="form-table">
                    <tr>
                        <th><?php echo esc_html__('Archivo CSV', 'ygb-city'); ?></th>
                        <td>
                            <input type="file" name="import_file" accept=".csv" required>
                            <p class="description"><?php echo esc_html__('Selecciona un archivo CSV para importar (máximo 5 MB)', 'ygb-city'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Opciones', 'ygb-city'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="overwrite" value="1">
                                <?php echo esc_html__('Sobrescribir municipios existentes (si no está marcado, solo añade nuevos)', 'ygb-city'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                <button type="submit" name="ygb_import" class="button button-primary" onclick="return confirm('<?php echo esc_js(__('¿Estás seguro de importar estos datos?', 'ygb-city')); ?>')"><?php echo esc_html__('Importar CSV', 'ygb-city'); ?></button>
            </form>
        </div>
        
        <div class="ygb-clear-section" style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-left: 4px solid #dc3232; border-radius: 4px;">
            <h3 style="color: #dc3232;">⚠️ <?php echo esc_html__('Limpiar Todos los Datos', 'ygb-city'); ?></h3>
            <p><strong><?php echo esc_html__('¡Advertencia!', 'ygb-city'); ?></strong> <?php echo esc_html__('Esta acción eliminará TODAS las provincias y municipios que has creado. Esta acción no se puede deshacer.', 'ygb-city'); ?></p>
            
            <form method="post" action="">
                <?php wp_nonce_field('ygb_clear_action'); ?>
                <label>
                    <input type="checkbox" name="confirm_clear" value="yes" required>
                    <?php echo esc_html__('Confirmo que quiero eliminar todos los datos', 'ygb-city'); ?>
                </label>
                <br><br>
                <button type="submit" name="ygb_clear" class="button" style="background: #dc3232; color: #fff; border-color: #dc3232;" onclick="return confirm('<?php echo esc_js(__('⚠️ ¿ESTÁS ABSOLUTAMENTE SEGURO? Esto eliminará TODOS los datos de provincias y municipios.', 'ygb-city')); ?>')"><?php echo esc_html__('Eliminar Todos los Datos', 'ygb-city'); ?></button>
            </form>
        </div>
    </div>
</div>

<style>
.ygb-city-admin .nav-tab-wrapper {
    margin-bottom: 20px;
}
.ygb-city-admin .tab-content {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}
.ygb-city-admin .form-table th {
    width: 200px;
}
.ygb-city-admin .shipping-cost {
    text-align: right;
}
.ygb-city-admin .button-small {
    margin-right: 5px;
}
.ygb-city-admin .description {
    color: #666;
}
.ygb-city-admin .shipping-cost.ygb-update-success {
    background-color: #d4edda;
    transition: background-color 0.3s ease;
}
</style>