<?php

namespace NoDrupal\Tests\flysystem\Unit\Flysystem\Adapter;

use Drupal\flysystem\Flysystem\Adapter\CacheItem;
use Drupal\flysystem\Flysystem\Adapter\CacheItemBackend;
use Drupal\Tests\UnitTestCase;

/**
 * @class CacheItemBackendTest
 *
 * @coversDefaultClass \Drupal\flysystem\Flysystem\Adapter\CacheItemBackend
 * @covers ::__construct
 */
class CacheItemBackendTest extends UnitTestCase {

  /**
   * @var \PHPUnit_Framework_MockObject_MockObject
   */
  protected $cacheBackend;

  /**
   * @var \PHPUnit_Framework_MockObject_MockObject
   */
  protected $cacheItemBackend;

  public function setup() {
    $this->cacheBackend = $this->getMock('\Drupal\Core\Cache\CacheBackendInterface');
    $this->cacheItemBackend = new CacheItemBackend($this->cacheBackend);
  }

  public function testLoad() {
    $this->cacheBackend->expects($this->once())
      ->method('setCacheItemBackend');

    $cached = new \stdClass();
    $cached->data = new CacheItem('test', 'test', $this->cacheItemBackend);

    $this->cacheBackend->expects($this->once())
      ->method('get')
      ->willReturn($cached);

    $item = $this->cacheItemBackend->load('test-scheme', 'test');
    $this->assertInstanceOf('\Drupal\flysystem\Flysystem\Adapter\CacheItem', $item);
  }

  public function testLoadMiss() {
    $this->cacheBackend->expects($this->never())
      ->method('setCacheItemBackend');

    $item = $this->cacheItemBackend->load('test-scheme', 'test');
    $this->assertInstanceOf('\Drupal\flysystem\Flysystem\Adapter\CacheItem', $item);
  }
}
