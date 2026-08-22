<?php

namespace ItemSetGroup;

use Laminas\Mvc\MvcEvent;
use Laminas\Validator\ValidatorChain;
use Laminas\View\Model\ViewModel;
use Omeka\Api\Adapter\ItemSetAdapter;
use Omeka\Form\ResourceForm;
use Omeka\Module\AbstractModule;

/**
 * ItemSetGroup module bootstrap.
 */
class Module extends AbstractModule {

  /**
   * {@inheritDoc}
   */
  public function getConfig(): array {
    return include __DIR__ . '/config/module.config.php';
  }

  /**
   * {@inheritDoc}
   */
  public function getAutoloaderConfig(): array {
    return [
      'Laminas\\Loader\\StandardAutoloader' => [
        'namespaces' => [
          __NAMESPACE__ => __DIR__ . '/src',
        ],
      ],
    ];
  }

  /**
   * {@inheritDoc}
   *
   * Integrate representative item/media admin UI and persistence.
   */
  public function onBootstrap(MvcEvent $event): void {
    $application = $event->getApplication();
    $eventManager = $application->getEventManager();
    $shared = $eventManager->getSharedManager();
    $services = $application->getServiceManager();
    // Logger is optional; not used currently.
    // Admin form: add elements for representative item/media.
    $shared->attach(ResourceForm::class, 'form.add_elements', function ($e) use ($services) {
      $form = $e->getTarget();
      $fidAttr = $form->getAttribute('id');
      $formId = $fidAttr ? (string) $fidAttr : '';
      $isItemSet = in_array($formId, ['edit-item-set', 'add-item-set'], TRUE);
      $resource = method_exists($form, 'getResource') ? $form->getResource() : NULL;
      if (!$isItemSet && $resource && method_exists($resource, 'resourceName')) {
        $isItemSet = ($resource->resourceName() === 'item_sets');
      }
      if (!$isItemSet || $form->has('primary_item_id')) {
        return;
      }

      // Ensure table exists and preload saved values (if any).
      $valueExisting = '';
      $valueOptions = ['' => '（未選択）'];
      $valueExistingMedia = '';
      if ($resource) {
        try {
          $conn = $services->get('Omeka\\Connection');
          try {
            $conn->fetchOne('SELECT 1 FROM item_set_primary_item LIMIT 1');
          }
          catch (\Throwable $missing) {
            try {
              $conn->executeStatement('CREATE TABLE IF NOT EXISTS item_set_primary_item (
                id INT AUTO_INCREMENT PRIMARY KEY,
                item_set_id INT NOT NULL UNIQUE,
                primary_item_id INT NOT NULL,
                primary_media_id INT NULL,
                INDEX (primary_item_id),
                INDEX (primary_media_id)
              ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            }
            catch (\Throwable $createFail) {
              // Ignore.
            }
          }
          try {
            $conn->fetchOne('SELECT primary_media_id FROM item_set_primary_item LIMIT 1');
          }
          catch (\Throwable $colMissing) {
            try {
              $conn->executeStatement('ALTER TABLE item_set_primary_item ADD COLUMN primary_media_id INT NULL');
              try {
                $conn->executeStatement('CREATE INDEX idx_item_set_primary_media_id ON item_set_primary_item (primary_media_id)');
              }
              catch (\Throwable $ix) {
                // Ignore index creation failure.
              }
            }
            catch (\Throwable $alterFail) {
              // Ignore.
            }
          }
          try {
            $val = $conn->fetchOne('SELECT primary_item_id FROM item_set_primary_item WHERE item_set_id = ?', [$resource->id()]);
            if ($val) {
              $valueExisting = (string) $val;
            }
            try {
              $valm = $conn->fetchOne('SELECT primary_media_id FROM item_set_primary_item WHERE item_set_id = ?', [$resource->id()]);
              if ($valm) {
                $valueExistingMedia = (string) $valm;
              }
            }
            catch (\Throwable $ignoreVal2) {
              // Ignore.
            }
          }
          catch (\Throwable $ignoreVal) {
            // Ignore.
          }
          if ($valueExisting !== '') {
            try {
              $api = $services->get('Omeka\\ApiManager');
              $it = $api->read('items', (int) $valueExisting)->getContent();
              if ($it) {
                $title = method_exists($it, 'displayTitle') ? $it->displayTitle() : ('Item ' . $it->id());
                $valueOptions[$valueExisting] = '#' . $it->id() . ' ' . $title;
              }
            }
            catch (\Throwable $e2) {
              $valueOptions[$valueExisting] = '#' . $valueExisting;
            }
          }
        }
        catch (\Throwable $t) {
          // Ignore preload failures.
        }
      }

      $rsOptions = [
        'label' => '代表アイテム (サムネイル元)',
        'info' => 'このセットに属するアイテムから代表アイテムを選択します。',
        'resource_value_options' => [
          'resource' => 'items',
          'query' => $resource ? [
            'item_set_id' => $resource->id(),
            'per_page' => 200,
            'page' => 1,
            'sort_by' => 'id',
            'sort_order' => 'asc',
          ] : [],
          'option_text_callback' => function ($rep) {
            try {
              $title = method_exists($rep, 'displayTitle') ? $rep->displayTitle() : ('Item ' . $rep->id());
              return '#' . $rep->id() . ' ' . $title;
            }
            catch (\Throwable $e) {
              return '#' . $rep->id();
            }
          },
        ],
        'prepend_value_options' => $valueOptions,
      ];
      $form->add([
        'name' => 'primary_item_id',
        'type' => 'Omeka\\Form\\Element\\ResourceSelect',
        'options' => $rsOptions + [
          'disable_inarray_validator' => TRUE,
        ],
        'attributes' => [
          'required' => FALSE,
          'value' => $valueExisting,
        ],
      ]);
      $form->add([
        'name' => 'primary_media_id',
        'type' => 'Hidden',
        'attributes' => [
          'value' => $valueExistingMedia,
        ],
      ]);
    }, 100);

    // Input filters: make fields optional and tolerant.
    $shared->attach(ResourceForm::class, 'form.add_input_filters', function ($e) {
      $form = $e->getTarget();
      $formId = (string) $form->getAttribute('id');
      $isItemSet = in_array($formId, ['edit-item-set', 'add-item-set'], TRUE);
      $resource = method_exists($form, 'getResource') ? $form->getResource() : NULL;
      if (!$isItemSet && $resource && method_exists($resource, 'resourceName')) {
        $isItemSet = ($resource->resourceName() === 'item_sets');
      }
      if (!$isItemSet) {
        return;
      }
      $inputFilter = $e->getParam('inputFilter');
      if (!$inputFilter) {
        return;
      }
      if ($inputFilter->has('primary_item_id')) {
        $in = $inputFilter->get('primary_item_id');
        if (method_exists($in, 'setRequired')) {
          $in->setRequired(FALSE);
        }
        if (method_exists($in, 'setAllowEmpty')) {
          $in->setAllowEmpty(TRUE);
        }
        if (method_exists($in, 'setContinueIfEmpty')) {
          $in->setContinueIfEmpty(TRUE);
        }
        if (method_exists($in, 'setValidators')) {
          $in->setValidators([]);
        }
        elseif (method_exists($in, 'setValidatorChain')) {
          $in->setValidatorChain(new ValidatorChain());
        }
      }
      else {
        $inputFilter->add([
          'name' => 'primary_item_id',
          'required' => FALSE,
          'allow_empty' => TRUE,
          'continue_if_empty' => TRUE,
          'filters' => [
            ['name' => 'ToInt'],
          ],
          'validators' => [],
        ]);
      }
      if ($inputFilter->has('primary_media_id')) {
        $inm = $inputFilter->get('primary_media_id');
        if (method_exists($inm, 'setRequired')) {
          $inm->setRequired(FALSE);
        }
        if (method_exists($inm, 'setAllowEmpty')) {
          $inm->setAllowEmpty(TRUE);
        }
        if (method_exists($inm, 'setContinueIfEmpty')) {
          $inm->setContinueIfEmpty(TRUE);
        }
      }
      else {
        $inputFilter->add([
          'name' => 'primary_media_id',
          'required' => FALSE,
          'allow_empty' => TRUE,
          'continue_if_empty' => TRUE,
          'filters' => [
            ['name' => 'ToInt'],
          ],
          'validators' => [],
        ]);
      }
    }, 100);

    // Persist representative item/media on create/update via API adapters.
    $persist = function (int $setId, int $pid, int $mid) use ($services) {
      try {
        $conn = $services->get('Omeka\\Connection');
        try {
          $conn->fetchOne('SELECT 1 FROM item_set_primary_item LIMIT 1');
        }
        catch (\Throwable $missing) {
          try {
            $conn->executeStatement('CREATE TABLE IF NOT EXISTS item_set_primary_item (
              id INT AUTO_INCREMENT PRIMARY KEY,
              item_set_id INT NOT NULL UNIQUE,
              primary_item_id INT NOT NULL,
              primary_media_id INT NULL,
              INDEX (primary_item_id),
              INDEX (primary_media_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
          }
          catch (\Throwable $createFail) {
            // Ignore.
          }
        }
        try {
          $conn->fetchOne('SELECT primary_media_id FROM item_set_primary_item LIMIT 1');
        }
        catch (\Throwable $colMissing) {
          try {
            $conn->executeStatement('ALTER TABLE item_set_primary_item ADD COLUMN primary_media_id INT NULL');
            try {
              $conn->executeStatement('CREATE INDEX idx_item_set_primary_media_id ON item_set_primary_item (primary_media_id)');
            }
            catch (\Throwable $ix) {
              // Ignore.
            }
          }
          catch (\Throwable $alterFail) {
            // Ignore.
          }
        }
        if ($pid > 0) {
          $ok = (int) $conn->fetchOne(
            'SELECT 1 FROM item_item_set WHERE item_set_id = ? AND item_id = ?',
            [$setId, $pid]
          );
          if (!$ok) {
            return;
          }
          if ($mid > 0) {
            $mok = (int) $conn->fetchOne('SELECT 1 FROM media WHERE id = ? AND item_id = ?', [$mid, $pid]);
            if (!$mok) {
              $mid = 0;
            }
          }
          $conn->executeStatement(
            'INSERT INTO item_set_primary_item (item_set_id, primary_item_id, primary_media_id) VALUES (?, ?, ?) '
            . 'ON DUPLICATE KEY UPDATE primary_item_id = VALUES(primary_item_id), primary_media_id = VALUES(primary_media_id)',
            [$setId, $pid, $mid > 0 ? $mid : NULL]
          );
        }
      }
      catch (\Throwable $t) {
        // Ignore persist errors.
      }
    };

    $shared->attach(ItemSetAdapter::class, 'api.create.post', function ($e) use ($services, $persist) {
      $request = $e->getParam('request');
      $data = $request->getContent();
      $pid = isset($data['primary_item_id']) ? (int) $data['primary_item_id'] : 0;
      $mid = isset($data['primary_media_id']) ? (int) $data['primary_media_id'] : 0;
      $entity = $e->getParam('response')->getContent();
      $itemSetId = method_exists($entity, 'getId') ? (int) $entity->getId() : (method_exists($entity, 'id') ? (int) $entity->id() : 0);
      if ($itemSetId && $pid > 0) {
        $persist($itemSetId, $pid, $mid);
      }
      if ($itemSetId) {
        $this->assignThumbnailFromPrimary($itemSetId, $services);
      }
    }, 60);

    $shared->attach(ItemSetAdapter::class, 'api.update.post', function ($e) use ($services, $persist) {
      $request = $e->getParam('request');
      $data = $request->getContent();
      $pid = isset($data['primary_item_id']) ? (int) $data['primary_item_id'] : 0;
      $mid = isset($data['primary_media_id']) ? (int) $data['primary_media_id'] : 0;
      $entity = $e->getParam('response')->getContent();
      $itemSetId = method_exists($entity, 'getId') ? (int) $entity->getId() : (method_exists($entity, 'id') ? (int) $entity->id() : 0);
      if (!$itemSetId) {
        return;
      }
      // If primary_item_id is present and empty (clear request), delete link.
      if (array_key_exists('primary_item_id', (array) $data) && $pid <= 0) {
        try {
          $conn = $services->get('Omeka\\Connection');
          $conn->executeStatement('DELETE FROM item_set_primary_item WHERE item_set_id = ?', [$itemSetId]);
        }
        catch (\Throwable $t) {
          // Ignore.
        }
        return;
      }
      // Update or clear media when an existing primary item remains.
      if ($pid > 0 || array_key_exists('primary_media_id', (array) $data)) {
        if ($pid > 0) {
          $persist($itemSetId, $pid, $mid);
        }
        else {
          try {
            $conn = $services->get('Omeka\\Connection');
            $existingPid = (int) $conn->fetchOne('SELECT primary_item_id FROM item_set_primary_item WHERE item_set_id = ?', [$itemSetId]);
            if ($existingPid > 0) {
              $persist($itemSetId, $existingPid, $mid);
            }
          }
          catch (\Throwable $t) {
            // Ignore.
          }
        }
        $this->assignThumbnailFromPrimary($itemSetId, $services);
      }
    }, 60);

    // Fallback persist on admin edit POST (when API update is bypassed).
    $eventManager->attach(MvcEvent::EVENT_DISPATCH, function ($e) use ($services, $persist) {
      $rm = $e->getRouteMatch();
      $req = $e->getRequest();
      if (!$rm || !$req || !$req->isPost()) {
        return;
      }
      $controller = (string) $rm->getParam('controller');
      $action = (string) $rm->getParam('action');
      if ($controller !== 'Omeka\\Controller\\Admin\\ItemSet' || $action !== 'edit') {
        return;
      }
      try {
        $itemSetId = (int) $rm->getParam('id');
        if ($itemSetId <= 0) {
          return;
        }
        $post = $req->getPost();
        $pid = (int) ($post['primary_item_id'] ?? 0);
        $mid = (int) ($post['primary_media_id'] ?? 0);
        // If primary_item_id field is present and empty, clear DB link.
        if (array_key_exists('primary_item_id', $post->toArray()) && $pid <= 0) {
          try {
            $conn = $services->get('Omeka\\Connection');
            $conn->executeStatement('DELETE FROM item_set_primary_item WHERE item_set_id = ?', [$itemSetId]);
          }
          catch (\Throwable $t) {
            // Ignore.
          }
          return;
        }
        if ($pid > 0 || array_key_exists('primary_media_id', $post->toArray())) {
          if ($pid <= 0) {
            try {
              $conn = $services->get('Omeka\\Connection');
              $existingPid = (int) $conn->fetchOne('SELECT primary_item_id FROM item_set_primary_item WHERE item_set_id = ?', [$itemSetId]);
              if ($existingPid > 0) {
                $pid = $existingPid;
              }
            }
            catch (\Throwable $t) {
              // Ignore.
            }
          }
          if ($pid > 0) {
            $persist($itemSetId, $pid, $mid);
            $this->assignThumbnailFromPrimary($itemSetId, $services);
          }
        }
      }
      catch (\Throwable $t) {
        // Ignore.
      }
    }, 5);

    // Ensure fields are visibly rendered on the Advanced tab (edit).
    $shared->attach('Omeka\\Controller\\Admin\\ItemSet', 'view.edit.form.advanced', function ($e) use ($services) { // phpcs:ignore
      $vars = $e->getParams();
      if (empty($vars['form'])) {
        return;
      }
      $form = $vars['form'];
      // Fallback: add elements if missing for any reason.
      if (!$form->has('primary_item_id')) {
        $resource = method_exists($form, 'getResource') ? $form->getResource() : NULL;
        $valueExisting = '';
        $valueOptions = ['' => '（未選択）'];
        if ($resource) {
          try {
            $conn = $services->get('Omeka\\Connection');
            $val = $conn->fetchOne('SELECT primary_item_id FROM item_set_primary_item WHERE item_set_id = ?', [$resource->id()]);
            if ($val) {
              $valueExisting = (string) $val;
            }
          }
          catch (\Throwable $t) {
            // Ignore.
          }
          if ($valueExisting !== '') {
            try {
              $api = $services->get('Omeka\\ApiManager');
              $it = $api->read('items', (int) $valueExisting)->getContent();
              $title = method_exists($it, 'displayTitle') ? $it->displayTitle() : ('Item ' . $it->id());
              $valueOptions[$valueExisting] = '#' . $it->id() . ' ' . $title;
            }
            catch (\Throwable $t) {
              $valueOptions[$valueExisting] = '#' . $valueExisting;
            }
          }
        }
        $form->add([
          'name' => 'primary_item_id',
          'type' => 'Omeka\\Form\\Element\\ResourceSelect',
          'options' => [
            'label' => '代表アイテム (サムネイル元)',
            'resource_value_options' => [
              'resource' => 'items',
              'query' => $resource ? [
                'item_set_id' => $resource->id(),
                'per_page' => 200,
                'sort_by' => 'id',
                'sort_order' => 'asc',
              ] : [],
              'option_text_callback' => function ($rep) {
                try {
                  $t = method_exists($rep, 'displayTitle') ? $rep->displayTitle() : ('Item ' . $rep->id());
                  return '#' . $rep->id() . ' ' . $t;
                }
                catch (\Throwable $e) {
                  return '#' . $rep->id();
                }
              },
            ],
            'prepend_value_options' => $valueOptions,
            'disable_inarray_validator' => TRUE,
          ],
          'attributes' => [
            'required' => FALSE,
            'value' => $valueExisting,
          ],
        ]);
      }
      echo $e->getTarget()->formRow($form->get('primary_item_id'));
      // Clear button for primary item.
      echo '<button type="button" id="clear-primary-item" class="button">代表アイテムをクリア</button>';
      if (!$form->has('primary_media_id')) {
        $form->add([
          'name' => 'primary_media_id',
          'type' => 'Hidden',
          'attributes' => [
            'value' => '',
          ],
        ]);
      }
      if ($form->has('primary_media_id')) {
        $mediaIdVal = (string) $form->get('primary_media_id')->getValue();
        echo $e->getTarget()->formRow($form->get('primary_media_id'));
        // phpcs:disable
        echo '<div id="primary-media-picker" class="field">'
          . '<div class="field-meta">'
          . '<label>代表メディア</label>'
          . '<div class="inputs">'
          . '<div class="value">'
          . '<div class="media-list" data-selected="' . htmlspecialchars($mediaIdVal, ENT_QUOTES) . '"></div>'
          . '<button type="button" id="clear-primary-media" class="button">代表メディアをクリア</button>'
          . '<div class="hint">アイテムを選択するとメディアが表示されます。</div>'
          . '</div></div></div></div>';
        echo <<<'EOT'
<script>(function(){
var sel=document.querySelector("select[name=primary_item_id]");
var hidden=document.querySelector("input[name=primary_media_id]");
var box=document.querySelector("#primary-media-picker .media-list");
var btnClearItem=document.getElementById('clear-primary-item');
var btnClearMedia=document.getElementById('clear-primary-media');
if(!sel||!hidden||!box) return;
function renderItem(itemId){
  box.innerHTML = '';
  if(!itemId){ return; }
  var selected = box.getAttribute("data-selected") || hidden.value || '';
  fetch("/api/media?item_id="+encodeURIComponent(itemId)+"&per_page=100&sort_by=position&sort_order=asc", {credentials:'same-origin'})
    .then(function(r){return r.json();})
    .then(function(json){
      var data = Array.isArray(json) ? json : (json["@graph"]||[]);
      if(!data || !data.length){ box.innerHTML = '<div>メディアがありません。</div>'; return; }
      var html='';
      data.forEach(function(m){
        try {
          var id = m['o:id'] || m['id'] || '';
          var thumb='';
          var tu = m['o:thumbnail_urls']||m['thumbnail_display_urls']||{};
          thumb = tu['square']||tu['medium']||tu['large']||'';
          if(!thumb){ thumb = m['o:original_url'] || ''; }
          if(!thumb){ var src = m['o:source'] || m['source'] || ''; if(src && /\/info\.json/.test(src)){ var base = src.replace(/\/info\.json.*/, ''); thumb = base + '/square/128,/0/default.jpg'; } }
          html += '<label style="display:inline-block;margin:4px;vertical-align:top;">'
            + '<input type="radio" name="__primary_media_choice" value="'+id+'" '+ (String(id)===String(selected)?'checked':'') +' />'
            + (thumb?('<br><img src="'+thumb+'" alt="" style="width:64px;height:64px;object-fit:cover;border:1px solid #ccc;"/>'):'')
            + '<br>#'+id
            + '</label>';
        } catch(e){}
      });
      box.innerHTML=html;
      var radios = box.querySelectorAll('input[name="__primary_media_choice"]');
      radios.forEach(function(r){ r.addEventListener('change', function(){ hidden.value = this.value; }); });
    })
    .catch(function(){ box.innerHTML = '<div>メディアの取得に失敗しました。</div>'; });
}
sel.addEventListener('change', function(){ hidden.value=''; renderItem(this.value); });
if(sel.value){ renderItem(sel.value); }
if(btnClearItem){ btnClearItem.addEventListener('click', function(){ sel.value=''; hidden.value=''; box.innerHTML=''; box.setAttribute('data-selected',''); }); }
if(btnClearMedia){ btnClearMedia.addEventListener('click', function(){ hidden.value=''; var r=box.querySelectorAll('input[name="__primary_media_choice"]'); r.forEach(function(x){ x.checked=false; }); box.setAttribute('data-selected',''); }); }
})();</script>
EOT;
        // phpcs:enable
      }
    }, 50);

    // Ensure fields are visibly rendered on the Advanced tab (add).
    $shared->attach('Omeka\\Controller\\Admin\\ItemSet', 'view.add.form.advanced', function ($e) use ($services) { // phpcs:ignore
      $vars = $e->getParams();
      if (empty($vars['form'])) {
        return;
      }
      $form = $vars['form'];
      // Fallback: add elements if missing for any reason.
      if (!$form->has('primary_item_id')) {
        $form->add([
          'name' => 'primary_item_id',
          'type' => 'Omeka\\Form\\Element\\ResourceSelect',
          'options' => [
            'label' => '代表アイテム (サムネイル元)',
            'resource_value_options' => [
              'resource' => 'items',
              'query' => ['per_page' => 200],
              'option_text_callback' => function ($rep) {
                try {
                  $t = method_exists($rep, 'displayTitle') ? $rep->displayTitle() : ('Item ' . $rep->id());
                  return '#' . $rep->id() . ' ' . $t;
                }
                catch (\Throwable $e) {
                  return '#' . $rep->id();
                }
              },
            ],
            'prepend_value_options' => ['' => '（未選択）'],
            'disable_inarray_validator' => TRUE,
          ],
          'attributes' => [
            'required' => FALSE,
            'value' => '',
          ],
        ]);
      }
      echo $e->getTarget()->formRow($form->get('primary_item_id'));
      echo '<button type="button" id="clear-primary-item" class="button">代表アイテムをクリア</button>';
      if (!$form->has('primary_media_id')) {
        $form->add([
          'name' => 'primary_media_id',
          'type' => 'Hidden',
          'attributes' => [
            'value' => '',
          ],
        ]);
      }
      if ($form->has('primary_media_id')) {
        echo $e->getTarget()->formRow($form->get('primary_media_id'));
        // phpcs:disable
        echo '<div id="primary-media-picker" class="field">'
          . '<div class="field-meta">'
          . '<label>代表メディア</label>'
          . '<div class="inputs">'
          . '<div class="value">'
          . '<div class="media-list"></div>'
          . '<button type="button" id="clear-primary-media" class="button">代表メディアをクリア</button>'
          . '<div class="hint">保存後にアイテムを追加すると選択できます。</div>'
          . '</div></div></div></div>';
        echo <<<'EOT'
<script>(function(){
var sel=document.querySelector("select[name=primary_item_id]");
var hidden=document.querySelector("input[name=primary_media_id]");
var box=document.querySelector("#primary-media-picker .media-list");
var btnClearItem=document.getElementById('clear-primary-item');
var btnClearMedia=document.getElementById('clear-primary-media');
if(!sel||!hidden||!box) return;
function renderItem(itemId){
  box.innerHTML = '';
  if(!itemId){ return; }
  fetch("/api/media?item_id="+encodeURIComponent(itemId)+"&per_page=100&sort_by=position&sort_order=asc", {credentials:'same-origin'})
    .then(function(r){return r.json();})
    .then(function(json){
      var data = Array.isArray(json) ? json : (json["@graph"]||[]);
      if(!data || !data.length){ box.innerHTML = '<div>メディアがありません。</div>'; return; }
      var html='';
      data.forEach(function(m){
        try {
          var id = m['o:id'] || m['id'] || '';
          var thumb='';
          var tu = m['o:thumbnail_urls']||m['thumbnail_display_urls']||{};
          thumb = tu['square']||tu['medium']||tu['large']||'';
          if(!thumb){ thumb = m['o:original_url'] || ''; }
          if(!thumb){ var src = m['o:source'] || m['source'] || ''; if(src && /\/info\.json/.test(src)){ var base = src.replace(/\/info\.json.*/, ''); thumb = base + '/square/128,/0/default.jpg'; } }
          html += '<label style="display:inline-block;margin:4px;vertical-align:top;">'
            + '<input type="radio" name="__primary_media_choice" value="'+id+'" />'
            + (thumb?('<br><img src="'+thumb+'" alt="" style="width:64px;height:64px;object-fit:cover;border:1px solid #ccc;"/>'):'')
            + '<br>#'+id
            + '</label>';
        } catch(e){}
      });
      box.innerHTML=html;
      var radios = box.querySelectorAll('input[name="__primary_media_choice"]');
      radios.forEach(function(r){ r.addEventListener('change', function(){ hidden.value = this.value; }); });
    })
    .catch(function(){ box.innerHTML = '<div>メディアの取得に失敗しました。</div>'; });
}
sel.addEventListener('change', function(){ hidden.value=''; renderItem(this.value); });
if(btnClearItem){ btnClearItem.addEventListener('click', function(){ sel.value=''; hidden.value=''; box.innerHTML=''; }); }
if(btnClearMedia){ btnClearMedia.addEventListener('click', function(){ hidden.value=''; var r=box.querySelectorAll('input[name="__primary_media_choice"]'); r.forEach(function(x){ x.checked=false; }); }); }
})();</script>
EOT;
        // phpcs:enable
      }
    }, 50);

    // Minimal route match logging for ItemSetGroup only (to verify routing).
    try {
      $logger = $services->has('Omeka\\Logger') ? $services->get('Omeka\\Logger') : NULL;
      $eventManager->attach(MvcEvent::EVENT_ROUTE, function (MvcEvent $ev) use ($logger) {
        try {
          $match = $ev->getRouteMatch();
          if (!$match) {
            return;
          }
          $name = (string) $match->getMatchedRouteName();
          if (strpos($name, 'item-set-group') !== FALSE) {
            if ($logger && method_exists($logger, 'info')) {
              $logger->info('ItemSetGroup route matched: ' . $name);
            }
          }
        }
        catch (\Throwable $ignore) {
          // Ignore logging failures.
        }
      }, 100);

      // When routed via groups_route to Site ItemSet browse, force an
      // alternate template that we provide in this module. A theme can
      // still override it by providing the same template path.
      $shared->attach('Omeka\\Controller\\Site\\ItemSet', MvcEvent::EVENT_DISPATCH, function (MvcEvent $ev) {
        try {
          $rm = $ev->getRouteMatch();
          if (!$rm) {
            return;
          }
          $action = (string) $rm->getParam('action');
          if ($action !== 'browse') {
            return;
          }
          $flag = (bool) $rm->getParam('groups_route');
          if (!$flag) {
            // Fallback: also honor explicit layout=groups query.
            $req = $ev->getRequest();
            if (method_exists($req, 'getQuery')) {
              $flag = ((string) $req->getQuery('layout', '')) === 'groups';
            }
          }
          if (!$flag) {
            return;
          }
          $result = $ev->getResult();
          if ($result instanceof ViewModel) {
            $result->setTemplate('omeka/site/item-set/browse-groups');
          }
        }
        catch (\Throwable $e) {
          // Ignore template override failures.
        }
      }, -100);
    }
    catch (\Throwable $ignore) {
      // Ignore logging setup failures.
    }
  }

  /**
   * Install: create custom table.
   */
  public function install($services): void { // phpcs:ignore
    $conn = $services->get('Omeka\\Connection');
    $sql = 'CREATE TABLE IF NOT EXISTS item_set_primary_item (
      id INT AUTO_INCREMENT PRIMARY KEY,
      item_set_id INT NOT NULL UNIQUE,
      primary_item_id INT NOT NULL,
      primary_media_id INT NULL,
      INDEX (primary_item_id),
      INDEX (primary_media_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    $conn->executeStatement($sql);
  }

  /**
   * Uninstall: drop table.
   */
  public function uninstall($services): void { // phpcs:ignore
    $conn = $services->get('Omeka\\Connection');
    try {
      $conn->executeStatement('DROP TABLE IF EXISTS item_set_primary_item');
    }
    catch (\Throwable $t) {
      // Ignore drop errors.
    }
  }

  /**
   * Assign thumbnail from primary item if available and none set yet.
   */
  protected function assignThumbnailFromPrimary($itemSetOrId, $services): void { // phpcs:ignore
    static $reenter = FALSE;
    if ($reenter) {
      return;
    }
    try {
      $api = $services->get('Omeka\\ApiManager');
      $itemSetId = 0;
      if (is_int($itemSetOrId)) {
        $itemSetId = $itemSetOrId;
      }
      elseif (is_object($itemSetOrId)) {
        if (method_exists($itemSetOrId, 'getId')) {
          $itemSetId = (int) $itemSetOrId->getId();
        }
        elseif (method_exists($itemSetOrId, 'id')) {
          $itemSetId = (int) $itemSetOrId->id();
        }
      }
      if ($itemSetId <= 0) {
        return;
      }
      $itemSet = $api->read('item_sets', $itemSetId)->getContent();
      if (!$itemSet || $itemSet->thumbnail()) {
        return;
      }
      $conn = $services->get('Omeka\\Connection');
      $pid = (int) $conn->fetchOne('SELECT primary_item_id FROM item_set_primary_item WHERE item_set_id = ?', [$itemSetId]);
      $mid = (int) $conn->fetchOne('SELECT primary_media_id FROM item_set_primary_item WHERE item_set_id = ?', [$itemSetId]);
      if ($pid <= 0) {
        return;
      }
      $media = NULL;
      if ($mid > 0) {
        try {
          $media = $api->read('media', $mid)->getContent();
        }
        catch (\Throwable $e) {
          $media = NULL;
        }
      }
      if (!$media) {
        $item = $api->read('items', $pid)->getContent();
        if (!$item) {
          return;
        }
        $media = $item->primaryMedia();
      }
      if (!$media || !$media->thumbnail()) {
        return;
      }
      $thumb = $media->thumbnail();
      $reenter = TRUE;
      $api->update('item_sets', $itemSetId, [
        'o:thumbnail' => ['o:id' => $thumb->id()],
      ], [], ['isPartial' => TRUE]);
      $reenter = FALSE;
    }
    catch (\Throwable $t) {
      $reenter = FALSE;
    }
  }

}
