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

		// Reload the page after 8 seconds if the sync was successful
		if (results.success) {
			setTimeout(() => {
				window.location.reload();
			}, 8000);
		}
	})
	.catch(err => console.log(err));
}

/**
 * Background importer UI (pause/stop/resume + near-real-time logs).
 */
(function () {
	if ( typeof ConEcom_ajaxAction === 'undefined' ) {
		return;
	}

	const importer = ConEcom_ajaxAction.importer || null;
	if ( ! importer || ! importer.actions ) {
		return;
	}

	const container = document.getElementById( 'conecom-importer' );
	if ( ! container ) {
		return;
	}

	const type          = container.dataset.type || 'products';
	const statusTextEl  = document.getElementById( 'conecom-importer-status-text' );
	const statusMetaEl  = document.getElementById( 'conecom-importer-status-meta' );
	const logListEl     = document.querySelector( '#logwrapper #loglist' );
	const startBtn      = document.getElementById( 'conecom-importer-start' );
	const pauseBtn      = document.getElementById( 'conecom-importer-pause' );
	const stopBtn       = document.getElementById( 'conecom-importer-stop' );
	const resumeBtn     = document.getElementById( 'conecom-importer-resume' );
	const pollInterval  = Number( importer.poll_interval || 2500 );

	let sessionId = 0;
	let sinceId   = 0;
	let pollTimer = null;

	const labelForStatus = (status) => {
		const labels = importer.labels || {};
		return labels[ 'status_' + status ] || status;
	};

	const setButtonsForStatus = (status) => {
		const isRunning   = ( 'running' === status );
		const isPaused    = ( 'paused' === status );
		const isStopped   = ( 'stopped' === status );
		const isCompleted = ( 'completed' === status );
		const isFailed    = ( 'failed' === status );
		const isIdle      = ( ! status || 'idle' === status );

		if ( startBtn ) {
			startBtn.disabled = isRunning;
		}
		if ( pauseBtn ) {
			pauseBtn.disabled = ! isRunning;
		}
		if ( stopBtn ) {
			stopBtn.disabled = isIdle || isCompleted || isFailed;
		}
		if ( resumeBtn ) {
			resumeBtn.disabled = isRunning || isIdle || isCompleted || isFailed || ( ! isPaused && ! isStopped );
		}
	};

	const appendLogs = (logs) => {
		if ( ! logListEl || ! Array.isArray( logs ) ) {
			return;
		}
		logs.forEach( (row, idx) => {
			const p = document.createElement( 'p' );
			p.className = ( ( ( sinceId + idx ) % 2 ) !== 0 ) ? 'odd' : 'even';
			if ( row.level ) {
				p.className += ' level-' + row.level;
			}
			p.innerHTML = row.message || '';
			logListEl.appendChild( p );
			if ( row.id ) {
				sinceId = Number( row.id );
			}
		} );

		logListEl.scrollTo( { top: logListEl.scrollHeight, behavior: 'smooth' } );
	};

	const setStatus = (session) => {
		if ( ! statusTextEl ) {
			return;
		}

		if ( ! session ) {
			statusTextEl.textContent = labelForStatus( 'idle' );
			if ( statusMetaEl ) {
				statusMetaEl.textContent = '';
			}
			setButtonsForStatus( 'idle' );
			return;
		}

		sessionId = Number( session.id || 0 );

		statusTextEl.textContent = labelForStatus( session.status || 'idle' );
		if ( statusMetaEl ) {
			const cursor = Number( session.cursor || 0 );
			statusMetaEl.textContent = ' (cursor: ' + cursor + ')';
		}
		setButtonsForStatus( session.status || 'idle' );
	};

	const callAjax = async (actionKey, data = {}) => {
		const action = importer.actions[ actionKey ];
		if ( ! action ) {
			return null;
		}
		const body = new URLSearchParams();
		body.set( 'action', action );
		body.set( 'nonce', importer.nonce );

		Object.keys( data ).forEach( (key) => {
			if ( data[ key ] !== undefined && data[ key ] !== null ) {
				body.set( key, String( data[ key ] ) );
			}
		} );

		const resp = await fetch( ConEcom_ajaxAction.url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
				'Cache-Control': 'no-cache',
			},
			body: body.toString(),
		} );

		return await resp.json();
	};

	const poll = async () => {
		try {
			const results = await callAjax( 'status', {
				type: type,
				session_id: sessionId,
				since_id: sinceId,
			} );

			if ( results && results.success && results.data ) {
				setStatus( results.data.session );
				appendLogs( results.data.logs || [] );
			}
		} catch (e) {
			// Silence polling errors.
		}
	};

	const startPolling = () => {
		if ( pollTimer ) {
			return;
		}
		pollTimer = window.setInterval( poll, pollInterval );
	};

	const resetLogView = () => {
		sinceId = 0;
		if ( logListEl ) {
			logListEl.innerHTML = '';
		}
	};

	if ( startBtn ) {
		startBtn.addEventListener( 'click', async () => {
			const productAI = document.querySelector( 'select[name="connwoo-sync-product-ai"]' )?.value || 'none';
			const results   = await callAjax( 'start', { type: type, ai: productAI } );
			if ( results && results.success && results.data && results.data.session_id ) {
				resetLogView();
				sessionId = Number( results.data.session_id );
				await poll();
			}
		} );
	}

	if ( pauseBtn ) {
		pauseBtn.addEventListener( 'click', async () => {
			if ( ! sessionId ) {
				return;
			}
			await callAjax( 'pause', { session_id: sessionId } );
			await poll();
		} );
	}

	if ( stopBtn ) {
		stopBtn.addEventListener( 'click', async () => {
			if ( ! sessionId ) {
				return;
			}
			await callAjax( 'stop', { session_id: sessionId } );
			await poll();
		} );
	}

	if ( resumeBtn ) {
		resumeBtn.addEventListener( 'click', async () => {
			if ( ! sessionId ) {
				return;
			}
			await callAjax( 'resume', { session_id: sessionId } );
			await poll();
		} );
	}

	// Initial status + polling (so returning users see live updates).
	poll();
	startPolling();
})();