<?php

if (!defined('ABSPATH')) {
  exit;
}

class WPOIWT_Scanner
{
  // Umbrales por página, peso en bytes (pueden moverse a Settings luego)
  // const PAGE_OPTIMAL_MAX = 1048576;   // 1 MB
  // const PAGE_MEDIUM_MAX = 3145728;   // 3 MB

  // Umbrales por imagen, peso en bytes
  const IMG_OPTIMAL_MAX = 153600;   // 150 KB
  const IMG_MEDIUM_MAX = 512000;   // 500 KB

  // Extensiones consideradas
  private static $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'avif', 'gif', 'svg'];

  // Cache de tamaños de imagen
  private static $bytes_cache = []; // key => int|null

  // Cache de IDs de adjuntos
  private static $att_cache = []; // url => attachment_id|0


  public static function init()
  {
    add_action('wp_ajax_wpoiwt_scan_images_batch', [self::class, 'ajax_scan_images_batch']);
  }

  private static function attachment_id_from_url_cached($url)
  {
    // Cache persistente WP
    if (isset(self::$att_cache[$url]))
      return self::$att_cache[$url];
    // Obtener ID y cachear
    $id = attachment_url_to_postid($url);
    self::$att_cache[$url] = $id ? (int) $id : 0;

    // Retornamos el valor cacheado
    return self::$att_cache[$url];
  }

  // Obtener la clave de imagen a partir de la URL (attachment o externa)
  private static function image_key_from_url($url)
  {
    // $attachment_id = attachment_url_to_postid($url);
    // if ($attachment_id)
    //   return 'att-' . $attachment_id;
    // return 'ext-' . md5($url);

    // Obtener ID de adjunto cacheado
    $attachment_id = self::attachment_id_from_url_cached($url);
    if ($attachment_id)
      return 'att-' . $attachment_id;
    return 'ext-' . md5($url);
  }

  // Formateo de extensión/format
  private static function format_from_url($url)
  {
    // Obtener la extensión del archivo a partir de la URL
    $ext = wpoiwt_guess_ext_from_url($url);
    // Formatear la extensión a mayúsculas
    return strtoupper($ext);
  }

  // Manejar la solicitud AJAX para escanear imágenes
  public static function ajax_scan_images_batch()
  {
    // si no está autorizado como administrador retorna error
    if (!current_user_can('manage_options')) {
      wp_send_json_error(['message' => __('Unauthorized', 'wp-ongoing-image-weight-tracker')], 403);
    }

    check_ajax_referer('wpoiwt_nonce', 'nonce');

    $offset = isset($_POST['offset']) ? max(0, intval($_POST['offset'])) : 0;
    $limit = isset($_POST['limit']) ? max(1, intval($_POST['limit'])) : 25;

    // Lote de posts publicados
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

    // Acumulador por imagen
    $by_image = [];

    foreach ($ids as $post_id) {
      // Obtener objeto del post
      $post_obj = get_post($post_id);
      if (!$post_obj)
        continue;

      // Obtener tipo de post y detalles
      $post_type = get_post_type($post_id);
      $title = get_the_title($post_id);
      $permalink = get_permalink($post_id);
      // Etiqueta tipo - título (Page - X, Post - Y, CPT - Z)
      $label = sprintf('%s - %s', ucfirst($post_type), $title);

      // Simular renderizado de contenido
      $content = apply_filters('the_content', $post_obj->post_content);
      // Extraer imágenes de contenido renderizado
      $images = self::extract_images_from_html($content);

      // featured
      $thumb_id = get_post_thumbnail_id($post_id);
      if ($thumb_id) {
        $u = wp_get_attachment_url($thumb_id);
        if ($u)
          $images[] = $u;
      }

      $images = wpoiwt_array_unique_urls($images);
      $images = array_values(array_filter($images, function ($u) {
        $ext = wpoiwt_guess_ext_from_url($u);
        return in_array($ext, self::$allowed_exts, true);
      }));

      foreach ($images as $url) {
        $key = self::image_key_from_url($url);
        if (!isset($by_image[$key])) {
          // calcular bytes una sola vez por imagen (con cache)
          $bytes = self::get_image_size_bytes_cached($url);
          if ($bytes === null)
            continue; // no medible -> omitir

          $by_image[$key] = [
            'key' => $key,
            'url' => $url,
            'name' => basename(parse_url($url, PHP_URL_PATH)),
            'format' => self::format_from_url($url),
            'bytes' => (int) $bytes,
            'used_in' => [],   // se llena abajo
          ];
        }

        // Agregar relación de uso (evitar duplicados por post)
        $by_image[$key]['used_in'][$post_id] = [
          'post_id' => (int) $post_id,
          'label' => $label,
          'permalink' => $permalink,
        ];
      }
    }

    // Construir lista final de imágenes (solo las usadas)
    $images_out = [];
    foreach ($by_image as $img) {
      // descarta claves
      $usage = array_values($img['used_in']);
      if (empty($usage))
        continue;

      $state_key = self::get_state_for_image($img['bytes']);
      $state_label = self::state_label_image($state_key);

      $images_out[] = [
        'key' => $img['key'],
        'url' => $img['url'],
        'name' => $img['name'],
        'format' => $img['format'],
        'bytes' => $img['bytes'],
        'status_key' => $state_key,
        'status_label' => $state_label,
        'used_in' => $usage,
        'usage_count' => count($usage),
      ];
    }

    // Orden: Heavy -> Medium -> Optimal ; bytes DESC
    $priority = ['heavy' => 0, 'medium' => 1, 'optimal' => 2];
    usort($images_out, function ($a, $b) use ($priority) {
      $pa = $priority[$a['status_key']] ?? 9;
      $pb = $priority[$b['status_key']] ?? 9;
      if ($pa !== $pb)
        return $pa - $pb;
      return $b['bytes'] <=> $a['bytes'];
    });

    $total_found = intval($q->found_posts);
    $has_more = ($offset + $limit) < $total_found;

    wp_send_json_success([
      'images' => $images_out,
      'has_more' => $has_more,
      'total' => $total_found,
    ]);
  }

  // Cache para HEAD/GET externos (transients por 24h)
  private static function get_image_size_bytes_cached($url)
  {
    $key = 'wpoiwt_sz_' . md5($url);

    // cache por request
    if (array_key_exists($key, self::$bytes_cache)) {
      return self::$bytes_cache[$key];
    }

    // cache persistente WP
    $cached = get_transient($key);
    if ($cached !== false) {
      self::$bytes_cache[$key] = $cached;
      return $cached; // puede ser int o null (guardamos null también)
    }

    $bytes = self::get_image_size_bytes($url);

    // TTL robusto (evita warning del IDE/CLI)
    $ttl = defined('DAY_IN_SECONDS') ? DAY_IN_SECONDS : 86400;

    // guarda incluso null para evitar golpear repetidamente
    set_transient($key, $bytes, $ttl);

    self::$bytes_cache[$key] = $bytes;

    return $bytes;
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
    // $attachment_id = attachment_url_to_postid($url);
    // if ($attachment_id) {
    //   $path = get_attached_file($attachment_id);
    //   if ($path && file_exists($path)) {
    //     $bytes = filesize($path);
    //     return (is_numeric($bytes) ? (int) $bytes : null);
    //   }
    // }

    // Local por attachment (rápido y exacto)
    $attachment_id = self::attachment_id_from_url_cached($url);
    if ($attachment_id) {
      $path = get_attached_file($attachment_id);
      if ($path && file_exists($path)) {
        $bytes = @filesize($path);
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
}

// Hook de arranque
WPOIWT_Scanner::init();