<?php

declare(strict_types=1);

namespace ItemSetGroup\Hierarchy;

use Doctrine\DBAL\Connection;

/**
 * Compute site-scoped item set hierarchy roles and descendants.
 */
class ItemSetHierarchy {

  /**
   * Database connection.
   *
   * @var \Doctrine\DBAL\Connection
   */
  protected $connection;

  /**
   * Cached dcterms:isPartOf property id.
   *
   * @var int|null
   */
  protected $isPartOfPropertyId;

  /**
   * Cached hierarchy graphs keyed by site/public scope.
   *
   * @var array<string, array<string, mixed>>
   */
  protected $graphCache = [];

  /**
   * Create the hierarchy helper.
   */
  public function __construct(Connection $connection) {
    $this->connection = $connection;
    $this->isPartOfPropertyId = NULL;
  }

  /**
   * Return visible root item set ids for the given scope.
   */
  public function getRootItemSetIds(?int $siteId = NULL, bool $publicOnly = TRUE): array {
    $graph = $this->getGraph($siteId, $publicOnly);
    return $this->extractRootIds($graph['visibleIds'], $graph['parentsByChild'], $graph['childrenByParent']);
  }

  /**
   * Return root item set ids within an arbitrary visible id set.
   */
  public function getRootItemSetIdsForSet(array $visibleIds): array {
    $normalizedIds = array_values(array_unique(array_map('intval', $visibleIds)));
    if (!$normalizedIds) {
      return [];
    }

    $lookup = [];
    $parentsByChild = [];
    $childrenByParent = [];
    foreach ($normalizedIds as $itemSetId) {
      if ($itemSetId <= 0) {
        continue;
      }
      $lookup[$itemSetId] = TRUE;
      $parentsByChild[$itemSetId] = [];
      $childrenByParent[$itemSetId] = [];
    }
    if (!$lookup) {
      return [];
    }

    $propId = $this->getIsPartOfPropertyId();
    if ($propId > 0) {
      $placeholders = implode(',', array_fill(0, count($lookup), '?'));
      $params = array_merge([$propId], array_keys($lookup));
      $rows = $this->connection->fetchAllAssociative(
        "SELECT DISTINCT v.resource_id AS child_id, v.value_resource_id AS parent_id
        FROM value v
        INNER JOIN resource child ON child.id = v.resource_id
        WHERE v.property_id = ?
          AND v.value_resource_id IS NOT NULL
          AND child.resource_type = 'Omeka\\Entity\\ItemSet'
          AND child.id IN ($placeholders)",
        $params
      );
      foreach ($rows as $row) {
        $childId = isset($row['child_id']) ? (int) $row['child_id'] : 0;
        $parentId = isset($row['parent_id']) ? (int) $row['parent_id'] : 0;
        if ($childId <= 0 || $parentId <= 0 || !isset($lookup[$childId])) {
          continue;
        }
        if (isset($lookup[$parentId])) {
          $parentsByChild[$childId][$parentId] = $parentId;
          $childrenByParent[$parentId][$childId] = $childId;
        }
      }
    }

    foreach ($parentsByChild as $itemSetId => $parentIds) {
      $parentsByChild[$itemSetId] = array_values($parentIds);
    }
    foreach ($childrenByParent as $itemSetId => $childIds) {
      $childrenByParent[$itemSetId] = array_values($childIds);
    }

    return $this->extractRootIds(array_keys($lookup), $parentsByChild, $childrenByParent);
  }

  /**
   * Return all visible descendants below one item set.
   */
  public function getDescendantIds(int $itemSetId, ?int $siteId = NULL, bool $publicOnly = TRUE): array {
    if ($itemSetId <= 0) {
      return [];
    }
    $graph = $this->getGraph($siteId, $publicOnly);
    return $this->collectDescendantIds($itemSetId, $graph['childrenByParent']);
  }

  /**
   * Return descendant ids for each visible item set.
   */
  public function getDescendantMap(?int $siteId = NULL, bool $publicOnly = TRUE): array {
    $graph = $this->getGraph($siteId, $publicOnly);
    $map = [];
    foreach ($graph['visibleIds'] as $itemSetId) {
      $descendants = $this->collectDescendantIds((int) $itemSetId, $graph['childrenByParent']);
      if ($descendants) {
        $map[(string) $itemSetId] = $descendants;
      }
    }
    return $map;
  }

  /**
   * Walk descendants in breadth-first order while preventing cycles.
   */
  protected function collectDescendantIds(int $itemSetId, array $childrenByParent): array {
    $queue = $childrenByParent[$itemSetId] ?? [];
    $descendants = [];
    $seen = [];
    while ($queue) {
      $childId = (int) array_shift($queue);
      if ($childId <= 0 || isset($seen[$childId])) {
        continue;
      }
      $seen[$childId] = TRUE;
      $descendants[] = $childId;
      foreach ($childrenByParent[$childId] ?? [] as $grandChildId) {
        if (!isset($seen[$grandChildId])) {
          $queue[] = (int) $grandChildId;
        }
      }
    }
    return $descendants;
  }

