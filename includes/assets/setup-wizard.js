/* Connect Ecommerce — Setup Wizard (vanilla JS) */
(function () {
	'use strict';

	var cfg = window.conecomWizard;
	if ( ! cfg ) { return; }

	// ── State ────────────────────────────────────────────────────────────────

	var currentStep      = 1;
	var totalSteps       = 6;
	var connectionTested = false; // Tracks whether the current connector passed a test.

	// ── DOM helpers ──────────────────────────────────────────────────────────

	function $( id )     { return document.getElementById( id ); }
	function qs( sel )   { return document.querySelector( sel ); }
	function qsa( sel )  { return document.querySelectorAll( sel ); }

	function show( el )  { if ( el ) { el.hidden = false; } }
	function hide( el )  { if ( el ) { el.hidden = true;  } }

	function setClass( el, cls, on ) {
		if ( ! el ) { return; }
		on ? el.classList.add( cls ) : el.classList.remove( cls );
	}

	// ── AJAX helper ──────────────────────────────────────────────────────────

	function post( action, data ) {
		var body = new URLSearchParams( data );
		body.append( 'action', action );
		body.append( 'nonce',  cfg.nonce );

		return fetch( cfg.ajaxUrl, {
			method:  'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body:    body.toString(),
		} ).then( function ( r ) { return r.json(); } );
	}

	// ── Step navigation ──────────────────────────────────────────────────────

	function goTo( n ) {
		if ( n < 1 || n > totalSteps ) { return; }

		// Hide current panel.
		var prev = qs( '.wiz-panel.is-active' );
		if ( prev ) { prev.classList.remove( 'is-active' ); }

		// Show target panel.
		var next = qs( '.wiz-panel[data-step="' + n + '"]' );
		if ( next ) { next.classList.add( 'is-active' ); }

		updateProgress( n );
		currentStep = n;

		// Mark wizard complete when reaching the done step.
		if ( 6 === n ) {
			post( 'conecom_wizard_complete', {} );
		}

		// Scroll card into view (top of page on mobile).
		var card = qs( '.wiz-card' );
		if ( card ) { card.scrollIntoView( { behavior: 'smooth', block: 'start' } ); }
	}

	function updateProgress( active ) {
		qsa( '.wiz-step-node' ).forEach( function ( node ) {
			var n = parseInt( node.dataset.step, 10 );
			setClass( node, 'is-active', n === active );
			setClass( node, 'is-done',   n < active );
		} );

		qsa( '.wiz-step-line' ).forEach( function ( line, idx ) {
			setClass( line, 'is-done', idx + 1 < active );
		} );
	}

	// ── Connector selection ──────────────────────────────────────────────────

	function showConnectorFields( slug ) {
		qsa( '.wiz-conn-fields' ).forEach( function ( el ) { hide( el ); } );
		var fields = qs( '[data-connector-fields="' + slug + '"]' );
		if ( fields ) { show( fields ); }
	}

	function selectedConnector() {
		var radio = qs( 'input[name="wiz_connector"]:checked' );
		return radio ? radio.value : '';
	}

	function getConnectorFields( slug ) {
		var data  = { connector: slug };
		var wrap  = qs( '[data-connector-fields="' + slug + '"]' );
		if ( ! wrap ) { return data; }

		wrap.querySelectorAll( 'input, select, textarea' ).forEach( function ( inp ) {
			if ( inp.name ) { data[ inp.name ] = inp.value; }
		} );
		return data;
	}

	// ── Step 2: Connection ───────────────────────────────────────────────────

	function initStep2() {
		var grid    = $( 'js-connector-grid' );
		var nextBtn = $( 'js-conn-next' );
		var panel   = qs( '.wiz-panel[data-step="2"]' );

		// If a connector is already pre-selected, show its fields.
		var preSelected = qs( 'input[name="wiz_connector"]:checked' );
		if ( preSelected ) {
			showConnectorFields( preSelected.value );
			nextBtn.disabled = false; // Allow continuing with pre-existing saved settings.
		}

		if ( grid ) {
			grid.addEventListener( 'change', function ( e ) {
				if ( 'wiz_connector' === e.target.name ) {
					// Deselect all cards.
					qsa( '.wiz-connector-card' ).forEach( function ( c ) {
						setClass( c, 'is-selected', false );
					} );
					// Select this card.
					var card = e.target.closest( '.wiz-connector-card' );
					if ( card ) { setClass( card, 'is-selected', true ); }

					showConnectorFields( e.target.value );
					connectionTested = false;
					nextBtn.disabled = true;

					var msg = qs( '[data-connector-fields="' + e.target.value + '"] .js-test-msg' );
					if ( msg ) { msg.textContent = ''; msg.className = 'wiz-test-msg js-test-msg'; }
				}
			} );
		}

		// Delegate the click: each connector renders its own "Test connection"
		// button/message pair inside its own .wiz-conn-fields block, so a single
		// getElementById() would only ever reach the first one in the DOM.
		if ( panel ) {
			panel.addEventListener( 'click', function ( e ) {
				var testBtn = e.target.closest( '.js-test-conn' );
				if ( ! testBtn ) { return; }

				var slug = selectedConnector();
				if ( ! slug ) { return; }

				var testMsg = testBtn.closest( '.wiz-conn-fields' ).querySelector( '.js-test-msg' );

				testBtn.disabled    = true;
				testBtn.textContent = cfg.i18n.testing;
				testMsg.textContent = '';
				testMsg.className   = 'wiz-test-msg js-test-msg';

				post( 'conecom_wizard_test_connection', getConnectorFields( slug ) )
					.then( function ( res ) {
						testBtn.disabled    = false;
						testBtn.textContent = 'Test connection';

						if ( res.success ) {
							testMsg.textContent = res.data.message || cfg.i18n.connOk;
							testMsg.className   = 'wiz-test-msg js-test-msg is-ok';
							connectionTested    = true;
							nextBtn.disabled    = false;
						} else {
							testMsg.textContent = res.data.message || 'Connection failed.';
							testMsg.className   = 'wiz-test-msg js-test-msg is-error';
							connectionTested    = false;
						}
					} )
					.catch( function () {
						testBtn.disabled    = false;
						testBtn.textContent = 'Test connection';
						testMsg.textContent = 'Request failed. Please try again.';
						testMsg.className   = 'wiz-test-msg js-test-msg is-error';
					} );
			} );
		}
	}

	// ── Step 3: VAT ──────────────────────────────────────────────────────────

	function initStep3() {
		var vatShow    = $( 'wiz_vat_show' );
		var extras     = [ $( 'wiz-vat-extra' ), $( 'wiz-vies-field' ), $( 'wiz-vatsense-field' ) ];

		function toggleVatExtras() {
			var on = vatShow && 'yes' === vatShow.value;
			extras.forEach( function ( el ) {
				if ( on ) { show( el ); } else { hide( el ); }
			} );
		}

		if ( vatShow ) {
			vatShow.addEventListener( 'change', toggleVatExtras );
		}

		var vatNextBtn = $( 'js-vat-next' );
		if ( vatNextBtn ) {
			vatNextBtn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				vatNextBtn.disabled    = true;
				vatNextBtn.textContent = cfg.i18n.saving;

				var data = {};
				[ 'vat_show', 'vat_mandatory', 'vat_vies_enabled', 'vatsense_api_key' ].forEach( function ( f ) {
					var el = qs( '[name="' + f + '"]' );
					if ( el ) { data[ f ] = el.value; }
				} );

				post( 'conecom_wizard_save_vat', data )
					.then( function () {
						vatNextBtn.disabled    = false;
						vatNextBtn.textContent = 'Save & Continue';
						goTo( 4 );
					} )
					.catch( function () {
						vatNextBtn.disabled    = false;
						vatNextBtn.textContent = 'Save & Continue';
						goTo( 4 ); // Proceed even on network error.
					} );
			} );
		}
	}

	// ── Step 4: AI ───────────────────────────────────────────────────────────

	function initStep4() {
		var aiSaveBtn = $( 'js-ai-save' );
		if ( ! aiSaveBtn ) { return; }

		aiSaveBtn.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			aiSaveBtn.disabled    = true;
			aiSaveBtn.textContent = cfg.i18n.saving;

			var data = {};
			var modelEl  = qs( '[name="ai_model"]' );
			var promptEl = qs( '[name="ai_prompt"]' );
			if ( modelEl )  { data.ai_model  = modelEl.value;  }
			if ( promptEl ) { data.ai_prompt = promptEl.value; }

			post( 'conecom_wizard_save_ai', data )
				.then( function () {
					aiSaveBtn.disabled    = false;
					aiSaveBtn.textContent = 'Save & Continue';
					goTo( 5 );
				} )
				.catch( function () {
					aiSaveBtn.disabled    = false;
					aiSaveBtn.textContent = 'Save & Continue';
					goTo( 5 );
				} );
		} );
	}

	// ── Step 5: Sync ─────────────────────────────────────────────────────────

	function initStep5() {
		var startBtn     = $( 'js-start-sync' );
		var syncIdle     = $( 'js-sync-idle' );
		var syncRunning  = $( 'js-sync-running' );
		var syncDone     = $( 'js-sync-done' );
		var syncBar      = $( 'js-sync-bar' );
		var syncLog      = $( 'js-sync-log' );
		var syncSummary  = $( 'js-sync-summary' );
		var syncSkip     = $( 'js-sync-skip' );
		var syncContinue = $( 'js-sync-continue' );
		var syncBack     = $( 'js-sync-back' );

		if ( ! startBtn ) { return; }

		var loop         = 0;
		var productCount = 0;
		var finished     = false;

		function appendLog( msg ) {
			if ( ! syncLog ) { return; }
			var line = document.createElement( 'div' );
			// Strip HTML tags from message for safe display.
			var tmp = document.createElement( 'div' );
			tmp.innerHTML = msg;
			line.textContent = tmp.textContent || tmp.innerText || msg;
			syncLog.appendChild( line );
			syncLog.scrollTop = syncLog.scrollHeight;
		}

		function updateBar( done, total ) {
			if ( ! syncBar || ! total ) { return; }
			syncBar.style.width = Math.min( 100, Math.round( ( done / total ) * 100 ) ) + '%';
		}

		function runLoop() {
			if ( finished ) { return; }

			var body = new URLSearchParams( {
				action:     'connect_ecommerce_sync_products',
				nonce:      cfg.syncNonce,
				loop:       loop,
				pagination: 50,
			} );

			fetch( cfg.ajaxUrl, {
				method:  'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body:    body.toString(),
			} )
			.then( function ( r ) { return r.json(); } )
			.then( function ( res ) {
				if ( ! res.success ) {
					finished = true;
					hide( syncRunning );
					show( syncDone );
					if ( syncSummary ) {
						syncSummary.textContent = 'Sync stopped: ' + ( res.data.message || 'Unexpected error.' );
					}
					show( syncContinue );
					return;
				}

				var d = res.data;
				if ( d.message )       { appendLog( d.message ); }
				if ( d.product_count ) { productCount = d.product_count; }
				updateBar( loop + 1, productCount );

				if ( d.finish ) {
					finished = true;
					syncBar.style.width = '100%';
					hide( syncRunning );
					show( syncDone );
					if ( syncSummary ) {
						syncSummary.textContent = cfg.i18n.syncDone + ' ' + ( productCount ? productCount + ' products processed.' : '' );
					}
					hide( syncSkip );
					show( syncContinue );
				} else {
					loop = d.loop;
					runLoop();
				}
			} )
			.catch( function () {
				appendLog( 'Network error — sync interrupted.' );
				finished = true;
				show( syncContinue );
			} );
		}

		startBtn.addEventListener( 'click', function () {
			hide( syncIdle );
			show( syncRunning );
			if ( syncBack ) { syncBack.disabled = true; }
			runLoop();
		} );
	}

	// ── Skip wizard ──────────────────────────────────────────────────────────

	function initSkip() {
		var skipLink = $( 'js-skip-wizard' );
		if ( ! skipLink ) { return; }

		skipLink.addEventListener( 'click', function () {
			post( 'conecom_wizard_complete', {} );
		} );
	}

	// ── Generic next/back buttons ─────────────────────────────────────────────

	function initNavButtons() {
		document.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '.wiz-next-btn' );
			if ( btn && ! btn.disabled ) {
				var next = parseInt( btn.dataset.next, 10 );
				if ( next ) { goTo( next ); }
				return;
			}

			var back = e.target.closest( '.wiz-back-btn' );
			if ( back ) {
				var prev = parseInt( back.dataset.back, 10 );
				if ( prev ) { goTo( prev ); }
			}
		} );
	}

	// ── Step 2 "Save & Continue" — saves connection before navigating ─────────

	function initConnNext() {
		var connNext = $( 'js-conn-next' );
		if ( ! connNext ) { return; }

		connNext.addEventListener( 'click', function ( e ) {
			e.stopPropagation(); // Prevent the generic handler from also firing.
			var slug = selectedConnector();
			if ( ! slug ) { return; }

			connNext.disabled    = true;
			connNext.textContent = cfg.i18n.saving;

			post( 'conecom_wizard_save_connection', getConnectorFields( slug ) )
				.then( function () {
					connNext.disabled    = false;
					connNext.textContent = 'Save & Continue';
					goTo( 3 );
				} )
				.catch( function () {
					connNext.disabled    = false;
					connNext.textContent = 'Save & Continue';
					goTo( 3 );
				} );
		} );
	}

	// ── Boot ─────────────────────────────────────────────────────────────────

	function init() {
		initNavButtons();
		initSkip();
		initStep2();
		initConnNext();
		initStep3();
		initStep4();
		initStep5();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

}());
