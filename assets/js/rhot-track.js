/**
 * RH Order Track — front-end behaviour.
 *
 * No jQuery dependency: the plugin must work on themes that dequeue it.
 */
( function () {
	'use strict';

	if ( typeof window.rhotTrack === 'undefined' ) {
		return;
	}

	var settings = window.rhotTrack;

	function post( data ) {
		var body = new URLSearchParams();

		Object.keys( data ).forEach( function ( key ) {
			body.append( key, data[ key ] );
		} );

		return fetch( settings.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		} ).then( function ( response ) {
			return response.json();
		} );
	}

	function message( type, text ) {
		var div = document.createElement( 'div' );
		div.className = 'rhot-message rhot-message--' + type;
		div.textContent = text;
		return div;
	}

	function Tracker( root ) {
		this.root = root;
		this.form = root.querySelector( '.rhot-form' );
		this.result = root.querySelector( '.rhot-result' );
		this.button = root.querySelector( '.rhot-btn' );

		if ( ! this.form || ! this.result || ! this.button ) {
			return;
		}

		this.busy = false;

		this.form.addEventListener( 'submit', this.onSubmit.bind( this ) );

		this.prefill();
	}

	/**
	 * Reads the collected field values, and flags empty required fields.
	 * Returns null when the form is not ready to submit.
	 */
	Tracker.prototype.collect = function () {
		var data = {};
		var valid = true;

		var inputs = this.form.querySelectorAll( '.rhot-input' );

		Array.prototype.forEach.call( inputs, function ( input ) {
			var value = input.value.trim();

			if ( input.hasAttribute( 'required' ) && '' === value ) {
				input.setAttribute( 'aria-invalid', 'true' );
				valid = false;
			} else {
				input.removeAttribute( 'aria-invalid' );
			}

			data[ input.name ] = value;
		} );

		if ( ! valid ) {
			return null;
		}

		// "Either" mode marks nothing required, so guard against an entirely
		// blank submission reaching the server.
		var filled = Object.keys( data ).some( function ( key ) {
			return '' !== data[ key ];
		} );

		if ( ! filled ) {
			return null;
		}

		var nonce = this.form.querySelector( 'input[name="rhot_nonce"]' );
		var mode = this.form.querySelector( 'input[name="rhot_mode"]' );

		data.action = settings.action;
		data.rhot_nonce = nonce ? nonce.value : '';
		data.rhot_mode = mode ? mode.value : '';

		return data;
	};

	Tracker.prototype.setBusy = function ( busy ) {
		this.busy = busy;
		this.button.classList.toggle( 'is-loading', busy );
		this.button.disabled = busy;
	};

	Tracker.prototype.show = function ( node ) {
		this.result.innerHTML = '';
		this.result.appendChild( node );
	};

	Tracker.prototype.showHtml = function ( html ) {
		this.result.innerHTML = html;
	};

	Tracker.prototype.onSubmit = function ( event ) {
		event.preventDefault();

		if ( this.busy ) {
			return;
		}

		var data = this.collect();

		if ( ! data ) {
			this.show( message( 'error', settings.i18n.empty ) );
			return;
		}

		this.lookup( data, true );
	};

	/**
	 * @param {Object}  data     Payload.
	 * @param {boolean} mayRetry Whether a stale nonce may be refreshed once.
	 */
	Tracker.prototype.lookup = function ( data, mayRetry ) {
		var self = this;

		this.setBusy( true );
		this.result.innerHTML = '';

		post( data ).then( function ( response ) {
			if ( response && response.success ) {
				self.setBusy( false );
				self.showHtml( response.data.html );
				return;
			}

			var payload = ( response && response.data ) || {};

			// On a full-page-cached site the nonce baked into the HTML goes
			// stale after a day. Fetch a fresh one and retry once, silently,
			// rather than showing the visitor a session error.
			if ( 'invalid_nonce' === payload.code && mayRetry ) {
				post( { action: settings.nonceAction } ).then( function ( refreshed ) {
					if ( refreshed && refreshed.success && refreshed.data.nonce ) {
						var field = self.form.querySelector( 'input[name="rhot_nonce"]' );

						if ( field ) {
							field.value = refreshed.data.nonce;
						}

						data.rhot_nonce = refreshed.data.nonce;
						self.lookup( data, false );
						return;
					}

					self.setBusy( false );
					self.show( message( 'error', payload.message || settings.i18n.error ) );
				} ).catch( function () {
					self.setBusy( false );
					self.show( message( 'error', settings.i18n.error ) );
				} );

				return;
			}

			self.setBusy( false );
			// A "hidden status" reply is not a failure — the order was found,
			// the shop just chose not to publish that stage.
			self.show( message( 'hidden' === payload.code ? 'info' : 'error', payload.message || settings.i18n.error ) );
		} ).catch( function () {
			self.setBusy( false );
			self.show( message( 'error', settings.i18n.error ) );
		} );
	};

	/**
	 * Fills the form from ?rhot_order= / ?rhot_phone= and submits, so a link
	 * in an SMS or order email can land the customer straight on their result.
	 */
	Tracker.prototype.prefill = function () {
		if ( 'yes' !== settings.urlPrefill ) {
			return;
		}

		var params = new URLSearchParams( window.location.search );
		var filled = false;

		[ 'rhot_order', 'rhot_phone', 'rhot_email' ].forEach( function ( key ) {
			var value = params.get( key );

			if ( ! value ) {
				return;
			}

			var input = this.form.querySelector( '[name="' + key + '"]' );

			if ( input ) {
				input.value = value;
				filled = true;
			}
		}.bind( this ) );

		if ( filled ) {
			var data = this.collect();

			if ( data ) {
				this.lookup( data, true );
			}
		}
	};

	function init( context ) {
		var roots = ( context || document ).querySelectorAll( '.rhot-track' );

		Array.prototype.forEach.call( roots, function ( root ) {
			if ( root.dataset.rhotReady ) {
				return;
			}

			root.dataset.rhotReady = '1';
			new Tracker( root );
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			init( document );
		} );
	} else {
		init( document );
	}

	// Elementor re-renders widgets in the editor and on popup/lightbox open,
	// so bind again whenever it announces a new frontend element.
	window.addEventListener( 'elementor/frontend/init', function () {
		if ( window.elementorFrontend && window.elementorFrontend.hooks ) {
			window.elementorFrontend.hooks.addAction( 'frontend/element_ready/rhot_order_track.default', function ( $scope ) {
				init( $scope && $scope[ 0 ] ? $scope[ 0 ] : document );
			} );
		}
	} );
} )();
