<?php
/**
 * Script de depuración simple
 * Ejecutar desde la raíz de WordPress
 */

require_once 'wp-load.php';

echo "=== DEPURACIÓN DE TRADUCCIONES ===" . PHP_EOL . PHP_EOL;

// 1. Configuración actual
echo "1. CONFIGURACIÓN:" . PHP_EOL;
echo "   Locale: " . get_locale() . PHP_EOL;
echo "   Idioma: " . get_bloginfo('language') . PHP_EOL . PHP_EOL;

// 2. Rutas del plugin
$plugin_dir = WP_PLUGIN_DIR . '/wp-ongoing-image-weight-tracker/';
$languages_dir = $plugin_dir . 'languages/';
echo "2. RUTAS:" . PHP_EOL;
echo "   Plugin: " . $plugin_dir . PHP_EOL;
echo "   Languages: " . $languages_dir . PHP_EOL . PHP_EOL;

// 3. Archivos
$locale = get_locale();
$mo_file = $languages_dir . "wp-ongoing-image-weight-tracker-{$locale}.mo";
$po_file = $languages_dir . "wp-ongoing-image-weight-tracker-{$locale}.po";

echo "3. ARCHIVOS:" . PHP_EOL;
echo "   .mo: " . ($mo_file) . PHP_EOL;
echo "   Existe: " . (file_exists($mo_file) ? 'SÍ' : 'NO') . PHP_EOL;
echo "   Tamaño: " . (file_exists($mo_file) ? filesize($mo_file) . ' bytes' : 'N/A') . PHP_EOL;
echo "   .po: " . (file_exists($po_file) ? 'SÍ' : 'NO') . PHP_EOL . PHP_EOL;

// 4. Cargar text domain manualmente
echo "4. CARGA MANUAL:" . PHP_EOL;
$loaded = load_plugin_textdomain(
    'wp-ongoing-image-weight-tracker',
    false,
    'wp-ongoing-image-weight-tracker/languages'
);
echo "   Resultado: " . ($loaded ? 'ÉXITO' : 'FALLO') . PHP_EOL . PHP_EOL;

// 5. Verificar carga global
echo "5. VERIFICACIÓN GLOBAL:" . PHP_EOL;
global $l10n;
if (isset($l10n['wp-ongoing-image-weight-tracker'])) {
    echo "   Text domain cargado: SÍ" . PHP_EOL;
    $mo_obj = $l10n['wp-ongoing-image-weight-tracker'];
    echo "   Entradas: " . count($mo_obj->entries) . PHP_EOL . PHP_EOL;
} else {
    echo "   Text domain cargado: NO" . PHP_EOL . PHP_EOL;
}

// 6. Probar traducción
echo "6. PRUEBA DE TRADUCCIÓN:" . PHP_EOL;
$test = __('Re-scan', 'wp-ongoing-image-weight-tracker');
echo "   Original: 'Re-scan'" . PHP_EOL;
echo "   Traducido: '{$test}'" . PHP_EOL;
echo "   Estado: " . ($test === 'Re-escanear' ? 'OK' : 'FALLO') . PHP_EOL . PHP_EOL;

$test2 = __('Heavy', 'wp-ongoing-image-weight-tracker');
echo "   Original: 'Heavy'" . PHP_EOL;
echo "   Traducido: '{$test2}'" . PHP_EOL;
echo "   Estado: " . ($test2 === 'Pesada' ? 'OK' : 'FALLO') . PHP_EOL . PHP_EOL;

echo "=== FIN DEPURACIÓN ===" . PHP_EOL;
