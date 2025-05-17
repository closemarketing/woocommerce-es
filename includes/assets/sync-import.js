function syncManualItems( element, action, loop = 0 ) {
	element.classList.add('disabled');
	element.innerHTML = ConEcom_ajaxAction.label_syncing + ' <span class="spinner is-active"></span>';
	const productAI = document.querySelector('select[name="connwoo-sync-product-ai"]')?.value || '';

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
		body: 'action=' + action + '&nonce=' + ConEcom_ajaxAction.nonce + '&loop=' + loop + '&product_ai=' + productAI,
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

		// Reload the page after 10 seconds if the sync was successful
		if (results.success) {
			setTimeout(() => {
				window.location.reload();
			}, 10000);
		}
	})
	.catch(err => console.log(err));
}