<?php

namespace Drupal\flysystem\Flysystem\Adapter;

use Drupal\Core\Cache\CacheBackendInterface;

/**
 *
 */
class CacheItemBackend implements CacheItemBackendInterface {

  /**
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   *
   */
  public function __construct(CacheBackendInterface $cacheBackend) {
    $this->cacheBackend = $cacheBackend;
  }

  /**
   *
   */
  public function load($scheme, $path) {
    $key = $this->getCacheKey($scheme, $path);
    if ($cached = $this->cacheBackend->get($key)) {
      /** @var \Drupal\flysystem\Flysystem\Adapter\CacheItem $item */
      $item = $cached->data;
      $item->setCacheItemBackend($this);
    }
    else {
      $item = new CacheItem($scheme, $path, $this);
    }

    return $item;
  }

  /**
   *
   */
  public function set(CacheItem $item) {
    $key = $this->getCacheKey($item->getScheme(), $item->getPath());
    $this->cacheBackend->set($key, $item);
  }

  /**
   *
   */
  public function delete(CacheItem $item) {
    $this->deleteByKey($item->getScheme(), $item->getPath());
  }

  /**
   * Delete an item by a key, saving having to load an item to delete it.
   */
  public function deleteByKey($scheme, $path) {
    $this->deleteMultiple($scheme, [$path]);
  }

  /**
   *
   */
  public function deleteMultiple($scheme, array $paths) {
    $keys = array();
    foreach ($paths as $path) {
      $keys[] = $this->getCacheKey($scheme, $path);
    }
    $this->cacheBackend->deleteMultiple($keys);
  }

  /**
   *
   */
  public function getCacheKey($scheme, $path) {
    $key = "$scheme://$path";
    return md5($key);
  }

}
