<?php

/**
 * @file
 * Contains \Drupal\flysystem\Plugin\ImageStyleGenerationTrait.
 */

namespace Drupal\flysystem\Plugin;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Helper trait for generating URLs from adapter plugins.
 */
trait ImageStyleGenerationTrait {

  /**
   * @param $target
   * @param bool $use_temporary
   * @return string
   */
  protected function generateImageUrl($target, $use_temporary = TRUE) {
    list(, $style, $scheme, $file) = explode('/', $target, 4);
    return \Drupal::urlGenerator()->generate("flysystem.$scheme.style_redirect", ['image_style' => $style], UrlGeneratorInterface::ABSOLUTE_URL) . "/$file";
  }

}
