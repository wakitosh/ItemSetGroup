<?php

declare(strict_types=1);

namespace ItemSetGroup\Site\BlockLayout;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

/**
 *
 */
class SelectionFactory implements FactoryInterface {

  /**
   *
   */
  public function __invoke(ContainerInterface $container, $requestedName, ?array $options = NULL) {
    $formElementManager = $container->get('FormElementManager');
    $connection = $container->get('Omeka\\Connection');
    return new Selection($formElementManager, $connection);
  }

}
