<?php

/**
 * @file
 * Contains DrupalFlysystemCache.
 */

namespace Drupal\flysystem;

use League\Flysystem\Cached\Storage\AbstractCache;

/**
 * An adapter that allows Flysystem to use Drupal's cache system.
 */
class DrupalFlysystemCache extends AbstractCache {

  /**
   * The cache key.
   *
   * @var string
   */
  protected $key = 'flysystem';

  /**
   * {@inheritdoc}
   */
  public function load() {
    if ($cache = cache_get($this->key)) {
      $this->cache = $cache->data[0];
      $this->complete = $cache->data[1];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save() {
    $cleaned = $this->cleanContents($this->cache);
    cache_set($this->key, [$cleaned, $this->complete]);
  }

}
