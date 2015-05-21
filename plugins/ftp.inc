<?php

/**
 * @file
 * Defines the 'ftp' plugin.
 */

if (extension_loaded('ftp')) {
  $plugin = array(
    'handler' => array(
      'class' => 'FlysystemFtp',
      'file' => 'FlysystemFtp.php',
      'path' => drupal_get_path('module', 'flysystem') . '/src/Flysystem',
    ),
  );
}
