// Shop banner slider. The track is a native scroll-snap row (swipe/trackpad/keyboard scrolling work without JS);
// this only adds the arrows, the dots and a slow auto-advance that stops on hover / focus / touch / hidden tab / reduced motion.
( function () {
  document.querySelectorAll( '[data-jn-banner]' ).forEach( function ( root ) {
    var track = root.querySelector( '.jn-banner__track' );
    var n = root.querySelectorAll( '.jn-banner__slide' ).length;
    if ( n < 2 ) return;
    var dots = root.querySelectorAll( '.jn-banner__dot' );
    var reduce = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
    var timer = null;

    var index = function () { return Math.round( track.scrollLeft / track.clientWidth ); };
    var go = function ( i ) { track.scrollTo( { left: ( ( i + n ) % n ) * track.clientWidth, behavior: reduce ? 'auto' : 'smooth' } ); };
    var mark = function () { var i = index(); dots.forEach( function ( d, k ) { d.setAttribute( 'aria-current', k === i ? 'true' : 'false' ); } ); };
    var stop = function () { clearInterval( timer ); timer = null; };
    var start = function () { if ( ! reduce && ! timer && ! document.hidden ) timer = setInterval( function () { go( index() + 1 ); }, 5000 ); };

    track.addEventListener( 'scroll', mark, { passive: true } );
    root.querySelector( '.jn-banner__nav--prev' ).addEventListener( 'click', function () { go( index() - 1 ); } );
    root.querySelector( '.jn-banner__nav--next' ).addEventListener( 'click', function () { go( index() + 1 ); } );
    dots.forEach( function ( d, k ) { d.addEventListener( 'click', function () { go( k ); } ); } );

    [ 'mouseenter', 'focusin', 'touchstart' ].forEach( function ( e ) { root.addEventListener( e, stop, { passive: true } ); } );
    [ 'mouseleave', 'focusout' ].forEach( function ( e ) { root.addEventListener( e, start ); } );
    root.addEventListener( 'touchend', function () { setTimeout( start, 8000 ); }, { passive: true } );
    document.addEventListener( 'visibilitychange', function () { document.hidden ? stop() : start(); } );

    mark();
    start();
  } );
} )();
