<?php

namespace Drupal\omega\Layout;

use Drupal\breakpoint\Breakpoint;
use Drupal\omega\Theme\OmegaSettingsInfo;
use Drupal\omega\Style\OmegaStyle;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class OmegaLayout
 *
 * The OmegaLayout class offers a transition between original procedural
 * functions provided via including omega-functions.php, etc. and static
 * methods available in OmegaLayout.
 *
 * @todo: Eventually, the methods defined here should be refactored.
 * @package Drupal\omega\Layout
 */
class OmegaLayout implements OmegaLayoutInterface {

  /**
   * The theme handler service.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * The file system handler service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileHandler;

  /**
   * An array of Drupal themes, each an array of information about that theme.
   *
   * @var array
   */
  public $themes;

  /**
   * @var array
   */
  public static $omegaGsDisabled = array(
    ':input[name="enable_omegags_layout"]' => array(
      'checked' => FALSE
    )
  );

  /**
   * @var array
   */
  public static $omegaGsEnabled = array(
    ':input[name="enable_omegags_layout"]' => array(
      'checked' => TRUE
    )
  );

  /**
   * Constructs a layout object.
   *
   * @param ThemeHandlerInterface $theme_handler
   * @param FileSystemInterface $file_handler
   */
  public function __construct(ThemeHandlerInterface $theme_handler, FileSystemInterface $file_handler) {
    $this->themeHandler = $theme_handler;
    $this->fileHandler = $file_handler;
    $this->themes = $this->themeHandler->rebuildThemeData();
  }

  /**
   * @inheritdoc
   * @todo: Update variables with @var comments.
   */
  public static function saveLayoutData($layout, $layout_id, $theme, $generate = FALSE) {
    // Grab the editable configuration objects
    /** @var \Drupal\Core\Config\ConfigFactory $configFactory */
    $configFactory = \Drupal::service('config.factory');
    $layoutConfig = $configFactory->getEditable($theme . '.layout.' . $layout_id);
    $layoutConfigGenerated = $configFactory->getEditable($theme . '.layout.' . $layout_id . '.generated');

    // unset some junk that was passed in the form's $layout array
    // this includes some informational messages, etc.
    unset($layout['breakpoint_group_updated']);

    // Check for differences in the $layoutConfig (current stored DB version) and the $layout (passed form values)
    // If and only if there are differences will we continue with saving the layout, otherwise, we'll skip it
    if ($layoutConfig->getOriginal() == $layout) {
      // no updates, throw message (to be removed likely)
      // drupal_set_message(t('The layout <strong>' . $layout_id . '</strong> matches the version already stored at <strong>' . $theme . '.layout.' . $layout_id . '</strong>. No save on this layout was performed.'));
    }
    else {
      /* updates found, proceed */

      // Set the value to $layout
      $layoutConfig->setData($layout);

      // Save it
      $saved = $layoutConfig->save();

      // check for errors
      if ($saved) {
        drupal_set_message(t('Layout <em>' . $layout_id . '</em> updated: <strong>' . $theme . '.layout.' . $layout_id . '</strong>'));
      }
      else {
        drupal_set_message(t('WTF002: Layout configuration error... : function _omega_save_database_layout()'), 'error');
      }
    }

    // $theme.layout.$layout_id.generated - We should save current values to .generated
    if ($generate) {
      if ($layoutConfigGenerated->getOriginal() != $layout) {
        $layoutConfigGenerated->setData($layout);
        $saved = $layoutConfigGenerated->save();

        if ($saved) {
          drupal_set_message(t('Layout <em>' . $layout_id . '</em> updated: <strong>' . $theme . '.layout.' . $layout_id . '.generated</strong>'));
        }
        else {
          drupal_set_message(t('WTF003: Layout configuration error... : function _omega_save_database_layout()'), 'error');
        }
        return TRUE;
      }
      else {
        //drupal_set_message(t('The layout <strong>' . $layout_id . '</strong> matches the version already stored at <strong>' . $theme . '.layout.' . $layout_id . '.generated</strong>. No save on this layout was performed.'));
        return FALSE;
      }
    }
  }

