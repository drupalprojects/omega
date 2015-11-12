<?php

use Drupal\omega\phpsass\SassParser;
use Drupal\omega\phpsass\SassFile;
// Include Breakpoint Functionality
use Drupal\breakpoint;

function omega_return_clean_breakpoint_id($breakpoint) {
  return str_replace($breakpoint->getGroup() . '.', "", $breakpoint->getBaseId());
}

/**
 * Custom function to return the available layouts (and config) for a given Omega theme/subtheme
 */
function omega_return_layouts($theme) {
  
  // grab the defined layouts in config/install/$theme.layouts.yml
  $layouts = \Drupal::config($theme . '.layouts')->get();
  
  foreach ($layouts AS $layout => $null) {
    // grab the configuration for the requested layout
    $layout_config_object = \Drupal::config($theme . '.layout.' . $layout);
    // assign the values to our array
    $layouts[$layout] = $layout_config_object->get();
  }
  return $layouts;
}
// returns select field options for the available layouts
function _omega_layout_select_options($layouts) {
  $options = array();
  foreach($layouts as $id => $info) {
    //$options[$id] = $info['theme'] . '--' . $info['name'];
    $options[$id] = $id;
  }
  //dsm($options);
  return $options;
}

/**
 * Custom function to return the active layout to be used for the active page.
 */
function omega_return_active_layout() {
  $theme = \Drupal::theme()->getActiveTheme()->getName();
  
  //$front = drupal_is_front_page();
  //$node = menu_get_object();

  // setup default layout
  $defaultLayout = theme_get_setting('default_layout', $theme);
  $layout = $defaultLayout;
  
  /*
  // if it is a node, check for an alternate layout
  if ($node) {
    $type = $node->type;
    $nodeLayout = theme_get_setting($type . '_layout', $theme);
    $layout = $nodeLayout ? $nodeLayout : $defaultLayout;
  }
  // if it is the front page, check for an alternate layout
  if ($front) {
    $homeLayout = theme_get_setting('home_layout', $theme);
    $layout = $homeLayout ? $homeLayout : $defaultLayout;
  }
  */
  
  return $layout;
}

/** 
 *  Returns array of optional Libraries that can be enabled/disabled in theme settings
 *  for Omega, and Omega sub-themes. The listings here are tied to entries in omega.libraries.yml.
 */
function _omega_optional_css($theme) {
  $status = theme_get_setting('styles', $theme);
  
  return array(
    'scss_html_elements' => array(
      'title' => 'Generic HTML Elements',
      'description' => 'Provides basic styles for generic tags like &lt;a&gt;, &lt;p&gt;, &lt;h2&gt;, etc.',
      'library' => 'omega/omega_html_elements',
      'status' => $status['scss_html_elements'],
    ),
    
    'scss_branding' => array(
      'title' => 'Branding Styles',
      'description' => 'Provides basic layout and styling for logo area.',
      'library' => 'omega/omega_branding',
      'status' => $status['scss_branding'],
    ),
    
    'scss_breadcrumbs' => array(
      'title' => 'Breadcrumbs',
      'description' => 'Basic breadcrumb styling.',
      'library' => 'omega/omega_breadcrumbs',
      'status' => $status['scss_breadcrumbs'],
    ),
    
    'scss_main_menus' => array(
      'title' => 'Main Menu Styling',
      'description' => 'Basic layout and styling for main menu elements.',
      'library' => 'omega/omega_main_menus',
      'status' => $status['scss_main_menus'],
    ),
    'scss_messages' => array(
      'title' => 'Messages',
      'description' => 'Custom styles for Drupal system messages.',
      'library' => 'omega/omega_messages',
      'status' => $status['scss_messages'],
    ),
    'scss_pagers' => array(
      'title' => 'Pagers',
      'description' => 'Custom styles for Drupal pagers.',
      'library' => 'omega/omega_pagers',
      'status' => $status['scss_pagers'],
    ),
    'scss_tabs' => array(
      'title' => 'Local Task Tabs',
      'description' => 'Custom styles for Drupal tabs.',
      'library' => 'omega/omega_tabs',
      'status' => $status['scss_tabs'],
    ),
  );
}

