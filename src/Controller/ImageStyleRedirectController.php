<?php
/**
 * @file
 * Contains Drupal\flysystem\Controller\ImageStyleRedirectController.
 */

namespace Drupal\flysystem\Controller;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\File\FileSystem;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\image\ImageStyleInterface;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\File\Exception\UploadException;
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
   * Constructs a ImageStyleDownloadController object.
   *
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   The lock backend.
   * @param \Drupal\Core\Image\ImageFactory $image_factory
   *   The image factory.
   * @param \Drupal\Core\Entity\EntityStorageInterface $file_storage
   *   The file entity storage.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system.
   */
  public function __construct(LockBackendInterface $lock, ImageFactory $image_factory, EntityStorageInterface $file_storage, FileSystemInterface $file_system) {
    parent::__construct($lock, $image_factory);
    $this->fileStorage = $file_storage;
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var LockBackendInterface $lock */
    $lock = $container->get('lock');
    /** @var ImageFactory $image_factory */
    $image_factory = $container->get('image.factory');
    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = $container->get('file_system');

    return new static(
      $lock,
      $image_factory,
      $container->get('entity.manager')->getStorage('file'),
      $file_system
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
      $this->logger->notice('Source image at %source_image_path not found while trying to generate derivative image at %derivative_path.', array('%source_image_path' => $source_uri, '%derivative_path' => $derivative_uri));
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
   * Generate an image with the remote stream wrapper.
   *
   * @param string $temporary_uri
   *   The temporary file URI to copy to the adapter.
   * @param string $source_uri
   *   The URI of the source image.
   * @param \Drupal\image\ImageStyleInterface $image_style
   *   The image style to generate.
   *
   * @return string Thrown if the image could not be copied.
   *   Thrown if the image could not be copied.
   */
  protected function copyToAdapter($temporary_uri, $source_uri, ImageStyleInterface $image_style) {
    $derivative_uri = $image_style->buildUri($source_uri);

    // file_unmanaged_copy() doesn't distinguish between a FALSE return due to
    // and error or a FALSE return due to an existing file. If we can't acquire
    // this lock, we know another thread is uploading the image and we ignore
    // uploading it in this thread.
    $lock_name = 'flysystem_copy_to_adapter:' . $image_style->id() . ':' . Crypt::hashBase64($source_uri);
    if (!$this->lock->acquire($lock_name)) {
      throw new UploadException('Another copy of %image to %destination is in progress', $temporary_uri, $derivative_uri);
    }

    // Get the folder for the final location of this style.
    $directory = $this->fileSystem->dirname($derivative_uri);

    // Build the destination folder tree if it doesn't already exist.
    if (!file_prepare_directory($directory, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS)) {
      $this->getLogger('image')->error('Failed to create style directory: %directory', array('%directory' => $directory));
      return FALSE;
    }
    if (!$path = file_unmanaged_copy($temporary_uri, $derivative_uri, FILE_EXISTS_REPLACE)) {
      $this->lock->release($lock_name);
      throw new UploadException(sprintf('Unable to copy %image to %destination', $temporary_uri, $derivative_uri));
    }

    $this->lock->release($lock_name);

    return $path;
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
   * @throws \Exception
   *   Thrown when generate() failed to generate an image.
   *
   * @return \Drupal\file\Entity\File
   *   The temporary image that was generated.
   */
  protected function generateTemporaryImage($scheme, $source_path, ImageStyleInterface $image_style) {
    $image_uri = "$scheme://$source_path";
    $destination_temp = $image_style->buildUri("temporary://flysystem/$scheme/$source_path");

    // Save the temporary image so cron will eventually clean it up.
    $temporary_image = reset($this->fileStorage->loadByProperties(['uri' => $destination_temp]));
    // The temporary file entity could exist, but the file on disk could have
    // been removed by a server reboot or a system administrator.
    if (!$temporary_image) {
      /** @var File $temporary_image */
      $temporary_image = File::create([
        'uid' => User::getAnonymousUser()->id(),
        'uri' => $destination_temp,
      ]);
    }

    // Try to generate the temporary image, watching for other threads that may
    // also be trying to generate the temporary image.
    try {
      $success = $this->generate($image_style, $image_uri, $destination_temp);
      if (!$success) {
        throw new \Exception('The temporary image could not be generated');
      }
      $temporary_image->save();
    }
    catch (ServiceUnavailableHttpException $e) {
      // This exception is only thrown if the lock could not be acquired.
      $tries = 0;
      while ($tries < 4 && (!file_exists($destination_temp) || !$temporary_image = reset($this->fileStorage->loadByProperties(['uri' => $destination_temp])))) {
        // The file still doesn't exist or it exists but the other thread hasn't
        // saved the entity yet.
        usleep(250000);
        $tries++;
      }

      // We waited for more than 1 second for the temporary image to appear.
      // Since local image generation should be fast, fail out here to try to
      // limit PHP process demands.
      if ($tries >= 4) {
        throw $e;
      }
    }

    return $temporary_image;
  }

  /**
   * Flush the output buffer and copy the temporary image to the adapter.
   *
   * @param \Drupal\file\FileInterface $temporary_image
   *   The temporary image that was generated.
   * @param string $source_uri
   *   The URI of the source image.
   * @param \Drupal\image\ImageStyleInterface $image_style
   *   The image style that was generated.
   *
   * @return string
   *   The path to the copied image.
   */
  protected function flushCopy(FileInterface $temporary_image, $source_uri, ImageStyleInterface $image_style) {
    // We have to call both of these to actually flush the image.
    ob_end_flush();
    flush();
    return $this->copyToAdapter($temporary_image->getFileUri(), $source_uri, $image_style);
  }

  /**
   * Redirect to to an adapter hosted image, if it exists.
   *
   * @param string $source_uri
   *   The URI to the source image.
   * @param \Drupal\image\ImageStyleInterface $image_style
   *   The image style to redirect to.
   *
   * @throws \Symfony\Component\Filesystem\Exception\FileNotFoundException
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
      $url = Url::fromUri($image_style->buildUrl($source_uri))->toString(TRUE);
      $response = new TrustedRedirectResponse($url->getGeneratedUrl());
      $response->addCacheableDependency($url);
      return $response;
    }

    throw new FileNotFoundException(sprintf('%derivative_uri does not exist', $derivative_uri));
  }

  /**
   * Deliver a generate an image, deliver it, and upload it to the adapter.
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
      $temporary_image = $this->generateTemporaryImage($scheme, $source_path, $image_style);
      drupal_register_shutdown_function(function () use ($source_uri, $temporary_image, $image_style) {
        $this->flushCopy($temporary_image, $source_uri, $image_style);
      });

      return $this->send($scheme, $temporary_image->getFileUri());
    }
    catch (\Exception $e) {
      $this->logger->notice('Unable to generate the derived image located at %path.', array('%path' => $derivative_uri));
      return new Response($this->t('Error generating image.'), 500);
    }
  }

}
