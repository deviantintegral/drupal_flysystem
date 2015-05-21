<?php

/**
 * @file
 * Defines the 'null' plugin.
 */

$plugin = array(
  'handler' => array(
    'class' => 'FlysystemNull',
    'file' => 'FlysystemNull.php',
    'path' => drupal_get_path('module', 'flysystem') . '/src/Flysystem',
  ),
);
