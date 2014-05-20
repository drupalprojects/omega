<?php

use Drupal\omega\phpsass\SassParser;
use Drupal\omega\phpsass\SassFile;

function _omega_optional_css($theme) {
  $status = theme_get_setting('styles', $theme);
  //dsm($status);
  
  return array(
    'scss_html_elements' => array(
      'title' => 'Generic HTML Elements',
      'description' => 'Provides basic styles for generic tags like &lt;a&gt;, &lt;p&gt;, &lt;h2&gt;, etc.',
      'file' => 'html-elements.css',
      'status' => $status['scss_html_elements'],
    ),
    
    'scss_branding' => array(
      'title' => 'Branding Styles',
      'description' => 'Provides basic layout and styling for logo area.',
      'file' => 'site-branding.css',
      'status' => $status['scss_branding'],
    ),
    
    'scss_breadcrumbs' => array(
      'title' => 'Breadcrumbs',
      'description' => 'Basic breadcrumb styling.',
      'file' => 'breadcrumbs.css',
      'status' => $status['scss_breadcrumbs'],
    ),
    
    'scss_main_menus' => array(
      'title' => 'Main Menu Styling',
      'description' => 'Basic layout and styling for main menu elements.',
      'file' => 'main-menus.css',
      'status' => $status['scss_main_menus'],
    ),
    'scss_messages' => array(
      'title' => 'Messages',
      'description' => 'Custom styles for Drupal system messages.',
      'file' => 'messages.css',
      'status' => $status['scss_messages'],
    ),
    'scss_pagers' => array(
      'title' => 'Pagers',
      'description' => 'Custom styles for Drupal pagers.',
      'file' => 'pagers.css',
      'status' => $status['scss_pagers'],
    ),
    'scss_tabs' => array(
      'title' => 'Local Task Tabs',
      'description' => 'Custom styles for Drupal tabs.',
      'file' => 'tabs.css',
      'status' => $status['scss_tabs'],
    ),
  );
}

function _omega_getBreakpointId($theme) {
  // get the appropriate id based on theme name
  if (entity_load('breakpoint_group', 'theme.'.$theme.'.'.$theme)) {
    // custom theme breakpoints
    return 'theme.'.$theme.'.'.$theme;
  }
  else {
    // default omega breakpoints
    return 'theme.omega.omega';
  }
}

