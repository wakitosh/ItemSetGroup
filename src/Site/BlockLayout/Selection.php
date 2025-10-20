<?php

declare(strict_types=1);

namespace ItemSetGroup\Site\BlockLayout;

use Doctrine\DBAL\Connection;
use Laminas\Form\Element\Button;
use Laminas\Form\Element\Checkbox;
use Laminas\Form\Element\Hidden;
use Laminas\Form\Element\Text;
use Laminas\Form\Element\Select;
use Laminas\Form\Form;
use Laminas\Form\FormElementManager as LaminasFormElementManager;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Entity\SitePageBlock;
use Omeka\Form\Element\Asset as AssetElement;
use Omeka\Form\Element\HtmlTextarea;

use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Omeka\Stdlib\ErrorStore;

/**
 * Block layout for selecting and displaying item set groups.
 */
class Selection extends AbstractBlockLayout {

  /**
   * Form element manager.
   *
   * @var \Laminas\Form\FormElementManager
   */
  protected $formElementManager;

  /**
   * Database connection.
   *
   * @var \Doctrine\DBAL\Connection
   */
  protected $connection;

  /**
   * Constructor.
   */
  public function __construct(LaminasFormElementManager $formElementManager, Connection $connection) {
    $this->formElementManager = $formElementManager;
    $this->connection = $connection;
  }

  /**
   * {@inheritDoc}
   */
  public function getLabel() {
    return 'Item Set Group selection';
  }

