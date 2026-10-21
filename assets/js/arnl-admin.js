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

	/*
	 * Callsign type-ahead.
	 *
	 * Inputs with data-arnl-lookup='{"name":"#field", ...}' suggest stations
	 * from previous check-ins (and the online lookup for new callsigns) as
	 * you type. Picking one fills the mapped fields. Leaving the field with a
	 * complete callsign fills any mapped fields that are still empty.
	 */
	var uid = 0;

	function fillFields( map, station, overwrite ) {
		var filled = false;
		$.each( map, function ( field, selector ) {
			var $field = $( selector );
			var value = station[ field ];
			if ( ! $field.length || ! value ) {
				return;
			}
			if ( field === 'station_type' && ! $field.find( 'option[value="' + value + '"]' ).length ) {
				return;
			}
			var current = $field.val();
			if ( overwrite || ! current || ( field === 'station_type' && current === 'fixed' ) ) {
				$field.val( value ).trigger( 'change' );
				filled = true;
			}
		} );
		return filled;
	}

	function describe( station ) {
		return $.grep( [ station.name, station.location ], function ( part ) {
			return !! part;
		} ).join( ' · ' );
	}

	function Typeahead( input ) {
		var self = this;
		this.$input = $( input );
		this.map = this.$input.data( 'arnl-lookup' ) || {};
		this.$status = this.$input.closest( 'form' ).find( '.arnl-lookup-status' );
		this.listId = 'arnl-suggest-' + ( ++uid );
		this.items = [];
		this.active = -1;
		this.cache = {};
		this.timer = null;
		this.term = '';
		this.lastFilled = '';

		this.$list = $( '<ul class="arnl-suggestions" role="listbox" hidden></ul>' ).attr( 'id', this.listId );
		this.$input
			.wrap( '<span class="arnl-typeahead"></span>' )
			.after( this.$list )
			.attr( {
				role: 'combobox',
				autocomplete: 'off',
				'aria-autocomplete': 'list',
				'aria-expanded': 'false',
				'aria-controls': this.listId
			} );

		this.$input.on( 'input', function () {
			self.schedule();
		} );
		this.$input.on( 'keydown', function ( event ) {
			self.keydown( event );
		} );
		this.$input.on( 'blur', function () {
			window.setTimeout( function () {
				self.close();
				self.fillOnLeave();
			}, 150 );
		} );
		this.$list.on( 'mousedown', 'li', function ( event ) {
			event.preventDefault(); // Keep focus in the input.
			self.choose( $( this ).data( 'index' ) );
		} );
	}

	Typeahead.prototype.schedule = function () {
		var self = this;
		window.clearTimeout( this.timer );
		this.timer = window.setTimeout( function () {
			self.search( $.trim( self.$input.val() ) );
		}, 200 );
	};

	Typeahead.prototype.search = function ( term ) {
		var self = this;
		this.term = term;
		if ( term.length < 2 ) {
			this.close();
			return;
		}
		if ( this.cache[ term ] ) {
			this.render( this.cache[ term ] );
			return;
		}
		$.getJSON( settings.ajaxUrl, {
			action: 'arnl_search_callsigns',
			nonce: settings.nonce,
			q: term
		} ).done( function ( response ) {
			var items = response && response.success && $.isArray( response.data ) ? response.data : [];
			self.cache[ term ] = items;
			if ( term === self.term && self.$input.is( ':focus' ) ) {
				self.render( items );
			}
		} );
	};

	Typeahead.prototype.render = function ( items ) {
		var self = this;
		this.items = items;
		this.active = -1;
		this.$list.empty();
		if ( ! items.length ) {
			this.close();
			return;
		}
		$.each( items, function ( index, station ) {
			var $item = $( '<li role="option" class="arnl-suggestion"></li>' )
				.attr( { id: self.listId + '-' + index, 'aria-selected': 'false' } )
				.data( 'index', index );
			$( '<strong class="arnl-suggestion-call"></strong>' ).text( station.callsign ).appendTo( $item );
			$( '<span class="arnl-suggestion-info"></span>' ).text( describe( station ) ).appendTo( $item );
			var meta = station.source === 'log' ?
				( settings.i18n.checkins || '%d' ).replace( '%d', station.checkins ) :
				settings.i18n.online;
			$( '<small class="arnl-suggestion-meta"></small>' ).text( meta ).appendTo( $item );
			self.$list.append( $item );
		} );
		this.$list.prop( 'hidden', false );
		this.$input.attr( 'aria-expanded', 'true' );
	};

	Typeahead.prototype.close = function () {
		this.$list.prop( 'hidden', true );
		this.$input.attr( 'aria-expanded', 'false' ).removeAttr( 'aria-activedescendant' );
		this.active = -1;
	};

	Typeahead.prototype.isOpen = function () {
		return ! this.$list.prop( 'hidden' );
	};

	Typeahead.prototype.highlight = function ( index ) {
		var $items = this.$list.children();
		if ( ! $items.length ) {
			return;
		}
		this.active = ( index + $items.length ) % $items.length;
		$items.removeClass( 'is-active' ).attr( 'aria-selected', 'false' );
		var $current = $items.eq( this.active ).addClass( 'is-active' ).attr( 'aria-selected', 'true' );
		this.$input.attr( 'aria-activedescendant', $current.attr( 'id' ) );
	};

	Typeahead.prototype.keydown = function ( event ) {
		if ( ! this.isOpen() ) {
			if ( event.key === 'ArrowDown' && this.items.length ) {
				this.render( this.items );
				this.highlight( 0 );
				event.preventDefault();
			}
			return;
		}
		switch ( event.key ) {
			case 'ArrowDown':
				this.highlight( this.active + 1 );
				event.preventDefault();
				break;
			case 'ArrowUp':
				this.highlight( this.active - 1 );
				event.preventDefault();
				break;
			case 'Enter':
				if ( this.active >= 0 ) {
					this.choose( this.active );
					event.preventDefault(); // Pick the station; the next Enter logs it.
				} else {
					this.close();
				}
				break;
			case 'Escape':
				this.close();
				event.preventDefault();
				break;
			case 'Tab':
				if ( this.active >= 0 ) {
					this.choose( this.active );
				}
				break;
		}
	};

	Typeahead.prototype.choose = function ( index ) {
		var station = this.items[ index ];
		if ( ! station ) {
			return;
		}
		this.$input.val( station.callsign );
		this.lastFilled = station.callsign;
		this.close();
		if ( fillFields( this.map, station, true ) ) {
			this.$status.text( station.source === 'log' ? settings.i18n.found : settings.i18n.foundOnline );
		}
	};

	// Typed a full callsign without picking a suggestion: fill what is still empty.
	Typeahead.prototype.fillOnLeave = function () {
		var self = this;
		var value = $.trim( this.$input.val() ).toUpperCase();
		if ( value.length < 3 || value === this.lastFilled || $.isEmptyObject( this.map ) ) {
			return;
		}
		this.lastFilled = value;
		$.getJSON( settings.ajaxUrl, {
			action: 'arnl_lookup_callsign',
			nonce: settings.nonce,
			callsign: value
		} ).done( function ( response ) {
			if ( response && response.success && response.data && fillFields( self.map, response.data, false ) ) {
				self.$status.text( response.data.source === 'log' ? settings.i18n.found : settings.i18n.foundOnline );
			}
		} );
	};

	$( '[data-arnl-lookup]' ).each( function () {
		$( this ).data( 'arnlTypeahead', new Typeahead( this ) );
	} );
}( jQuery ) );
