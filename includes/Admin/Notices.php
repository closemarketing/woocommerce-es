<?php
/**
 * Admin Notices
 *
 * @package    Connect Ecommerce
 * @author     David Perez <david@close.technology>
 * @copyright  2025 Close Technology
 * @version    Version
 */

namespace CLOSE\ConnectEcommerce\Admin;

defined( 'ABSPATH' ) || exit;

class Notices {
	public function __construct() {
		add_action( 'admin_notices', array( $this, 'review_notice' ) );
		add_action( 'wp_ajax_conecom_dismiss_review_notice', array( $this, 'dismiss_review_notice' ) );
	}

	/**
	 * Display admin notice to remind users to leave a review on WordPress.org
	 *
	 * @return void
	 */
	function review_notice() {
		// Only show to administrators
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
	
		// Only show on plugin pages or dashboard
		$screen = get_current_screen();
		if ( ! $screen || ( strpos( $screen->id, 'woocommerce-es' ) === false && $screen->id !== 'dashboard' ) ) {
			return;
		}
	
		// Check if user has already dismissed the notice
		$dismissed = get_user_meta( get_current_user_id(), 'conecom_review_notice_dismissed', true );
		if ( $dismissed ) {
			return;
		}
	
		$review_url = 'https://wordpress.org/support/plugin/woocommerce-es/reviews/';
		
		$notice_title = sprintf(
			// translators: %s is the plugin name
			__( '⭐ Love %s?', 'woocommerce-es' ),
			'Connect Ecommerce'
		);
		
		$notice_message = sprintf(
			// translators: %1$s is the plugin name, %2$s is the review URL
			__( 'Thank you for using %1$s! The plugin that connects WooCommerce to ERPs and CRMs. If you find it helpful, please consider leaving a review on WordPress.org. It helps us a lot! <p></p><a href="%2$s" target="_blank" class="button button-primary">Leave a Review</a>', 'woocommerce-es' ),
			'Connect Ecommerce',
			$review_url
		);
	
		wp_admin_notice(
			'<h3>' . $notice_title . '</h3><p>' . $notice_message . '</p>',
			array(
				'type'        => 'info',
				'dismissible' => true,
				'id'          => 'conecom-review-notice',
				'additional_classes' => 'is-dismissible',
			)
		);
		
		// Enqueue admin script with WordPress variables
		$this->enqueue_admin_script();
	}
	
	/**
	 * Enqueue admin script with WordPress variables
	 *
	 * @return void
	 */
	function enqueue_admin_script() {
		wp_enqueue_script(
			'conecom-admin',
			CONECOM_PLUGIN_URL . 'includes/assets/admin.js',
			array(),
			CONECOM_VERSION,
			true
		);
		
		wp_localize_script(
			'conecom-admin',
			'conecom_admin',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'conecom_dismiss_review' ),
			)
		);
	}
	
	/**
	 * Handle dismissal of the review notice
	 *
	 * @return void
	 */
	function dismiss_review_notice() {
		if ( isset( $_POST['action'] ) && 'conecom_dismiss_review_notice' ===$_POST['action'] ) {
			// Verify nonce for security
			if ( ! wp_verify_nonce( $_POST['nonce'], 'conecom_dismiss_review' ) ) {
				wp_die( 'Security check failed' );
			}
			
			update_user_meta( get_current_user_id(), 'conecom_review_notice_dismissed', true );
			wp_die();
		}
	}	
}