function _omega_getActiveBreakpoints($theme) {
  // get the default layout and convert to name for breakpoint group
  $breakpointGroupId = str_replace("_", ".", theme_get_setting('default_layout', $theme));
  $breakpointGroup = \Drupal::service('breakpoint.manager')->getBreakpointsByGroup($breakpointGroupId);
  if ($breakpointGroup) {
    // custom theme breakpoints
    return $breakpointGroup;
  }
  else {
    // default omega breakpoints
    return \Drupal::service('breakpoint.manager')->getBreakpointsByGroup('omega.standard');
  }
}



function _omega_save_database_layout($layout, $layout_id, $theme) {
  // Grab the editable configuration object
  $layoutConfig = \Drupal::service('config.factory')->getEditable($theme . '.layout.' . $layout_id);
  // Set the value to $layout
  $layoutConfig->setData($layout);
  // Save it
  $saved = $layoutConfig->save();
  // check for errors
  if ($saved) {
    drupal_set_message(t('Layout configuration saved: <strong>'.$theme . '.layout.' . $layout_id.'</strong>'));
  }
  else {
    drupal_set_message(t('WTF002: Layout configuration error... : function _omega_save_database_layout()'), 'error');
  }
}

function _omega_compile_layout_css($scss, $options) {
  $parser = new SassParser($options);
  // create CSS from SCSS
  $css = $parser->toCss($scss, false);
  return $css;
}

