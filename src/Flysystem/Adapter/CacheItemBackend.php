<?php

namespace Drupal\flysystem\Flysystem\Adapter;

use Drupal\Core\Cache\CacheBackendInterface;

/**
 * Storage backend for cache items.
 *
 * This class is separated out from CacheItems so we can easily test loading,
 * saving, and deleting separately from the logic to reach back to a child
 * Flysystem adapter.
 */
class CacheItemBackend {

  /**
   * The Drupal cache backend to store data in.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   * Construct a new CacheItemBackend.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheBackend
   *   The Drupal cache backend to store items in.
   */
  public function __construct(CacheBackendInterface $cacheBackend) {
    $this->cacheBackend = $cacheBackend;
  }

  /**
   * Load a cache item for a given scheme and path.
   *
   * @param string $scheme
   *   The scheme of the item to load.
   * @param string $path
   *   The path of the item to load.
   *
   * @return \Drupal\flysystem\Flysystem\Adapter\CacheItem
   *   The cache item, or a new cache item if one isn't in the cache.
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
   * Set a cache item in the backend.
   *
   * @param \Drupal\flysystem\Flysystem\Adapter\CacheItem $item
   *   The item to set.
   */
  public function set(CacheItem $item) {
    $key = $this->getCacheKey($item->getScheme(), $item->getPath());
    $this->cacheBackend->set($key, $item);
  }

  /**
   * Delete a cache item from the backend.
   *
   * @param \Drupal\flysystem\Flysystem\Adapter\CacheItem $item
   *   The cache item to delete.
   */
  public function delete(CacheItem $item) {
    $this->deleteByKey($item->getScheme(), $item->getPath());
  }

  /**
   * Delete an item by a key, saving having to load an item to delete it.
   *
   * @param string $scheme
   *   The scheme of the item to delete.
   * @param string $path
   *   The path of the item to delete.
   */
  public function deleteByKey($scheme, $path) {
    $this->deleteMultiple($scheme, [$path]);
  }

  /**
   * Delete multiple paths for a scheme from the cache.
   *
   * @param string $scheme
   *   The scheme of the items to delete.
   * @param array $paths
   *   The array of paths to delete.
   */
  public function deleteMultiple($scheme, array $paths) {
    $keys = array();
    foreach ($paths as $path) {
      $keys[] = $this->getCacheKey($scheme, $path);
    }
    $this->cacheBackend->deleteMultiple($keys);
  }

  /**
   * Get the cache key for a cache item.
   *
   * @param string $scheme
   *   The scheme of the cache item.
   * @param string $path
   *   The path of the cache item.
   *
   * @return string
   *   A hashed key suitable for use in a cache.
   */
  public function getCacheKey($scheme, $path) {
    $key = "$scheme://$path";
    return md5($key);
  }

}
