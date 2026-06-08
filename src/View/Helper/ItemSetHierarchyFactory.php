<?php

declare(strict_types=1);

namespace ItemSetGroup\View\Helper;

use Interop\Container\ContainerInterface;
use ItemSetGroup\Hierarchy\ItemSetHierarchy as ItemSetHierarchyService;
use ItemSetGroup\View\Helper\ItemSetHierarchy as ItemSetHierarchyHelper;
use Laminas\ServiceManager\Factory\FactoryInterface;

/**
 * Factory for the item set hierarchy view helper.
 */
class ItemSetHierarchyFactory implements FactoryInterface {

  /**
   * Create the item set hierarchy view helper.
   */
  public function __invoke(ContainerInterface $container, $requestedName, ?array $options = NULL) {
    $connection = $container->get('Omeka\Connection');
    $hierarchy = new ItemSetHierarchyService($connection);
    return new ItemSetHierarchyHelper($hierarchy);
  }

}
