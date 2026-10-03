<?php
/**
 * Webhooks admin panel
 *
 * @package    WordPress
 * @author     Closetechnology
 * @copyright  2026 Closemarketing
 * @version    1.0
 */

namespace CLOSE\ConnectEcommerce\Admin;

defined( 'ABSPATH' ) || exit;

use CLOSE\ConnectEcommerce\Helpers\HELPER;
use CLOSE\ConnectEcommerce\Helpers\WEBHOOK;

/**
 * Renders the Webhooks tab under Synchronization > Products.
 *
 * @since 3.5.1
 */
class Webhooks {
	/**
	 * Admin post action to regenerate the token.
	 */
	const ACTION_REGENERATE = 'conecom_webhook_regenerate_token';

	/**
	 * Admin post action to save the signing secret.
	 */
	const ACTION_SECRET = 'conecom_webhook_save_secret';

	/**
	 * Construct of class.
	 */
	public function __construct() {
		add_action( 'admin_post_' . self::ACTION_REGENERATE, array( $this, 'regenerate_token' ) );
		add_action( 'admin_post_' . self::ACTION_SECRET, array( $this, 'save_secret' ) );
	}

	/**
	 * Regenerates the webhook token of a connector.
	 *
	 * @return void
	 */
	public function regenerate_token() {
		$connector_id = isset( $_POST['connector_id'] ) ? sanitize_key( wp_unslash( $_POST['connector_id'] ) ) : '';
		check_admin_referer( self::ACTION_REGENERATE . '_' . $connector_id );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'woocommerce-es' ) );
		}

		WEBHOOK::regenerate_token( $connector_id );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'connect_ecommerce',
					'tab'     => 'connector_' . $connector_id,
					'subtab'  => 'sync_products',
					'webhook' => 'regenerated',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Saves the signing secret given by the ERP.
	 *
	 * @return void
	 */
	public function save_secret() {
		$connector_id = isset( $_POST['connector_id'] ) ? sanitize_key( wp_unslash( $_POST['connector_id'] ) ) : '';
		check_admin_referer( self::ACTION_SECRET . '_' . $connector_id );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'woocommerce-es' ) );
		}

		$secret = isset( $_POST['webhook_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['webhook_secret'] ) ) : '';
		WEBHOOK::save_signing_secret( $connector_id, $secret );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'connect_ecommerce',
					'tab'     => 'connector_' . $connector_id,
					'subtab'  => 'sync_products',
					'webhook' => 'secret_saved',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Gets the instructions for a connector.
	 *
	 * @param string $connector_id Connector ID.
	 * @param object $connapi_erp  Connector API object.
	 * @param string $webhook_url  Webhook URL.
	 * @return string HTML.
	 */
	public static function get_instructions( $connector_id, $connapi_erp, $webhook_url ) {
		$instructions = '';
		if ( HELPER::connector_supports( $connapi_erp, 'get_webhook_instructions' ) ) {
			$instructions = (string) $connapi_erp->get_webhook_instructions( $webhook_url );
		}

		if ( '' === $instructions ) {
			$instructions  = '<p>' . esc_html__( 'Create a webhook in your ERP for the product created/updated events and paste the URL above as destination. Every call updates only the affected product.', 'woocommerce-es' ) . '</p>';
			$instructions .= '<p>' . esc_html__( 'Supported formats:', 'woocommerce-es' ) . '</p>';
			$instructions .= '<ul style="list-style:disc;margin-left:20px;">';
			$instructions .= '<li><code>GET/POST ...&amp;id=N</code></li>';
			$instructions .= '<li><code>POST {"id": "N"}</code> (JSON)</li>';
			$instructions .= '</ul>';
		}

		/**
		 * Filters the webhook instructions shown for a connector.
		 *
		 * @param string $instructions HTML instructions.
		 * @param string $connector_id Connector ID.
		 * @param string $webhook_url  Webhook URL.
		 * @param object $connapi_erp  Connector API object.
		 */
		return (string) apply_filters( 'conecom_webhook_instructions', $instructions, $connector_id, $webhook_url, $connapi_erp );
	}

	/**
	 * Renders the panel content.
	 *
	 * @param string $connector_id Connector ID.
	 * @param object $connapi_erp  Connector API object.
	 * @return void
	 */
	public static function render_panel( $connector_id, $connapi_erp ) {
		if ( '' === (string) $connector_id ) {
			return;
		}
		$webhook_url = WEBHOOK::get_webhook_url( $connector_id, $connapi_erp );
		$logs        = WEBHOOK::get_logs( $connector_id );
		$native      = HELPER::connector_supports( $connapi_erp, 'parse_webhook_product' );
		$verifies    = HELPER::connector_supports( $connapi_erp, 'verify_webhook' );
		$secret      = WEBHOOK::get_signing_secret( $connector_id );
		$signed      = WEBHOOK::uses_signature( $connector_id, $connapi_erp );
		$date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		?>
		<div class="conecom-webhooks">
			<?php $notice = isset( $_GET['webhook'] ) ? sanitize_key( wp_unslash( $_GET['webhook'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<?php if ( 'regenerated' === $notice ) : ?>
				<div class="notice notice-success inline"><p><?php esc_html_e( 'Webhook token regenerated. Update the URL in your ERP.', 'woocommerce-es' ); ?></p></div>
			<?php elseif ( 'secret_saved' === $notice ) : ?>
				<div class="notice notice-success inline"><p><?php esc_html_e( 'Signing secret saved.', 'woocommerce-es' ); ?></p></div>
			<?php endif; ?>
			<p>
				<label for="conecom-webhook-url-<?php echo esc_attr( $connector_id ); ?>"><strong><?php esc_html_e( 'Webhook URL', 'woocommerce-es' ); ?></strong></label><br/>
				<input type="text" readonly class="large-text code conecom-webhook-url" id="conecom-webhook-url-<?php echo esc_attr( $connector_id ); ?>" value="<?php echo esc_attr( $webhook_url ); ?>" onclick="this.select();" />
			</p>
			<p>
				<button type="button" class="button conecom-webhook-copy" data-target="conecom-webhook-url-<?php echo esc_attr( $connector_id ); ?>"><?php esc_html_e( 'Copy URL', 'woocommerce-es' ); ?></button>
				<?php if ( $native ) : ?>
					<span style="color: green; margin-left: 8px;">● <?php esc_html_e( 'The connector translates the webhook payload natively.', 'woocommerce-es' ); ?></span>
				<?php else : ?>
					<span style="color: #646970; margin-left: 8px;">○ <?php esc_html_e( 'Generic mode: the product is requested to the API using the received ID.', 'woocommerce-es' ); ?></span>
				<?php endif; ?>
			</p>
			<?php if ( $signed ) : ?>
				<p class="description"><?php esc_html_e( 'Requests are authenticated with the ERP signature, so the URL does not need a token. Requests without a valid signature are rejected.', 'woocommerce-es' ); ?></p>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'Keep this URL secret: it includes the token that authorizes the requests. The token can also be sent in the X-Conecom-Token header.', 'woocommerce-es' ); ?></p>
			<?php endif; ?>

			<div class="conecom-webhook-instructions">
				<h4><?php esc_html_e( 'How to configure it', 'woocommerce-es' ); ?></h4>
				<?php echo wp_kses_post( self::get_instructions( $connector_id, $connapi_erp, $webhook_url ) ); ?>
			</div>

			<?php if ( ! $signed ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'The current URL will stop working. Continue?', 'woocommerce-es' ) ); ?>');">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_REGENERATE ); ?>" />
					<input type="hidden" name="connector_id" value="<?php echo esc_attr( $connector_id ); ?>" />
					<?php wp_nonce_field( self::ACTION_REGENERATE . '_' . $connector_id ); ?>
					<?php submit_button( __( 'Regenerate token', 'woocommerce-es' ), 'secondary small', 'submit_webhook_token', false ); ?>
				</form>
			<?php endif; ?>

			<?php if ( $verifies ) : ?>
				<h4><?php esc_html_e( 'Signing secret', 'woocommerce-es' ); ?></h4>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_SECRET ); ?>" />
					<input type="hidden" name="connector_id" value="<?php echo esc_attr( $connector_id ); ?>" />
					<?php wp_nonce_field( self::ACTION_SECRET . '_' . $connector_id ); ?>
					<input type="password" class="regular-text code" name="webhook_secret" autocomplete="off" value="<?php echo esc_attr( $secret ); ?>" placeholder="whsec_..." />
					<?php submit_button( __( 'Save secret', 'woocommerce-es' ), 'secondary small', 'submit_webhook_secret', false ); ?>
					<?php if ( '' !== $secret ) : ?>
						<span style="color: green; margin-left: 8px;">● <?php esc_html_e( 'Signatures are verified; the URL token is not needed.', 'woocommerce-es' ); ?></span>
					<?php else : ?>
						<span style="color: #b32d2e; margin-left: 8px;">○ <?php esc_html_e( 'Not set: the URL token protects the endpoint.', 'woocommerce-es' ); ?></span>
					<?php endif; ?>
					<p class="description"><?php esc_html_e( 'Paste the signing secret your ERP shows when creating the webhook. It is used to check that every request really comes from the ERP and has not been altered.', 'woocommerce-es' ); ?></p>
				</form>
			<?php endif; ?>

			<h4><?php esc_html_e( 'Latest webhooks', 'woocommerce-es' ); ?></h4>
			<?php if ( empty( $logs ) ) : ?>
				<p style="color: #666; font-style: italic;"><?php esc_html_e( 'No webhooks received yet.', 'woocommerce-es' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date', 'woocommerce-es' ); ?></th>
							<th><?php esc_html_e( 'Event', 'woocommerce-es' ); ?></th>
							<th><?php esc_html_e( 'Product ID', 'woocommerce-es' ); ?></th>
							<th><?php esc_html_e( 'Status', 'woocommerce-es' ); ?></th>
							<th><?php esc_html_e( 'Data source', 'woocommerce-es' ); ?></th>
							<th><?php esc_html_e( 'Message', 'woocommerce-es' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $logs as $log ) : ?>
							<tr>
								<td><?php echo esc_html( wp_date( $date_format, (int) ( $log['time'] ?? 0 ) ) ); ?></td>
								<td><?php echo esc_html( $log['action'] ?? '' ); ?></td>
								<td>
									<?php echo esc_html( $log['id'] ?? '' ); ?>
									<?php if ( ! empty( $log['post_id'] ) ) : ?>
										→ <a href="<?php echo esc_url( get_edit_post_link( (int) $log['post_id'] ) ); ?>">#<?php echo (int) $log['post_id']; ?></a>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $log['status'] ?? '' ); ?></td>
								<td><?php echo esc_html( $log['source'] ?? '' ); ?></td>
								<td><?php echo esc_html( $log['message'] ?? '' ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<script type="text/javascript">
		document.addEventListener('DOMContentLoaded', function() {
			document.querySelectorAll('.conecom-webhook-copy').forEach(function(btn) {
				btn.addEventListener('click', function() {
					var input = document.getElementById(this.getAttribute('data-target'));
					if ( ! input ) { return; }
					input.select();
					if ( navigator.clipboard ) {
						navigator.clipboard.writeText(input.value);
					} else {
						document.execCommand('copy');
					}
				});
			});
		});
		</script>
		<?php
	}
}
