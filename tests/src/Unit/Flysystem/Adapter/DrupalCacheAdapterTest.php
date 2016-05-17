<?php

namespace NoDrupal\Tests\flysystem\Unit\Flysystem\Adapter;

use Drupal\flysystem\Flysystem\Adapter\DrupalCacheAdapter;
use Drupal\Tests\UnitTestCase;
use League\Flysystem\Config;
use League\Flysystem\Memory\MemoryAdapter;
use League\Flysystem\Util;

/**
 * Test the Drupal Cache Adapter.
 *
 * @class DrupalCacheAdapterTest
 *
 * @group flysystem
 *
 * @coversDefaultClass \Drupal\flysystem\Flysystem\Adapter\DrupalCacheAdapter
 *
 * @covers \Drupal\flysystem\Flysystem\Adapter\DrupalCacheAdapter::__construct
 * @covers \Drupal\flysystem\Flysystem\Adapter\DrupalCacheAdapter::getScheme
 */
class DrupalCacheAdapterTest extends UnitTestCase {

  /**
   * The cache adapter under test.
   *
   * @var \Drupal\flysystem\Flysystem\Adapter\DrupalCacheAdapter
   */
  protected $adapter;

  /**
   * The flysystem backend for testing.
   *
   * @var \PHPUnit_Framework_MockObject_MockObject|\Drupal\flysystem\Flysystem\Adapter\CacheItemBackend
   */
  protected $cacheItemBackend;

  /**
   * URI scheme to use for testing.
   */
  const SCHEME = 'test-scheme';

  /**
   * {@inheritdoc}
   */
  public function setup() {
    $this->cacheItemBackend = $this->getCacheItemBackendStub();
    $this->adapter = new DrupalCacheAdapter(static::SCHEME, new MemoryAdapter(), $this->cacheItemBackend);
  }

  /**
   * Test writes to the child adapter.
   *
   * @covers ::write
   */
  public function testWrite() {
    $this->expectMetadataSaves('test.txt', 1);
    $metadata = $this->adapter->write('test.txt', 'test', new Config());
    $this->assertInternalType('array', $metadata);
  }

  /**
   * Test failing writes to the child adapter.
   *
   * @covers ::write
   */
  public function testWriteFails() {
    $this->expectMetadataSaves('block-directory', 1);

    $this->adapter->write('block-directory', '', new Config());
    $metadata = $this->adapter->write('block-directory/test', '', new Config());
    $this->assertFalse($metadata);
  }

  /**
   * Test stream writes.
   *
   * @covers ::writeStream
   */
  public function testWriteStream() {
    $this->expectMetadataSaves('test.txt', 1);
    $file = fopen('php://memory', 'rw+');
    $metadata = $this->adapter->writeStream('test.txt', $file, new Config());
    $this->assertInternalType('array', $metadata);
    fclose($file);
  }

  /**
   * Test failing stream writes.
   *
   * @covers ::writeStream
   */
  public function testWriteStreamFails() {
    $this->expectMetadataSaves('block-directory', 1);
    $file = fopen('php://memory', 'rw+');
    $this->adapter->write('block-directory', '', new Config());
    $metadata = $this->adapter->writeStream('block-directory/test', $file, new Config());
    $this->assertFalse($metadata);
    fclose($file);
  }

  /**
   * Test updating existing files.
   *
   * @covers ::update
   */
  public function testUpdate() {
    $this->expectMetadataSaves('test.txt', 2);
    $this->adapter->write('test.txt', 'testing', new Config());
    $this->adapter->update('test.txt', 'test', new Config());

    $metadata = $this->adapter->update('does-not-exist.txt', 'test', new Config());
    $this->assertFalse($metadata);
  }

  /**
   * Test updating existing files with a stream.
   *
   * @covers ::updateStream
   */
  public function testUpdateStream() {
    $this->expectMetadataSaves('test.txt', 2);
    $this->adapter->write('test.txt', 'testing', new Config());
    $file = fopen('php://memory', 'rw+');
    $this->adapter->updateStream('test.txt', $file, new Config());

    $metadata = $this->adapter->updateStream('does-not-exist', $file, new Config());
    $this->assertFalse($metadata);

    fclose($file);
  }

