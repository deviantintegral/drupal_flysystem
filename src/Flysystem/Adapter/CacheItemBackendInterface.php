<?php
namespace Drupal\flysystem\Flysystem\Adapter;

interface CacheItemBackendInterface {
  public function load($scheme, $path);

  public function set(CacheItem $item);

  public function delete(CacheItem $item);

  public function deleteMultiple($scheme, array $paths);

  /**
   * @param $scheme
   * @param $path
   * @return string
   */
  public function getCacheKey($scheme, $path);
}
