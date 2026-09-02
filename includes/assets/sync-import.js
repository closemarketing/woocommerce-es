function syncManualItems( element, action, loop = 0 ) {
	element.classList.add('disabled');
	element.innerHTML = ConEcom_ajaxAction.label_syncing + ' <span class="spinner is-active"></span>';
	const productAI = document.querySelector('select[name="connwoo-sync-product-ai"]')?.value || '';
	const connectorId = document.querySelector('select[name="connwoo-connector-select"]')?.value || '';

	const isOdd = number => number % 2 !== 0;
	class_task = isOdd(loop) ? 'odd' : 'even';

	// AJAX request.
	fetch( ConEcom_ajaxAction.url, {
		method: 'POST',
		credentials: 'same-origin',
		headers: {
			'Content-Type': 'application/x-www-form-urlencoded',
			'Cache-Control': 'no-cache',
		},
		body: 'action=' + action + '&nonce=' + ConEcom_ajaxAction.nonce + '&loop=' + loop + '&product_ai=' + productAI + '&connector_id=' + connectorId,
	})
	.then( (resp) => resp.json() )
	.then( function(results) {
		if ( results.success ) {
			if ( ! results.data.finish ) {
				syncManualItems(element, action, results.data.loop );
			} else {
				element.classList.remove('disabled');
				element.innerHTML = ConEcom_ajaxAction.label_sync;
				results.data.message = results.data.message + ConEcom_ajaxAction.label_sync_complete;
			}
		} else {
			element.classList.remove('disabled');
			element.innerHTML = ConEcom_ajaxAction.label_sync;
		}
		// Message.
		if ( results.data.message != undefined ){
			progressElement = document.createElement('p');
			progressElement.className = class_task;
			document.querySelector('#logwrapper #loglist').appendChild(progressElement);
			progressElement.innerHTML = results.data.message;
		}
		const loglist = document.querySelector('#logwrapper #loglist');
		loglist.scrollTo({ top: loglist.scrollHeight, behavior: "smooth" });
	})
	.catch(err => console.log(err));
}

/**
 * Manual sync with mode (updated/all) and pagination. Used when get_all_product_skus exists.
 */
