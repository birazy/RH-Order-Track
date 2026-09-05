/**
 * RH Order Track — settings screen behaviour.
 */
( function ( $ ) {
	'use strict';

	var settings = window.rhotAdmin || {};

	function initColorPickers( context ) {
		var $fields = $( context || document ).find( '.rhot-color' );

		if ( $fields.wpColorPicker ) {
			$fields.wpColorPicker();
		}
	}

	/* -----------------------------------------------------------------
	 * Custom meta-row repeater
	 * -------------------------------------------------------------- */
	function initRepeater() {
		var $table = $( '#rhot-meta-rows' );

		if ( ! $table.length ) {
			return;
		}

		var $body = $table.find( 'tbody' );
		var template = $( '#rhot-row-template' ).html() || '';

		function addRow( label, key ) {
			// Row names are indexed by position; using a timestamp instead
			// would leave gaps, which is harmless but makes the saved option
			// harder to read.
			var index = $body.find( '.rhot-repeater__row' ).length;
			var html = template.replace( /__INDEX__/g, index );

			var $row = $( html );

			if ( label ) {
				$row.find( 'input[name*="[label]"]' ).val( label );
			}

			if ( key ) {
				$row.find( 'input[name*="[meta_key]"]' ).val( key );
			}

			$body.append( $row );

			return $row;
		}

		$( '#rhot-add-row' ).on( 'click', function () {
			addRow();
		} );

		$body.on( 'click', '.rhot-remove-row', function () {
			if ( window.confirm( settings.i18n.removeRow ) ) {
				$( this ).closest( 'tr' ).remove();
			}
		} );

		$( '.rhot-suggest' ).on( 'click', function () {
			var $button = $( this );
			var key = $button.data( 'key' );

			// Don't add the same meta key twice.
			var exists = false;

			$body.find( 'input[name*="[meta_key]"]' ).each( function () {
				if ( this.value === key ) {
					exists = true;
				}
			} );

			if ( exists ) {
				return;
			}

			addRow( $button.data( 'label' ), key );
		} );
	}

	/* -----------------------------------------------------------------
	 * Phone index rebuild
	 * -------------------------------------------------------------- */
	function initBackfill() {
		var $button = $( '#rhot-backfill' );

		if ( ! $button.length ) {
			return;
		}

		var $status = $( '#rhot-backfill-status' );
		var $progress = $( '#rhot-progress' );
		var $bar = $( '#rhot-progress-bar' );

		var updated = 0;

		function step( page ) {
			$.post( settings.ajaxUrl, {
				action: settings.action,
				nonce: settings.nonce,
				page: page
			} ).done( function ( response ) {
				if ( ! response || ! response.success ) {
					$button.prop( 'disabled', false );
					$status.text( ( response && response.data && response.data.message ) || settings.i18n.failed );
					return;
				}

				var data = response.data;

				updated += data.updated;

				var percent = data.total ? Math.min( 100, Math.round( ( data.processed / data.total ) * 100 ) ) : 100;

				$bar.css( 'width', percent + '%' );
				$status.text( settings.i18n.running + ' ' + data.processed + ' / ' + data.total );

				if ( data.done ) {
					$bar.css( 'width', '100%' );
					$button.prop( 'disabled', false );
					$status.text( settings.i18n.done + ' (' + updated + ')' );
					return;
				}

				step( data.page );
			} ).fail( function () {
				$button.prop( 'disabled', false );
				$status.text( settings.i18n.failed );
			} );
		}

		$button.on( 'click', function () {
			if ( ! window.confirm( settings.i18n.confirm ) ) {
				return;
			}

			updated = 0;
			$button.prop( 'disabled', true );
			$progress.prop( 'hidden', false );
			$bar.css( 'width', '0%' );
			$status.text( settings.i18n.running );

			step( 1 );
		} );
	}

	/* -----------------------------------------------------------------
	 * Design tab: dim the colour pickers while "inherit" is selected, so
	 * it is obvious they are not in effect.
	 * -------------------------------------------------------------- */
	function initColorSource() {
		var $select = $( '#rhot-color_source' );
		var $table = $( '.rhot-colors' );

		if ( ! $select.length || ! $table.length ) {
			return;
		}

		function sync() {
			$table.toggleClass( 'rhot-colors--inactive', 'custom' !== $select.val() );
		}

		$select.on( 'change', sync );
		sync();
	}

	$( function () {
		initColorPickers( document );
		initRepeater();
		initBackfill();
		initColorSource();
	} );
} )( jQuery );
