<?php

namespace Drupal\flysystem\Flysystem\Adapter;

use Drupal\Core\Cache\CacheBackendInterface;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;

/**
 * @class DrupalCacheAdapter
 * @package Drupal\flysystem\Flysystem\Adapter
 */
class DrupalCacheAdapter implements AdapterInterface {

  /**
   * The flysystem adapter to cache data for.
   *
   * @var \League\Flysystem\AdapterInterface
   */
  protected $adapter;

  /**
   * The cache backend to store data in.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   * Construct a new caching Flysystem adapter.
   *
   * @param \League\Flysystem\AdapterInterface $adapter
   *   The flysystem adapter to cache data for.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheBackend
   *   The cache backend to store data in.
   */
  public function __construct(AdapterInterface $adapter, CacheBackendInterface $cacheBackend) {
    $this->adapter = $adapter;
    $this->cacheBackend = $cacheBackend;
  }

  /**
   * {@inheritdoc}
   */
  public function write($path, $contents, Config $config) {
    $metadata = $this->adapter->write($path, $contents, $config);

    if ($metadata) {
      $item = $this->getCachedItem($path);
      $item->setMetadata($metadata);
      $this->cacheBackend->set($path, $item);
    }

    return $metadata;
  }

  /**
   * {@inheritdoc}
   */
  public function writeStream($path, $resource, Config $config) {
    $metadata = $this->adapter->writeStream($path, $resource, $config);

    if ($metadata) {
      $item = $this->getCachedItem($path);
      $item->setMetadata($metadata);
      $this->cacheBackend->set($path, $item);
    }

    return $metadata;
  }

  /**
   * {@inheritdoc}
   */
  public function update($path, $contents, Config $config) {
    $metadata = $this->adapter->update($path, $contents, $config);

    if ($metadata) {
      $item = $this->getCachedItem($path);
      $item->setMetadata($metadata);
      $this->cacheBackend->set($path, $item);
    }

    return $metadata;
  }

  /**
   * {@inheritdoc}
   */
  public function updateStream($path, $resource, Config $config) {
    $metadata = $this->adapter->updateStream($path, $resource, $config);

    if ($metadata) {
      $item = $this->getCachedItem($path);
      $item->setMetadata($metadata);
      $this->cacheBackend->set($path, $item);
    }

    return $metadata;
  }

  /**
   * {@inheritdoc}
   */
  public function rename($path, $newpath) {
    $result = $this->adapter->rename($path, $newpath);

    if ($result) {
      $item = $this->getCachedItem($path);
      $item->setPath($newpath);
      $metadata = $item->getMetadata();
      $metadata['path'] = $newpath;
      $item->setMetadata($metadata);
      $this->cacheBackend->set($newpath, $item);
      $this->cacheBackend->delete($path);
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function copy($path, $newpath) {
    $result = $this->adapter->copy($path, $newpath);

    if ($result) {
      $this->cacheBackend->delete($newpath);
      $this->getMetadata($newpath);
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function delete($path) {
    $result = $this->adapter->delete($path);

    if ($result) {
      $this->cacheBackend->delete($path);
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteDir($dirname) {
    // Before the delete we need to know what files are in the directory.
    $contents = $this->adapter->listContents($dirname, TRUE);

    $result = $this->adapter->deleteDir($dirname);

    if ($result) {
      $paths = array_column($contents, 'path');
      $this->cacheBackend->deleteMultiple($paths);
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function createDir($dirname, Config $config) {
    $result = $this->adapter->createDir($dirname, $config);

    // Warm the metadata cache.
    if ($result) {
      $this->getMetadata($dirname);
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function setVisibility($path, $visibility) {
    $result = $this->adapter->setVisibility($path, $visibility);

    if ($result) {
      $item = $this->getCachedItem($path);
      $item->setVisibility($visibility);
      $this->cacheBackend->set($path, $item);
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function has($path) {
    if ($item = $this->cacheBackend->get($path)) {
      return TRUE;
    }

    // Always check the upstream adapter for new files.
    // TODO: This could be a good place for a microcache?
    return $this->adapter->has($path);
  }

  /**
   * {@inheritdoc}
   */
  public function read($path) {
    return $this->adapter->read($path);
  }

  /**
   * {@inheritdoc}
   */
  public function readStream($path) {
    return $this->adapter->readStream($path);
  }

  /**
   * {@inheritdoc}
   */
  public function listContents($directory = '', $recursive = FALSE) {
    // Don't cache directory listings to avoid having to keep track of
    // incomplete cache entries.
    // TODO: This could be a good place for a microcache?
    return $this->adapter->listContents($directory, $recursive);
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadata($path) {
    if ($cached = $this->cacheBackend->get($path)) {
      /** @var \Drupal\flysystem\Flysystem\Adapter\CacheItem $item */
      $item = $cached->data;
      if ($metadata = $item->getMetadata()) {
        return $metadata;
      }
    }

    $metadata = $this->adapter->getMetadata($path);
    $item = $this->getCachedItem($path);
    $item->setMetadata($metadata);
    $this->cacheBackend->set($path, $item);

    return $metadata;
  }

  /**
   * {@inheritdoc}
   */
  public function getSize($path) {
    if ($cached = $this->cacheBackend->get($path)) {
      /** @var \Drupal\flysystem\Flysystem\Adapter\CacheItem $item */
      $item = $cached->data;
      if ($size = $item->getSize()) {
        return $size;
      }
    }

    $size = $this->adapter->getSize($path);
    $item = $this->getCachedItem($path);
    $item->setSize($size);
    $this->cacheBackend->set($path, $item);

    return $size;
  }

  /**
   * {@inheritdoc}
   */
  public function getMimetype($path) {
    if ($cached = $this->cacheBackend->get($path)) {
      /** @var \Drupal\flysystem\Flysystem\Adapter\CacheItem $item */
      $item = $cached->data;
      if ($mimetype = $item->getMimetype()) {
        return $mimetype;
      }
    }

    $mimetype = $this->adapter->getMimetype($path);
    $item = $this->getCachedItem($path);
    $item->setMimetype($mimetype);
    $this->cacheBackend->set($path, $item);

    return $mimetype;
  }

  /**
   * {@inheritdoc}
   */
  public function getTimestamp($path) {
    if ($cached = $this->cacheBackend->get($path)) {
      /** @var \Drupal\flysystem\Flysystem\Adapter\CacheItem $item */
      $item = $cached->data;
      if ($timestamp = $item->getTimestamp()) {
        return $timestamp;
      }
    }

    $timestamp = $this->adapter->getTimestamp($path);
    $item = $this->getCachedItem($path);
    $item->setTimestamp($timestamp);
    $this->cacheBackend->set($path, $item);

    return $timestamp;
  }

  /**
   * {@inheritdoc}
   */
  public function getVisibility($path) {
    if ($cached = $this->cacheBackend->get($path)) {
      /** @var \Drupal\flysystem\Flysystem\Adapter\CacheItem $item */
      $item = $cached->data;
      if ($visibility = $item->getVisibility()) {
        return $visibility;
      }
    }

    $visibility = $this->adapter->getVisibility($path);
    $item = $this->getCachedItem($path);
    $item->setVisibility($visibility);
    $this->cacheBackend->set($path, $item);

    return $visibility;
  }

  /**
   * @param $path
   *
   * @return \Drupal\flysystem\Flysystem\Adapter\CacheItem
   */
  private function getCachedItem($path) {
    if ($cached = $this->cacheBackend->get($path)) {
      return $cached->data;
    }

    return new CacheItem($path);
  }

}
