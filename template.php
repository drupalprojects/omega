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

function omega_block_list_alter(&$blocks) {
  if (drupal_is_front_page()) {
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
 * Implements hook_omega_theme_libraries_info().
 */
function omega_omega_theme_libraries_info() {
  $libraries['selectivizr'] = array(
    'name' => t('Selectivizr'),
    'description' => t('Selectivizr is a JavaScript utility that emulates CSS3 pseudo-classes and attribute selectors in Internet Explorer 6-8. Simply include the script in your pages and selectivizr will do the rest.'),
    'vendor' => 'Keith Clark',
    'vendor url' => 'http://selectivizr.com/',
    // With our drush integration we can automatically download all our library
    // files that have a download url registered.
    'download url' => 'http://selectivizr.com/downloads/selectivizr-1.0.2.zip',
    'version arguments' => array(
      'file' => 'changelog.txt',
      'pattern' => '@v([0-9\.]+)@',
    ),
    'theme' => 'omega',
    'files' => array(
      'js' => array(
        'selectivizr-min.js' => array(
          // Only load Selectivizr for Internet Explorer > 6 and < 8.
          'browsers' => array('IE' => '(gte IE 6)&(lte IE 8)', '!IE' => FALSE),
          'group' => JS_LIBRARY,
          'weight' => -100,
        ),
      ),
    ),
    // The Selectivizr library also ships with a source (unminified) version.
    'variants' => array(
      'source' => array(
        'name' => t('Source'),
        'description' => t('During development it might be useful to include the source files instead of the minified version.'),
        'files' => array(
          'js' => array(
            'selectivizr.js' => array(
              // Only load Selectivizr for Internet Explorer > 6 and < 8.
              'browsers' => array('IE' => '(gte IE 6)&(lte IE 8)', '!IE' => FALSE),
              'group' => JS_LIBRARY,
              'weight' => -100,
            ),
          ),
        ),
      ),
    ),
  );

  $libraries['css3mediaqueries'] = array(
    'name' => t('CSS3 Media Queries'),
    'description' => t('CSS3 Media Queries is a JavaScript library to make IE 5+, Firefox 1+ and Safari 2 transparently parse, test and apply CSS3 Media Queries. Firefox 3.5+, Opera 7+, Safari 3+ and Chrome already offer native support.'),
    'vendor' => 'Wouter van der Graaf',
    'vendor url' => 'http://woutervandergraaf.nl/',
    'download url' => 'https://github.com/livingston/css3-mediaqueries-js/tarball/master',
    'download subdirectory' => '/^livingston-css3-mediaqueries-js-[a-z0-9]+$/',
    'version arguments' => array(
      'file' => 'css3-mediaqueries.js',
      'pattern' => '@version:\s([0-9\.]+)@',
    ),
    'theme' => 'omega',
    'files' => array(
      'js' => array(
        'css3-mediaqueries.min.js' => array(
          'browsers' => array('IE' => '(gte IE 6)&(lte IE 8)', '!IE' => FALSE),
          'group' => JS_LIBRARY,
          'weight' => -100,
        ),
      ),
    ),
    'variants' => array(
      'source' => array(
        'name' => t('Source'),
        'description' => t('During development it might be useful to include the source files instead of the minified version.'),
        'files' => array(
          'js' => array(
            'css3-mediaqueries.js' => array(
              'browsers' => array('IE' => '(gte IE 6)&(lte IE 8)', '!IE' => FALSE),
              'group' => JS_LIBRARY,
              'weight' => -100,
            ),
          ),
        ),
      ),
    ),
  );

  $libraries['respond'] = array(
    'name' => t('Respond'),
    'description' => t('Respond is a fast & lightweight polyfill for min/max-width CSS3 Media Queries (for IE 6-8, and more).'),
    'vendor' => 'Scott Jehl',
    'vendor url' => 'http://scottjehl.com/',
    'download url' => 'https://github.com/scottjehl/Respond/tarball/master',
    'download subdirectory' => '/^scottjehl-Respond-[a-z0-9]+$/',
    'version arguments' => array(
      'file' => 'respond.min.js',
      'pattern' => '@Respond\.js\sv([0-9\.]+)@',
    ),
    'theme' => 'omega',
    'files' => array(
      'js' => array(
        'respond.min.js' => array(
          'browsers' => array('IE' => '(gte IE 6)&(lte IE 8)', '!IE' => FALSE),
          'group' => JS_LIBRARY,
          'weight' => -100,
        ),
      ),
    ),
    'variants' => array(
      'source' => array(
        'name' => t('Source'),
        'description' => t('During development it might be useful to include the source files instead of the minified version.'),
        'files' => array(
          'js' => array(
            'respond.js' => array(
              'browsers' => array('IE' => '(gte IE 6)&(lte IE 8)', '!IE' => FALSE),
              'group' => JS_LIBRARY,
              'weight' => -100,
            ),
          ),
        ),
      ),
    ),
  );

  $libraries['css3pie'] = array(
    'name' => t('CSS3 PIE'),
    'description' => t('PIE makes Internet Explorer 6-9 capable of rendering several of the most useful CSS3 decoration features.'),
    'vendor' => 'Keith Clark',
    'vendor url' => 'http://css3pie.com/',
    'download url' => 'http://css3pie.com/download-latest',
    'version arguments' => array(
      'file' => 'PIE.htc',
      'pattern' => '@Version\s([a-z0-9\.]+)@',
    ),
    'options form callback' => 'omega_library_pie_options_form',
    'theme' => 'omega',
    'files' => array(),
    // The pie library is completely different to all other libraries in how it
    // is loaded (different inclusion types, etc.) so we just handle it with a
    // custom pre-load callback.
    'callbacks' => array(
      'post-load' => array('omega_library_pie_post_load_callback'),
    ),
    'variants' => array(
      'js' => array(
        'name' => t('JavaScript'),
        'description' => t('While the .htc behavior is still the recommended approach for most users, the JS version has some advantages that may be a better fit for some users.'),
        'files' => array(
          'js' => array(
            'PIE.js' => array(
              'browsers' => array('IE' => '(gte IE 6)&(lte IE 8)', '!IE' => FALSE),
              'group' => JS_LIBRARY,
              'weight' => -100,
            ),
          ),
        ),
      ),
    ),
  );

  $libraries['html5shiv'] = array(
    'name' => t('HTML5 Shiv'),
    'description' => t('This script is the defacto way to enable use of HTML5 sectioning elements in legacy Internet Explorer, as well as default HTML5 styling in Internet Explorer 6 - 9, Safari 4.x (and iPhone 3.x), and Firefox 3.x.'),
    'vendor' => 'Alexander Farkas',
    'download url' => 'https://github.com/aFarkas/html5shiv/tarball/master',
    'download subdirectory' => '/^aFarkas-html5shiv-[a-z0-9]+$/',
    'version arguments' => array(
      'file' => 'src/html5shiv.js',
      'pattern' => '@HTML5\sShiv\s([a-z0-9\.]+)@',
    ),
    'theme' => 'omega',
    'files' => array(
      'js' => array(
        'dist/html5shiv.js' => array(
          'browsers' => array('IE' => '(gte IE 6)&(lte IE 8)', '!IE' => FALSE),
          'group' => JS_LIBRARY,
        ),
      ),
    ),
    'variants' => array(
      'source' => array(
        'name' => t('Source'),
        'description' => t('During development it might be useful to include the source files instead of the minified version.'),
        'files' => array(
          'js' => array(
            'src/html5shiv.js' => array(
              'type' => 'external',
              'browsers' => array('IE' => '(gte IE 6)&(lte IE 8)', '!IE' => FALSE),
              'group' => JS_LIBRARY,
            ),
          ),
        ),
      ),
    ),
  );

  return $libraries;
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
