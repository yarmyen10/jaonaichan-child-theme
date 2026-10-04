// Shop banner slider. The track is a native scroll-snap row (swipe/trackpad/keyboard scrolling work without JS);
// this only adds the arrows, the dots and an auto-advance (every data-interval seconds, set in Banner Management) that stops on hover / focus / touch / hidden tab.
// "Reduce motion" (Windows: animations off) only makes the slide change instantly, like the designer's mock — the auto-advance and the dots' own transition stay.
( function () {
  document.querySelectorAll( '[data-jn-banner]' ).forEach( function ( root ) {
    var track = root.querySelector( '.jn-banner__track' );
    var n = root.querySelectorAll( '.jn-banner__slide' ).length;
    if ( n < 2 ) return;
    var dots = root.querySelectorAll( '.jn-banner__dot' );
    var reduce = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
    var raw = root.getAttribute( 'data-interval' ), secs = /^\d+$/.test( raw ) ? +raw : -1;   // Banner Management: 0 = no auto-advance, else 2–60; anything else → 5
    var every = secs === 0 || ( secs >= 2 && secs <= 60 ) ? secs * 1000 : 5000;
    var timer = null, target = -1, settle = null;

    var index = function () { return Math.round( track.scrollLeft / track.clientWidth ); };
    var mark = function ( i ) { dots.forEach( function ( d, k ) { d.setAttribute( 'aria-current', k === i ? 'true' : 'false' ); } ); };
    // the dot moves the moment the slide starts to move (the mock does the same); the scroll events are ignored until it arrives, 1s at most
    var go = function ( i ) {
      target = ( i + n ) % n; mark( target );
      track.scrollTo( { left: target * track.clientWidth, behavior: reduce ? 'auto' : 'smooth' } );
      clearTimeout( settle ); settle = setTimeout( function () { target = -1; mark( index() ); }, 1000 );
    };
    var at = function () { return target < 0 ? index() : target; };
    var stop = function () { clearInterval( timer ); timer = null; };
    var start = function () { if ( every && ! timer && ! document.hidden ) timer = setInterval( function () { go( at() + 1 ); }, every ); };

    track.addEventListener( 'scroll', function () { var i = index(); if ( target < 0 ) mark( i ); else if ( i === target ) target = -1; }, { passive: true } );
    root.querySelector( '.jn-banner__nav--prev' ).addEventListener( 'click', function () { go( at() - 1 ); } );
    root.querySelector( '.jn-banner__nav--next' ).addEventListener( 'click', function () { go( at() + 1 ); } );
    dots.forEach( function ( d, k ) { d.addEventListener( 'click', function () { go( k ); } ); } );

    [ 'mouseenter', 'focusin', 'touchstart' ].forEach( function ( e ) { root.addEventListener( e, stop, { passive: true } ); } );
    [ 'mouseleave', 'focusout' ].forEach( function ( e ) { root.addEventListener( e, start ); } );
    root.addEventListener( 'touchend', function () { setTimeout( start, 8000 ); }, { passive: true } );
    document.addEventListener( 'visibilitychange', function () { document.hidden ? stop() : start(); } );

    mark( index() );
    start();
  } );
} )();
