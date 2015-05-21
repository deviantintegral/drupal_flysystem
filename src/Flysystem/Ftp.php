<?php

/**
 * @file
 * Contains \Drupal\flysystem\Flysystem\Ftp.
 */

namespace Drupal\flysystem\Flysystem;

use Drupal\flysystem\Plugin\FlysystemPluginBase;
use League\Flysystem\Adapter\Ftp as FtpAdapter;

/**
 * Drupal plugin for the "FTP" Flysystem adapter.
 */
class Ftp extends FlysystemPluginBase {

  /**
   * Plugin configuration.
   *
   * @var array
   */
  protected $configuration;

  /**
   * Constructs an Ftp object.
   *
   * @param array $configuration
   *   Plugin configuration array.
   */
  public function __construct(array $configuration) {
    $this->configuration = $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function getAdapter() {
    return new FtpAdapter($this->configuration);
  }

}
