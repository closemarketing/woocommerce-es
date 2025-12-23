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
				const vatField = this.getVATField();
				
				if ( vatField && ! vatField.dataset.conecomVatListener ) {
					vatField.dataset.conecomVatListener = 'true';
					this.attachValidationToField( vatField );
				}
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
		 * Get VAT field in blocks context
		 * 
		 * @return {HTMLElement|null}
		 */
		getVATField() {
			// Try multiple selectors for blocks.
			const selectors = [
				'input[id*="billing_vat"]',
				'input[name*="billing_vat"]',
				'input[id*="billing-vat"]',
				'input[name*="connect_ecommerce/billing_vat"]',
			];

			for ( const selector of selectors ) {
				const field = document.querySelector( selector );
				if ( field ) {
					return field;
				}
			}

			return null;
		},

		/**
		 * Get country field in blocks
		 * 
		 * @return {HTMLElement|null}
		 */
		getCountryField() {
			return document.querySelector( 'select[id*="billing-country"]' ) ||
				   document.querySelector( 'select[id*="billing_country"]' );
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
			const countryField = this.getCountryField();
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

