<?php

namespace NoDrupal\Tests\flysystem\Unit\Flysystem\Adapter;

use Drupal\flysystem\Flysystem\Adapter\CacheItem;
use Drupal\Tests\UnitTestCase;

/**
 * Tests \Drupal\flysystem\Flysystem\Adapter\CacheItem.
 *
 * @class CacheItemTest
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
   * @covers ::setMetadata
   * @covers ::setMimetype
   * @covers ::setPath
   * @covers ::setSize
   * @covers ::setTimestamp
   * @covers ::setType
   * @covers ::setVisibility
   */
  public function testGetSetMethods($method, $value) {
    $item = new CacheItem('path');
    $this->assertEquals('path', $item->getPath());

    $set = 'set' . ucfirst($method);
    $get = 'get' . ucfirst($method);
    $item->$set($value);
    $this->assertEquals($value, $item->$get());
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
    ];
  }

}
