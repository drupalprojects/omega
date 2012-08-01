<?php

/**
 * @file
 * Template overrides and (pre-)process hooks for the Omega base theme.
 */

require_once dirname(__FILE__) . '/includes/omega.inc';

/**
 * Slightly hacky performance tweak for theme_get_setting(). This resides
 * outside of any function declaration to make sure that it runs directly after
 * the theme has been initialized.
 *
 * Instead of rebuilding the theme settings array on every page load we are
 * caching the content of the static cache in the database after it has been
 * built initially. This is quite a bit faster than running all the code in
 * theme_get_setting() on every page.
 *
 * By checking whether the global 'theme' and 'theme_key' properties are
 * identical we make sure that we don't interfere with any of the theme settings
 * pages and only use this feature when actually rendering a page with this
 * theme.
 *
 * @see theme_get_setting()
 */
if ($GLOBALS['theme'] == $GLOBALS['theme_key'] && !$static = &drupal_static('theme_get_setting')) {
  if ($cache = cache_get('theme_settings:' . $GLOBALS['theme'])) {
    // If the cache entry exists, populate the static theme settings array with
    // its data. This prevents the theme settings from being rebuilt on every
    // page load.
    $static[$GLOBALS['theme']] = $cache->data;
  }
  else {
    // Invoke theme_get_setting() with a random argument to build the theme
    // settings array and populate the static cache.
    theme_get_setting('foo');
    // Extract the theme settings from the previously populated static cache.
    $static = &drupal_static('theme_get_setting');

    // Write the toggled state of all extensions into the theme settings.
    foreach (omega_extensions() as $extension) {
      $static[$GLOBALS['theme']]['toggle_' . $extension] = TRUE;
    }

    // Cache the theme settings in the database.
    cache_set('theme_settings:' . $GLOBALS['theme'], $static[$GLOBALS['theme']]);
  }
}

/**
 * Rebuild the theme registry on every page load if the development extension
 * is enabled and configured to do so. This also lives outside of any function
 * declaration to make sure that the registry is rebuilt before invoking any
 * theme hooks.
 */
if (theme_get_setting('toggle_development') && theme_get_setting('omega_rebuild_theme_registry') &&  user_access('administer site configuration')) {
  drupal_theme_rebuild();

  if (flood_is_allowed('omega_' . $GLOBALS['theme'] . '_rebuild_registry_warning', 3)) {
    // Alert the user that the theme registry is being rebuilt on every request.
    flood_register_event('omega_' . $GLOBALS['theme'] . '_rebuild_registry_warning');
    drupal_set_message(t('The theme registry is being rebuilt on every request. Remember to <a href="!url">turn off</a> this feature on production websites.', array("!url" => url('admin/appearance/settings/' . $GLOBALS['theme']))));
  }
}

/**
 * Implements hook_preprocess().
 */
function omega_preprocess(&$variables) {
  // Copy over the classes array into the attributes array.
  if (!empty($variables['classes_array'])) {
    $variables['attributes_array']['class'] = !empty($variables['attributes_array']['class']) ? $variables['attributes_array']['class'] + $variables['classes_array']: $variables['classes_array'];
    $variables['attributes_array']['class'] = array_unique($variables['attributes_array']['class']);
  }
}

/**
 * Implements hook_element_info_alter().
 */
function omega_element_info_alter(&$elements) {
  if (theme_get_setting('omega_media_queries_inline') && variable_get('preprocess_css', FALSE) && (!defined('MAINTENANCE_MODE') || MAINTENANCE_MODE != 'update')) {
    array_unshift($elements['styles']['#pre_render'], 'omega_css_preprocessor');
  }
}

/**
 * Implements hook_css_alter().
 */
function omega_css_alter(&$css) {
  if (theme_get_setting('toggle_manipulation') && $exclude = theme_get_setting('omega_css_exclude')) {
    omega_exclude_assets($css, $exclude);
  }

  // The CSS_SYSTEM aggregation group doesn't make any sense. Therefore, we are
  // pre-pending it to the CSS_DEFAULT group. This has the same effect as giving
  // it a separate (low-weighted) group but also allows it to be aggregated
  // together with the rest of the CSS.
  foreach ($css as &$item) {
    if ($item['group'] == CSS_SYSTEM) {
      $item['group'] = CSS_DEFAULT;
      $item['weight'] = $item['weight'] - 100;
    }
  }
}

/**
 * Implements hook_js_alter().
 */
function omega_js_alter(&$js) {
  if (theme_get_setting('toggle_manipulation') && $exclude = theme_get_setting('omega_js_exclude')) {
    omega_exclude_assets($js, $exclude);
  }

  // Move all the JavaScript to the footer if the theme is configured that way.
  if (theme_get_setting('omega_js_footer')) {
    foreach ($js as &$item) {
      $item['scope'] = 'footer';
    }
  }
}

/**
 * Implements hook_theme().
 */
function omega_theme($existing, $type, $theme, $path) {
  $info = array();

  if (theme_get_setting('toggle_layouts') && $layouts = omega_layouts_info()) {
    foreach ($layouts as $key => $layout) {
      $info['page__' . $key . '_layout'] = array(
        'layout' => $layout,
        'template' => $layout['template'],
        'path' => $layout['path'],
      );
    }
  }

  return $info;
}

/**
 * Implements hook_theme_registry_alter().
 *
 * Allows subthemes to split preprocess / process / theme code across separate
 * files to keep the main template.php file clean. This is really fast because
 * it uses the theme registry to cache the pathes to the files that it finds.
 */