function _omega_compile_layout_sass($layout, $layoutName, $theme = 'omega', $options) {
  //dsm($layout);
  // get a list of themes
  $themes = \Drupal::service('theme_handler')->listInfo();
  // get the current settings/info for the theme
  $themeSettings = $themes[$theme];
  // get the default layout/breakpoint group
  $defaultLayout = $layoutName;
  // get all the active breakpoints we'll be editing
  $breakpoints = _omega_getActiveBreakpoints($theme);
  // get the stored layout data
  // $layouts = theme_get_setting('layouts', $theme);
  // pull an array of "region groups" based on the "all" media query that should always be present
  // @todo consider adjusting this data to be stored in the top level of the $theme.layout.$layout.yml file instead
  $region_groups = $layout['region_groups']['all'];
  //dsm($region_groups);
  //dsm($layouts);
  $theme_regions = $themeSettings->info['regions'];
  // create variable to hold all SCSS we need
  $scss = '';
 
  $parser = new SassParser($options);
  
  // get the variables for the theme
  $vars = realpath(".") . base_path() . drupal_get_path('theme', 'omega') . '/style/scss/vars.scss';
  $omegavars = new SassFile;
  $varscss = $omegavars->get_file_contents($vars, $parser);
  // set the grid to fluid
  $varscss .= '$twidth: 100%;';
  
  // get the SCSS for the grid system
  $gs = realpath(".") . base_path() . drupal_get_path('theme', 'omega') . '/style/scss/grids/omega.scss';
  $omegags = new SassFile;
  $gsscss = $omegags->get_file_contents($gs, $parser);
  $scss = $varscss . $gsscss;  
  

  // loop over the media queries
  foreach($breakpoints as $breakpoint) {
    // create a clean var for the scss for this breakpoint
    $breakpoint_scss = '';
    $idtrim = omega_return_clean_breakpoint_id($breakpoint);
    
    // loop over the region groups
    foreach ($region_groups as $gid => $info ) {
      // add row mixin



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
      
// FORMATTED INTENTIONALLY
      $breakpoint_scss .= '
// Breakpoint: ' . $breakpoint->getLabel() . '; Region Group: ' . $gid . ';
.' . $rowname . ' { 
  @include row(' . $rowval . ');
  max-width: '. $maxwidth . $unit .';
';
// END FORMATTED INTENTIONALLY
      // loop over regions for basic responsive configuration
      foreach($layout['region_groups'][$idtrim][$gid]['regions'] as $rid => $data) {
        $regionname = str_replace("_", "-", $rid);
// FORMATTED INTENTIONALLY        
        $breakpoint_scss .= '
  // Breakpoint: ' . $breakpoint->getLabel() . '; Region Group: ' . $gid . '; Region: ' . $rid . ';
  .region--' . $regionname . ' { 
    @include column(' . $layout['region_groups'][$idtrim][$gid]['regions'][$rid]['width'] . ', ' . $layout['region_groups'][$idtrim][$gid]['row'] . '); ';
        
        if ($layout['region_groups'][$idtrim][$gid]['regions'][$rid]['prefix'] > 0) {
          $breakpoint_scss .= '  
    @include prefix(' . $layout['region_groups'][$idtrim][$gid]['regions'][$rid]['prefix'] . '); ';  
        }
        
        if ($layout['region_groups'][$idtrim][$gid]['regions'][$rid]['suffix'] > 0) {
        $breakpoint_scss .= '  
    @include suffix(' . $layout['region_groups'][$idtrim][$gid]['regions'][$rid]['suffix'] . '); ';
        }
        
        if ($layout['region_groups'][$idtrim][$gid]['regions'][$rid]['push'] > 0) {
        $breakpoint_scss .= '  
    @include push(' . $layout['region_groups'][$idtrim][$gid]['regions'][$rid]['push'] . '); ';
        }
        
        if ($layout['region_groups'][$idtrim][$gid]['regions'][$rid]['pull'] > 0) {
        $breakpoint_scss .= '  
    @include pull(' . $layout['region_groups'][$idtrim][$gid]['regions'][$rid]['pull'] . '); ';
        }
        
        $breakpoint_scss .= '
    margin-bottom: $regionSpacing;
  } 
'; // end of initial region configuration
// END FORMATTED INTENTIONALLY        
      }
      // check to see if primary region is set
      if ($primary_region && $total_regions <= 3) {
// FORMATTED INTENTIONALLY        
        $breakpoint_scss .= '
  // A primary region exists for the '. $gid .' region group.
  // so we are going to iterate over combinations of available/missing
  // regions to change the layout for this group based on those scenarios.
  
  // 1 missing region
';
// END FORMATTED INTENTIONALLY
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
          '.with--'. $primary_region
        );
        
        foreach($otherRegions as $orid => $odata) {
          
          $classCreate[] = '.without--' . $regionname;
          $regionname = str_replace("_", "-", $orid);
          // combine the region widths
          
          
          
          $adjust = _omega_layout_generation_adjust($mainRegion, array($otherRegions[$orid]), $cols);
          
          
// FORMATTED INTENTIONALLY          
          $breakpoint_scss .= '
  &.with--'. $primary_region . '.without--' . $regionname .' {
    .region--' . $primary_region . ' {
      @include column-reset();
      @include column(' . $adjust['width'] . ', ' . $cols . ');';
// END FORMATTED INTENTIONALLY          
          
      // @todo need to adjust for push/pull here
      
      
      // ACK!!! .sidebar-first would need push/pull adjusted if 
      // the sidebar-second is gone
      // this might be IMPOSSIBLE
      
      $pushPullAltered = FALSE;
      
      if ($adjust['pull'] >= 1) {
// FORMATTED INTENTIONALLY          
          $pushPullAltered = TRUE;
          $breakpoint_scss .= '
      @include pull(' . $adjust['pull'] . ');';
// END FORMATTED INTENTIONALLY        
      }
      
      if ($adjust['push'] >= 1) {
// FORMATTED INTENTIONALLY          
          $pushPullAltered = TRUE;
          $breakpoint_scss .= '
      @include push(' . $adjust['push'] . ');';
// END FORMATTED INTENTIONALLY        
      }
      
// FORMATTED INTENTIONALLY          
          $breakpoint_scss .= '
    }'; // end of iteration of condition missing one region
// END FORMATTED INTENTIONALLY
        
        
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
            
            
            $breakpoint_scss .= '
    .region--' . $regionname_other . ' {
      @include column-reset();
      @include column(' . $region_other[$region_other_id]['width'] . ', ' . $cols . ');';
// END FORMATTED INTENTIONALLY
            
            
            
            
            // APPEARS to position the remaining (not primary) region
            // BUT the primary region is positioned wrong with push/pull
            
            
            
            // if there is a pull on the primary region, we adjust the push on the remaining one
            if ($adjust['pull'] >= 1) {
// FORMATTED INTENTIONALLY          
              $pushPullAltered = TRUE;
              $breakpoint_scss .= '
      @include push(' . $adjust['width'] . ');';
// END FORMATTED INTENTIONALLY        
            }
            // if there is a push on the primary region, we adjust the pull on the remaining one
            if ($adjust['push'] >= 1) {
// FORMATTED INTENTIONALLY          
              $pushPullAltered = TRUE;
              $breakpoint_scss .= '
      @include pull(' . $adjust['width'] . ');';
// END FORMATTED INTENTIONALLY        
            }
            
            
// FORMATTED INTENTIONALLY          
          $breakpoint_scss .= '
    }'; // end of iteration of condition missing one region
// END FORMATTED INTENTIONALLY
          }
          
        
// FORMATTED INTENTIONALLY
        $breakpoint_scss .= '
  }
'; // end of intial loop of regions to assign individual cases of missing regions first in the scss/css
// END FORMATTED INTENTIONALLY
        
        
        
        } // end foreach loop
        
// FORMATTED INTENTIONALLY
        // throw a comment in the scss
        $breakpoint_scss .= '
  // 2 missing regions
';
// END FORMATTED INTENTIONALLY





          // here we are beginning to loop again, assuming more than just 
          // one region might be missing and to assign to the primary_region accordingly
          
          $classMatch = array();
          //$classCreate = array();
          
          // loop the "other" regions that aren't the primary one again
          foreach($otherRegions as $orid => $odata) {
            $regionname = str_replace("_", "-", $orid);
            
            //$classCreate[] = '.with--'. $primary_region . '.without--' . $regionname;
            
            // now that we are looping, we will loop again to then create
            // .without--sidebar-first.without--sidebar-second.without--sidebar-second
            foreach($otherRegions as $orid2 => $odata2) {
              $regionname2 = str_replace("_", "-", $orid2);
              $notYetMatched = TRUE;
              
              
              if ($regionname != $regionname2) {
                $attemptedTest = array(
                  '.with--'. $primary_region,
                  '.without--' . $regionname,
                  '.without--' . $regionname2,
                );
                asort($attemptedTest);
                //dsm($attemptedTest);
                $attemptedMatch = implode('', $attemptedTest);
                //asort()
                
                if (in_array($attemptedMatch, $classMatch)) {
                  $notYetMatched = FALSE;  
                }
                
                
                
                
                $adjust = _omega_layout_generation_adjust($mainRegion, array($otherRegions[$orid], $otherRegions[$orid2]), $cols);
                
                if ($notYetMatched) {
                  $classCreate = '.with--'. $primary_region . '.without--' . $regionname . '.without--' . $regionname2;
                  
                  
                  $classMatch[] = $attemptedMatch;
                  
                  if (count($classMatch) >= 1) {
                    //dsm($classMatch);  
                  }

            
// FORMATTED INTENTIONALLY          
                  $breakpoint_scss .= '
  &' . $classCreate . ' {
    .region--' . $primary_region . ' {
      @include column-reset();
      @include column(' . $adjust['width'] . ', ' . $cols . ');
';
// END FORMATTED INTENTIONALLY          
          
      // @todo need to adjust for push/pull here

// FORMATTED INTENTIONALLY          
          $breakpoint_scss .= '
    }'; 
// END FORMATTED INTENTIONALLY

// FORMATTED INTENTIONALLY
              $breakpoint_scss .= '
  }
'; 
// END FORMATTED INTENTIONALLY
                } // end if ($notYetMatched)
              } // end if ($regionname != $regionname2)
            
            
              
              
            
            
            
            } // end foreach $otherRegions (2nd loop)
          }  // end foreach $otherRegions (1st loop)
          
          
          
        }  // end if($primary_region)
// FORMATTED INTENTIONALLY      
      $breakpoint_scss .= '
}
'; // end of region group
// END FORMATTED INTENTIONALLY
      
    }
    
    // if not the defualt media query that should apply to all screens
    // we will wrap the scss we've generated in the appropriate media query.
    if ($breakpoint->getLabel() != 'all') {
      $breakpoint_scss = '@media ' . $breakpoint->getMediaQuery() . ' { ' . $breakpoint_scss . '
}
';
    }
    
    // add in the SCSS from this breakpoint and add to our SCSS
    $scss .= $breakpoint_scss;
    //dsm($breakpoint_scss);
  }
  return $scss;
}

