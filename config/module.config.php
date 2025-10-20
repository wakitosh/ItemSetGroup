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
      // Register controller under generic Controllers parent.
      'ItemSetGroup\\Controller\\GroupsController' => 'Controllers',
    ],
    'allow' => [
    // Allow any role (including anonymous) to access the redirect action.
    [NULL, 'ItemSetGroup\\Controller\\GroupsController', ['redirect']],
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
        'priority' => 10000,
        'options' => [
          'route' => '/s/:site-slug/item-set-group[/:parent]',
          'constraints' => [
            'site-slug' => '[a-zA-Z0-9_-]+',
            'parent' => '[0-9]+',
          ],
          'defaults' => [
            '__SITE__' => TRUE,
            // Bypass our controller for site route to avoid ACL issues.
            // Directly dispatch to Site ItemSet browse.
            // Set groups layout flag via route param to trigger theme.
            'controller' => 'Omeka\\Controller\\Site\\ItemSet',
            'action' => 'browse',
            'groups_route' => TRUE,
          ],
        ],
      ],
      'default-item-set-group' => [
        'type' => 'Segment',
        'priority' => 10000,
        'options' => [
          'route' => '/item-set-group[/:parent]',
          'constraints' => [
            'parent' => '[0-9]+',
          ],
          'defaults' => [
            '__SITE__' => TRUE,
            // Bypass our controller even for default route.
            'controller' => 'Omeka\\Controller\\Site\\ItemSet',
            'action' => 'browse',
            'groups_route' => TRUE,
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