  /**
   * {@inheritDoc}
   */
  public function form(PhpRenderer $view, SiteRepresentation $site, ?SitePageRepresentation $page = NULL, ?SitePageBlockRepresentation $block = NULL) {
    $form = new Form();
    $view->headLink()->appendStylesheet($view->assetUrl('css/admin.css', 'ItemSetGroup'));

    // Heading and description.
    $heading = new Text('o:block[__blockIndex__][o:data][heading]');
    $heading->setLabel($view->translate('Title'));
    if ($block) {
      $heading->setValue((string) $block->dataValue('heading', ''));
    }
    $form->add($heading);

    $desc = new HtmlTextarea('o:block[__blockIndex__][o:data][description]');
    $desc->setLabel($view->translate('Description (HTML)'));
    $desc->setAttributes(['rows' => 5]);
    if ($block) {
      $desc->setValue((string) $block->dataValue('description', ''));
    }
    $form->add($desc);

    $showTitle = new Checkbox('o:block[__blockIndex__][o:data][show_title]');
    $showTitle->setLabel($view->translate('Show title under thumbnail'));
    $showTitle->setUseHiddenElement(TRUE);
    $showTitle->setCheckedValue('1');
    $showTitle->setUncheckedValue('0');
    $showTitle->setChecked($block ? (bool) $block->dataValue('show_title', TRUE) : TRUE);
    $form->add($showTitle);

    $showDesc = new Checkbox('o:block[__blockIndex__][o:data][show_description]');
    $showDesc->setLabel($view->translate('Show description'));
    $showDesc->setUseHiddenElement(TRUE);
    $showDesc->setCheckedValue('1');
    $showDesc->setUncheckedValue('0');
    $showDesc->setChecked($block ? (bool) $block->dataValue('show_description', FALSE) : FALSE);
    $form->add($showDesc);

    $descMax = new Text('o:block[__blockIndex__][o:data][description_max]');
    $descMax->setLabel($view->translate('Max description length (0 = unlimited)'));
    $descMax->setAttributes([
      'type' => 'number',
      'min' => '0',
      'step' => '1',
      'inputmode' => 'numeric',
      'placeholder' => '0',
      'pattern' => '\\d*',
    ]);
    if ($block) {
      $descMax->setValue((string) $block->dataValue('description_max', '0'));
    }
    $form->add($descMax);

    $moreUrl = new Text('o:block[__blockIndex__][o:data][more_url]');
    $moreUrl->setLabel($view->translate('More link URL'));
    $moreText = new Text('o:block[__blockIndex__][o:data][more_text]');
    $moreText->setLabel($view->translate('More link text'));
    $moreText->setAttribute('placeholder', $view->translate('もっと見る'));
    if ($block) {
      $moreUrl->setValue((string) $block->dataValue('more_url', ''));
      $moreText->setValue((string) $block->dataValue('more_text', ''));
    }
    $form->add($moreUrl);
    $form->add($moreText);

    $existing = $block ? ($block->dataValue('entries') ?: []) : [];

    // Build item set value options once, excluding group-parent item sets
    // (those that have child item sets pointing via dcterms:isPartOf).
    $valueOptions = [];
    $valueOptions[''] = $view->translate('(none)');
    $parentIds = [];
    try {
      $propId = (int) $this->connection->fetchOne(
        "SELECT id FROM property WHERE term = 'dcterms:isPartOf'"
      );
      if ($propId) {
        $rows = $this->connection->fetchFirstColumn(
          "SELECT DISTINCT v.value_resource_id
           FROM value v
           INNER JOIN resource r ON r.id = v.resource_id
           WHERE v.property_id = ?
             AND v.value_resource_id IS NOT NULL
             AND r.resource_type = 'Omeka\\\\Entity\\\\ItemSet'",
          [$propId]
        );
        foreach ($rows as $pid) {
          $parentIds[(int) $pid] = TRUE;
        }
      }
    }
    catch (\Throwable $e) {
      $parentIds = [];
    }

    try {
      $sets = $view->api()->search('item_sets', [
        'per_page' => 500,
        'page' => 1,
        'sort_by' => 'id',
        'sort_order' => 'asc',
        'site_id' => $site->id(),
        'is_public' => 1,
      ])->getContent();
      foreach ($sets as $rep) {
        $sid = (int) $rep->id();
        if (!isset($parentIds[$sid])) {
          try {
            $valueOptions[(string) $sid] = '#' . $sid . ' ' . $rep->displayTitle();
          }
          catch (\Throwable $e) {
            $valueOptions[(string) $sid] = '#' . $sid;
          }
        }
      }
    }
    catch (\Throwable $e) {
      // Leave minimal options only.
    }
    $max = 12;
    for ($i = 0; $i < $max; $i++) {
      $setSel = new Select('o:block[__blockIndex__][o:data][entries][' . $i . '][item_set_id]');
      $setSel->setLabel($view->translate('Item set'));
      $setSel->setValueOptions($valueOptions);
      // Allow posting values outside options (e.g., legacy selections)
      // without validation error.
      if (method_exists($setSel, 'setDisableInArrayValidator')) {
        $setSel->setDisableInArrayValidator(TRUE);
      }
      if (isset($existing[$i]['item_set_id'])) {
        $setSel->setValue((string) $existing[$i]['item_set_id']);
      }
      $form->add($setSel);

      $asset = new AssetElement('o:block[__blockIndex__][o:data][entries][' . $i . '][thumb_asset]');
      $asset->setLabel($view->translate('Thumbnail asset (override)'));
      if (isset($existing[$i]['thumb_asset'])) {
        $asset->setValue((string) $existing[$i]['thumb_asset']);
      }
      $form->add($asset);

      $thumbUrl = new Text('o:block[__blockIndex__][o:data][entries][' . $i . '][thumb_url]');
      $thumbUrl->setLabel($view->translate('Thumbnail URL (fallback)'));
      if (isset($existing[$i]['thumb_url'])) {
        $thumbUrl->setValue((string) $existing[$i]['thumb_url']);
      }
      $form->add($thumbUrl);

      $childId = new Hidden('o:block[__blockIndex__][o:data][entries][' . $i . '][child_item_id]');
      if (isset($existing[$i]['child_item_id'])) {
        $childId->setValue((string) $existing[$i]['child_item_id']);
      }
      $form->add($childId);

      $childTitleEl = new Text('o:block[__blockIndex__][o:data][entries][' . $i . '][child_item_title]');
      $childTitleEl->setLabel($view->translate('Child item'));
      $childTitleEl->setAttributes(['readonly' => TRUE]);
      if (!empty($existing[$i]['child_item_id'])) {
        try {
          $rep = $view->api()->read('items', (int) $existing[$i]['child_item_id'])->getContent();
          if ($rep) {
            $childTitleEl->setValue((string) $rep->displayTitle());
          }
        }
        catch (\Throwable $e) {
          /* Ignore. */
        }
      }
      $form->add($childTitleEl);

      $pickChildBtn = new Button('o:block[__blockIndex__][o:data][entries][' . $i . '][child_item_pick_button]');
      $pickChildBtn->setLabel($view->translate('Select'));
      $pickChildBtn->setAttributes([
        'type' => 'button',
        'class' => 'button thematic-select-child-item',
        'data-sidebar-content-url' => $site->adminUrl('sidebar-item-select'),
      ]);
      $form->add($pickChildBtn);

      $clearChildBtn = new Button('o:block[__blockIndex__][o:data][entries][' . $i . '][child_item_clear_button]');
      $clearChildBtn->setLabel($view->translate('Clear'));
      $clearChildBtn->setAttributes([
        'type' => 'button',
        'class' => 'button red o-icon-undo thematic-clear-child-item',
      ]);
      $form->add($clearChildBtn);
    }

    $view->headScript()->appendScript(<<<'JS'
      (function($){
        if (window.ItemSetGroupPickerInit) return;
        window.ItemSetGroupPickerInit = true;

        function tsWriteSelection($input, id, title) {
          $input.val(id).trigger('change');
          var $fieldset = $input.closest('.ts-entry');
          var $title = $fieldset.find('input[name$="[child_item_title]"]').first();
          if ($title.length) { $title.val(title || '').trigger('change'); }
        }

        // Clear button: clear hidden id and readonly title.
        $(document).on('click', '.thematic-clear-child-item', function(e){
          e.preventDefault();
          var $fieldset = $(this).closest('.ts-entry');
          $fieldset.find('input[name$="[child_item_id]"]').val('').trigger('change');
          $fieldset.find('input[name$="[child_item_title]"]').val('').trigger('change');
          return false;
        });

        $(document).on('click', '.thematic-select-child-item', function(e){
          e.preventDefault();
          var $btn = $(this);
          var sidebar = $('#select-resource');
          var $fieldset = $btn.closest('.ts-entry');
          var $targetInput = $fieldset.find('input[name$="[child_item_id]"]').first();
          var $parentSelect = $fieldset.find('select[name$="[item_set_id]"]').first();
          var parentVal = $parentSelect.length ? ($parentSelect.val() || '') : '';
          sidebar.data('thematicTargetInput', $targetInput);
          var baseUrl = $btn.data('sidebarContentUrl');
          var openWithUrl = function(url){
            Omeka.populateSidebarContent(sidebar, url);
            Omeka.openSidebar(sidebar);
          };
          if (parentVal) {
            var apiUrl = '/api/item_sets';
            var q = {
              'property[0][property]': 'dcterms:isPartOf',
              'property[0][type]': 'res',
              'property[0][text]': String(parentVal),
              'per_page': 1000
            };
            $.getJSON(apiUrl, q)
              .done(function(data){
                var sep = baseUrl.indexOf('?') === -1 ? '?' : '&';
                if ($.isArray(data) && data.length > 0) {
                  var params = [];
                  // Include the parent set itself as well.
                  params.push('item_set_id=' + encodeURIComponent(String(parentVal)));
                  for (var i=0;i<data.length;i++) {
                    var cid = data[i] && data[i]['o:id'];
                    if (cid != null) {
                      params.push('item_set_id=' + encodeURIComponent(String(cid)));
                    }
                  }
                  openWithUrl(baseUrl + sep + params.join('&'));
                } else {
                  // No children: filter by the selected set only.
                  openWithUrl(baseUrl + sep + 'item_set_id=' + encodeURIComponent(String(parentVal)));
                }
              })
              .fail(function(){
                var sep = baseUrl.indexOf('?') === -1 ? '?' : '&';
                openWithUrl(baseUrl + sep + 'item_set_id=' + encodeURIComponent(String(parentVal)));
              });
          } else {
            openWithUrl(baseUrl);
          }
        });

        // Strong interception: capture-phase listener inside sidebar to beat other handlers.
        (function(){
          var intercept = function(ev){
            var $sidebar = $('#select-resource');
            var $input = $sidebar.data('thematicTargetInput');
            if (!$input || !$input.length) return;
            var target = ev.target;
            var resEl = target.closest ? target.closest('#select-resource .resource') : null;
            var linkEl = target.closest ? target.closest('#select-resource a.resource-link') : null;
            if (!(resEl || linkEl)) return;
            ev.preventDefault();
            if (ev.stopImmediatePropagation) ev.stopImmediatePropagation();
            ev.stopPropagation();
            var $resEl = resEl ? $(resEl) : $(linkEl).closest('.resource');
            var values = $resEl.data('resource-values') || {};
            var id = values.value_resource_id != null ? String(values.value_resource_id) : '';
            var title = values.display_title || '';
            if (!id) {
              var cb = $resEl.find('input.select-resource-checkbox').val();
              if (cb) id = String(cb);
            }
            if (id) {
              tsWriteSelection($input, id, title);
            }
            $sidebar.removeData('thematicTargetInput');
            Omeka.closeSidebar($('#select-resource'));
            Omeka.closeSidebar($('#resource-details'));
          };
          var bindCapture = function(root){
            if (!root) return;
            root.addEventListener('pointerdown', intercept, true);
            root.addEventListener('mousedown', intercept, true);
            root.addEventListener('pointerup', intercept, true);
            root.addEventListener('mouseup', intercept, true);
            root.addEventListener('click', intercept, true);
          };
          bindCapture(document.getElementById('select-resource'));
          bindCapture(document.getElementById('resource-details'));
        })();

        // When sidebar content is (re)loaded, remove details-opening behavior
        // and bind a direct-select click handler to resource links.
        $(document).on('o:sidebar-content-loaded', '#select-resource, #resource-details', function(){
          var $sidebar = $('#select-resource');
          if (!$sidebar.data('thematicTargetInput')) return;
          // Remove Omeka's details-opening hooks to avoid navigation.
          $('#select-resource .items.resource-list a.resource-link.sidebar-content,\
            #resource-details .items.resource-list a.resource-link.sidebar-content')
            .removeClass('sidebar-content')
            .removeAttr('data-sidebar-content-url')
            .removeAttr('data-sidebar-selector');
          // Also neutralize href to avoid default navigation in any edge case.
          $('#select-resource .items.resource-list a.resource-link,\
            #resource-details .items.resource-list a.resource-link')
            .attr('href', 'javascript:void(0)');
          // Bind our click to select and close.
          $('#select-resource .items.resource-list a.resource-link,\
            #resource-details .items.resource-list a.resource-link')
            .off('click.isgSelect')
            .on('click.isgSelect', function(e){
              var $input = $sidebar.data('thematicTargetInput');
              if (!$input || !$input.length) return;
              e.preventDefault();
              if (e.stopImmediatePropagation) e.stopImmediatePropagation();
              var $resEl = $(this).closest('.resource');
              var values = $resEl.data('resource-values') || {};
              var id = values.value_resource_id != null ? String(values.value_resource_id) : '';
              var title = values.display_title || '';
              if (!id) {
                var cb = $resEl.find('input.select-resource-checkbox').val();
                if (cb) id = String(cb);
              }
              if (id) {
                tsWriteSelection($input, id, title);
              }
              $sidebar.removeData('thematicTargetInput');
              Omeka.closeSidebar($('#select-resource'));
              Omeka.closeSidebar($('#resource-details'));
              return false;
            });
        });

          // Global capture-phase interception to catch any late-bound handlers
          // or keyboard activations before navigation occurs.
          (function(){
            var handler = function(e){
              try {
                var $sidebar = $('#select-resource');
                var $input = $sidebar.data('thematicTargetInput');
                if (!$input || !$input.length) return;
                // Only act for relevant links in either sidebar container.
                var isKey = (e.type === 'keydown');
                if (isKey && !(e.key === 'Enter' || e.key === ' ')) return;
                var link = e.target && e.target.closest
                  ? e.target.closest('#select-resource a.resource-link, #resource-details a.resource-link')
                  : null;
                if (!link) return;
                e.preventDefault();
                if (e.stopImmediatePropagation) e.stopImmediatePropagation();
                e.stopPropagation();
                var $resEl = $(link).closest('.resource');
                var values = $resEl.data('resource-values') || {};
                var id = values.value_resource_id != null ? String(values.value_resource_id) : '';
                var title = values.display_title || '';
                if (!id) {
                  var cb = $resEl.find('input.select-resource-checkbox').val();
                  if (cb) id = String(cb);
                }
                if (id) {
                  tsWriteSelection($input, id, title);
                }
                $sidebar.removeData('thematicTargetInput');
                Omeka.closeSidebar($('#select-resource'));
                Omeka.closeSidebar($('#resource-details'));
              } catch (ex) { /* no-op */ }
            };
            var opts = true; // capture phase
            ['click','dblclick','auxclick','contextmenu','pointerdown','pointerup','mousedown','mouseup','keydown']
              .forEach(function(t){ document.addEventListener(t, handler, opts); });
          })();

        // Fallback for the "Add selected" button: take the first checked.
        $(document).on('click', '#select-resource .select-resources-button', function(e){
          var $sidebar = $('#select-resource');
          var $input = $sidebar.data('thematicTargetInput');
          if (!$input || !$input.length) return;
          var $first = $('#select-resource .resource .select-resource-checkbox:checked').first();
          if ($first.length) {
            e.preventDefault();
            if (e.stopImmediatePropagation) e.stopImmediatePropagation();
            tsWriteSelection($input, String($first.val() || ''), '');
            $sidebar.removeData('thematicTargetInput');
            Omeka.closeSidebar($('#select-resource'));
            Omeka.closeSidebar($('#resource-details'));
            return false;
          }
        });

        // Early interception on mousedown for anchors, similar to ThematicSelection.
        $(document).on('mousedown', '#select-resource a.resource-link', function(e){
          var $sidebar = $('#select-resource');
          var $input = $sidebar.data('thematicTargetInput');
          if (!$input || !$input.length) return;
          e.preventDefault();
          if (e.stopImmediatePropagation) e.stopImmediatePropagation();
          var $resEl = $(this).closest('.resource');
          var values = $resEl.data('resource-values') || {};
          var id = values.value_resource_id != null ? String(values.value_resource_id) : '';
          var title = values.display_title || '';
          if (!id) {
            var cb = $resEl.find('input.select-resource-checkbox').val();
            if (cb) id = String(cb);
          }
          if (id) {
            tsWriteSelection($input, id, title);
          }
          $sidebar.removeData('thematicTargetInput');
          Omeka.closeSidebar($('#select-resource'));
          Omeka.closeSidebar($('#resource-details'));
          return false;
        });
      })(jQuery);
    JS);

    $html = '';
    $translate = $view->plugin('translate');
    $escape = $view->plugin('escapeHtml');
    $html .= '<fieldset class="ts-fieldset ts-global">'
      . '<legend>' . $escape($translate('Global settings')) . '</legend>'
      . '<div class="ts-group">' . $view->formRow($heading) . '</div>'
      . '<div class="ts-group">' . $view->formRow($desc) . '</div>'
      . '<div class="ts-group">' . $view->formRow($showTitle) . '</div>'
      . '<div class="ts-group">' . $view->formRow($showDesc) . '</div>'
      . '<div class="ts-group">' . $view->formRow($moreUrl) . '</div>'
      . '<div class="ts-group">' . $view->formRow($moreText) . '</div>'
      . '<div class="ts-group">' . $view->formRow($descMax) . '</div>'
      . '</fieldset>';

    for ($i = 0; $i < $max; $i++) {
      $html .= '<fieldset class="ts-fieldset ts-entry">'
        . '<legend>' . $escape(sprintf($translate('Selection %d'), $i + 1)) . '</legend>'
        . '<div class="ts-group">' . $view->formRow($form->get('o:block[__blockIndex__][o:data][entries][' . $i . '][item_set_id]')) . '</div>'
        . '<div class="ts-group">'
          . $view->formRow($form->get('o:block[__blockIndex__][o:data][entries][' . $i . '][child_item_title]')) . ' '
          . $view->formRow($form->get('o:block[__blockIndex__][o:data][entries][' . $i . '][child_item_pick_button]')) . ' '
          . $view->formRow($form->get('o:block[__blockIndex__][o:data][entries][' . $i . '][child_item_clear_button]'))
          . $view->formRow($form->get('o:block[__blockIndex__][o:data][entries][' . $i . '][child_item_id]'))
        . '</div>'
        . '<div class="ts-group">' . $view->formRow($form->get('o:block[__blockIndex__][o:data][entries][' . $i . '][thumb_asset]')) . '</div>'
        . '<div class="ts-group">' . $view->formRow($form->get('o:block[__blockIndex__][o:data][entries][' . $i . '][thumb_url]')) . '</div>'
        . '</fieldset>';
    }

    return $html;
  }