  /**
   * @inheritdoc
   */
  public static function saveLayoutFiles($scss, $theme, $layout_id, $options) {
    // create full paths to the scss and css files we will be rendering.
    $layoutscss = realpath(".") . '/' . drupal_get_path('theme', $theme) . '/style/scss/layout/' . $layout_id . '-layout.scss';
    $layoutcss = realpath(".") .  '/' . drupal_get_path('theme', $theme) . '/style/css/layout/' . $layout_id . '-layout.css';

    // save the scss file
    $scssfile = file_unmanaged_save_data($scss, $layoutscss, FILE_EXISTS_REPLACE);
    // check for errors
    if ($scssfile) {
      drupal_set_message(t('SCSS file saved: <strong>' . str_replace(realpath("."), '', $scssfile) . '</strong>'));
    }
    else {
      drupal_set_message(t('WTF001: SCSS save error... : function _omega_save_layout_files()'), 'error');
    }

    // if the Compile SCSS option is enabled, continue
    $compile_scss = theme_get_setting('compile_scss', $theme);
    $compile = isset($compile_scss) ? $compile_scss : FALSE;
    if ($compile) {

      $relativeSource = str_replace(realpath(".") . '/' .  drupal_get_path('theme', $theme), '', $scssfile);
      $options = OmegaStyle::getScssOptions($relativeSource, $scssfile, $theme);
      // generate the CSS from the SCSS created above
      $css = _omega_compile_css($scss, $options);
      // save the css file
      $cssfile = file_unmanaged_save_data($css, $layoutcss, FILE_EXISTS_REPLACE);
      // check for errors
      if ($cssfile) {
        drupal_set_message(t('CSS file saved: <strong>' . str_replace(realpath("."), '', $cssfile) . '</strong>'));
      }
      else {
        drupal_set_message(t('WTF002: CSS save error... : function _omega_save_layout_files()'), 'error');
      }
    }
    // else throw a warning/reminder that it IS disabled and they should be using compass or alternative compiler.
    elseif (theme_get_setting('show_compile_warning', $theme)) {
      drupal_set_message(t("Since <strong>Compile SCSS Directly</strong> is disabled, please ensure Compass or an alternative SCSS compiler is set to watch for these saved changes. You can disable this warning under <strong>Default Options</strong>."), "warning");
    }
  }

  /**
   * @inheritdoc
   */
  public static function exportLayout() {
    // TODO: Implement exportLayout() method.
  }

  /**
   * @inheritdoc
   */
  public static function compileLayout($layout, $layout_id, $theme) {
    // Options for phpsass compiler. Defaults in SassParser.php

    $options = array(
      'style' => 'nested',
      'cache' => FALSE,
      'syntax' => 'scss',
      'debug' => TRUE,
    );

    $scss = OmegaLayout::compileLayoutScss($layout, $layout_id, $theme, $options);
    // save the SCSS and CSS files to the theme's filesystem
    OmegaLayout::saveLayoutFiles($scss, $theme, $layout_id, $options);
  }

