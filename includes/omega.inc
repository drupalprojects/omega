<?php

/**
 * @file
 * Helper functions for the Omega base theme.
 */

function omega_theme_name($theme) {
  $themes = list_themes();
  return $themes[$theme]->info['name'];
}

/**
 * Builds the full theme trail (deepest base theme first, subtheme last) for a
 * theme.
 *
 * @param $theme
 *   (Optional) The key (machine-readable name) of a theme. Defaults to the key
 *   of the current theme.
 *
 * @return array
 *   An array of all themes in the trail, keyed by theme key.
 */
function omega_theme_trail($theme = NULL) {
  $theme = isset($theme) ? $theme : $GLOBALS['theme_key'];
  $static = &drupal_static(__FUNCTION__);

  if (!isset($static[$theme])) {
    $static[$theme] = array();
    $themes = list_themes();

    if (isset($themes[$theme]->info['base theme'])) {
      foreach (system_find_base_themes($themes, $theme) as $base => $name) {
        if ($name && isset($themes[$base])) {
          $static[$theme][$base] = $themes[$base]->info['name'];
        }
      }
    }

    // Add our current subtheme ($key) to that array.
    $static[$theme][$theme] = $themes[$theme]->info['name'];
  }

  return $static[$theme];
}

/**
 * Pre-processes CSS files so that CSS files that have 'preprocess_media' set to
 * TRUE are set to media="all" while having their former media query added to
 * the file content.
 *
 * @param $elements
 *   An array of CSS files as in drupal_pre_render_styles().
 *
 * @return array
 *   An array of preprocessed CSS files.
 *
 * @see drupal_pre_render_styles()
 */
function omega_css_preprocessor($elements) {
  foreach ($elements['#items'] as &$item) {
    if ($item['type'] == 'file' && $item['preprocess'] && $item['media'] != 'all') {
      $item['data'] = omega_css_cache_media_queries($item);
      $item['media'] = 'all';
    }
  }
  return $elements;
}

/**
 * Optimizes CSS aggregation by creating a cached version of each CSS file that,
 * instead of using the 'media' attribute on the styles tag, writes the media
 * query into the file itself using the '@media { ... }' syntax.
 *
 * This prevents unnecessary sprouting of new CSS aggregation.
 *
 * @see drupal_build_css_cache().
 */
function omega_css_cache_media_queries($item) {
  $map = variable_get('drupal_css_cache_files', array());
  $key = hash('sha256', serialize($item));
  $uri = isset($map[$key]) ? $map[$key] : NULL;

  if (empty($uri) || !file_exists($uri)) {
    // Build the base URL of this CSS file: start with the full URL.
    $base = file_create_url($item['data']);
    $base = substr($base, 0, strrpos($base, '/'));

    if (substr($base, 0, strlen($GLOBALS['base_omega'])) == $GLOBALS['base_omega']) {
      $base = substr($base, strlen($GLOBALS['base_omega']));
    }

    _drupal_build_css_path(NULL, $base . '/');

    $data = drupal_load_stylesheet($item['data'], TRUE);

    // Anchor all paths in the CSS with its base URL, ignoring external and absolute paths.
    $data = preg_replace_callback('/url\(\s*[\'"]?(?![a-z]+:|\/+)([^\'")]+)[\'"]?\s*\)/i', '_drupal_build_css_path', $data);
    $data = '@media ' . $item['media'] . '{' . $data . '}';

    // Create the css/ within the files folder.
    $directory = 'public://css';
    $uri = $directory . '/css_' . drupal_hash_base64($data) . '.css';

    // Create the CSS file.
    file_prepare_directory($directory, FILE_CREATE_DIRECTORY);
    if (!file_exists($uri) && !file_unmanaged_save_data($data, $uri, FILE_EXISTS_REPLACE)) {
      return FALSE;
    }

    // If CSS gzip compression is enabled, clean URLs are enabled (which means
    // that rewrite rules are working) and the zlib extension is available then
    // create a gzipped version of this file. This file is served conditionally
    // to browsers that accept gzip using .htaccess rules.
    if (variable_get('css_gzip_compression', TRUE) && variable_get('clean_url', 0) && extension_loaded('zlib')) {
      if (!file_exists($uri . '.gz') && !file_unmanaged_save_data(gzencode($data, 9, FORCE_GZIP), $uri . '.gz', FILE_EXISTS_REPLACE)) {
        return FALSE;
      }
    }

    // Save the updated map.
    $map[$key] = $uri;

    // Write the updated map into the variable.
    variable_set('drupal_css_cache_files', $map);
  }

  return $uri;
}