  /**
   * Test renaming files.
   *
   * @covers ::rename
   */
  public function testRename() {
    $cache_item = $this->getCacheItemStub();
    $cache_item->expects($this->once())
      ->method('getMetadata');
    $cache_item->expects($this->exactly(3))
      ->method('setMetadata')
      ->withConsecutive(
        [$this->anything()],
        [new \PHPUnit_Framework_Constraint_ArraySubset(['path' => 'rename.txt'])],
        [$this->anything()]
      );
    $cache_item->expects($this->exactly(3))
      ->method('save');
    $cache_item->expects($this->once())
      ->method('setPath')
      ->with('rename.txt');

    $this->cacheItemBackend->expects($this->exactly(3))
      ->method('load')
      ->withConsecutive(
        [static::SCHEME, 'test.txt'],
        [static::SCHEME, 'rename.txt'],
        [static::SCHEME, 'block-directory']
      )
      ->willReturn($cache_item);

    $metadata = $this->adapter->write('test.txt', 'test', new Config());

    // Test a normal rename.
    $result = $this->adapter->rename('test.txt', 'rename.txt');
    $this->assertTrue($result);
    $metadata['path'] = 'rename.txt';

    // Test a failing rename.
    $this->adapter->write('block-directory', '', new Config());
    $result = $this->adapter->rename('rename.txt', 'block-directory/rename.txt');
    $this->assertFalse($result);
  }

  /**
   * Test copying files.
   *
   * @covers ::copy
   */
  public function testCopy() {
    $cache_item = $this->getCacheItemStub();
    $cache_item->expects($this->exactly(2))
      ->method('setMetadata');
    $cache_item->expects($this->exactly(2))
      ->method('save');

    $this->cacheItemBackend->expects($this->exactly(2))
      ->method('load')
      ->withConsecutive(
        [static::SCHEME, 'test.txt'],
        [static::SCHEME, 'copy.txt']
      )
      ->willReturn($cache_item);
    $this->cacheItemBackend->expects($this->once())
      ->method('deleteByKey')
      ->with(static::SCHEME, 'copy.txt');

    $this->adapter->write('test.txt', 'test', new Config());
    $result = $this->adapter->copy('test.txt', 'copy.txt');
    $this->assertTrue($result);
  }

  /**
   * Test when a copy fails.
   *
   * @covers ::copy
   */
  public function testCopyFails() {
    $this->expectMetadataSaves('block-directory', 1);
    $this->cacheItemBackend->expects($this->never())
      ->method('deleteByKey');

    $this->adapter->write('block-directory', '', new Config());
    $result = $this->adapter->copy('test.txt', 'block-directory/copy.txt');
    $this->assertFalse($result);
  }

  /**
   * Test deleting files.
   *
   * @covers ::delete
   */
  public function testDelete() {
    $this->expectMetadataSaves('test.txt', 1);
    $this->cacheItemBackend->expects($this->once())
      ->method('deleteByKey');

    $this->adapter->write('test.txt', 'test', new Config());
    $result = $this->adapter->delete('test.txt');
    $this->assertTrue($result);
  }

  /**
   * Test when deleting fails.
   *
   * @covers ::delete
   */
  public function testDeleteFails() {
    $this->cacheItemBackend->expects($this->never())
      ->method('deleteByKey');

    $result = $this->adapter->delete('test.txt');
    $this->assertFalse($result);
  }

  /**
   * Test deleting directories.
   *
   * @covers ::deleteDir
   */
  public function testDeleteDir() {
    $this->cacheItemBackendLoadStubItem();

    $expected = [
      'directory/test.txt',
      'directory/subdirectory',
      'directory/subdirectory/test.txt',
    ];
    $this->cacheItemBackend->expects($this->once())
      ->method('deleteMultiple')
      ->with(static::SCHEME, $expected);

    // Ensure that all directory contents are removed from the cache, but that
    // other files are preserved.
    $this->adapter->write('directory/test.txt', 'test', new Config());
    $this->adapter->write('directory/subdirectory/test.txt', 'test', new Config());
    $this->adapter->write('test.txt', 'test', new Config());

    $result = $this->adapter->deleteDir('directory');
    $this->assertTrue($result);

    $result = $this->adapter->deleteDir('directory');
    $this->assertFalse($result);
  }

  /**
   * Test creating directories.
   *
   * @covers ::createDir
   */
  public function testCreateDir() {
    $this->cacheItemBackendLoadStubItem();
    $metadata = $this->adapter->createDir('directory', new Config());
    $this->assertInternalType('array', $metadata);
    $this->adapter->write('file', '', new Config());
    $metadata = $this->adapter->createDir('file', new Config());
    $this->assertFalse($metadata);
  }

  /**
   * Test getting setting visibility with no cache hits.
   *
   * @covers ::setVisibility
   * @covers ::getVisibility
   */
  public function testVisibilityMiss() {
    $this->cacheItemBackendLoadStubItem();
    $this->adapter->write('file', '', new Config());
    $metadata = $this->adapter->setVisibility('file', ['hidden']);
    $this->assertEquals($metadata, $this->adapter->getVisibility('file'));

    $this->assertFalse($this->adapter->setVisibility('does-not-exist', ['hidden']));
  }

