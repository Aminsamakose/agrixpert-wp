/**
 * "Pin my current GPS location" button on the Post a Listing form
 * (includes/public/class-marketplace-shortcode.php). Mirrors the
 * useMyLocation() behaviour added to the live Next.js app's
 * PostListingForm (src/app/marketplace/page.tsx): uses the browser
 * Geolocation API, writes the result into the two hidden
 * latitude/longitude inputs, and reports status next to the button.
 * No map library is loaded here — the stored coordinates are only ever
 * surfaced as a "View on map" link to Google Maps (see the listing card
 * renderer in the same PHP file), so there is nothing to render client
 * side beyond this capture step.
 */
function agxPinMyLocation( button ) {
	'use strict';

	var field = button.closest( '.agx-geo-field' );
	if ( ! field ) {
		return;
	}
	var latInput  = field.querySelector( '#agx-geo-lat' );
	var lngInput  = field.querySelector( '#agx-geo-lng' );
	var statusEl  = field.querySelector( '.agx-geo-status' );

	function setStatus( text ) {
		if ( statusEl ) {
			statusEl.textContent = text;
		}
	}

	if ( ! navigator.geolocation ) {
		setStatus( 'Your browser does not support location pinning.' );
		return;
	}

	setStatus( 'Getting your location…' );
	button.disabled = true;

	navigator.geolocation.getCurrentPosition(
		function ( position ) {
			latInput.value = position.coords.latitude;
			lngInput.value = position.coords.longitude;
			setStatus( 'Pinned: ' + position.coords.latitude.toFixed( 5 ) + ', ' + position.coords.longitude.toFixed( 5 ) );
			button.disabled = false;
			button.textContent = 'Update pinned GPS location';
		},
		function () {
			setStatus( 'Could not get your location. You can still save without it.' );
			button.disabled = false;
		},
		{ enableHighAccuracy: true, timeout: 10000 }
	);
}
