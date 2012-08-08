<?php

/**
 * @file
 * Main extension file for the 'development' extension.
 */

/**
 * Implements hook_extension_EXTENSION_registry_alter().
 */
function omega_extension_layouts_theme_registry_alter(&$registry) {
  $registry['page']['includes'][] = drupal_get_path('theme', 'omega') . '/includes/layouts/layouts.inc';
  $registry['page']['process functions'][] = 'omega_extension_layouts_process_layout';
}