  /**
   * Test loading visibility from the cache.
   *
   * @covers ::getVisibility
   */
  public function testVisibility() {
    $cache_item = $this->getCacheItemStub();
    $cache_item->method('getVisibility')
      ->willReturn(['visibility' => TRUE]);

    $this->cacheItemBackendLoad($cache_item);
    $this->assertEquals(['visibility' => TRUE], $this->adapter->getVisibility('file'));
  }

  /**
   * Test if files exist.
   *
   * @covers ::has
   */
  public function testHas() {
    $this->assertFalse($this->adapter->has('does-not-exist'));

    $this->cacheItemBackendLoadStubItem();

    // Test that when we have a cache item that we don't call the child
    // adapter.
    $mock_adapter = $this->getFlysystemAdapterStub();
    $mock_adapter->expects($this->never())
      ->method('has');

    /** @var \League\Flysystem\AdapterInterface $mock_adapter */
    $adapter = new DrupalCacheAdapter('memory', $mock_adapter, $this->cacheItemBackend);
    $this->assertTrue($adapter->has('file'));
  }

  /**
   * Test reading files.
   *
   * @covers ::read
   */
  public function testRead() {
    $this->cacheItemBackendLoadStubItem();
    $this->adapter->write('test.txt', 'test', new Config());
    $this->assertEquals('test', $this->adapter->read('test.txt')['contents']);
  }

  /**
   * Test reading a stream.
   *
   * @covers ::readStream
   */
  public function testReadStream() {
    $this->cacheItemBackendLoadStubItem();
    $this->adapter->write('test.txt', 'test', new Config());
    $stream = $this->adapter->readStream('test.txt')['stream'];
    $this->assertInternalType('resource', $stream);
  }

  /**
   * Test listing a directory.
   *
   * @covers ::listContents
   */
  public function testListContents() {
    $this->cacheItemBackendLoadStubItem();
    $this->adapter->write('test.txt', 'test', new Config());
    $this->adapter->createDir('directory/subdirectory', new Config());
    $contents = $this->adapter->listContents('', TRUE);
    $expected = [
      'test.txt',
      'directory',
      'directory/subdirectory',
    ];

    $this->assertEquals($expected, array_column($contents, 'path'));
  }

  /**
   * Test methods that just wrap getMetadata().
   *
   * @param string $method
   *   The method to test.
   *
   * @dataProvider methodReturnsMetadataArrayProvider
   *
   * @covers ::getMetadata
   * @covers ::getSize
   * @covers ::getTimestamp
   * @covers ::getVisibility
   * @covers ::fetchMetadataKey
   */
  public function testGetMetadataMethods($method) {
    $get_method = 'get' . ucfirst($method);
    $cache_item = $this->getCacheItemStub();
    $cache_item->method('getMetadata')
      ->willReturn([$method => 12345]);
    $this->cacheItemBackendLoad($cache_item);

    // Test that when we have a cache item that we don't call the child
    // adapter.
    $mock_adapter = $this->getFlysystemAdapterStub();
    $mock_adapter->method('write')
      ->willReturn([$method => 12345]);
    $mock_adapter->expects($this->never())
      ->method($get_method);

    // Populate the adapter with a file, but then bust the cache.
    $adapter = new DrupalCacheAdapter('memory', $mock_adapter, $this->cacheItemBackend);
    $metadata = $adapter->write('test.txt', 'test', new Config());

    // Tests fetching from the cache.
    $this->assertEquals($metadata, $adapter->$get_method('test.txt'), "Test cached $get_method on the child adapter");
  }

  /**
   * Test methods that just wrap getMetadata().
   *
   * @param string $method
   *   The method to test.
   *
   * @dataProvider methodReturnsMetadataArrayProvider
   *
   * @covers ::getMetadata
   * @covers ::getSize
   * @covers ::getTimestamp
   * @covers ::getVisibility
   * @covers ::fetchMetadataKey
   */
  public function testGetMetadataMethodsCacheMiss($method) {
    $get_method = 'get' . ucfirst($method);

    $cache_item = $this->getCacheItemStub();
    $cache_item->expects($this->once())
      ->method('setMetadata');
    $cache_item->expects($this->once())
      ->method('save');
    $cache_item->method('getMetadata')
      ->willReturn(FALSE);

    $this->cacheItemBackendLoad($cache_item);

    // Test that when we have a cache item that we don't call the child
    // adapter.
    $mock_adapter = $this->getFlysystemAdapterStub();
    $mock_adapter->method($get_method)
      ->willReturn([$method => 12345]);
    $mock_adapter->expects($this->once())
      ->method($get_method);

    $adapter = new DrupalCacheAdapter('memory', $mock_adapter, $this->cacheItemBackend);

    // Tests fetching from the child adapter.
    $this->assertEquals([$method => 12345], $adapter->$get_method('test.txt'), "Test calling $get_method on the child adapter");
  }

