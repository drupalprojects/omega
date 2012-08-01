(function ($) {

/**
 * Array for caching the media queries.
 */
var mediaQueries = new Array();

/**
 * Check if a media query currently applies.
 *
 * @param query
 *   The media query to check for.
 */
$.matchmedia = function(query) {
  // Check if the media query is already in the list.
  var index = $.inArray(query, mediaQueries);
  var $dummy;

  if (index == -1) {
    // The media query is not yet in the list.
    index = mediaQueries.length;

    // Add the media query to the list. Its index is going to be the previous
    // length of the array.
    mediaQueries.push(query);

    // Create the dummy for checking for the media query.
    $dummy = $('<div id="matchmedia-' + index + '" />').css({position: 'absolute', top: '-999em'}).prependTo('body');
    $dummy.html('<style media="' + query + '"> #matchmedia-' + index + ' { width: 42px; } </style>');
  }
  else {
    // The media query is already in the list. We just have to find it.
    $dummy = $('#matchmedia-' + index);
  }

  // If the media query applies the width of the dummy is 42.
  return $dummy.outerWidth() == 42;
};

/**
 * Throttled resize event. Fires only once after the resize ended.
 */
var $event = $.event.special.mediaquery = {
  add: function (handleObj) {
    $(this).bind('resize.matchmedia.' + handleObj.guid, {query: handleObj.data, applies: $.matchmedia(handleObj.data)}, $event.handler);
  },

  remove: function (handleObj) {
    $(this).unbind('resize.matchmedia.' + handleObj.guid, $event.handler);
  },

  handler: function (e) {
    var context = this;
    var args = arguments;

    if (e.data.applies != $.matchmedia(e.data.query)) {
      e.type = 'mediaquery';
      e.applies = e.data.applies = !e.data.applies;
      $.event.handle.apply(context, args);
    }
  }
};

/**
 * Event shortcut.
 */
$.fn.mediaquery = function (query, callback) {
  return $(this).bind('mediaquery', query, callback);
}

})(jQuery);