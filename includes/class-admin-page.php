<?php
if (!defined('ABSPATH')) {
  exit;
}

class WPOIWT_Admin_Page
{
  const MENU_SLUG = 'wp-ongoing-image-weight-tracker';

  // Inicializar hooks de la página de administración
  public static function init()
  {
    add_action('admin_menu', [self::class, 'add_admin_menu']);
    add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);
  }

  // Agregar el menú al dashboard
  public static function add_admin_menu()
  {
    add_menu_page(
      __('Image Weight Tracker', 'wp-ongoing-image-weight-tracker'), // Título
      __('Image Weight Tracker', 'wp-ongoing-image-weight-tracker'), // Texto
      'manage_options', // Solo administradores
      self::MENU_SLUG, // Slug
      [self::class, 'dispatch'], // Callback
      'dashicons-chart-area', // Icono
      80 // Posición en el menú
    );
  }

  public static function dispatch()
  {
    self::render_list_page();
  }

  public static function render_list_page()
  { ?>
    <div class="wrap wpoiwt-wrap">
      <h1>
        <?php _e('Image Weight Tracker', 'wp-ongoing-image-weight-tracker'); ?>
      </h1>
      <p class="description">
        <?php _e('Analyze images actually used across posts, pages and CPTs.', 'wp-ongoing-image-weight-tracker'); ?>
      </p>

      <div class="wpoiwt-toolbar">
        <button id="wpoiwt-rescan" class="button button-primary wpoiwt-rescan">
          <?php _e('Re-scan', 'wp-ongoing-image-weight-tracker'); ?>
        </button>
        <!--  -->
        <div class="wpoiwt-search-container">
          <input id="wpoiwt-search" type="search"
            placeholder="<?php esc_attr_e('Search image or page…', 'wp-ongoing-image-weight-tracker'); ?>"
            style="min-width:220px;" />
        </div>
      </div>

      <!-- Filtros y Paginación -->
      <div class="wpoiwt-filters-tablebar">
        <div class="wpoiwt-filters-container">
          <div class="wpoiwt-filters">
            <span><?php _e('Format:', 'wp-ongoing-image-weight-tracker'); ?></span>
            <select id="wpoiwt-format">
              <option value="all"><?php _e('All', 'wp-ongoing-image-weight-tracker'); ?></option>
              <option value="jpg">JPG</option>
              <option value="jpeg">JPEG</option>
              <option value="png">PNG</option>
              <option value="webp">WEBP</option>
              <option value="avif">AVIF</option>
              <option value="gif">GIF</option>
              <option value="svg">SVG</option>
            </select>
          </div>
          <div class="wpoiwt-filters">
            <span><?php _e('Status:', 'wp-ongoing-image-weight-tracker'); ?></span>
            <button class="button wpoiwt-chip is-active"
              data-status="all"><?php _e('All', 'wp-ongoing-image-weight-tracker'); ?></button>
            <button class="button wpoiwt-chip"
              data-status="heavy"><?php _e('Heavy', 'wp-ongoing-image-weight-tracker'); ?></button>
            <button class="button wpoiwt-chip"
              data-status="medium"><?php _e('Medium', 'wp-ongoing-image-weight-tracker'); ?></button>
            <button class="button wpoiwt-chip"
              data-status="optimal"><?php _e('Optimal', 'wp-ongoing-image-weight-tracker'); ?></button>
          </div>
        </div>
        <div class="wpoiwt-pagination-container">
          <div id="wpoiwt-counter" style="margin:6px 0 4px; font-size:12px; color:#555;"></div>
          <div id="wpoiwt-pagination" class="wpoiwt-pagination" style="margin:8px 0;"></div>
        </div>
      </div>

      <!-- Contador de imágenes -->
      <!-- <div id="wpoiwt-counter" style="margin:6px 0 4px; font-size:12px; color:#555;"></div> -->

      <table class="widefat fixed striped wpoiwt-table">
        <thead>
          <tr>
            <th><?php _e('Status', 'wp-ongoing-image-weight-tracker'); ?></th>
            <th><?php _e('Image', 'wp-ongoing-image-weight-tracker'); ?></th>
            <th><?php _e('Format', 'wp-ongoing-image-weight-tracker'); ?></th>
            <th><?php _e('Preview', 'wp-ongoing-image-weight-tracker'); ?></th>
            <th><?php _e('Page – Post', 'wp-ongoing-image-weight-tracker'); ?></th>
            <th><?php _e('Total Image Weight', 'wp-ongoing-image-weight-tracker'); ?></th>
          </tr>
        </thead>
        <tbody id="wpoiwt-tbody">
          <tr>
            <td colspan="6" style="text-align:center; padding:20px;">
              <?php _e(
                'No data yet. Click "Re-scan" to start analyzing images.',
                'wp-ongoing-image-weight-tracker'
              ); ?>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div id="loader-image-weight-tracker" class="loader-image-weight-tracker">
      <span class="loader"></span>
    </div>
    <?php
  }

  // Cargar CSS y JS solo en la página del plugin
  public static function enqueue_assets($hook_suffix)
  {
    // Objeto pantalla actual
    $current_screen = get_current_screen();
    /*
    Si no estamos en la página cuyo slug contiene "wp-ongoing-image-weight-tracker",
    no cargues nada y salí del método.
    */
    if (!isset($current_screen->id) || strpos($current_screen->id, self::MENU_SLUG) === false) {
      return;
    }

    // Obtiene la instancia Singleton del plugin principal
    $plugin = Wp_Ongoing_Image_Weight_Tracker::get_instance();

    // Cargar estilos y scripts
    wp_enqueue_style(
      'wpoiwt-admin',
      $plugin->plugin_url . 'assets/admin.css',
      [],
      '1.0.0'
    );
    wp_enqueue_script(
      'wpoiwt-admin',
      $plugin->plugin_url . 'assets/admin.js',
      ['wp-i18n'], // sin jquery , incluimos wp-i18n para traducciones JS
      '1.0.0',
      true
    );

    // Variables JS globales (AJAX)
    wp_localize_script(
      'wpoiwt-admin',
      'WPOIWT_VARS',
      [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wpoiwt_nonce')
      ]
    );

    /* Enlazar el text-domain del script a /languages */
    if (function_exists('wp_set_script_translations')) {
      wp_set_script_translations(
        'wpoiwt-admin',
        'wp-ongoing-image-weight-tracker',
        $plugin->plugin_dir . 'languages'
      );
    }

  }
}