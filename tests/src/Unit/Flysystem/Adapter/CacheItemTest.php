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
   * @dataProvider getSetMethodsProvider
   *
   * @covers ::__construct
   * @covers ::getMetadata
   * @covers ::getMimetype
   * @covers ::getPath
   * @covers ::getSize
   * @covers ::getTimestamp
   * @covers ::getType
   * @covers ::getVisibility
   * @covers ::getCacheItemBackend
   * @covers ::setMetadata
   * @covers ::setMimetype
   * @covers ::setPath
   * @covers ::setSize
   * @covers ::setTimestamp
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
   * @covers ::getScheme
   */
  public function testGetScheme() {
    $item = new CacheItem('testing', 'path', new CacheItemBackend(new MemoryBackend('test')));
    $this->assertEquals('testing', $item->getScheme());
  }

  /**
   * @covers ::save
   */
  public function testSave() {
    $backend = $this->getMockBuilder('\Drupal\flysystem\Flysystem\Adapter\CacheItemBackendInterface')
      ->disableOriginalConstructor()
      ->getMock();

    $item = new CacheItem('testing', 'path', $backend);
    $backend->expects($this->once())
      ->method('set')
      ->with($item);

    $item->save();
  }

  /**
   * @covers ::delete
   */
  public function testDelete() {
    $backend = $this->getMockBuilder('\Drupal\flysystem\Flysystem\Adapter\CacheItemBackendInterface')
      ->disableOriginalConstructor()
      ->getMock();

    $item = new CacheItem('testing', 'path', $backend);
    $backend->expects($this->once())
      ->method('delete')
      ->with($item);

    $item->delete();
  }

  /**
   * @covers::__sleep
   */
  public function testSleep() {
    $backend = $this->getMockBuilder('\Drupal\flysystem\Flysystem\Adapter\CacheItemBackendInterface')
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
      ['mimetype', ['mimetype']],
      ['path', 'path'],
      ['size', ['size']],
      ['timestamp', ['timestamp']],
      ['type', 'type'],
      ['visibility', ['visibility']],
      ['cacheItemBackend', $this->getMock('\Drupal\flysystem\Flysystem\Adapter\CacheItemBackendInterface')],
    ];
  }

}