  /**
   * Extract root ids from precomputed parent and child maps.
   */
  protected function extractRootIds(array $visibleIds, array $parentsByChild, array $childrenByParent): array {
    $rootIds = [];
    foreach ($visibleIds as $itemSetId) {
      $itemSetId = (int) $itemSetId;
      $hasChild = !empty($childrenByParent[$itemSetId]);
      $hasParent = !empty($parentsByChild[$itemSetId]);
      if ($hasChild && !$hasParent) {
        $rootIds[] = $itemSetId;
      }
    }
    sort($rootIds, SORT_NUMERIC);
    return $rootIds;
  }

  /**
   * Build the visible hierarchy graph for one site/public scope.
   */
  protected function getGraph(?int $siteId = NULL, bool $publicOnly = TRUE): array {
    $cacheKey = (string) ((int) $siteId) . ':' . ($publicOnly ? '1' : '0');
    if (isset($this->graphCache[$cacheKey])) {
      return $this->graphCache[$cacheKey];
    }

    $visibleIds = $this->getVisibleItemSetIds($siteId, $publicOnly);
    $visibleLookup = [];
    $parentsByChild = [];
    $childrenByParent = [];
    foreach ($visibleIds as $visibleId) {
      $visibleLookup[$visibleId] = TRUE;
      $parentsByChild[$visibleId] = [];
      $childrenByParent[$visibleId] = [];
    }

    $propId = $this->getIsPartOfPropertyId();
    if ($propId > 0 && $visibleIds) {
      $sql = "SELECT DISTINCT v.resource_id AS child_id, v.value_resource_id AS parent_id
        FROM value v
        INNER JOIN resource child ON child.id = v.resource_id";
      $params = [$propId];
      if ($siteId) {
        $sql .= "
        INNER JOIN site_item_set sis ON sis.item_set_id = child.id AND sis.site_id = ?";
        $params[] = (int) $siteId;
      }
      $sql .= "
        WHERE v.property_id = ?
          AND v.value_resource_id IS NOT NULL
          AND child.resource_type = 'Omeka\\Entity\\ItemSet'";
      if ($publicOnly) {
        $sql .= "
          AND child.is_public = 1";
      }
      $rows = $this->connection->fetchAllAssociative($sql, array_reverse($params));
      foreach ($rows as $row) {
        $childId = isset($row['child_id']) ? (int) $row['child_id'] : 0;
        $parentId = isset($row['parent_id']) ? (int) $row['parent_id'] : 0;
        if ($childId <= 0 || $parentId <= 0 || !isset($visibleLookup[$childId])) {
          continue;
        }
        $parentsByChild[$childId][$parentId] = $parentId;
        if (isset($visibleLookup[$parentId])) {
          $childrenByParent[$parentId][$childId] = $childId;
        }
      }
    }

    foreach ($parentsByChild as $itemSetId => $parentIds) {
      $parentsByChild[$itemSetId] = array_values($parentIds);
    }
    foreach ($childrenByParent as $itemSetId => $childIds) {
      $childrenByParent[$itemSetId] = array_values($childIds);
    }

    $this->graphCache[$cacheKey] = [
      'visibleIds' => $visibleIds,
      'parentsByChild' => $parentsByChild,
      'childrenByParent' => $childrenByParent,
    ];
    return $this->graphCache[$cacheKey];
  }

  /**
   * Return visible item set ids for the given scope.
   */
  protected function getVisibleItemSetIds(?int $siteId = NULL, bool $publicOnly = TRUE): array {
    $sql = "SELECT DISTINCT r.id
      FROM resource r";
    $params = [];
    if ($siteId) {
      $sql .= "
      INNER JOIN site_item_set sis ON sis.item_set_id = r.id AND sis.site_id = ?";
      $params[] = (int) $siteId;
    }
    $sql .= "
      WHERE r.resource_type = 'Omeka\\Entity\\ItemSet'";
    if ($publicOnly) {
      $sql .= "
        AND r.is_public = 1";
    }
    $sql .= "
      ORDER BY r.id ASC";
    $ids = $this->connection->fetchFirstColumn($sql, $params);
    return array_values(array_map('intval', $ids));
  }

  /**
   * Resolve the dcterms:isPartOf property id once.
   */
  protected function getIsPartOfPropertyId(): int {
    if ($this->isPartOfPropertyId !== NULL) {
      return (int) $this->isPartOfPropertyId;
    }
    try {
      $this->isPartOfPropertyId = (int) $this->connection->fetchOne(
        "SELECT p.id
        FROM property p
        INNER JOIN vocabulary v ON v.id = p.vocabulary_id
        WHERE v.prefix = 'dcterms'
          AND p.local_name = 'isPartOf'
        LIMIT 1"
      );
    }
    catch (\Throwable $e) {
      $this->isPartOfPropertyId = 0;
    }
    return (int) $this->isPartOfPropertyId;
  }

}
