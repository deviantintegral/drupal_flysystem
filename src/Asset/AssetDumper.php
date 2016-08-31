<?php

namespace Drupal\flysystem\Asset;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Asset\AssetDumper as DrupalAssetDumper;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\file\Entity\File;
use Drupal\flysystem\AssetCopier;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Flysystem dependency injection container.
 *
 * @codeCoverageIgnore
 */
class AssetDumper extends DrupalAssetDumper implements ContainerInjectionInterface {

  use SchemeExtensionTrait;

  /**
   * The AssetCopier service.
   *
   * @var \Drupal\flysystem\AssetCopier
   */
  protected $assetCopier;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('flysystem.asset_copier')
    );
  }

  /**
   * AssetDumper constructor.
   *
   * @param \Drupal\flysystem\AssetCopier $asset_copier
   *   The AssetCopier service.
   */
  public function __construct($asset_copier) {
    $this->assetCopier = $asset_copier;
  }

  /**
   * {@inheritdoc}
   */
  public function dump($data, $file_extension) {
    // Prefix filename to prevent blocking by firewalls which reject files
    // starting with "ad*".
    $filename = $file_extension . '_' . Crypt::hashBase64($data) . '.' . $file_extension;
    $scheme = $this->getSchemeForExtension($file_extension);
    // Create the css/ or js/ path within the files folder.
    $path = $scheme . '://' . $file_extension;
    $uri = $path . '/' . $filename;
    // Create the CSS or JS file.
    if (!file_exists($uri)) {
      // The file is not available in the remote storage. Therefore, we will
      // serve it from the temporary storage and register a copy task to be
      // processed during shutdown.
      $remote_uri = $uri;
      $path = 'temporary://flysystem/' . $scheme . '/' . $file_extension;
      $uri = $path . '/' . $filename;

      // Save the temporary asset so cron will eventually clean it up.
      $result = file_prepare_directory($path, FILE_CREATE_DIRECTORY);
      if (!$result || !file_unmanaged_save_data($data, $uri, FILE_EXISTS_REPLACE)) {
        return FALSE;
      }
      $file = File::create([
        'uid' => User::getAnonymousUser()->id(),
        'uri' => $uri,
      ]);
      $file->save();
      $this->assetCopier->addCopyTask($uri, $remote_uri);

      // @TODO make core to provide the following logic as a method so we don't
      // have to copy it here.
      // If CSS/JS gzip compression is enabled and the zlib extension is
      // available // then create a gzipped version of this file. This file is
      // served conditionally to browsers that accept gzip using .htaccess
      // rules. It's possible that the rewrite rules in .htaccess aren't working
      // on this server, but there's no harm (other than the time spent
      // generating the file) in generating the file anyway. Sites on servers
      // where rewrite rules aren't working can set css.gzip to FALSE in order
      // to skip generating a file that won't be used.
      if (extension_loaded('zlib') && \Drupal::config('system.performance')->get($file_extension . '.gzip')) {
        if (!file_unmanaged_save_data(gzencode($data, 9, FORCE_GZIP), $uri . '.gz', FILE_EXISTS_REPLACE)) {
          return FALSE;
        }
        $this->assetCopier->addCopyTask($uri . '.gz', $remote_uri . '.gz');
      }
    }

    return $uri;
  }

}
