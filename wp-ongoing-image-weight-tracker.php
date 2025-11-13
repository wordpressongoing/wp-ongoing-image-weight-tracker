<?php
/**
 * Plugin Name: Image Weight Tracker by WP Ongoing
 * Description: A complete WordPress plugin for tracking and managing image weights
 * Version: 1.0.0
 * Author: Wordpress Ongoing
 * Author URI: https://wordpressongoing.com
 * Text Domain: wp-ongoing-image-weight-tracker
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Evita acceso directo al archivo
if (!defined('ABSPATH')) {
  exit;
}
final class Wp_Ongoing_Image_Weight_Tracker
{
  // Instancia única (singleton)
  private static $instance = null;

  // Rutas
  public $plugin_dir;
  public $plugin_url;
  public $plugin_basename;

  // Constructor privado para evitar instanciación directa
  private function __construct()
  {
    $this->plugin_dir = plugin_dir_path(__FILE__);
    $this->plugin_url = plugin_dir_url(__FILE__);
    $this->plugin_basename = plugin_basename(__FILE__);

    // Cargar archivos base
    $this->includes();

    // Registrar hook de inicialización
    add_action('plugins_loaded', [$this, 'init']);
  }
  // Cargar clases base (comunes)
  private function includes()
  {
    require_once $this->plugin_dir . 'includes/helpers.php';
    require_once $this->plugin_dir . 'includes/class-settings.php';
    require_once $this->plugin_dir . 'includes/class-scanner.php';    
  }

  // Iniciar plugin (hooks, clases, etc.)
  public function init()
  {
    // Aplicar filtros de the_content a nuestro hook personalizado
    add_filter('wpoiwt_the_content', [$this, 'apply_content_filters']);

    // Inicializar admin (solo usuario admin)
    if (is_admin()) {
      // Inicializar página de administración
      require_once $this->plugin_dir . 'includes/class-admin-page.php';
      WPOIWT_Admin_Page::init();
    }
  }

  // Aplicar los mismos filtros que the_content usa
  public function apply_content_filters($content)
  {
    // Aplicar los filtros estándar de WordPress a the_content
    $content = wptexturize($content);
    $content = convert_smilies($content);
    $content = convert_chars($content);
    $content = wpautop($content);
    $content = shortcode_unautop($content);
    $content = prepend_attachment($content);
    $content = do_shortcode($content);
    
    return $content;
  }

  // Obtener la instancia única
  public static function get_instance()
  {
    if (null === self::$instance) {
      self::$instance = new self();
    }
    return self::$instance;
  }


  /* Evitar clonación o wakeup */
  private function __clone()
  {
  }
  private function __wakeup()
  {
  }
}

// Inicializar plugin
function wp_ongoing_image_weight_tracker_init()
{
  return Wp_Ongoing_Image_Weight_Tracker::get_instance();
}
wp_ongoing_image_weight_tracker_init();