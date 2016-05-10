<?php

namespace Drupal\flysystem\Flysystem\Adapter;

use Drupal\Core\Cache\CacheBackendInterface;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use League\Flysystem\Util;

/**
 * @class DrupalCacheAdapter
 * @package Drupal\flysystem\Flysystem\Adapter
 */
class DrupalCacheAdapter implements AdapterInterface {

  protected $adapter;
  protected $cacheBackend;

  public function __construct(AdapterInterface $adapter, CacheBackendInterface $cacheBackend) {
    $this->adapter = $adapter;
    $this->cacheBackend = $cacheBackend;
  }

  /**
   * Write a new file.
   *
   * @param string $path
   * @param string $contents
   * @param Config $config Config object
   *
   * @return array|false false on failure file meta data on success
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
   * Write a new file using a stream.
   *
   * @param string $path
   * @param resource $resource
   * @param Config $config Config object
   *
   * @return array|false false on failure file meta data on success
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
   * Update a file.
   *
   * @param string $path
   * @param string $contents
   * @param Config $config Config object
   *
   * @return array|false false on failure file meta data on success
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
   * Update a file using a stream.
   *
   * @param string $path
   * @param resource $resource
   * @param Config $config Config object
   *
   * @return array|false false on failure file meta data on success
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
   * Rename a file.
   *
   * @param string $path
   * @param string $newpath
   *
   * @return bool
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
   * Copy a file.
   *
   * @param string $path
   * @param string $newpath
   *
   * @return bool
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
   * Delete a file.
   *
   * @param string $path
   *
   * @return bool
   */
  public function delete($path) {
    $result = $this->adapter->delete($path);

    if ($result) {
      $this->cacheBackend->delete($path);
    }

    return $result;
  }

  /**
   * Delete a directory.
   *
   * @param string $dirname
   *
   * @return bool
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
   * Create a directory.
   *
   * @param string $dirname directory name
   * @param Config $config
   *
   * @return array|false
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
   * Set the visibility for a file.
   *
   * @param string $path
   * @param string $visibility
   *
   * @return array|false file meta data
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
   * Check whether a file exists.
   *
   * @param string $path
   *
   * @return array|bool|null
   */
  public function has($path) {
    if ($item = $this->cacheBackend->get($path)) {
      return TRUE;
    }

    return $this->adapter->has($path);
  }

  /**
   * Read a file.
   *
   * @param string $path
   *
   * @return array|false
   */
  public function read($path) {
    return $this->adapter->read($path);
  }

  /**
   * Read a file as a stream.
   *
   * @param string $path
   *
   * @return array|false
   */
  public function readStream($path) {
    return $this->adapter->readStream($path);
  }

  /**
   * List contents of a directory.
   *
   * @param string $directory
   * @param bool $recursive
   *
   * @return array
   */
  public function listContents($directory = '', $recursive = FALSE) {
    // TODO: Implement listContents() method.
    return $this->adapter->listContents($directory, $recursive);
  }

  /**
   * Get all the meta data of a file or directory.
   *
   * @param string $path
   *
   * @return array|false
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
   * Get all the meta data of a file or directory.
   *
   * @param string $path
   *
   * @return array|false
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
   * Get the mimetype of a file.
   *
   * @param string $path
   *
   * @return array|false
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
   * Get the timestamp of a file.
   *
   * @param string $path
   *
   * @return array|false
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
   * Get the visibility of a file.
   *
   * @param string $path
   *
   * @return array|false
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
