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

  // Add a separate pre-process function just for loading the layout from the
  // theme settings. This way subthemes can change the layout before it gets
  // turned into a theme hook suggestion.
  array_unshift($registry['page']['preprocess functions'], 'omega_extension_layouts_init_layout');
}

/**
 * Implements hook_extension_EXTENSION_preprocess_page().
 */
function omega_extension_layouts_init_layout(&$variables) {
  // Load the default layout from the theme settings.
  if (!isset($variables['omega layout'])) {
    $variables['omega layout'] = theme_get_setting('omega_layout');
  }
}

/**
 * Implements hook_extension_EXTENSION_process_page().
 */
function omega_extension_layouts_process_layout(&$variables) {
  if (!empty($variables['omega layout'])) {
    $registry = theme_get_registry();
    $suggestion = 'page__' . $variables['omega layout'] . '_layout';

    // Check if the provided layout is valid.
    if (isset($registry[$suggestion]) && isset($registry[$suggestion]['layout'])) {
      $variables['theme_hook_suggestion'] = $suggestion;

      // Load all the assets for the active layout.
      drupal_process_attached(array('#attached' => $registry[$suggestion]['layout']['attached']));
    }
  }
}