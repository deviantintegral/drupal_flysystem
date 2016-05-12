<?php

namespace NoDrupal\Tests\flysystem\Unit\Flysystem\Adapter;

use Drupal\flysystem\Flysystem\Adapter\CacheItem;
use Drupal\flysystem\Flysystem\Adapter\CacheItemBackend;
use Drupal\Tests\UnitTestCase;

/**
 * @class CacheItemBackendTest
 *
 * @group flysystem
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

  /**
   * @covers ::load
   */
  public function testLoad() {
    $cache_item = $this->getCacheItemStub();
    $cache_item->expects($this->once())
      ->method('setCacheItemBackend');

    $cached = new \stdClass();
    $cached->data = $cache_item;

    $this->cacheBackend->expects($this->once())
      ->method('get')
      ->willReturn($cached);

    $item = $this->cacheItemBackend->load('test-scheme', 'test');
    $this->assertInstanceOf('\Drupal\flysystem\Flysystem\Adapter\CacheItem', $item);
  }

  /**
   * @covers ::load
   */
  public function testLoadMiss() {
    $cache_item = $this->getCacheItemStub();
    $cache_item->expects($this->never())
      ->method('setCacheItemBackend');

    $item = $this->cacheItemBackend->load('test-scheme', 'test');
    $this->assertInstanceOf('\Drupal\flysystem\Flysystem\Adapter\CacheItem', $item);
  }

  /**
   * @return \PHPUnit_Framework_MockObject_MockObject
   */
  private function getCacheItemStub() {
    $cache_item = $this->getMockBuilder('\Drupal\flysystem\Flysystem\Adapter\CacheItem')
      ->setConstructorArgs(['test', 'test', $this->cacheItemBackend])
      ->getMock();
    return $cache_item;
  }
}
