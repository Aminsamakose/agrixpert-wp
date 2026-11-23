/**
 * Wires the "Choose from Media Library" button on the Images admin screen
 * (includes/admin/class-images-admin.php) to WordPress's built-in media
 * frame (wp.media), so picking or uploading an image fills in the URL
 * field without the admin ever typing or finding a URL by hand.
 */
jQuery( function ( $ ) {
	if ( typeof wp === 'undefined' || ! wp.media ) {
		return;
	}

	$( '.agx-choose-image' ).on( 'click', function ( e ) {
		e.preventDefault();
		var $card = $( this ).closest( '.agx-image-card' );
		var $srcField = $card.find( '.agx-image-src-field' );
		var $altField = $card.find( 'input[name="agx_image_alt"]' );
		var $preview = $card.find( '.agx-image-preview' );

		var frame = wp.media( {
			title: 'Select or Upload Image',
			button: { text: 'Use this image' },
			multiple: false,
		} );

		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();
			$srcField.val( attachment.url );
			$preview.attr( 'src', attachment.url );
			if ( ! $altField.val() && attachment.alt ) {
				$altField.val( attachment.alt );
			}
		} );

		frame.open();
	} );
} );
