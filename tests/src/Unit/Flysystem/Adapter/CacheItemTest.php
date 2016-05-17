<?php

namespace NoDrupal\Tests\flysystem\Unit\Flysystem\Adapter;

use Drupal\Core\Cache\MemoryBackend;
use Drupal\flysystem\Flysystem\Adapter\CacheItem;
use Drupal\flysystem\Flysystem\Adapter\CacheItemBackend;
use Drupal\Tests\UnitTestCase;

/**
 * Tests \Drupal\flysystem\Flysystem\Adapter\CacheItem.
 *
 * @class CacheItemTest
 *
 * @group flysystem
 *
 * @coversDefaultClass \Drupal\flysystem\Flysystem\Adapter\CacheItem
 */
class CacheItemTest extends UnitTestCase {

  /**
   * Test all get and set methods.
   *
   * @param string $method
   *   The method to test.
   * @param mixed $value
   *   The value to use.
   *
   * @dataProvider getSetMethodsProvider
   *
   * @covers ::__construct
   * @covers ::getMetadata
   * @covers ::getPath
   * @covers ::getType
   * @covers ::getVisibility
   * @covers ::getCacheItemBackend
   * @covers ::setMetadata
   * @covers ::setPath
   * @covers ::setType
   * @covers ::setVisibility
   * @covers ::setCacheItemBackend
   */
  public function testGetSetMethods($method, $value) {
    $item = new CacheItem('testing', 'path', new CacheItemBackend(new MemoryBackend('test')));
    $this->assertEquals('path', $item->getPath());

    $set = 'set' . ucfirst($method);
    $get = 'get' . ucfirst($method);
    $item->$set($value);
    $this->assertEquals($value, $item->$get());
  }

  /**
   * Test getting the cache item scheme.
   *
   * @covers ::getScheme
   */
  public function testGetScheme() {
    $item = new CacheItem('testing', 'path', new CacheItemBackend(new MemoryBackend('test')));
    $this->assertEquals('testing', $item->getScheme());
  }

  /**
   * Test saving a cache item.
   *
   * @covers ::save
   */
  public function testSave() {
    /** @var \PHPUnit_Framework_MockObject_MockObject|\Drupal\flysystem\Flysystem\Adapter\CacheItemBackend $backend */
    $backend = $this->getMockBuilder('\Drupal\flysystem\Flysystem\Adapter\CacheItemBackend')
      ->disableOriginalConstructor()
      ->getMock();

    $item = new CacheItem('testing', 'path', $backend);
    $backend->expects($this->once())
      ->method('set')
      ->with($item);

    $item->save();
  }

  /**
   * Test deleting a cache item.
   *
   * @covers ::delete
   */
  public function testDelete() {
    /** @var \PHPUnit_Framework_MockObject_MockObject|\Drupal\flysystem\Flysystem\Adapter\CacheItemBackend $backend */
    $backend = $this->getMockBuilder('\Drupal\flysystem\Flysystem\Adapter\CacheItemBackend')
      ->disableOriginalConstructor()
      ->getMock();

    $item = new CacheItem('testing', 'path', $backend);
    $backend->expects($this->once())
      ->method('delete')
      ->with($item);

    $item->delete();
  }

  /**
   * Test removing the cache backend when saving.
   *
   * @covers::__sleep
   */
  public function testSleep() {
    /** @var \PHPUnit_Framework_MockObject_MockObject|\Drupal\flysystem\Flysystem\Adapter\CacheItemBackend $backend */
    $backend = $this->getMockBuilder('\Drupal\flysystem\Flysystem\Adapter\CacheItemBackend')
      ->disableOriginalConstructor()
      ->getMock();

    $item = new CacheItem('testing', 'path', $backend);
    $properties = $item->__sleep();
    $this->assertFalse(isset($properties['cacheBackend']));
  }

  /**
   * Return an array of all get / set methods.
   *
   * @return array
   *   An array of method name components and a value.
   */
  public function getSetMethodsProvider() {
    return [
      ['metadata', ['metadata']],
      ['path', 'path'],
      ['type', 'type'],
      ['visibility', ['visibility']],
      ['cacheItemBackend', $this->getMockBuilder('\Drupal\flysystem\Flysystem\Adapter\CacheItemBackend')->disableOriginalConstructor()->getMock(),
      ],
    ];
  }

}
