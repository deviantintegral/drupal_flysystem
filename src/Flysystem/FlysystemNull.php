<?php

/**
 * @file
 * Contains FlysystemNull.
 */

use League\Flysystem\Adapter\NullAdapter;

/**
 * Drupal plugin for the "Null" Flysystem adapter.
 */
class FlysystemNull extends FlysystemPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getAdapter() {
    return new NullAdapter();
  }

}
