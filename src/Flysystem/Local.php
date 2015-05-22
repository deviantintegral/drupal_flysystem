<?php

/**
 * @file
 * Contains \Drupal\flysystem\Flysystem\Local.
 */

namespace Drupal\flysystem\Flysystem;

use Drupal\flysystem\Plugin\FlysystemPluginBase;
use League\Flysystem\Adapter\Local as LocalAdapter;

/**
 * Drupal plugin for the "Local" Flysystem adapter.
 */
class Local extends FlysystemPluginBase {

  /**
   * The root of the local adapter.
   *
   * @var string
   */
  protected $root;

  /**
   * The base URL.
   *
   * @var string
   */
  protected $baseUrl;

  /**
   * The location of the public files directory.
   *
   * @var string
   */
  protected $basePath;

  /**
   * Whether the root is in the public path.
   *
   * @var bool
   */
  protected $publicPath;

  /**
   * Constructs a Local object.
   */
  public function __construct($root, $base_url, $base_path) {
    $this->root = rtrim($root, '\/');
    $this->baseUrl = $base_url;
    $this->basePath = $base_path;

    $this->publicPath = $this->pathIsPublic($this->root);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(array $configuration) {
    $base_path = variable_get('file_public_path', conf_path() . '/files');

    return new static($configuration['root'], $GLOBALS['base_url'], $base_path);
  }

  /**
   * {@inheritdoc}
   */
  public function getAdapter() {
    return new LocalAdapter($this->root);
  }

  /**
   * {@inheritdoc}
   */
  public function getExternalUrl($uri) {
    if (!$this->publicPath) {
      return parent::getExternalUrl($uri);
    }

    list(, $path) = explode('://', $uri, 2);
    $path = str_replace('\\', '/', $this->publicPath . '/' . $path);

    return $this->baseUrl . '/' . drupal_encode_path($path);
  }

  /**
   * Determines if the path is inside the public path.
   *
   * @param string $root
   *   The root path.
   *
   * @return string|false
   *   The public path, or false.
   */
  protected function pathIsPublic($root) {
    $public = realpath($this->basePath);
    $root = realpath($root);

    if ($public === FALSE || $root === FALSE) {
      return FALSE;
    }

    // The same directory.
    if ($public === $root) {
      return $this->basePath;
    }

    if (strpos($root, $public) !== 0) {
      return FALSE;
    }

    if (($subpath = substr($root, strlen($public))) && $subpath[0] === DIRECTORY_SEPARATOR) {
      return $this->basePath . '/' . ltrim($subpath, DIRECTORY_SEPARATOR);
    }

    return FALSE;
  }

}
