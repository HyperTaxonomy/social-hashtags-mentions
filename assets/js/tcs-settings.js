/**
 * TermConnect Social — admin settings sortable.
 *
 * Makes the network list drag-to-reorder. The order is captured on submit from
 * the DOM order of the hidden tcs_order[] inputs, so no extra wiring is needed.
 */
( function ( $ ) {
	'use strict';

	$( function () {
		$( '.tcs-network-list' ).sortable( {
			handle: '.tcs-drag',
			axis: 'y',
			cursor: 'grabbing',
			placeholder: 'tcs-network-item tcs-dragging',
			forcePlaceholderSize: true,
		} );
	} );
}( jQuery ) );
