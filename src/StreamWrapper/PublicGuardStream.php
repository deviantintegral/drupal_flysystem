<?php

namespace Drupal\flysystem\StreamWrapper;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Site\Settings;
use Drupal\Core\StreamWrapper\PublicStream;
use Psr\Log\LoggerInterface;

/**
 * Stream wrapper that logs or fails requests using the public file system.
 *
 * @class PublicGuardStream
 */
class PublicGuardStream extends PublicStream {

  /**
   * Log public:// usage to the system log.
   */
  const LOG_USAGE = 'log';

  /**
   * Throw exceptions when public:// is used.
   */
  const THROW_EXCEPTION = 'exception';

  /**
   * The system logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The method ('log', or 'exception') to use when public:// is used.
   *
   * @var string
   */
  protected $method;

  /**
   * PublicGuardStream constructor.
   *
   * As PHP instantiates the stream wrapper outside of PHP code, we have to fall
   * back to global services. However, unit tests can still pass in
   * dependencies.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   (optional) The logger to use for system messages.
   * @param string $guard_method
   *   (optional) The method to use when public files are used, either 'log' or
   *   'exception'.
   */
  public function __construct(LoggerInterface $logger = NULL, $guard_method = NULL) {
    if (!$logger) {
      $this->logger = \Drupal::logger('flysystem');
    }

    if (!$guard_method) {
      $this->method = Settings::get('flysystem_public_guard_method');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return t('Public files guard');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return t('Flysystem: Public files guard to log or redirect public file accesses.');
  }

  /**
   * {@inheritdoc}
   */
  public function setUri($uri) {
    $this->handle($uri, __FUNCTION__);
    parent::setUri($uri);
  }

  /**
   * {@inheritdoc}
   */
  protected function getTarget($uri = NULL) {
    $this->handle($uri, __FUNCTION__);
    return parent::getTarget($uri);
  }

  /**
   * {@inheritdoc}
   */
  protected function getLocalPath($uri = NULL) {
    $this->handle($uri, __FUNCTION__);
    return parent::getLocalPath($uri);
  }

  /**
   * {@inheritdoc}
   */
  public function stream_open($uri, $mode, $options, &$opened_path) {
    $this->handle($uri, __FUNCTION__);
    return parent::stream_open($uri, $mode, $options, $opened_path);
  }

  /**
   * {@inheritdoc}
   */
  public function stream_metadata($uri, $option, $value) {
    $this->handle($uri, __FUNCTION__);
    return parent::stream_metadata($uri, $option, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function unlink($uri) {
    $this->handle($uri, __FUNCTION__);
    return parent::unlink($uri);
  }

  /**
   * {@inheritdoc}
   */
  public function rename($from_uri, $to_uri) {
    $this->handle($from_uri, __FUNCTION__);
    return parent::rename($from_uri, $to_uri);
  }

  /**
   * {@inheritdoc}
   */
  public function dirname($uri = NULL) {
    $this->handle($uri, __FUNCTION__);
    return parent::dirname($uri);
  }

  /**
   * {@inheritdoc}
   */
  public function mkdir($uri, $mode, $options) {
    $this->handle($uri, __FUNCTION__);
    return parent::mkdir($uri, $mode, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function rmdir($uri, $options) {
    $this->handle($uri, __FUNCTION__);
    return parent::rmdir($uri, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function url_stat($uri, $flags) {
    $this->handle($uri, __FUNCTION__);
    return parent::url_stat($uri, $flags);
  }

  /**
   * {@inheritdoc}
   */
  public function dir_opendir($uri, $options) {
    $this->handle($uri, __FUNCTION__);
    return parent::dir_opendir($uri, $options);
  }

  /**
   * Handle accessing a public URI.
   *
   * @param string $uri
   *   The URI being accessed.
   * @param string $function
   *   The method in the stream wrapper that was called.
   */
  protected function handle($uri, $function) {
    switch ($this->method) {
      case static::THROW_EXCEPTION:
        $this->exception($uri, $function);
        break;

      case static::LOG_USAGE:
      default:
        $this->log($uri, $function);
        break;

    }
  }

  /**
   * Log a public access to the system log.
   *
   * @param string $uri
   *   The URI being accessed.
   * @param string $function
   *   The method in the stream wrapper that was called.
   */
  protected function log($uri, $function) {
    if (empty($uri)) {
      $uri = 'null uri';
    }
    $message = $this->errorMessage($uri, $function);
    $this->logger->info($message['message'], $message['context']);
  }

  /**
   * Throw an exception due to a public access.
   *
   * @param string $uri
   *   The URI being accessed.
   * @param string $function
   *   The method in the stream wrapper that was called.
   */
  protected function exception($uri, $function) {
    $message = $this->errorMessage($uri, $function);
    $markup = new FormattableMarkup($message['message'], $message['context']);
    throw new \RuntimeException($markup->__toString());
  }

  /**
   * Generate an error message.
   *
   * @param string $uri
   *   The URI being accessed.
   * @param string $function
   *   The method in the stream wrapper that was called.
   *
   * @return array
   *   An array suitable for use within FormattableMarkup, with 'message' and
   *   'context' keys.
   */
  protected function errorMessage($uri, $function) {
    $message = [
      'message' => 'Public file stream @function called to access @uri',
      'context' => ['@function' => $function, '@uri' => $uri],
    ];
    return $message;
  }
}