/**
 * Helper function for eliminating elements from an array using a simplified
 * regex pattern.
 *
 * @param $elements
 *   The array of elements that should have some elements nuked.
 * @param $exclude
 *   An array of strings that should be matched against the keys of the array
 *   of elements.
 *
 * @return array
 *   The purged array.
 */
function omega_exclude_assets(&$elements, $exclude) {
  // For optimization reasons we load the theme trail to check whether a
  // namespace matches the machine-readable name of one of the themes in the
  // trail.
  $trail = omega_theme_trail();

  foreach ($exclude as $item) {
    $path = '';
    // The first segment (everything before the first slash) is the namespace.
    list($namespace) = explode('/', $item);

    // Check if the namespace refers to a file residing in the 'misc' folder.
    if ($namespace == 'misc') {
      $path = DRUPAL_ROOT . '/misc';
    }
    // Check if the namespace refers to a theme.
    elseif (array_key_exists($namespace, $trail)) {
      $path = drupal_get_path('theme', $namespace);
    }
    else {
      // Otherwise, check if it refers to a module, profile or theme engine.
      foreach (array('module', 'profile', 'theme_engine') as $type) {
        if ($path = drupal_get_path($type, $namespace)) {
          break;
        }
      }
    }

    // If a namespace could be identified, use its path as a prefix, otherwise
    // use the plain file path as provided.
    $item = $path ? $path . '/' . substr($item, strlen($namespace) + 1) : $item;
    $item = preg_quote($item, '/');
    // Turn the * wildcards into actual regex wildcards and make sure that, if
    // a .css file is targeted directly we are also removing the RTL version of
    // that file.
    $item = str_replace(array('\*', '\.css'), array('(.*)', '(\.css|-rtl\.css)'), $item);

    // Look up all elements that match this exclusion pattern.
    $filtered = preg_grep("/^$item$/", array_keys($elements));
    $elements = array_diff_key($elements, array_flip($filtered));
  }
}

/**
 * Retrieves the array of enabled extensions for a theme. Every feature that has
 * been toggled on in the themes .info file, excluding the default theme
 * features (as defined in core), is considered an extension. We need this
 * information to make it easy for us to conditionally build the theme settings
 * form based on which extensions are enabled. Each extension can define a
 * theme settings form altering function named
 * 'THEMENAME_extension_EXTENSION_theme_settings_form_alter()' and optionally
 * put it into 'THEME_ROOT/includes/includes/EXTENSION.settings.inc' to have
 * it automatically included whenever the theme settings form is displayed
 * (given that the extension is currently enabled). Each extension can also
 * define a 'THEMENAME_extension_EXTENSION_theme_registry_alter()' function and
 * optionally put it into 'THEME_ROOT/includes/includes/EXTENSION.inc' to
 * register custom hooks with the theme registry.
 *
 * @param $theme
 *   (Optional) The key (machine-readable name) of a theme. Defaults to the key
 *   of the current theme.
 *
 * @return array
 *   The theme info array of the passed or current theme.
 *
 * @see _system_default_theme_features()
 * @see omega_extension_development_theme_settings_form_alter()
 * @see omega_extension_development_theme_registry_alter()
 */
