<?php

if (!defined('ABSPATH')) {
  exit;
}

class WPOIWT_Scanner
{
  // Umbrales por página, peso en bytes (pueden moverse a Settings luego)
  const PAGE_OPTIMAL_MAX = 1048576;   // 1 MB
  const PAGE_MEDIUM_MAX = 3145728;   // 3 MB

  // Umbrales por imagen, peso en bytes
  const IMG_OPTIMAL_MAX = 153600;   // 150 KB
  const IMG_MEDIUM_MAX = 512000;   // 500 KB

  // Extensiones consideradas
  private static $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'avif', 'gif', 'svg'];

  public static function init()
  {
    // Re-escanear imágenes
    add_action('wp_ajax_wpoiwt_rescan', [self::class, 'ajax_rescan']);
    // Re-escanear imágenes en lote
    add_action('wp_ajax_wpoiwt_rescan_batch', [self::class, 'ajax_rescan_batch']);
  }

  // Manejar la solicitud AJAX para re-escanear imágenes en lote
  public static function ajax_rescan_batch()
  {
    // si no está autorizado como administrador
    if (!current_user_can('manage_options')) {
      wp_send_json_error(['message' => __('Unauthorized', 'wp-ongoing-image-weight-tracker')], 403);
    }
    check_ajax_referer('wpoiwt_nonce', 'nonce');

    $offset = isset($_POST['offset']) ? max(0, intval($_POST['offset'])) : 0;
    $limit = isset($_POST['limit']) ? max(1, intval($_POST['limit'])) : 25;

    // Obtener IDs publicados
    $q = new WP_Query([
      'post_type' => 'any',
      'post_status' => 'publish',
      'fields' => 'ids',
      'posts_per_page' => $limit,
      'offset' => $offset,
      'orderby' => 'ID',
      'order' => 'ASC',
      'no_found_rows' => false,
      'ignore_sticky_posts' => true,
    ]);

    $ids = $q->posts ?: [];
    $rows_html = ''; // 
    $rows_data = []; // 

    foreach ($ids as $post_id) {
      $post_type = get_post_type($post_id);
      $post_obj = get_post($post_id);
      if (!$post_obj)
        continue;

      $content = apply_filters('the_content', $post_obj->post_content);

      $images = self::extract_images_from_html($content);

      $thumb_id = get_post_thumbnail_id($post_id);
      if ($thumb_id) {
        $url = wp_get_attachment_url($thumb_id);
        if ($url)
          $images[] = $url;
      }

      $images = wpoiwt_array_unique_urls($images);
      $images = array_values(array_filter($images, function ($u) {
        $ext = wpoiwt_guess_ext_from_url($u);
        return in_array($ext, self::$allowed_exts, true);
      }));

      $total_bytes = 0;
      $count = 0;
      foreach ($images as $url) {
        $bytes = self::get_image_size_bytes_cached($url); // con cache
        if ($bytes === null)
          continue;
        $total_bytes += (int) $bytes;
        $count++;
      }

      $state = self::get_state_for_total($total_bytes);
      $state_label = self::state_label($state);

      $label = sprintf('%s - %s', ucfirst($post_type), get_the_title($post_id));
      $details_url = add_query_arg([
        'page' => WPOIWT_Admin_Page::MENU_SLUG,
        'view' => 'details',
        'post_id' => $post_id,
      ], admin_url('admin.php'));

      $rows_html .= '<tr>';
      $rows_html .= '<td><span class="wpoiwt-state wpoiwt-state-' . esc_attr($state) . '">' . esc_html($state_label) . '</span></td>';
      $rows_html .= '<td>' . esc_html($label) . '</td>';
      $rows_html .= '<td>' . esc_html($count) . '</td>';
      $rows_html .= '<td>' . esc_html(wpoiwt_bytes_to_readable($total_bytes)) . '</td>';
      $rows_html .= '<td><a class="button button-small" href="' . esc_url($details_url) . '">' . esc_html__('View Details', 'wp-ongoing-image-weight-tracker') . '</a></td>';
      $rows_html .= '</tr>';

      // DATA (para paginación/export front)
      $rows_data[] = [
        'state' => $state_label,
        'state_key' => $state,          // optimal/medium/heavy
        'type' => ucfirst($post_type),
        'title' => get_the_title($post_id),
        'label' => $label,
        'image_count' => $count,
        'total_bytes' => (int) $total_bytes,
        'page_url' => get_permalink($post_id),
        'details' => $details_url,
        'post_id' => (int) $post_id,
      ];
    }

    // ORDENAR por estado y peso
    $priority = ['heavy' => 0, 'medium' => 1, 'optimal' => 2];
    usort($rows_data, function ($a, $b) use ($priority) {
      $pa = $priority[$a['state_key']] ?? 9;
      $pb = $priority[$b['state_key']] ?? 9;
      if ($pa !== $pb) {
        return $pa - $pb; // heavy primero
      }
      // dentro del mismo grupo: total_bytes DESC
      return $b['total_bytes'] <=> $a['total_bytes'];
    });

    // RECONSTRUIR HTML ya ordenado
    $rows_html = '';
    foreach ($rows_data as $r) {
      $rows_html .= '<tr>';
      $rows_html .= '<td><span class="wpoiwt-state wpoiwt-state-' . esc_attr($r['state_key']) . '">' . esc_html($r['state']) . '</span></td>';
      $rows_html .= '<td>' . esc_html($r['label']) . '</td>';
      $rows_html .= '<td>' . esc_html($r['image_count']) . '</td>';
      $rows_html .= '<td>' . esc_html(wpoiwt_bytes_to_readable($r['total_bytes'])) . '</td>';
      $rows_html .= '<td><a class="button button-small" href="' . esc_url($r['details']) . '">' . esc_html__('View Details', 'wp-ongoing-image-weight-tracker') . '</a></td>';
      $rows_html .= '</tr>';
    }

    // 
    $total_found = intval($q->found_posts);
    $has_more = ($offset + $limit) < $total_found;

    // Si no hay más resultados, mostrar mensaje
    if ($rows_html === '') {
      $rows_html = '<tr><td colspan="5" style="text-align:center; padding:20px;">' . esc_html__('No images found in this batch.', 'wp-ongoing-image-weight-tracker') . '</td></tr>';
    }

    // Respuesta JSON
    wp_send_json_success([
      'html' => $rows_html,
      'rows' => $rows_data,
      'has_more' => $has_more,
      'total' => $total_found,
    ]);
  }

  // Cache para HEAD/GET externos (transients por 24h)
  private static function get_image_size_bytes_cached($url)
  {
    $key = 'wpoiwt_sz_' . md5($url);
    $cached = get_transient($key);
    if ($cached !== false) {
      return $cached; // puede ser int o null (guardamos null también)
    }
    $bytes = self::get_image_size_bytes($url);

    // TTL robusto (evita warning del IDE/CLI)
    $ttl = defined('DAY_IN_SECONDS') ? DAY_IN_SECONDS : 86400;

    // guarda incluso null para evitar golpear repetidamente
    set_transient($key, $bytes, $ttl);
    return $bytes;
  }

  public static function ajax_rescan()
  {
    if (!current_user_can('manage_options')) {
      wp_send_json([
        'success' => false,
        'message' => __('Unauthorized', 'wp-ongoing-image-weight-tracker')
      ], 403);
    }

    check_ajax_referer('wpoiwt_nonce', 'nonce');

    // 1) Obtener posts publicados (post/page/CPT)
    $posts = get_posts([
      'post_type' => 'any',
      'post_status' => 'publish',
      'numberposts' => 200, // límite inicial (ajustable)
      'fields' => 'ids',
      'suppress_filters' => false,
    ]);

    if (empty($posts)) {
      wp_send_json_success([
        'html' => '<tr>
        <td colspan="5" style="text-align:center; padding:20px;">'
          . esc_html__('No published content found.', 'wp-ongoing-image-weight-tracker') .
          '</td></tr>'
      ]);
    }

    $rows_html = '';
    foreach ($posts as $post_id) {
      $post_type = get_post_type($post_id);
      $post_obj = get_post($post_id);
      if (!$post_obj)
        continue;

      // 2) Renderizar contenido
      $content = apply_filters('the_content', $post_obj->post_content);

      // 3) Extraer imágenes del HTML
      $images = self::extract_images_from_html($content);

      // 3.1) Incluir featured image (si existe)
      $thumb_id = get_post_thumbnail_id($post_id);
      if ($thumb_id) {
        $url = wp_get_attachment_url($thumb_id);
        if ($url)
          $images[] = $url;
      }

      $images = wpoiwt_array_unique_urls($images);
      $images = array_values(array_filter($images, function ($u) {
        $ext = wpoiwt_guess_ext_from_url($u);
        return in_array($ext, self::$allowed_exts, true);
      }));

      // 4) Calcular peso total y contador
      $total_bytes = 0;
      $count = 0;

      foreach ($images as $url) {
        $bytes = self::get_image_size_bytes($url);
        if ($bytes === null)
          continue;
        $total_bytes += (int) $bytes;
        $count++;
      }

      // 5) Estado por página según umbral
      $state = self::get_state_for_total($total_bytes);
      $state_label = self::state_label($state);

      // 6) Título y prefijo tipo (Page - X, Post - Y, CPT - Z)
      $type_prefix = ucfirst($post_type);
      $title = get_the_title($post_id);
      $label = sprintf('%s - %s', $type_prefix, $title);

      // 7) Botón Ver Detalles (por ahora dummy, enlazaremos a otra subpágina luego)
      $details_url = add_query_arg([
        'page' => WPOIWT_Admin_Page::MENU_SLUG,
        'view' => 'details',
        'post_id' => $post_id,
      ], admin_url('admin.php'));

      $rows_html .= '<tr>';
      $rows_html .= '<td><span class="wpoiwt-state wpoiwt-state-' . esc_attr($state) . '">' . esc_html($state_label) . '</span></td>';
      $rows_html .= '<td>' . esc_html($label) . '</td>';
      $rows_html .= '<td>' . esc_html($count) . '</td>';
      $rows_html .= '<td>' . esc_html(wpoiwt_bytes_to_readable($total_bytes)) . '</td>';
      $rows_html .= '<td><a class="button button-small" href="' . esc_url($details_url) . '">' . esc_html__('View Details', 'wp-ongoing-image-weight-tracker') . '</a></td>';
      $rows_html .= '</tr>';
    }

    if ($rows_html === '') {
      $rows_html = '<tr><td colspan="5" style="text-align:center; padding:20px;">' . esc_html__('No images found in published content.', 'wp-ongoing-image-weight-tracker') . '</td></tr>';
    }

    wp_send_json_success(['html' => $rows_html]);
  }

  // Obtener imágenes de un post con sus tamaños en bytes
  public static function get_post_images_with_bytes($post_id)
  {
    $post = get_post($post_id);
    if (!$post || $post->post_status !== 'publish')
      return [];

    $content = apply_filters('the_content', $post->post_content);
    $images = self::extract_images_from_html($content);

    $thumb_id = get_post_thumbnail_id($post_id);
    if ($thumb_id) {
      $url = wp_get_attachment_url($thumb_id);
      if ($url)
        $images[] = $url;
    }

    $images = wpoiwt_array_unique_urls($images);
    $images = array_values(array_filter($images, function ($u) {
      $ext = wpoiwt_guess_ext_from_url($u);
      return in_array($ext, self::$allowed_exts, true);
    }));

    $out = [];
    foreach ($images as $url) {
      $bytes = self::get_image_size_bytes($url);
      if ($bytes === null)
        continue;

      $name = basename(parse_url($url, PHP_URL_PATH));
      $out[] = [
        'url' => $url,
        'name' => $name,
        'bytes' => (int) $bytes
      ];
    }
    return $out;
  }

  // NEW: estado por imagen
  public static function get_state_for_image($bytes)
  {
    if ($bytes <= self::IMG_OPTIMAL_MAX)
      return 'optimal';
    if ($bytes <= self::IMG_MEDIUM_MAX)
      return 'medium';
    return 'heavy';
  }

  // NEW: etiquetas legibles para imagen
  public static function state_label_image($state)
  {
    switch ($state) {
      case 'optimal':
        return __('Optimal', 'wp-ongoing-image-weight-tracker');
      case 'medium':
        return __('Medium', 'wp-ongoing-image-weight-tracker');
      default:
        return __('Heavy', 'wp-ongoing-image-weight-tracker');
    }
  }


  /* Extrae URLs de imágenes desde HTML (img[src], img[srcset], picture>source[srcset]) */
  private static function extract_images_from_html($html)
  {
    $urls = [];

    if (trim($html) === '')
      return $urls;

    // DOMDocument es suficiente para la mayoría de contenidos editoriales
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    libxml_clear_errors();

    if (!$loaded)
      return $urls;

    // <img>
    $imgs = $dom->getElementsByTagName('img');
    foreach ($imgs as $img) {
      $src = $img->getAttribute('src');
      if ($src)
        $urls[] = $src;

      $srcset = $img->getAttribute('srcset');
      if ($srcset) {
        $urls = array_merge($urls, self::pick_srcs_from_srcset($srcset));
      }
    }

    // <picture><source>
    $sources = $dom->getElementsByTagName('source');
    foreach ($sources as $srcNode) {
      $srcset = $srcNode->getAttribute('srcset');
      if ($srcset) {
        $urls = array_merge($urls, self::pick_srcs_from_srcset($srcset));
      }
    }

    return $urls;
  }

  /* Dado un srcset, devuelve la URL de mayor ancho y también las URLs listadas (para cobertura) */
  private static function pick_srcs_from_srcset($srcset)
  {
    $urls = [];
    $candidates = array_map('trim', explode(',', $srcset));
    $best = null;
    $bestW = -1;

    foreach ($candidates as $c) {
      // ejemplo: "https://... 1024w" o "https://... 2x"
      $parts = preg_split('/\s+/', $c);
      if (empty($parts))
        continue;
      $url = $parts[0];
      $urls[] = $url;

      if (isset($parts[1]) && substr($parts[1], -1) === 'w') {
        $w = (int) rtrim($parts[1], 'w');
        if ($w > $bestW) {
          $bestW = $w;
          $best = $url;
        }
      }
    }

    // Podrías priorizar $best si quisieras solo la mayor, pero por ahora devolvemos todas:
    return $urls;
  }

  /* Devuelve tamaño en bytes o null si no se puede determinar */
  private static function get_image_size_bytes($url)
  {
    // Local: via attachment
    $attachment_id = attachment_url_to_postid($url);
    if ($attachment_id) {
      $path = get_attached_file($attachment_id);
      if ($path && file_exists($path)) {
        $bytes = filesize($path);
        return (is_numeric($bytes) ? (int) $bytes : null);
      }
    }

    // Externa: intenta HEAD para Content-Length
    $response = wp_remote_head($url, ['timeout' => 3, 'redirection' => 3]);
    if (!is_wp_error($response)) {
      $len = wp_remote_retrieve_header($response, 'content-length');
      if ($len && is_numeric($len)) {
        return (int) $len;
      }
    }

    // Fallback GET (sin descargar al disco)
    $response = wp_remote_get($url, ['timeout' => 3, 'redirection' => 3, 'stream' => false]);
    if (!is_wp_error($response)) {
      $len = wp_remote_retrieve_header($response, 'content-length');
      if ($len && is_numeric($len)) {
        return (int) $len;
      }
      // Si no hay header, usar el cuerpo como último recurso
      $body = wp_remote_retrieve_body($response);
      if (is_string($body) && $body !== '') {
        return strlen($body);
      }
    }

    return null;
  }

  // 
  public static function get_state_for_total($bytes)
  {
    // ≤ 1 MB
    if ($bytes <= self::PAGE_OPTIMAL_MAX)
      return 'optimal';
    // (1 MB, 3 MB]
    if ($bytes <= self::PAGE_MEDIUM_MAX)
      return 'medium';
    //  > 3 MB
    return 'heavy';
  }

  // 
  private static function state_label($state)
  {
    switch ($state) {
      case 'optimal':
        return __('Optimal', 'wp-ongoing-image-weight-tracker');
      case 'medium':
        return __('Medium', 'wp-ongoing-image-weight-tracker');
      default:
        return __('Heavy', 'wp-ongoing-image-weight-tracker');
    }
  }
}

// Hook de arranque
WPOIWT_Scanner::init();