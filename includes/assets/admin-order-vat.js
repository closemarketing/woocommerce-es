/**
 * EU VAT validation on the admin order screen.
 *
 * Validates the billing VAT No field against VIES, stores the exemption on the
 * order and refreshes the "EU VAT" metabox. Vanilla JS, no jQuery.
 */
( function () {
	'use strict';

	const config = window.conecomOrderVAT;
	const box    = document.getElementById( 'conecom-order-vat-box' );
	if ( ! config || ! box ) {
		return;
	}

	const button     = box.querySelector( '.conecom-order-vat-validate' );
	const statusEl   = box.querySelector( '.conecom-order-vat-status' );
	const numberEl   = box.querySelector( '.conecom-order-vat-number' );
	const exemptEl   = box.querySelector( '.conecom-order-vat-exempt' );
	const messageEl  = box.querySelector( '.conecom-order-vat-message' );
	const fieldValue = ( id ) => {
		const field = document.getElementById( id );
		return field ? field.value.trim() : '';
	};

	let lastChecked = null;

	const setStatus = ( status ) => {
		statusEl.className   = 'conecom-order-vat-status conecom-order-vat-status--' + status;
		statusEl.textContent = config.messages[ status ] || status;
	};

	const validate = () => {
		const vatNumber = fieldValue( '_billing_vat' );
		const request   = vatNumber + '|' + fieldValue( '_billing_country' ) + '|' + fieldValue( '_shipping_country' );
		if ( request === lastChecked ) {
			return;
		}
		lastChecked = request;

		button.disabled       = true;
		messageEl.textContent = config.messages.checking;

		const body = new URLSearchParams( {
			action: config.action,
			security: config.nonce,
			order_id: box.dataset.orderId,
			vat_number: vatNumber,
			billing_country: fieldValue( '_billing_country' ),
			shipping_country: fieldValue( '_shipping_country' ),
		} );

		fetch( config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body,
		} )
			.then( ( response ) => response.json() )
			.then( ( response ) => {
				if ( ! response.success ) {
					throw new Error( response.data && response.data.message ? response.data.message : config.messages.error );
				}
				const data = response.data;

				numberEl.textContent = vatNumber || '—';
				exemptEl.textContent = data.vat_exempt ? config.messages.yes : config.messages.no;
				setStatus( data.status );
				messageEl.textContent = ( data.message ? data.message + ' ' : '' ) + config.messages.recalculate;
			} )
			.catch( ( error ) => {
				lastChecked           = null;
				messageEl.textContent = error.message || config.messages.error;
			} )
			.finally( () => {
				button.disabled = false;
			} );
	};

	button.addEventListener( 'click', () => {
		lastChecked = null;
		validate();
	} );

	// Validate automatically when the VAT No field changes.
	document.addEventListener( 'change', ( event ) => {
		if ( event.target && '_billing_vat' === event.target.id ) {
			validate();
		}
	} );
} )();