function omega_extensions($theme = NULL) {
  $features = &drupal_static(__FUNCTION__);
  $theme = isset($theme) ? $theme : $GLOBALS['theme_key'];

  if (!isset($features[$theme])) {
    $features[$theme] = array();
    foreach (array_reverse(omega_theme_trail($theme)) as $key => $title) {
      $info = omega_theme_info($key);
      if (!empty($info['features'])) {
        // The default theme features are not considered 'extensions' per-se.
        $features[$theme] = array_diff($info['features'], _system_default_theme_features());
        break;
      }
    }
  }

  return $features[$theme];
}

/**
 * @param null $theme
 * @param bool $merge
 * @return mixed
 */
function omega_theme_trail_info($element, $merge = TRUE, $theme = NULL) {
  $output = array();

  // Loop over all themes in the theme trail and look up $element in the .info
  // array.
  foreach (array_reverse(omega_theme_trail($theme)) as $key => $name) {
    $info = omega_theme_info($key);

    // If $merge is TRUE we combine all the results of all themes in the theme
    // trail. Otherwise we just return the first occurrence.
    if (isset($info[$element]) && is_array($info[$element])) {
      $output = array_merge($info[$element], $output);
    }
    if (!$merge) {
      break;
    }
  }

  return $output;
}

/**
 * Retrieves the full info array of a theme.
 *
 * @param $theme
 *   (Optional) The key (machine-readable name) of a theme. Defaults to the key
 *   of the current theme.
 *
 * @return array
 *   The theme info array of the passed or current theme.
 */
function omega_theme_info($theme = NULL) {
  $theme = isset($theme) ? $theme : $GLOBALS['theme_key'];

  // If this is the current theme, just load the theme info from the globals.
  // Note: The global 'theme_key' property is not reliable in this case because
  // it gets overridden on theme settings pages.
  if ($theme == $GLOBALS['theme']) {
    return $GLOBALS['theme_info']->info;
  }

  $themes = list_themes();
  return $themes[$theme]->info;
}

/**
 * Includes all occurrences of a file in all themes in the theme trail. This is
 * very similar to module_load_include().
 *
 * @param $type
 *   The include file's type (file extension).
 * @param $name
 *   The base file name (without the $type extension).
 * @param $all
 *   (Optional) Whether all occurrences of this file should be loaded or just
 *   the first. Defaults to TRUE (all).
 * @param $theme
 *   (Optional) The key (machine-readable name) of a theme. Defaults to the key
 *   of the current theme.
 *
 * @see module_load_include()
 */
function omega_theme_trail_load_include($type, $name, $all = TRUE, $theme = NULL) {
  $theme = isset($theme) ? $theme : $GLOBALS['theme_key'];
  $trail = array_reverse(omega_theme_trail($theme));

  foreach ($trail as $key => $title) {
    // Check if the requested file exists in any of the themes in the theme
    // trail and include all occurrences.
    $file = DRUPAL_ROOT . '/' . drupal_get_path('theme', $key) . "/$name.$type";
    if (is_file($file)) {
      include_once $file;

      // Exit right here if we only want the first occurrence of this file to
      // be loaded.
      if (!$all) {
        return;
      }
    }
  }
}

/**
 * Invoke a hook in all themes in the theme trail that implement it.
 *
 * @param $hook
 *   The name of the hook to invoke.
 * @param $theme
 *   (Optional) The key (machine-readable name) of a theme. Defaults to the key
 *   of the current theme.
 * @param ...
 *   Arguments to pass to the hook.
 *
 * @return array
 *   An array of return values of the hook implementations. If themes return
 *   arrays from their implementations, those are merged into one array.
 *
 * @see module_invoke_all()
 */
function omega_invoke_all($hook, $theme = NULL) {
  $args = func_get_args();
  // Remove $hook and $theme from the arguments.
  unset($args[0], $args[1]);
  $return = array();
  foreach (omega_theme_trail($theme) as $key => $name) {
    $function = $key . '_' . $hook;

    if (function_exists($function)) {
      $result = call_user_func_array($function, $args);
      if (isset($result) && is_array($result)) {
        // Append the 'theme' property to each array element.
        foreach ($result as &$item) {
          $item['theme'] = $key;
        }
        $return = array_merge_recursive($return, $result);
      }
      elseif (isset($result)) {
        $return[] = $result;
      }
    }
  }
  return $return;
}