  /**
   * {@inheritDoc}
   */
  public function onHydrate(SitePageBlock $block, ErrorStore $errorStore) {
    $data = $block->getData() ?: [];

    $data['show_title'] = isset($data['show_title'])
      ? (bool) $data['show_title']
      : TRUE;
    $data['show_description'] = isset($data['show_description'])
      ? (bool) $data['show_description']
      : FALSE;
    $data['heading'] = isset($data['heading']) && is_string($data['heading'])
      ? trim($data['heading'])
      : '';
    $data['description'] = isset($data['description']) && is_string($data['description'])
      ? $data['description']
      : '';
    $data['more_url'] = isset($data['more_url']) && is_string($data['more_url'])
      ? trim($data['more_url'])
      : '';
    $data['more_text'] = isset($data['more_text']) && is_string($data['more_text'])
      ? trim($data['more_text'])
      : '';
    $data['description_max'] = isset($data['description_max'])
      ? (int) $data['description_max']
      : 0;

    $entries = [];
    if (!empty($data['entries']) && is_array($data['entries'])) {
      foreach ($data['entries'] as $row) {
        if (!is_array($row)) {
          continue;
        }
        $setId = isset($row['item_set_id']) ? (int) $row['item_set_id'] : 0;
        $assetId = isset($row['thumb_asset']) ? (int) $row['thumb_asset'] : 0;
        $thumbUrl = isset($row['thumb_url']) && is_string($row['thumb_url'])
          ? trim($row['thumb_url'])
          : '';
        $childId = isset($row['child_item_id']) ? (int) $row['child_item_id'] : 0;
        if ($setId > 0) {
          $entries[] = [
            'item_set_id' => $setId,
            'child_item_id' => $childId,
            'thumb_asset' => $assetId,
            'thumb_url' => $thumbUrl,
          ];
        }
      }
    }

    $data['entries'] = $entries;
    $block->setData($data);
  }