function syncManualItemsWithMode( element, action, loop, pagination ) {
	const importMode = document.getElementById('import-mode');
	const mode = importMode ? importMode.value : 'all';
	const dateFrom = document.getElementById('orders-date-from');
	const dateTo = document.getElementById('orders-date-to');
	const refreshButton = document.getElementById('refresh_stats');
	const spinner = element.parentElement ? element.parentElement.querySelector('.spinner') : null;
	const connectorId = document.querySelector('select[name="connwoo-connector-select"]')?.value || '';

	if ( loop === 0 ) {
		const loglist = document.querySelector('#logwrapper #loglist');
		if ( loglist ) {
			loglist.innerHTML = '';
		}
	}

	element.disabled = true;
	element.textContent = ConEcom_ajaxAction.label_syncing;
	if ( importMode ) { importMode.disabled = true; }
	if ( dateFrom ) { dateFrom.disabled = true; }
	if ( dateTo ) { dateTo.disabled = true; }
	if ( refreshButton ) { refreshButton.disabled = true; }
	if ( spinner ) { spinner.classList.add('is-active'); }

	const productAI = document.querySelector('select[name="connwoo-sync-product-ai"]')?.value || document.querySelector('#connect_ecommerce_ai_stats')?.value || '';
	const isOdd = number => number % 2 !== 0;
	const class_task = isOdd(loop) ? 'odd' : 'even';

	let body = 'action=' + action + '&nonce=' + ConEcom_ajaxAction.nonce + '&loop=' + loop + '&product_ai=' + productAI
		+ '&mode=' + encodeURIComponent(mode) + '&pagination=' + (pagination || 100) + '&connector_id=' + connectorId;
	if ( dateFrom && dateFrom.value ) {
		body += '&date_from=' + encodeURIComponent(dateFrom.value);
	}
	if ( dateTo && dateTo.value ) {
		body += '&date_to=' + encodeURIComponent(dateTo.value);
	}

	fetch( ConEcom_ajaxAction.url, {
		method: 'POST',
		credentials: 'same-origin',
		headers: {
			'Content-Type': 'application/x-www-form-urlencoded',
			'Cache-Control': 'no-cache',
		},
		body: body,
	})
	.then( (resp) => resp.json() )
	.then( function(results) {
		if ( results.success ) {
			if ( results.data && results.data.message ) {
				const progressElement = document.createElement('p');
				progressElement.className = class_task;
				const loglist = document.querySelector('#logwrapper #loglist');
				if ( loglist ) {
					progressElement.innerHTML = results.data.message;
					loglist.appendChild(progressElement);
				}
			}
			if ( ! results.data.finish ) {
				syncManualItemsWithMode(element, action, results.data.loop, pagination);
			} else {
				element.disabled = false;
				element.textContent = ConEcom_ajaxAction.label_sync;
				if ( importMode ) { importMode.disabled = false; }
				if ( dateFrom ) { dateFrom.disabled = false; }
				if ( dateTo ) { dateTo.disabled = false; }
				if ( refreshButton ) { refreshButton.disabled = false; }
				if ( spinner ) { spinner.classList.remove('is-active'); }
				if ( typeof loadImportStats === 'function' ) {
					loadImportStats();
				}
			}
		} else {
			element.disabled = false;
			element.textContent = ConEcom_ajaxAction.label_sync;
			if ( importMode ) { importMode.disabled = false; }
			if ( dateFrom ) { dateFrom.disabled = false; }
			if ( dateTo ) { dateTo.disabled = false; }
			if ( refreshButton ) { refreshButton.disabled = false; }
			if ( spinner ) { spinner.classList.remove('is-active'); }
			if ( results.data && results.data.message ) {
				const errEl = document.createElement('p');
				errEl.className = 'error';
				errEl.style.color = 'red';
				errEl.innerHTML = results.data.message;
				const loglist = document.querySelector('#logwrapper #loglist');
				if ( loglist ) { loglist.appendChild(errEl); }
			}
		}
		const loglist = document.querySelector('#logwrapper #loglist');
		if ( loglist ) {
			loglist.scrollTo({ top: loglist.scrollHeight, behavior: 'smooth' });
		}
	})
	.catch(function(err) {
		console.error('Import error:', err);
		element.disabled = false;
		element.textContent = ConEcom_ajaxAction.label_sync;
		if ( importMode ) { importMode.disabled = false; }
		if ( refreshButton ) { refreshButton.disabled = false; }
		if ( spinner ) { spinner.classList.remove('is-active'); }
	});
}

/**
 * Loads import statistics (available/synced/to-import/delete counts) for the selected connector.
 */