  /**
   * @inheritdoc
   */
  public static function compileLayoutScss($layout, $layoutName, $theme = 'omega', $options) {
    // get a list of themes
    $themes = \Drupal::service('theme_handler')->listInfo();
    // get the current settings/info for the theme
    $themeSettings = $themes[$theme];
    // get the default layout/breakpoint group
    $defaultLayout = $layoutName;
    // get all the active breakpoints we'll be editing
    $breakpoints = OmegaLayout::getActiveBreakpoints($layoutName, $theme);
    // get the stored layout data
    // $layouts = theme_get_setting('layouts', $theme);
    // pull an array of "region groups" based on the "all" media query that should always be present
    // @todo consider adjusting this data to be stored in the top level of the $theme.layout.$layout.yml file instead
    $region_groups = $layout['region_groups']['all'];

    $theme_regions = $themeSettings->info['regions'];
    // create variable to hold all SCSS we need
    $scss = '';
    $scss .= "@import 'omega_mixins';\n";
    $scss .= "@import 'omega-default-style-vars';\n";
    $scss .= "@import 'omega-style-vars';\n";
    $scss .= "@import 'omegags';\n";

    // loop over the media queries
    foreach ($breakpoints as $breakpoint) {
      /** @var \Drupal\breakpoint\Breakpoint $breakpoint */
      // create a clean var for the scss for this breakpoint
      $breakpoint_scss = '';
      $idtrim = OmegaLayout::cleanBreakpointId($breakpoint);

      // loop over the region groups
      foreach ($region_groups as $gid => $info) {
        /* add row mixin */
        // @todo change $layout['region_groups'][$idtrim][$gid] to $info
        $rowname = str_replace("_", "-", $gid) . '-layout';
        $rowval = $layout['region_groups'][$idtrim][$gid]['row'];
        $primary_region = $layout['region_groups'][$idtrim][$gid]['primary_region'];
        $total_regions = count($layout['region_groups'][$idtrim][$gid]['regions']);
        $maxwidth = $layout['region_groups'][$idtrim][$gid]['maxwidth'];
        if ($layout['region_groups'][$idtrim][$gid]['maxwidth_type'] == 'pixel') {
          $unit = 'px';
        }
        else {
          $unit = '%';
        }
        if ($maxwidth && $rowval) {
          $breakpoint_scss .= "\n\n  " . '// Breakpoint: ' . $breakpoint->getLabel() . '; Region Group: ' . $gid . ';';
          $breakpoint_scss .= "\n  " . '.' . $rowname . ' {';
          $breakpoint_scss .= "\n    " . '@include row(' . $rowval . ');';
          $breakpoint_scss .= "\n    " . 'max-width: ' . $maxwidth . $unit . ';';
        }
        // loop over regions for basic responsive configuration
        foreach ($layout['region_groups'][$idtrim][$gid]['regions'] as $rid => $data) {
          $regionname = str_replace("_", "-", $rid);
          $breakpoint_scss .= "\n\n    " . '// Breakpoint: ' . $breakpoint->getLabel() . '; Region Group: ' . $gid . '; Region: ' . $rid . ';';
          $breakpoint_scss .= "\n    " . '.region--' . $regionname . ' {';
          $breakpoint_scss .= "\n      " . '@include column(' . $layout['region_groups'][$idtrim][$gid]['regions'][$rid]['width'] . ', ' . $layout['region_groups'][$idtrim][$gid]['row'] . ');';

          if ($layout['region_groups'][$idtrim][$gid]['regions'][$rid]['prefix'] > 0) {
            $breakpoint_scss .= "\n      " . '@include prefix(' . $layout['region_groups'][$idtrim][$gid]['regions'][$rid]['prefix'] . ');';
          }

          if ($layout['region_groups'][$idtrim][$gid]['regions'][$rid]['suffix'] > 0) {
            $breakpoint_scss .= "\n      " . '@include suffix(' . $layout['region_groups'][$idtrim][$gid]['regions'][$rid]['suffix'] . ');';
          }

          if ($layout['region_groups'][$idtrim][$gid]['regions'][$rid]['push'] > 0) {
            $breakpoint_scss .= "\n      " . '@include push(' . $layout['region_groups'][$idtrim][$gid]['regions'][$rid]['push'] . ');';
          }

          if ($layout['region_groups'][$idtrim][$gid]['regions'][$rid]['pull'] > 0) {
            $breakpoint_scss .= "\n      " . '@include pull(' . $layout['region_groups'][$idtrim][$gid]['regions'][$rid]['pull'] . ');';
          }

          $breakpoint_scss .= "\n    " . '}'; // end of initial region configuration
        }
        // check to see if primary region is set
        if ($primary_region && $total_regions <= 3) {
          $breakpoint_scss .= "\n\n    " . '// A primary region exists for the ' . $gid . ' region group.';
          $breakpoint_scss .= "\n    " . '// so we are going to iterate over combinations of available/missing';
          $breakpoint_scss .= "\n    " . '// regions to change the layout for this group based on those scenarios.';

          $breakpoint_scss .= "\n\n    " . '// 1 missing region';

          // loop over the regions that are not the primary one again
          $mainRegion = $layout['region_groups'][$idtrim][$gid]['regions'][$primary_region];
          $otherRegions = $layout['region_groups'][$idtrim][$gid]['regions'];
          unset($otherRegions[$primary_region]);
          $num_otherRegions = count($otherRegions);
          $cols = $layout['region_groups'][$idtrim][$gid]['row'];
          $classMatch = array();
          // in order to ensure the primary region we want to assign extra empty space to
          // exists, we use the .with--region_name class so it would only apply if the
          // primary region is present.
          $classCreate = array(
            '.with--' . $primary_region
          );

          foreach ($otherRegions as $orid => $odata) {

            $classCreate[] = '.without--' . $regionname;
            $regionname = str_replace("_", "-", $orid);
            // combine the region widths
            $adjust = OmegaLayout::layoutAdjust($mainRegion, array($otherRegions[$orid]), $cols);

            $breakpoint_scss .= "\n\n    " . '&.with--' . $primary_region . '.without--' . $regionname . ' {';
            $breakpoint_scss .= "\n      " . '.region--' . $primary_region . ' {';
            $breakpoint_scss .= "\n        " . '@include column-reset();';
            $breakpoint_scss .= "\n        " . '@include column(' . $adjust['width'] . ', ' . $cols . ');';

            // @todo need to adjust for push/pull here
            // ACK!!! .sidebar-first would need push/pull adjusted if
            // the sidebar-second is gone
            // this might be IMPOSSIBLE
            $pushPullAltered = FALSE;

            if ($adjust['pull'] >= 1) {
              $pushPullAltered = TRUE;
              $breakpoint_scss .= "\n        " . '@include pull(' . $adjust['pull'] . ');';
            }

            if ($adjust['push'] >= 1) {
              $pushPullAltered = TRUE;
              $breakpoint_scss .= "\n        " . '@include push(' . $adjust['push'] . ');';
            }

            $breakpoint_scss .= "\n      " . '}' . "\n"; // end of iteration of condition missing one region

            // now what if we adjusted the push/pull of the main region, or the
            // remaining region had a push/pull, we need to re-evaluate the layout for that region
            if ($pushPullAltered) {
              // find that other remaining region.
              $region_other = $otherRegions;
              unset($region_other[$orid]);
              $region_other_keys = array_keys($region_other);
              $region_other_id = $region_other_keys[0];
              $regionname_other = str_replace("_", "-", $region_other_id);
              $otherRegionWidth = $region_other[$region_other_id]['width'];
              $breakpoint_scss .= "\n      " . '.region--' . $regionname_other . ' {';
              $breakpoint_scss .= "\n        " . '@include column-reset();';
              $breakpoint_scss .= "\n        " . '@include column(' . $region_other[$region_other_id]['width'] . ', ' . $cols . ');';

              // APPEARS to position the remaining (not primary) region
              // BUT the primary region is positioned wrong with push/pull
              // if there is a pull on the primary region, we adjust the push on the remaining one
              if ($adjust['pull'] >= 1) {
                $pushPullAltered = TRUE;
                $breakpoint_scss .= "\n        " . '@include push(' . $adjust['width'] . ');';
              }
              // if there is a push on the primary region, we adjust the pull on the remaining one
              if ($adjust['push'] >= 1) {
                $pushPullAltered = TRUE;
                $breakpoint_scss .= "\n        " . '@include pull(' . $adjust['width'] . ');';
              }

              $breakpoint_scss .= "\n      " . '}'; // end of iteration of condition missing one region
            }

            $breakpoint_scss .= "\n    " . '}'; // end of intial loop of regions to assign individual cases of missing regions first in the scss/css
          } /* end foreach loop*/

          // throw a comment in the scss
          $breakpoint_scss .= "\n\n    " . '// 2 missing regions';

          // here we are beginning to loop again, assuming more than just
          // one region might be missing and to assign to the primary_region accordingly
          $classMatch = array();

          // loop the "other" regions that aren't the primary one again
          foreach ($otherRegions as $orid => $odata) {
            $regionname = str_replace("_", "-", $orid);

            // now that we are looping, we will loop again to then create
            foreach ($otherRegions as $orid2 => $odata2) {
              $regionname2 = str_replace("_", "-", $orid2);
              $notYetMatched = TRUE;

              if ($regionname != $regionname2) {
                $attemptedTest = array(
                  '.with--' . $primary_region,
                  '.without--' . $regionname,
                  '.without--' . $regionname2,
                );
                asort($attemptedTest);
                $attemptedMatch = implode('', $attemptedTest);

                if (in_array($attemptedMatch, $classMatch)) {
                  $notYetMatched = FALSE;
                }

                $adjust = OmegaLayout::layoutAdjust($mainRegion, array(
                  $otherRegions[$orid],
                  $otherRegions[$orid2]
                ), $cols);

                if ($notYetMatched) {
                  $classCreate = '.with--' . $primary_region . '.without--' . $regionname . '.without--' . $regionname2;

                  $classMatch[] = $attemptedMatch;

                  if (count($classMatch) >= 1) {

                  }

                  $breakpoint_scss .= "\n    " . '&' . $classCreate . ' {';
                  $breakpoint_scss .= "\n      " . '.region--' . $primary_region . ' {';
                  $breakpoint_scss .= "\n        " . '@include column-reset();';
                  $breakpoint_scss .= "\n        " . '@include column(' . $adjust['width'] . ', ' . $cols . ');';

                  // @todo need to adjust for push/pull here
                  $breakpoint_scss .= "\n      " . '}';
                  $breakpoint_scss .= "\n    " . '}';
                } // end if ($notYetMatched)
              } // end if ($regionname != $regionname2)
            } // end foreach $otherRegions (2nd loop)
          }  // end foreach $otherRegions (1st loop)
        }  // end if($primary_region)
        $breakpoint_scss .= "\n  " . '}'; // end of region group
      }
      // if not the defualt media query that should apply to all screens
      // we will wrap the scss we've generated in the appropriate media query.
      if ($breakpoint->getLabel() != 'all') {
        $breakpoint_scss = "\n" . '@media ' . $breakpoint->getMediaQuery() . ' {' . $breakpoint_scss . "\n" . '}';
      }
      // add in the SCSS from this breakpoint and add to our SCSS
      $scss .= $breakpoint_scss . "\n"; // add newline at eof
    }
    return $scss;
  }