function _omega_compile_layout_sass($layout, $theme = 'omega', $options) {
  //dsm('Running custom function "_omega_compile_layout_sass()" which will generate the appropriate scss to be passed to the parser.');
  //$scss = '$color: #FF0000; $size: 14px; .colored { color: $color; text-decoration: underline; font-size: $size; }';
  //dsm($layout);
  // get a list of themes
  $themes = list_themes();
  // get the default BreakpointGroupID
  $breakpointGroupId = _omega_getBreakpointId($theme);
  // Load the BreakpointGroup and it's Breakpoints
  $breakpointGroup = entity_load('breakpoint_group', $breakpointGroupId);
  $breakpoints = $breakpointGroup->getBreakpoints();

  $themeSettings = $themes[$theme];
  $defaultLayout = theme_get_setting('default_layout', $theme);
  $layouts = theme_get_setting('layouts', $theme);

  // pull an array of "region groups" based on the "all" media query that should always be present
  $region_groups = $layouts[$defaultLayout]['region_groups']['all'];
  //dsm($region_groups);
  //dsm($layouts);
  $theme_regions = $themeSettings->info['regions'];
  
  // create variable to hold all SCSS we need
  $scss = '';
  
  
  
  global $base_path;
  //dsm(realpath(".") . $base_path);
  // Options for phpsass compiler. Defaults in SassParser.php
  




  $parser = new SassParser($options);
  
  // get the variables for the theme
  $vars = realpath(".") . $base_path . drupal_get_path('theme', 'omega') . '/style/scss/vars.scss';
  $omegavars = new SassFile;
  $varscss = $omegavars->get_file_contents($vars, $parser);
  // set the grid to fluid
  $varscss .= '$twidth: 100%;';
  
  // get the SCSS for the grid system
  $gs = realpath(".") . $base_path . drupal_get_path('theme', 'omega') . '/style/scss/grids/omega.scss';
  $omegags = new SassFile;
  $gsscss = $omegags->get_file_contents($gs, $parser);

  $scss = $varscss . $gsscss;  
  //$scss .= '#content { @include column(8); } #sidebar-first { @include column(2); } #sidebar-second { @include column(2); }';

    // loop over the media queries
  foreach($breakpoints as $breakpoint) {
    // create a clean var for the scss for this breakpoint
    $breakpoint_scss = '';
    //dsm($breakpoint);
    
    // loop over the region groups
    foreach ($region_groups as $gid => $info ) {
      // add row mixin

      $rowname = str_replace("_", "-", $gid) . '-layout';
      $rowval = $layout[$defaultLayout]['region_groups'][$breakpoint->name][$gid]['row'];
      $maxwidth = $layout[$defaultLayout]['region_groups'][$breakpoint->name][$gid]['maxwidth'];
      if ($layout[$defaultLayout]['region_groups'][$breakpoint->name][$gid]['maxwidth_type'] == 'pixel') {
        $unit = 'px';
      }
      else {
        $unit = '%';
      }
      
      $breakpoint_scss .= '#' . $rowname . ' { 
  @include row(' . $rowval . ');
  max-width: '. $maxwidth . $unit .';         
';
  
      // loop over regions
      foreach($info['regions'] as $rid => $data) {
        $regionname = str_replace("_", "-", $rid);
        $breakpoint_scss .= '  #' . $regionname . ' { 
    @include column(' . $layout[$defaultLayout]['region_groups'][$breakpoint->name][$gid]['regions'][$rid]['width'] . ', ' . $info['row'] . '); ';
        
        if ($layout[$defaultLayout]['region_groups'][$breakpoint->name][$gid]['regions'][$rid]['prefix'] > 0) {
          $breakpoint_scss .= '  
    @include prefix(' . $layout[$defaultLayout]['region_groups'][$breakpoint->name][$gid]['regions'][$rid]['prefix'] . '); ';  
        }
        
        if ($layout[$defaultLayout]['region_groups'][$breakpoint->name][$gid]['regions'][$rid]['suffix'] > 0) {
        $breakpoint_scss .= '  
    @include suffix(' . $layout[$defaultLayout]['region_groups'][$breakpoint->name][$gid]['regions'][$rid]['suffix'] . '); ';
        }
        
        if ($layout[$defaultLayout]['region_groups'][$breakpoint->name][$gid]['regions'][$rid]['push'] > 0) {
        $breakpoint_scss .= '  
    @include push(' . $layout[$defaultLayout]['region_groups'][$breakpoint->name][$gid]['regions'][$rid]['push'] . '); ';
        }
        
        if ($layout[$defaultLayout]['region_groups'][$breakpoint->name][$gid]['regions'][$rid]['pull'] > 0) {
        $breakpoint_scss .= '  
    @include pull(' . $layout[$defaultLayout]['region_groups'][$breakpoint->name][$gid]['regions'][$rid]['pull'] . '); ';
        }
        
        $breakpoint_scss .= '
    margin-bottom: $regionSpacing;
  } 
';
        // apply all functions 
      }
      
      $breakpoint_scss .= '
}
';
    }
    
    // if not the defualt media query that should apply to all screens
    // we will wrap the scss we've generated in the appropriate media query.
    if ($breakpoint->name != 'all') {
      $breakpoint_scss = '@media ' . $breakpoint->mediaQuery . ' { 
' . $breakpoint_scss . '
}
';
    }
    
    // add in the SCSS from this breakpoint and add to our SCSS
    $scss .= $breakpoint_scss; 
  }

  return $scss;
}

function _omega_render_layout_css($scss, $options) {
  $parser = new SassParser($options);
  
  // create CSS from SCSS
  $css = $parser->toCss($scss, false);
  
  return $css;
}

function _omega_save_layout_files($scss, $css, $theme) {
  global $base_path;
  // going to overwrite some stuff
  $layoutscss = realpath(".") . $base_path . drupal_get_path('theme', $theme) . '/style/scss/omega-layout.scss';
  $layoutcss = realpath(".") . $base_path . drupal_get_path('theme', $theme) . '/style/css/omega-layout.css';
  
  $scssfile = file_unmanaged_save_data($scss, $layoutscss, TRUE);
  $cssfile = file_unmanaged_save_data($css, $layoutcss, TRUE);

  //return $file;
}