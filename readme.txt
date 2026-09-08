=== YGB City Shipping ===
Contributors: ygb
Tags: woocommerce, shipping, cities, provinces, delivery, checkout, shipping-cost
Requires at least: 7.0
Tested up to: 7.1
Stable tag: 2.6.0
Requires PHP: 8.0
Tested PHP: 8.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sistema de envío por provincia y municipio con costos personalizados para WooCommerce.

== Description ==

**YGB City Shipping** te permite gestionar envíos personalizados basados en provincia y municipio para tu tienda WooCommerce.

= Características principales =

* ✅ **Gestión completa de provincias** - Añade, edita y elimina provincias fácilmente
* ✅ **Municipios con costos individuales** - Define un costo de envío específico para cada municipio
* ✅ **Importación/Exportación CSV** - Importa grandes volúmenes de datos desde archivos CSV
* ✅ **Selección en checkout** - Los clientes seleccionan provincia y municipio al finalizar la compra
* ✅ **Cálculo automático** - El costo de envío se calcula automáticamente según el municipio seleccionado
* ✅ **Compatibilidad total** - Funciona con cualquier tema compatible con WooCommerce
* ✅ **Seguridad reforzada** - Nonces, escape de salidas y preparación de consultas SQL
* ✅ **Soporte multidioma** - Listo para traducción (español e inglés incluidos)
* ✅ **Registro de actividad** - Panel de logs para auditoría de cambios
* ✅ **Desinstalación limpia** - Elimina completamente los datos si se confirma

= ¿Cómo funciona? =

1. **Configuración inicial**: Añade las provincias donde realizas envíos
2. **Añade municipios**: Para cada provincia, añade los municipios con su costo de envío específico
3. **Importación masiva** (opcional): Usa el importador CSV para cargar cientos de municipios rápidamente
4. **Checkout**: Tus clientes verán los campos de provincia y municipio en el checkout
5. **Envío automático**: El costo se calcula y se añade automáticamente al carrito

= Formato del archivo CSV para importación =

El archivo CSV debe tener el siguiente formato:
Provincia Código,Provincia Nombre,Municipio,Costo de Envío (€)
M,Madrid,Madrid Capital,5.00
B,Barcelona,Barcelona Capital,5.50
V,Valencia,Valencia Capital,4.50

text

= Desinstalación =

Por defecto, al desinstalar el plugin **NO se eliminan los datos** para prevenir pérdidas accidentales.

Si deseas eliminar **TODOS** los datos (tablas, opciones y logs) al desinstalar, añade esta línea a tu archivo `wp-config.php`:

```php
define('YGB_CITY_CLEANUP_ON_UNINSTALL', true);
¡ADVERTENCIA! Esto eliminará permanentemente todas las provincias, municipios y registros de actividad.

== Installation ==

Sube la carpeta ygb-city-shipping al directorio /wp-content/plugins/

Activa el plugin a través del menú 'Plugins' en WordPress

Asegúrate que WooCommerce está activo

Ve a YGB City en el menú de administración

Configura tus provincias y municipios

== Changelog ==

= 2.6.0 =
Correcciones críticas de seguridad post-auditoría (08/09/2026):
* ELIMINADA exposición de shipping_cost en frontend (atributo data-cost en checkout.js)
* CORREGIDA vulnerabilidad SQL en cleanup de logs (ahora usa $wpdb->prepare)
* MEJORADA validación MIME en uploads CSV (verificación estricta de text/csv y application/vnd.ms-excel)
* REDUCIDOS mensajes de error detallados en producción (solo mostrar errores genéricos)
* Los costos ahora se obtienen exclusivamente vía AJAX autenticado
* Compatibilidad enterprise reforzada

= 2.5.0 =
Mejora completa de la limpieza de datos: ahora usa DELETE si TRUNCATE falla.
Manejo robusto de duplicados en importación (sin sobrescribir ya no genera errores falsos).
Uso de COLLATE en consultas de existencia para ignorar acentos y caracteres invisibles.
Eliminación de caracteres zero-width space (\x{200B}) en importación.
Logs detallados de errores en add_city e importación para facilitar depuración.
Compatibilidad asegurada con hosts que restringen TRUNCATE.

= 2.4.8 =
Mejoras de logs en add_city e importación.
Verificación de resultados en clear_all_data.

= 2.4.4 =
Corrección de exportación (elimina línea vacía al final).
Importación robusta con detección de líneas vacías y caracteres invisibles.

= 2.4.1 =
Añadida verificación de existencia de tabla de logs antes de truncar.
Mejorada la validación en la importación/exportación.
Añadido fichero uninstall.php con limpieza opcional de datos.
Mejorados los mensajes de límite de logs en la UI.

= 2.4.0 =
Añadido panel de logs de actividad en el admin.
Implementado WP_Filesystem para importación CSV.
Mejorado rate limiting con detección de proxy/CDN.
Añadidos headers anti-caché para página de checkout.
Refactorizado código para mejor mantenibilidad.

= 2.3.1 =
Corregida vulnerabilidad SQL injection.
Corregida vulnerabilidad CSRF en save_city_selection.
Eliminada exposición de costos en JSON del frontend.
Añadido wp_unslash antes de sanitizaciones.

= 2.3.0 =
Versión inicial con seguridad mejorada.

== Upgrade Notice ==

= 2.6.0 =
Actualización CRÍTICA de seguridad recomendada para todos los usuarios. Corrige exposición de costos en frontend y vulnerabilidad SQL en logs.

= 2.5.0 =
Actualización crítica que soluciona problemas de importación con duplicados y limpieza de datos. Recomendada para todos los usuarios.

== Frequently Asked Questions ==

= ¿Se eliminan los datos al desinstalar? =

No por defecto. Para eliminar todos los datos, define YGB_CITY_CLEANUP_ON_UNINSTALL como true en wp-config.php.

= ¿Es compatible con plugins de caché? =

Sí, el plugin añade automáticamente headers anti-caché en la página de checkout para evitar problemas.

= ¿Puedo importar cientos de municipios? =

Sí, el importador CSV soporta archivos de hasta 5MB y puede procesar miles de registros.

= ¿Los costos de envío se ven en el frontend? =

No. Los costos solo se envían al servidor y no se exponen en el código fuente.

== Screenshots ==

Pantalla de gestión de provincias

Pantalla de gestión de municipios

Panel de importación/exportación

Panel de logs de actividad

Checkout con selección de provincia/municipio

== Additional Info ==

Plugin requiere WooCommerce 5.0 o superior

Compatible con PHP 8.0 a 8.3

Soporte: https://tusitio.com/soporte