<?php

declare(strict_types=1);

namespace ItemSetGroup\Controller;

use Laminas\Mvc\Controller\AbstractActionController;

/**
 * Controller for pretty route redirects for item set groups.
 */
class GroupsController extends AbstractActionController {

  /**
   * Forward /item-set-group routes internally to the site ItemSet browse.
   */
  public function redirectAction() {
    $routeParams = $this->params();
    $siteSlug = $routeParams->fromRoute('site-slug');
    $parent = $routeParams->fromRoute('parent');

    if ($siteSlug === NULL || $siteSlug === '') {
      try {
        $services = $this->getEvent()->getApplication()->getServiceManager();
        $settings = $services->get('Omeka\\Settings');
        $api = $services->get('Omeka\\ApiManager');
        $defaultSiteId = (int) ($settings->get('default_site') ?: 0);
        $slug = '';
        if ($defaultSiteId > 0) {
          try {
            $site = $api->read('sites', $defaultSiteId)->getContent();
            if ($site && method_exists($site, 'slug')) {
              $slug = (string) $site->slug();
            }
          }
          catch (\Throwable $e) {
          }
        }
        if ($slug === '') {
          $res = $api->search('sites', ['limit' => 1, 'is_public' => 1]);
          $sites = $res->getContent();
          if (!empty($sites)) {
            $first = reset($sites);
            if ($first && method_exists($first, 'slug')) {
              $slug = (string) $first->slug();
            }
          }
        }
        if ($slug !== '') {
          $url = '/s/' . rawurlencode($slug) . '/item-set-group';
          if ($parent !== NULL) {
            $url .= '/' . rawurlencode((string) (int) $parent);
          }
          return $this->redirect()->toUrl($url);
        }
      }
      catch (\Throwable $e) {
      }
    }

    $routeMatch = $this->getEvent()->getRouteMatch();
    if ($routeMatch) {
      $routeMatch->setParam('controller', 'Omeka\\Controller\\Site\\ItemSet');
      $routeMatch->setParam('groups_route', TRUE);
      if ($parent !== NULL) {
        $routeMatch->setParam('parent', $parent);
      }
    }

    try {
      $request = $this->getRequest();
      if ($request && method_exists($request, 'getQuery')) {
        $query = $request->getQuery();
        if ($parent !== NULL) {
          $existing = $query->get('property', []);
          if (!is_array($existing)) {
            $existing = [];
          }
          $target = [
            'property' => 'dcterms:isPartOf',
            'type' => 'res',
            'text' => (string) (int) $parent,
          ];
          $normalized = [];
          $found = FALSE;
          foreach ($existing as $f) {
            if (is_array($f)
              && isset($f['property'], $f['type'], $f['text'])
              && (string) $f['property'] === 'dcterms:isPartOf'
              && (string) $f['type'] === 'res'
              && (string) $f['text'] === $target['text']) {
              if (!$found) {
                $normalized[] = $target;
                $found = TRUE;
              }
              continue;
            }
            if (is_array($f)) {
              $normalized[] = $f;
            }
          }
          if (!$found) {
            $normalized[] = $target;
          }
          $query->set('property', array_values($normalized));
        }
        if (!$query->get('layout')) {
          $query->set('layout', 'groups');
        }
        if (!$query->get('sort_by')) {
          $query->set('sort_by', 'dcterms:title');
        }
        if (!$query->get('sort_order')) {
          $query->set('sort_order', 'asc');
        }
        try {
          $identity = $this->identity();
          if (!$identity && !$query->get('is_public')) {
            $query->set('is_public', 1);
          }
        }
        catch (\Throwable $e) {
        }
      }
    }
    catch (\Throwable $e) {
    }

    $forwardParams = [
      'controller' => 'Omeka\\Controller\\Site\\ItemSet',
      'action' => 'browse',
      'groups_route' => TRUE,
    ];
    if ($siteSlug !== NULL) {
      $forwardParams['site-slug'] = $siteSlug;
    }
    if ($parent !== NULL) {
      $forwardParams['parent'] = $parent;
    }
    $result = $this->forward()->dispatch('Omeka\\Controller\\Site\\ItemSet', $forwardParams);

    try {
      $services = $this->getEvent()->getApplication()->getServiceManager();
      if ($services && $services->has('ControllerPluginManager')) {
        $cpm = $services->get('ControllerPluginManager');
        if (method_exists($cpm, 'getController')) {
          $current = $cpm->getController();
          if ($current && method_exists($current, 'getEvent')) {
            $rm2 = $current->getEvent()->getRouteMatch();
            if ($rm2 && !($rm2->getParam('controller'))) {
              $rm2->setParam('controller', 'Omeka\\Controller\\Site\\ItemSet');
            }
          }
        }
        if ($services->has('ControllerManager')) {
          try {
            $cm = $services->get('ControllerManager');
            $itemSetCtrl = $cm->get('Omeka\\Controller\\Site\\ItemSet');
            if ($itemSetCtrl && method_exists($itemSetCtrl, 'getEvent')) {
              $evt = $itemSetCtrl->getEvent();
              if ($evt && method_exists($evt, 'getRouteMatch')) {
                $rm3 = $evt->getRouteMatch();
                if ($rm3 && !($rm3->getParam('controller'))) {
                  $rm3->setParam('controller', 'Omeka\\Controller\\Site\\ItemSet');
                }
              }
            }
            if (method_exists($cpm, 'setController')) {
              $cpm->setController($itemSetCtrl);
            }
          }
          catch (\Throwable $e) {
          }
        }
      }
    }
    catch (\Throwable $ignore) {
    }

    return $result;
  }

}