  /**
   * Test mimetypes.
   *
   * @covers ::getMimetype
   */
  public function testGetMimetype() {
    $mimetype = [
      'mimetype' => Util::guessMimeType('test.txt', 'test'),
      'path' => 'test.txt',
    ];
    $cache_item = $this->getCacheItemStub();
    $cache_item->method('getMetadata')
      ->willReturn($mimetype);
    $this->cacheItemBackendLoad($cache_item);
    // Test that when we have a cache item that we don't call the child
    // adapter.
    $mock_adapter = $this->getFlysystemAdapterStub();
    $mock_adapter->expects($this->never())
      ->method('getMimetype');

    // Tests fetching from the cache.
    $adapter = new DrupalCacheAdapter('memory', $mock_adapter, $this->cacheItemBackend);
    $this->assertEquals($mimetype, $adapter->getMimetype('test.txt'));
  }

  /**
   * Test when mimetypes aren't in the cache.
   *
   * @covers ::getMimeType
   */
  public function testGetMimetypeMiss() {
    $mimetype = [
      'mimetype' => Util::guessMimeType('test.txt', 'test'),
      'path' => 'test.txt',
    ];
    $cache_item = $this->getCacheItemStub();
    $cache_item->method('getMimetype')
      ->willReturn(FALSE);
    $this->cacheItemBackendLoad($cache_item);
    // Test that when we have a cache item that we don't call the child
    // adapter.
    $mock_adapter = $this->getFlysystemAdapterStub();
    $mock_adapter->expects($this->once())
      ->method('getMimetype')
      ->willReturn($mimetype);

    // Tests fetching from the cache.
    $adapter = new DrupalCacheAdapter('memory', $mock_adapter, $this->cacheItemBackend);
    $this->assertEquals($mimetype, $adapter->getMimetype('test.txt'));
  }

  /**
   * Return methods that use the same values as getMetadata().
   *
   * @return array
   *   An array of test cases, each containing an array of parameters.
   */
  public function methodReturnsMetadataArrayProvider() {
    return [
      ['metadata'],
      ['size'],
      ['timestamp'],
    ];
  }

  /**
   * Return a stub CacheItemBackend.
   */
  protected function getCacheItemBackendStub() {
    $stub = $this->getMockBuilder('\Drupal\flysystem\Flysystem\Adapter\CacheItemBackend')
      ->disableOriginalConstructor()
      ->getMock();
    return $stub;
  }

  /**
   * Set an expectation that metadata for a file is saved a set number of times.
   *
   * @param string $path
   *   The path to expect saves on.
   * @param int $count
   *   The number of saves to expect.
   */
  private function expectMetadataSaves($path, $count) {
    $cache_item = $this->getCacheItemStub();
    $cache_item->expects($this->exactly($count))
      ->method('setMetadata');
    $cache_item->expects($this->exactly($count))
      ->method('save');

    $this->cacheItemBackend->expects($this->exactly($count))
      ->method('load')
      ->with(static::SCHEME, $path)
      ->willReturn($cache_item);
  }

  /**
   * Return a stub Flysystem adapter.
   *
   * @return \PHPUnit_Framework_MockObject_MockObject|\League\Flysystem\AdapterInterface
   *   Return a new adapter stub.
   */
  protected function getFlysystemAdapterStub() {
    return $this->getMockBuilder('\League\Flysystem\AdapterInterface')
      ->disableOriginalConstructor()
      ->getMock();
  }

  /**
   * Return a stub cache item.
   *
   * @return \PHPUnit_Framework_MockObject_MockObject|\Drupal\flysystem\Flysystem\Adapter\CacheItem
   *   Return a new cache item stub.
   */
  protected function getCacheItemStub() {
    return $this->getMockBuilder('\Drupal\flysystem\Flysystem\Adapter\CacheItem')
      ->disableOriginalConstructor()
      ->getMock();
  }

  /**
   * Load a stub item into the cache.
   */
  protected function cacheItemBackendLoadStubItem() {
    $cache_item = $this->getCacheItemStub();
    $this->cacheItemBackendLoad($cache_item);
  }

  /**
   * Load a specific cache item into the cache.
   *
   * @param mixed $cache_item
   *   The cache item to load.
   */
  protected function cacheItemBackendLoad($cache_item) {
    $this->cacheItemBackend->method('load')
      ->willReturn($cache_item);
  }

}
