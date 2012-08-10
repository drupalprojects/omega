<?php

/**
 * @file
 * Main extension file for the 'compatibility' extension.
 */

/**
 * Implements hook_extension_EXTENSION_registry_alter().
 */
function omega_extension_scripts_theme_registry_alter(&$registry) {
  if ($settings = theme_get_setting('omega_libraries')) {
    foreach (omega_theme_libraries_info() as $library => $info) {
      if (!empty($settings[$library]['status'])) {
        $registry['html']['libraries'][$library] = array(
          'info' => $info,
          'settings' => $settings[$library],
        );
      }
    }
  }

  if (!empty($registry['html']['libraries'])) {
    $registry['html']['includes'][] = drupal_get_path('theme', 'omega') . '/includes/scripts/scripts.inc';
    $registry['html']['preprocess functions'][] = 'omega_extension_scripts_attach_libraries';
  }
}