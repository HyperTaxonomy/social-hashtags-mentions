/**
 * TermConnect Social — admin settings sortable.
 *
 * Makes the network list drag-to-reorder. The order is captured on submit from
 * the DOM order of the hidden tcsoc_order[] inputs, so no extra wiring is needed.
 */
( function ( $ ) {
	'use strict';

	$( function () {
		$( '.tcsoc-network-list' ).sortable( {
			handle: '.tcsoc-drag',
			axis: 'y',
			cursor: 'grabbing',
			placeholder: 'tcsoc-network-item tcsoc-dragging',
			forcePlaceholderSize: true,
		} );
	} );
}( jQuery ) );
