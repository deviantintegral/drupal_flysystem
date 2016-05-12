<?php

namespace Drupal\flysystem\Flysystem\Adapter;

/**
 * A filesystem item stored in the Drupal cache.
 *
 * Many of the file properties don't seem like they should be arrays, but that's
 * what the upstream Flysystem library uses.
 *
 * @class CacheItem
 */
class CacheItem {

  /**
   * The scheme of the stream wrapper used for this cache item.
   *
   * @var string
   */
  protected $scheme;

  /**
   * The path to the item.
   *
   * @var string
   */
  protected $path;

  /**
   * @var CacheItemBackendInterface
   */
  protected $cacheItemBackend;

  /**
   * The array of metadata for the item.
   *
   * @var array
   */
  protected $metadata;

  /**
   * The array of MIME information for the item.
   *
   * @var array
   */
  protected $mimetype;

  /**
   * The array of size information for the item.
   *
   * @var array
   */
  protected $size;

  /**
   * The array of timestamp information for the item.
   *
   * @var array
   */
  protected $timestamp;

  /**
   * The type of the item.
   *
   * @var string
   */
  protected $type;

  /**
   * The visibility information for the item.
   *
   * @var array
   */
  protected $visibility;

  /**
   * CacheItem constructor.
   *
   * @param string $scheme
   *   The scheme of the stream wrapper used for this cache item.
   * @param string $path
   *   The path of the item.
   * @param CacheItemBackendInterface $cacheItemBackend
   */
  public function __construct($scheme, $path, CacheItemBackendInterface $cacheItemBackend) {
    $this->scheme = $scheme;
    $this->path = $path;
    $this->cacheItemBackend = $cacheItemBackend;
  }

  /**
   * Get the metadata for the item.
   *
   * @return array
   *   The array of metadata for the item.
   */
  public function getMetadata() {
    return $this->metadata;
  }

  /**
   * Set the metadata for the item.
   *
   * @param array $metadata
   *   The array of metadata for the item.
   */
  public function setMetadata($metadata) {
    $this->metadata = $metadata;
  }

  /**
   * Get the MIME type information for the item.
   *
   * @return array
   *   The array of MIME type information for the item.
   */
  public function getMimetype() {
    return $this->mimetype;
  }

  /**
   * Set the MIME type for the item.
   *
   * @param array $mimetype
   *   The array of MIME type information for the item.
   */
  public function setMimetype($mimetype) {
    $this->mimetype = $mimetype;
  }

  /**
   * Get the size information for the item.
   *
   * @return array
   *   The array of size information for the item.
   */
  public function getSize() {
    return $this->size;
  }

  /**
   * Set the size information for the item.
   *
   * @param array $size
   *   The array of size information for the item.
   */
  public function setSize($size) {
    $this->size = $size;
  }

  /**
   * Get the timestamp information for the item.
   *
   * @return array
   *   The array of size information for the item.
   */
  public function getTimestamp() {
    return $this->timestamp;
  }

  /**
   * Set the timestamp information for the item.
   *
   * @param array $timestamp
   *   The array of size information for the item.
   */
  public function setTimestamp($timestamp) {
    $this->timestamp = $timestamp;
  }

  /**
   * Get the type of this item.
   *
   * @return string
   *   The type of this item, such as 'file' or 'directory'.
   */
  public function getType() {
    return $this->type;
  }

  /**
   * Set the type of this item.
   *
   * @param string $type
   *   The type of this item, such as 'file' or 'directory'.
   */
  public function setType($type) {
    $this->type = $type;
  }

  /**
   * Get the visibility of this item.
   *
   * @return array
   *   The array of visibility information.
   */
  public function getVisibility() {
    return $this->visibility;
  }

  /**
   * Set the visibility of this item.
   *
   * @param array $visibility
   *   The array of visibility information.
   */
  public function setVisibility($visibility) {
    $this->visibility = $visibility;
  }

  /**
   * Get the path of this item.
   *
   * @return string
   *   The path of this item.
   */
  public function getPath() {
    return $this->path;
  }

  /**
   * Set the path of this item.
   *
   * @param string $path
   *   The path of this item.
   */
  public function setPath($path) {
    $this->path = $path;
  }

  /**
   * Save this cache item.
   */
  public function save() {
    $this->cacheItemBackend->set($this);
  }

  /**
   * Delete this cache item.
   */
  public function delete() {
    $this->cacheItemBackend->delete($this);
  }

  /**
   * Implements __sleep() to not serialize the cache backend.
   *
   * @return array
   *   An array of properties to sleep.
   */
  public function __sleep() {
    $properties = array_keys((array) $this);
    unset($properties['cacheBackend']);
    return $properties;
  }

  /**
   * @return CacheItemBackendInterface
   */
  public function getCacheItemBackend() {
    return $this->cacheItemBackend;
  }

  /**
   * @param CacheItemBackendInterface $cacheBackend
   */
  public function setCacheItemBackend($cacheBackend) {
    $this->cacheItemBackend = $cacheBackend;
  }

  /**
   * @return string
   */
  public function getScheme() {
    return $this->scheme;
  }

}
