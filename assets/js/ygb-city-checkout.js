/**
 * YGB City Checkout Scripts
 * Sistema de selección de provincia y municipio con costos de envío
 * @version 2.3.0
 */
jQuery(document).ready(function($) {
    
    console.log('YGB City Checkout JS cargado');
    
    var provincesData = [];
    
    /**
     * Cargar datos de provincias desde el objeto localizado
     */
    function loadProvincesData() {
        if (typeof ygb_frontend !== 'undefined' && ygb_frontend.provinces) {
            try {
                provincesData = JSON.parse(ygb_frontend.provinces);
                console.log('Provincias cargadas:', provincesData.length);
            } catch(e) {
                console.error('Error al parsear provincias:', e);
            }
        }
    }
    
    /**
     * Escapar HTML para prevenir XSS
     */
    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }
    
    /**
     * Manejar cambio de provincia
     */
    function handleProvinceChange() {
        $('#ygb_province').on('change', function() {
            var provinceId = $(this).val();
            var $citySelect = $('#ygb_city');
            
            $citySelect.empty();
            $citySelect.append('<option value="">' + escapeHtml(ygb_frontend.select_city || 'Selecciona un municipio') + '</option>');
            
            if (provinceId && provincesData.length > 0) {
                var province = provincesData.find(function(p) {
                    return p.id == provinceId;
                });
                
                if (province && province.cities && province.cities.length > 0) {
                    $.each(province.cities, function(index, city) {
                        $citySelect.append(
                            '<option value="' + escapeHtml(city.id) + '" data-cost="' + escapeHtml(city.shipping_cost) + '">' + 
                            escapeHtml(city.name) + 
                            '</option>'
                        );
                    });
                    $citySelect.prop('disabled', false);
                } else {
                    $citySelect.append('<option value="">' + escapeHtml(ygb_frontend.no_cities || 'No hay municipios disponibles') + '</option>');
                    $citySelect.prop('disabled', true);
                }
            } else {
                $citySelect.prop('disabled', true);
            }
        });
    }
    
    /**
     * Manejar cambio de municipio
     */
    function handleCityChange() {
        $('#ygb_city').on('change', function() {
            var cityId = $(this).val();
            var $select = $(this);
            
            if (cityId) {
                var selectedOption = $select.find('option:selected');
                var cost = selectedOption.data('cost');
                
                if (cost !== undefined && cost > 0) {
                    var costFormatted = new Intl.NumberFormat('es-ES', {
                        style: 'currency',
                        currency: 'EUR'
                    }).format(cost);
                    console.log('Costo seleccionado:', costFormatted);
                }
                
                $.ajax({
                    url: ygb_frontend.ajax_url,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'update_shipping_cost',
                        city_id: cityId,
                        nonce: ygb_frontend.nonce
                    },
                    beforeSend: function() {
                        $select.css('opacity', '0.6');
                    },
                    success: function(response) {
                        $select.css('opacity', '1');
                        
                        if (response.success) {
                            console.log('Costo actualizado:', response.data.cost_formatted);
                            $(document.body).trigger('update_checkout');
                        } else {
                            console.error('Error:', response.data);
                            $select.css('border-color', '#dc3232');
                            setTimeout(function() {
                                $select.css('border-color', '');
                            }, 2000);
                        }
                    },
                    error: function(xhr, status, error) {
                        $select.css('opacity', '1');
                        console.error('Error AJAX:', error, xhr.responseText);
                        $select.css('border-color', '#dc3232');
                        setTimeout(function() {
                            $select.css('border-color', '');
                        }, 2000);
                    }
                });
            }
        });
    }
    
    /**
     * Validar formulario antes de enviar
     */
    function validateCheckout() {
        $(document.body).on('checkout_place_order', function() {
            var province = $('#ygb_province').val();
            var city = $('#ygb_city').val();
            
            if (!province) {
                alert(ygb_frontend.select_province_error || 'Por favor, selecciona una provincia');
                $('#ygb_province').focus();
                return false;
            }
            
            if (!city) {
                alert(ygb_frontend.select_city_error || 'Por favor, selecciona un municipio');
                $('#ygb_city').focus();
                return false;
            }
            
            return true;
        });
    }
    
    /**
     * Inicializar todo
     */
    function init() {
        loadProvincesData();
        
        var checkExist = setInterval(function() {
            if ($('#ygb_province').length) {
                clearInterval(checkExist);
                handleProvinceChange();
                handleCityChange();
                validateCheckout();
                console.log('YGB City Checkout inicializado correctamente');
            }
        }, 100);
    }
    
    init();
});