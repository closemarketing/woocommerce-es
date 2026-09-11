/**
 * ERP Tax Types Admin Script
 *
 * Handles the ERP tax type column in WooCommerce tax rates table.
 *
 * @package WooCommerce_ES
 */

( function() {
	'use strict';

	// Wait for localized data to be available.
	if ( typeof conecomErpTaxTypesData === 'undefined' ) {
		console.error( 'ERP Tax Types: Data not loaded' );
		return;
	}

	const erpTaxOptionsHTML = conecomErpTaxTypesData.optionsHtml;
	const taxTypesMap       = conecomErpTaxTypesData.taxTypesMap || {};
	const headerText        = conecomErpTaxTypesData.headerText;

	/**
	 * Initialize tax type column in the tax rates table.
	 */
	function initializeTaxTypeColumn() {
		const taxRatesTable = document.querySelector( '.wc_tax_rates' );
		if ( ! taxRatesTable ) {
			return;
		}

		// Add header column.
		const existingHeader = taxRatesTable.querySelector( 'thead tr th.erp-tax-header' );
		if ( ! existingHeader ) {
			const headerRow = taxRatesTable.querySelector( 'thead tr' );
			const lastTh    = headerRow.querySelector( 'th:last-child' );
			if ( headerRow && lastTh ) {
				const newTh       = document.createElement( 'th' );
				newTh.className   = 'erp-tax-header';
				newTh.style.width = '15%';
				newTh.textContent = headerText;
				lastTh.parentNode.insertBefore( newTh, lastTh.nextSibling );
			}
		}

		// Update footer colspan.
		const footerTh = taxRatesTable.querySelector( 'tfoot th' );
		if ( footerTh ) {
			footerTh.setAttribute( 'colspan', '10' );
		}

		// Update empty row template colspan.
		const emptyTemplate = document.getElementById( 'tmpl-wc-tax-table-row-empty' );
		if ( emptyTemplate ) {
			let templateHTML = emptyTemplate.innerHTML;
			if ( templateHTML.indexOf( 'colspan="10"' ) === -1 ) {
				templateHTML              = templateHTML.replace( 'colspan="9"', 'colspan="10"' );
				emptyTemplate.innerHTML = templateHTML;
			}
		}

		// Function to add column to a row.
		function addColumnToRow( row ) {
			if ( row.nodeType === 1 && row.tagName === 'TR' && ! row.querySelector( 'td.erp_tax_type' ) ) {
				const lastTd = row.querySelector( 'td:last-child' );
				if ( lastTd ) {
					const newTd     = document.createElement( 'td' );
					newTd.className = 'erp_tax_type';
					const select    = document.createElement( 'select' );
					select.className = 'erp-tax-type-select';
					select.setAttribute( 'name', 'erp_tax_type' );
					select.setAttribute( 'data-attribute', 'erp_tax_type' );
					select.innerHTML = erpTaxOptionsHTML;
					attachSelectListeners( select );

					// Get tax rate ID from the row's data-id attribute.
					const taxRateId = row.getAttribute( 'data-id' );
					if ( taxRateId ) {
						select.setAttribute( 'data-tax-rate-id', taxRateId );

					// Set the value if it exists in our map.
					if ( taxRateId in taxTypesMap ) {
						select.value = taxTypesMap[ taxRateId ];
					}
				}

					newTd.appendChild( select );
					lastTd.parentNode.insertBefore( newTd, lastTd.nextSibling );
					return true;
				}
			}
			return false;
		}

		// Watch for when WooCommerce adds rows and add our column.
		const observer = new MutationObserver( function( mutations ) {
			mutations.forEach( function( mutation ) {
				if ( mutation.type === 'childList' && mutation.addedNodes.length > 0 ) {
					mutation.addedNodes.forEach( function( node ) {
						addColumnToRow( node );
					} );
				}

				if ( mutation.type === 'attributes' && mutation.attributeName === 'value' ) {
					const target = mutation.target;
					if ( target.classList && target.classList.contains( 'tax_rate_id' ) ) {
						const row = target.closest( 'tr' );
						if ( row ) {
							const select = row.querySelector( '.erp-tax-type-select' );
							if ( select ) {
								const newTaxRateId = target.value || target.getAttribute( 'value' );
								const oldTaxRateId = select.getAttribute( 'data-tax-rate-id' );

								if ( newTaxRateId ) {
									select.setAttribute( 'data-tax-rate-id', newTaxRateId );
									if ( oldTaxRateId && oldTaxRateId !== newTaxRateId && taxTypesMap[ oldTaxRateId ] && ! taxTypesMap[ newTaxRateId ] ) {
										taxTypesMap[ newTaxRateId ] = taxTypesMap[ oldTaxRateId ];
										delete taxTypesMap[ oldTaxRateId ];
									}
									if ( newTaxRateId in taxTypesMap ) {
										select.value = taxTypesMap[ newTaxRateId ];
									}
								}
							}
						}
					}
				}
			} );
		} );

		// Observe the tbody for new rows and attribute changes.
		const tbody = taxRatesTable.querySelector( 'tbody' );
		if ( tbody ) {
			observer.observe( tbody, {
				childList: true,
				subtree: true,
				attributes: true,
				attributeFilter: [ 'value' ]
			} );
		}

		// Add column to existing rows.
		function refreshRowSelects() {
			const rows = taxRatesTable.querySelectorAll( 'tbody tr' );

			rows.forEach( function( row ) {
				if ( row.querySelectorAll( 'td' ).length === 0 ) {
					return;
				}

				const added = addColumnToRow( row );

				const select = row.querySelector( '.erp-tax-type-select' );
				if ( select ) {
					attachSelectListeners( select );
					const taxRateInput = row.querySelector( '.tax_rate_id' );
					const taxRateId    = taxRateInput ? taxRateInput.value || taxRateInput.getAttribute( 'value' ) : select.getAttribute( 'data-tax-rate-id' );

					if ( taxRateId ) {
						const oldTaxRateId = select.getAttribute( 'data-tax-rate-id' );
						select.setAttribute( 'data-tax-rate-id', taxRateId );

						if ( taxRateId in taxTypesMap ) {
							select.value = taxTypesMap[ taxRateId ];
						} else if ( added ) {
							select.value = '';
						}

						if ( oldTaxRateId && oldTaxRateId !== taxRateId ) {
							delete taxTypesMap[ oldTaxRateId ];
						}
					}
				}
			} );
		}

		function attachSelectListeners( select ) {
			if ( ! select || select.dataset.erpTaxListener === 'true' ) {
				return;
			}

			select.addEventListener( 'change', function( event ) {
				const currentSelect = event.target;
				const taxRateId     = currentSelect.getAttribute( 'data-tax-rate-id' );

				if ( taxRateId ) {
					taxTypesMap[ taxRateId ] = currentSelect.value;
				}
			} );

			select.dataset.erpTaxListener = 'true';
		}

		refreshRowSelects();
	}

	/**
	 * Intercept WooCommerce AJAX calls to add our custom field.
	 */
	function interceptWooCommerceSave() {
		// Override jQuery.ajax to intercept WooCommerce saves.
		if ( typeof jQuery !== 'undefined' ) {
			const originalAjax = jQuery.ajax;

			jQuery.ajax = function( settings ) {
				// Check if this is the WooCommerce tax rates save.
				if ( settings && settings.data && typeof settings.data === 'object' && settings.data.action === 'woocommerce_tax_rates_save_changes' ) {
					const originalSuccess  = settings.success;
					const originalComplete = settings.complete;

					// Add our ERP tax types to the changes object.
					if ( settings.data.changes ) {
						const selects = document.querySelectorAll( '.erp-tax-type-select' );

						selects.forEach( function( select ) {
							const taxRateId = select.getAttribute( 'data-tax-rate-id' );
							if ( taxRateId && taxRateId !== '0' && taxRateId in settings.data.changes ) {
								// Add our field to the existing changes for this rate.
								settings.data.changes[ taxRateId ].erp_tax_type = select.value;
							}
						} );
					}

					settings.success = function( response ) {
						if ( settings.data.changes ) {
							Object.keys( settings.data.changes ).forEach( function( rateId ) {
								if ( ! settings.data.changes.hasOwnProperty( rateId ) ) {
									return;
								}
								const rateData = settings.data.changes[ rateId ];
								if ( rateData && Object.prototype.hasOwnProperty.call( rateData, 'erp_tax_type' ) ) {
									taxTypesMap[ rateId ] = rateData.erp_tax_type;
									const matchingSelects  = document.querySelectorAll( '.erp-tax-type-select[data-tax-rate-id="' + rateId + '"]' );
									matchingSelects.forEach( function( select ) {
										select.value = rateData.erp_tax_type;
									} );
								}
							} );
						}

						if ( typeof originalSuccess === 'function' ) {
							originalSuccess.apply( this, arguments );
						}
					};

					settings.complete = function() {
						initializeTaxTypeColumn();

						if ( typeof originalComplete === 'function' ) {
							originalComplete.apply( this, arguments );
						}
					};
				}

				// Call the original ajax method.
				return originalAjax.call( this, settings );
			};
		}
	}

	// Initialize when DOM is ready.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function() {
			setTimeout( function() {
				initializeTaxTypeColumn();
				interceptWooCommerceSave();
			}, 1000 );
		} );
	} else {
		setTimeout( function() {
			initializeTaxTypeColumn();
			interceptWooCommerceSave();
		}, 1000 );
	}
} )();
