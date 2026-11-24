/**
 * VAT Validation for WooCommerce Blocks
 * Integration with Gutenberg Checkout Blocks
 *
 * @package WordPress
 * @author  David Perez <david@close.technology>
 */

(function() {
	'use strict';

	const { registerCheckoutFilters } = window.wc.blocksCheckout;
	const { extensionCartUpdate } = window.wc.blocksCheckout;
	const { useState, useEffect } = window.wp.element;

	// VAT Validation state manager for blocks.
	const VATBlocksValidator = {
		config: window.conecomVATConfig || {},
		validationTimer: null,
		lastValidatedVAT: '',
		lastValidatedCountry: '',

		/**
		 * Initialize blocks integration
		 */
		init() {
			if ( ! this.config.enabled ) {
				return;
			}

			// Register additional field for VAT number.
			if ( window.wc?.wcBlocksRegistry?.registerCheckoutBlock ) {
				this.registerVATField();
			}

			// Listen to field changes via DOM since blocks use React.
			document.addEventListener( 'DOMContentLoaded', () => {
				this.setupBlocksListeners();
			} );
		},

		/**
		 * Setup listeners for blocks checkout
		 */
		setupBlocksListeners() {
			// Use MutationObserver to detect when blocks are rendered.
			const observer = new MutationObserver( () => {
				// Billing and shipping VAT fields.
				this.getVATFields().forEach( ( vatField ) => {
					if ( ! vatField.dataset.conecomVatListener ) {
						vatField.dataset.conecomVatListener = 'true';
						this.attachValidationToField( vatField );
					}
				} );
			} );

			// Observe checkout block container.
			const checkoutBlock = document.querySelector( '.wp-block-woocommerce-checkout' );
			if ( checkoutBlock ) {
				observer.observe( checkoutBlock, {
					childList: true,
					subtree: true,
				} );
			}
		},

		/**
		 * Address type ('billing' | 'shipping') of a field.
		 *
		 * @param {HTMLElement} field
		 * @return {string}
		 */
		getAddressType( field ) {
			const ref = ( ( field.id || '' ) + ' ' + ( field.name || '' ) ).toLowerCase();
			return ref.indexOf( 'shipping' ) !== -1 ? 'shipping' : 'billing';
		},

		/**
		 * Get all VAT fields in blocks context (billing and shipping).
		 *
		 * @return {HTMLElement[]}
		 */
		getVATFields() {
			const selectors = [
				'input[id*="billing_vat"]',
				'input[name*="billing_vat"]',
				'input[id*="billing-vat"]',
				'input[name*="connect_ecommerce/billing_vat"]',
			];
			const found = [];
			selectors.forEach( ( selector ) => {
				document.querySelectorAll( selector ).forEach( ( field ) => {
					if ( found.indexOf( field ) === -1 ) {
						found.push( field );
					}
				} );
			} );
			return found;
		},

		/**
		 * Get the VAT field to validate: the priority address first, billing as fallback.
		 * Only fields with a value are considered; falls back to the first field found.
		 *
		 * @return {HTMLElement|null}
		 */
		getVATField() {
			const fields = this.getVATFields();
			if ( ! fields.length ) {
				return null;
			}
			const order = this.config.priority === 'shipping' ? [ 'shipping', 'billing' ] : [ 'billing', 'shipping' ];
			for ( const type of order ) {
				const match = fields.find( ( f ) => this.getAddressType( f ) === type && f.value.trim() );
				if ( match ) {
					return match;
				}
			}
			return fields[ 0 ];
		},

		/**
		 * Get country field of the same address type as a VAT field.
		 *
		 * @param {HTMLElement} vatField
		 * @return {HTMLElement|null}
		 */
		getCountryField( vatField ) {
			const type = vatField ? this.getAddressType( vatField ) : 'billing';
			return document.querySelector( 'select[id*="' + type + '-country"]' ) ||
				   document.querySelector( 'select[id*="' + type + '_country"]' );
		},

		/**
		 * Attach validation to VAT field
		 * 
		 * @param {HTMLElement} vatField
		 */
		attachValidationToField( vatField ) {
			// Reuse validation logic from main validator.
			if ( window.ConecomVATValidator ) {
				vatField.addEventListener( 'input', ( e ) => {
					window.ConecomVATValidator.handleVATInput( e );
				} );

				vatField.addEventListener( 'blur', ( e ) => {
					window.ConecomVATValidator.handleVATBlur( e );
				} );

				console.log( 'WooCommerce ES: VAT validation attached to blocks field' );
			}
		},

		/**
		 * Register VAT field for blocks if using Store API
		 */
		registerVATField() {
			// This would require building with @wordpress/scripts
			// For now, we rely on the woocommerce_register_additional_checkout_field
			// which already handles blocks automatically.
		},

		/**
		 * Validate VAT before placing order in blocks
		 * 
		 * @param {Object} checkoutData
		 * @return {Object}
		 */
		async validateBeforeCheckout( checkoutData ) {
			const vatField = this.getVATField();
			if ( ! vatField ) {
				return { valid: true };
			}

			const vatNumber = vatField.value.trim();
			const countryField = this.getCountryField( vatField );
			const countryCode = countryField ? countryField.value : '';

			if ( ! vatNumber ) {
				return { valid: true };
			}

			// Validate using AJAX.
			try {
				const formData = new FormData();
				formData.append( 'action', 'conecom_validate_vat' );
				formData.append( 'security', this.config.nonce );
				formData.append( 'vat_number', vatNumber );
				formData.append( 'country_code', countryCode );

				const response = await fetch( this.config.ajaxUrl, {
					method: 'POST',
					body: formData,
					credentials: 'same-origin',
				} );

				const data = await response.json();

				if ( data.success && data.data && data.data.status === 'valid' ) {
					return { valid: true };
				}

				// Check if validation is mandatory.
				const isMandatory = this.config.vat_mandatory || false;
				
				if ( isMandatory ) {
					return {
						valid: false,
						message: data.data?.message || 'VAT validation failed',
					};
				}

				return { valid: true }; // Allow checkout but with warning.

			} catch ( error ) {
				console.error( 'VAT validation error in blocks:', error );
				return { valid: true }; // Fail-safe: allow checkout.
			}
		},
	};

	// Initialize when script loads.
	VATBlocksValidator.init();

	// Expose to window for external access.
	window.ConecomVATBlocksValidator = VATBlocksValidator;

	// Register validation hook if blocks checkout is available.
	if ( window.wp?.hooks?.addFilter ) {
		window.wp.hooks.addFilter(
			'woocommerce_blocks_checkout_validation_errors',
			'conecom-vat-validation',
			async function( errors, checkoutData ) {
				const result = await VATBlocksValidator.validateBeforeCheckout( checkoutData );
				
				if ( ! result.valid ) {
					errors.push( {
						message: result.message,
						hidden: false,
					} );
				}
				
				return errors;
			}
		);
	}

})();

