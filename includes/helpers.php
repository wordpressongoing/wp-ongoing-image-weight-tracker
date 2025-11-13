<?php

if (!defined('ABSPATH')) {
  exit;
}

if (!function_exists('wpoiwt_bytes_to_readable')) {
  function wpoiwt_bytes_to_readable($bytes)
  {
    $bytes = (float) $bytes;
    if ($bytes < 1024)
      return sprintf(
        /* translators: %s: file size in bytes */
        __('%s B', 'wp-ongoing-image-weight-tracker'),
        number_format_i18n($bytes, 0)
      );
    $kb = $bytes / 1024;
    if ($kb < 1024)
      return sprintf(
        /* translators: %s: file size in kilobytes */
        __('%s KB', 'wp-ongoing-image-weight-tracker'),
        number_format_i18n($kb, 1)
      );
    $mb = $kb / 1024;
    if ($mb < 1024)
      return sprintf(
        /* translators: %s: file size in megabytes */
        __('%s MB', 'wp-ongoing-image-weight-tracker'),
        number_format_i18n($mb, 2)
      );
    $gb = $mb / 1024;
    return sprintf(
      /* translators: %s: file size in gigabytes */
      __('%s GB', 'wp-ongoing-image-weight-tracker'),
      number_format_i18n($gb, 2)
    );
  }
}

if (!function_exists('wpoiwt_array_unique_urls')) {
  function wpoiwt_array_unique_urls(array $urls)
  {
    $map = [];
    foreach ($urls as $u) {
      $key = trim((string) $u);
      if ($key === '')
        continue;
      $map[$key] = true;
    }
    return array_keys($map);
  }
}

if (!function_exists('wpoiwt_guess_ext_from_url')) {
  function wpoiwt_guess_ext_from_url($url)
  {
    $p = wp_parse_url($url, PHP_URL_PATH);
    if (!$p)
      return '';
    $ext = strtolower(pathinfo($p, PATHINFO_EXTENSION));
    return $ext;
  }
}
