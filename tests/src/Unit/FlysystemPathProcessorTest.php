<?php

/**
 * @file
 * Contains \Drupal\Tests\flysystem\Unit\FlysystemPathProcessorTest.
 */

namespace Drupal\Tests\flysystem\Unit;

use Drupal\flysystem\PathProcessor\FlysystemPathProcessor;
use Symfony\Component\HttpFoundation\Request;

/**
 * @coversDefaultClass \Drupal\flysystem\PathProcessor\FlysystemPathProcessor
 * @group flysystem
 */
class FlysystemPathProcessorTest extends \PHPUnit_Framework_TestCase {

  public function test() {
    $request = new Request();
    $processor = new FlysystemPathProcessor();
    $this->assertSame('beep', $processor->processInbound('beep', $request));
    $this->assertSame('_flysystem/scheme', $processor->processInbound('_flysystem/scheme', $request));

    // Test image style.
    $this->assertSame('system/files/styles/scheme/small/image.jpg', $processor->processInbound('_flysystem/scheme/styles/scheme/small/image.jpg', $request));
    $this->assertSame('system/files/styles/scheme/small/dir/image.jpg', $processor->processInbound('_flysystem/scheme/styles/scheme/small/dir/image.jpg', $request));

    // Test system download.
    $request = new Request();
    $this->assertSame('system/files/scheme', $processor->processInbound('_flysystem/scheme/file.txt', $request));
    $this->assertSame('file.txt', $request->query->get('file'));
    $this->assertSame('scheme', $request->attributes->get('scheme'));

    // Test system download from sub-dir.
    $request = new Request();
    $this->assertSame('system/files/scheme', $processor->processInbound('_flysystem/scheme/dir/file.txt', $request));
    $this->assertSame('dir/file.txt', $request->query->get('file'));
    $this->assertSame('scheme', $request->attributes->get('scheme'));
  }

}
