<?php

declare(strict_types=1);

namespace ItemSetGroup\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Omeka\Mvc\Exception\PermissionDeniedException;

/**
 * Controller for pretty route redirects for item set groups.
 */
class GroupsController extends AbstractActionController {

  /**
   * Redirect or forward groups browse.
   */
  public function redirectAction() {
    $routeParams = $this->params();
    $siteSlug = $routeParams->fromRoute('site-slug');
    $parent = $routeParams->fromRoute('parent');

    // If no site slug, resolve default (first public if needed).
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
          // Anonymous: redirect to /item-set with proper query.
          $isAnonymous = TRUE;
          try {
            $identity = $this->identity();
            $isAnonymous = !$identity;
          }
          catch (\Throwable $e) {
          }
          if ($isAnonymous) {
            $dest = '/s/' . rawurlencode($slug) . '/item-set';
            $params = [];
            try {
              $req = $this->getRequest();
              if ($req && method_exists($req, 'getQuery')) {
                $params = (array) $req->getQuery()->toArray();
              }
            }
            catch (\Throwable $e) {
            }
            if ($parent !== NULL) {
              if (!isset($params['property']) || !is_array($params['property'])) {
                $params['property'] = [];
              }
              $target = [
                'property' => 'dcterms:isPartOf',
                'type' => 'res',
                'text' => (string) (int) $parent,
              ];
              $normalized = [];
              $found = FALSE;
              foreach ($params['property'] as $f) {
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
              $params['property'] = array_values($normalized);
            }
            if (empty($params['layout'])) {
              $params['layout'] = 'groups';
            }
            if (empty($params['sort_by'])) {
              $params['sort_by'] = 'title';
            }
            if (empty($params['sort_order'])) {
              $params['sort_order'] = 'asc';
            }
            $params['is_public'] = 1;
            $qs = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
            return $this->redirect()->toUrl($dest . ($qs ? ('?' . $qs) : ''));
          }
          // Logged-in: keep pretty route (site-aware).
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

    // Anonymous with explicit site slug: redirect to /item-set.
    try {
      $identity = $this->identity();
      if (!$identity) {
        $slug = $siteSlug ? (string) $siteSlug : '';
        if ($slug === '') {
          try {
            $services = $this->getEvent()->getApplication()->getServiceManager();
            $settings = $services->get('Omeka\\Settings');
            $api = $services->get('Omeka\\ApiManager');
            $defaultSiteId = (int) ($settings->get('default_site') ?: 0);
            if ($defaultSiteId > 0) {
              $site = $api->read('sites', $defaultSiteId)->getContent();
              if ($site && method_exists($site, 'slug')) {
                $slug = (string) $site->slug();
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
          }
          catch (\Throwable $e) {
          }
        }
        $dest = ($slug !== '' ? ('/s/' . rawurlencode($slug)) : '') . '/item-set';
        $params = [];
        try {
          $req = $this->getRequest();
          if ($req && method_exists($req, 'getQuery')) {
            $params = (array) $req->getQuery()->toArray();
          }
        }
        catch (\Throwable $e) {
        }
        if ($parent !== NULL) {
          if (!isset($params['property']) || !is_array($params['property'])) {
            $params['property'] = [];
          }
          $target = [
            'property' => 'dcterms:isPartOf',
            'type' => 'res',
            'text' => (string) (int) $parent,
          ];
          $normalized = [];
          $found = FALSE;
          foreach ($params['property'] as $f) {
            if (is_array($f) && isset($f['property'], $f['type'], $f['text'])
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
          $params['property'] = array_values($normalized);
        }
        if (empty($params['layout'])) {
          $params['layout'] = 'groups';
        }
        if (empty($params['sort_by'])) {
          $params['sort_by'] = 'title';
        }
        if (empty($params['sort_order'])) {
          $params['sort_order'] = 'asc';
        }
        $params['is_public'] = 1;
        $qs = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        return $this->redirect()->toUrl($dest . ($qs ? ('?' . $qs) : ''));
      }
    }
    catch (\Throwable $e) {
      // Fall through to internal forward for logged-in users.
    }

    // Logged-in: forward to Site ItemSet browse.
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
          if (!$identity) {
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
    try {
      $result = $this->forward()->dispatch('Omeka\\Controller\\Site\\ItemSet', $forwardParams);
    }
    catch (\Throwable $denied) {
      if (!($denied instanceof PermissionDeniedException)) {
        throw $denied;
      }
      // Fallback: redirect to browse URL with query.
      $slug = $siteSlug ? (string) $siteSlug : '';
      if ($slug === '') {
        try {
          $services = $this->getEvent()->getApplication()->getServiceManager();
          $settings = $services->get('Omeka\\Settings');
          $api = $services->get('Omeka\\ApiManager');
          $defaultSiteId = (int) ($settings->get('default_site') ?: 0);
          if ($defaultSiteId > 0) {
            $site = $api->read('sites', $defaultSiteId)->getContent();
            if ($site && method_exists($site, 'slug')) {
              $slug = (string) $site->slug();
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
        }
        catch (\Throwable $e) {
        }
      }
      $url = $slug !== '' ? ('/s/' . rawurlencode($slug) . '/item-set') : '/item-set';
      $qs = [];
      if ($parent !== NULL) {
        $qs[] = 'property%5B0%5D%5Bproperty%5D=' . rawurlencode('dcterms:isPartOf');
        $qs[] = 'property%5B0%5D%5Btype%5D=res';
        $qs[] = 'property%5B0%5D%5Btext%5D=' . rawurlencode((string) (int) $parent);
      }
      $qs[] = 'layout=groups';
      $qs[] = 'sort_by=dcterms%3Atitle';
      $qs[] = 'sort_order=asc';
      $qs[] = 'is_public=1';
      return $this->redirect()->toUrl($url . '?' . implode('&', $qs));
    }

    return $result;
  }

}
