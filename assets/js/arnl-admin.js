/* AR Net Logger admin behaviour. */
( function ( $ ) {
	'use strict';

	var settings = window.arnlAdmin || { i18n: {} };

	// Confirm destructive links.
	$( document ).on( 'click', '.arnl-confirm', function ( event ) {
		var message = $( this ).data( 'arnl-confirm' ) || settings.i18n.confirm;
		if ( ! window.confirm( message ) ) {
			event.preventDefault();
		}
	} );

	// Upper-case callsign inputs as the operator types.
	$( document ).on( 'input', '.arnl-callsign-input', function () {
		var start = this.selectionStart;
		var end = this.selectionEnd;
		this.value = this.value.toUpperCase().replace( /\s+/g, '' );
		if ( typeof start === 'number' ) {
			this.setSelectionRange( start, end );
		}
	} );

	// Show / hide fields controlled by a checkbox (traffic details).
	function syncToggle( box ) {
		var target = document.getElementById( $( box ).data( 'arnl-toggle' ) );
		if ( target ) {
			target.hidden = ! box.checked;
		}
	}
	$( '[data-arnl-toggle]' ).each( function () {
		syncToggle( this );
	} ).on( 'change', function () {
		syncToggle( this );
	} );

	// Net form: show the schedule rows that apply to the chosen recurrence.
	function syncRecurrence() {
		var value = $( '#arnl-recurrence' ).val();
		var repeating = value === 'weekly' || value === 'biweekly' || value === 'monthly';
		$( '.arnl-row-days, .arnl-row-months' ).toggle( repeating );
		$( '.arnl-row-weeks' ).toggle( value === 'monthly' );
	}
	if ( $( '#arnl-recurrence' ).length ) {
		syncRecurrence();
		$( '#arnl-recurrence' ).on( 'change', syncRecurrence );
	}

	if ( $.fn.wpColorPicker ) {
		$( '.arnl-color-picker' ).wpColorPicker();
	}

	$( '.arnl-print' ).on( 'click', function () {
		window.print();
	} );

	// Browsers ignore autofocus when the URL has a #fragment (as after logging a
	// check-in), so put the cursor back in the callsign field for the next station.
	var $autofocus = $( '.arnl-checkin-admin [autofocus]' );
	if ( $autofocus.length ) {
		$( window ).on( 'load', function () {
			if ( ! $autofocus.prop( 'disabled' ) && ! $( document.activeElement ).is( 'input, select, textarea' ) ) {
				$autofocus.trigger( 'focus' );
			}
		} );
	}

	// Net log: fill name / location from the station's previous check-in.
	var $callsign = $( '[data-arnl-lookup]' );
	var lastLookup = '';
	$callsign.on( 'change blur', function () {
		var value = $.trim( $callsign.val() ).toUpperCase();
		var $form = $callsign.closest( 'form' );
		if ( value.length < 3 || value === lastLookup ) {
			return;
		}
		lastLookup = value;
		$.getJSON( settings.ajaxUrl, {
			action: 'arnl_lookup_callsign',
			nonce: settings.nonce,
			callsign: value
		} ).done( function ( response ) {
			if ( ! response || ! response.success || ! response.data ) {
				return;
			}
			var filled = false;
			$.each( [ 'name', 'location', 'station_type' ], function ( i, field ) {
				var $input = $form.find( '[name="' + field + '"]' );
				if ( response.data[ field ] && ( ! $input.val() || ( field === 'station_type' && $input.val() === 'fixed' ) ) ) {
					$input.val( response.data[ field ] );
					filled = true;
				}
			} );
			if ( filled ) {
				$form.find( '.arnl-lookup-status' ).text( settings.i18n.found );
			}
		} );
	} );
}( jQuery ) );
