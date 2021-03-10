<?php

/**
 * Class for admin.
 *
 * Description.
 *
 * @since 1.0
 */
class WCES_Admin {

	/**
	 * Construct of Class
	 */
	public function __construct() {
		// Admin notice.
		add_action( 'admin_notices', array( $this, 'action_admin_notices' ) );
	}

	/**
	 * Define de admin notices
	 *
	 * @return void
	 */
	public function action_admin_notices() {
		?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php
				esc_html_e( '¡Subscribe to WooCommerce!<button type="submit" name="btnSub" id="btnSub" onclick=\'alert("Hello")\'>¡Subscribe!</button>', 'woocommerce-es' );
				?>
			</p>
		</div>
		<?php
	}
}

new WCES_Admin();
