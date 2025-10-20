<?php

declare(strict_types=1);

namespace ItemSetGroup\View\Helper;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

/**
 * Factory for ItemSetPrimaryThumb view helper.
 */
class ItemSetPrimaryThumbFactory implements FactoryInterface {

  /**
   * {@inheritDoc}
   */
  public function __invoke(ContainerInterface $container, $requestedName, ?array $options = NULL) {
    return new ItemSetPrimaryThumb(
      $container->get('Omeka\\Connection'),
      $container->get('Omeka\\ApiManager')
    );
  }

}
