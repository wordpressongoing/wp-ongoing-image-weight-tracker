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
      [self::class, 'dispatch'],
      'dashicons-chart-area', // Icono
      80 // Posición en el menú
    );
  }

  public static function dispatch()
  {
    // Determinar la vista a mostrar
    $view = isset($_GET['view']) ? sanitize_key($_GET['view']) : 'list';
    if ($view === 'details') {
      self::render_details_page();
    } else {
      self::render_list_page();
    }
  }

  public static function render_list_page()
  {
    ?>
    <div class="wrap wpoiwt-wrap">
      <h1>
        <?php _e('Image Weight Tracker', 'wp-ongoing-image-weight-tracker'); ?>
      </h1>
      <p class="description">
        <?php _e('Analyze and track the total image weight per page or post to optimize performance.', 'wp-ongoing-image-weight-tracker'); ?>
      </p>

      <div class="wpoiwt-toolbar">
        <button id="wpoiwt-rescan" class="button button-primary">
          <?php _e('Re-scan', 'wp-ongoing-image-weight-tracker'); ?>
        </button>
        <button id="wpoiwt-export" class="button">
          <?php _e('Export to Excel', 'wp-ongoing-image-weight-tracker'); ?>
        </button>
        <!-- <a href="<?php /* echo esc_url($export_url); */ ?>" class="button">
          <?php _e('Export all (CSV)', 'wp-ongoing-image-weight-tracker'); ?>
        </a> -->
      </div>

      <!-- Paginación -->
      <div id="wpoiwt-pagination" style="margin:8px 0;"></div>

      <table class="widefat fixed striped wpoiwt-table">
        <thead>
          <tr>
            <th><?php _e('Status', 'wp-ongoing-image-weight-tracker'); ?></th>
            <th><?php _e('Page - Post', 'wp-ongoing-image-weight-tracker'); ?></th>
            <th><?php _e('Image Count', 'wp-ongoing-image-weight-tracker'); ?></th>
            <th><?php _e('Total Image Weight', 'wp-ongoing-image-weight-tracker'); ?></th>
            <th><?php _e('Actions', 'wp-ongoing-image-weight-tracker'); ?></th>
          </tr>
        </thead>
        <tbody id="wpoiwt-tbody">
          <tr>
            <td colspan="5" style="text-align:center; padding:20px;">
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

  public static function render_details_page()
  {
    if (!current_user_can('manage_options'))
      wp_die(__('Unauthorized', 'wp-ongoing-image-weight-tracker'));
    $post_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;
    if (!$post_id)
      wp_die(__('Invalid post id', 'wp-ongoing-image-weight-tracker'));

    $post = get_post($post_id);
    $post_type = get_post_type($post_id);
    $prefix = ucfirst($post_type);
    $title = $post ? get_the_title($post) : ('#' . $post_id);

    // datos de imágenes
    $items = WPOIWT_Scanner::get_post_images_with_bytes($post_id);
    ?>

    <div class="wrap wpoiwt-wrap">
      <h1><?php echo esc_html(sprintf('%s — %s', $prefix, $title)); ?></h1>

      <div class="wpoiwt-toolbar">
        <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::MENU_SLUG)); ?>" class="button">
          <?php _e('Back', 'wp-ongoing-image-weight-tracker'); ?>
        </a>
        <button id="wpoiwt-export-details" class="button button-primary">
          <?php _e('Export this page (Excel)', 'wp-ongoing-image-weight-tracker'); ?>
        </button>
        <!-- <a href="<?php /*echo esc_url($export_single_url); */ ?>" class="button button-primary">
          <?php _e('Export this page (CSV)', 'wp-ongoing-image-weight-tracker'); ?>
        </a> -->
      </div>

      <table class="widefat fixed striped wpoiwt-table">
        <thead>
          <tr>
            <th><?php _e('Image', 'wp-ongoing-image-weight-tracker'); ?></th>
            <th><?php _e('Preview', 'wp-ongoing-image-weight-tracker'); ?></th>
            <th><?php _e('Total Image Weight', 'wp-ongoing-image-weight-tracker'); ?></th>
            <th><?php _e('State', 'wp-ongoing-image-weight-tracker'); ?></th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($items)): ?>
            <tr>
              <td colspan="4" style="text-align:center; padding:20px;">
                <?php _e('No images found.', 'wp-ongoing-image-weight-tracker'); ?>
              </td>
            </tr>
          <?php else:
            foreach ($items as $it):
              $url = $it['url'];
              $name = $it['name'];
              $bytes = $it['bytes'];
              $state = WPOIWT_Scanner::get_state_for_image($bytes);
              ?>
              <tr>
                <td>
                  <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer">
                    <?php echo esc_html($name); ?>
                  </a>
                </td>
                <td>
                  <img src="<?php echo esc_url($url); ?>" alt="" style="max-width:120px; height:auto;">
                </td>
                <td><?php echo esc_html(wpoiwt_bytes_to_readable($bytes)); ?></td>
                <td><span class="wpoiwt-state wpoiwt-state-<?php echo esc_attr($state); ?>">
                    <?php echo esc_html(WPOIWT_Scanner::state_label_image($state)); ?>
                  </span></td>
              </tr>
            <?php endforeach; endif; ?>
        </tbody>
      </table>
      <?php
      // construir items con estado (para export front)
      $items_for_js = [];
      if (!empty($items)) {
        foreach ($items as $it) {
          $items_for_js[] = [
            'url' => $it['url'],
            'name' => $it['name'],
            'bytes' => (int) $it['bytes'],
            'state' => WPOIWT_Scanner::get_state_for_image($it['bytes']),
          ];
        }
      }
      ?>
      <script type="application/json" id="wpoiwt-details-data">
        <?php echo wp_json_encode($items_for_js, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>
      </script>
    </div>
  <?php }


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
    wp_enqueue_script(
      'sheetjs',
      'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js',
      [],
      '0.18.5',
      true
    );
    wp_enqueue_style(
      'wpoiwt-admin',
      $plugin->plugin_url . 'assets/admin.css',
      [],
      '1.0.0'
    );
    wp_enqueue_script(
      'wpoiwt-admin',
      $plugin->plugin_url . 'assets/admin.js',
      ['sheetjs'], // sin jquery
      '1.0.0',
      true
    );

    // Variables JS globales (por si luego usamos AJAX)
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