  /**
   * @inheritdoc
   */
  public static function compileLayoutCss() {
    // TODO: Implement compileLayoutCss() method.
  }

  /**
   * @inheritdoc
   */
  public static function generateLayout() {
    // TODO: Implement generateLayout() method.
  }

  /**
   * @inheritdoc
   */
  public static function getAvailableLayouts($theme) {
    // grab the defined layouts in config/install/$theme.layouts.yml
    $layouts = \Drupal::config($theme . '.omega-layouts')->get();
    foreach ($layouts AS $layout => $null) {
      // grab the configuration for the requested layout
      $layout_config_object = \Drupal::config($theme . '.layout.' . $layout);
      // assign the values to our array
      $layouts[$layout] = $layout_config_object->get();
      unset($layouts[$layout]['_core']);
    }
    unset($layouts['_core']);
    return $layouts;
  }

  /**
   * @inheritdoc
   */
  public static function getAvailableLayoutFormOptions($layouts) {
    $options = array();
    foreach ($layouts as $id => $info) {
      $options[$id] = $id;
    }
    return $options;
  }

  /**
   * @inheritdoc
   */
  public static function getActiveLayout() {
    // The active theme being used
    $theme = \Drupal::theme()->getActiveTheme()->getName();
    // Is this page the front page?
    $front = \Drupal::service('path.matcher')->isFrontPage() ? \Drupal::service('path.matcher')->isFrontPage() : FALSE;
    // Is this page a node?
    $nid = \Drupal::routeMatch()->getRawParameter('node') ? \Drupal::routeMatch()->getRawParameter('node') : FALSE;
    // Is this page a taxonomy term?
    $term = \Drupal::routeMatch()->getParameter('taxonomy_term') ? \Drupal::routeMatch()->getParameter('taxonomy_term') : FALSE;
    // Is this page a view?
    $view_id = \Drupal::routeMatch()->getParameter('view_id') ? \Drupal::routeMatch()->getParameter('view_id') : FALSE;
    // All parameters for the page
    $params = \Drupal::routeMatch()->getParameters();
    $layoutProvider = OmegaLayout::getLayoutProvider($theme);
    // setup default layout
    $defaultLayout = theme_get_setting('default_layout', $layoutProvider);
    $layout = $defaultLayout;

    // if it is a node, check for and assign alternate layout
    if ($nid) {
      /** @var \Drupal\node\Entity\Node $node */
      $node = \Drupal\node\Entity\Node::load($nid);
      $type = $node->getType();
      $nodeLayout = theme_get_setting('node_type_' . $type . '_layout', $layoutProvider);
      $layout = $nodeLayout ? $nodeLayout : $defaultLayout;
    }

    // if it is a views page, check for and assign alternate layout

    if ($view_id) {
      // @todo: Ensure this views integration is flexible.
      // Grab the string value of the display_id parameter
      $view_display_id = \Drupal::routeMatch()->getParameter('display_id');
      $view_layout_id = 'views_view_' . $view_id . '_' . $view_display_id . '_layout';
      $viewLayout = theme_get_setting($view_layout_id);
      $layout = $viewLayout ? $viewLayout : $defaultLayout;
    }

    // if it is a term page, check for and assign alternate layout
    // @todo: Since this view is more specific than the VIEW taxonomy_term_page_1, we should remove that view from the options in the theme settings.
    if ($term) {
      $vocab = $term->getVocabularyId();
      $vocabLayout = theme_get_setting('taxonomy_' . $vocab . '_layout');
      $layout = $vocabLayout ? $vocabLayout : $defaultLayout;
    }

    // if it is the front page, check for an alternate layout
    // this should come AFTER all other adjustments
    // This ensures if someone has set an individual node page, term page, etc.
    // as the front page, the front page setting has more priority
    if ($front) {
      $homeLayout = theme_get_setting('home_layout', $layoutProvider);
      $layout = $homeLayout ? $homeLayout : $defaultLayout;
    }

    return array(
      'theme' => $layoutProvider,
      'layout' => $layout,
    );
  }

