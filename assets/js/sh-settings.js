/**
 * Social Hashtags & Mentions — admin settings sortable.
 *
 * Makes the network list drag-to-reorder. The order is captured on submit from
 * the DOM order of the hidden sh_order[] inputs, so no extra wiring is needed.
 */
( function ( $ ) {
	'use strict';

	$( function () {
		$( '.sh-network-list' ).sortable( {
			handle: '.sh-drag',
			axis: 'y',
			cursor: 'grabbing',
			placeholder: 'sh-network-item sh-dragging',
			forcePlaceholderSize: true,
		} );
	} );
}( jQuery ) );
