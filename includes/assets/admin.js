/**
 * Admin JavaScript for Connect Ecommerce Plugin
 */

document.addEventListener('DOMContentLoaded', function() {
	// Handle review notice dismissal
	document.addEventListener('click', function(e) {
		if (e.target && e.target.closest('#conecom-review-notice .notice-dismiss')) {
			var xhr = new XMLHttpRequest();
			xhr.open('POST', conecom_admin.ajaxurl, true);
			xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
			xhr.send('action=conecom_dismiss_review_notice&nonce=' + conecom_admin.nonce);
		}
	});
});