  /**
   * {@inheritDoc}
   */
  public function render(PhpRenderer $view, SitePageBlockRepresentation $block) {
    $api = $view->api();
    $filterLocale = (bool) $view->siteSetting('filter_locale_values');
    $lang = (string) $view->lang();
    $valueLang = $filterLocale ? [$lang, ''] : NULL;
    $translate = $view->plugin('translate');
    $view->headLink()->appendStylesheet($view->assetUrl('css/site.css', 'ItemSetGroup'));

    $showTitle = (bool) $block->dataValue('show_title', TRUE);
    $showDesc = (bool) $block->dataValue('show_description', FALSE);
    $entries = $block->dataValue('entries') ?: [];
    $maxDesc = (int) $block->dataValue('description_max', 0);

    $items = [];
    foreach ($entries as $row) {
      try {
        $thumb = '';

        if (!empty($row['thumb_asset'])) {
          try {
            $assetRep = $api->read('assets', (int) $row['thumb_asset'])->getContent();
            if ($assetRep) {
              $thumb = (string) $assetRep->assetUrl();
            }
          }
          catch (\Throwable $e) {
            $thumb = '';
          }
        }

        if (!$thumb && !empty($row['thumb_url']) && is_string($row['thumb_url'])) {
          $thumb = (string) $row['thumb_url'];
        }

        $set = $api->read('item_sets', (int) $row['item_set_id'])->getContent();

        // No group-parent-specific logic: item set groups are excluded from
        // selection candidates, so standard resolution is sufficient.
        // Non-parent or still unresolved: theme-aligned fallback.
        if (!$thumb) {
          try {
            $helper = $view->plugin('itemSetPrimaryThumb');
            if ($helper) {
              $resolved = (string) $helper($set, 800, 'url');
              if ($resolved !== '') {
                $thumb = $resolved;
              }
            }
          }
          catch (\Throwable $e) {
            /* Ignore. */
          }
        }

        if (!$thumb) {
          $thumb = (string) (
            $set->thumbnailUrl('large')
            ?: $set->thumbnailUrl('medium')
            ?: $set->thumbnailUrl('square')
          );
        }
        // Final safety net: always show some thumbnail (placeholder)
        // when unresolved.
        if ($thumb === '') {
          try {
            $thumb = (string) $view->assetUrl('img/placeholder.svg', 'ItemSetGroup');
          }
          catch (\Throwable $e) {
            $thumb = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';
          }
        }

        $title = $set->displayTitle(NULL, $valueLang);
        $desc = $set->displayDescription(NULL, $valueLang);
        if ($maxDesc > 0 && is_string($desc)) {
          $plain = trim(strip_tags($desc));
          if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($plain, 'UTF-8') > $maxDesc) {
              $plain = mb_substr($plain, 0, $maxDesc, 'UTF-8') . '…';
            }
          }
          else {
            if (strlen($plain) > $maxDesc) {
              $plain = substr($plain, 0, $maxDesc) . '…';
            }
          }
          $desc = $plain;
        }

        $childTitle = '';
        $childUrl = '';
        $linkUrl = '';

        if (!empty($row['child_item_id'])) {
          try {
            $child = $api->read('items', (int) $row['child_item_id'])->getContent();
            $childTitle = (string) $child->displayTitle(NULL, $valueLang);
            if ($child && method_exists($child, 'siteUrl')) {
              $childUrl = (string) $child->siteUrl();
            }
          }
          catch (\Throwable $e) {
            $childTitle = '';
          }
        }

        try {
          if (method_exists($set, 'siteUrl')) {
            $linkUrl = (string) $set->siteUrl();
          }
          else {
            $sid = (int) $set->id();
            $linkUrl = '/item-set/' . $sid;
          }
        }
        catch (\Throwable $e) {
          $linkUrl = '';
        }

        $items[] = [
          'type' => 'item-set',
          'resource' => $set,
          'thumb' => $thumb,
          'title' => (string) $title,
          'desc' => (string) $desc,
          'child' => $childTitle,
          'childUrl' => $childUrl,
          'url' => $linkUrl,
          'isGroupParent' => FALSE,
        ];
      }
      catch (\Throwable $e) {
        // Fallback: if the item set cannot be read in site context,
        // still render a minimal tile to avoid silent disappearance.
        $sidFallback = isset($row['item_set_id']) ? (int) $row['item_set_id'] : 0;
        if ($sidFallback > 0) {
          $titleFallback = '#' . $sidFallback;
          try {
            $t = (string) $this->connection->fetchOne('SELECT title FROM resource WHERE id = ?', [$sidFallback]);
            if ($t !== '') {
              $titleFallback = $t;
            }
          }
          catch (\Throwable $e2) {
            // Ignore.
          }
          $thumbFallback = '';
          try {
            $thumbFallback = (string) $view->assetUrl('img/placeholder.svg', 'ItemSetGroup');
          }
          catch (\Throwable $e2) {
            $thumbFallback = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';
          }
          $items[] = [
            'type' => 'item-set',
            'resource' => NULL,
            'thumb' => $thumbFallback,
            'title' => $titleFallback,
            'desc' => '',
            'child' => '',
            'childUrl' => '',
            'url' => '/item-set-group/' . $sidFallback,
            'isGroupParent' => TRUE,
          ];
        }
      }
    }

    $moreUrl = (string) $block->dataValue('more_url', '');
    $moreText = (string) $block->dataValue('more_text', '');
    if ($moreUrl !== '' && $moreText === '') {
      $isJa = (strpos(strtolower($lang), 'ja') === 0);
      $moreText = $isJa ? 'もっと見る' : (string) $translate('See more');
    }

    return $view->partial('common/block-layout/item-set-group-selection', [
      'heading' => (string) $block->dataValue('heading', ''),
      'description' => (string) $block->dataValue('description', ''),
      'moreUrl' => $moreUrl,
      'moreText' => $moreText,
      'showTitle' => $showTitle,
      'showDesc' => $showDesc,
      'items' => $items,
    ]);
  }

  /**
   * Build a thumbnail URL for a media with an IIIF-first strategy.
   *
   * Falls back to Omeka-generated thumbnails when IIIF data is unavailable.
   *
   * @param mixed $media
   *   Media representation.
   *
   * @return string
   *   URL or empty string if not resolvable.
   */
  protected function mediaThumbUrl($media): string {
    try {
      if ($media && method_exists($media, 'mediaData')) {
        $data = $media->mediaData();
        $base = isset($data['id']) ? (string) $data['id'] : (string) $media->source();
        if ($base && substr($base, -9) === 'info.json') {
          $base = substr($base, 0, -9);
        }
        if ($base) {
          return $base . '/square/800,/0/default.jpg';
        }
      }
      if ($media && method_exists($media, 'thumbnailUrl')) {
        return (string) (
          $media->thumbnailUrl('large')
          ?: $media->thumbnailUrl('medium')
          ?: $media->thumbnailUrl('square')
        );
      }
    }
    catch (\Throwable $e) {
      /* Ignore. */
    }
    return '';
  }

}
