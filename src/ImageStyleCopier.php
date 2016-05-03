<?php
/**
 * @file
 * Contains \Drupal\flysystem\ImageStyleCopier.
 */

namespace Drupal\flysystem;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\image\ImageStyleInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\File\Exception\UploadException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Copies an image style from temporary storage to a flysystem adapter.
 *
 * This class is registered to run on the kernel's terminate event so it doesn't
 * block image delivery.
 *
 * @class ImageStyleCopier
 */
class ImageStyleCopier implements EventSubscriberInterface, ContainerInjectionInterface {

  /**
   * The lock backend interface.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected $lock;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * The system logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * An array of image derivatives to copy.
   *
   * @var array
   */
  protected $copyTasks = [];

  /**
   * Construct ImageStyleCopier.
   *
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   The lock backend.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system.
   * @param \Psr\Log\LoggerInterface $logger
   *   The system logger.
   */
  public function __construct(LockBackendInterface $lock, FileSystemInterface $file_system, LoggerInterface $logger) {
    $this->lock = $lock;
    $this->fileSystem = $file_system;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var LockBackendInterface $lock */
    $lock = $container->get('lock');
    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = $container->get('file_system');
    /** @var \Psr\Log\LoggerInterface $logger */
    $logger = $container->get('logger.channel.image');
    return new static(
      $lock,
      $file_system,
      $logger
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = array();
    $events[KernelEvents::TERMINATE] = 'processCopyTasks';
    return $events;
  }

  /**
   * Add a task to generate and copy an image derivative.
   *
   * @param string $temporary_uri
   *   The URI of the temporary image to copy from.
   * @param string $source_uri
   *   The final destination of the image derivative.
   * @param \Drupal\image\ImageStyleInterface $image_style
   *   The image style being copied.
   */
  public function addCopyTask($temporary_uri, $source_uri, ImageStyleInterface $image_style) {
    $this->copyTasks[] = func_get_args();
  }

  /**
   * Process all image copy tasks.
   */
  public function processCopyTasks() {
    foreach ($this->copyTasks as $task) {
      list($temporary_uri, $source_uri, $image_style) = $task;
      $this->copyToAdapter($temporary_uri, $source_uri, $image_style);
    }

    $this->copyTasks = array();
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
      $this->logger->error('Failed to create style directory: %directory', array('%directory' => $directory));
      return FALSE;
    }
    if (!$path = file_unmanaged_copy($temporary_uri, $derivative_uri, FILE_EXISTS_REPLACE)) {
      $this->lock->release($lock_name);
      throw new UploadException(sprintf('Unable to copy %image to %destination', $temporary_uri, $derivative_uri));
    }

    $this->lock->release($lock_name);

    return $path;
  }

}
