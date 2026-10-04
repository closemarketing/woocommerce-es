/**
 * Interactions for the Connect Ecommerce settings redesign.
 *
 * @package WordPress
 */

( function () {
	'use strict';

	const config = window.ConecomSettingsUI || {};
	const binaryPairs = [
		[ 'yes', 'no' ],
		[ 'on', 'off' ],
		[ 'active', 'inactive' ],
		[ 'hide', 'no_change' ],
	];

	/**
	 * Return whether a two-option select represents an enabled/disabled value.
	 *
	 * @param {HTMLSelectElement} select Select element.
	 * @return {Array|null} Enabled and disabled values.
	 */
	function getBinaryPair( select ) {
		const values = Array.from( select.options ).map( ( option ) => option.value );

		if ( 2 !== values.length ) {
			return null;
		}

		return binaryPairs.find( ( pair ) => pair.every( ( value ) => values.includes( value ) ) ) || null;
	}

	/**
	 * Replace a binary select visually with an accessible switch.
	 *
	 * The original select remains the submitted form control.
	 *
	 * @param {HTMLSelectElement} select Select element.
	 */
	function enhanceBinarySelect( select ) {
		const pair = getBinaryPair( select );

		if ( ! pair || select.disabled || select.classList.contains( 'conecom-toggle-select' ) ) {
			return;
		}

		const wrapper = document.createElement( 'span' );
		const button = document.createElement( 'button' );
		const knob = document.createElement( 'span' );
		const state = document.createElement( 'span' );
		const isStatus = /\[status\]$/.test( select.name );
		const rowLabel = select.closest( 'tr' )?.querySelector( 'th' )?.textContent.trim();
		const accessibleLabel = isStatus ? ( config.status || 'Status' ) : rowLabel || select.id || ( config.setting || 'Setting' );

		wrapper.className = 'conecom-toggle-control';
		button.type = 'button';
		button.className = 'conecom-toggle';
		button.setAttribute( 'role', 'switch' );
		knob.className = 'conecom-toggle-knob';
		state.className = 'conecom-toggle-state';
		button.appendChild( knob );
		wrapper.appendChild( button );
		wrapper.appendChild( state );
		select.classList.add( 'conecom-toggle-select' );
		select.setAttribute( 'aria-hidden', 'true' );
		select.tabIndex = -1;
		select.insertAdjacentElement( 'afterend', wrapper );

		const sync = function () {
			const enabled = pair[ 0 ] === select.value;
			const selectedOption = select.options[ select.selectedIndex ];

			button.classList.toggle( 'is-active', enabled );
			button.setAttribute( 'aria-checked', enabled ? 'true' : 'false' );
			state.textContent = isStatus && selectedOption ? selectedOption.textContent : enabled ? ( config.enabled || 'Enabled' ) : ( config.disabled || 'Disabled' );
			button.setAttribute( 'aria-label', accessibleLabel + ': ' + state.textContent );
		};

		button.addEventListener( 'click', function () {
			select.value = pair[ 0 ] === select.value ? pair[ 1 ] : pair[ 0 ];
			sync();
			select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} );

		select.conecomSyncToggle = sync;
		sync();
	}

	/**
	 * Create a stable representation of one form control.
	 *
	 * @param {Element} field Form control.
	 * @return {string} Current value signature.
	 */
	function fieldSignature( field ) {
		if ( 'checkbox' === field.type || 'radio' === field.type ) {
			return field.checked ? 'checked:' + field.value : 'unchecked';
		}

		return String( field.value );
	}

	/**
	 * Add dirty-state and discard controls to a settings form.
	 *
	 * @param {HTMLFormElement} form Settings form.
	 */
	function enhanceSettingsForm( form ) {
		const saveBar = Array.from( form.children ).find( ( child ) => child.matches( 'p.submit' ) );

		if ( ! saveBar ) {
			return;
		}

		const fields = Array.from( form.elements ).filter( ( field ) => field.name && ! [ 'submit', 'button' ].includes( field.type ) );
		const initial = new Map( fields.map( ( field ) => [ field, fieldSignature( field ) ] ) );
		const state = document.createElement( 'span' );
		const discard = document.createElement( 'button' );

		state.className = 'connwoo-save-state';
		state.textContent = config.no_changes || 'No changes';
		discard.type = 'button';
		discard.className = 'button connwoo-discard';
		discard.textContent = config.discard || 'Discard';
		discard.disabled = true;
		saveBar.classList.add( 'connwoo-save-bar' );
		saveBar.prepend( state );
		const submit = saveBar.querySelector( '[type="submit"]' );
		if ( submit ) {
			saveBar.insertBefore( discard, submit );
		} else {
			saveBar.appendChild( discard );
		}

		const update = function () {
			const changed = fields.filter( ( field ) => initial.get( field ) !== fieldSignature( field ) ).length;

			discard.disabled = 0 === changed;
			if ( 0 === changed ) {
				state.textContent = config.no_changes || 'No changes';
			} else if ( 1 === changed ) {
				state.textContent = config.unsaved_change || '1 unsaved change';
			} else {
				state.textContent = ( config.unsaved_changes || '%d unsaved changes' ).replace( '%d', changed );
			}
		};

		form.addEventListener( 'input', update );
		form.addEventListener( 'change', update );
		discard.addEventListener( 'click', function () {
			form.reset();
			form.querySelectorAll( '.conecom-toggle-select' ).forEach( ( select ) => {
				if ( select.conecomSyncToggle ) {
					select.conecomSyncToggle();
				}
			} );
			update();
		} );
	}

	/**
	 * Apply an order date preset.
	 *
	 * @param {HTMLButtonElement} button Preset button.
	 */
	function applyDatePreset( button ) {
		const from = document.getElementById( 'orders-date-from' );
		const to = document.getElementById( 'orders-date-to' );

		if ( ! from || ! to ) {
			return;
		}

		const end = new Date();
		const start = new Date( end );
		if ( button.dataset.yearStart ) {
			start.setMonth( 0, 1 );
		} else {
			start.setDate( start.getDate() - Number( button.dataset.days || 0 ) );
		}

		const format = ( date ) => date.getFullYear() + '-' + String( date.getMonth() + 1 ).padStart( 2, '0' ) + '-' + String( date.getDate() ).padStart( 2, '0' );
		from.value = format( start );
		to.value = format( end );
		document.querySelectorAll( '.connwoo-date-preset' ).forEach( ( item ) => item.classList.toggle( 'is-active', item === button ) );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.connwoo-settings-form select' ).forEach( enhanceBinarySelect );
		document.querySelectorAll( '.connwoo-settings-form' ).forEach( enhanceSettingsForm );
		document.querySelectorAll( '.connwoo-date-preset' ).forEach( ( button ) => {
			button.addEventListener( 'click', function () {
				applyDatePreset( button );
			} );
		} );
	} );
}() );