  /**
   * @inheritdoc
   */
  public static function getLayoutProvider($theme) {
    // Create Omega Settings Object
    $omegaSettings = new OmegaSettingsInfo($theme);

    // get the default settings for the current theme
    $themeSettings = $omegaSettings->getThemeInfo();

    // get the value of 'inherit_layout' from THEME.info.yml
    $inherit_layout = isset($themeSettings->info['inherit_layout']) ? $themeSettings->info['inherit_layout'] : FALSE;

    // we have encountered a theme that inherits layout from a base theme
    // now we will scan the array of applicable base themes looking for the
    // closest parent providing layout and not inheriting it
    if ($inherit_layout) {
      // grab the base themes
      $baseThemes = $themeSettings->base_themes;
      // remove the core themes from the list
      unset($baseThemes['stable'], $baseThemes['classy']);
      // put the base themes in the proper order to traverse for layouts
      $baseThemes = array_reverse($baseThemes);

      foreach ($baseThemes AS $baseKey => $baseName) {
        $baseThemeSettings = $omegaSettings->getThemeInfo($baseKey);
        $base_inherit_layout = $baseThemeSettings->info['inherit_layout'];

        if (!$base_inherit_layout) {
          // we've found the first base theme in the chain that does provide its own layout
          // so we will return the key of that theme to use.
          return $baseKey;
        }
      }

    }
    // this theme provides its own layout, so just return the appropriate theme name
    else {
      return $theme;
    }
    return FALSE;
  }

