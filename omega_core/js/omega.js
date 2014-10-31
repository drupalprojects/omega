/**
 * @todo
 */

Drupal.omega = Drupal.omega || {};





(function($) {
  "use strict";
  
  var breakpoints;
  var breakpointMatch;
  var breakpointText;
  var oScreen;
  var screenWidth;
  
  Drupal.omega = {
    currentBreakpoints: {
     'all' : true
    }
  };
  
  Drupal.omega.updateIndicatorBreakpoints = function(breakpoints, activeBreakpoints) {
    breakpointText = [];
    oScreen = $('#omega-screen--indicator');
    
    $.each(breakpoints, function() {    
      if (activeBreakpoints[this.name] == true) {
        breakpointText.push(this.name);
        var text = breakpointText.join(', ');
        oScreen.find('.screen-query .data').html(text);  
      }
    });
  };
  
  Drupal.omega.toolbarFix = function() {
    // this function needs to compensate for admin users (or anyone w/access to toolbar) 
    // and rearrange the positioning of the top nav bar accordingly.
    // assuming that the user is anonymous, it already just "works" with the CSS
    $(document).ready(function(){    
      if ($('body').hasClass('toolbar')) {
        // default toolbar height = 30px;
        var toolbarHeight = $('#toolbar').height();
        // reapply padding to body because toolbar module doesn't do this... LOL
        $('body').css('padding-top', toolbarHeight);
      }
    });
  }
  
  Drupal.behaviors.omegaBreakpoint = {
    attach: function (context) {
      
      $('body', context).once('omega-breakpoint', function () {
        
        // return if not viewing on screen
        if (!window.matchMedia('only screen').matches) {
          //console.log('This appears not to be a screen...');
          return;
        }
        
        breakpoints = Drupal.settings.omega_breakpoints.layouts;
        breakpointMatch = false;
        //console.log(breakpoints);
        
        // Handle the intial load
        $(window).ready( function() {
          $.each(breakpoints, function() {
            //console.log(this.query);
          	if (window.matchMedia(this.query).matches) {
          	  //console.log('matchMedia match found: ' + this.query);
              breakpointMatch = true;
              Drupal.omega.currentBreakpoints[this.name] = true;
              $('body').addClass('omega-breapoint--'+this.name);
              $.event.trigger('breakpointAdded', {name: this.name, query: this.query});
            }
            else {
              Drupal.omega.currentBreakpoints[this.name] = false;
            }
          });
          // run it once on page load
          Drupal.omega.updateIndicatorBreakpoints(breakpoints, Drupal.omega.currentBreakpoints);
          
          $( 'body' ).bind({
            breakpointAdded: function(query) {
              // do something when a breakpoint is added
            },
            breakpointRemoved: function(query) {
              // do something when a breakpoint is removed
            },
            breakpointUpdated: function() {
              // do something when breakpoints are updated
            }
          });
          
          $.event.trigger('breakpointUpdated', {});
        });
        
        // handle resize events
        $(window).resize( function() {
          var breakpointAdjust = false;
          
          $.each(breakpoints, function() {
          	
          	if (window.matchMedia(this.query).matches) {
          	  breakpointMatch = true;
              // if it wasn't already active
              if (Drupal.omega.currentBreakpoints[this.name] != true) {
                breakpointAdjust = true;
                Drupal.omega.currentBreakpoints[this.name] = true;
                $.event.trigger('breakpointAdded', {name: this.name, query: this.query});
                $('body').addClass('omega-breapoint--'+this.name);
              }
            }
            else {
              // if it was already active
              if (Drupal.omega.currentBreakpoints[this.name] == true) {
                breakpointAdjust = true;
                Drupal.omega.currentBreakpoints[this.name] = false;
                $.event.trigger('breakpointRemoved', {name: this.name, query: this.query});
                $('body').removeClass('omega-breapoint--'+this.name);
              }
            }
          });
          
          // if the breakpoints have been updated by adding or removing something, then fire breakpointUpdated
          if (breakpointAdjust) {
            $.event.trigger('breakpointUpdated', {});  
          }
          
          
          // must be mobile or something shitty like IE8
          if (!breakpointMatch) {
            breakpointMatch = false;
            Drupal.omega.currentBreakpoints['all'] = true;
          }
        });
      });
    }
  };
  
  Drupal.behaviors.attachIndicatorData = {
    attach: function (context) {
      // grab the wrapper element to manipulate
      oScreen = $('#omega-screen--indicator');
      
      
      $(window).ready(function(){
        screenWidth = $(this).width();
        var layout = Drupal.settings.omega.activeLayout;
        //console.log(screenWidth);
        oScreen.find('.screen-size .data').html(screenWidth + 'px');  
        oScreen.find('.screen-layout .data').html(layout);
        oScreen.find('.theme-name .data').html(Drupal.settings.omega.activeTheme);
        
      });
      
      $(window).resize(function(){
        //console.log(this);
        screenWidth = $(this).width();
        //console.log(screenWidth);
        oScreen.find('.screen-size .data').html(screenWidth + 'px');  
      });
      
      
      
      $('body', context).once('breakpoint', function () {
        
        breakpoints = Drupal.settings.omega_breakpoints.layouts;
        
        
        $( 'body' ).bind({
          breakpointAdded: function(query) {
            // do something when a breakpoint is added
            Drupal.omega.updateIndicatorBreakpoints(breakpoints, Drupal.omega.currentBreakpoints);
          },
          breakpointRemoved: function(query) {
            // do something when a breakpoint is removed
            Drupal.omega.updateIndicatorBreakpoints(breakpoints, Drupal.omega.currentBreakpoints);
          },
          breakpointUpdated: function() {
            // do something when  breakpoints are updated
          }
        });
      });
    }
  };

  // need to use some LocalStorage to keep the indicator open/closed based on last setting.
  Drupal.behaviors.indicatorToggle = {
    attach: function (context) {
      
      $('#indicator-toggle').click( function() {
        if ($(this).hasClass('indicator-open')) {
          $(this).removeClass('indicator-open').addClass('indicator-closed');
          //$('#omega-screen--indicator').css('right', '-280px');
          
          $('#omega-screen--indicator').animate({
            opacity: 0.25,
            right: '-280',
            //height: "toggle"
          }, 500, function() {
            // Animation complete.
          });
          
        }
        else {
          $(this).removeClass('indicator-closed').addClass('indicator-open');
          //$('#omega-screen--indicator').css('right', '0');
          
          $('#omega-screen--indicator').animate({
            opacity: 1,
            right: '0',
            //height: "toggle"
          }, 250, function() {
            // Animation complete.
          });
        }
        return false;
      });
    }
  };
  
  // Handles Drupal's lack of ability to adjust the padding applied to the <body> 
  // when the toolbar becomes 'taller' when shrinking the screen
  Drupal.behaviors.toolbarResize = {
    attach: function (context) {
      
      $(document).ready(function(){  
        Drupal.omega.toolbarFix();
      });
      $(window).resize(function(){
        Drupal.omega.toolbarFix();
      });
    }
  };
  
})(jQuery);