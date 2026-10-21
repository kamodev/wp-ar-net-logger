/* AR Net Logger front-end: check-in form conveniences. No dependencies. */
( function () {
	'use strict';

	var STORAGE_KEY = 'arnlCheckin';

	function load() {
		try {
			return JSON.parse( window.localStorage.getItem( STORAGE_KEY ) || '{}' ) || {};
		} catch ( e ) {
			return {};
		}
	}

	function save( values ) {
		try {
			window.localStorage.setItem( STORAGE_KEY, JSON.stringify( values ) );
		} catch ( e ) {
			// Storage unavailable (private mode); remembering details is optional.
		}
	}

	function init( form ) {
		var remembered = load();

		// Pre-fill the station details used last time.
		Array.prototype.forEach.call( form.querySelectorAll( '[data-arnl-remember]' ), function ( field ) {
			var key = field.getAttribute( 'data-arnl-remember' );
			if ( ! field.value && remembered[ key ] ) {
				field.value = remembered[ key ];
			}
		} );

		// Traffic precedence only matters when the station has traffic.
		Array.prototype.forEach.call( form.querySelectorAll( '[data-arnl-toggle]' ), function ( box ) {
			var target = document.getElementById( box.getAttribute( 'data-arnl-toggle' ) );
			if ( ! target ) {
				return;
			}
			var sync = function () {
				target.hidden = ! box.checked;
			};
			sync();
			box.addEventListener( 'change', sync );
		} );

		var callsign = form.querySelector( '[name="callsign"]' );
		if ( callsign ) {
			callsign.addEventListener( 'input', function () {
				var pos = callsign.selectionStart;
				callsign.value = callsign.value.toUpperCase().replace( /\s+/g, '' );
				if ( typeof pos === 'number' ) {
					callsign.setSelectionRange( pos, pos );
				}
			} );
		}

		form.addEventListener( 'submit', function () {
			var values = {};
			Array.prototype.forEach.call( form.querySelectorAll( '[data-arnl-remember]' ), function ( field ) {
				values[ field.getAttribute( 'data-arnl-remember' ) ] = field.value;
			} );
			save( values );
			var button = form.querySelector( '[type="submit"]' );
			if ( button ) {
				button.disabled = true;
			}
		} );
	}

	document.documentElement.classList.add( 'arnl-js' );
	document.addEventListener( 'DOMContentLoaded', function () {
		Array.prototype.forEach.call( document.querySelectorAll( '.arnl-checkin-form' ), init );
	} );
}() );