  /**
   * @inheritdoc
   */
  public static function getAvailableBreakpoints($theme) {
    // Check for breakpoints module and set a warning and a flag to disable much of the theme settings if its not available
    $breakpoints_module = \Drupal::moduleHandler()->moduleExists('breakpoint');
    $breakpoint_groups = array();
    $breakpoint_options = array();
    if ($breakpoints_module == TRUE) {
      // get all the breakpoint groups available to Drupal
      $all_breakpoint_groups = \Drupal::service('breakpoint.manager')->getGroups();
      // get all the base themes of this theme
      $baseThemes = \Drupal::theme()->getActiveTheme()->getBaseThemes();

      $theme_ids = array(
        $theme => \Drupal::theme()->getActiveTheme()->getExtension()->info['name']
      );
      foreach ($baseThemes AS $theme_key => $data) {
        // create/add to array with base themes as values
        $clean_theme_name = $data->getExtension()->info['name'];
        $theme_ids[$theme_key] = $clean_theme_name;
      }

      // cycle all the breakpoint groups and see if they are a part of this theme or its base theme(s)
      foreach ($all_breakpoint_groups as $group_key => $group_values) {
        // get the theme name that provides this breakpoint group
        $breakpoint_theme = \Drupal::service('breakpoint.manager')->getGroupProviders($group_key);
        // see if the theme providing the breakpoint group is part of our base theme structure
        $breakpoint_theme_name = key($breakpoint_theme);
        if (array_key_exists($breakpoint_theme_name, $theme_ids)) {
          $breakpoint_groups[$group_key] = \Drupal::service('breakpoint.manager')->getBreakpointsByGroup($group_key);
        }
      }

      foreach ($breakpoint_groups as $group => $breakpoint_values) {
        if ($breakpoint_values !== array()) {
          // get the theme name that provides this breakpoint group
          $breakpoint_theme = \Drupal::service('breakpoint.manager')->getGroupProviders($group);
          // see if the theme providing the breakpoint group is part of our base theme structure
          $breakpoint_theme_id = key($breakpoint_theme);
          $breakpoint_theme_name = $theme_ids[$breakpoint_theme_id];
          $breakpoint_options[$breakpoint_theme_name][$group] = $group;
        }
      }
    }
    else {
      drupal_set_message(t('Omega requires the <b>Breakpoint module</b>. Open the <a href="@extendpage" target="_blank">Extend</a> page and enable Breakpoint.', array('@extendpage' => base_path() . 'admin/modules')), 'warning');
    }
    return $breakpoint_options;
  }

