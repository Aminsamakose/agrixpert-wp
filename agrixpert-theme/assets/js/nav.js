document.addEventListener( 'DOMContentLoaded', function () {
	'use strict';
	var toggle = document.querySelector( '.agx-menu-toggle' );
	var nav = document.querySelector( '.agx-primary-nav' );
	if ( ! toggle || ! nav ) {
		return;
	}
	toggle.addEventListener( 'click', function () {
		nav.classList.toggle( 'is-open' );
		var expanded = nav.classList.contains( 'is-open' );
		toggle.setAttribute( 'aria-expanded', expanded ? 'true' : 'false' );
	} );
} );
