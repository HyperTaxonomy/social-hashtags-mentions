/**
 * TermConnect Social — frontend pane behavior.
 *
 * A hashtag token that links to more than one network renders as a trigger
 * (.tcs-token--multi) with a sibling .tcs-pane. Clicking the trigger opens the
 * pane; clicking elsewhere or pressing Escape closes any open pane. No-JS users
 * simply follow the token's fallback href (the local archive).
 */
( function () {
	'use strict';

	function closeAll() {
		document.querySelectorAll( '.tcs-pane:not([hidden])' ).forEach( function ( pane ) {
			pane.setAttribute( 'hidden', '' );
		} );
		document.querySelectorAll( '.tcs-token--multi[aria-expanded="true"]' ).forEach( function ( trigger ) {
			trigger.setAttribute( 'aria-expanded', 'false' );
		} );
	}

	document.addEventListener( 'click', function ( event ) {
		var trigger = event.target.closest( '.tcs-token--multi' );

		if ( trigger ) {
			event.preventDefault();
			var wrap = trigger.closest( '.tcs-token-wrap' );
			var pane = wrap ? wrap.querySelector( '.tcs-pane' ) : null;
			if ( ! pane ) {
				return;
			}
			var isOpen = ! pane.hasAttribute( 'hidden' );
			closeAll();
			if ( ! isOpen ) {
				pane.removeAttribute( 'hidden' );
				trigger.setAttribute( 'aria-expanded', 'true' );
			}
			return;
		}

		// A click inside an open pane should follow the link, not close prematurely.
		if ( event.target.closest( '.tcs-pane' ) ) {
			return;
		}

		closeAll();
	} );

	document.addEventListener( 'keydown', function ( event ) {
		if ( 'Escape' === event.key ) {
			closeAll();
		}
	} );
}() );
