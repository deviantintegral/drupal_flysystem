<?php

namespace Drupal\flysystem\Controller;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\flysystem\AssetCopier;
use Drupal\file\FileStorageInterface;
use Drupal\system\FileDownloadController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines a controller to serve assets.
 */
class AssetDownloadController extends FileDownloadController {

  /**
   * The asset copier.
   *
   * @var \Drupal\flysystem\AssetCopier
   */
  protected $assetCopier;

  /**
   * Constructs a AssetDownloadController object.
   *
   * @param \Drupal\flysystem\AssetCopier $asset_copier
   *   The asset copier.
   */
  public function __construct(FileStorageInterface $file_storage, FileSystemInterface $file_system, AssetCopier $asset_copier) {
    $this->fileStorage = $file_storage;
    $this->fileSystem = $file_system;
    $this->assetCopier = $asset_copier;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager')->getStorage('file'),
      $container->get('file_system'),
      $container->get('flysystem.asset_copier')
    );
  }

  /**
   * Returns a requested asset either from temporary or remote storage.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param string $scheme
   *   The file scheme, defaults to 'private'.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Symfony\Component\HttpFoundation\Response
   *   The transferred file as response or some error response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Thrown when the user does not have access to the file.
   * @throws \Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException
   *   Thrown when the file is still being generated.
   */
  public function download(Request $request, $scheme = 'private') {
    $target = $request->query->get('file');

    // Match temporary assets for Flysystem module.
    // The following regular expression matches a $target like the following:
    //
    // flysystem/s3/css/css_XxSiqk25LbfrUfH1WWSIPRUKMC0BHjtx1fLYAkrBZDI.css.
    //
    // The match array contains the following structure:
    // array(3) {
    //   [0] => string(68) "flysystem/s3/css/css_XxSiqk25LbfrUfH1WWSIPRUKMC0BHjtx1fLYAkrBZDI.css"
    //   [1] => string(2) "s3"
    //   [2] => string(3) "css"
    //   [3] => string(51) "css_XxSiqk25LbfrUfH1WWSIPRUKMC0BHjtx1fLYAkrBZDI.css"
    // }
    // If there is no match, we let the original controller to process the
    // request.
    $matches = [];
    if (!preg_match('#flysystem/([\w]+)/(css|js)/(.+\.(?:css|js))#', $target, $matches)) {
      return parent::download($request, $scheme);
    }

    // Check if the asset has been copied already at the remote storage.
    $scheme = $matches[1];
    $asset_type = $matches[2];
    $asset_filename = $matches[3];
    $remote_url = $scheme . '://' . $asset_type . '/' . $asset_filename;
    if (file_exists($remote_url)) {
      // Yipie! The file has been copied. Return a 301 redirection.
      $url = file_create_url($remote_url);
      $response = new TrustedRedirectResponse($url);
      $response->addCacheableDependency($url);
      return $response;
    }

    // Serve the file from temporary storage.
    $uri = 'temporary://' . $target;
    $files = $this->fileStorage->loadByProperties(['uri' => $uri]);
    $headers = file_get_content_headers(reset($files));
    return new BinaryFileResponse($uri, 200, $headers, $scheme !== 'private');
  }

}
