<?php

/**
 * @file
 * Defines the 'local' plugin.
 */

$plugin = array(
  'handler' => array(
    'class' => 'FlysystemLocal',
    'file' => 'FlysystemLocal.php',
    'path' => drupal_get_path('module', 'flysystem') . '/src/Flysystem',
  ),
);