  /**
   * @inheritdoc
   */
  public static function getActiveBreakpoints($layout, $theme) {
    // get the default layout and convert to name for breakpoint group
    $breakpointGroupId = theme_get_setting('breakpoint_group_' . $layout, $theme);
    $breakpointGroup = \Drupal::service('breakpoint.manager')->getBreakpointsByGroup($breakpointGroupId);
    if ($breakpointGroup) {
      // custom theme breakpoints
      return $breakpointGroup;
    }
    else {
      // default omega breakpoints
      drupal_set_message('The breakpoint group for your theme could not be found. Using default Omega version instead.', 'warning');
      return \Drupal::service('breakpoint.manager')->getBreakpointsByGroup('omega.standard');
    }
  }

  /**
   * @inheritdoc
   */
  public static function layoutAdjust($main, $empty_regions = array(), $cols) {
    // assign values from $main region's data
    $original_prefix = $prefix = $main['prefix'];
    $original_pull = $pull = $main['pull'];
    $original_width = $width = $main['width'];
    $original_push = $push = $main['push'];
    $original_suffix = $suffix = $main['suffix'];

    foreach ($empty_regions as $rid => $data) {
      /* Calculate the width */
      // Add the width, prefix & suffix of the regions we are combining.
      // This creates the "true" width of the primary regions.
      $newActualWidth = $data['width'] + $data['prefix'] + $data['suffix'] + $width;
      // Reassign the $width variable.
      $width = $newActualWidth;
      // This ensures if the primary region has a prefix/suffix, they are calculated too.
      // When ensuring that the region doesn't have more columns than the container.
      $newTotalWidth = $newActualWidth + $prefix + $suffix;

      /* END EARLY IF WIDTH IS TOO WIDE */

      // if the columns combine to be wider than the row, set the max columns
      // and remove all push/pull/prefix/suffix values
      if ($newTotalWidth > $cols) {
        return array(
          'width' => $cols,
          'prefix' => 0,
          'suffix' => 0,
          'push' => 0,
          'pull' => 0,
        );
      }

      /* Calculate updates for the push/pull */
      if ($data['push'] >= 1) {

        // appears these regions were swapped, compensate by removing the push/pull
        if ($data['push'] == $original_width && $data['width'] == $original_pull) {
          $pull = 0;
        }

        // assume now that BOTH other regions were pushed
        if ($original_pull > $data['width']) {
          $pull = $cols - $width;
        }
      }

      if ($data['pull'] >= 1) {
        // appears these regions were swapped, compensate by removing the push/pull
        if ($data['pull'] == $original_width && $data['width'] == $original_push) {
          $push = 0;
        }

        // assume now that BOTH other regions were pushed
        if ($original_push > $data['width']) {
          $push = $cols - $width;
        }
      }

      // Calculate the prefix/suffix
      /**
       * We don't actually need to do this as the prefix/suffix is added to the actual
       * width of the primary region rather than adding/subtracting additional margins.
       */
    }

    return array(
      'width' => $width,
      'prefix' => $prefix,
      'suffix' => $suffix,
      'push' => $push,
      'pull' => $pull,
    );
  }

  /**
   * @inheritdoc
   */
  public static function cleanBreakpointId(Breakpoint $breakpoint) {
    return str_replace($breakpoint->getGroup() . '.', "", $breakpoint->getBaseId());
  }
}
