/**
 * Gated Downloads public JS (vanilla, no jQuery): modal and capture form.
 */
( function ( data ) {
	'use strict';

	var FOCUSABLE = 'a[href], button:not([disabled]), textarea, input:not([disabled]), select, [tabindex]:not([tabindex="-1"])';
	var lastTrigger = null;
	var beaconSent = {};
	var UTM_KEYS = [ 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' ];
	var ATTR_STORE = 'tslgd_attribution';

	/**
	 * Remember where this visit came from, once per session (first touch).
	 */
	function captureAttribution() {
		var stored = readAttribution();
		if ( stored ) {
			return stored;
		}

		var params = new URLSearchParams( window.location.search );
		var data = {};

		UTM_KEYS.forEach( function ( key ) {
			var value = params.get( key );
			if ( value ) {
				data[ key ] = String( value ).slice( 0, 200 );
			}
		} );

		// Only an external referrer is meaningful as a traffic source.
		if ( document.referrer && document.referrer.indexOf( window.location.host ) === -1 ) {
			data.referrer = document.referrer.slice( 0, 300 );
		}

		if ( ! Object.keys( data ).length ) {
			return null;
		}

		try {
			window.sessionStorage.setItem( ATTR_STORE, JSON.stringify( data ) );
		} catch ( e ) {}

		return data;
	}

	function readAttribution() {
		try {
			var raw = window.sessionStorage.getItem( ATTR_STORE );
			return raw ? JSON.parse( raw ) : null;
		} catch ( e ) {
			return null;
		}
	}

	/**
	 * Dispatch a public DOM event. Site owners and add-ons (including Pro's
	 * analytics events) listen for these instead of patching this file.
	 */
	function emit( name, detail ) {
		try {
			document.dispatchEvent( new CustomEvent( name, { detail: detail } ) );
		} catch ( e ) {}
	}

	/**
	 * Anonymous analytics beacon (view/open counters). Fire-and-forget and
	 * deduped per gate per event per pageview.
	 */
	function beacon( gateId, event ) {
		if ( ! data.beaconUrl || ! gateId ) {
			return;
		}
		var key = gateId + ':' + event;
		if ( beaconSent[ key ] ) {
			return;
		}
		beaconSent[ key ] = true;

		var body = new FormData();
		body.append( 'gate_id', gateId );
		body.append( 'event', event );

		if ( navigator.sendBeacon && navigator.sendBeacon( data.beaconUrl, body ) ) {
			return;
		}
		fetch( data.beaconUrl, { method: 'POST', body: body, keepalive: true } ).catch( function () {} );
	}

	function initBeacons() {
		// Any trigger bound to a gate. The Pro popup's hidden trigger has no data-gate.
		document.querySelectorAll( '[data-modal][data-gate]' ).forEach( function ( trigger ) {
			beacon( trigger.getAttribute( 'data-gate' ), 'view' );
		} );
	}

	/**
	 * Content Locker: swap locked placeholders for the real content,
	 * fetched from the REST unlock endpoint.
	 */
	function hasUnlockCookie( gateId ) {
		return -1 !== ( '; ' + document.cookie ).indexOf( '; tslgd_unlock_' + gateId + '=' );
	}

	function unlockLocks( gateId ) {
		var locks = document.querySelectorAll( '.tslgd-lock[data-gate="' + gateId + '"]:not(.is-unlocked)' );
		if ( ! locks.length || ! data.unlockUrl ) {
			return;
		}

		// One request per source post; chunks come back in source order.
		var byPost = {};
		locks.forEach( function ( el ) {
			var postId = el.getAttribute( 'data-post' ) || '0';
			( byPost[ postId ] = byPost[ postId ] || [] ).push( el );
		} );

		Object.keys( byPost ).forEach( function ( postId ) {
			var body = new FormData();
			body.append( 'gate_id', gateId );
			body.append( 'post_id', postId );

			fetch( data.unlockUrl, { method: 'POST', body: body, credentials: 'same-origin' } )
				.then( function ( res ) {
					return res.ok ? res.json() : Promise.reject();
				} )
				.then( function ( out ) {
					var items = ( out && out.items ) || [];
					byPost[ postId ].forEach( function ( el, i ) {
						if ( 'string' === typeof items[ i ] ) {
							el.classList.add( 'is-unlocked' );
							el.innerHTML = items[ i ];
						}
					} );
				} )
				.catch( function () {} );
		} );
	}

	function initLocks() {
		var gates = {};
		document.querySelectorAll( '.tslgd-lock[data-gate]' ).forEach( function ( el ) {
			gates[ el.getAttribute( 'data-gate' ) ] = true;
		} );
		Object.keys( gates ).forEach( function ( gateId ) {
			if ( hasUnlockCookie( gateId ) ) {
				unlockLocks( gateId );
			}
		} );
	}

	function init() {
		captureAttribution();
		initBeacons();
		initLocks();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

	document.addEventListener( 'click', function ( e ) {
		var trigger = e.target.closest( '.tslgd-btn, .tslgd-link' );
		if ( trigger ) {
			e.preventDefault(); // Inline link triggers are <a href="#">.
			beacon( trigger.getAttribute( 'data-gate' ), 'open' );
			openModal( trigger.getAttribute( 'data-modal' ), trigger );
			return;
		}

		if ( e.target.closest( '.tslgd-modal__close' ) ) {
			closeModal( e.target.closest( '.tslgd-overlay' ) );
			return;
		}

		if ( e.target.classList && e.target.classList.contains( 'tslgd-overlay' ) ) {
			closeModal( e.target );
		}
	} );

	document.addEventListener( 'keydown', function ( e ) {
		var open = document.querySelector( '.tslgd-overlay.is-open' );
		if ( ! open ) {
			return;
		}
		if ( 'Escape' === e.key ) {
			closeModal( open );
		} else if ( 'Tab' === e.key ) {
			trapFocus( e, open );
		}
	} );

	document.addEventListener( 'submit', function ( e ) {
		var form = e.target.closest( '.tslgd-form' );
		if ( form ) {
			e.preventDefault();
			submitForm( form );
		}
	} );

	function openModal( id, trigger ) {
		var overlay = document.getElementById( id );
		if ( ! overlay ) {
			return;
		}
		lastTrigger = trigger || null;
		overlay.hidden = false;
		overlay.classList.add( 'is-open' );
		document.body.style.overflow = 'hidden';

		var first = overlay.querySelector( FOCUSABLE );
		if ( first ) {
			first.focus();
		}
	}

	function closeModal( overlay ) {
		if ( ! overlay ) {
			return;
		}
		overlay.classList.remove( 'is-open' );
		overlay.hidden = true;
		document.body.style.overflow = '';
		if ( lastTrigger ) {
			lastTrigger.focus();
			lastTrigger = null;
		}
	}

	function trapFocus( e, container ) {
		var items = Array.prototype.slice.call( container.querySelectorAll( FOCUSABLE ) )
			.filter( function ( el ) { return el.offsetParent !== null; } );
		if ( ! items.length ) {
			return;
		}
		var first = items[ 0 ];
		var last = items[ items.length - 1 ];

		if ( e.shiftKey && document.activeElement === first ) {
			e.preventDefault();
			last.focus();
		} else if ( ! e.shiftKey && document.activeElement === last ) {
			e.preventDefault();
			first.focus();
		}
	}

	function submitForm( form ) {
		var btn = form.querySelector( '.tslgd-submit' );
		var msg = form.querySelector( '.tslgd-form__message' );

		clearErrors( form );
		if ( ! validate( form ) ) {
			return;
		}

		setLoading( btn, true );
		msg.className = 'tslgd-form__message';
		msg.textContent = '';

		var payload = new FormData( form );
		payload.append( 'nonce', data.nonce );
		payload.append( 'source_url', window.location.href );

		var attribution = readAttribution() || captureAttribution();
		if ( attribution ) {
			Object.keys( attribution ).forEach( function ( key ) {
				payload.append( 'tslgd_attr[' + key + ']', attribution[ key ] );
			} );
		}

		fetch( data.restUrl, {
			method: 'POST',
			headers: { 'X-WP-Nonce': data.nonce },
			body: payload
		} )
			.then( function ( res ) {
				return res.json().then( function ( body ) {
					return { ok: res.ok, body: body };
				} );
			} )
			.then( function ( result ) {
				setLoading( btn, false );
				// A 200 response means the lead was saved. Show success even when
				// there is no file and no redirect (form used as a simple email
				// opt-in / subscribe form).
				if ( result.ok ) {
					showSuccess( form, result.body );
				} else {
					msg.classList.add( 'is-error' );
					msg.textContent = ( result.body && result.body.message ) || data.i18n.error;
				}
			} )
			.catch( function () {
				setLoading( btn, false );
				msg.classList.add( 'is-error' );
				msg.textContent = data.i18n.error;
			} );
	}

	function validate( form ) {
		var valid = true;
		form.querySelectorAll( 'input[required]' ).forEach( function ( input ) {
			var value = ( input.value || '' ).trim();
			if ( 'checkbox' === input.type ) {
				if ( ! input.checked ) {
					valid = false;
				}
				return;
			}
			if ( ! value ) {
				setError( input, data.i18n.required );
				valid = false;
			} else if ( 'email' === input.type && ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( value ) ) {
				setError( input, data.i18n.email );
				valid = false;
			}
		} );
		return valid;
	}

	function setError( input, message ) {
		input.classList.add( 'is-invalid' );
		var err = input.parentNode.querySelector( '.tslgd-field-error' );
		if ( err ) {
			err.textContent = message;
		}
	}

	function clearErrors( form ) {
		form.querySelectorAll( '.tslgd-field-error' ).forEach( function ( el ) { el.textContent = ''; } );
		form.querySelectorAll( 'input' ).forEach( function ( el ) { el.classList.remove( 'is-invalid' ); } );
	}

	function setLoading( btn, loading ) {
		if ( ! btn ) {
			return;
		}
		btn.classList.toggle( 'is-loading', loading );
		btn.disabled = loading;
	}

	function showSuccess( form, body ) {
		// Reveal locked content on the page, the response has set the unlock cookie.
		var gateInput = form.querySelector( 'input[name="gate_id"]' );
		if ( gateInput && document.querySelector( '.tslgd-lock[data-gate="' + gateInput.value + '"]' ) ) {
			unlockLocks( gateInput.value );
		}

		emit( 'tslgd:lead', { gateId: gateInput ? gateInput.value : '', response: body } );

		form.querySelectorAll( '.tslgd-field, .tslgd-modal__footer, .tslgd-form__message' )
			.forEach( function ( el ) { el.style.display = 'none'; } );

		var wrap = document.createElement( 'div' );
		wrap.className = 'tslgd-success';
		wrap.innerHTML = '<div class="tslgd-success__icon">✓</div><h3></h3><p></p>';
		wrap.querySelector( 'h3' ).textContent = body.message || '';
		var container = form.querySelector( '.tslgd-modal__body' ) || form;
		container.appendChild( wrap );

		// A per-form redirect takes precedence over the file download.
		if ( body.redirect ) {
			setTimeout( function () {
				window.location.assign( body.redirect );
			}, 900 );
			return;
		}

		setTimeout( function () {
			deliver( body, gateInput ? gateInput.value : '' );
		}, 600 );

		setTimeout( function () {
			closeModal( form.closest( '.tslgd-overlay' ) );
		}, 3200 );
	}

	function deliver( body, gateId ) {
		if ( ! body.file_url ) {
			return;
		}

		// Count the delivery itself, not just the lead.
		beacon( gateId, 'download' );
		emit( 'tslgd:download', { gateId: gateId, url: body.file_url } );

		var link = document.createElement( 'a' );
		link.href = body.file_url;
		link.target = '_blank';
		link.rel = 'noopener';
		if ( 'download' === body.delivery ) {
			link.download = '';
		}
		document.body.appendChild( link );
		link.click();
		document.body.removeChild( link );
	}
}( window.TslgdData || {} ) );