function loadImportStats() {
	if ( typeof ConEcom_ajaxAction === 'undefined' ) {
		return;
	}
	const connectorId = document.querySelector('select[name="connwoo-connector-select"]')?.value || '';
	const btn = document.getElementById('refresh_stats');
	const cards = document.querySelectorAll('.conecom-stat-card');
	if ( btn ) { btn.disabled = true; }
	if ( cards.length ) { cards.forEach(function(c){ c.classList.add('loading'); }); }

	fetch(ConEcom_ajaxAction.url, {
		method: 'POST',
		credentials: 'same-origin',
		headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
		body: 'action=connect_ecommerce_get_import_stats&security=' + encodeURIComponent(ConEcom_ajaxAction.stats_nonce) + '&connector_id=' + connectorId
	})
	.then(function(r){ return r.json(); })
	.then(function(response) {
		if ( response.success && response.data ) {
			const d = response.data;
			const set = function(id, val) { const el = document.getElementById(id); if (el) el.textContent = (typeof val === 'number') ? val.toLocaleString() : val; };
			set('stat-available-count', d.available_count);
			set('stat-wp-count', d.wp_count);
			set('stat-import-count', d.import_count);
			set('stat-new-count', d.new_count);
			set('stat-outdated-count', d.outdated_count);
			set('stat-delete-count', d.delete_count);

			const sublabel = document.getElementById('stat-available-sublabel');
			if ( sublabel ) {
				if ( d.filter_tag && d.api_total_count !== undefined && d.api_total_count !== d.available_count ) {
					const i18n = ConEcom_ajaxAction.i18n || {};
					sublabel.style.display = '';
					sublabel.innerHTML = (i18n.tag_label || 'Tag:') + ' <strong>' + d.filter_tag + '</strong><br><small>' + (i18n.total_label || 'Total:') + ' ' + Number(d.api_total_count).toLocaleString() + '</small>';
				} else {
					sublabel.style.display = 'none';
					sublabel.innerHTML = '';
				}
			}
		}
	})
	.catch(function() {})
	.finally(function() {
		if ( btn ) { btn.disabled = false; }
		if ( cards.length ) { cards.forEach(function(c){ c.classList.remove('loading'); }); }
	});
}

/**
 * Loads recent Action Scheduler sync runs for the automatic-sync log tab.
 */
function loadAsLogs() {
	if ( typeof ConEcom_ajaxAction === 'undefined' ) { return; }
	const container = document.getElementById('conecom-as-logs-container');
	if ( ! container ) { return; }
	const i18n = ConEcom_ajaxAction.i18n || {};
	container.innerHTML = '<p style="color:#666;font-style:italic;padding:20px;text-align:center;">' + (i18n.loading || 'Loading…') + '</p>';

	fetch(ConEcom_ajaxAction.url, {
		method: 'POST',
		credentials: 'same-origin',
		headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
		body: 'action=connect_ecommerce_get_as_logs&security=' + encodeURIComponent(ConEcom_ajaxAction.as_logs_nonce)
	})
	.then(function(r){ return r.json(); })
	.then(function(response) {
		if ( ! response.success ) {
			container.innerHTML = '<p style="color:#d63638;padding:20px;">' + ( response.data && response.data.message ? response.data.message : (i18n.error_loading_logs || 'Error loading logs.') ) + '</p>';
			return;
		}
		const actions = response.data;
		if ( ! actions || actions.length === 0 ) {
			container.innerHTML = '<p style="color:#666;font-style:italic;padding:20px;text-align:center;">' + (i18n.no_sync_runs || 'No sync runs recorded yet.') + '</p>';
			return;
		}

		const statusLabels = {
			'complete':    i18n.status_complete || 'Complete',
			'failed':      i18n.status_failed || 'Failed',
			'pending':     i18n.status_pending || 'Pending',
			'in-progress': i18n.status_in_progress || 'Running',
			'canceled':    i18n.status_canceled || 'Canceled'
		};
		const statusColors = {
			'complete':    '#00a32a',
			'failed':      '#d63638',
			'pending':     '#dba617',
			'in-progress': '#2271b1',
			'canceled':    '#787c82'
		};

		let html = '<table class="widefat striped" style="margin:0;">'
			+ '<thead><tr>'
			+ '<th style="width:160px;">' + (i18n.col_date || 'Date') + '</th>'
			+ '<th style="width:100px;">' + (i18n.col_status || 'Status') + '</th>'
			+ '<th style="width:130px;">' + (i18n.col_frequency || 'Frequency') + '</th>'
			+ '<th>' + (i18n.col_last_log || 'Last log') + '</th>'
			+ '</tr></thead><tbody>';

		actions.forEach(function(action) {
			const status      = action.status || 'pending';
			const color       = statusColors[status] || '#787c82';
			const statusLabel = statusLabels[status] || status;
			const lastLog     = action.logs && action.logs.length ? action.logs[action.logs.length - 1].message : '—';
			const rowId       = 'as-log-row-' + action.id;
			const detailId    = 'as-log-detail-' + action.id;
			const hasLogs     = action.logs && action.logs.length > 1;

			html += '<tr id="' + rowId + '" style="cursor:' + ( hasLogs ? 'pointer' : 'default' ) + ';" '
				+ ( hasLogs ? 'onclick="document.getElementById(\'' + detailId + '\').style.display = document.getElementById(\'' + detailId + '\').style.display === \'none\' ? \'\' : \'none\';"' : '' )
				+ '>'
				+ '<td style="white-space:nowrap;font-size:12px;">' + action.scheduled_date + '</td>'
				+ '<td><span style="color:' + color + ';font-weight:600;">' + statusLabel + '</span></td>'
				+ '<td style="font-size:12px;">' + action.hook_label + '</td>'
				+ '<td style="font-size:12px;color:#50575e;">' + lastLog + '</td>'
				+ '</tr>';

			if ( hasLogs ) {
				html += '<tr id="' + detailId + '" style="display:none;background:#f6f7f7;">'
					+ '<td colspan="4" style="padding:8px 16px;">'
					+ '<ol style="margin:0;padding-left:20px;">';
				action.logs.forEach(function(log) {
					html += '<li style="font-size:12px;margin-bottom:2px;"><span style="color:#50575e;">[' + log.date + ']</span> ' + log.message + '</li>';
				});
				html += '</ol></td></tr>';
			}
		});

		html += '</tbody></table>';
		container.innerHTML = html;
	})
	.catch(function() {
		container.innerHTML = '<p style="color:#d63638;padding:20px;">' + (i18n.error_loading_logs || 'Error loading logs.') + '</p>';
	});
}

