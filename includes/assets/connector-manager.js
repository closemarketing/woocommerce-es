/**
 * Connector Manager — delete connector via AJAX.
 *
 * @package WordPress
 * @author  David Perez <david@closemarketing.es>
 */

( function () {
	'use strict';

	/**
	 * Show a WP-style admin notice above the connector table.
	 *
	 * @param {string} html    Raw HTML returned by the server (may contain <p> tags).
	 * @param {string} type    'success' | 'error' | 'warning' | 'info'
	 */
	function showNotice( html, type ) {
		var wrapper = document.querySelector( '.connector-manager' );
		if ( ! wrapper ) {
			return;
		}

		// Remove any previous inline notice.
		var previous = wrapper.querySelector( '.conecom-inline-notice' );
		if ( previous ) {
			previous.parentNode.removeChild( previous );
		}

		var notice = document.createElement( 'div' );
		notice.className = 'notice notice-' + type + ' is-dismissible conecom-inline-notice';
		notice.innerHTML = html;

		// Dismissible button.
		var btn = document.createElement( 'button' );
		btn.type      = 'button';
		btn.className = 'notice-dismiss';
		btn.innerHTML = '<span class="screen-reader-text">Dismiss this notice.</span>';
		btn.addEventListener( 'click', function () {
			notice.parentNode.removeChild( notice );
		} );
		notice.appendChild( btn );

		wrapper.insertBefore( notice, wrapper.firstChild );
	}

	/**
	 * Refresh the active/total summary after a connector is removed.
	 */
	function updateActiveCount() {
		var summary = document.querySelector( '.connector-active-count' );
		var rows = document.querySelectorAll( '.conecom-connectors-table tbody tr' );

		if ( ! summary ) {
			return;
		}

		var active = Array.from( rows ).filter( function ( row ) {
			var status = row.querySelector( 'select[name$="[status]"]' );
			return status && 'active' === status.value;
		} ).length;
		var template = ConecomConnectorManager.active_count || '%1$d of %2$d active';
		summary.textContent = template.replace( '%1$d', active ).replace( '%2$d', rows.length );
	}

	/**
	 * Handle click on a "Remove connector" button.
	 *
	 * @param {MouseEvent} e
	 */
	function onRemoveClick( e ) {
		e.preventDefault();

		var btn         = e.currentTarget;
		var connectorId = btn.dataset.connectorId;

		if ( ! connectorId ) {
			return;
		}

		if ( ! window.confirm( ConecomConnectorManager.confirm_text ) ) {
			return;
		}

		btn.disabled = true;

		var data = new FormData();
		data.append( 'action', 'conecom_remove_connector' );
		data.append( 'nonce', ConecomConnectorManager.nonce );
		data.append( 'connector_id', connectorId );

		fetch( ConecomConnectorManager.ajax_url, {
			method      : 'POST',
			credentials : 'same-origin',
			body        : data,
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( json ) {
				if ( json.success ) {
					// Remove the table row from the DOM.
					var row = btn.closest( 'tr' );
					if ( row ) {
						row.parentNode.removeChild( row );
					}
					updateActiveCount();
					showNotice( json.data.message, 'success' );
				} else {
					btn.disabled = false;
					showNotice( json.data.message, 'error' );
				}
			} )
			.catch( function () {
				btn.disabled = false;
				showNotice( '<p>' + ConecomConnectorManager.error_text + '</p>', 'error' );
			} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var buttons = document.querySelectorAll( '.conecom-remove-connector' );
		var statusFields = document.querySelectorAll( '.conecom-connectors-table select[name$="[status]"]' );
		buttons.forEach( function ( btn ) {
			btn.addEventListener( 'click', onRemoveClick );
		} );
		statusFields.forEach( function ( field ) {
			field.addEventListener( 'change', updateActiveCount );
		} );
	} );
}() );
