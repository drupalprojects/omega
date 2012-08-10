(function ($) {

Drupal.behaviors.epiqoMobileSidebar = {
  attach: function (context, settings) {
    $('#sidebar-first-container').once('mobile-sidebar', function () {
      // Show the sidebar when a click is performed on its overlapping border.
      $(this).click(function (e) {
        if ($.matchmedia('all and (max-width: 800px)') && !$('body').hasClass('show-sidebar') && $(e.srcElement).attr('id') != 'sidebar-first-toggle') {
          $('body').addClass('show-sidebar');
        }
      });

      // Hide the sidebar when the area outside of it is clicked.
      $('#main').click(function (e) {
        if ($.matchmedia('all and (max-width: 800px)') && $('body').hasClass('show-sidebar') && $(e.srcElement).attr('id') != 'sidebar-first-container' && !$(e.srcElement).parents('#sidebar-first-container').length) {
          // Check if the clicked element is the sidebar or a child element of
          // the sidebar.
          $('body').removeClass('show-sidebar');
        }
      });

      // Toggle the sidebar with the toggle button.
      $('<a href="#sidebar-first-container" id="sidebar-first-toggle" class="sidebar-first-toggle" />').click(function (e) {
        if ($.matchmedia('all and (max-width: 800px)')) {
          if (!$('body').hasClass('show-sidebar')) {
            $('body').addClass('show-sidebar');
          }
          else {
            $('body').removeClass('show-sidebar');
          }
        }

        return false;
      }).prependTo(this);
    });
  }
}

})(jQuery);