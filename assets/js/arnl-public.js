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

	// Mark the visitor's own check-in (the callsign this browser last checked in with).
	function markMine( log ) {
		var mine = ( load().callsign || '' ).toUpperCase();
		if ( ! mine ) {
			return;
		}
		Array.prototype.forEach.call( log.querySelectorAll( '[data-callsign]' ), function ( item ) {
			item.classList.toggle( 'is-mine', item.getAttribute( 'data-callsign' ) === mine );
		} );
	}

	/*
	 * While a session is running, keep its public check-in list current so a
	 * station sees Net Control's acknowledgement without reloading the page.
	 */
	function live( log ) {
		var settings = window.arnlPublic || {};
		var interval = parseInt( settings.interval, 10 ) * 1000;
		var timer = null;
		var busy = false;
		var stopped = false;

		markMine( log );
		if ( ! settings.ajaxUrl || ! interval || ! window.fetch ) {
			return;
		}

		function schedule() {
			window.clearTimeout( timer );
			timer = window.setTimeout( poll, interval );
		}

		function poll() {
			if ( busy || stopped ) {
				return;
			}
			if ( document.hidden ) {
				schedule();
				return;
			}
			busy = true;
			var url = settings.ajaxUrl + ( settings.ajaxUrl.indexOf( '?' ) < 0 ? '?' : '&' ) +
				'action=arnl_public_log&event=' + encodeURIComponent( log.getAttribute( 'data-arnl-live' ) ) +
				'&since=' + encodeURIComponent( log.getAttribute( 'data-arnl-signature' ) || '' );
			window.fetch( url, { credentials: 'same-origin', headers: { Accept: 'application/json' } } )
				.then( function ( response ) {
					if ( 404 === response.status ) {
						return null;
					}
					if ( ! response.ok ) {
						throw new Error( response.status );
					}
					return response.json();
				} )
				.then( function ( json ) {
					busy = false;
					if ( ! json || ! json.success ) {
						stopped = true; // Session gone or list switched off: stop quietly.
						return;
					}
					var data = json.data;
					if ( data.changed ) {
						var holder = document.createElement( 'div' );
						holder.innerHTML = data.html;
						var fresh = holder.firstElementChild;
						if ( ! fresh ) {
							stopped = true;
							log.parentNode.removeChild( log );
							return;
						}
						var before = {};
						Array.prototype.forEach.call( log.querySelectorAll( '.arnl-checkin-item.is-ack' ), function ( item ) {
							before[ item.getAttribute( 'data-callsign' ) ] = true;
						} );
						log.parentNode.replaceChild( fresh, log );
						log = fresh;
						markMine( log );
						Array.prototype.forEach.call( log.querySelectorAll( '.arnl-checkin-item.is-ack' ), function ( item ) {
							if ( ! before[ item.getAttribute( 'data-callsign' ) ] ) {
								item.classList.add( 'is-new' );
							}
						} );
					}
					if ( data.live && log.hasAttribute( 'data-arnl-live' ) ) {
						schedule();
					} else {
						stopped = true; // Net closed: the list no longer changes.
					}
				} )
				.catch( function () {
					busy = false;
					schedule(); // Network blip: try again later.
				} );
		}

		document.addEventListener( 'visibilitychange', function () {
			if ( ! document.hidden && ! stopped ) {
				poll();
			}
		} );
		schedule();
	}

	document.documentElement.classList.add( 'arnl-js' );
	document.addEventListener( 'DOMContentLoaded', function () {
		Array.prototype.forEach.call( document.querySelectorAll( '.arnl-checkin-form' ), init );
		Array.prototype.forEach.call( document.querySelectorAll( '.arnl-public-log' ), function ( log ) {
			if ( log.hasAttribute( 'data-arnl-live' ) ) {
				live( log );
			} else {
				markMine( log );
			}
		} );
	} );
}() );
