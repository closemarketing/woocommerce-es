/**
 * VAT Real-time Validation
 * Modern vanilla JavaScript implementation for VAT number validation
 *
 * @package WordPress
 * @author  David Perez <david@close.technology>
 */

(function() {
	'use strict';

	// Configuration object.
	const VATValidator = {
		config: {
			minLengths: window.conecomVATConfig?.minLengths || {},
			ajaxUrl: window.conecomVATConfig?.ajaxUrl || '/wp-admin/admin-ajax.php',
			nonce: window.conecomVATConfig?.nonce || '',
			debounceDelay: 800, // Different from the 700ms of the other plugin.
			enabled: window.conecomVATConfig?.enabled || false,
		},

		state: {
			lastVAT: '',
			lastCountry: '',
			lastType: '',
			activeType: 'billing',
			validationTimer: null,
			isValidating: false,
			currentRequest: null,
		},

		/**
		 * Initialize the validator
		 */
		init() {
			if ( ! this.config.enabled ) {
				return;
			}

			// Wait for DOM to be ready.
			if ( document.readyState === 'loading' ) {
				document.addEventListener( 'DOMContentLoaded', () => this.setupListeners() );
			} else {
				this.setupListeners();
			}
		},

		/**
		 * Clean up duplicate feedback containers
		 */
		cleanupDuplicateContainers() {
			// One feedback container per address type (billing / shipping).
			const seen = {};
			document.querySelectorAll( '.conecom-vat-feedback' ).forEach( ( container ) => {
				const type = container.dataset.addressType || 'billing';
				if ( seen[ type ] ) {
					container.remove();
				} else {
					seen[ type ] = true;
				}
			} );
		},

		/**
		 * Setup event listeners for VAT field
		 */
		setupListeners() {
			// Clean up any duplicate feedback containers first.
			this.cleanupDuplicateContainers();
			
			// Billing and shipping VAT fields (shipping only exists in some checkouts).
			const vatFields = this.getVATFields();

			if ( ! vatFields.length ) {
				console.warn( 'WooCommerce ES: VAT field not found' );
				return;
			}

			vatFields.forEach( ( vatField ) => {
				if ( vatField.dataset.conecomVatBound ) {
					return;
				}
				vatField.dataset.conecomVatBound = 'true';
				// Listen to input events (more modern than keyup).
				vatField.addEventListener( 'input', ( e ) => this.handleVATInput( e ) );
				vatField.addEventListener( 'blur', ( e ) => this.handleVATBlur( e ) );
			} );

			// Listen to country changes of both addresses.
			[ 'billing', 'shipping' ].forEach( ( type ) => {
				const countryField = this.getCountryField( type );
				if ( countryField && ! countryField.dataset.conecomVatBound ) {
					countryField.dataset.conecomVatBound = 'true';
					countryField.addEventListener( 'change', () => {
						this.state.activeType = type;
						this.resetAndValidate();
					} );
				}
			} );

			// Listen to WooCommerce checkout updates.
			document.body.addEventListener( 'updated_checkout', () => this.reattachListeners() );
			
			// Listen to WooCommerce Blocks checkout updates.
			document.body.addEventListener( 'wc-blocks_added_to_cart', () => this.reattachListeners() );
			document.body.addEventListener( 'wc-blocks_removed_from_cart', () => this.reattachListeners() );

			console.log( 'WooCommerce ES: VAT validator initialized' );
		},

		/**
		 * Address type ('billing' | 'shipping') a field belongs to.
		 *
		 * @param {HTMLElement} field
		 * @return {string}
		 */
		getAddressType( field ) {
			const ref = ( ( field && ( field.id || '' ) ) + ' ' + ( field && ( field.name || '' ) ) ).toLowerCase();
			return ref.indexOf( 'shipping' ) !== -1 ? 'shipping' : 'billing';
		},

		/**
		 * Get all VAT input fields (billing and shipping, classic + blocks).
		 *
		 * @return {HTMLElement[]}
		 */
		getVATFields() {
			const selectors = [
				'#billing_vat',
				'#shipping_vat',
				'input[name="billing_vat"]',
				'input[name="shipping_vat"]',
				'input[name="connect_ecommerce/billing_vat"]',
				'input[id*="billing_vat"]',
				'input[id*="billing-vat"]',
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
		 * Get the VAT input field of an address type (defaults to the active one).
		 *
		 * @param {string} type
		 * @return {HTMLElement|null}
		 */
		getVATField( type ) {
			const wanted = type || this.state.activeType || 'billing';
			const fields = this.getVATFields();
			return fields.find( ( f ) => this.getAddressType( f ) === wanted ) || fields[ 0 ] || null;
		},

		/**
		 * Get country field of an address type (defaults to the active one).
		 *
		 * @param {string} type
		 * @return {HTMLElement|null}
		 */
		getCountryField( type ) {
			const t = type || this.state.activeType || 'billing';
			const selectors = [
				'#' + t + '_country',
				'select[name="' + t + '_country"]',
				'select[id*="' + t + '-country"]',
				'select[id*="' + t + '_country"]',
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
		 * Get feedback container for messages
		 * 
		 * @return {HTMLElement}
		 */
		getFeedbackContainer() {
			const type     = this.state.activeType || 'billing';
			const vatField = this.getVATField( type );
			if ( ! vatField ) {
				return null;
			}

			const fieldWrapper = vatField.closest( '.form-row' ) || vatField.parentElement;
			const id           = 'conecom-vat-feedback-container-' + type;

			let container = document.getElementById( id );

			if ( ! container ) {
				container = document.createElement( 'div' );
				container.id = id;
				container.className = 'conecom-vat-feedback';
				container.dataset.addressType = type;
				container.setAttribute( 'role', 'status' );
				container.setAttribute( 'aria-live', 'polite' );

				// Insert after the field wrapper.
				fieldWrapper.insertAdjacentElement( 'afterend', container );
			}

			return container;
		},

		/**
		 * Handle VAT input event
		 * 
		 * @param {Event} e
		 */
		handleVATInput( e ) {
			this.state.activeType = this.getAddressType( e.target );
			const vatValue = e.target.value.trim().toUpperCase();

			// Clear previous timer.
			if ( this.state.validationTimer ) {
				clearTimeout( this.state.validationTimer );
			}

			// Cancel previous request if still pending.
			if ( this.state.currentRequest ) {
				this.state.currentRequest.abort();
			}

			// Empty field - clear feedback and restore standard VAT.
			if ( ! vatValue ) {
				this.showFeedback( '', 'empty' );
				
				// If field was previously filled, update checkout to restore standard VAT.
				if ( this.state.lastVAT ) {
					this.state.lastVAT = '';
					this.state.lastCountry = '';
					this.updateCheckoutTotals();
				}
				
				return;
			}

			// Show "checking" state.
			this.showFeedback( window.conecomVATConfig?.messages?.checking || 'Checking...', 'checking' );

			// Set new timer with debounce.
			this.state.validationTimer = setTimeout( () => {
				this.performValidation( vatValue );
			}, this.config.debounceDelay );
		},

		/**
		 * Handle VAT blur event
		 * 
		 * @param {Event} e
		 */
		handleVATBlur( e ) {
			this.state.activeType = this.getAddressType( e.target );
			const vatValue = e.target.value.trim().toUpperCase();

			if ( vatValue && vatValue !== this.state.lastVAT ) {
				// Force immediate validation on blur.
				if ( this.state.validationTimer ) {
					clearTimeout( this.state.validationTimer );
				}
				this.performValidation( vatValue );
			}
		},

		/**
		 * Perform AJAX validation
		 * 
		 * @param {string} vatNumber
		 */
		async performValidation( vatNumber ) {
			const type         = this.state.activeType || 'billing';
			const countryField = this.getCountryField( type );
			const countryCode = countryField ? countryField.value : '';

			// Check if we've already validated this combination.
			if ( this.state.lastVAT === vatNumber && this.state.lastCountry === countryCode && this.state.lastType === type ) {
				return;
			}

			this.state.isValidating = true;
			this.state.lastVAT = vatNumber;
			this.state.lastCountry = countryCode;
			this.state.lastType = type;

			// Create FormData.
			const formData = new FormData();
			formData.append( 'action', 'conecom_validate_vat' );
			formData.append( 'security', this.config.nonce );
			formData.append( 'vat_number', vatNumber );
			formData.append( 'country_code', countryCode );

			try {
				// Create abort controller for this request.
				const controller = new AbortController();
				this.state.currentRequest = controller;

				const response = await fetch( this.config.ajaxUrl, {
					method: 'POST',
					body: formData,
					signal: controller.signal,
					credentials: 'same-origin',
				} );

				if ( ! response.ok ) {
					throw new Error( 'Network response was not ok' );
				}

				const data = await response.json();
				
				if ( data.success && data.data ) {
					this.handleValidationResponse( data.data );
				} else {
					this.showFeedback( 
						window.conecomVATConfig?.messages?.error || 'Validation error',
						'error'
					);
				}
			} catch ( error ) {
				// Ignore abort errors (user is still typing).
				if ( error.name !== 'AbortError' ) {
					console.error( 'VAT Validation Error:', error );
					this.showFeedback( 
						window.conecomVATConfig?.messages?.error || 'Validation error',
						'error'
					);
				}
			} finally {
				this.state.isValidating = false;
				this.state.currentRequest = null;
			}
		},

		/**
		 * Handle validation response
		 * 
		 * @param {Object} data
		 */
		handleValidationResponse( data ) {
			const { status, message, company_name, vat_exempt, exempt_message } = data;
			const messages = window.conecomVATConfig?.messages || {};

			let feedbackMessage = message;
			let feedbackClass = status;

			switch ( status ) {
				case 'valid':
					feedbackMessage = messages.valid || 'Valid VAT number';
					if ( company_name ) {
						feedbackMessage += ` - ${company_name}`;
					}
					
					// Add exemption message if applicable.
					if ( vat_exempt && exempt_message ) {
						feedbackMessage += '. ' + exempt_message;
					} else if ( exempt_message ) {
						// Show message even if not exempt (e.g., domestic transaction).
						feedbackMessage += '. ' + exempt_message;
					}
					break;

				case 'invalid':
					feedbackMessage = message || messages.invalid || 'Invalid VAT number';
					
					// Add info about standard VAT applying.
					if ( exempt_message ) {
						feedbackMessage += '. ' + exempt_message;
					}
					break;

				case 'too_short':
					feedbackMessage = message || messages.too_short || 'VAT number is too short';
					break;

				case 'invalid_format':
					feedbackMessage = message || messages.invalid_format || 'Invalid VAT format';
					break;

				case 'empty':
					feedbackMessage = '';
					break;

				default:
					feedbackMessage = message || messages.unknown || 'Could not validate VAT number';
					feedbackClass = 'unknown';
			}

			this.showFeedback( feedbackMessage, feedbackClass, vat_exempt );

			// Update checkout totals if VAT exemption status changed.
			if ( status === 'valid' || status === 'invalid' ) {
				this.updateCheckoutTotals();
			}

			// Trigger custom event for other scripts to hook into.
			const vatField = this.getVATField();
			if ( vatField ) {
				const event = new CustomEvent( 'conecom_vat_validated', {
					detail: data,
					bubbles: true,
				} );
				vatField.dispatchEvent( event );
			}
		},

		/**
		 * Trigger WooCommerce checkout update to recalculate taxes
		 */
		updateCheckoutTotals() {
			// Check if jQuery is available (for WooCommerce compatibility).
			if ( typeof jQuery !== 'undefined' && jQuery( 'body' ).trigger ) {
				jQuery( 'body' ).trigger( 'update_checkout' );
			} else {
				// Fallback: trigger native event.
				const event = new Event( 'update_checkout', { bubbles: true } );
				document.body.dispatchEvent( event );
			}
		},

		/**
		 * Show feedback message
		 * 
		 * @param {string} message
		 * @param {string} className
		 * @param {boolean} vatExempt
		 */
		showFeedback( message, className, vatExempt = false ) {
			const container = this.getFeedbackContainer();
			if ( ! container ) {
				return;
			}

			// Clear all previous content first.
			container.innerHTML = '';
			container.textContent = '';
			
			// Remove all status classes.
			container.className = 'conecom-vat-feedback';

			if ( message ) {
				// Use textContent for security (prevents XSS).
				container.textContent = message;
				container.classList.add( className );
				
				// Add special class for VAT exemption.
				if ( vatExempt && className === 'valid' ) {
					container.classList.add( 'vat-exempt' );
				}
				
				container.style.display = 'block';
			} else {
				container.style.display = 'none';
			}
		},

		/**
		 * Reset and revalidate
		 */
		resetAndValidate() {
			this.state.lastVAT = '';
			this.state.lastCountry = '';
			this.state.lastType = '';
			
			const vatField = this.getVATField();
			if ( vatField && vatField.value ) {
				this.performValidation( vatField.value.trim().toUpperCase() );
			}
		},

		/**
		 * Reattach listeners after AJAX update
		 */
		reattachListeners() {
			// Small delay to ensure DOM is updated.
			setTimeout( () => {
				this.setupListeners();
			}, 100 );
		},
	};

	// Initialize when script loads.
	VATValidator.init();

	// Expose to window for external access if needed.
	window.ConecomVATValidator = VATValidator;

})();

