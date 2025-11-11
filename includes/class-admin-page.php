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
      // [self::class, 'render_page'], // Callback
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
        <button id="wpoiwt-rescan" class="button button-primary">
          <?php _e('Re-scan', 'wp-ongoing-image-weight-tracker'); ?>
        </button>
      </div>

      <!-- Paginación -->
      <div id="wpoiwt-pagination" style="margin:8px 0;"></div>

      <table class="widefat fixed striped wpoiwt-table">
        <thead>
          <tr>
            <!-- <th><?php _e('Status', 'wp-ongoing-image-weight-tracker'); ?></th>
            <th><?php _e('Page - Post', 'wp-ongoing-image-weight-tracker'); ?></th>
            <th><?php _e('Image Count', 'wp-ongoing-image-weight-tracker'); ?></th>
            <th><?php _e('Total Image Weight', 'wp-ongoing-image-weight-tracker'); ?></th>
            <th><?php _e('Actions', 'wp-ongoing-image-weight-tracker'); ?></th> -->

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
      [], // sin jquery
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
  }
}