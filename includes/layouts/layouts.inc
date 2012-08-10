<?php

/**
 * @file
 * Main extension file for the 'development' extension.
 */

/**
 * Implements hook_extension_EXTENSION_process_page().
 */
function omega_extension_layouts_process_layout(&$variables) {
  if (!isset($variables['theme_hook_suggestion']) && !empty($variables['page']['#omega_layout'])) {
    $registry = theme_get_registry();
    $suggestion = 'page__' . $variables['page']['#omega_layout'] . '_layout';

    // Check if the provided layout is valid.
    if (isset($registry[$suggestion]) && isset($registry[$suggestion]['layout']) && $registry[$suggestion]['layout']['supported']) {
      $variables['theme_hook_suggestion'] = $suggestion;

      // Load all the assets for the active layout.
      drupal_process_attached(array('#attached' => $registry[$suggestion]['layout']['attached']));

      // Load the callback file if it has been defined.
      if (!empty($registry[$suggestion]['layout']['callback file'])) {
        require_once $registry[$suggestion]['layout']['callback file'];
      }

      // Invoke the callback if it has been defined.
      if (!empty($registry[$suggestion]['layout']['callback'])) {
        $registry[$suggestion]['layout']['callback']($variables, $variables['page']['#omega_layout'], $registry[$suggestion]['layout']);
      }
    }
  }
}