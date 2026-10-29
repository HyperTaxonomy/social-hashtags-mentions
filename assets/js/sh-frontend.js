/**
 * Social Hashtags & Mentions — frontend pane behavior.
 *
 * A hashtag token that links to more than one network renders as a trigger
 * (.sh-token--multi) with a sibling .sh-pane. Clicking the trigger opens the
 * pane; clicking elsewhere or pressing Escape closes any open pane. No-JS users
 * simply follow the token's fallback href (the local archive).
 */
( function () {
	'use strict';

	function closeAll() {
		document.querySelectorAll( '.sh-pane:not([hidden])' ).forEach( function ( pane ) {
			pane.setAttribute( 'hidden', '' );
		} );
		document.querySelectorAll( '.sh-token--multi[aria-expanded="true"]' ).forEach( function ( trigger ) {
			trigger.setAttribute( 'aria-expanded', 'false' );
		} );
	}

	document.addEventListener( 'click', function ( event ) {
		var trigger = event.target.closest( '.sh-token--multi' );

		if ( trigger ) {
			event.preventDefault();
			var wrap = trigger.closest( '.sh-token-wrap' );
			var pane = wrap ? wrap.querySelector( '.sh-pane' ) : null;
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
		if ( event.target.closest( '.sh-pane' ) ) {
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