/**
 * Function to take SCSS/CSS data and save to appropriate files
 */ 

function _omega_save_layout_files($scss, $css, $theme, $layout_id) {
  // create full paths to the scss and css files we will be rendering.
  $layoutscss = realpath(".") . base_path() . drupal_get_path('theme', $theme) . '/style/scss/layout/' . $layout_id . '-layout.scss';
  $layoutcss = realpath(".") . base_path() . drupal_get_path('theme', $theme) . '/style/css/layout/' . $layout_id . '-layout.css';
  
  // save the scss file
  $scssfile = file_unmanaged_save_data($scss, $layoutscss, FILE_EXISTS_REPLACE);
  // check for errors
  if ($scssfile) {
    drupal_set_message(t('SCSS file saved: <strong>'. str_replace(realpath(".") . base_path(), "", $scssfile) .'</strong>'));
  }
  else {
    drupal_set_message(t('WTF001: SCSS save error... : function _omega_save_layout_files()'), 'error');
  }
  
  // save the css file
  $cssfile = file_unmanaged_save_data($css, $layoutcss, FILE_EXISTS_REPLACE);
  // check for errors
  if ($cssfile) {
    drupal_set_message(t('CSS file saved: <strong>'.str_replace(realpath(".") . base_path(), "", $cssfile).'</strong>'));
  }
  else {
    drupal_set_message(t('WTF002: CSS save error... : function _omega_save_layout_files()'), 'error');
  }
}



