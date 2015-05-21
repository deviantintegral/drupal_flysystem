<?php

/**
 * @file
 * Contains FlysystemLocal.
 */

use League\Flysystem\Adapter\Local as LocalAdapter;

/**
 * Drupal plugin for the "Local" Flysystem adapter.
 */
class FlysystemLocal extends FlysystemPluginBase {

  /**
   * The root of the local adapter.
   *
   * @var string
   */
  protected $root;

  /**
   * Whether the root is in the public path.
   *
   * @var bool
   */
  protected $isPublic;

  /**
   * Constructs a Local object.
   *
   * @param array $configuration
   *   Plugin configuration array.
   */
  public function __construct(array $configuration) {
    $this->root = $configuration['root'];

    $root = realpath($configuration['root']);
    $public = realpath($this->basePath());

    $this->isPublic = strpos($root, $public) === 0;
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
    if (!$this->isPublic) {
      return parent::getExternalUrl($uri);
    }

    list(, $path) = explode('://', $uri, 2);
    $path = str_replace('\\', '/', $path);

    return $GLOBALS['base_url'] . '/' . $this->basePath() . '/' . drupal_encode_path($path);
  }

  /**
   * Returns the base path for public://.
   *
   * @return string
   *   The base path for public:// typically sites/default/files.
   */
  protected static function basePath() {
    return variable_get('file_public_path', conf_path() . '/files');
  }

}
