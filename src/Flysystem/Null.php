<?php

/**
 * @file
 * Contains \Drupal\flysystem\Flysystem\Null.
 */

namespace Drupal\flysystem\Flysystem;

use Drupal\flysystem\Plugin\FlysystemPluginBase;
use League\Flysystem\Adapter\NullAdapter;

/**
 * Drupal plugin for the "Null" Flysystem adapter.
 */
class Null extends FlysystemPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getAdapter() {
    return new NullAdapter();
  }

}