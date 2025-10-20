<?php

/**
 * @file
 * ItemSetGroup module configuration.
 */

declare(strict_types=1);

use ItemSetGroup\Site\BlockLayout\SelectionFactory as SelectionBlockFactory;
use ItemSetGroup\View\Helper\ItemSetPrimaryThumbFactory;

return [
  'acl' => [
    'resources' => [
      // Treat as a site controller so public routes work without auth.
      'ItemSetGroup\\Controller\\GroupsController' => 'Omeka\\Controller\\Site',
    ],
    'allow' => [
      // Allow anonymous/guest users to access the redirect action.
      ['guest', 'ItemSetGroup\\Controller\\GroupsController', ['redirect']],
    ],
  ],
  'view_manager' => [
    'template_path_stack' => [
      __DIR__ . '/../view',
    ],
  ],
  'controllers' => [
    'invokables' => [
      'ItemSetGroup\\Controller\\GroupsController' => 'ItemSetGroup\\Controller\\GroupsController',
    ],
  ],
  'router' => [
    'routes' => [
      'site-item-set-group' => [
        'type' => 'Segment',
        'options' => [
          'route' => '/s/:site-slug/item-set-group[/:parent]',
          'constraints' => [
            'site-slug' => '[a-zA-Z0-9_-]+',
            'parent' => '[0-9]+',
          ],
          'defaults' => [
            '__SITE__' => TRUE,
            'controller' => 'ItemSetGroup\\Controller\\GroupsController',
            'action' => 'redirect',
          ],
        ],
      ],
      'default-item-set-group' => [
        'type' => 'Segment',
        'options' => [
          'route' => '/item-set-group[/:parent]',
          'constraints' => [
            'parent' => '[0-9]+',
          ],
          'defaults' => [
            '__SITE__' => TRUE,
            'controller' => 'ItemSetGroup\\Controller\\GroupsController',
            'action' => 'redirect',
          ],
        ],
      ],
    ],
  ],
  'block_layouts' => [
    'factories' => [
      'item_set_group_selection' => SelectionBlockFactory::class,
    ],
  ],
  'view_helpers' => [
    'factories' => [
      'itemSetPrimaryThumb' => ItemSetPrimaryThumbFactory::class,
    ],
  ],
];
