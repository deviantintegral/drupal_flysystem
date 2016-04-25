<?php

/**
 * @file
 * Contains \Drupal\image\Controller\ImageStyleDownloadController.
 */

namespace Drupal\flysystem\Controller;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\image\ImageStyleInterface;
use Drupal\system\FileDownloadController;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * Defines a controller to serve image styles.
 */
class ImageStyleDownloadController extends FileDownloadController {

  /**
   * The lock backend.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected $lock;

  /**
   * The image factory.
   *
   * @var \Drupal\Core\Image\ImageFactory
   */
  protected $imageFactory;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The logger factory.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $loggerFactory;

  /**
   * Constructs a ImageStyleDownloadController object.
   *
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   The lock backend.
   * @param \Drupal\Core\Image\ImageFactory $image_factory
   *   The image factory.
   */
  public function __construct(LockBackendInterface $lock, ImageFactory $image_factory) {
    $this->lock = $lock;
    $this->imageFactory = $image_factory;
    $this->logger = $this->getLogger('image');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('lock'),
      $container->get('image.factory')
    );
  }

  /**
   * Generates a derivative, given a style and image path.
   *
   * After generating an image, transfer it to the requesting agent.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param string $scheme
   *   The file scheme, defaults to 'private'.
   * @param \Drupal\image\ImageStyleInterface $image_style
   *   The image style to deliver.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Symfony\Component\HttpFoundation\Response
   *   The transferred file as response or some error response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Thrown when the user does not have access to the file.
   * @throws \Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException
   *   Thrown when the file is still being generated.
   */
  public function deliver(Request $request, $scheme, ImageStyleInterface $image_style) {
    $target = $request->query->get('file');
    $image_uri = $scheme . '://' . $target;

    $this->validateRequest($request, $image_style, $scheme, $target);

    $derivative_uri = $image_style->buildUri($image_uri);
    $headers = array();

    // If using the private scheme, let other modules provide headers and
    // control access to the file.
    if ($scheme == 'private') {
      if (file_exists($derivative_uri)) {
        return parent::download($request, $scheme);
      }
      else {
        $headers = $this->moduleHandler()->invokeAll('file_download', array($image_uri));
        if (in_array(-1, $headers) || empty($headers)) {
          throw new AccessDeniedHttpException();
        }
      }
    }

    // Don't try to generate file if source is missing.
    try {
      $image_uri = $this->validateSource($image_uri);
    }
    catch (FileNotFoundException $e) {
      $this->logger->notice('Source image at %source_image_path not found while trying to generate derivative image at %derivative_path.', array('%source_image_path' => $image_uri, '%derivative_path' => $derivative_uri));
      return new Response($this->t('Error generating image, missing source file.'), 404);
    }

    $success = $this->generate($image_style, $image_uri, $derivative_uri);

    if ($success) {
      return $this->send($scheme, $derivative_uri, $headers);
    }
    else {
      $this->logger->notice('Unable to generate the derived image located at %path.', array('%path' => $derivative_uri));
      return new Response($this->t('Error generating image.'), 500);
    }
  }

  /**
   * Validate that a source image exists, checking for double extensions.
   *
   * If the image style converted the extension, it has been added to the
   * original file, resulting in filenames like image.png.jpeg. So to find
   * the actual source image, we remove the extension and check if that
   * image exists.
   *
   * @param string $image_uri
   *   The URI to the source image.
   *
   * @return string
   *   The original $image_uri, or the source with the original extension.
   *
   * @throws \Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException
   *   Thrown when no valid source image is found.
   */
  protected function validateSource($image_uri) {
    if (!file_exists($image_uri)) {
      $path_info = pathinfo($image_uri);
      $converted_image_uri = $path_info['dirname'] . DIRECTORY_SEPARATOR . $path_info['filename'];
      if (!file_exists($converted_image_uri)) {
        throw new FileNotFoundException($converted_image_uri);
      }
      // The converted file does exist, use it as the source.
      return $converted_image_uri;
    }

    return $image_uri;
  }

  /**
   * Return a response of the derived image.
   *
   * @param string $scheme
   *   The URI scheme of $derivative_uri.
   * @param string $derivative_uri
   *   The URI of the derived image.
   * @param array $headers
   *   (optional) An array of headers to return in the response.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
   *   A response with the derived image.
   */
  protected function send($scheme, $derivative_uri, $headers = array()) {
    $image = $this->imageFactory->get($derivative_uri);
    $uri = $image->getSource();
    $headers += array(
      'Content-Type' => $image->getMimeType(),
      'Content-Length' => $image->getFileSize(),
    );
    // \Drupal\Core\EventSubscriber\FinishResponseSubscriber::onRespond()
    // sets response as not cacheable if the Cache-Control header is not
    // already modified. We pass in FALSE for non-private schemes for the
    // $public parameter to make sure we don't change the headers.
    return new BinaryFileResponse($uri, 200, $headers, $scheme !== 'private');
  }

  /**
   * Generate an image derivative.
   *
   * @param \Drupal\image\ImageStyleInterface $image_style
   *   The image style to use for the derivative.
   * @param string $image_uri
   *   The URI of the original image.
   * @param string $derivative_uri
   *   The URI of the derived image.
   *
   * @return bool
   *   TRUE if the image exists or was generated, FALSE otherwise.
   */
  protected function generate(ImageStyleInterface $image_style, $image_uri, $derivative_uri) {
    // Don't start generating the image if the derivative already exists or if
    // generation is in progress in another thread.
    $lock_name = 'image_style_deliver:' . $image_style->id() . ':' . Crypt::hashBase64($image_uri);
    if (!file_exists($derivative_uri)) {
      $lock_acquired = $this->lock->acquire($lock_name);
      if (!$lock_acquired) {
        // Tell client to retry again in 3 seconds. Currently no browsers are
        // known to support Retry-After.
        throw new ServiceUnavailableHttpException(3, $this->t('Image generation in progress. Try again shortly.'));
      }
    }

    // Try to generate the image, unless another thread just did it while we
    // were acquiring the lock.
    $success = file_exists($derivative_uri) || $image_style->createDerivative($image_uri, $derivative_uri);

    if (!empty($lock_acquired)) {
      $this->lock->release($lock_name);
    }

    return $success;
  }

  /**
   * Validate an incoming derivative request.
   *
   * Check that the style is defined, the scheme is valid, and the image
   * derivative token is valid. Sites which require image derivatives to be
   * generated without a token can set the
   * 'image.settings:allow_insecure_derivatives' configuration to TRUE to
   * bypass the latter check, but this will increase the site's vulnerability
   * to denial-of-service attacks. To prevent this variable from leaving the
   * site vulnerable to the most serious attacks, a token is always required
   * when a derivative of a style is requested.
   * The $target variable for a derivative of a style has
   * styles/<style_name>/... as structure, so we check if the $target variable
   * starts with styles/.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming derivative request.
   * @param \Drupal\image\ImageStyleInterface $image_style
   *   The image style to use for the derivative.
   * @param string $scheme
   *   The URI scheme of $target.
   * @param string $target
   *   The path for the generated derivative.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Thrown when the image style, the scheme, or the path token is invalid.
   */
  protected function validateRequest(Request $request, ImageStyleInterface $image_style, $scheme, $target) {
    $valid = !empty($image_style) && file_stream_wrapper_valid_scheme($scheme);
    $image_uri = $scheme . '://' . $target;
    if (!$this->config('image.settings')
        ->get('allow_insecure_derivatives') || strpos(ltrim($target, '\/'), 'styles/') === 0
    ) {
      $valid &= $request->query->get(IMAGE_DERIVATIVE_TOKEN) === $image_style->getPathToken($image_uri);
    }
    if (!$valid) {
      throw new AccessDeniedHttpException();
    }
  }

  /**
   * Returns a channel logger object.
   *
   * @param string $channel
   *   The name of the channel. Can be any string, but the general practice is
   *   to use the name of the subsystem calling this.
   *
   * @return \Psr\Log\LoggerInterface
   *   The logger for this channel.
   */
  protected function getLogger($channel) {
    if (!$this->loggerFactory) {
      $this->loggerFactory = \Drupal::getContainer()->get('logger.factory');
    }
    return $this->loggerFactory->get($channel);
  }

}

