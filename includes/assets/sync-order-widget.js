function syncOrderERP( order_id, element_id, type ) {
	button_sync = document.getElementById(element_id);
	button_sync.classList.add('disabled');
	button_sync.removeAttribute('onclick');
	button_sync.innerHTML = ConEcom_ajaxActionOrder.label_syncing + ' <span class="spinner is-active"></span>';

	// AJAX request.
	fetch( ConEcom_ajaxActionOrder.url, {
		method: 'POST',
		credentials: 'same-origin',
		headers: {
			'Content-Type': 'application/x-www-form-urlencoded',
			'Cache-Control': 'no-cache',
		},
		body: 'action=sync_erp_order&nonce=' + ConEcom_ajaxActionOrder.nonce + '&order_id=' + order_id + '&type=' + type,
	})
	.then((response) => response.json())
	.then( (response) => {
		if ( response.success ) {
			// Success: Show success state without alert
			button_sync.innerHTML = ConEcom_ajaxActionOrder.label_synced;
			button_sync.insertAdjacentHTML( 'afterend', '<p style="color: green;">' + response.data.message + '</p>' );
			
			// Optional: Auto-hide success message after 5 seconds
			setTimeout(function() {
				var successMsg = button_sync.nextElementSibling;
				if (successMsg && successMsg.tagName === 'P') {
					successMsg.remove();
				}
			}, 5000);
		} else {
			// Error: Show alert and error message
			alert( 'Error: ' + (response.data && response.data.message ? response.data.message : 'Unknown error occurred') );
			button_sync.classList.remove('disabled');
			button_sync.setAttribute('onclick', 'syncOrderERP(' + order_id + ',\'' + element_id + '\',\'' + type + '\')');
			button_sync.innerHTML = ConEcom_ajaxActionOrder.label_syncing.replace('ing', '');
			button_sync.insertAdjacentHTML( 'afterend', '<p style="color: red;">' + (response.data ? response.data.message : 'Unknown error') + '</p>' );
		}
	})
	.catch(err => {
		console.log(err);
		alert( 'Connection error: Unable to sync order. Please try again.' );
		button_sync.classList.remove('disabled');
		button_sync.setAttribute('onclick', 'syncOrderERP(' + order_id + ',\'' + element_id + '\',\'' + type + '\')');
		button_sync.innerHTML = ConEcom_ajaxActionOrder.label_syncing.replace('ing', '');
	});
}