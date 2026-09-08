<?php
/**
 * Admin Logs Template - Versión 2.4.0
 * 
 * @package YGB_City
 */

if (!defined('ABSPATH')) {
    exit;
}

$logs = YGB_City_Database::get_activity_logs(100);
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
        <h2><?php echo esc_html__('Registro de Actividad', 'ygb-city'); ?></h2>
        
        <?php if (empty($logs)): ?>
            <div class="notice notice-info">
                <p><?php echo esc_html__('No hay registros de actividad disponibles.', 'ygb-city'); ?></p>
            </div>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th width="180"><?php echo esc_html__('Fecha', 'ygb-city'); ?></th>
                        <th width="100"><?php echo esc_html__('Tipo', 'ygb-city'); ?></th>
                        <th width="150"><?php echo esc_html__('Usuario ID', 'ygb-city'); ?></th>
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