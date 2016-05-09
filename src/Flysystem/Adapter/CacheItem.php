<?php

namespace Drupal\flysystem\Flysystem\Adapter;

class CacheItem {
  /**
   * @var string
   */
  protected $path;

  /**
   * @var array
   */
  protected $metadata;

  /**
   * @var array
   */
  protected $mimetype;
  /**
   * @var array
   */
  protected $size;

  public function __construct($path) {
    $this->path = $path;
  }

  /**
   * @return array
   */
  public function getMetadata() {
    return $this->metadata;
  }

  /**
   * @param array $metadata
   */
  public function setMetadata($metadata) {
    $this->metadata = $metadata;
  }

  /**
   * @return array
   */
  public function getMimetype() {
    return $this->mimetype;
  }

  /**
   * @param array $mimetype
   */
  public function setMimetype($mimetype) {
    $this->mimetype = $mimetype;
  }

  /**
   * @return array
   */
  public function getSize() {
    return $this->size;
  }

  /**
   * @param array $size
   */
  public function setSize($size) {
    $this->size = $size;
  }

  /**
   * @return array
   */
  public function getTimestamp() {
    return $this->timestamp;
  }

  /**
   * @param array $timestamp
   */
  public function setTimestamp($timestamp) {
    $this->timestamp = $timestamp;
  }

  /**
   * @return string
   */
  public function getType() {
    return $this->type;
  }

  /**
   * @param string $type
   */
  public function setType($type) {
    $this->type = $type;
  }

  /**
   * @return array
   */
  public function getVisibility() {
    return $this->visibility;
  }

  /**
   * @param array $visibility
   */
  public function setVisibility($visibility) {
    $this->visibility = $visibility;
  }
  /**
   * @var array
   */
  protected $timestamp;
  /**
   * @var string
   */
  protected $type;
  /**
   * @var array
   */
  protected $visibility;

  /**
   * @return string
   */
  public function getPath() {
    return $this->path;
  }

  /**
   * @param string $path
   */
  public function setPath($path) {
    $this->path = $path;
  }
}
