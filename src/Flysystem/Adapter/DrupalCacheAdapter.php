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
   * @param \Drupal\flysystem\Flysystem\Adapter\CacheItemBackend $cacheItemBackend
   *   The cache backend to store data in.
   */
  public function __construct($scheme, AdapterInterface $adapter, CacheItemBackend $cacheItemBackend) {
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
      $item = $this->cacheItemBackend->load($this->scheme, $path);
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
      $item = $this->cacheItemBackend->load($this->scheme, $path);
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
      $item = $this->cacheItemBackend->load($this->scheme, $path);
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
      $item = $this->cacheItemBackend->load($this->scheme, $path);
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
      $item = $this->cacheItemBackend->load($this->scheme, $newpath);
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
      $this->cacheItemBackend->deleteByKey($this->scheme, $newpath);
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
      $this->cacheItemBackend->deleteByKey($this->scheme, $path);
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
      $this->cacheItemBackend->deleteMultiple($this->scheme, $paths);
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
      $item = $this->cacheItemBackend->load($this->scheme, $path);
      $item->setVisibility($visibility);
      $item->save();
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function has($path) {
    if ($this->cacheItemBackend->load($this->scheme, $path)) {
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
    $item = $this->cacheItemBackend->load($this->scheme, $path);
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
    return $this->fetchMetadataKey($path, 'size');
  }

  /**
   * {@inheritdoc}
   */
  public function getMimetype($path) {
    return $this->fetchMetadataKey($path, 'mimetype');
  }

  /**
   * {@inheritdoc}
   */
  public function getTimestamp($path) {
    return $this->fetchMetadataKey($path, 'timestamp');
  }

  /**
   * {@inheritdoc}
   */
  public function getVisibility($path) {
    if ($item = $this->cacheItemBackend->load($this->scheme, $path)) {
      if ($visibility = $item->getVisibility()) {
        return $visibility;
      }
    }

    $visibility = $this->adapter->getVisibility($path);
    $item = $this->cacheItemBackend->load($this->scheme, $path);
    $item->setVisibility($visibility);
    $item->save();

    return $visibility;
  }

  /**
   * Fetch a specific key from metadata.
   *
   * @param string $path
   *   The path to load metadata for.
   * @param string $key
   *   The key in metadata, such as 'mimetype', to load metadata for.
   *
   * @return array
   *   The array of metadata.
   */
  protected function fetchMetadataKey($path, $key) {
    if ($item = $this->cacheItemBackend->load($this->scheme, $path)) {
      if (($metadata = $item->getMetadata()) && isset($metadata[$key])) {
        return $metadata;
      }
    }

    $method = 'get' . ucfirst($key);
    $metadata = $this->adapter->$method($path);

    // Merge any new metadata into the existing metadata.
    $item = $this->cacheItemBackend->load($this->scheme, $path);
    $item->setMetadata($metadata);
    $item->save();

    return $metadata;
  }

}
