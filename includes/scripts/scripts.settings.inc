<?php

/**
 * @file
 * Contains the theme settings form elements for the development extension.
 */

include_once dirname(__FILE__) . '/scripts.inc';

/**
 * Implements hook_extension_EXTENSION_theme_settings_form_alter().
 */
function omega_extension_scripts_theme_settings_form_alter($element, &$form, $form_state) {
  $element['omega_js_footer'] = array(
    '#type' => 'checkbox',
    '#title' => t('Output JavaScript in the footer'),
    '#description' => t('Outputs all JavaScript files and settings in the footer instead of placing them in the header (default).'),
    '#default_value' => theme_get_setting('omega_js_footer'),
  );

  $element['omega_js_exclude'] = array(
    '#type' => 'textarea',
    '#title' => t('Exclude JavaScript files'),
    '#description' => t("Enter one file per line. The '*' character is a wildcard. Each path is relative to a module, theme, profile, theme engine or the 'misc' folder."),
    // The paths are stored in an array.
    '#default_value' => implode("\n", (array) theme_get_setting('omega_js_exclude')),
  );

  // We need a custom submit handler to store the CSS and JS paths as arrays.
  $form['#submit'][] = 'omega_extension_scripts_theme_settings_form_submit';

  return $element;
}

/**
 * Form submit handler for the css theme settings extension.
 */
function omega_extension_scripts_theme_settings_form_submit($form, &$form_state) {
  $values = $form_state['values'];
  // Explode and trim the values for the JavaScript exclusion so we end up with
  // a clean array.
  $exclude = array_filter(array_map('trim', explode("\n", $values['omega_js_exclude'])));
  form_set_value(array('#parents' => array('omega_js_exclude')), $exclude, $form_state);
}