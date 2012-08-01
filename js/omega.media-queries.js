(function ($) {

/**
 * Array for gathering media queries from (sub)themes and modules.
 */
Drupal.mediaQueries = Drupal.mediaQueries | new Array;

/**
 * Toggles media-query specific body classes.
 */
Drupal.behaviors.mediaQueryClasses = {
  attach: function (context, settings) {
    $('body').once('mediaqueries', function () {
      $.each(Drupal.mediaQueries, function (index, value) {
        // Initially, check if the media query applies or not and add the
        // corresponding class to the body.
        if ($.matchmedia(value)) {
          $('body').addClass(index + '-active');
        }
        else {
          $('body').addClass(index + '-inactive');
        }

        // React to media query changes and toggle the class names.
        $(window).mediaquery(value, function (e) {
          if (e.applies) {
            $('body').removeClass(index + '-inactive').addClass(index + '-active');
          }
          else {
            $('body').removeClass(index + '-active').addClass(index + '-inactive');
          }
        });
      });
    });
  }
}

})(jQuery);