function omega_theme_registry_alter(&$registry) {
  // Register theme hook and function implementations from
  foreach (omega_theme_trail() as $key => $theme) {
    foreach (array('preprocess', 'process', 'theme') as $type) {
      $path = drupal_get_path('theme', $key);
      // Only look for files that match the 'something.preprocess.inc' pattern.
      $mask = '/.' . $type . '.inc$/';
      // This is the length of the suffix (e.g. '.preprocess') of the basename
      // of a file.
      $strlen = -(strlen($type) + 1);

      // Recursively scan the folder for the current step for (pre-)process
      // files and write them to the registry.
      foreach (file_scan_directory($path . '/' . $type, $mask) as $item) {
        $hook = strtr(substr($item->name, 0, $strlen), '-', '_');

        if (array_key_exists($hook, $registry)) {
          // Template files override theme functions.
          if (($type == 'theme' && isset($registry[$hook]['template']))) {
            continue;
          }

          // Name of the function (theme hook or theme function).
          $function = $type == 'theme' ? $key . '_' . $hook : $key . '_' . $type . '_' . $hook;

          // Load the file once so we can check if the function exists.
          require_once $item->uri;

          // Proceed if the callback doesn't exist.
          if (!function_exists($function)) {
            continue;
          }

          // By adding this file to the 'includes' array we make sure that it is
          // available when the hook is executed.
          $registry[$hook]['includes'][] = $item->uri;

          if ($type == 'theme') {
            $registry[$hook]['type'] = $key == $GLOBALS['theme'] ? 'theme_engine' : 'base_theme_engine';
            $registry[$hook]['theme path'] = $path;

            // Replace the theme function.
            $registry[$hook]['function'] = $function;
          }
          else {
            // Append the included preprocess hook to the array of functions.
            $registry[$hook][$type . ' functions'][] = $function;
          }
        }
      }
    }
  }

  // Include the main extension file for every enabled extensions. This is
  // required for the next step (allowing extensions to register hooks in the
  // theme registry).
  foreach (omega_extensions() as $extension) {
    omega_theme_trail_load_include('inc', 'includes/' . $extension . '/' . $extension);

    // Give every enabled extension a chance to alter the theme registry.
    foreach (omega_theme_trail() as $key => $theme) {
      $hook = $key . '_extension_' . $extension . '_theme_registry_alter';
      if (function_exists($hook)) {
        $hook($registry);
      }
    }
  }

  // Fix for integration with the theme developer module.
  if (module_exists('devel_themer')) {
    foreach ($registry as &$item) {
      if (isset($item['function']) && $item['function'] != 'devel_themer_catch_function') {
        // If the hook is a function, store it so it can be run after it has been intercepted.
        // This does not apply to template calls.
        $item['devel_function_intercept'] = $item['function'];
      }

      // Add our catch function to intercept functions as well as templates.
      $item['function'] = 'devel_themer_catch_function';

      // Remove the process and preprocess functions so they are
      // only called by devel_themer_theme_twin().
      $item['devel_function_preprocess_intercept'] = !empty($item['preprocess functions']) ? array_merge($item['devel_function_preprocess_intercept'], array_diff($item['preprocess functions'], $item['devel_function_preprocess_intercept'])) : $item['devel_function_preprocess_intercept'];
      $item['devel_function_process_intercept'] = !empty($item['process functions']) ? array_merge($item['devel_function_process_intercept'], array_diff($item['process functions'], $item['devel_function_process_intercept'])) : $item['devel_function_process_intercept'];
      $item['preprocess functions'] = array();
      $item['process functions'] = array();
    }
  }
}

/**
 * Implements hook_block_list_alter().
 */
function omega_block_list_alter(&$blocks) {
  if (!theme_get_setting('omega_toggle_front_page_content') && drupal_is_front_page()) {
    foreach ($blocks as $key => $block) {
      if ($block->module == 'system' && $block->delta == 'main') {
        unset($blocks[$key]);
      }
    }

    drupal_set_page_content();
  }
}

/**
 * Implements hook_page_alter().
 *
 * Look for the last block in the region. This is impossible to determine from
 * within a preprocess_block function.
 */
function omega_page_alter(&$page) {
  // Look in each visible region for blocks.
  foreach (system_region_list($GLOBALS['theme'], REGIONS_VISIBLE) as $region => $name) {
    if (!empty($page[$region])) {
      // Find the last block in the region.
      $blocks = array_reverse(element_children($page[$region]));
      while ($blocks && !isset($page[$region][$blocks[0]]['#block'])) {
        array_shift($blocks);
      }

      if ($blocks) {
        $page[$region][$blocks[0]]['#block']->last_in_region = TRUE;
      }
    }
  }
}

/**
 * Implements hook_html_head_alter().
 */
function omega_html_head_alter(&$head) {
  // Simplify the meta tag for character encoding.
  $head['system_meta_content_type']['#attributes'] = array('charset' => str_replace('text/html; charset=', '', $head['system_meta_content_type']['#attributes']['content']));
}

/**
 * Implements hook_omega_layouts_info().
 */
function omega_omega_layouts_info() {
  $info['epiqo'] = array(
    'label' => t('epiqo'),
    'description' => t('Default layout for epiqo distributions.'),
    'attached' => array(
      'css' => array(
        'layouts/epiqo/css/epiqo.layout.css' => array('group' => CSS_THEME),
      ),
      'js' => array(
        'js/libraries/jquery.matchmedia.js' => array('group' => JS_THEME),
        'layouts/epiqo/js/epiqo.layout.js' => array('group' => JS_THEME),
      ),
    ),
  );

  return $info;
}
