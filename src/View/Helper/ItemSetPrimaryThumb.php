<?php

declare(strict_types=1);

namespace ItemSetGroup\View\Helper;

use Doctrine\DBAL\Connection;
use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Manager as ApiManager;
use Omeka\Api\Representation\ItemSetRepresentation;

/**
 * Resolve an Item Set thumbnail.
 *
 * Prioritizes representative media or item. Falls back to IIIF-derived
 * thumbnails and can emit HTML with a skeleton wrapper.
 */
class ItemSetPrimaryThumb extends AbstractHelper {
  /**
   * Database connection.
   *
   * @var \Doctrine\DBAL\Connection
   */
  private $conn;

  /**
   * Omeka API manager.
   *
   * @var \Omeka\Api\Manager
   */
  private $api;

  public function __construct(Connection $conn, ApiManager $api) {
    $this->conn = $conn;
    $this->api = $api;
  }

  /**
   * Resolve and render the item set thumbnail.
   *
   * @param \\Omeka\\Api\\Representation\\ItemSetRepresentation $itemSet
   *   Target item set.
   * @param int $size
   *   Pixel size.
   * @param string $mode
   *   'URL' or 'HTML'.
   * @param string|null $iiifMode
   *   'Square' or 'Full'.
   */
  public function __invoke(ItemSetRepresentation $itemSet, int $size = 800, string $mode = 'url', ?string $iiifMode = NULL): string {
    $url = '';

    // 1) primary_media_id
    try {
      $sid = (int) $itemSet->id();
      $mid = (int) $this->conn->fetchOne('SELECT primary_media_id FROM item_set_primary_item WHERE item_set_id = ?', [$sid]);
      if ($mid > 0) {
        try {
          $media = $this->api->read('media', $mid)->getContent();
          $url = $this->iiifSquare($media, $size) ?: (string) $media->thumbnailUrl('large');
        }
        catch (\Throwable $e) {
          // Ignore.
        }
      }
      // 2) primary_item_id -> primaryMedia
      if ($url === '') {
        $pid = (int) $this->conn->fetchOne('SELECT primary_item_id FROM item_set_primary_item WHERE item_set_id = ?', [$sid]);
        if ($pid > 0) {
          try {
            $item = $this->api->read('items', $pid)->getContent();
            if ($item && method_exists($item, 'primaryMedia')) {
              $pm = $item->primaryMedia();
              if ($pm) {
                $url = $this->iiifSquare($pm, $size) ?: (string) $pm->thumbnailUrl('large');
              }
            }
          }
          catch (\Throwable $e) {
            // Ignore.
          }
        }
      }
    }
    catch (\Throwable $e) {
      // Ignore.
    }

    // 3) Fallback: first item in set (respect theme iiif mode if available)
    if ($url === '') {
      $modePref = $iiifMode ?: 'square';
      try {
        $view = $this->getView();
        if (method_exists($view, 'plugin')) {
          $ts = $view->plugin('themeSetting');
          if ($ts) {
            $m = (string) $ts('thumbnail_iiif_mode');
            if ($m === 'square' || $m === 'full') {
              $modePref = $m;
            }
          }
        }
      }
      catch (\Throwable $e) {
        // Ignore.
      }
      try {
        $items = $this->api->search('items', [
          'item_set_id' => (int) $itemSet->id(),
          'limit' => 1,
          'sort_by' => 'created',
          'sort_order' => 'asc',
        ])->getContent();
        if ($items && isset($items[0]) && method_exists($items[0], 'primaryMedia')) {
          $pm = $items[0]->primaryMedia();
          if ($pm) {
            $url = ($modePref === 'full')
              ? $this->iiifBestFit($pm, $size)
              : $this->iiifSquare($pm, $size);
            if (!$url) {
              $url = (string) $pm->thumbnailUrl('large');
            }
          }
        }
      }
      catch (\Throwable $e) {
        // Ignore.
      }
    }

    if ($mode === 'html') {
      $attrs = 'class="thumbnail" loading="lazy" decoding="async" alt="" '
        . 'onload="this.parentNode.classList.add(\'is-loaded\')" '
        . 'onerror="this.parentNode.classList.add(\'is-loaded\')"';
      if ($url) {
        return '<span class="thumb-frame"><img src="' . htmlspecialchars($url, ENT_QUOTES) . '" ' . $attrs . ' /></span>';
      }
      // Resource thumbnail fallback.
      try {
        $resUrl = '';
        if (method_exists($itemSet, 'thumbnailUrl')) {
          $resUrl = (string) $itemSet->thumbnailUrl('large') ?: (string) $itemSet->thumbnailUrl('medium');
        }
        if ($resUrl) {
          return '<span class="thumb-frame"><img src="' . htmlspecialchars($resUrl, ENT_QUOTES) . '" ' . $attrs . ' /></span>';
        }
      }
      catch (\Throwable $e) {
        // Ignore.
      }
      $blank = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';
      return '<span class="thumb-frame"><img src="' . $blank . '" ' . $attrs . ' /></span>';
    }

    return $url;
  }

  /**
   * Build IIIF square thumbnail URL.
   */
  private function iiifSquare($media, int $size): string {
    try {
      $data = $media->mediaData();
      $width = (int) ($data['width'] ?? 0);
      $height = (int) ($data['height'] ?? 0);
      $base = isset($data['id']) ? (string) $data['id'] : (string) $media->source();
      if ($base && substr($base, -9) === 'info.json') {
        $base = substr($base, 0, -9);
      }
      if ($base) {
        if ($width < $size || $height < $size) {
          $min = max(1, min($width, $height));
          return $base . '/square/' . $min . ',' . $min . '/0/default.jpg';
        }
        return $base . '/square/' . $size . ',/0/default.jpg';
      }
    }
    catch (\Throwable $e) {
      // Ignore.
    }
    return '';
  }

  /**
   * Build IIIF best-fit thumbnail URL.
   */
  private function iiifBestFit($media, int $size): string {
    try {
      $data = $media->mediaData();
      $base = isset($data['id']) ? (string) $data['id'] : (string) $media->source();
      if ($base && substr($base, -9) === 'info.json') {
        $base = substr($base, 0, -9);
      }
      if ($base) {
        return $base . '/full/!' . $size . ',' . $size . '/0/default.jpg';
      }
    }
    catch (\Throwable $e) {
      // Ignore.
    }
    return '';
  }

}
