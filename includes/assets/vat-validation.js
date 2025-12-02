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
			// Find all feedback containers.
			const containers = document.querySelectorAll( '.conecom-vat-feedback' );
			
			if ( containers.length > 1 ) {
				// Keep only the one with ID, or the first one.
				const mainContainer = document.querySelector( '#conecom-vat-feedback-container' ) || containers[0];
				
				containers.forEach( ( container ) => {
					if ( container !== mainContainer ) {
						container.remove();
					}
				} );
			}
		},

		/**
		 * Setup event listeners for VAT field
		 */
		setupListeners() {
			// Clean up any duplicate feedback containers first.
			this.cleanupDuplicateContainers();
			
			// Find VAT input field - try multiple selectors.
			const vatField = this.getVATField();
			const countryField = this.getCountryField();

			if ( ! vatField ) {
				console.warn( 'WooCommerce ES: VAT field not found' );
				return;
			}

			// Listen to input events (more modern than keyup).
			vatField.addEventListener( 'input', ( e ) => this.handleVATInput( e ) );
			vatField.addEventListener( 'blur', ( e ) => this.handleVATBlur( e ) );

			// Listen to country changes.
			if ( countryField ) {
				countryField.addEventListener( 'change', () => this.resetAndValidate() );
			}

			// Listen to WooCommerce checkout updates.
			document.body.addEventListener( 'updated_checkout', () => this.reattachListeners() );

			console.log( 'WooCommerce ES: VAT validator initialized' );
		},

		/**
		 * Get VAT input field
		 * 
		 * @return {HTMLElement|null}
		 */
		getVATField() {
			// Try multiple selectors for compatibility.
			const selectors = [
				'#billing_vat',
				'input[name="billing_vat"]',
				'input[name="connect_ecommerce/billing_vat"]',
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
		 * Get country field
		 * 
		 * @return {HTMLElement|null}
		 */
		getCountryField() {
			return document.querySelector( '#billing_country' ) || 
				   document.querySelector( 'select[name="billing_country"]' );
		},

		/**
		 * Get feedback container for messages
		 * 
		 * @return {HTMLElement}
		 */
		getFeedbackContainer() {
			const vatField = this.getVATField();
			if ( ! vatField ) {
				return null;
			}

			// Try to find existing container in multiple locations.
			const fieldWrapper = vatField.closest( '.form-row' ) || vatField.parentElement;
			
			// Search in various locations.
			let container = document.querySelector( '#conecom-vat-feedback-container' );
			
			if ( ! container ) {
				container = fieldWrapper.querySelector( '.conecom-vat-feedback' );
			}
			
			if ( ! container && fieldWrapper.nextElementSibling ) {
				if ( fieldWrapper.nextElementSibling.classList.contains( 'conecom-vat-feedback' ) ) {
					container = fieldWrapper.nextElementSibling;
				}
			}

			if ( ! container ) {
				// Create new container with unique ID.
				container = document.createElement( 'div' );
				container.id = 'conecom-vat-feedback-container';
				container.className = 'conecom-vat-feedback';
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
			const vatValue = e.target.value.trim().toUpperCase();

			// Clear previous timer.
			if ( this.state.validationTimer ) {
				clearTimeout( this.state.validationTimer );
			}

			// Cancel previous request if still pending.
			if ( this.state.currentRequest ) {
				this.state.currentRequest.abort();
			}

			// Empty field - clear feedback.
			if ( ! vatValue ) {
				this.showFeedback( '', 'empty' );
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
			const countryField = this.getCountryField();
			const countryCode = countryField ? countryField.value : '';

			// Check if we've already validated this combination.
			if ( this.state.lastVAT === vatNumber && this.state.lastCountry === countryCode ) {
				return;
			}

			this.state.isValidating = true;
			this.state.lastVAT = vatNumber;
			this.state.lastCountry = countryCode;

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
					}
					break;

				case 'invalid':
					feedbackMessage = message || messages.invalid || 'Invalid VAT number';
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

