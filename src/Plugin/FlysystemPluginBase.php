<?php

/**
 * @file
 * Contains FlysystemPluginBase.
 */

/**
 * Base class for plugins.
 */
abstract class FlysystemPluginBase implements FlysystemPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function getExternalUrl($uri) {
    list($scheme, $path) = explode('://', $uri, 2);
    $path = str_replace('\\', '/', $path);

    return url('_flysystem/' . $scheme . '/' . $path, array('absolute' => TRUE));
  }

}
