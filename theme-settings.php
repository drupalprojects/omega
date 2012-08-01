<?php

/**
 * @file
 * Theme settings file for the Omega base theme.
 */

require_once dirname(__FILE__) . '/template.php';

/**
 * Implements hook_form_FORM_alter().
 */
function omega_form_system_theme_settings_alter(&$form, $form_state) {
  // General "alters" use a form id. Settings should not be set here. The only
  // thing useful about this is if you need to alter the form for the running
  // theme and *not* the theme setting. @see http://drupal.org/node/943212
  if (isset($form_id)) {
    return;
  }

  // Include the template.php for all the themes in the theme trail.
  foreach (omega_theme_trail() as $theme => $name) {
    $filename = DRUPAL_ROOT . '/' . drupal_get_path('theme', $theme) . '/template.php';
    if (file_exists($filename)) {
      require_once $filename;
    }
  }

  // Get the admin theme so we can set a class for styling this form.
  $admin = drupal_html_class(variable_get('admin_theme', $GLOBALS['theme']));
  $form['#prefix'] = '<div class="admin-theme-' . $admin . '">';
  $form['#suffix'] = '</div>';

  // Add some custom styling and functionality to our theme settings form.
  $form['#attached']['css'][] = drupal_get_path('theme', 'omega') . '/css/omega.admin.css';
  $form['#attached']['js'][] = drupal_get_path('theme', 'omega') . '/js/omega.admin.js';

  // Collapse all the core theme settings tabs in order to have the form actions
  // visible all the time without having to scroll.
  foreach (element_children($form) as $key) {
    if ($form[$key]['#type'] == 'fieldset')  {
      $form[$key]['#collapsible'] = TRUE;
      $form[$key]['#collapsed'] = TRUE;
    }
  }

  $form['omega'] = array(
    '#type' => 'vertical_tabs',
    '#weight' => -10,
  );

  // Load the theme settings for all enabled extensions.
  foreach (omega_extensions() as $extension) {
    // Load all the implementations for this extensions and invoke the according
    // hooks.
    omega_theme_trail_load_include('inc', 'includes/' . $extension . '/' . $extension . '.settings');

    // By default, each extension resides in a vertical tab.
    $element = array(
      '#type' => 'fieldset',
      '#title' => t(filter_xss_admin(ucfirst($extension))),
    );

    foreach (omega_theme_trail() as $theme => $title) {
      $function = $theme . '_extension_' . $extension . '_theme_settings_form_alter';

      if (function_exists($function)) {
        $element = $function($element, $form, $form_state);
      }
    }

    if (element_children($element)) {
      // Append the extension form to the theme settings form if it has any
      // children.
      $form['omega']['omega_' . $extension] = $element;
    }
  }

  // We need a custom form submit handler for processing some of the values.
  $form['#submit'][] = 'omega_theme_settings_form_submit';
}

/**
 * Form submit handler for the theme settings form.
 */
function omega_theme_settings_form_submit($form, &$form_state) {
  // Clear the theme settings cache.
  cache_clear_all('theme_settings:' . $form_state['build_info']['args'][0], 'cache');

  // Rebuild the theme registry. This has quite a performance impact but since
  // this only happens once after we (re-)saved the theme settings this is fine.
  // Also, this is actually required because we are caching certain things in
  // the theme registry.
  $theme = $form_state['build_info']['args'][0];
  cache_clear_all('theme_registry:' . $theme, 'cache');
  cache_clear_all('theme_registry:runtime:' . $theme, 'cache');

  // This is a relict from the vertical tabs and should be removed so it doesn't
  // end up in the theme settings array.
  unset($form_state['values']['omega__active_tab']);
}
