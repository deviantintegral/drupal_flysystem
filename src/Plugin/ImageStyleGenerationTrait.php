<?php

namespace Drupal\flysystem\Plugin;

use Drupal\Component\Utility\Crypt;
use Drupal\image\Entity\ImageStyle;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Helper trait for generating URLs from adapter plugins.
 */
trait ImageStyleGenerationTrait {

  /**
   * Generates an image style for a file target.
   *
   * @param string $target
   *   The file target.
   *
   * @return bool
   *   True on success, false on failure.
   *
   * @deprecated Adapters should use generateImageUrl() to enable non-blocking
   * image uploads. Will be removed before Flysystem 8.x-1.0.
   *
   */
  protected function generateImageStyle($target) {
    if (strpos($target, 'styles/') !== 0 || substr_count($target, '/') < 3) {
      return FALSE;
    }

    list(, $style, $scheme, $file) = explode('/', $target, 4);

    if (!$image_style = ImageStyle::load($style)) {
      return FALSE;
    }

    $image_uri = $scheme . '://' . $file;

    $derivative_uri = $image_style->buildUri($image_uri);

    if (!file_exists($image_uri)) {
      $path_info = pathinfo($image_uri);
      $converted_image_uri = $path_info['dirname'] . '/' . $path_info['filename'];

      if (!file_exists($converted_image_uri)) {
        return FALSE;
      }
      else {
        // The converted file does exist, use it as the source.
        $image_uri = $converted_image_uri;
      }
    }

    $lock_name = 'image_style_deliver:' . $image_style->id() . ':' . Crypt::hashBase64($image_uri);

    if (!file_exists($derivative_uri)) {
      $lock_acquired = \Drupal::lock()->acquire($lock_name);
      if (!$lock_acquired) {
        return FALSE;
      }
    }

    $success = file_exists($derivative_uri) || $image_style->createDerivative($image_uri, $derivative_uri);

    if (!empty($lock_acquired)) {
      \Drupal::lock()->release($lock_name);
    }

    return $success;
  }

  /**
   * Return the external URL for a generated image.
   *
   * @param string $target
   *   The target URI.
   *
   * @return string
   *   The generated URL.
   */
  protected function generateImageUrl($target) {
    list(, $style, $scheme, $file) = explode('/', $target, 4);
    $args = [
      'image_style' => $style,
      'scheme' => $scheme,
      'filepath' => $file,
    ];

    return \Drupal::urlGenerator()->generate('flysystem.image_stye_redirect.serve', $args, UrlGeneratorInterface::ABSOLUTE_URL);
  }

}