/**
 * Custom implementation of drupal_array_get_nested_value() that also supports
 * objects instead of just arrays.
 *
 * @param $object
 *   The array or object from which to get the value.
 * @param $parents
 *   An array of parent keys of the value, starting with the outermost key.
 * @param $key_exists
 *   (optional) If given, an already defined variable that is altered by
 *   reference.
 *
 * @return mixed
 *   The requested nested value. Possibly NULL if the value is NULL or not all
 *   nested parent keys exist. $key_exists is altered by reference and is a
 *   Boolean that indicates whether all nested parent keys exist (TRUE) or not
 *   (FALSE). This allows to distinguish between the two possibilities when NULL
 *   is returned.
 *
 * @see drupal_array_get_nested_value()
 */
function omega_get_nested_value(&$object, array $parents, &$key_exists = NULL) {
  $ref = &$object;
  foreach ($parents as $parent) {
    if (is_array($ref) && array_key_exists($parent, $ref)) {
      $ref = &$ref[$parent];
    }
    elseif (is_object($ref) && property_exists($ref, $parent)) {
      $ref = &$ref->$parent;
    }
    else {
      $key_exists = FALSE;
      return NULL;
    }
  }
  $key_exists = TRUE;
  return $ref;
}

/**
 * Wrapper for hook_libraries_info() to allow themes to easily define libraries
 * and distinguish between module and theme provided libraries.
 *
 * @return array
 *   An array of libraries defined by themes in the theme trail of the active
 *   theme.
 *
 * @see hook_libraries_info()
 */
function omega_theme_libraries_info() {
  $libraries = array();
  foreach (omega_invoke_all('omega_theme_libraries_info') as $library => $info) {
    $libraries[$library] = libraries_info_defaults($info, $library);
  }
  return $libraries;
}

/**
 * @return array
 */
function omega_layouts_info($theme = NULL) {
  $theme = isset($theme) ? $theme : $GLOBALS['theme_key'];
  $layouts = drupal_static('__FUNCTION__');

  if (!isset($layouts[$theme])) {
    foreach (omega_invoke_all('omega_layouts_info') as $layout => $info) {
      $info['layout'] = $layout;

      // Provide some defaults.
      $layouts[$theme][$layout] = $info + array(
        'label' => ucfirst($layout),
        'description' => '',
        'template' => $layout,
        'path' => drupal_get_path('theme', $info['theme']) . '/layouts/' . $layout,
        'icon' => drupal_get_path('theme', 'omega') . '/images/layout-icon-placeholder.png',
        'attached' => array(),
      );

      // Find the attached files in the theme trail. Subtheme implementations
      // override basetheme implementations.
      foreach (array('css', 'js') as $type) {
        if (!empty($layouts[$theme][$layout]['attached'][$type])) {
          foreach ($layouts[$theme][$layout]['attached'][$type] as $file => &$options) {
            if ($file = omega_theme_trail_find_file_specific($file, NULL, $theme)) {
              $options['data'] = $file;
            }
          }
        }
      }
    }

    // Give all themes in the theme trail a chance to alter the layouts.
    drupal_alter('omega_layouts_info', $layouts[$theme]);
  }

  return $layouts[$theme];
}

/**
 * @param $file
 * @param null $theme
 * @return bool|string
 */
function omega_theme_trail_find_file_specific($file, $path = NULL, $theme = NULL) {
  $theme = isset($theme) ? $theme : $GLOBALS['theme_key'];

  foreach (array_reverse(omega_theme_trail($theme)) as $key => $name) {
    $current = drupal_get_path('theme', $key) . '/' . $path . '/' . $file;

    if (is_file($current)) {
      return $current;
    }
  }

  return FALSE;
}