/**
 * Helper function to calculate the new width/push/pull/prefix/suffix of a primary region 
 * $main is the primary region for a group which will actually be the one we are adjusting
 * $empty_regions is an array of region data for regions that would be empty
 * $cols is the total number of columns assigned using row(); for the region group
 * 
 * @return array()
 * array contains width, push, pull, prefix and suffix of adjusted primary region
 */
function _omega_layout_generation_adjust($main, $empty_regions = array(), $cols) {
  // assign values from $main region's data
  $original_prefix = $prefix = $main['prefix'];
  $original_pull = $pull = $main['pull'];
  $original_width = $width = $main['width'];
  $original_push = $push = $main['push'];
  $original_suffix = $suffix = $main['suffix'];
  
  foreach($empty_regions as $rid => $data) {
    
    
    /* Calculate the width */
    
    // add the width, prefix & suffix of the regions we are combining
    // this creates the "true" width of the primary regions
    $newActualWidth = $data['width'] + $data['prefix'] + $data['suffix'] + $width;
    // reassign the $width variable
    $width = $newActualWidth;
    // this ensures if the primary region has a prefix/suffix, they are calculated too
    // when ensuring that the region doesn't have more columns than the container.
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
    
    /* Calculate the prefix/suffix */
    // we don't actually need to do this as the prefix/suffix is added to the actual 
    // width of the primary region rather than adding/subtracting additional margings.
    
    
  }
  
  return array(
    'width' => $width,
    'prefix' => $prefix,
    'suffix' => $suffix,
    'push' => $push,
    'pull' => $pull,
  );
}