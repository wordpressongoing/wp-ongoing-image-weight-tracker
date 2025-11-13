<?php
/**
 * Script temporal para probar traducciones
 * Ejecutar desde WordPress: wp-admin/admin.php?page=test-translations
 * O desde línea de comandos con wp-load.php
 */

// Cargar WordPress si no está cargado
if (!defined('ABSPATH')) {
    require_once dirname(__DIR__, 4) . '/wp-load.php';
}

echo '<h1>Test de Traducciones - Image Weight Tracker</h1>';

// Información del idioma actual
echo '<h2>Configuración de Idioma</h2>';
echo '<p><strong>Locale actual:</strong> ' . get_locale() . '</p>';
echo '<p><strong>Idioma del sitio:</strong> ' . get_bloginfo('language') . '</p>';

// Probar carga del text domain
echo '<h2>Carga de Text Domain</h2>';
$plugin_dir = plugin_dir_path(__FILE__);
$loaded = load_plugin_textdomain(
    'wp-ongoing-image-weight-tracker',
    false,
    dirname(plugin_basename(__FILE__)) . '/languages'
);
echo '<p><strong>Text domain cargado:</strong> ' . ($loaded ? '✓ SÍ' : '✗ NO') . '</p>';

// Verificar archivos
echo '<h2>Archivos de Traducción</h2>';
$languages_dir = $plugin_dir . 'languages/';
$locale = get_locale();

$mo_file = $languages_dir . "wp-ongoing-image-weight-tracker-{$locale}.mo";
$po_file = $languages_dir . "wp-ongoing-image-weight-tracker-{$locale}.po";

echo '<p><strong>Directorio languages:</strong> ' . ($languages_dir) . '</p>';
echo '<p><strong>Archivo .mo existe:</strong> ' . (file_exists($mo_file) ? '✓ SÍ - ' . $mo_file : '✗ NO - ' . $mo_file) . '</p>';
echo '<p><strong>Archivo .po existe:</strong> ' . (file_exists($po_file) ? '✓ SÍ' : '✗ NO') . '</p>';

// Probar traducciones
echo '<h2>Prueba de Traducciones</h2>';

$tests = [
    'Image Weight Tracker' => 'Rastreador de Peso de Imágenes',
    'Re-scan' => 'Re-escanear',
    'Heavy' => 'Pesada',
    'Medium' => 'Media',
    'Optimal' => 'Óptima',
    'Search image or page…' => 'Buscar imagen o página…',
    'All' => 'Todos',
];

echo '<table border="1" cellpadding="5" style="border-collapse: collapse;">';
echo '<tr><th>Original (inglés)</th><th>Esperado (español)</th><th>Resultado</th><th>Estado</th></tr>';

foreach ($tests as $original => $expected) {
    $translated = __($original, 'wp-ongoing-image-weight-tracker');
    $match = ($translated === $expected);
    
    echo '<tr>';
    echo '<td>' . esc_html($original) . '</td>';
    echo '<td>' . esc_html($expected) . '</td>';
    echo '<td>' . esc_html($translated) . '</td>';
    echo '<td style="color: ' . ($match ? 'green' : 'red') . ';">' . ($match ? '✓ OK' : '✗ FALLO') . '</td>';
    echo '</tr>';
}

echo '</table>';

// Información de depuración
echo '<h2>Información de Depuración</h2>';
echo '<p><strong>Traducciones globales cargadas:</strong></p>';
global $l10n;
echo '<pre>';
if (isset($l10n['wp-ongoing-image-weight-tracker'])) {
    echo '✓ Text domain "wp-ongoing-image-weight-tracker" está cargado' . PHP_EOL;
    $mo = $l10n['wp-ongoing-image-weight-tracker'];
    echo 'Número de entradas: ' . count($mo->entries) . PHP_EOL;
} else {
    echo '✗ Text domain "wp-ongoing-image-weight-tracker" NO está cargado' . PHP_EOL;
}
echo '</pre>';

echo '<hr>';
echo '<p><a href="' . admin_url('admin.php?page=wp-ongoing-image-weight-tracker') . '">Ir al plugin Image Weight Tracker</a></p>';
