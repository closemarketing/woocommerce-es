<?php
/**
 * First-install setup wizard
 *
 * @package    WordPress
 * @author     David Perez <david@closemarketing.es>
 * @copyright  2024 Closemarketing
 * @version    1.0
 */

namespace CLOSE\ConnectEcommerce\Admin;

defined( 'ABSPATH' ) || exit;

use CLOSE\ConnectEcommerce\Helpers\HELPER;
use CLOSE\ConnectEcommerce\Helpers\AI;

/**
 * Renders the first-install setup wizard and handles its AJAX endpoints.
 */
class Setup_Wizard {

	/**
	 * All connector options (from conecom_get_options()).
	 *
	 * @var array
	 */
	private $all_options;

	/**
	 * Nonce action shared by all wizard AJAX calls.
	 *
	 * @var string
	 */
	private $nonce_action = 'conecom_wizard_nonce';

	/**
	 * Constructor.
	 *
	 * @param array $all_options Connector options array from conecom_get_options().
	 */
	public function __construct( $all_options ) {
		$this->all_options = $all_options;

		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'admin_init', array( $this, 'maybe_redirect' ) );
		add_action( 'admin_init', array( $this, 'maybe_reset' ) );

		add_action( 'wp_ajax_conecom_wizard_save_connection', array( $this, 'ajax_save_connection' ) );
		add_action( 'wp_ajax_conecom_wizard_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_conecom_wizard_save_vat',        array( $this, 'ajax_save_vat' ) );
		add_action( 'wp_ajax_conecom_wizard_save_ai',         array( $this, 'ajax_save_ai' ) );
		add_action( 'wp_ajax_conecom_wizard_complete',        array( $this, 'ajax_complete' ) );
	}

	/**
	 * Registers the hidden admin page under the Dashboard menu.
	 *
	 * @return void
	 */
	public function register_page() {
		add_dashboard_page(
			'',
			'',
			'manage_woocommerce',
			'conecom-setup-wizard',
			array( $this, 'render' )
		);
	}

	/**
	 * Redirects to the wizard on first activation (via transient set during activation hook).
	 *
	 * @return void
	 */
	public function maybe_redirect() {
		if ( ! get_transient( 'conecom_wizard_redirect' ) ) {
			return;
		}
		delete_transient( 'conecom_wizard_redirect' );

		if ( get_option( 'conecom_wizard_complete' ) || defined( 'DOING_AJAX' ) ) {
			return;
		}
		if ( isset( $_GET['page'] ) && 'conecom-setup-wizard' === $_GET['page'] ) {
			return;
		}

		wp_safe_redirect( admin_url( 'index.php?page=conecom-setup-wizard' ) );
		exit;
	}

	/**
	 * Handles the "reset wizard" link from the Settings page.
	 *
	 * @return void
	 */
	public function maybe_reset() {
		if ( empty( $_GET['conecom_reset_wizard'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ?? '' ), 'conecom_reset_wizard' ) ) {
			return;
		}
		delete_option( 'conecom_wizard_complete' );
		wp_safe_redirect( admin_url( 'index.php?page=conecom-setup-wizard' ) );
		exit;
	}

	/**
	 * Renders the full-page wizard HTML (outputs standalone HTML and exits).
	 *
	 * @return void
	 */
	public function render() {
		$connector_data   = array();
		foreach ( $this->all_options as $slug => $opts ) {
			$connector_data[ $slug ] = array(
				'name'          => $opts['name'] ?? $slug,
				'logo'          => $opts['settings_logo'] ?? '',
				'fields'        => $opts['settings_fields'] ?? array(),
				'admin_message' => $opts['settings_admin_message'] ?? '',
			);
		}

		$current_settings   = get_option( 'connect_ecommerce', array() );
		$current_connector  = $current_settings['connector'] ?? '';
		$current_vat        = get_option( 'connect_ecommerce_public', array() );
		$current_ai         = get_option( 'connect_ecommerce_ai', array() );
		$has_ai             = AI::has_wp_ai();
		$ai_models          = $has_ai ? AI::get_available_models() : array();
		$nonce              = wp_create_nonce( $this->nonce_action );
		$sync_nonce         = wp_create_nonce( 'conecom_manual_import_nonce' );
		$ajax_url           = admin_url( 'admin-ajax.php' );
		$settings_url       = admin_url( 'admin.php?page=connect_ecommerce' );
		$sync_url           = admin_url( 'admin.php?page=connect_ecommerce&tab=synchronization&subtab=sync_products' );
		$css_url            = esc_url( CONECOM_PLUGIN_URL . 'includes/assets/setup-wizard.css' );
		$js_url             = esc_url( CONECOM_PLUGIN_URL . 'includes/assets/setup-wizard.js' );
		$logo_url           = esc_url( CONECOM_PLUGIN_URL . 'includes/assets/logo.png' );

		$step_labels = array(
			1 => __( 'Welcome',    'woocommerce-es' ),
			2 => __( 'Connection', 'woocommerce-es' ),
			3 => __( 'VAT',        'woocommerce-es' ),
			4 => __( 'AI',         'woocommerce-es' ),
			5 => __( 'Sync',       'woocommerce-es' ),
			6 => __( 'Done',       'woocommerce-es' ),
		);
		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
		<meta charset="<?php bloginfo( 'charset' ); ?>">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<title><?php esc_html_e( 'Setup Wizard — Connect Ecommerce', 'woocommerce-es' ); ?></title>
		<link rel="stylesheet" href="<?php echo $css_url; ?>">
		</head>
		<body class="conecom-wizard-body">

		<div class="wiz-shell">

			<header class="wiz-top-bar">
				<img src="<?php echo $logo_url; ?>" alt="Connect Ecommerce" height="34">
				<a href="<?php echo esc_url( admin_url() ); ?>" class="wiz-skip-link" id="js-skip-wizard">
					<?php esc_html_e( 'Skip setup', 'woocommerce-es' ); ?>
				</a>
			</header>

			<nav class="wiz-progress" id="js-progress" aria-label="<?php esc_attr_e( 'Setup steps', 'woocommerce-es' ); ?>">
				<?php foreach ( $step_labels as $n => $label ) { ?>
				<div class="wiz-step-node<?php echo 1 === $n ? ' is-active' : ''; ?>" data-step="<?php echo $n; ?>">
					<div class="wiz-step-circle"><span><?php echo $n; ?></span></div>
					<span class="wiz-step-label"><?php echo esc_html( $label ); ?></span>
				</div>
				<?php if ( $n < count( $step_labels ) ) { ?>
				<div class="wiz-step-line"></div>
				<?php } ?>
				<?php } ?>
			</nav>

			<div class="wiz-card">

				<?php /* ── Step 1: Welcome ───────────────────────────────── */ ?>
				<section class="wiz-panel is-active" data-step="1">
					<div class="wiz-panel-icon">
						<svg width="64" height="64" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
							<circle cx="32" cy="32" r="32" fill="#EEF5FA"/>
							<path d="M32 16C23.163 16 16 23.163 16 32s7.163 16 16 16c8.836 0 16-7.163 16-16S40.836 16 32 16z" stroke="#007CBA" stroke-width="2"/>
							<path d="M32 24v10l6 3" stroke="#007CBA" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
					</div>
					<h1 class="wiz-title"><?php esc_html_e( 'Welcome to Connect Ecommerce', 'woocommerce-es' ); ?></h1>
					<p class="wiz-subtitle"><?php esc_html_e( 'This wizard will guide you through connecting WooCommerce to your ERP or CRM, setting up EU VAT compliance, and running your first product sync — in about five minutes.', 'woocommerce-es' ); ?></p>
					<ul class="wiz-checklist">
						<li><?php esc_html_e( 'ERP / CRM connection credentials', 'woocommerce-es' ); ?></li>
						<li><?php esc_html_e( 'EU VAT compliance options', 'woocommerce-es' ); ?></li>
						<li><?php esc_html_e( 'AI-assisted product descriptions', 'woocommerce-es' ); ?></li>
						<li><?php esc_html_e( 'Initial product synchronisation', 'woocommerce-es' ); ?></li>
					</ul>
					<div class="wiz-actions wiz-actions--end">
						<button class="button button-primary wiz-next-btn" data-next="2">
							<?php esc_html_e( 'Get started', 'woocommerce-es' ); ?>
						</button>
					</div>
				</section>

				<?php /* ── Step 2: Connector & Connection ──────────────────── */ ?>
				<section class="wiz-panel" data-step="2">
					<h2 class="wiz-title"><?php esc_html_e( 'Connect your ERP or CRM', 'woocommerce-es' ); ?></h2>
					<p class="wiz-subtitle"><?php esc_html_e( 'Select your connector, enter the API credentials, then test the connection.', 'woocommerce-es' ); ?></p>

					<div class="wiz-connector-grid" id="js-connector-grid">
						<?php foreach ( $connector_data as $slug => $info ) { ?>
						<label class="wiz-connector-card<?php echo $current_connector === $slug ? ' is-selected' : ''; ?>" data-slug="<?php echo esc_attr( $slug ); ?>">
							<input type="radio" name="wiz_connector" value="<?php echo esc_attr( $slug ); ?>"<?php checked( $current_connector, $slug ); ?>>
							<?php if ( ! empty( $info['logo'] ) ) { ?>
							<img src="<?php echo esc_url( $info['logo'] ); ?>" alt="<?php echo esc_attr( $info['name'] ); ?>" class="wiz-connector-logo">
							<?php } ?>
							<span class="wiz-connector-name"><?php echo esc_html( $info['name'] ); ?></span>
						</label>
						<?php } ?>
					</div>

					<?php foreach ( $connector_data as $slug => $info ) {
						$s = $current_settings[ $slug ] ?? array();
					?>
					<div class="wiz-conn-fields" data-connector-fields="<?php echo esc_attr( $slug ); ?>" hidden>
						<?php if ( ! empty( $info['admin_message'] ) ) { ?>
						<p class="wiz-admin-msg"><?php echo wp_kses_post( $info['admin_message'] ); ?></p>
						<?php } ?>

						<?php if ( in_array( 'url', $info['fields'], true ) ) { ?>
						<div class="wiz-field">
							<label><?php esc_html_e( 'URL', 'woocommerce-es' ); ?></label>
							<input type="url" name="url" class="regular-text" value="<?php echo esc_attr( $s['url'] ?? '' ); ?>" placeholder="https://your-erp.example.com">
						</div>
						<?php } ?>

						<?php if ( in_array( 'dbname', $info['fields'], true ) ) { ?>
						<div class="wiz-field">
							<label><?php esc_html_e( 'Database Name', 'woocommerce-es' ); ?></label>
							<input type="text" name="dbname" class="regular-text" value="<?php echo esc_attr( $s['dbname'] ?? '' ); ?>">
						</div>
						<?php } ?>

						<?php if ( in_array( 'username', $info['fields'], true ) ) { ?>
						<div class="wiz-field">
							<label><?php esc_html_e( 'Username', 'woocommerce-es' ); ?></label>
							<input type="text" name="username" class="regular-text" value="<?php echo esc_attr( $s['username'] ?? '' ); ?>">
						</div>
						<?php } ?>

						<?php if ( in_array( 'password', $info['fields'], true ) ) { ?>
						<div class="wiz-field">
							<label><?php esc_html_e( 'Password', 'woocommerce-es' ); ?></label>
							<input type="password" name="password" class="regular-text" value="<?php echo esc_attr( $s['password'] ?? '' ); ?>">
						</div>
						<?php } ?>

						<?php if ( in_array( 'domain', $info['fields'], true ) ) { ?>
						<div class="wiz-field">
							<label><?php esc_html_e( 'Domain', 'woocommerce-es' ); ?></label>
							<input type="text" name="domain" class="regular-text" value="<?php echo esc_attr( $s['domain'] ?? '' ); ?>">
						</div>
						<?php } ?>

						<?php if ( in_array( 'apipassword', $info['fields'], true ) ) { ?>
						<div class="wiz-field">
							<label><?php esc_html_e( 'API Key', 'woocommerce-es' ); ?></label>
							<input type="password" name="api" class="regular-text" value="<?php echo esc_attr( $s['api'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Paste your API key here', 'woocommerce-es' ); ?>">
						</div>
						<?php } ?>

						<?php if ( in_array( 'company', $info['fields'], true ) ) { ?>
						<div class="wiz-field">
							<label><?php esc_html_e( 'Company', 'woocommerce-es' ); ?></label>
							<input type="text" name="company" class="regular-text" value="<?php echo esc_attr( $s['company'] ?? '' ); ?>">
						</div>
						<?php } ?>

						<?php if ( in_array( 'manufacturer_code', $info['fields'], true ) ) { ?>
						<div class="wiz-field">
							<label><?php esc_html_e( 'Manufacturer Code', 'woocommerce-es' ); ?></label>
							<input type="text" name="manufacturer_code" class="regular-text" value="<?php echo esc_attr( $s['manufacturer_code'] ?? '' ); ?>">
						</div>
						<?php } ?>

						<?php if ( in_array( 'customer_code', $info['fields'], true ) ) { ?>
						<div class="wiz-field">
							<label><?php esc_html_e( 'Customer Code', 'woocommerce-es' ); ?></label>
							<input type="text" name="customer_code" class="regular-text" value="<?php echo esc_attr( $s['customer_code'] ?? '' ); ?>">
						</div>
						<?php } ?>

						<div class="wiz-test-row">
							<button type="button" class="button button-secondary" id="js-test-conn">
								<?php esc_html_e( 'Test connection', 'woocommerce-es' ); ?>
							</button>
							<span class="wiz-test-msg" id="js-test-msg" aria-live="polite"></span>
						</div>
					</div>
					<?php } ?>

					<div class="wiz-actions">
						<button class="button wiz-back-btn" data-back="1"><?php esc_html_e( 'Back', 'woocommerce-es' ); ?></button>
						<button class="button button-primary wiz-next-btn" data-next="3" id="js-conn-next" disabled>
							<?php esc_html_e( 'Save &amp; Continue', 'woocommerce-es' ); ?>
						</button>
					</div>
				</section>

				<?php /* ── Step 3: VAT Compliance ────────────────────────────── */ ?>
				<section class="wiz-panel" data-step="3">
					<h2 class="wiz-title"><?php esc_html_e( 'EU VAT Compliance', 'woocommerce-es' ); ?></h2>
					<p class="wiz-subtitle"><?php esc_html_e( 'Configure how VAT numbers are collected and validated at checkout.', 'woocommerce-es' ); ?></p>

					<div class="wiz-fields">
						<div class="wiz-field wiz-field--row">
							<label for="wiz_vat_show"><?php esc_html_e( 'Ask for VAT number at checkout', 'woocommerce-es' ); ?></label>
							<select name="vat_show" id="wiz_vat_show">
								<option value=""  <?php selected( $current_vat['vat_show'] ?? '', '' ); ?>><?php esc_html_e( 'No',  'woocommerce-es' ); ?></option>
								<option value="yes" <?php selected( $current_vat['vat_show'] ?? '', 'yes' ); ?>><?php esc_html_e( 'Yes', 'woocommerce-es' ); ?></option>
							</select>
						</div>

						<div class="wiz-field wiz-field--row" id="wiz-vat-extra" <?php echo empty( $current_vat['vat_show'] ) ? 'hidden' : ''; ?>>
							<label for="wiz_vat_mandatory"><?php esc_html_e( 'VAT number required', 'woocommerce-es' ); ?></label>
							<select name="vat_mandatory" id="wiz_vat_mandatory">
								<option value=""  <?php selected( $current_vat['vat_mandatory'] ?? '', '' ); ?>><?php esc_html_e( 'No',  'woocommerce-es' ); ?></option>
								<option value="yes" <?php selected( $current_vat['vat_mandatory'] ?? '', 'yes' ); ?>><?php esc_html_e( 'Yes', 'woocommerce-es' ); ?></option>
							</select>
						</div>

						<div class="wiz-field wiz-field--row" id="wiz-vies-field" <?php echo empty( $current_vat['vat_show'] ) ? 'hidden' : ''; ?>>
							<label for="wiz_vat_vies"><?php esc_html_e( 'Validate EU VAT via VIES', 'woocommerce-es' ); ?></label>
							<select name="vat_vies_enabled" id="wiz_vat_vies">
								<option value=""  <?php selected( $current_vat['vat_vies_enabled'] ?? '', '' ); ?>><?php esc_html_e( 'No',  'woocommerce-es' ); ?></option>
								<option value="yes" <?php selected( $current_vat['vat_vies_enabled'] ?? '', 'yes' ); ?>><?php esc_html_e( 'Yes', 'woocommerce-es' ); ?></option>
							</select>
						</div>

						<div class="wiz-field" id="wiz-vatsense-field" <?php echo empty( $current_vat['vat_show'] ) ? 'hidden' : ''; ?>>
							<label for="wiz_vatsense"><?php esc_html_e( 'VATSense API Key', 'woocommerce-es' ); ?> <span class="wiz-optional"><?php esc_html_e( 'optional', 'woocommerce-es' ); ?></span></label>
							<input type="text" name="vatsense_api_key" id="wiz_vatsense" class="regular-text" value="<?php echo esc_attr( $current_vat['vatsense_api_key'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'For enhanced VAT validation', 'woocommerce-es' ); ?>">
						</div>
					</div>

					<div class="wiz-actions">
						<button class="button wiz-back-btn" data-back="2"><?php esc_html_e( 'Back', 'woocommerce-es' ); ?></button>
						<button class="button button-primary wiz-next-btn" data-next="4" id="js-vat-next">
							<?php esc_html_e( 'Save &amp; Continue', 'woocommerce-es' ); ?>
						</button>
					</div>
				</section>

				<?php /* ── Step 4: AI Products ──────────────────────────────── */ ?>
				<section class="wiz-panel" data-step="4">
					<h2 class="wiz-title"><?php esc_html_e( 'AI Product Descriptions', 'woocommerce-es' ); ?></h2>

					<?php if ( ! $has_ai ) { ?>
					<p class="wiz-subtitle"><?php esc_html_e( 'AI-powered product descriptions require WordPress 7.0+ with a configured AI provider. You can enable this feature later under Settings → AI Products.', 'woocommerce-es' ); ?></p>
					<div class="wiz-actions">
						<button class="button wiz-back-btn" data-back="3"><?php esc_html_e( 'Back', 'woocommerce-es' ); ?></button>
						<button class="button button-primary wiz-next-btn" data-next="5"><?php esc_html_e( 'Continue', 'woocommerce-es' ); ?></button>
					</div>
					<?php } else { ?>
					<p class="wiz-subtitle"><?php esc_html_e( 'Generate SEO-ready product titles, descriptions, and meta tags directly from your ERP data.', 'woocommerce-es' ); ?></p>

					<div class="wiz-fields">
						<div class="wiz-field">
							<label for="wiz_ai_model"><?php esc_html_e( 'AI Model', 'woocommerce-es' ); ?></label>
							<select name="ai_model" id="wiz_ai_model" class="regular-text">
								<option value=""><?php esc_html_e( '— Select a model —', 'woocommerce-es' ); ?></option>
								<?php foreach ( $ai_models as $provider_label => $models ) { ?>
								<optgroup label="<?php echo esc_attr( $provider_label ); ?>">
									<?php foreach ( $models as $m ) { ?>
									<option value="<?php echo esc_attr( $m['value'] ); ?>" <?php selected( $current_ai['model'] ?? '', $m['value'] ); ?>>
										<?php echo esc_html( $m['label'] ); ?>
									</option>
									<?php } ?>
								</optgroup>
								<?php } ?>
							</select>
						</div>

						<div class="wiz-field">
							<label for="wiz_ai_prompt">
								<?php esc_html_e( 'Custom prompt', 'woocommerce-es' ); ?>
								<span class="wiz-optional"><?php esc_html_e( 'optional', 'woocommerce-es' ); ?></span>
							</label>
							<textarea name="ai_prompt" id="wiz_ai_prompt" rows="4" class="large-text"><?php echo esc_textarea( $current_ai['prompt'] ?? '' ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Leave empty to use the default prompt. Product data is appended automatically.', 'woocommerce-es' ); ?></p>
						</div>
					</div>

					<div class="wiz-actions">
						<button class="button wiz-back-btn" data-back="3"><?php esc_html_e( 'Back', 'woocommerce-es' ); ?></button>
						<button class="button wiz-next-btn" data-next="5"><?php esc_html_e( 'Skip', 'woocommerce-es' ); ?></button>
						<button class="button button-primary" id="js-ai-save" data-next="5">
							<?php esc_html_e( 'Save &amp; Continue', 'woocommerce-es' ); ?>
						</button>
					</div>
					<?php } ?>
				</section>

				<?php /* ── Step 5: Product Sync ────────────────────────────── */ ?>
				<section class="wiz-panel" data-step="5">
					<h2 class="wiz-title"><?php esc_html_e( 'Initial Product Sync', 'woocommerce-es' ); ?></h2>
					<p class="wiz-subtitle"><?php esc_html_e( 'Import your product catalogue from your ERP or CRM. You can re-run or schedule this at any time from the Synchronisation tab.', 'woocommerce-es' ); ?></p>

					<div id="js-sync-idle">
						<button class="button button-primary" id="js-start-sync">
							<?php esc_html_e( 'Start first sync', 'woocommerce-es' ); ?>
						</button>
					</div>

					<div id="js-sync-running" hidden>
						<div class="wiz-sync-progress">
							<div class="wiz-sync-bar" id="js-sync-bar"></div>
						</div>
						<div class="wiz-sync-log" id="js-sync-log" aria-live="polite"></div>
					</div>

					<div id="js-sync-done" hidden>
						<p class="wiz-success-msg" id="js-sync-summary"></p>
					</div>

					<div class="wiz-actions">
						<button class="button wiz-back-btn" data-back="4" id="js-sync-back"><?php esc_html_e( 'Back', 'woocommerce-es' ); ?></button>
						<button class="button wiz-next-btn" data-next="6" id="js-sync-skip"><?php esc_html_e( 'Skip for now', 'woocommerce-es' ); ?></button>
						<button class="button button-primary wiz-next-btn" data-next="6" id="js-sync-continue" hidden>
							<?php esc_html_e( 'Continue', 'woocommerce-es' ); ?>
						</button>
					</div>
				</section>

				<?php /* ── Step 6: Done ─────────────────────────────────────── */ ?>
				<section class="wiz-panel" data-step="6">
					<div class="wiz-panel-icon">
						<svg width="64" height="64" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
							<circle cx="32" cy="32" r="32" fill="#EDFAEF"/>
							<path d="M20 32L28 41L44 23" stroke="#00A32A" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
					</div>
					<h2 class="wiz-title"><?php esc_html_e( "You're all set!", 'woocommerce-es' ); ?></h2>
					<p class="wiz-subtitle"><?php esc_html_e( 'Connect Ecommerce is configured. Head to Settings to adjust any option, or to Synchronisation to manage your product imports.', 'woocommerce-es' ); ?></p>

					<div class="wiz-done-links">
						<a href="<?php echo esc_url( $settings_url . '&tab=settings&subtab=connection' ); ?>" class="button button-primary">
							<?php esc_html_e( 'Open Settings', 'woocommerce-es' ); ?>
						</a>
						<a href="<?php echo esc_url( $sync_url ); ?>" class="button">
							<?php esc_html_e( 'Synchronisation', 'woocommerce-es' ); ?>
						</a>
					</div>
				</section>

			</div><!-- .wiz-card -->
		</div><!-- .wiz-shell -->

		<script>
		var conecomWizard = {
			ajaxUrl:       <?php echo wp_json_encode( $ajax_url ); ?>,
			nonce:         <?php echo wp_json_encode( $nonce ); ?>,
			syncNonce:     <?php echo wp_json_encode( $sync_nonce ); ?>,
			connectorData: <?php echo wp_json_encode( $connector_data ); ?>,
			settingsUrl:   <?php echo wp_json_encode( $settings_url ); ?>,
			i18n: {
				testing:     <?php echo wp_json_encode( __( 'Testing…',              'woocommerce-es' ) ); ?>,
				connOk:      <?php echo wp_json_encode( __( 'Connection successful!', 'woocommerce-es' ) ); ?>,
				saving:      <?php echo wp_json_encode( __( 'Saving…',               'woocommerce-es' ) ); ?>,
				syncing:     <?php echo wp_json_encode( __( 'Syncing…',              'woocommerce-es' ) ); ?>,
				syncDone:    <?php echo wp_json_encode( __( 'Sync complete.',         'woocommerce-es' ) ); ?>
			}
		};
		</script>
		<script src="<?php echo $js_url; ?>"></script>

		</body>
		</html>
		<?php
		exit;
	}

	// ── AJAX helpers ──────────────────────────────────────────────────────────

	/**
	 * Verifies nonce and capability for all wizard AJAX calls.
	 *
	 * @return void
	 */
	private function verify_request() {
		check_ajax_referer( $this->nonce_action, 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'woocommerce-es' ) ), 403 );
		}
	}

	/**
	 * Persists the connector + credential fields from the POST payload.
	 *
	 * @return bool True if a recognised connector was found and saved.
	 */
	private function do_save_connection() {
		$slug = sanitize_key( $_POST['connector'] ?? '' );
		if ( empty( $slug ) || ! isset( $this->all_options[ $slug ] ) ) {
			return false;
		}

		$allowed = array( 'url', 'username', 'password', 'api', 'company', 'domain', 'dbname', 'manufacturer_code', 'customer_code' );
		$settings             = get_option( 'connect_ecommerce', array() );
		$settings['connector'] = $slug;

		$connector_settings = $settings[ $slug ] ?? array();
		foreach ( $allowed as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				$connector_settings[ $field ] = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
			}
		}
		$settings[ $slug ] = $connector_settings;
		update_option( 'connect_ecommerce', $settings );
		return true;
	}

	// ── AJAX handlers ─────────────────────────────────────────────────────────

	/**
	 * Saves connection settings (connector + credentials).
	 *
	 * @return void
	 */
	public function ajax_save_connection() {
		$this->verify_request();
		$this->do_save_connection();
		wp_send_json_success( array( 'message' => __( 'Connection settings saved.', 'woocommerce-es' ) ) );
	}

	/**
	 * Saves connection settings then tests the API connection.
	 *
	 * @return void
	 */
	public function ajax_test_connection() {
		$this->verify_request();
		if ( ! $this->do_save_connection() ) {
			wp_send_json_error( array( 'message' => __( 'Select a connector first.', 'woocommerce-es' ) ) );
		}

		$connector = HELPER::get_connector( $this->all_options );
		if ( empty( $connector['connapi_erp'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Connector plugin is not active. Install and activate it, then try again.', 'woocommerce-es' ) ) );
		}

		$result = $connector['connapi_erp']->check_can_sync();
		if ( true === $result ) {
			wp_send_json_success( array( 'message' => __( 'Connection successful!', 'woocommerce-es' ) ) );
		} else {
			$msg = is_array( $result ) ? implode( ' ', array_filter( $result ) ) : __( 'Connection failed. Check your credentials and try again.', 'woocommerce-es' );
			wp_send_json_error( array( 'message' => $msg ) );
		}
	}

	/**
	 * Saves VAT compliance settings.
	 *
	 * @return void
	 */
	public function ajax_save_vat() {
		$this->verify_request();

		$vat_fields = array( 'vat_show', 'vat_mandatory', 'company_field', 'vat_vies_enabled', 'vat_vies_mandatory', 'vatsense_api_key' );
		$settings   = get_option( 'connect_ecommerce_public', array() );
		foreach ( $vat_fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				$settings[ $field ] = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
			}
		}
		update_option( 'connect_ecommerce_public', $settings );
		wp_send_json_success( array( 'message' => __( 'VAT settings saved.', 'woocommerce-es' ) ) );
	}

	/**
	 * Saves AI product description settings.
	 *
	 * @return void
	 */
	public function ajax_save_ai() {
		$this->verify_request();

		$settings = get_option( 'connect_ecommerce_ai', array() );
		if ( isset( $_POST['ai_model'] ) ) {
			$settings['model'] = sanitize_text_field( wp_unslash( $_POST['ai_model'] ) );
		}
		if ( isset( $_POST['ai_prompt'] ) ) {
			$settings['prompt'] = sanitize_textarea_field( wp_unslash( $_POST['ai_prompt'] ) );
		}
		update_option( 'connect_ecommerce_ai', $settings );
		wp_send_json_success( array( 'message' => __( 'AI settings saved.', 'woocommerce-es' ) ) );
	}

	/**
	 * Marks the wizard as complete.
	 *
	 * @return void
	 */
	public function ajax_complete() {
		$this->verify_request();
		update_option( 'conecom_wizard_complete', true );
		wp_send_json_success( array( 'redirect' => admin_url( 'admin.php?page=connect_ecommerce' ) ) );
	}
}
