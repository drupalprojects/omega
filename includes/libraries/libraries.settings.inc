<?php

/**
 * @file
 * Contains the theme settings form elements for the development extension.
 */

/**
 * Implements hook_extension_EXTENSION_theme_settings_form_alter().
 */
function omega_extension_libraries_theme_settings_form_alter($element, &$form, $form_state) {
  if (module_exists('libraries')) {
    $libraries = theme_get_setting('omega_libraries');

    foreach (omega_theme_libraries_info() as $library => $info) {
      if (isset($info['theme'])) {
        $element[$library]['status'] = array(
          '#type' => 'checkbox',
          '#title' => isset($info['vendor url']) ? t('@library by <a href="!url">@vendor</a>', array('@library' => $info['name'], '!url' => $info['vendor url'], '@vendor' => $info['vendor'])) : t('@library by @vendor', array('@library' => $info['name'], '@vendor' => $info['vendor'])),
          '#description' => $info['description'],
          '#default_value' => !empty($libraries[$library]['status']),
          '#parents' => array('omega_libraries', $library, 'status'),
        );

        $options = array();
        if (!empty($info['variants'])) {
          $variants = array();
          foreach ($info['variants'] as $variant => $variant_info) {
            $variants[$variant] = $variant_info['name'] . (isset($variant_info['description']) ? '<div class="description">' . $variant_info['description'] . '</div>': '');
          }

          if (!empty($variants)) {
            $variants = array_merge(array('_default' => t('Default') . '<div class="description">' . t('The default variant generally is a minified version of the library and provides the optimal production solution in most cases.') . '</div>'), $variants);

            $options['variant'] = array(
              '#type' => 'radios',
              '#title' => t('Variant'),
              '#options' => $variants,
              '#default_value' => !empty($libraries[$library]['variant']) ? $libraries[$library]['variant'] : '_default',
              '#parents' => array('omega_libraries', $library, 'variant'),
            );
          }
        }

        // Some libraries might want to provide special options.
        if (isset($info['options form callback']) && function_exists($info['options form callback'])) {
          // If a library options form callback exists and actually returns some
          // options for this library append conditionally show them underneath
          // the library checkbox with #states.
          $info['options form callback']($options, $form, $form_state, $library, $info);
        }

        if (!empty($options)) {
          $element[$library]['options'] = array(
            '#type' => 'fieldset',
            '#title' => t('Options'),
            '#states' => array(
              'visible' => array(
                ':input[name="omega_libraries[' . $library . '][status]"]' => array('checked' => TRUE),
              ),
            ),
            '#parents' => array('omega_libraries', $library),
            '#tree' => TRUE,
            '#attributes' => array('class' => array('omega-settings-container')),
            '#collapsible' => TRUE,
            '#collapsed' => TRUE,
          ) + $options;
        }
      }
    }

    $form['#submit'][] = 'omega_extension_libraries_theme_settings_form_submit';
  }

  return $element;
}

/**
 * Theme settings form submit handler for the libraries extension.
 */
function omega_extension_libraries_theme_settings_form_submit($form, &$form_state) {
  if (!empty($form_state['values']['omega_libraries'])) {
    $values = &$form_state['values']['omega_libraries'];

    foreach ($values as &$settings) {
      if (isset($settings['variant']) && $settings['variant'] == '_default') {
        // Unset the 'variant' option if it is the default option.
        unset($settings['variant']);
      }
    }
  }
}

/**
 * Theme settings form submit handler for handling the PIE selectors.
 */
function omega_library_pie_selectors_submit($form, &$form_state) {
  $values = &$form_state['values']['omega_libraries']['css3pie'];

  // The generated CSS file is stored in the public file system.
  $theme = $form_state['build_info']['args'][0];
  $destination = 'public://omega/' . $theme;

  // Generate a CSS file for the provided selectors if the inclusion method is
  // set to 'pie.htc' or 'pie.php'.
  $selectors = array();
  if (!empty($values['selectors'])) {
    // Sanitize the selectors.
    $selectors = array_map('trim', explode("\n", $values['selectors']));
    $selectors = array_unique($selectors);

    // Retrieve the path to the library.
    $path = base_path() . libraries_get_path('css3pie') . '/pie.htc';

    // Build the contents of the CSS and JS file file.
    $css = implode(",", $selectors) . "{behavior:url($path)}";
    $js = '$(function(){Drupal.behaviors.css3pie={attach:function(context,settings){if(window.PIE){$("' . implode(",", $selectors) . '").each(function(){PIE.attach(this)})}}}})(jQuery);';

    // Save the generated CSS in the public file system replacing any previous
    // version.
    file_prepare_directory($destination, FILE_CREATE_DIRECTORY);
    file_unmanaged_save_data($css, $destination . '/pie-selectors.css', FILE_EXISTS_REPLACE);
    file_unmanaged_save_data($js, $destination . '/pie-selectors.js', FILE_EXISTS_REPLACE);
  }
  else {
    // Otherwise, we can delete the (now stale) CSS file.
    file_unmanaged_delete($destination . '/pie-selectors.css');
  }

  // Save the selectors as an array.
  $parents = array('#parents' => array('omega_libraries', 'css3pie', 'selectors'));
  form_set_value($parents, $selectors, $form_state);
}


/**
 * CSS3PIE Library options form callback.
 */
function omega_library_pie_options_form(&$element, &$form, $form_state, $library, $info) {
  // Give the default variant a proper label.
  $element['variant']['#options']['_default'] = t('HTML Component (default)');

  // Pull the selectors from the theme settings.
  $settings = theme_get_setting('omega_libraries');

  // The generated CSS file is stored in the public file system.
  $theme = $form_state['build_info']['args'][0];
  $destination = file_create_url('public://omega/' . $theme);

  $element['selectors'] = array(
    '#title' => t('Selectors'),
    '#type' => 'textarea',
    '#description' => t("You can use this textarea to define all the CSS rules that you want to apply the PIE behavior to. Define one CSS selector per line. Note: The selectors submitted with this field get directly cached in a <a href=\"!css\">.css</a> file (as well as a <a href=\"!js\">.js</a> file) in the public file system to not clutter the theme settings array. Therefore, it won't get exported if you choose to export your theme settings.", array('!css' => $destination . '/pie-selectors.css', '!js' => $destination . '/pie-selectors.js')),
    '#default_value' => !empty($settings['css3pie']['selectors']) ? implode("\n", $settings['css3pie']['selectors']) : '',
  );

  // We need to provide a submit handler to create a CSS file for the
  // defined selectors and remove them from the theme settings array.
  $form['#submit'][] = 'omega_library_pie_selectors_submit';
}
