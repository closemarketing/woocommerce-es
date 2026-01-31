function syncManualItems( element, action, loop = 0 ) {
	element.classList.add('disabled');
	element.innerHTML = ConEcom_ajaxAction.label_syncing + ' <span class="spinner is-active"></span>';
	const productAI = document.querySelector('select[name="connwoo-sync-product-ai"]')?.value || document.querySelector('#connect_ecommerce_ai_stats')?.value || '';

	const isOdd = number => number % 2 !== 0;
	var class_task = isOdd(loop) ? 'odd' : 'even';

	var body = 'action=' + action + '&nonce=' + ConEcom_ajaxAction.nonce + '&loop=' + loop + '&product_ai=' + productAI;

	fetch( ConEcom_ajaxAction.url, {
		method: 'POST',
		credentials: 'same-origin',
		headers: {
			'Content-Type': 'application/x-www-form-urlencoded',
			'Cache-Control': 'no-cache',
		},
		body: body,
	})
	.then( function(resp) { return resp.json(); } )
	.then( function(results) {
		if ( results.success ) {
			if ( ! results.data.finish ) {
				syncManualItems(element, action, results.data.loop );
			} else {
				element.classList.remove('disabled');
				element.innerHTML = ConEcom_ajaxAction.label_sync;
				if ( results.data.message ) {
					results.data.message = results.data.message + ConEcom_ajaxAction.label_sync_complete;
				}
			}
		} else {
			element.classList.remove('disabled');
			element.innerHTML = ConEcom_ajaxAction.label_sync;
		}
		if ( results.data && results.data.message !== undefined ) {
			var progressElement = document.createElement('p');
			progressElement.className = class_task;
			var loglist = document.querySelector('#logwrapper #loglist');
			if ( loglist ) {
				progressElement.innerHTML = results.data.message;
				loglist.appendChild(progressElement);
			}
		}
		var loglist = document.querySelector('#logwrapper #loglist');
		if ( loglist ) {
			loglist.scrollTo({ top: loglist.scrollHeight, behavior: 'smooth' });
		}
	})
	.catch(function(err) { console.log(err); });
}

/**
 * Manual sync with mode (updated/all) and pagination. Used when get_all_product_skus exists.
 */
function syncManualItemsWithMode( element, action, loop, pagination ) {
	var importMode = document.getElementById('import-mode');
	var mode = importMode ? importMode.value : 'all';
	var refreshButton = document.getElementById('refresh_stats');
	var spinner = element.parentElement ? element.parentElement.querySelector('.spinner') : null;

	if ( loop === 0 ) {
		var manualTabButton = document.querySelector('.conecom-tab-button[data-tab="manual"]');
		if ( manualTabButton ) {
			manualTabButton.click();
		}
		var loglist = document.querySelector('#logwrapper #loglist');
		if ( loglist ) {
			loglist.innerHTML = '';
		}
	}

	element.disabled = true;
	element.textContent = ConEcom_ajaxAction.label_syncing;
	if ( importMode ) { importMode.disabled = true; }
	if ( refreshButton ) { refreshButton.disabled = true; }
	if ( spinner ) { spinner.classList.add('is-active'); }

	var productAI = document.querySelector('select[name="connwoo-sync-product-ai"]')?.value || document.querySelector('#connect_ecommerce_ai_stats')?.value || '';
	var isOdd = function(n) { return n % 2 !== 0; };
	var class_task = isOdd(loop) ? 'odd' : 'even';

	var body = 'action=' + action + '&nonce=' + ConEcom_ajaxAction.nonce + '&loop=' + loop + '&product_ai=' + productAI + '&mode=' + encodeURIComponent(mode) + '&pagination=' + (pagination || 100);

	fetch( ConEcom_ajaxAction.url, {
		method: 'POST',
		credentials: 'same-origin',
		headers: {
			'Content-Type': 'application/x-www-form-urlencoded',
			'Cache-Control': 'no-cache',
		},
		body: body,
	})
	.then( function(resp) { return resp.json(); } )
	.then( function(results) {
		if ( results.success ) {
			if ( results.data && results.data.message ) {
				var progressElement = document.createElement('p');
				progressElement.className = class_task;
				var loglist = document.querySelector('#logwrapper #loglist');
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
			if ( refreshButton ) { refreshButton.disabled = false; }
			if ( spinner ) { spinner.classList.remove('is-active'); }
			if ( results.data && results.data.message ) {
				var errEl = document.createElement('p');
				errEl.className = 'error';
				errEl.style.color = 'red';
				errEl.innerHTML = results.data.message;
				var loglist = document.querySelector('#logwrapper #loglist');
				if ( loglist ) { loglist.appendChild(errEl); }
			}
		}
		var loglist = document.querySelector('#logwrapper #loglist');
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

function syncProductERP( element, action, product_erp_id = 0, product_sku = '', product_id = 0 ) {
	element.classList.add('disabled');
	element.innerHTML = ConEcom_ajaxAction.label_syncing + ' <span class="spinner is-active"></span>';
	const productAI = document.querySelector('input[name="connwoo-sync-product-ai"]')?.checked || '';

	loop = -1;
	// AJAX request.
	fetch( ConEcom_ajaxAction.url, {
		method: 'POST',
		credentials: 'same-origin',
		headers: {
			'Content-Type': 'application/x-www-form-urlencoded',
			'Cache-Control': 'no-cache',
		},
		body: 'action=' + action + '&nonce=' + ConEcom_ajaxAction.nonce + '&loop=' + loop + '&product_erp_id=' + product_erp_id + '&product_sku=' + product_sku + '&product_id=' + product_id + '&product_ai=' + productAI,
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