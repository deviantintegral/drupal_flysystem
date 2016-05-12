<?php

namespace NoDrupal\Tests\flysystem\Unit\Flysystem\Adapter;

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
   * @var \Drupal\flysystem\Flysystem\Adapter\CacheItemBackend
   */
  protected $cacheItemBackend;

  /**
   *
   */
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
   * @covers ::set
   */
  public function testSet() {
    $cache_item = $this->getCacheItemStub();
    $this->cacheBackend->expects($this->once())
      ->method('set')
      ->with($this->cacheItemBackend->getCacheKey('test', 'test'), $cache_item);

    $this->cacheItemBackend->set($cache_item);
  }

  /**
   * @covers ::delete
   */
  public function testDelete() {
    $cache_item = $this->getCacheItemStub();
    $cache_item->expects($this->never())
      ->method('setCacheItemBackend');

    $this->cacheBackend->expects($this->once())
      ->method('set')
      ->with($this->cacheItemBackend->getCacheKey('test', 'test'), $cache_item);

    $this->cacheItemBackend->set($cache_item);
    $this->cacheItemBackend->delete($cache_item);
    $this->cacheItemBackend->load('test', 'test');
  }

  /**
   * @covers ::deleteByKey
   */
  public function testDeleteByKey() {
    $cache_item = $this->getCacheItemStub();
    $cache_item->expects($this->never())
      ->method('setCacheItemBackend');

    $this->cacheBackend->expects($this->once())
      ->method('set')
      ->with($this->cacheItemBackend->getCacheKey('test', 'test'), $cache_item);

    $this->cacheItemBackend->set($cache_item);
    $this->cacheItemBackend->deleteByKey('test', 'test');
    $this->cacheItemBackend->load('test', 'test');
  }

  /**
   * @covers ::deleteMultiple
   */
  public function testDeleteMultiple() {
    $cache_item_one = $this->getCacheItemStub('test', 'one');
    $cache_item_one->expects($this->never())
      ->method('setCacheItemBackend');

    $cache_item_two = $this->getCacheItemStub('test', 'two');
    $cache_item_two->expects($this->never())
      ->method('setCacheItemBackend');

    $this->cacheBackend->expects($this->exactly(2))
      ->method('set')
      ->withConsecutive(
        [$this->cacheItemBackend->getCacheKey('test', 'one'), $cache_item_one],
        [$this->cacheItemBackend->getCacheKey('test', 'two'), $cache_item_two]
      );

    $this->cacheItemBackend->set($cache_item_one);
    $this->cacheItemBackend->set($cache_item_two);
    $this->cacheItemBackend->deleteMultiple('test', ['one', 'two']);
    $this->cacheItemBackend->load('test', 'one');
    $this->cacheItemBackend->load('test', 'two');
  }

  /**
   * @covers ::getCacheKey
   */
  public function testGetCacheKey() {
    $this->assertEquals(md5("testing://test.txt"), $this->cacheItemBackend->getCacheKey('testing', 'test.txt'));
  }

  /**
   * @return \PHPUnit_Framework_MockObject_MockObject
   */
  private function getCacheItemStub($scheme = 'test', $path = 'test') {
    $cache_item = $this->getMockBuilder('\Drupal\flysystem\Flysystem\Adapter\CacheItem')
      ->setConstructorArgs([$scheme, $path, $this->cacheItemBackend])
      ->getMock();
    $cache_item->method('getScheme')
      ->willReturn($scheme);
    $cache_item->method('getPath')
      ->willReturn($path);

    return $cache_item;
  }

}
