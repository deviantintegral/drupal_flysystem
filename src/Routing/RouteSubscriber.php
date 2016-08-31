<?php

namespace Drupal\flysystem\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\flysystem\Controller\AssetDownloadController;
use Symfony\Component\Routing\RouteCollection;

/**
 * Class RouteSubscriber.
 *
 * @package Drupal\flysystem\Routing
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // Change the controller for downloading temporary files.
    // We can't use a path_processor_inbound service because
    // the temporary file controller does not implement that
    // interface.
    if ($route = $collection->get('system.temporary')) {
      $route->setDefault('_controller', AssetDownloadController::class . '::download');
    }
  }

}
