<?php

namespace Drupal\flysystem\PathProcessor;

use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines a path processor to rewrite Flysystem URLs.
 *
 * As the route system does not allow arbitrary amount of parameters convert
 * the file path to a query parameter on the request.
 */
class FlysystemImageStyleRedirectProcessor implements InboundPathProcessorInterface {

  /**
   * The base menu path for style redirects.
   */
  const STYLES_PATH = '/_flysystem-style-redirect/styles';

  /**
   * {@inheritdoc}
   */
  public function processInbound($path, Request $request) {
    // Quick exit.
    if (strpos($path, $this::STYLES_PATH) !== 0) {
      return $path;
    }

    // Stream wrapper protocols must conform to /^[a-zA-Z0-9+.-]+$/
    // Via php_stream_wrapper_scheme_validate() in the PHP source.
    $matches = [];
    if (!preg_match('|^' . static::STYLES_PATH . '/([^/]+)/([a-zA-Z0-9+.-]+)/|', $path, $matches)) {
      return $path;
    }

    $file = substr($path, strlen($matches[0]));
    $image_style = $matches[1];
    $scheme = $matches[2];

    // Set the file as query parameter.
    $request->query->set('file', $file);

    return static::STYLES_PATH . '/' . $image_style . '/' . $scheme;
  }

}
