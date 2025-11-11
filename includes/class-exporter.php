<?php

if (!defined('ABSPATH'))
  exit;

class WPOIWT_Exporter
{
  public static function init()
  {
    add_action('admin_post_wpoiwt_export_all', [self::class, 'export_all']);
    add_action('admin_post_wpoiwt_export_single', [self::class, 'export_single']);
  }

  private static function send_csv_headers($filename)
  {
    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
  }

  // Export de TODA la lista (Resumen + sin detallar imágenes)
  public static function export_all()
  {
    if (!current_user_can('manage_options'))
      wp_die(__('Unauthorized', 'wp-ongoing-image-weight-tracker'));
    check_admin_referer('wpoiwt_export_all');

    // Volvemos a calcular on-demand, similar al rescan (simple)
    $posts = get_posts([
      'post_type' => 'any',
      'post_status' => 'publish',
      'numberposts' => -1,
      'fields' => 'ids',
      'suppress_filters' => false,
    ]);

    $rows = [];
    foreach ($posts as $post_id) {
      $post_type = get_post_type($post_id);
      $title = get_the_title($post_id);
      $label = sprintf('%s - %s', ucfirst($post_type), $title);
      $url = get_permalink($post_id);

      $items = WPOIWT_Scanner::get_post_images_with_bytes($post_id);
      $count = count($items);
      $total = array_sum(array_map(fn($i) => $i['bytes'], $items));
      $state = WPOIWT_Scanner::get_state_for_total($total);
      $rows[] = [$state, $label, $count, $total, $url];
    }

    self::send_csv_headers('image-weight-summary-' . date('Ymd-His') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['state', 'page_post', 'image_count', 'total_bytes', 'url']);
    foreach ($rows as $r)
      fputcsv($out, $r);
    fclose($out);
    exit;
  }

  // Export SOLO la página (detalle de imágenes)
  public static function export_single()
  {
    if (!current_user_can('manage_options'))
      wp_die(__('Unauthorized', 'wp-ongoing-image-weight-tracker'));
    $post_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;
    if (!$post_id)
      wp_die(__('Invalid post id', 'wp-ongoing-image-weight-tracker'));
    check_admin_referer('wpoiwt_export_single_' . $post_id);

    $post_type = get_post_type($post_id);
    $title = get_the_title($post_id);
    $prefix = ucfirst($post_type);

    $items = WPOIWT_Scanner::get_post_images_with_bytes($post_id);

    self::send_csv_headers('image-weight-details-' . $post_id . '-' . date('Ymd-His') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['type', 'title', 'page_url', 'file_name', 'bytes', 'state', 'image_url']);

    $page_url = get_permalink($post_id);
    foreach ($items as $it) {
      $state = WPOIWT_Scanner::get_state_for_image($it['bytes']);
      fputcsv($out, [
        $prefix,
        $title,
        $page_url,
        $it['name'],
        $it['bytes'],
        $state,
        $it['url']
      ]);
    }
    fclose($out);
    exit;
  }
}
WPOIWT_Exporter::init();
