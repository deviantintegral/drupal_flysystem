<?php

namespace Drupal\flysystem;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\File\Exception\UploadException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Copies an asset from temporary storage to a flysystem adapter.
 *
 * This class is registered to run on the kernel's terminate event so it doesn't
 * block asset delivery.
 *
 * @class AssetCopier
 */
class AssetCopier implements EventSubscriberInterface, ContainerInjectionInterface {

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
   * The flysystem logger channel.
   *
   * @var \Psr\Log\LoggerInterface;
   */
  protected $logger;

  /**
   * An array of assets to copy.
   *
   * @var array
   */
  protected $copyTasks = [];

  /**
   * Construct AssetCopier.
   *
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   The lock backend.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system.
   * @param \Psr\Log\LoggerInterface $logger
   *   The flysystem logger channel.
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
    return new static(
      $container->get('lock'),
      $container->get('file_system'),
      $container->get('logger.channel.flysystem')
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
   * Add a task to copy an asset.
   *
   * @param string $temporary_uri
   *   The URI of the temporary asset to copy from.
   * @param string $source_uri
   *   The final destination of the asset.
   */
  public function addCopyTask($temporary_uri, $source_uri) {
    $this->copyTasks[] = [$temporary_uri, $source_uri];
  }

  /**
   * Flush the output buffer and process asset copy tasks.
   */
  public function processCopyTasks() {
    flush();
    foreach ($this->copyTasks as $task) {
      list($temporary_uri, $source_uri) = $task;
      $this->copyToAdapter($temporary_uri, $source_uri);
    }

    $this->copyTasks = array();
  }

  /**
   * Copy an asset to a remote storage.
   *
   * @param string $temporary_uri
   *   The temporary file URI to copy to the adapter.
   * @param string $destination_uri
   *   The destination URI of the asset.
   *
   * @return string Thrown if the asset could not be copied.
   *   Thrown if the asset could not be copied.
   */
  protected function copyToAdapter($temporary_uri, $destination_uri) {
    // file_unmanaged_copy() doesn't distinguish between a FALSE return due to
    // and error or a FALSE return due to an existing file. If we can't acquire
    // this lock, we know another thread is uploading the asset and we ignore
    // uploading it in this thread.
    $lock_name = 'flysystem_copy_to_adapter:' . Crypt::hashBase64($temporary_uri);
    if (!$this->lock->acquire($lock_name)) {
      throw new UploadException('Another copy of %asset to %destination is in progress', $temporary_uri, $destination_uri);
    }

    $directory = $this->fileSystem->dirname($destination_uri);

    // Build the destination folder tree if it doesn't already exist.
    if (!file_prepare_directory($directory, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS)) {
      $this->logger->error('Failed to create asset directory: %directory', array('%directory' => $directory));
      return FALSE;
    }
    if (!$path = file_unmanaged_copy($temporary_uri, $destination_uri, FILE_EXISTS_REPLACE)) {
      $this->lock->release($lock_name);
      throw new UploadException(sprintf('Unable to copy %asset to %destination', $temporary_uri, $destination_uri));
    }

    $this->lock->release($lock_name);
    return $path;
  }

}
