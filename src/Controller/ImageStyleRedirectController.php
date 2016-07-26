<?php

namespace Drupal\flysystem\Controller;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\flysystem\ImageStyleCopier;
use Drupal\image\ImageStyleInterface;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * Defines an image style controller that serves from temporary, then redirects.
 */
class ImageStyleRedirectController extends ImageStyleDownloadController {

  /**
   * The file entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $fileStorage;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The image style copier.
   *
   * @var \Drupal\flysystem\ImageStyleCopier
   */
  protected $imageStyleCopier;

  /**
   * Constructs an ImageStyleRedirectController.
   *
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   The lock backend.
   * @param \Drupal\Core\Image\ImageFactory $image_factory
   *   The image factory.
   * @param \Drupal\Core\Entity\EntityStorageInterface $file_storage
   *   The file entity storage.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system.
   * @param \Drupal\flysystem\ImageStyleCopier $image_style_copier
   *   The image style copier.
   */
  public function __construct(LockBackendInterface $lock, ImageFactory $image_factory, EntityStorageInterface $file_storage, FileSystemInterface $file_system, ImageStyleCopier $image_style_copier) {
    parent::__construct($lock, $image_factory);
    $this->fileStorage = $file_storage;
    $this->fileSystem = $file_system;
    $this->imageStyleCopier = $image_style_copier;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('lock'),
      $container->get('image.factory'),
      $container->get('entity.manager')->getStorage('file'),
      $container->get('file_system'),
      $container->get('flysystem_image_style_copier')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function deliver(Request $request, $scheme, ImageStyleInterface $image_style) {
    $target = $request->query->get('file');
    $source_uri = $scheme . '://' . $target;

    $this->validateRequest($request, $image_style, $scheme, $target);

    // Don't try to generate file if source is missing.
    try {
      $source_uri = $this->validateSource($source_uri);
    }
    catch (FileNotFoundException $e) {
      $derivative_uri = $image_style->buildUri($source_uri);
      $this->logger->notice('Source image at %source_image_path not found while trying to generate derivative image at %derivative_path.', ['%source_image_path' => $source_uri, '%derivative_path' => $derivative_uri]);
      return new Response($this->t('Error generating image, missing source file.'), 404);
    }

    // If the image already exists on the adapter, deliver it instead.
    try {
      return $this->redirectAdapterImage($source_uri, $image_style);
    }
    catch (FileNotFoundException $e) {
      return $this->deliverTemporary($scheme, $target, $image_style);
    }
  }

  /**
   * Generate a temporary image for an image style.
   *
   * @param string $scheme
   *   The file scheme, defaults to 'private'.
   * @param string $source_path
   *   The image file to generate the temporary image for.
   * @param \Drupal\image\ImageStyleInterface $image_style
   *   The image style to generate.
   *
   * @throws \RuntimeException
   *   Thrown when generate() failed to generate an image.
   *
   * @return \Drupal\file\Entity\File
   *   The temporary image that was generated.
   */
  protected function generateTemporaryImage($scheme, $source_path, ImageStyleInterface $image_style) {
    $image_uri = "$scheme://$source_path";
    $destination_temp = $image_style->buildUri("temporary://flysystem/$scheme/$source_path");

    // Try to generate the temporary image, watching for other threads that may
    // also be trying to generate the temporary image.
    try {
      $success = $this->generate($image_style, $image_uri, $destination_temp);
      if (!$success) {
        throw new \RuntimeException('The temporary image could not be generated');
      }
    }
    catch (ServiceUnavailableHttpException $e) {
      // This exception is only thrown if the lock could not be acquired.
      $tries = 0;

      do {
        if (file_exists($destination_temp)) {
          break;
        }

        // The file still doesn't exist.
        usleep(250000);
        $tries++;
      } while ($tries < 4);

      // We waited for more than 1 second for the temporary image to appear.
      // Since local image generation should be fast, fail out here to try to
      // limit PHP process demands.
      if ($tries >= 4) {
        throw $e;
      }
    }

    return $destination_temp;
  }

  /**
   * Flushes the output buffer and copies the temporary images to the adapter.
   */
  protected function flushCopy() {
    // We have to call both of these to actually flush the image.
    Response::closeOutputBuffers(0, TRUE);
    flush();
    $this->imageStyleCopier->processCopyTasks();
  }

  /**
   * Redirects to to an adapter hosted image, if it exists.
   *
   * @param string $source_uri
   *   The URI to the source image.
   * @param \Drupal\image\ImageStyleInterface $image_style
   *   The image style to redirect to.
   *
   * @throws \Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException
   *   Thrown if the derivative does not exist on the adapter.
   *
   * @return \Drupal\Core\Routing\TrustedRedirectResponse
   *   A redirect to the image if it exists.
   */
  protected function redirectAdapterImage($source_uri, ImageStyleInterface $image_style) {
    $derivative_uri = $image_style->buildUri($source_uri);

    if (file_exists($derivative_uri)) {
      // We can't just return TrustedRedirectResponse because core throws an
      // exception about missing cache metadata.
      // https://www.drupal.org/node/2638686
      // https://www.drupal.org/node/2630808
      // http://drupal.stackexchange.com/questions/187086/trustedresponseredirect-failing-how-to-prevent-cache-metadata

      // @todo Figure out why caching this response leads to stale images being
      // served.

      // $url = Url::fromUri($image_style->buildUrl($source_uri))->toString(TRUE);
      // $response = new TrustedRedirectResponse($url->getGeneratedUrl());
      // $response->addCacheableDependency($url);

      $response = new TrustedRedirectResponse($image_style->buildUrl($source_uri));
      $response->addCacheableDependency(0);

      return $response;
    }

    throw new FileNotFoundException(sprintf('%derivative_uri does not exist', $derivative_uri));
  }

  /**
   * Delivers a generate an image, deliver it, and upload it to the adapter.
   *
   * @param string $scheme
   *   The scheme of the source image.
   * @param string $source_path
   *   The path of the source image.
   * @param \Drupal\image\ImageStyleInterface $image_style
   *   The image style to generate.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Symfony\Component\HttpFoundation\Response
   *   The image response, or an error response if image generation failed.
   */
  protected function deliverTemporary($scheme, $source_path, ImageStyleInterface $image_style) {
    $source_uri = $scheme . '://' . $source_path;
    $derivative_uri = $image_style->buildUri($source_uri);
    try {
      $temporary_uri = $this->generateTemporaryImage($scheme, $source_path, $image_style);
    }
    catch (\RuntimeException $e) {
      $this->logger->notice('Unable to generate the derived image located at %path.', ['%path' => $derivative_uri]);
      return new Response($this->t('Error generating image.'), 500);
    }

    // Register a copy task with the kernel terminate handler.
    $this->imageStyleCopier->addCopyTask($temporary_uri, $source_uri, $image_style);

    // Symfony's kernel terminate handler is documented to only executes after
    // flushing with fastcgi, and not with mod_php or regular CGI. However,
    // it appears to work with mod_php. We assume it doesn't and register a
    // shutdown handler unless we know we are under fastcgi. If images have
    // been previously flushed and uploaded, this call will do nothing.
    //
    // https://github.com/symfony/symfony-docs/issues/6520
    if (!function_exists('fastcgi_finish_request')) {
      drupal_register_shutdown_function(function () {
        $this->flushCopy();
      });
    }

    return $this->send($scheme, $temporary_uri);
  }

}
