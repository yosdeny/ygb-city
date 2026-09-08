/**
 * YGB City Admin Scripts - Versión 2.4.0
 * Gestión de provincias y municipios en el panel de administración
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        
        if (typeof ygb_admin === 'undefined') {
            console.error('YGB City Admin: variables no definidas');
            return;
        }

        function showError(message) {
            if (message && message.length) {
                alert(message);
            } else {
                alert(ygb_admin.ajax_error || 'Error de conexión');
            }
        }

        function showSuccess(message) {
            if (message) {
                alert('✅ ' + message);
            }
        }

        /**
         * Añadir provincia
         */
        $('#add-province-form').on('submit', function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $button = $form.find('button[type="submit"]');
            var provinceName = $form.find('input[name="province_name"]').val().trim();
            var provinceCode = $form.find('input[name="province_code"]').val().trim();
            
            if (!provinceName) {
                showError('Por favor, introduce el nombre de la provincia.');
                return;
            }
            
            if (!provinceCode) {
                showError('Por favor, introduce el código de la provincia.');
                return;
            }
            
            if (provinceCode.length > 10) {
                showError('El código no puede tener más de 10 caracteres.');
                return;
            }
            
            $.ajax({
                url: ygb_admin.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'ygb_add_province',
                    name: provinceName,
                    code: provinceCode,
                    nonce: ygb_admin.nonce
                },
                beforeSend: function() {
                    $button.prop('disabled', true);
                    $button.text('Guardando...');
                },
                success: function(response) {
                    if (response.success) {
                        showSuccess(response.data.message);
                        location.reload();
                    } else {
                        showError('Error: ' + (response.data || 'No se pudo añadir la provincia'));
                        $button.prop('disabled', false);
                        $button.text('Añadir Provincia');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error, xhr.responseText);
                    showError(ygb_admin.ajax_error);
                    $button.prop('disabled', false);
                    $button.text('Añadir Provincia');
                }
            });
        });

        /**
         * Eliminar provincia
         */
        $(document).on('click', '.delete-province', function() {
            if (!confirm('⚠️ ¿Estás seguro de que quieres eliminar esta provincia?\n\nEsta acción no se puede deshacer.')) {
                return;
            }
            
            var $row = $(this).closest('tr');
            var provinceId = $row.data('province-id');
            var $button = $(this);
            var originalText = $button.text();
            
            $.ajax({
                url: ygb_admin.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'ygb_delete_province',
                    province_id: provinceId,
                    nonce: ygb_admin.nonce
                },
                beforeSend: function() {
                    $button.prop('disabled', true);
                    $button.text('Eliminando...');
                },
                success: function(response) {
                    if (response.success) {
                        showSuccess(response.data.message);
                        $row.fadeOut(400, function() {
                            $(this).remove();
                        });
                    } else {
                        showError('Error: ' + (response.data || 'No se pudo eliminar la provincia'));
                        $button.prop('disabled', false);
                        $button.text(originalText);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error, xhr.responseText);
                    showError(ygb_admin.ajax_error);
                    $button.prop('disabled', false);
                    $button.text(originalText);
                }
            });
        });

        /**
         * Añadir municipio
         */
        $('#add-city-form').on('submit', function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $button = $form.find('button[type="submit"]');
            var provinceId = $form.find('select[name="province_id"]').val();
            var cityName = $form.find('input[name="city_name"]').val().trim();
            var shippingCost = $form.find('input[name="shipping_cost"]').val();
            
            if (!provinceId) {
                showError('Por favor, selecciona una provincia.');
                return;
            }
            
            if (!cityName) {
                showError('Por favor, introduce el nombre del municipio.');
                return;
            }
            
            if (cityName.length > 100) {
                showError('El nombre del municipio no puede exceder 100 caracteres.');
                return;
            }
            
            var cost = parseFloat(shippingCost);
            if (isNaN(cost) || cost < 0) {
                showError('Por favor, introduce un costo válido (mayor o igual a 0).');
                return;
            }
            
            $.ajax({
                url: ygb_admin.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'ygb_add_city',
                    province_id: provinceId,
                    name: cityName,
                    cost: cost,
                    nonce: ygb_admin.nonce
                },
                beforeSend: function() {
                    $button.prop('disabled', true);
                    $button.text('Guardando...');
                },
                success: function(response) {
                    if (response.success) {
                        showSuccess(response.data.message);
                        location.reload();
                    } else {
                        showError('Error: ' + (response.data || 'No se pudo añadir el municipio'));
                        $button.prop('disabled', false);
                        $button.text('Añadir Municipio');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error, xhr.responseText);
                    showError(ygb_admin.ajax_error);
                    $button.prop('disabled', false);
                    $button.text('Añadir Municipio');
                }
            });
        });

        /**
         * Actualizar costo de envío
         */
        $(document).on('click', '.update-cost', function() {
            var $row = $(this).closest('tr');
            var cityId = $row.data('city-id');
            var $costInput = $row.find('.shipping-cost');
            var newCost = $costInput.val();
            var $button = $(this);
            var originalText = $button.text();
            
            var cost = parseFloat(newCost);
            if (isNaN(cost) || cost < 0) {
                showError('Por favor, introduce un costo válido (mayor o igual a 0).');
                $costInput.focus();
                return;
            }
            
            $.ajax({
                url: ygb_admin.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'ygb_update_cost',
                    city_id: cityId,
                    cost: cost,
                    nonce: ygb_admin.nonce
                },
                beforeSend: function() {
                    $button.prop('disabled', true);
                    $button.text('Actualizando...');
                },
                success: function(response) {
                    if (response.success) {
                        // ✅ Usar clase CSS en lugar de inline style
                        $costInput.addClass('ygb-update-success');
                        setTimeout(function() {
                            $costInput.removeClass('ygb-update-success');
                        }, 1000);
                        showSuccess(response.data.message);
                    } else {
                        showError('Error: ' + (response.data || 'No se pudo actualizar el costo'));
                    }
                    $button.prop('disabled', false);
                    $button.text(originalText);
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error, xhr.responseText);
                    showError(ygb_admin.ajax_error);
                    $button.prop('disabled', false);
                    $button.text(originalText);
                }
            });
        });

        /**
         * Eliminar municipio
         */
        $(document).on('click', '.delete-city', function() {
            if (!confirm('¿Estás seguro de que quieres eliminar este municipio?')) {
                return;
            }
            
            var $row = $(this).closest('tr');
            var cityId = $row.data('city-id');
            var $button = $(this);
            var originalText = $button.text();
            
            $.ajax({
                url: ygb_admin.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'ygb_delete_city',
                    city_id: cityId,
                    nonce: ygb_admin.nonce
                },
                beforeSend: function() {
                    $button.prop('disabled', true);
                    $button.text('Eliminando...');
                },
                success: function(response) {
                    if (response.success) {
                        showSuccess(response.data.message);
                        $row.fadeOut(400, function() {
                            $(this).remove();
                        });
                    } else {
                        showError('Error: ' + (response.data || 'No se pudo eliminar el municipio'));
                        $button.prop('disabled', false);
                        $button.text(originalText);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error, xhr.responseText);
                    showError(ygb_admin.ajax_error);
                    $button.prop('disabled', false);
                    $button.text(originalText);
                }
            });
        });

        /**
         * Mejora UX: Enter en campos numéricos actualiza automáticamente
         */
        $(document).on('keypress', '.shipping-cost', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                $(this).closest('tr').find('.update-cost').trigger('click');
            }
        });

        /**
         * Confirmación para limpieza de datos
         */
        $('button[name="ygb_clear"]').on('click', function(e) {
            var $checkbox = $('input[name="confirm_clear"]');
            if (!$checkbox.is(':checked')) {
                e.preventDefault();
                showError('Debes marcar la casilla de confirmación para eliminar todos los datos.');
            }
        });

        console.log('YGB City Admin JS inicializado correctamente (v2.4.0)');
    });

})(jQuery);