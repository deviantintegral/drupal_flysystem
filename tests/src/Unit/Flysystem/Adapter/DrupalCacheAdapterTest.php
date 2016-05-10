<?php

namespace NoDrupal\Tests\flysystem\Unit\Flysystem\Adapter;

use Drupal\Core\Cache\MemoryBackend;
use Drupal\flysystem\Flysystem\Adapter\DrupalCacheAdapter;
use Drupal\Tests\UnitTestCase;
use League\Flysystem\Config;
use League\Flysystem\Memory\MemoryAdapter;

class DrupalCacheAdapterTest extends UnitTestCase {
  /**
   * @var \Drupal\flysystem\Flysystem\Adapter\DrupalCacheAdapter
   */
  protected $adapter;

  /**
   * @var \Drupal\Core\Cache\MemoryBackend
   */
  protected $cacheBackend;

  public function setup() {
    $this->cacheBackend = new MemoryBackend('test');
    $this->adapter = new DrupalCacheAdapter(new MemoryAdapter(), $this->cacheBackend);
  }

  public function testWrite() {
    $metadata = $this->adapter->write('test.txt', 'test', new Config());
    $this->assertEquals($metadata, $this->cacheBackend->get('test.txt')->data->getMetadata());

    $this->adapter->write('block-directory', '', new Config());
    $metadata = $this->adapter->write('block-directory/test', '', new Config());
    $this->assertFalse($metadata);
  }

  public function testWriteStream() {
    $file = fopen('php://memory', 'rw+');
    $metadata = $this->adapter->writeStream('test.txt', $file, new Config());
    $this->assertEquals($metadata, $this->cacheBackend->get('test.txt')->data->getMetadata());

    $this->adapter->write('block-directory', '', new Config());
    $metadata = $this->adapter->writeStream('block-directory/test', $file, new Config());
    $this->assertFalse($metadata);

    fclose($file);
  }

  public function testUpdate() {
    $this->adapter->write('test.txt', 'testing', new Config());
    $metadata = $this->adapter->update('test.txt', 'test', new Config());
    $this->assertEquals($metadata, $this->cacheBackend->get('test.txt')->data->getMetadata());

    $metadata = $this->adapter->update('doesn-not-exist.txt', 'test', new Config());
    $this->assertFalse($metadata);
  }

  public function testUpdateStream() {
    $this->adapter->write('test.txt', 'testing', new Config());
    $file = fopen('php://memory', 'rw+');
    $metadata = $this->adapter->updateStream('test.txt', $file, new Config());
    $this->assertEquals($metadata, $this->cacheBackend->get('test.txt')->data->getMetadata());

    $metadata = $this->adapter->updateStream('does-not-exist', $file, new Config());
    $this->assertFalse($metadata);

    fclose($file);
  }

  public function testRename() {
    $metadata = $this->adapter->write('test.txt', 'test', new Config());
    $result = $this->adapter->rename('test.txt', 'rename.txt');
    $this->assertTrue($result);
    $metadata['path'] = 'rename.txt';
    $this->assertEquals($metadata, $this->cacheBackend->get('rename.txt')->data->getMetadata());

    $this->adapter->write('block-directory', '', new Config());
    $result = $this->adapter->rename('rename.txt', 'block-directory/rename.txt');
    $this->assertFalse($result);
  }

  public function testCopy() {
    $metadata = $this->adapter->write('test.txt', 'test', new Config());
    $result = $this->adapter->copy('test.txt', 'copy.txt');
    $this->assertTrue($result);

    // Write in MemoryAdapter returns file contents in it's metadata, but not in
    // the getMetadata() call.
    unset($metadata['contents']);
    $metadata['path'] = 'copy.txt';

    $this->assertEquals($metadata, $this->cacheBackend->get('copy.txt')->data->getMetadata());

    $this->adapter->write('block-directory', '', new Config());
    $result = $this->adapter->copy('test.txt', 'block-directory/copy.txt');
    $this->assertFalse($result);
  }

  public function testDelete() {
    $this->adapter->write('test.txt', 'test', new Config());
    $result = $this->adapter->delete('test.txt');
    $this->assertTrue($result);
    $this->assertFalse($this->cacheBackend->get('test.txt'));

    $result = $this->adapter->delete('test.txt');
    $this->assertFalse($result);
  }

  public function testDeleteDir() {
    $this->adapter->write('directory/test.txt', 'test', new Config());
    $this->adapter->write('directory/subdirectory/test.txt', 'test', new Config());
    $this->adapter->write('test.txt', 'test', new Config());

    $result = $this->adapter->deleteDir('directory');
    $this->assertTrue($result);
    $this->assertFalse($this->cacheBackend->get('directory'));
    $this->assertFalse($this->cacheBackend->get('directory/test.txt'));
    $this->assertFalse($this->cacheBackend->get('directory/subdirectory'));
    $this->assertFalse($this->cacheBackend->get('directory/subdirectory/test.txt'));
    $this->assertNotEmpty($this->cacheBackend->get('test.txt'));

    $result = $this->adapter->deleteDir('directory');
    $this->assertFalse($result);
  }

  public function testCreateDir() {
    $metadata = $this->adapter->createDir('directory', new Config());
    $this->assertEquals($metadata, $this->cacheBackend->get('directory')->data->getMetadata());
    $this->adapter->write('file', '', new Config());
    $metadata = $this->adapter->createDir('file', new Config());
    $this->assertFalse($metadata);
    $this->assertEquals('file', $this->cacheBackend->get('file')->data->getMetadata()['type']);
  }

  public function testSetVisibility() {
    $this->adapter->write('file', '', new Config());
    $metadata = $this->adapter->setVisibility('file', ['hidden']);
    $this->assertEquals($metadata['visibility'], $this->cacheBackend->get('file')->data->getVisibility());
    $this->assertEquals($metadata['visibility'], $this->adapter->getVisibility('file'));

    $this->assertFalse($this->adapter->setVisibility('does-not-exist', ['hidden']));
    $this->assertFalse($this->cacheBackend->get('does-not-exist'));
  }

  public function testHas() {
    $this->assertFalse($this->adapter->has('does-not-exist'));

    // Test that when we have a cache item that we don't call the child
    // adapter.
    $mock_adapter = $this->getMockBuilder('\League\Flysystem\AdapterInterface')
      ->disableOriginalConstructor()
      ->getMock();
    $mock_adapter->expects($this->never())
      ->method('has');

    // Populate the cache with a file.
    $adapter = new DrupalCacheAdapter(new MemoryAdapter(), $this->cacheBackend);
    $adapter->write('file', '', new Config());

    /** @var \League\Flysystem\AdapterInterface $mock_adapter */
    $adapter = new DrupalCacheAdapter($mock_adapter, $this->cacheBackend);
    $this->assertTrue($adapter->has('file'));
  }

  public function testRead() {
    $this->adapter->write('test.txt', 'test', new Config());
    $this->assertEquals('test', $this->adapter->read('test.txt')['contents']);
  }


  public function testReadStream() {
    $this->adapter->write('test.txt', 'test', new Config());
    $stream = $this->adapter->readStream('test.txt')['stream'];
    $this->assertInternalType('resource', $stream);
  }

  public function testListContents() {
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
}