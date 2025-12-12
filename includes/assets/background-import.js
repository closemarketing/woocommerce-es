/**
 * Background Import Handler
 * 
 * Handles background product imports with pause/stop/resume functionality
 * and real-time log updates.
 */

(function() {
	'use strict';

	/**
	 * Background Import Manager
	 */
	const BackgroundImport = {
		processId: null,
		pollInterval: null,
		logOffset: 0,
		isPolling: false,
		isInitialLoad: true,

		/**
		 * Initialize the background import handler
		 */
		init: function() {
			this.bindEvents();
			this.checkExistingProcess();
		},

		/**
		 * Bind UI events
		 */
		bindEvents: function() {
			const startBtn = document.getElementById('conecom-start-background-import');
			const pauseBtn = document.getElementById('conecom-pause-import');
			const resumeBtn = document.getElementById('conecom-resume-import');
			const stopBtn = document.getElementById('conecom-stop-import');

			if (startBtn) {
				startBtn.addEventListener('click', (e) => {
					e.preventDefault();
					this.startImport();
				});
			}

			if (pauseBtn) {
				pauseBtn.addEventListener('click', (e) => {
					e.preventDefault();
					this.pauseImport();
				});
			}

			if (resumeBtn) {
				resumeBtn.addEventListener('click', (e) => {
					e.preventDefault();
					this.resumeImport();
				});
			}

			if (stopBtn) {
				stopBtn.addEventListener('click', (e) => {
					e.preventDefault();
					this.stopImport();
				});
			}
		},

		/**
		 * Check if there's an existing active process
		 */
		checkExistingProcess: function() {
			this.isInitialLoad = true;
			this.logOffset = 0; // Start from beginning to load all logs.
			
			this.getStatus(null, (data) => {
				if (data.status && ['running', 'paused', 'completed', 'stopped'].includes(data.status)) {
					this.processId = data.process_id;
					
					// Load all existing logs on initial load.
					if (data.logs && data.logs.length > 0) {
						this.clearLogs();
						this.updateLogs(data.logs);
					}
					
					this.logOffset = data.logs_offset || 0;
					this.updateUI(data);
					
					// Only start polling if running.
					if (data.status === 'running') {
						this.isInitialLoad = false;
						this.startPolling();
					} else if (data.status === 'completed') {
						// Show completion message.
						this.addLog('Import completed. You can start a new import.', 'info');
					} else if (data.status === 'stopped') {
						// Show stopped message.
						this.addLog('Import was stopped. You can start a new import or resume if available.', 'info');
					}
				}
			});
		},

		/**
		 * Start a new import
		 */
		startImport: function() {
			const productAI = document.querySelector('select[name="connwoo-sync-product-ai"]')?.value || 'none';
			
			this.showLoader(true);
			this.logOffset = 0;
			this.isInitialLoad = true;
			this.clearLogs();
			
			fetch(ConEcom_ajaxAction.url, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
					'Cache-Control': 'no-cache',
				},
				body: 'action=conecom_start_background_import&nonce=' + ConEcom_ajaxAction.nonce + '&product_ai=' + productAI,
			})
			.then(resp => resp.json())
			.then(data => {
				if (data.success) {
					this.processId = data.data.process_id;
					this.addLog(data.data.message, 'info');
					this.updateButtons('running');
					this.isInitialLoad = false;
					this.startPolling();
				} else {
					this.addLog('Error: ' + (data.data?.error || 'Unknown error'), 'error');
					this.showLoader(false);
				}
			})
			.catch(err => {
				console.error(err);
				this.addLog('Error starting import: ' + err.message, 'error');
				this.showLoader(false);
			});
		},

		/**
		 * Pause the import
		 */
		pauseImport: function() {
			if (!this.processId) return;

			this.showLoader(true);

			fetch(ConEcom_ajaxAction.url, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
					'Cache-Control': 'no-cache',
				},
				body: 'action=conecom_pause_import&nonce=' + ConEcom_ajaxAction.nonce + '&process_id=' + this.processId,
			})
			.then(resp => resp.json())
			.then(data => {
				this.showLoader(false);
				if (data.success) {
					this.stopPolling();
					this.updateButtons('paused');
					this.addLog(data.data.message, 'info');
				} else {
					this.addLog('Error: ' + (data.data?.error || 'Unknown error'), 'error');
				}
			})
			.catch(err => {
				console.error(err);
				this.addLog('Error pausing import: ' + err.message, 'error');
				this.showLoader(false);
			});
		},

		/**
		 * Resume the import
		 */
		resumeImport: function() {
			if (!this.processId) return;

			this.showLoader(true);

			fetch(ConEcom_ajaxAction.url, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
					'Cache-Control': 'no-cache',
				},
				body: 'action=conecom_resume_import&nonce=' + ConEcom_ajaxAction.nonce + '&process_id=' + this.processId,
			})
			.then(resp => resp.json())
			.then(data => {
				this.showLoader(false);
				if (data.success) {
					this.updateButtons('running');
					this.startPolling();
					this.addLog(data.data.message, 'info');
				} else {
					this.addLog('Error: ' + (data.data?.error || 'Unknown error'), 'error');
				}
			})
			.catch(err => {
				console.error(err);
				this.addLog('Error resuming import: ' + err.message, 'error');
				this.showLoader(false);
			});
		},

		/**
		 * Stop the import
		 */
		stopImport: function() {
			if (!this.processId) return;

			if (!confirm(ConEcom_ajaxAction.confirm_stop || 'Are you sure you want to stop the import?')) {
				return;
			}

			this.showLoader(true);

			fetch(ConEcom_ajaxAction.url, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
					'Cache-Control': 'no-cache',
				},
				body: 'action=conecom_stop_import&nonce=' + ConEcom_ajaxAction.nonce + '&process_id=' + this.processId,
			})
			.then(resp => resp.json())
			.then(data => {
				this.showLoader(false);
				if (data.success) {
					this.stopPolling();
					this.updateButtons('stopped');
					this.addLog(data.data.message, 'info');
					this.processId = null;
					this.logOffset = 0;
				} else {
					this.addLog('Error: ' + (data.data?.error || 'Unknown error'), 'error');
				}
			})
			.catch(err => {
				console.error(err);
				this.addLog('Error stopping import: ' + err.message, 'error');
				this.showLoader(false);
			});
		},

		/**
		 * Get current import status
		 */
		getStatus: function(processId, callback) {
			const pid = processId || this.processId || '';
			// On initial load, get all logs from the beginning.
			const offset = this.isInitialLoad ? 0 : this.logOffset;

			fetch(ConEcom_ajaxAction.url, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
					'Cache-Control': 'no-cache',
				},
				body: 'action=conecom_get_import_status&nonce=' + ConEcom_ajaxAction.nonce + '&process_id=' + pid + '&log_offset=' + offset,
			})
			.then(resp => resp.json())
			.then(data => {
				if (data.success && callback) {
					callback(data.data);
				}
			})
			.catch(err => console.error(err));
		},

		/**
		 * Start polling for status updates
		 */
		startPolling: function() {
			if (this.isPolling) return;

			this.isPolling = true;
			this.pollInterval = setInterval(() => {
				this.getStatus(null, (data) => {
					this.updateUI(data);

					// Stop polling if completed or stopped.
					if (['completed', 'stopped'].includes(data.status)) {
						this.stopPolling();
						this.showLoader(false);
					}
				});
			}, 2000); // Poll every 2 seconds.
		},

		/**
		 * Stop polling
		 */
		stopPolling: function() {
			if (this.pollInterval) {
				clearInterval(this.pollInterval);
				this.pollInterval = null;
			}
			this.isPolling = false;
		},

		/**
		 * Update UI with status data
		 */
		updateUI: function(data) {
			if (!data || data.status === 'none') {
				this.updateButtons('none');
				return;
			}

			this.updateButtons(data.status);
			this.updateProgress(data);
			this.updateLogs(data.logs);
			
			if (data.logs_offset) {
				this.logOffset = data.logs_offset;
			}
		},

		/**
		 * Update button states
		 */
		updateButtons: function(status) {
			const startBtn = document.getElementById('conecom-start-background-import');
			const pauseBtn = document.getElementById('conecom-pause-import');
			const resumeBtn = document.getElementById('conecom-resume-import');
			const stopBtn = document.getElementById('conecom-stop-import');

			// Reset all buttons.
			[startBtn, pauseBtn, resumeBtn, stopBtn].forEach(btn => {
				if (btn) {
					btn.style.display = 'none';
					btn.disabled = false;
				}
			});

			switch (status) {
				case 'running':
					if (pauseBtn) pauseBtn.style.display = 'inline-block';
					if (stopBtn) stopBtn.style.display = 'inline-block';
					if (startBtn) startBtn.disabled = true;
					break;
				case 'paused':
					if (resumeBtn) resumeBtn.style.display = 'inline-block';
					if (stopBtn) stopBtn.style.display = 'inline-block';
					break;
				case 'completed':
				case 'stopped':
				case 'none':
					if (startBtn) {
						startBtn.style.display = 'inline-block';
						startBtn.disabled = false;
					}
					break;
			}
		},

		/**
		 * Update progress information
		 */
		updateProgress: function(data) {
			const progressDiv = document.getElementById('conecom-import-progress');
			if (!progressDiv) return;

			const synced = data.synced_count || 0;
			const errors = data.error_count || 0;
			const total = data.total || 0;
			const current = data.current_loop || 0;

			let html = '<div class="conecom-progress-info">';
			html += '<span><strong>' + (ConEcom_ajaxAction.label_progress || 'Progress') + ':</strong> ' + current + (total ? ' / ' + total : '') + '</span>';
			html += '<span><strong>' + (ConEcom_ajaxAction.label_synced || 'Synced') + ':</strong> ' + synced + '</span>';
			
			if (errors > 0) {
				html += '<span class="errors"><strong>' + (ConEcom_ajaxAction.label_errors || 'Errors') + ':</strong> ' + errors + '</span>';
			}
			
			html += '</div>';

			progressDiv.innerHTML = html;
		},

		/**
		 * Update logs
		 */
		updateLogs: function(logs) {
			if (!logs || logs.length === 0) return;

			const logList = document.querySelector('#logwrapper #loglist');
			if (!logList) return;

			logs.forEach(log => {
				const logElement = document.createElement('p');
				logElement.className = 'log-' + (log.type || 'info');
				logElement.innerHTML = log.message;
				logList.appendChild(logElement);
			});

			// Auto-scroll to bottom.
			logList.scrollTo({ top: logList.scrollHeight, behavior: 'smooth' });
		},

		/**
		 * Add a single log entry
		 */
		addLog: function(message, type) {
			const logList = document.querySelector('#logwrapper #loglist');
			if (!logList) return;

			const logElement = document.createElement('p');
			logElement.className = 'log-' + (type || 'info');
			logElement.innerHTML = message;
			logList.appendChild(logElement);

			// Auto-scroll to bottom.
			logList.scrollTo({ top: logList.scrollHeight, behavior: 'smooth' });
		},

		/**
		 * Clear all logs
		 */
		clearLogs: function() {
			const logList = document.querySelector('#logwrapper #loglist');
			if (logList) {
				logList.innerHTML = '';
			}
		},

		/**
		 * Show/hide loader
		 */
		showLoader: function(show) {
			const loader = document.querySelector('.conecom-import-loader');
			if (loader) {
				loader.style.display = show ? 'block' : 'none';
			}
		}
	};

	// Initialize when DOM is ready.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', () => BackgroundImport.init());
	} else {
		BackgroundImport.init();
	}

	// Expose to global scope for debugging.
	window.ConEcomBackgroundImport = BackgroundImport;
})();
