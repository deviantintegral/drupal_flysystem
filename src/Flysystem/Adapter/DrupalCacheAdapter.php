<?php

namespace Drupal\flysystem\Flysystem\Adapter;

use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;

/**
 * A Flysystem adapter implementing caching with Drupal's Cache API.
 *
 * @class DrupalCacheAdapter
 */
class DrupalCacheAdapter implements AdapterInterface {

  /**
   * The scheme of the stream wrapper used for this adapter.
   *
   * @var string
   */
  protected $scheme;

  /**
   * The flysystem adapter to cache data for.
   *
   * @var \League\Flysystem\AdapterInterface
   */
  protected $adapter;

  /**
   * The cache backend to store data in.
   *
   * @var \Drupal\flysystem\Flysystem\Adapter\CacheItemBackend
   */
  protected $cacheItemBackend;

  /**
   * Construct a new caching Flysystem adapter.
   *
   * @param string $scheme
   *   The scheme of the stream wrapper used for this adapter.
   * @param \League\Flysystem\AdapterInterface $adapter
   *   The flysystem adapter to cache data for.
   * @param \Drupal\flysystem\Flysystem\Adapter\CacheItemBackendInterface $cacheItemBackend
   *   The cache backend to store data in.
   */
  public function __construct($scheme, AdapterInterface $adapter, CacheItemBackendInterface $cacheItemBackend) {
    $this->scheme = $scheme;
    $this->adapter = $adapter;
    $this->cacheItemBackend = $cacheItemBackend;
  }

  /**
   * {@inheritdoc}
   */
  public function write($path, $contents, Config $config) {
    $metadata = $this->adapter->write($path, $contents, $config);

    if ($metadata) {
      $item = $this->cacheItemBackend->load($this->getScheme(), $path);
      $item->setMetadata($metadata);
      $item->save();
    }

    return $metadata;
  }

  /**
   * {@inheritdoc}
   */
  public function writeStream($path, $resource, Config $config) {
    $metadata = $this->adapter->writeStream($path, $resource, $config);

    if ($metadata) {
      $item = $this->cacheItemBackend->load($this->getScheme(), $path);
      $item->setMetadata($metadata);
      $item->save();
    }

    return $metadata;
  }

  /**
   * {@inheritdoc}
   */
  public function update($path, $contents, Config $config) {
    $metadata = $this->adapter->update($path, $contents, $config);

    if ($metadata) {
      $item = $this->cacheItemBackend->load($this->getScheme(), $path);
      $item->setMetadata($metadata);
      $item->save();
    }

    return $metadata;
  }

  /**
   * {@inheritdoc}
   */
  public function updateStream($path, $resource, Config $config) {
    $metadata = $this->adapter->updateStream($path, $resource, $config);

    if ($metadata) {
      $item = $this->cacheItemBackend->load($this->getScheme(), $path);
      $item->setMetadata($metadata);
      $item->save();
    }

    return $metadata;
  }

  /**
   * {@inheritdoc}
   */
  public function rename($path, $newpath) {
    $result = $this->adapter->rename($path, $newpath);

    if ($result) {
      $item = $this->cacheItemBackend->load($this->getScheme(), $newpath);
      $item->setPath($newpath);
      $metadata = $item->getMetadata();
      $metadata['path'] = $newpath;
      $item->setMetadata($metadata);
      $item->save();
      $item->delete();
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function copy($path, $newpath) {
    $result = $this->adapter->copy($path, $newpath);

    if ($result) {
      $this->cacheItemBackend->deleteByKey($this->getScheme(), $newpath);
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
      $this->cacheItemBackend->deleteByKey($this->getScheme(), $path);
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
      $this->cacheItemBackend->deleteMultiple($this->getScheme(), $paths);
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
      $item = $this->cacheItemBackend->load($this->getScheme(), $path);
      $item->setVisibility($visibility);
      $item->save();
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function has($path) {
    if ($this->cacheItemBackend->load($this->getScheme(), $path)) {
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
    $item = $this->cacheItemBackend->load($this->getScheme(), $path);
    if ($metadata = $item->getMetadata()) {
      return $metadata;
    }

    $metadata = $this->adapter->getMetadata($path);
    $item->setMetadata($metadata);
    $item->save();

    return $metadata;
  }

  /**
   * {@inheritdoc}
   */
  public function getSize($path) {
    if ($item = $this->cacheItemBackend->load($this->getScheme(), $path)) {
      if ($size = $item->getSize()) {
        return $size;
      }
    }

    $size = $this->adapter->getSize($path);
    $item = $this->cacheItemBackend->load($this->getScheme(), $path);
    $item->setSize($size);
    $item->save();

    return $size;
  }

  /**
   * {@inheritdoc}
   */
  public function getMimetype($path) {
    if ($item = $this->cacheItemBackend->load($this->getScheme(), $path)) {
      if ($mimetype = $item->getMimetype()) {
        return $mimetype;
      }
    }

    $mimetype = $this->adapter->getMimetype($path);
    $item = $this->cacheItemBackend->load($this->getScheme(), $path);
    $item->setMimetype($mimetype);
    $item->save();

    return $mimetype;
  }

  /**
   * {@inheritdoc}
   */
  public function getTimestamp($path) {
      if ($item = $this->cacheItemBackend->load($this->getScheme(), $path)) {
      if ($timestamp = $item->getTimestamp()) {
        return $timestamp;
      }
    }

    $timestamp = $this->adapter->getTimestamp($path);
    $item = $this->cacheItemBackend->load($this->getScheme(), $path);
    $item->setTimestamp($timestamp);
    $item->save();

    return $timestamp;
  }

  /**
   * {@inheritdoc}
   */
  public function getVisibility($path) {
    if ($item = $this->cacheItemBackend->load($this->getScheme(), $path)) {
      if ($visibility = $item->getVisibility()) {
        return $visibility;
      }
    }

    $visibility = $this->adapter->getVisibility($path);
    $item = $this->cacheItemBackend->load($this->getScheme(), $path);
    $item->setVisibility($visibility);
    $item->save();

    return $visibility;
  }

  /**
   * @return string
   */
  private function getScheme() {
    return $this->scheme;
  }

}
