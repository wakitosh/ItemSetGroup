<?php

declare(strict_types=1);

namespace ItemSetGroup\View\Helper;

use ItemSetGroup\Hierarchy\ItemSetHierarchy as ItemSetHierarchyService;
use Laminas\View\Helper\AbstractHelper;

/**
 * Expose item set hierarchy queries to view templates.
 */
class ItemSetHierarchy extends AbstractHelper {

  /**
   * Hierarchy service.
   *
   * @var \ItemSetGroup\Hierarchy\ItemSetHierarchy
   */
  protected $hierarchy;

  public function __construct(ItemSetHierarchyService $hierarchy) {
    $this->hierarchy = $hierarchy;
  }

  /**
   * Return the helper instance for method chaining in templates.
   */
  public function __invoke(): self {
    return $this;
  }

  /**
   * Return visible root item set ids.
   */
  public function getRootItemSetIds(?int $siteId = NULL, bool $publicOnly = TRUE): array {
    return $this->hierarchy->getRootItemSetIds($siteId, $publicOnly);
  }

  /**
   * Return root item set ids within the given visible set.
   */
  public function getRootItemSetIdsForSet(array $visibleIds): array {
    return $this->hierarchy->getRootItemSetIdsForSet($visibleIds);
  }

  /**
   * Return descendants of one item set.
   */
  public function getDescendantIds(int $itemSetId, ?int $siteId = NULL, bool $publicOnly = TRUE): array {
    return $this->hierarchy->getDescendantIds($itemSetId, $siteId, $publicOnly);
  }

  /**
   * Return descendant ids keyed by item set id.
   */
  public function getDescendantMap(?int $siteId = NULL, bool $publicOnly = TRUE): array {
    return $this->hierarchy->getDescendantMap($siteId, $publicOnly);
  }

}
