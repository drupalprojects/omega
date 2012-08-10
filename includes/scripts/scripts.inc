<?php

/**
 * @file
 * Main extension file for the 'scripts' extension.
 */

/**
 * Extension callback for attaching enabled libraries.
 */
function omega_extension_scripts_attach_libraries($variables) {
  $registry = theme_get_registry();

  // Check if there are any enabled libraries.
  foreach ($registry['html']['libraries'] as $library => $info) {
    $variant = NULL;

    if (isset($info['settings']['variant']) && $variant = $info['settings']['variant']) {
      drupal_process_attached(array('#attached' => $info['info']['variants'][$variant]['files']));
    }
    else {
      drupal_process_attached(array('#attached' => $info['info']['files']));
    }

    if (isset($info['info']['callback'])) {
      $info['info']['callback']($library, $info['info'], $info['settings'], $variant);
    }
  }
}