function syncProductERP( element, action, product_erp_id = 0, product_sku = '', product_id = 0, connector_select_id = '' ) {
	element.classList.add('disabled');
	element.innerHTML = ConEcom_ajaxAction.label_syncing + ' <span class="spinner is-active"></span>';
	const productAI = document.querySelector('input[name="connwoo-sync-product-ai"]')?.checked || '';
	const connectorId = connector_select_id ? ( document.getElementById(connector_select_id)?.value || '' ) : '';

	loop = -1;
	// AJAX request.
	fetch( ConEcom_ajaxAction.url, {
		method: 'POST',
		credentials: 'same-origin',
		headers: {
			'Content-Type': 'application/x-www-form-urlencoded',
			'Cache-Control': 'no-cache',
		},
		body: 'action=' + action + '&nonce=' + ConEcom_ajaxAction.nonce + '&loop=' + loop + '&product_erp_id=' + product_erp_id + '&product_sku=' + product_sku + '&product_id=' + product_id + '&product_ai=' + productAI + '&connector_id=' + encodeURIComponent(connectorId),
	})
	.then( (resp) => resp.json() )
	.then( function(results) {
		element.classList.remove('disabled');
		element.innerHTML = ConEcom_ajaxAction.label_sync;

		console.log(results.data);
		// Message handling
		if (results.data.message !== undefined) {
			const aiInput = document.querySelector('#connect_ecommerce_ai');
			if (aiInput) {
				const aiLabel = aiInput.closest('label');
				const aiMessage = document.createElement('div');
				aiMessage.className = 'ai-message';
				aiMessage.innerHTML = results.data.message;
				
				const targetElement = aiLabel || aiInput;
				if (targetElement.nextSibling) {
					targetElement.parentNode.insertBefore(aiMessage, targetElement.nextSibling);
				} else {
					targetElement.parentNode.appendChild(aiMessage);
				}
			}
		}

		// Reload the page after 8 seconds if the sync was successful
		if (results.success) {
			setTimeout(() => {
				window.location.reload();
			}, 8000);
		}
	})
	.catch(err => console.log(err));
}
