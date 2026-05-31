<?php
/**
 * Library for admin settings
 *
 * @package    WordPress
 * @author     David Perez <david@closemarketing.es>
 * @copyright  2019 Closemarketing
 * @version    1.0
 */

namespace CLOSE\ConnectEcommerce\Admin;

defined( 'ABSPATH' ) || exit;

use CLOSE\ConnectEcommerce\Helpers\PROD;
use CLOSE\ConnectEcommerce\Helpers\TAX;
use CLOSE\ConnectEcommerce\Helpers\HELPER;
use CLOSE\ConnectEcommerce\Helpers\AI;
use CLOSE\ConnectEcommerce\Helpers\CRON;
use CLOSE\ConnectEcommerce\Helpers\ALERT;

/**
 * Library for WooCommerce Settings
 *
 * Settings in order to sync products
 *
 * @package    WordPress
 * @author     David Perez <david@closemarketing.es>
 * @copyright  2019 Closemarketing
 * @version    0.1
 */

/**
 * Class Admin Connect WooCommerce.
 */
class Settings {
	/**
	 * Settings
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Settings
	 *
	 * @var array
	 */
	private $settings_public;

	/**
	 * Settings
	 *
	 * @var array
	 */
	private $settings_prod_mergevars;

	/**
	 * Options name for getting settings
	 *
	 * @var array
	 */
	private $options;

	/**
	 * API Object
	 *
	 * @var object
	 */
	private $connapi_erp;

	/**
	 * Settings AI
	 *
	 * @var string
	 */
	private $settings_ai;

	/**
	 * Settings slug
	 *
	 * @var string
	 */
	private $is_mergevars;

	/**
	 * Have payments methods
	 *
	 * @var boolean
	 */
	private $have_payments_methods;

	/**
	 * Settings slug
	 *
	 * @var string
	 */
	private $is_disabled_orders;

	/**
	 * Connector
	 *
	 * @var string
	 */
	private $connector;

	/**
	 * All options
	 *
	 * @var array
	 */
	private $all_options;

	/**
	 * Settings slug
	 *
	 * @var string
	 */
	private $settings_all;

	/**
	 * Settings slug
	 *
	 * @var string
	 */
	private $is_disabled_ai;

	/**
	 * Payment methods page handler.
	 *
	 * @var Settings_Payment_Methods|null
	 */
	private $payment_methods_page;

	/**
	 * Construct of class
	 *
	 * @param array $connector Connector.
	 * @return void
	 */
	public function __construct( $connector ) {
		$this->settings_all          = $connector['settings_all'];
		$this->connector             = $connector['connector'] ?? '';
		$this->settings              = $connector['settings'] ?? array();
		$this->all_options           = $connector['all_options'];
		$this->options               = $connector['options'] ?? array();
		$this->connapi_erp           = $connector['connapi_erp'] ?? null;
		$this->is_mergevars          = $connector['is_mergevars'] ?? false;
		$this->is_disabled_orders    = $connector['is_disabled_orders'] ?? false;
		$this->is_disabled_ai        = $connector['is_disabled_ai'] ?? false;
		$this->have_payments_methods = ! empty( $this->connapi_erp ) && method_exists( $this->connapi_erp, 'get_payment_methods' );

		// If connector is saved but the plugin is no longer active (no options/api loaded), still register the admin page so the user can change the connector.
		if ( ! empty( $this->connector ) && empty( $this->options ) && ! empty( $this->connapi_erp ) ) {
			return;
		}

		if ( $this->have_payments_methods ) {
			$this->init_payment_methods_page();
		}

		add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
		add_action( 'admin_init', array( $this, 'page_init' ) );
		add_action( 'wp_ajax_connect_ecommerce_test_alert', array( $this, 'test_alert_callback' ) );
	}

	/**
	 * Whether the saved connector is fully active (plugin loaded and options available).
	 *
	 * Returns false when a connector slug is saved but the corresponding plugin is
	 * no longer active, so settings can still be shown to allow changing the connector.
	 *
	 * @return bool
	 */
	private function is_connector_active() {
		return ! empty( $this->connector ) && ! empty( $this->options );
	}

	/**
	 * Initialize payment methods page handler.
	 *
	 * @return void
	 */
	private function init_payment_methods_page() {
		if ( ! is_object( $this->connapi_erp ) ) {
			return;
		}

		$connector_options = $this->options;
		$connector_slug    = (string) $this->connector;

		$this->payment_methods_page = new Settings_Payment_Methods(
			$this->connapi_erp,
			$connector_slug,
			is_array( $connector_options ) ? $connector_options : array()
		);
	}

	/**
	 * Adds plugin page.
	 *
	 * @return void
	 */
	public function add_plugin_page() {
		add_submenu_page(
			'woocommerce',
			__( 'Connect Ecommerce', 'woocommerce-es' ),
			__( 'Connect Ecommerce', 'woocommerce-es' ),
			'manage_options',
			'connect_ecommerce',
			array( $this, 'create_admin_page' )
		);
	}

	/**
	 * Create admin page.
	 *
	 * @return void
	 */
	public function create_admin_page() {
		$special_tabs = array();
		if ( $this->is_connector_active() ) {
			$this->settings_public         = get_option( 'connect_ecommerce_public' );
			$this->settings_prod_mergevars = get_option( 'connect_ecommerce_prod_mergevars' );
			$this->settings_ai             = get_option( 'connect_ecommerce_ai' );
			$special_tabs                  = ! empty( $this->options['settings_special_tabs'] ) ? $this->options['settings_special_tabs'] : array();
		}
		$plugin_logo = CONECOM_PLUGIN_URL . 'includes/assets/logo.png';
		?>
		<div class="connect-ecommerce-settings">
			<div class="header-wrap">
				<div class="wrapper">
					<h2 style="display: none;"></h2>
					<div id="nag-container"></div>
					<div class="header connwoo-header">
						<div class="logo">
							<img src="<?php echo esc_url( $plugin_logo ); ?>" height="47" width="154"/>
							<h2>
								<?php
								esc_html_e( 'Connect Ecommerce and EU/VAT Compliance', 'woocommerce-es' );
								?>
							</h2>
						</div>
					</div>
				</div>
			</div>
			<div class="wrap">
				<?php settings_errors(); ?>

				<?php
				// Main tabs. Default to settings when connector plugin is not active.
				$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : ( $this->is_connector_active() ? 'synchronization' : 'settings' );

				// Subtabs.
				$active_subtab = isset( $_GET['subtab'] ) ? sanitize_text_field( wp_unslash( $_GET['subtab'] ) ) : '';

				// Set default subtabs.
				if ( 'synchronization' === $active_tab && empty( $active_subtab ) ) {
					$active_subtab = 'sync_products';
				}
				if ( 'settings' === $active_tab && empty( $active_subtab ) ) {
					$active_subtab = 'connection';
				}
				?>
				<h2 class="nav-tab-wrapper">
					<?php
					if ( $this->is_connector_active() ) {
						?>
						<a href="?page=connect_ecommerce&tab=synchronization&subtab=sync_products" class="nav-tab <?php echo 'synchronization' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Synchronization', 'woocommerce-es' ); ?></a>
						<?php
					}
					?>
					<a href="?page=connect_ecommerce&tab=settings&subtab=connection" class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Settings', 'woocommerce-es' ); ?></a>
					<?php
					if ( $this->is_connector_active() && in_array( 'subscriptions', $special_tabs, true ) ) {
						?>
						<a href="?page=connect_ecommerce&tab=subscriptions" class="nav-tab <?php echo 'subscriptions' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Subscriptions', 'woocommerce-es' ); ?></a>
						<?php
					}

					// Support for connect_ecommerce_settings_tabs (custom tabs).
					$custom_tabs = apply_filters( 'connect_ecommerce_settings_tabs', array() );
					if ( ! empty( $custom_tabs ) ) {
						foreach ( $custom_tabs as $custom_tab ) {
							$tab_slug  = isset( $custom_tab['tab'] ) ? $custom_tab['tab'] : '';
							$tab_label = isset( $custom_tab['label'] ) ? $custom_tab['label'] : '';
							if ( ! empty( $tab_slug ) && ! empty( $tab_label ) ) {
								?>
								<a href="?page=connect_ecommerce&tab=<?php echo esc_attr( $tab_slug ); ?>" class="nav-tab <?php echo esc_attr( $tab_slug ) === $active_tab ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $tab_label ); ?></a>
								<?php
							}
						}
					}

					do_action( 'connect_ecommerce_settings_tabs', $active_tab );
					?>
				</h2>

				<?php
				// Subtabs for Synchronization.
				if ( 'synchronization' === $active_tab && $this->is_connector_active() ) {
					?>
					<ul class="subsubsub">
						<li><a href="?page=connect_ecommerce&tab=synchronization&subtab=sync_products" class="<?php echo 'sync_products' === $active_subtab ? 'current' : ''; ?>"><?php esc_html_e( 'Products', 'woocommerce-es' ); ?></a><?php echo ( ! $this->is_disabled_orders ) ? ' | ' : ''; ?></li>
						<?php
						if ( ! $this->is_disabled_orders ) {
							?>
							<li><a href="?page=connect_ecommerce&tab=synchronization&subtab=sync_orders" class="<?php echo 'sync_orders' === $active_subtab ? 'current' : ''; ?>"><?php esc_html_e( 'Orders', 'woocommerce-es' ); ?></a></li>
							<?php
						}
						?>
					</ul>
					<br class="clear">
					<?php
				}

				// Subtabs for Settings.
				if ( 'settings' === $active_tab ) {
					?>
					<ul class="subsubsub">
						<li><a href="?page=connect_ecommerce&tab=settings&subtab=connection" class="<?php echo 'connection' === $active_subtab ? 'current' : ''; ?>"><?php esc_html_e( 'Connection', 'woocommerce-es' ); ?></a> | </li>
						<li><a href="?page=connect_ecommerce&tab=settings&subtab=vat_compliance" class="<?php echo 'vat_compliance' === $active_subtab ? 'current' : ''; ?>"><?php esc_html_e( 'EU VAT Compliance', 'woocommerce-es' ); ?></a><?php echo ( $this->is_connector_active() && $this->is_mergevars ) || ( ! $this->is_disabled_ai && $this->is_connector_active() ) ? ' | ' : ''; ?></li>
						<?php
						if ( $this->is_connector_active() && $this->is_mergevars ) {
							?>
							<li><a href="?page=connect_ecommerce&tab=settings&subtab=merge_vars" class="<?php echo 'merge_vars' === $active_subtab ? 'current' : ''; ?>"><?php esc_html_e( 'Merge Vars', 'woocommerce-es' ); ?></a><?php echo ( ! $this->is_disabled_ai && $this->is_connector_active() ) ? ' | ' : ''; ?></li>
							<?php
						}
						if ( $this->is_connector_active() && $this->have_payments_methods ) {
							?>
							<li><a href="?page=connect_ecommerce&tab=settings&subtab=payment_methods" class="<?php echo 'payment_methods' === $active_subtab ? 'current' : ''; ?>"><?php esc_html_e( 'Payment Methods', 'woocommerce-es' ); ?></a><?php echo ( ! $this->is_disabled_ai && $this->is_connector_active() ) ? ' | ' : ''; ?></li>
							<?php
						}
						if ( ! $this->is_disabled_ai && $this->is_connector_active() ) {
							?>
							<li><a href="?page=connect_ecommerce&tab=settings&subtab=ai_products" class="<?php echo 'ai_products' === $active_subtab ? 'current' : ''; ?>"><?php esc_html_e( 'AI Products', 'woocommerce-es' ); ?></a> | </li>
							<?php
						}
						if ( $this->is_connector_active() ) {
						?>
							<li><a href="?page=connect_ecommerce&tab=settings&subtab=alerts" class="<?php echo 'alerts' === $active_subtab ? 'current' : ''; ?>"><?php esc_html_e( 'Alerts', 'woocommerce-es' ); ?></a></li>
							<?php
						}
						?>
					</ul>
					<br class="clear">
					<?php
				}
				?>
				<?php
				// Synchronization Tab Content.
				if ( 'synchronization' === $active_tab ) {
					if ( 'sync_products' === $active_subtab || 'sync_orders' === $active_subtab ) {
						$this->page_get_sync( $active_subtab );
					}
				}

				// Settings Tab Content.
				if ( 'settings' === $active_tab ) {
					if ( 'connection' === $active_subtab ) {
						if ( ! empty( $this->connector ) && ! $this->is_connector_active() ) {
							?>
							<div class="notice notice-warning">
								<p>
									<?php
									printf(
										/* translators: %s: connector slug */
										esc_html__( 'The connector "%s" is saved but its plugin is not active. Select a different connector below.', 'woocommerce-es' ),
										esc_html( $this->connector )
									);
									?>
								</p>
							</div>
							<?php
						}
						?>
						<form method="post" action="options.php">
							<?php
								settings_fields( 'connect_ecommerce_settings' );
								do_settings_sections( 'connect_ecommerce_admin' );
								submit_button(
									__( 'Save settings', 'woocommerce-es' ),
									'primary',
									'submit_settings'
								);
							?>
						</form>
						<?php
					}

					if ( 'vat_compliance' === $active_subtab ) {
						?>
						<form method="post" action="options.php">
							<?php
							settings_fields( 'connect_ecommerce_settings_public' );
							do_settings_sections( 'connect_ecommerce_public' );
							submit_button(
								__( 'Save VAT Compliance', 'woocommerce-es' ),
								'primary',
								'submit_public'
							);
							?>
						</form>
						<?php
					}

					if ( 'merge_vars' === $active_subtab && $this->is_mergevars ) {
						?>
						<form method="post" action="options.php">
							<?php
							settings_fields( 'connect_ecommerce_settings_prod_mergevars' );
							do_settings_sections( 'connect_ecommerce_prod_mergevars' );
							submit_button(
								__( 'Save merge vars', 'woocommerce-es' ),
								'primary',
								'submit_prod_mergevars'
							);
							?>
						</form>
						<?php
					}

					if ( 'payment_methods' === $active_subtab ) {
						if ( $this->have_payments_methods && is_object( $this->payment_methods_page ) ) {
							$this->payment_methods_page->render_page();
						}
					}
					if ( 'ai_products' === $active_subtab ) {
						?>
						<form method="post" action="options.php">
							<?php
							settings_fields( 'connect_ecommerce_settings_ai' );
							do_settings_sections( 'connect_ecommerce_ai' );
							submit_button(
								__( 'Save AI', 'woocommerce-es' ),
								'primary',
								'submit_ai'
							);
							?>
						</form>
						<?php
					}

					if ( 'alerts' === $active_subtab ) {
						?>
						<form method="post" action="options.php">
							<?php
							settings_fields( 'connect_ecommerce_settings_alerts' );
							do_settings_sections( 'connect_ecommerce_alerts' );
							?>
							<p>
								<button type="button" id="test-alert" class="button button-secondary">
									<?php esc_html_e( 'Send Test Alert', 'woocommerce-es' ); ?>
								</button>
								<span id="test-alert-result"></span>
							</p>
							<?php
							submit_button(
								__( 'Save Alerts Settings', 'woocommerce-es' ),
								'primary',
								'submit_alerts'
							);
							?>
						</form>
						<script>
						document.getElementById('test-alert').addEventListener('click', function() {
							var btn = this;
							var resultSpan = document.getElementById('test-alert-result');
							btn.disabled = true;
							btn.textContent = '<?php esc_html_e( 'Sending...', 'woocommerce-es' ); ?>';
							resultSpan.textContent = '';
							
							fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
								method: 'POST',
								headers: {
									'Content-Type': 'application/x-www-form-urlencoded',
								},
								body: 'action=connect_ecommerce_test_alert&nonce=<?php echo esc_js( wp_create_nonce( 'test_alert_nonce' ) ); ?>'
							})
							.then(response => response.json())
							.then(data => {
								btn.disabled = false;
								btn.textContent = '<?php esc_html_e( 'Send Test Alert', 'woocommerce-es' ); ?>';
								if (data.success) {
									resultSpan.innerHTML = '<span style="color: green;">✓ <?php esc_html_e( 'Test alert sent successfully!', 'woocommerce-es' ); ?></span>';
								} else {
									resultSpan.innerHTML = '<span style="color: red;">✗ <?php esc_html_e( 'Failed to send test alert.', 'woocommerce-es' ); ?></span>';
								}
								setTimeout(function() {
									resultSpan.textContent = '';
								}, 5000);
							})
							.catch(error => {
								btn.disabled = false;
								btn.textContent = '<?php esc_html_e( 'Send Test Alert', 'woocommerce-es' ); ?>';
								resultSpan.innerHTML = '<span style="color: red;">✗ <?php esc_html_e( 'Error sending test alert.', 'woocommerce-es' ); ?></span>';
							});
						});
						</script>
						<?php
					}
				}

				// Subscriptions Tab (kept separate as it's not part of the two main tabs).
				if ( 'subscriptions' === $active_tab ) {
					$this->page_get_subscriptions();
				}

				// Render content of customized tabs.
				$conecom_tabs = apply_filters( 'connect_ecommerce_settings_tabs', array() );
				if ( ! empty( $conecom_tabs ) ) {
					foreach ( $conecom_tabs as $conecom_tab ) {
						$tab_slug    = isset( $conecom_tab['tab'] ) ? $conecom_tab['tab'] : '';
						$action_hook = isset( $conecom_tab['action'] ) ? $conecom_tab['action'] : '';

						if ( $tab_slug === $active_tab && ! empty( $action_hook ) ) {
							do_action( $action_hook );
						}
					}
				}

				do_action( 'connect_ecommerce_settings_tabs_content', $active_tab, $active_subtab );
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Init for page
	 *
	 * @return void
	 */
	public function page_init() {
		$settings_fields = ! empty( $this->options['settings_fields'] ) ? $this->options['settings_fields'] : array();

		register_setting(
			'connect_ecommerce_settings',
			'connect_ecommerce',
			array( $this, 'sanitize_fields_settings' )
		);

		add_settings_section(
			'connect_woocommerce_setting_section',
			__( 'Settings for Importing in WooCommerce', 'woocommerce-es' ),
			array( $this, 'connect_woocommerce_section_info' ),
			'connect_ecommerce_admin'
		);

		add_settings_field(
			'conecom_connector',
			__( 'Connector', 'woocommerce-es' ),
			array( $this, 'connector_callback' ),
			'connect_ecommerce_admin',
			'connect_woocommerce_setting_section'
		);

		if ( $this->connector && ! empty( $this->options ) ) {
			if ( 'connwoo_neo' === $this->options['slug'] ) {
				add_settings_field(
					'wcpimh_idcentre',
					__( 'NEO ID Centre', 'woocommerce-es' ),
					array( $this, 'idcentre_callback' ),
					'connect_ecommerce_admin',
					'connect_woocommerce_setting_section'
				);
			}

			// URL.
			if ( in_array( 'url', $settings_fields, true ) ) {
				add_settings_field(
					'wcpimh_url',
					__( 'URL', 'woocommerce-es' ),
					array( $this, 'url_callback' ),
					'connect_ecommerce_admin',
					'connect_woocommerce_setting_section'
				);
			}

			// DB Name.
			if ( in_array( 'dbname', $settings_fields, true ) ) {
				add_settings_field(
					'wcpimh_dbname',
					__( 'DB Name', 'woocommerce-es' ),
					array( $this, 'dbname_callback' ),
					'connect_ecommerce_admin',
					'connect_woocommerce_setting_section'
				);
			}

			// Username.
			if ( in_array( 'username', $settings_fields, true ) ) {
				add_settings_field(
					'wcpimh_username',
					__( 'Username', 'woocommerce-es' ),
					array( $this, 'username_callback' ),
					'connect_ecommerce_admin',
					'connect_woocommerce_setting_section'
				);
			}

			// Password.
			if ( in_array( 'password', $settings_fields, true ) ) {
				add_settings_field(
					'wcpimh_password',
					__( 'Password', 'woocommerce-es' ),
					array( $this, 'password_callback' ),
					'connect_ecommerce_admin',
					'connect_woocommerce_setting_section'
				);
			}

			// Company.
			if ( in_array( 'company', $settings_fields, true ) ) {
				add_settings_field(
					'wcpimh_company',
					__( 'Company', 'woocommerce-es' ),
					array( $this, 'company_callback' ),
					'connect_ecommerce_admin',
					'connect_woocommerce_setting_section'
				);
			}

			// Domain.
			if ( in_array( 'domain', $settings_fields, true ) ) {
				add_settings_field(
					'wcpimh_domain',
					__( 'domain', 'woocommerce-es' ),
					array( $this, 'domain_callback' ),
					'connect_ecommerce_admin',
					'connect_woocommerce_setting_section'
				);
			}

			// API Password.
			if ( in_array( 'apipassword', $settings_fields, true ) ) {
				add_settings_field(
					'wcpimh_api',
					__( 'API Key', 'woocommerce-es' ),
					array( $this, 'api_callback' ),
					'connect_ecommerce_admin',
					'connect_woocommerce_setting_section'
				);
			}

			// Manufacturer Code.
			if ( in_array( 'manufacturer_code', $settings_fields, true ) ) {
				add_settings_field(
					'wcpimh_manufacturer_code',
					__( 'Manufacturer Code', 'woocommerce-es' ),
					array( $this, 'manufacturer_code_callback' ),
					'connect_ecommerce_admin',
					'connect_woocommerce_setting_section'
				);
			}

			// Customer Code.
			if ( in_array( 'customer_code', $settings_fields, true ) ) {
				add_settings_field(
					'wcpimh_customer_code',
					__( 'Customer Code', 'woocommerce-es' ),
					array( $this, 'customer_code_callback' ),
					'connect_ecommerce_admin',
					'connect_woocommerce_setting_section'
				);
			}

			// Company Select.
			if ( in_array( 'company_id', $settings_fields, true ) ) {
				add_settings_field(
					'wcpimh_company_select',
					__( 'Company', 'woocommerce-es' ),
					array( $this, 'company_select_callback' ),
					'connect_ecommerce_admin',
					'connect_woocommerce_setting_section'
				);
			}

		// API Connection Status.
		add_settings_field(
			'wcpimh_api_status',
			__( 'Connection Status', 'woocommerce-es' ),
			array( $this, 'api_status_callback' ),
			'connect_ecommerce_admin',
			'connect_woocommerce_setting_section'
		);

		if ( $this->options['product_option_stock'] ) {
				add_settings_field(
					'wcpimh_stock',
					__( 'Import stock?', 'woocommerce-es' ),
					array( $this, 'stock_callback' ),
					'connect_ecommerce_admin',
					'connect_woocommerce_setting_section'
				);
			}

			add_settings_field(
				'wcpimh_prodst',
				__( 'Default status for new products?', 'woocommerce-es' ),
				array( $this, 'prodst_callback' ),
				'connect_ecommerce_admin',
				'connect_woocommerce_setting_section'
			);

			add_settings_field(
				'wcpimh_virtual',
				__( 'Virtual products?', 'woocommerce-es' ),
				array( $this, 'virtual_callback' ),
				'connect_ecommerce_admin',
				'connect_woocommerce_setting_section'
			);

			add_settings_field(
				'wcpimh_backorders',
				__( 'Allow backorders?', 'woocommerce-es' ),
				array( $this, 'backorders_callback' ),
				'connect_ecommerce_admin',
				'connect_woocommerce_setting_section'
			);

			add_settings_field(
				'wcpimh_catsep',
				__( 'Category separator', 'woocommerce-es' ),
				array( $this, 'catsep_callback' ),
				'connect_ecommerce_admin',
				'connect_woocommerce_setting_section'
			);

			add_settings_field(
				'wcpimh_catattr',
				__( 'Attribute to use as category', 'woocommerce-es' ),
				array( $this, 'catattr_callback' ),
				'connect_ecommerce_admin',
				'connect_woocommerce_setting_section'
			);

			add_settings_field(
				'wcpimh_catnp',
				__( 'Import category only in new products?', 'woocommerce-es' ),
				array( $this, 'catnp_callback' ),
				'connect_ecommerce_admin',
				'connect_woocommerce_setting_section'
			);

			add_settings_field(
				'wcpimh_filter',
				__( 'Filter products by tags? Only import this tags (separated by comma and no space)', 'woocommerce-es' ),
				array( $this, 'filter_callback' ),
				'connect_ecommerce_admin',
				'connect_woocommerce_setting_section'
			);

			add_settings_field(
				'wcpimh_filter_sku',
				__( 'Filter products by SKU? Only the products that complies these formula (use * for formula)', 'woocommerce-es' ),
				array( $this, 'filter_sku_callback' ),
				'connect_ecommerce_admin',
				'connect_woocommerce_setting_section'
			);

			if ( $this->options['product_price_tax_option'] ) {
				add_settings_field(
					'wcpimh_tax_option',
					__( 'Get prices with Tax?', 'woocommerce-es' ),
					array( $this, 'tax_option_callback' ),
					'connect_ecommerce_admin',
					'connect_woocommerce_setting_section'
				);
			}

			add_settings_field(
				'wcpimh_make_discount',
				__( 'Percentage to Make a discount from prices and save in sale price?', 'woocommerce-es' ),
				array( $this, 'pricesale_discount_option_callback' ),
				'connect_ecommerce_admin',
				'connect_woocommerce_setting_section'
			);

			if ( $this->options['product_price_rate_option'] ) {
				$desc_tip     = __( 'Copy and paste the ID of the rates for publishing in the web', 'woocommerce-es' );
				add_settings_field(
					'wcpimh_rates',
					__( 'Product price rate for this eCommerce', 'woocommerce-es' ),
					array( $this, 'rates_callback' ),
					'connect_ecommerce_admin',
					'connect_woocommerce_setting_section'
				);
			}

			if ( ( isset( $this->options['order_series_number'] ) && $this->options['order_series_number'] ) || 'Holded' === $this->options['name'] ) {
				add_settings_field(
					'wcpimh_serie_number',
					__( 'Serie number', 'woocommerce-es' ),
					array( $this, 'serie_number_callback' ),
					'connect_ecommerce_admin',
					'connect_woocommerce_setting_section'
				);
			}

			add_settings_field(
				'wcpimh_cleanchars',
				__( 'Clean special characters for Verifactu?', 'woocommerce-es' ),
				array( $this, 'cleanchars_callback' ),
				'connect_ecommerce_admin',
				'connect_woocommerce_setting_section'
			);

			if ( in_array( 'approve_document', $settings_fields, true ) ) {
				add_settings_field(
					'wcpimh_approve_document',
					__( 'Approve document by default for validations?', 'woocommerce-es' ),
					array( $this, 'approve_document_callback' ),
					'connect_ecommerce_admin',
					'connect_woocommerce_setting_section'
				);
			}

			if ( 'Holded' === $this->options['name'] || in_array( 'doctype', $settings_fields, true ) ) {
				add_settings_field(
					'wcpimh_doctype',
					__( 'Document to create after order completed?', 'woocommerce-es' ),
					array( $this, 'doctype_callback' ),
					'connect_ecommerce_admin',
					'connect_woocommerce_setting_section'
				);
			}

			if ( 'Holded' === $this->options['name'] ) {
				add_settings_field(
					'wcpimh_design_id',
					__( 'ID Holded design for document', 'woocommerce-es' ),
					array( $this, 'wcpimh_design_id_callback' ),
					'connect_ecommerce_admin',
					'connect_woocommerce_setting_section'
				);
			}

			if ( ! $this->is_disabled_orders ) {
				add_settings_field(
					'wcpimh_freeorder',
					__( 'Create document for free Orders?', 'woocommerce-es' ),
					array( $this, 'freeorder_callback' ),
					'connect_ecommerce_admin',
					'connect_woocommerce_setting_section'
				);

				add_settings_field(
					'wcpimh_ecstatus',
					__( 'Status to sync Orders?', 'woocommerce-es' ),
					array( $this, 'ecstatus_callback' ),
					'connect_ecommerce_admin',
					'connect_woocommerce_setting_section'
				);
			}

			if ( ! empty( $this->options['order_tags'] ) ) {
				add_settings_field(
					'wcpimh_order_tags',
					__( 'Order Tag by default (separated by coma)?', 'woocommerce-es' ),
					array( $this, 'order_tags_callback' ),
					'connect_ecommerce_admin',
					'connect_woocommerce_setting_section'
				);
			}

			if ( ! empty( $this->options['product_weight_equivalence'] ) ) {
				$attributes = get_transient( 'conecom_query_attributes' );
				if ( false === $attributes ) { // Query attributes.
					$attributes = $this->connapi_erp->get_product_attributes();
					set_transient( 'conecom_query_attributes', $attributes, HOUR_IN_SECONDS * 3 );
				}
				if ( ! empty( $attributes ) ) {
					add_settings_field(
						'wcpimh_product_weight_equivalence',
						__( 'Custom field for Equivalence with weight', 'woocommerce-es' ),
						array( $this, 'product_weight_equivalence_callback' ),
						'connect_ecommerce_admin',
						'connect_woocommerce_setting_section'
					);
				}
			}
			add_settings_field(
				'wcpimh_debug_log',
				__( 'Debug Mode', 'woocommerce-es' ),
				array( $this, 'debug_log_callback' ),
				'connect_ecommerce_admin',
				'connect_woocommerce_setting_section'
			);
		}

		/**
		 * ## Merge Vars
		 * --------------------------- */

		register_setting(
			'connect_ecommerce_settings_prod_mergevars',
			'connect_ecommerce_prod_mergevars',
			array( $this, 'sanitize_fields_prod_mergevars' )
		);

		add_settings_section(
			'connect_ecommerce_prod_mergevars_section',
			__( 'Merge variables from ERP to WooCommerce', 'woocommerce-es' ),
			array( $this, 'section_info_prod_mergevars' ),
			'connect_ecommerce_prod_mergevars'
		);

		add_settings_field(
			'wcpimh_prod_mergevars',
			__( 'Merge fields', 'woocommerce-es' ),
			array( $this, 'prod_mergevars_callback' ),
			'connect_ecommerce_prod_mergevars',
			'connect_ecommerce_prod_mergevars_section'
		);

		/**
		 * ## Public
		 * --------------------------- */

		register_setting(
			'connect_ecommerce_settings_public',
			'connect_ecommerce_public',
			array( $this, 'sanitize_fields_public' )
		);

		add_settings_section(
			'imhset_pub_setting_section',
			__( 'Settings for Woocommerce Shop', 'woocommerce-es' ),
			array( $this, 'section_info_public' ),
			'connect_ecommerce_public'
		);

		add_settings_field(
			'wcpimh_vat_show',
			__( 'Ask for VAT in Checkout?', 'woocommerce-es' ),
			array( $this, 'vat_show_callback' ),
			'connect_ecommerce_public',
			'imhset_pub_setting_section'
		);
		add_settings_field(
			'wcpimh_vat_mandatory',
			__( 'VAT info mandatory?', 'woocommerce-es' ),
			array( $this, 'vat_mandatory_callback' ),
			'connect_ecommerce_public',
			'imhset_pub_setting_section'
		);

		add_settings_field(
			'wcpimh_company_field',
			__( 'Show Company field?', 'woocommerce-es' ),
			array( $this, 'company_field_callback' ),
			'connect_ecommerce_public',
			'imhset_pub_setting_section'
		);

		add_settings_field(
			'wcpimh_remove_free_others',
			__( 'Remove other shipping methods when free is possible?', 'woocommerce-es' ),
			array( $this, 'remove_free_others_callback' ),
			'connect_ecommerce_public',
			'imhset_pub_setting_section'
		);

		add_settings_field(
			'wcpimh_terms_registration',
			__( 'Adds terms and conditions in registration page?', 'woocommerce-es' ),
			array( $this, 'terms_registration_callback' ),
			'connect_ecommerce_public',
			'imhset_pub_setting_section'
		);

		add_settings_field(
			'wcpimh_vat_vies_enabled',
			__( 'Enable VAT validation via VIES?', 'woocommerce-es' ),
			array( $this, 'vat_vies_enabled_callback' ),
			'connect_ecommerce_public',
			'imhset_pub_setting_section'
		);

		add_settings_field(
			'wcpimh_vatsense_api_key',
			__( 'VATSense API Key (Optional)', 'woocommerce-es' ),
			array( $this, 'vatsense_api_key_callback' ),
			'connect_ecommerce_public',
			'imhset_pub_setting_section'
		);

		add_settings_field(
			'wcpimh_vat_vies_mandatory',
			__( 'Mandatory VAT validation for checkout?', 'woocommerce-es' ),
			array( $this, 'vat_vies_mandatory_callback' ),
			'connect_ecommerce_public',
			'imhset_pub_setting_section'
		);

		/**
		 * ## AI
		 * --------------------------- */

		register_setting(
			'connect_ecommerce_settings_ai',
			'connect_ecommerce_ai',
			array( $this, 'sanitize_fields_ai' )
		);

		add_settings_section(
			'imhset_ai_setting_section',
			__( 'Options to Use AI generating description for products', 'woocommerce-es' ),
			array( $this, 'section_info_ai' ),
			'connect_ecommerce_ai'
		);

		add_settings_field(
			'connect_ecommerce_ai_model',
			__( 'Model', 'woocommerce-es' ),
			array( $this, 'ai_model_callback' ),
			'connect_ecommerce_ai',
			'imhset_ai_setting_section'
		);

		add_settings_field(
			'connect_ecommerce_ai_prompt',
			__( 'Prompt', 'woocommerce-es' ),
			array( $this, 'ai_prompt_callback' ),
			'connect_ecommerce_ai',
			'imhset_ai_setting_section'
		);

		/**
		 * ## Alerts
		 * --------------------------- */

		register_setting(
			'connect_ecommerce_settings_alerts',
			'connect_ecommerce_alerts',
			array( $this, 'sanitize_fields_alerts' )
		);

		add_settings_section(
			'connect_ecommerce_alerts_section',
			__( 'Configure Alert Notifications for Errors', 'woocommerce-es' ),
			array( $this, 'section_info_alerts' ),
			'connect_ecommerce_alerts'
		);

		add_settings_field(
			'connect_ecommerce_alert_enabled',
			__( 'Enable Alerts', 'woocommerce-es' ),
			array( $this, 'alert_enabled_callback' ),
			'connect_ecommerce_alerts',
			'connect_ecommerce_alerts_section'
		);

		add_settings_field(
			'connect_ecommerce_alert_product_synced',
			__( 'Enable Alerts Product synced', 'woocommerce-es' ),
			array( $this, 'alert_product_synced_callback' ),
			'connect_ecommerce_alerts',
			'connect_ecommerce_alerts_section'
		);

		add_settings_field(
			'connect_ecommerce_alert_method',
			__( 'Alert Method', 'woocommerce-es' ),
			array( $this, 'alert_method_callback' ),
			'connect_ecommerce_alerts',
			'connect_ecommerce_alerts_section'
		);

		add_settings_field(
			'connect_ecommerce_alert_email',
			__( 'Email Address', 'woocommerce-es' ),
			array( $this, 'alert_email_callback' ),
			'connect_ecommerce_alerts',
			'connect_ecommerce_alerts_section'
		);

		add_settings_field(
			'connect_ecommerce_slack_webhook',
			__( 'Slack Webhook URL', 'woocommerce-es' ),
			array( $this, 'slack_webhook_callback' ),
			'connect_ecommerce_alerts',
			'connect_ecommerce_alerts_section'
		);
	}

	/**
	 * Page get Merge Product variables
	 *
	 * @param string $type Type of page.
	 * @return void
	 */
	public function page_get_sync( $type = 'sync_products' ) {
		$ajax_action = 'connect_ecommerce_' . $type;
		if ( empty( $this->connapi_erp ) || ! method_exists( $this->connapi_erp, 'check_can_sync' ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'No connector configured. Please configure a connector in the Settings tab.', 'woocommerce-es' ) . '</p></div>';
			return;
		}
		$login_api = $this->connapi_erp->check_can_sync();
		$can_sync  = false;
		if ( is_array( $login_api ) ) {
			$message  = $login_api['message'] ?? '';
			$can_sync = 'ok' === $login_api['status'] ? true : false;
		} else {
			$can_sync = $login_api;
			$message  = $login_api ? '' : __( 'We couln\'t connect to the API', 'woocommerce-es' );
		}

		$has_get_all_product_skus = ! empty( $this->connapi_erp ) && method_exists( $this->connapi_erp, 'get_all_product_skus' );
		$api_pagination           = defined( 'CONECOM_SYNC_PRODUCTS_PER_BATCH' ) ? CONECOM_SYNC_PRODUCTS_PER_BATCH : 50;
		?>
		<div class="connwoo-sync-engine connwoo-sync-with-stats">
			<div class="sync-wrapper">
				<?php
				if ( empty( $can_sync ) ) {
					?>
					<div class="error notice">
						<p>
							<?php esc_html_e( 'You need to set the API settings before importing products.', 'woocommerce-es' ); ?>
							<br/>
							<?php echo esc_html( $message ); ?>
						</p>
					</div>
					<?php
				} else {
					$this->render_import_with_stats( $ajax_action, $api_pagination, $has_get_all_product_skus );
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders import UI with tabs, sync type selection and optional stats (stats only when get_all_product_skus exists).
	 *
	 * @param string $ajax_action AJAX action name.
	 * @param int    $api_pagination Products per page for sync.
	 * @param bool   $has_get_all_product_skus Whether connector has get_all_product_skus (shows indicators).
	 * @return void
	 */
	private function render_import_with_stats( $ajax_action, $api_pagination, $has_get_all_product_skus = false ) {
		$cron_enabled        = ! empty( $this->settings['sync'] ) && 'no' !== $this->settings['sync'];
		$has_product_updated = $has_get_all_product_skus && (
			! method_exists( $this->connapi_erp, 'has_product_updated' ) || $this->connapi_erp->has_product_updated()
		);
		?>
		<h2>
			<?php
			printf(
				/* translators: %s: Name of the connector */
				esc_html__( 'Import Products from %s', 'woocommerce-es' ),
				esc_html( $this->options['name'] )
			);
			?>
		</h2>

		<?php
		if ( $has_get_all_product_skus ) {
			?>
		<!-- Import Statistics (only when get_all_product_skus exists) -->
		<div class="conecom-import-stats">
			<div class="conecom-stat-card">
				<div class="conecom-stat-icon conecom-icon-api">
					<span class="dashicons dashicons-cloud"></span>
				</div>
				<div class="conecom-stat-content">
					<div class="conecom-stat-value" id="stat-available-count">--</div>
					<div class="conecom-stat-label">
						<?php
						printf(
							/* translators: %s: Name of the ERP connector */
							esc_html__( 'Available in %s', 'woocommerce-es' ),
							esc_html( $this->options['name'] )
						);
						?>
					</div>
					<div class="conecom-stat-sublabel" id="stat-available-sublabel" style="display:none;"></div>
				</div>
			</div>

			<div class="conecom-stat-card">
				<div class="conecom-stat-icon conecom-icon-wp">
					<span class="dashicons dashicons-cart"></span>
				</div>
				<div class="conecom-stat-content">
					<div class="conecom-stat-value" id="stat-wp-count">--</div>
					<div class="conecom-stat-label"><?php esc_html_e( 'Products in WooCommerce', 'woocommerce-es' ); ?></div>
					<div class="conecom-stat-sublabel"><?php esc_html_e( 'Synced products', 'woocommerce-es' ); ?></div>
				</div>
			</div>

			<?php if ( $has_product_updated ) : ?>
			<div class="conecom-stat-card conecom-stat-import">
				<div class="conecom-stat-icon conecom-icon-import">
					<span class="dashicons dashicons-download"></span>
				</div>
				<div class="conecom-stat-content">
					<div class="conecom-stat-value" id="stat-import-count">--</div>
					<div class="conecom-stat-label"><?php esc_html_e( 'To Import/Update', 'woocommerce-es' ); ?></div>
					<div class="conecom-stat-sublabel">
						<span id="stat-new-count">--</span> <?php esc_html_e( 'new', 'woocommerce-es' ); ?> +
						<span id="stat-outdated-count">--</span> <?php esc_html_e( 'outdated', 'woocommerce-es' ); ?>
					</div>
				</div>
			</div>
			<?php endif; ?>

			<div class="conecom-stat-card conecom-stat-delete">
				<div class="conecom-stat-icon conecom-icon-delete">
					<span class="dashicons dashicons-trash"></span>
				</div>
				<div class="conecom-stat-content">
					<div class="conecom-stat-value" id="stat-delete-count">--</div>
					<div class="conecom-stat-label"><?php esc_html_e( 'Not in API', 'woocommerce-es' ); ?></div>
				</div>
			</div>
		</div>
			<?php
		}
		?>

		<!-- Two columns: Automatic Sync + Manual Import -->
		<div class="conecom-two-columns">
			<div class="conecom-cron-logs">
				<h3>
					<span class="dashicons dashicons-clock" style="vertical-align: middle;"></span>
					<?php esc_html_e( 'Automatic Sync (Cron)', 'woocommerce-es' ); ?>
					<?php if ( $cron_enabled ) : ?>
						<span style="color: green; font-size: 0.8em; font-weight: normal;">● <?php esc_html_e( 'Enabled', 'woocommerce-es' ); ?></span>
					<?php else : ?>
						<span style="color: #999; font-size: 0.8em; font-weight: normal;">○ <?php esc_html_e( 'Disabled', 'woocommerce-es' ); ?></span>
					<?php endif; ?>
				</h3>
				<p style="color: #646970; font-size: 14px; margin-top: 10px;">
					<?php esc_html_e( 'Choose when to run automatic sync below.', 'woocommerce-es' ); ?>
				</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" style="margin-top: 12px;">
					<?php settings_fields( 'connect_ecommerce_settings' ); ?>
					<input type="hidden" name="connect_ecommerce[connector]" value="<?php echo esc_attr( $this->connector ); ?>" />
					<p style="display: flex; align-items: center; flex-wrap: wrap; gap: 8px; margin: 0;">
						<label for="wcpimh_sync" style="margin: 0;"><?php esc_html_e( 'When do you want to sync?', 'woocommerce-es' ); ?></label>
						<?php $this->sync_callback(); ?>
						<?php submit_button( __( 'Save', 'woocommerce-es' ), 'secondary', 'submit_sync_frequency', false ); ?>
					</p>
				</form>
			</div>

			<div class="conecom-manual-import">
				<h3>
					<span class="dashicons dashicons-upload" style="vertical-align: middle;"></span>
					<?php esc_html_e( 'Manual Import', 'woocommerce-es' ); ?>
				</h3>

				<div class="import-button-wrapper">
					<select id="import-mode" class="import-mode-select">
						<?php if ( $has_product_updated ) : ?>
						<option value="updated"><?php esc_html_e( 'Products to update', 'woocommerce-es' ); ?></option>
						<?php endif; ?>
						<option value="all"><?php esc_html_e( 'All products', 'woocommerce-es' ); ?></option>
					</select>
					<button type="button" id="sync-products" name="sync-products" class="button button-large button-primary" onclick="syncManualItemsWithMode(this, '<?php echo esc_attr( $ajax_action ); ?>', 0, <?php echo (int) $api_pagination; ?>);"><?php esc_html_e( 'Start Import', 'woocommerce-es' ); ?></button>
					<?php
					if ( $has_get_all_product_skus ) {
						?>
					<button type="button" id="refresh_stats" name="refresh_stats" class="button button-large" onclick="loadImportStats();">
						<span class="dashicons dashicons-update"></span>
						<?php esc_html_e( 'Refresh Statistics', 'woocommerce-es' ); ?>
					</button>
						<?php
					}
					?>
					<span class="spinner"></span>
				</div>
				<?php
				if ( ! $this->is_disabled_ai ) {
					?>
					<p style="margin-top: 10px;">
						<label for="connect_ecommerce_ai_stats"><?php esc_html_e( 'AI generation SEO options for products:', 'woocommerce-es' ); ?></label>
						<select name="connwoo-sync-product-ai" id="connect_ecommerce_ai_stats">
							<option value="none"><?php esc_html_e( 'None', 'woocommerce-es' ); ?></option>
							<option value="new"><?php esc_html_e( 'NEW Products', 'woocommerce-es' ); ?></option>
							<option value="all"><?php esc_html_e( 'ALL Products', 'woocommerce-es' ); ?></option>
						</select>
						</p>
					<?php } ?>
			</div>
		</div>

		<!-- Log container with tabs -->
		<div class="conecom-log-container">
			<div class="conecom-log-tabs">
				<button class="conecom-tab-button active" data-tab="automatic">
					<span class="dashicons dashicons-clock"></span>
					<?php esc_html_e( 'Automatic Sync', 'woocommerce-es' ); ?>
				</button>
				<button class="conecom-tab-button" data-tab="manual">
					<span class="dashicons dashicons-upload"></span>
					<?php esc_html_e( 'Manual Import', 'woocommerce-es' ); ?>
				</button>
			</div>

			<div class="conecom-tab-content">
			<div class="conecom-tab-pane active" id="tab-automatic">
				<div id="conecom-as-logs-container">
					<p style="color: #666; font-style: italic; padding: 20px; text-align: center;">
						<?php esc_html_e( 'Loading...', 'woocommerce-es' ); ?>
					</p>
				</div>
			</div>
				<div class="conecom-tab-pane" id="tab-manual">
					<fieldset id="logwrapper" style="border: none; padding: 0; margin: 0;">
						<div id="loglist"></div>
					</fieldset>
				</div>
			</div>
		</div>

		<script type="text/javascript">
		function loadImportStats() {
			if ( typeof ConEcom_ajaxAction === 'undefined' || ! ConEcom_ajaxAction.has_get_all_product_skus ) {
				return;
			}
			var btn = document.getElementById('refresh_stats');
			var cards = document.querySelectorAll('.conecom-stat-card');
			if ( btn ) { btn.disabled = true; }
			if ( cards.length ) { cards.forEach(function(c){ c.classList.add('loading'); }); }

			fetch(ConEcom_ajaxAction.url, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: 'action=connect_ecommerce_get_import_stats&security=' + encodeURIComponent(ConEcom_ajaxAction.stats_nonce)
			})
			.then(function(r){ return r.json(); })
		.then(function(response) {
			if ( response.success && response.data ) {
				var d = response.data;
				var set = function(id, val) { var el = document.getElementById(id); if (el) el.textContent = (typeof val === 'number') ? val.toLocaleString() : val; };
				set('stat-available-count', d.available_count);
				set('stat-wp-count', d.wp_count);
				set('stat-import-count', d.import_count);
				set('stat-new-count', d.new_count);
				set('stat-outdated-count', d.outdated_count);
				set('stat-delete-count', d.delete_count);

				var sublabel = document.getElementById('stat-available-sublabel');
				if ( sublabel ) {
					if ( d.filter_tag && d.api_total_count !== undefined && d.api_total_count !== d.available_count ) {
						sublabel.style.display = '';
						sublabel.innerHTML = '<?php esc_html_e( 'Tag:', 'woocommerce-es' ); ?> <strong>' + d.filter_tag + '</strong>'
							+ '<br><small><?php esc_html_e( 'Total:', 'woocommerce-es' ); ?> ' + Number(d.api_total_count).toLocaleString() + '</small>';
					} else {
						sublabel.style.display = 'none';
						sublabel.innerHTML = '';
					}
				}
			}
		})
			.catch(function() {})
			.finally(function() {
				if ( btn ) { btn.disabled = false; }
				if ( cards.length ) { cards.forEach(function(c){ c.classList.remove('loading'); }); }
			});
		}
	function loadAsLogs() {
		if ( typeof ConEcom_ajaxAction === 'undefined' ) { return; }
		var container = document.getElementById('conecom-as-logs-container');
		if ( ! container ) { return; }
		container.innerHTML = '<p style="color:#666;font-style:italic;padding:20px;text-align:center;"><?php esc_html_e( 'Loading…', 'woocommerce-es' ); ?></p>';

		fetch(ConEcom_ajaxAction.url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: 'action=connect_ecommerce_get_as_logs&security=' + encodeURIComponent(ConEcom_ajaxAction.as_logs_nonce)
		})
		.then(function(r){ return r.json(); })
		.then(function(response) {
			if ( ! response.success ) {
				container.innerHTML = '<p style="color:#d63638;padding:20px;">' + ( response.data && response.data.message ? response.data.message : '<?php esc_html_e( 'Error loading logs.', 'woocommerce-es' ); ?>' ) + '</p>';
				return;
			}
			var actions = response.data;
			if ( ! actions || actions.length === 0 ) {
				container.innerHTML = '<p style="color:#666;font-style:italic;padding:20px;text-align:center;"><?php esc_html_e( 'No sync runs recorded yet.', 'woocommerce-es' ); ?></p>';
				return;
			}

			var statusLabels = {
				'complete':    '<?php esc_html_e( 'Complete', 'woocommerce-es' ); ?>',
				'failed':      '<?php esc_html_e( 'Failed', 'woocommerce-es' ); ?>',
				'pending':     '<?php esc_html_e( 'Pending', 'woocommerce-es' ); ?>',
				'in-progress': '<?php esc_html_e( 'Running', 'woocommerce-es' ); ?>',
				'canceled':    '<?php esc_html_e( 'Canceled', 'woocommerce-es' ); ?>'
			};
			var statusColors = {
				'complete':    '#00a32a',
				'failed':      '#d63638',
				'pending':     '#dba617',
				'in-progress': '#2271b1',
				'canceled':    '#787c82'
			};

			var html = '<table class="widefat striped" style="margin:0;">'
				+ '<thead><tr>'
				+ '<th style="width:160px;"><?php esc_html_e( 'Date', 'woocommerce-es' ); ?></th>'
				+ '<th style="width:100px;"><?php esc_html_e( 'Status', 'woocommerce-es' ); ?></th>'
				+ '<th style="width:130px;"><?php esc_html_e( 'Frequency', 'woocommerce-es' ); ?></th>'
				+ '<th><?php esc_html_e( 'Last log', 'woocommerce-es' ); ?></th>'
				+ '</tr></thead><tbody>';

			actions.forEach(function(action) {
				var status      = action.status || 'pending';
				var color       = statusColors[status] || '#787c82';
				var statusLabel = statusLabels[status] || status;
				var lastLog     = action.logs && action.logs.length ? action.logs[action.logs.length - 1].message : '—';
				var rowId       = 'as-log-row-' + action.id;
				var detailId    = 'as-log-detail-' + action.id;
				var hasLogs     = action.logs && action.logs.length > 1;

				html += '<tr id="' + rowId + '" style="cursor:' + ( hasLogs ? 'pointer' : 'default' ) + ';" '
					+ ( hasLogs ? 'onclick="document.getElementById(\'' + detailId + '\').style.display = document.getElementById(\'' + detailId + '\').style.display === \'none\' ? \'\' : \'none\';"' : '' )
					+ '>'
					+ '<td style="white-space:nowrap;font-size:12px;">' + action.scheduled_date + '</td>'
					+ '<td><span style="color:' + color + ';font-weight:600;">' + statusLabel + '</span></td>'
					+ '<td style="font-size:12px;">' + action.hook_label + '</td>'
					+ '<td style="font-size:12px;color:#50575e;">' + lastLog + '</td>'
					+ '</tr>';

				if ( hasLogs ) {
					html += '<tr id="' + detailId + '" style="display:none;background:#f6f7f7;">'
						+ '<td colspan="4" style="padding:8px 16px;">'
						+ '<ol style="margin:0;padding-left:20px;">';
					action.logs.forEach(function(log) {
						html += '<li style="font-size:12px;margin-bottom:2px;"><span style="color:#50575e;">[' + log.date + ']</span> ' + log.message + '</li>';
					});
					html += '</ol></td></tr>';
				}
			});

			html += '</tbody></table>';
			container.innerHTML = html;
		})
		.catch(function() {
			container.innerHTML = '<p style="color:#d63638;padding:20px;"><?php esc_html_e( 'Error loading logs.', 'woocommerce-es' ); ?></p>';
		});
	}

	document.addEventListener('DOMContentLoaded', function() {
		var tabButtons = document.querySelectorAll('.conecom-tab-button');
		var tabPanes = document.querySelectorAll('.conecom-tab-pane');
		tabButtons.forEach(function(btn) {
			btn.addEventListener('click', function() {
				var targetTab = this.getAttribute('data-tab');
				tabButtons.forEach(function(b){ b.classList.remove('active'); });
				tabPanes.forEach(function(p){ p.classList.remove('active'); });
				this.classList.add('active');
				var pane = document.getElementById('tab-' + targetTab);
				if ( pane ) pane.classList.add('active');
				if ( 'automatic' === targetTab ) { loadAsLogs(); }
			});
		});
		if ( typeof ConEcom_ajaxAction !== 'undefined' && ConEcom_ajaxAction.has_get_all_product_skus ) {
			loadImportStats();
		}
		loadAsLogs();
	});
	</script>
		<?php
	}

	/**
	 * Page get subscriptions
	 *
	 * @return void
	 */
	public function page_get_subscriptions() {
		?>
		<div id="<?php echo esc_attr( $this->options['slug'] ); ?>-engine-subscriptions">
			<input type="text" id="conwoo-wp-email">						
			<button id="wp-get-user-data" class="button button-primary">
			get wordpress user by email
			</button>
			<div id="wp-user-data">
			</div>
			<input type="text" id="conwoo-sub-id">						
			<button id="conwoo-get-subs" class="button button-primary">
			get subs
			</button>
			<div id="odoo-user-subs">
			</div>
		</div>
		<?php
	}

	/**
	 * Sanitize fiels before saves in DB
	 *
	 * @param array $input Input fields.
	 * @return array
	 */
	public function sanitize_fields_settings( $input ) {
		$sanitary_values = array();
		$imh_settings    = get_option( 'connect_ecommerce' );
		$connector       = isset( $input['connector'] ) ? $input['connector'] : '';

		$admin_settings = [
			$connector => [
				'api'                => '',
				'idcentre'           => '',
				'url'                => '',
				'username'           => '',
				'password'           => '',
				'company'            => '',
				'company_id'         => '',
				'domain'             => '',
				'dbname'             => '',
				'stock'              => 'no',
				'prodst'             => 'draft',
				'virtual'            => 'no',
				'backorders'         => 'no',
				'catsep'             => '',
				'catattr'            => '',
				'filter'             => '',
				'pricesale_discount' => '',
				'filter_sku'         => '',
				'tax_option'         => 'no',
				'rates'              => 'default',
				'catnp'              => 'yes',
				'doctype'            => 'invoice',
				'cleanchars'         => '',
				'approve_document'   => 'no',
			'manufacturer_code'  => '',
			'customer_code'      => '',
				'series'             => '',
				'freeorder'          => 'no',
				'ecstatus'           => 'all',
				'order_tags'         => '',
				'design_id'          => '',
				'sync'               => 'no',
				'prod_weight_eq'     => '',
				'debug_log'          => 'no',
			],
		];

		foreach ( $admin_settings[ $connector ] as $setting => $default_value ) {
			if ( isset( $input[ $connector ][ $setting ] ) ) {
				$sanitary_values[ $connector ][ $setting ] = sanitize_text_field( $input[ $connector ][ $setting ] );
			} elseif ( isset( $imh_settings[ $connector ][ $setting ] ) ) {
				$sanitary_values[ $connector ][ $setting ] = $imh_settings[ $connector ][ $setting ];
			} else {
				$sanitary_values[ $connector ][ $setting ] = $default_value;
			}
		}
		$sanitary_values['connector'] = $connector;

		return $sanitary_values;
	}

	/**
	 * Info for holded automate section.
	 *
	 * @return void
	 */
	public function connect_woocommerce_section_info() {
		$arr = array(
			'a' => array(
				'href'   => array(),
				'target' => array(),
			),
		);
		echo wp_kses( $this->options['settings_admin_message'], $arr );
	}

	/**
	 * Connector type
	 *
	 * @return void
	 */
	public function connector_callback() {
		$connector = isset( $this->connector ) ? $this->connector : '';
		?>
		<select name="connect_ecommerce[connector]" id="wcpimh_connector" onchange="this.form.submit();">
			<option value="" <?php selected( $connector, '' ); ?>><?php esc_html_e( 'Select the ERP/CRM that you wish to connect', 'woocommerce-es' ); ?></option>
			<?php
			foreach ( $this->all_options as $key => $option ) {
				?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $connector, $key ); ?>><?php echo esc_html( $option['name'] ); ?></option>
				<?php
			}
			?>
		</select>
		<?php
	}

	/**
	 * NEO ID Centre
	 *
	 * @return void
	 */
	public function idcentre_callback() {
		printf(
			'<input class="regular-text" type="text" name="connect_ecommerce[' . esc_html( $this->connector ) . '][idcentre]" id="wcpimh_idcentre" value="%s">',
			isset( $this->settings['idcentre'] ) ? esc_attr( $this->settings['idcentre'] ) : ''
		);
	}

	/**
	 * URL input
	 *
	 * @return void
	 */
	public function url_callback() {
		printf(
			'<input class="regular-text" type="url" name="connect_ecommerce[' . esc_html( $this->connector ) . '][url]" id="wcpimh_url" value="%s">',
			isset( $this->settings['url'] ) ? esc_attr( $this->settings['url'] ) : ''
		);
	}

	/**
	 * Username input
	 *
	 * @return void
	 */
	public function username_callback() {
		printf(
			'<input class="regular-text" type="text" name="connect_ecommerce[' . esc_html( $this->connector ) . '][username]" id="wcpimh_username" value="%s">',
			isset( $this->settings['username'] ) ? esc_attr( $this->settings['username'] ) : ''
		);
	}

	/**
	 * DB Name input
	 *
	 * @return void
	 */
	public function dbname_callback() {
		printf(
			'<input class="regular-text" type="text" name="connect_ecommerce[' . esc_html( $this->connector ) . '][dbname]" id="wcpimh_dbname" value="%s">',
			isset( $this->settings['dbname'] ) ? esc_attr( $this->settings['dbname'] ) : ''
		);
	}

	/**
	 * Password input
	 *
	 * @return void
	 */
	public function password_callback() {
		printf(
			'<input class="regular-text" type="password" name="connect_ecommerce[' . esc_html( $this->connector ) . '][password]" id="wcpimh_password" value="%s">',
			isset( $this->settings['password'] ) ? esc_attr( $this->settings['password'] ) : ''
		);
	}

	/**
	 * Domain input
	 *
	 * @return void
	 */
	public function domain_callback() {
		printf(
			'<input class="regular-text" type="text" name="connect_ecommerce[' . esc_html( $this->connector ) . '][domain]" id="wcpimh_domain" value="%s">',
			isset( $this->settings['domain'] ) ? esc_attr( $this->settings['domain'] ) : ''
		);
	}

	/**
	 * Password input
	 *
	 * @return void
	 */
	public function company_callback() {
		printf(
			'<input class="regular-text" type="text" name="connect_ecommerce[' . esc_html( $this->connector ) . '][company]" id="wcpimh_company" value="%s">',
			isset( $this->settings['company'] ) ? esc_attr( $this->settings['company'] ) : ''
		);
	}

	/**
	 * Get companies and select them
	 *
	 * @return void
	 */
	public function company_select_callback() {
		if ( empty( $this->connapi_erp ) || ! method_exists( $this->connapi_erp, 'get_companies' ) ) {
			echo '<p>' . esc_html__( 'By default', 'woocommerce-es' ) . '</p>';
			return;
		}
		$result_companies = $this->connapi_erp->get_companies();
		if ( empty( $result_companies ) || 'error' === $result_companies['status'] || empty( $result_companies['data'] ) ) {
			$message = ! empty( $result_companies['message'] ) ? $result_companies['message'] : '';
			$message = empty( $message ) && ! empty( $result_companies['data'] ) ? $result_companies['data'] : $message;
			$message = empty( $message ) ? esc_html__( 'Error getting companies', 'woocommerce-es' ) : $message;
			echo '<p>' . esc_html__( 'Error', 'woocommerce-es' ) . ': ' . esc_html( $message ) . '</p>';
			return;
		}
		$saved_attr = isset( $this->settings['company_id'] ) ? $this->settings['company_id'] : '';
		?>
		<select name="connect_ecommerce[<?php echo esc_html( $this->connector ); ?>][company_id]" id="wcpimh_company_id">
			<?php
			foreach ( $result_companies['data'] as $value => $label ) {
				echo '<option value="' . esc_html( $value ) . '" ';
				selected( $value, $saved_attr );
				echo '>' . esc_html( $label ) . '</option>';
			}
			?>
		</select>
		<?php
	}

	/**
	 * API input
	 * API field
	 *
	 * @return void
	 */
	public function api_callback() {
		printf(
			'<input class="regular-text" type="password" name="connect_ecommerce[' . esc_html( $this->connector ) . '][api]" id="wcpimh_api" value="%s">',
			isset( $this->settings['api'] ) ? esc_attr( $this->settings['api'] ) : ''
		);
	}

	/**
	 * Stock field
	 *
	 * @return void
	 */
	public function stock_callback() {
		$stock_option = isset( $this->settings['stock'] ) ? $this->settings['stock'] : 'no';
		?>
		<select name="connect_ecommerce[<?php echo esc_html( $this->connector ); ?>][stock]" id="wcpimh_stock">
			<option value="yes" <?php selected( $stock_option, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woocommerce-es' ); ?></option>
			<option value="no" <?php selected( $stock_option, 'no' ); ?>><?php esc_html_e( 'No', 'woocommerce-es' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Product status
	 *
	 * @return void
	 */
	public function prodst_callback() {
		$product_status = isset( $this->settings['prodst'] ) ? $this->settings['prodst'] : 'draft';
		?>
		<select name="connect_ecommerce[<?php echo esc_html( $this->connector ); ?>][prodst]" id="wcpimh_prodst">
			<option value="draft" <?php selected( $product_status, 'draft' ); ?>><?php esc_html_e( 'Draft', 'woocommerce-es' ); ?></option>
			<option value="publish" <?php selected( $product_status, 'publish' ); ?>><?php esc_html_e( 'Publish', 'woocommerce-es' ); ?></option>
			<option value="pending" <?php selected( $product_status, 'pending' ); ?>><?php esc_html_e( 'Pending', 'woocommerce-es' ); ?></option>
			<option value="private" <?php selected( $product_status, 'private' ); ?>><?php esc_html_e( 'Private', 'woocommerce-es' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Virtual products
	 *
	 * @return void
	 */
	public function virtual_callback() {
		$virtual_option = isset( $this->settings['virtual'] ) ? $this->settings['virtual'] : 'no';
		?>
		<select name="connect_ecommerce[<?php echo esc_html( $this->connector ); ?>][virtual]" id="wcpimh_virtual">
			<option value="no" <?php selected( $virtual_option, 'no' ); ?>><?php esc_html_e( 'No', 'woocommerce-es' ); ?></option>
			<option value="yes" <?php selected( $virtual_option, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woocommerce-es' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Back orders
	 *
	 * @return void
	 */
	public function backorders_callback() {
		$backorders = isset( $this->settings['backorders'] ) ? $this->settings['backorders'] : 'no';
		?>
		<select name="connect_ecommerce[<?php echo esc_html( $this->connector ); ?>][backorders]" id="wcpimh_backorders">
			<option value="no" <?php selected( $backorders, 'no' ); ?>><?php esc_html_e( 'No', 'woocommerce-es' ); ?></option>
			<option value="yes" <?php selected( $backorders, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woocommerce-es' ); ?></option>
			<option value="notify" <?php selected( $backorders, 'notify' ); ?>><?php esc_html_e( 'Notify', 'woocommerce-es' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Call back for category separation
	 *
	 * @return void
	 */
	public function catsep_callback() {
		$prod_category_fixed = ! empty( $this->options['product_category_fixed'] ) ? $this->options['product_category_fixed'] : '';
		if ( ! empty( $prod_category_fixed ) ) {
			$this->settings['catsep'] = $this->options['product_category_fixed'];
		}
		printf(
			'<input class="regular-text" type="text" name="connect_ecommerce[%s][catsep]" id="wcpimh_catsep" value="%s" %s>',
			esc_html( $this->connector ),
			isset( $this->settings['catsep'] ) ? esc_attr( $this->settings['catsep'] ) : '',
			! empty( $prod_category_fixed ) ? ' readonly' : ''
		);
	}

	/**
	 * Get categories to use as attributes
	 *
	 * @return void
	 */
	public function catattr_callback() {
		$catattr_options = $this->connapi_erp->get_attributes();
		if ( empty( $catattr_options ) ) {
			return;
		}
		$saved_attr = isset( $this->settings['catattr'] ) ? $this->settings['catattr'] : '';
		?>
		<select name="connect_ecommerce[<?php echo esc_html( $this->connector ); ?>][catattr]" id="wcpimh_catattr">
			<?php
			foreach ( $catattr_options as $value => $label ) {
				echo '<option value="' . esc_html( $value ) . '" ';
				selected( $value, $saved_attr );
				echo '>' . esc_html( $label ) . '</option>';
			}
			?>
		</select>
		<?php
	}

	/**
	 * Filter products
	 *
	 * @return void
	 */
	public function filter_callback() {
		printf(
			'<input class="regular-text" type="text" name="connect_ecommerce[' . esc_html( $this->connector ) . '][filter]" id="wcpimh_filter" value="%s">',
			isset( $this->settings['filter'] ) ? esc_attr( $this->settings['filter'] ) : ''
		);
	}

	/**
	 * Filter products
	 *
	 * @return void
	 */
	public function filter_sku_callback() {
		printf(
			'<input class="regular-text" type="text" name="connect_ecommerce[' . esc_html( $this->connector ) . '][filter_sku]" id="wcpimh_filter_sku" value="%s">',
			isset( $this->settings['filter_sku'] ) ? esc_attr( $this->settings['filter_sku'] ) : ''
		);
	}

	/**
	 * Tax option
	 *
	 * @return void
	 */
	public function tax_option_callback() {
		$tax_price = isset( $this->settings['tax'] ) ? $this->settings['tax'] : 'no';
		?>
		<select name="connect_ecommerce[<?php echo esc_html( $this->connector ); ?>][tax_price]" id="wcsen_tax">
			<option value="yes" <?php selected( $tax_price, 'yes' ); ?>><?php esc_html_e( 'Yes, tax included', 'woocommerce-es' ); ?></option>
			<option value="no" <?php selected( $tax_price, 'no' ); ?>><?php esc_html_e( 'No, tax not included', 'woocommerce-es' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Percentage price discount
	 *
	 * @return void
	 */
	public function pricesale_discount_option_callback() {
		printf(
			'<input class="regular-text" type="text" name="connect_ecommerce[' . esc_html( $this->connector ) . '][pricesale_discount]" id="wcpimh_pricesale_discount" value="%s" style="width:60px">%%',
			isset( $this->settings['pricesale_discount'] ) ? esc_attr( $this->settings['pricesale_discount'] ) : ''
		);
	}

	/**
	 * Rates option from API
	 *
	 * @return void
	 */
	public function rates_callback() {
		$rates_options = $this->connapi_erp->get_rates();
		if ( empty( $rates_options ) ) {
			return;
		}
		?>
		<select name="connect_ecommerce[<?php echo esc_html( $this->connector ); ?>][rates]" id="wcpimh_rates">
			<?php
			foreach ( $rates_options as $value => $label ) {
				echo '<option value="' . esc_html( $value ) . '" ';
				selected( $value, $this->settings['rates'] );
				echo '>' . esc_html( $label ) . '</option>';
			}
			?>
		</select>
		<?php
	}

	/**
	 * Rates option from API
	 *
	 * @return void
	 */
	public function serie_number_callback() {
		$type = ! empty( $this->settings['doctype'] ) ? $this->settings['doctype'] : 'invoice';
		$series_options = $this->connapi_erp->get_series_number( $type );
		if ( empty( $series_options ) ) {
			return;
		}
		?>
		<select name="connect_ecommerce[<?php echo esc_html( $this->connector ); ?>][series]" id="wcpimh_series">
			<?php
			foreach ( $series_options as $value => $label ) {
				echo '<option value="' . esc_html( $value ) . '" ';
				if ( ! empty( $this->settings['series'] ) ) {
					selected( $value, $this->settings['series'] );
				}
				echo '>' . esc_html( $label ) . '</option>';
			}
			?>
		</select>
		<?php
	}

	/**
	 * Category for new products
	 *
	 * @return void
	 */
	public function catnp_callback() {
		$categorynp = isset( $this->settings['catnp'] ) ? $this->settings['catnp'] : 'yes';
		?>
		<select name="connect_ecommerce[<?php echo esc_html( $this->connector ); ?>][catnp]" id="wcpimh_catnp">
			<option value="yes" <?php selected( $categorynp, 'yes' ); ?>><?php esc_html_e( 'Yes, it will import ONLY on new products', 'woocommerce-es' ); ?></option>		<option value="no" <?php selected( $categorynp, 'no' ); ?>><?php esc_html_e( 'No, it will import in ALL products', 'woocommerce-es' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Call back for clean special characters
	 *
	 * @return void
	 */
	public function cleanchars_callback() {
		$cleanchars = isset( $this->settings['cleanchars'] ) ? $this->settings['cleanchars'] : 'no';
		echo '<input type="checkbox" id="connwoo_cleanchars_checkbox" name="connect_ecommerce[' . esc_html( $this->connector ) . '][cleanchars]" value="on"';
		echo checked( $cleanchars, 'on' );
		echo '/>';
		echo '<label for="connwoo_cleanchars_checkbox" class="description">';
		esc_html_e( 'Clean special characters from firstname, lastname and company name', 'woocommerce-es' );
		echo '</label>';
	}

	/**
	 * Call back for approve document
	 *
	 * @return void
	 */
	public function approve_document_callback() {
		$approve_document = isset( $this->settings['approve_document'] ) ? $this->settings['approve_document'] : 'no';
		?>
		<select name="connect_ecommerce[<?php echo esc_html( $this->connector ); ?>][approve_document]" id="wcpimh_approve_document">
			<option value="no" <?php selected( $approve_document, 'no' ); ?>><?php esc_html_e( 'No', 'woocommerce-es' ); ?></option>
			<option value="yes" <?php selected( $approve_document, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woocommerce-es' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Manufacturer Code field
	 *
	 * @return void
	 */
	public function manufacturer_code_callback() {
		printf(
			'<input class="regular-text" type="text" name="connect_ecommerce[%s][manufacturer_code]" id="wcpimh_manufacturer_code" value="%s">',
			esc_html( $this->connector ),
			isset( $this->settings['manufacturer_code'] ) ? esc_attr( $this->settings['manufacturer_code'] ) : ''
		);
	}

	/**
	 * Customer Code field
	 *
	 * @return void
	 */
	public function customer_code_callback() {
		printf(
			'<input class="regular-text" type="text" name="connect_ecommerce[%s][customer_code]" id="wcpimh_customer_code" value="%s">',
			esc_html( $this->connector ),
			isset( $this->settings['customer_code'] ) ? esc_attr( $this->settings['customer_code'] ) : ''
		);
	}

	/**
	 * Document type
	 *
	 * @return void
	 */
	public function doctype_callback() {
		$documents_type = array(
			'nosync'       => __( 'Not sync', 'woocommerce-es' ),
			'smart'        => __( 'Smart', 'woocommerce-es' ),
			'invoice'      => __( 'Invoice', 'woocommerce-es' ),
			'salesreceipt' => __( 'Sales receipt', 'woocommerce-es' ),
			'salesorder'   => __( 'Sales order', 'woocommerce-es' ),
			'waybill'      => __( 'Waybill', 'woocommerce-es' ),
		);
		$doctype = isset( $this->settings['doctype'] ) ? $this->settings['doctype'] : 'invoice';
		?>
		<select name="connect_ecommerce[<?php echo esc_html( $this->connector ); ?>][doctype]" id="wcpimh_doctype">
			<?php
			foreach ( $documents_type as $value => $label ) {
				echo '<option value="' . esc_html( $value ) . '" ';
				selected( $value, $doctype );
				echo '>' . esc_html( $label ) . '</option>';
			}
			?>
		</select>
		<?php
	}

	/**
	 * Freeorder option to send API
	 *
	 * @return void
	 */
	public function freeorder_callback() {
		$freeorder = isset( $this->settings['freeorder'] ) ? $this->settings['freeorder'] : 'no';
		?>
		<select name="connect_ecommerce[<?php echo esc_html( $this->connector ); ?>][freeorder]" id="wcpimh_freeorder">
			<option value="no" <?php selected( $freeorder, 'no' ); ?>><?php esc_html_e( 'No', 'woocommerce-es' ); ?></option>		<option value="yes" <?php selected( $freeorder, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woocommerce-es' ); ?></option>

			</select>
		<?php
	}

	/**
	 * Send API depending order status
	 *
	 * @return void
	 */
	public function ecstatus_callback() {
		$ecstatus = isset( $this->settings['ecstatus'] ) ? $this->settings['ecstatus'] : 'all';
		?>
		<select name="connect_ecommerce[<?php echo esc_html( $this->connector ); ?>][ecstatus]" id="wcpimh_ecstatus">
			<option value="all" <?php selected( $ecstatus, 'all' ); ?>><?php esc_html_e( 'All status orders', 'woocommerce-es' ); ?></option>

			<option value="paid" <?php selected( $ecstatus, 'paid' ); ?>><?php esc_html_e( 'Paid orders', 'woocommerce-es' ); ?></option>

			<option value="completed" <?php selected( $ecstatus, 'completed' ); ?>><?php esc_html_e( 'Only Completed', 'woocommerce-es' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Filter products
	 *
	 * @return void
	 */
	public function order_tags_callback() {
		printf(
			'<input class="regular-text" type="text" name="connect_ecommerce[' . esc_html( $this->connector ) . '][order_tags]" id="wcpimh_order_tags" value="%s">',
			isset( $this->settings['order_tags'] ) ? esc_attr( $this->settings['order_tags'] ) : ''
		);
	}

	/**
	 * Callback Billing nif key
	 *
	 * @return void
	 */
	public function wcpimh_design_id_callback() {
		printf(
			'<input class="regular-text" type="text" name="connect_ecommerce[' . esc_html( $this->connector ) . '][design_id]" id="wcpimh_design_id" value="%s">',
			isset( $this->settings['design_id'] ) ? esc_attr( $this->settings['design_id'] ) : ''
		);
	}

	/**
	 * Callback Billing nif key
	 *
	 * @return void
	 */
	public function product_weight_equivalence_callback() {
		$attribute_fields = $this->connapi_erp->get_product_attributes();
		if ( empty( $attribute_fields ) ) {
			return;
		}
		?>
		<select name="connect_ecommerce[<?php echo esc_html( $this->connector ); ?>][prod_weight_eq]" id="wcpimh_prod_weight_eq">
			<?php
			echo '<option value="">' . esc_html__( 'No', 'woocommerce-es' ) . '</option>';
			foreach ( $attribute_fields as $value => $label ) {
				echo '<option value="' . esc_html( $value ) . '" ';
				selected( $value, $this->settings['prod_weight_eq'] );
				echo '>' . esc_html( $label ) . '</option>';
			}
			?>
		</select>
		<?php
	}

	/**
	 * Callback Billing nif key
	 *
	 * @return void
	 */
	public function debug_log_callback() {
		$debug_log = isset( $this->settings['debug_log'] ) ? $this->settings['debug_log'] : 'no';
		echo '<input type="checkbox" id="connwoo_debug_log_checkbox" name="connect_ecommerce[' . esc_html( $this->connector ) . '][debug_log]" value="on"';
		echo checked( $debug_log, 'on' );
		echo '/>';
		echo '<label for="connwoo_debug_log_checkbox" class="description">';
		esc_html_e( 'Activates debug mode to save logs.', 'woocommerce-es' );
		echo '</label>';
	}

	/**
	 * API Status callback
	 *
	 * @return void
	 */
	public function api_status_callback() {
		if ( empty( $this->connapi_erp ) ) {
			echo '<span style="color: #666;">' . esc_html__( 'No connector configured.', 'woocommerce-es' ) . '</span>';
			return;
		}

		// Test the API connection using check_can_sync which uses login internally.
		$login_api = $this->connapi_erp->check_can_sync();

		if ( is_array( $login_api ) ) {
			$message  = $login_api['message'] ?? __( 'Connection successful', 'woocommerce-es' );
			$can_sync = 'ok' === ( $login_api['status'] ?? '' ) ? true : false;

			if ( $can_sync ) {
				echo '<span style="color: green; font-weight: bold;">✓ ' . esc_html( $message ) . '</span>';
			} else {
				$message = $login_api['message'] ?? __( 'Connection failed. Please check your credentials.', 'woocommerce-es' );
				echo '<span style="color: red; font-weight: bold;">✗ ' . esc_html( $message ) . '</span>';
			}
		} elseif ( true === $login_api ) {
			echo '<span style="color: green; font-weight: bold;">✓ ' . esc_html__( 'Connection successful! Credentials are valid.', 'woocommerce-es' ) . '</span>';
		} else {
			echo '<span style="color: red; font-weight: bold;">✗ ' . esc_html__( 'Connection failed. Please check your credentials.', 'woocommerce-es' ) . '</span>';
		}
	}

	/**
	 * Callback sync field.
	 *
	 * @return void
	 */
	public function sync_callback() {
		$sync = isset( $this->settings['sync'] ) ? $this->settings['sync'] : 'no';
		?>
		<select name="connect_ecommerce[<?php echo esc_html( $this->connector ); ?>][sync]" id="wcpimh_sync">
			<option value="no" <?php selected( $sync, 'no' ); ?>><?php esc_html_e( 'No', 'woocommerce-es' ); ?></option>
			<?php
			$periods = CRON::get_cron_periods();
			if ( ! empty( $periods ) ) {
				foreach ( $periods as $period ) {
					echo '<option value="' . esc_html( $period['cron'] ) . '" ';
					selected( $sync, $period['cron'] );
					echo '>' . esc_html( $period['display'] ) . '</option>';
				}
			}
			?>
		</select>
		<?php
	}

	/**
	 * ## Merge vars
	 * --------------------------- */

	/**
	 * Sanitize fiels before saves in DB
	 *
	 * @param array $input Input fields.
	 * @return array
	 */
	public function sanitize_fields_prod_mergevars( $input ) {
		$sanitary_values = array();

		if ( ! isset( $input['prod_mergevars'] ) ) {
			return $sanitary_values;
		}

		foreach ( $input['prod_mergevars'] as $mergevar ) {
			if ( isset( $mergevar['attrprod'] ) && ! empty( $mergevar['custom_field'] ) ) {
				if ( false === strpos( $mergevar['custom_field'], '|' ) ) {
					$mergevar['custom_field'] = 'cf|' . $mergevar['custom_field'];
				}
				$sanitary_values['prod_mergevars'][ $mergevar['attrprod'] ] = sanitize_text_field( $mergevar['custom_field'] );
			}
		}

		return $sanitary_values;
	}

	/**
	 * Info for holded automate section.
	 *
	 * @return void
	 */
	public function section_info_prod_mergevars() {
		esc_html_e( 'Please select the following settings in order customize your eCommerce. ', 'woocommerce-es' );
	}

	/**
	 * Page get Merge Product variables
	 *
	 * @return void
	 */
	public function prod_mergevars_callback() {
		$product_fields      = PROD::get_all_product_fields();
		$custom_fields       = PROD::get_all_custom_fields();
		$custom_taxonomies   = TAX::get_all_custom_taxonomies();
		$product_cat_terms   = TAX::get_terms_product_cat();
		$attribute_fields    = $this->connapi_erp->get_product_attributes();

		$settings_mergevars = ! empty( $this->settings_prod_mergevars['prod_mergevars'] ) ? $this->settings_prod_mergevars['prod_mergevars'] : array();

		$saved_attr = array();
		foreach ( $settings_mergevars as $key => $value ) {
			$saved_attr[] = array(
				'attrprod'     => $key,
				'custom_field' => $value,
			);
		}
		?>
		<div id="<?php echo esc_attr( $this->options['slug'] ); ?>-products-mergevars" class="repeater-section">
			<div class="wrap">
				<div class="product-mergevars">
					<div class="save-item"><strong><?php esc_html_e( 'Field from ', 'woocommerce-es' ); echo ' ' . esc_html( $this->options['name'] ); ?></strong></div>
					<div></div>
					<div class="save-item"><strong><?php esc_html_e( 'WooCommerce Field', 'woocommerce-es' );?></strong></div>
				</div>
				<?php
				$size = ! empty( $settings_mergevars ) ? count( $settings_mergevars ) : 0;
				for ( $idx = 0, $size; $idx <= $size; ++$idx ) {
					$attrprod       = isset( $saved_attr[ $idx ]['attrprod'] ) ? $saved_attr[ $idx ]['attrprod'] : '';
					$attrprod_label = '';
					?>
					<div class="product-mergevars repeating" style="border: 1px solid #ccc; padding: 10px; margin-bottom: 10px;">
						<div class="save-item">
							<select name='connect_ecommerce_prod_mergevars[prod_mergevars][<?php echo esc_html( $idx ); ?>][attrprod]' class="attrprod-publish" data-row="<?php echo esc_html( $idx ); ?>">
								<option value=''></option>
								<?php
								foreach ( $attribute_fields as $attribute ) {
									if ( empty( $attribute['elements'] ) ) {
										continue;
									}
									?>
									<optgroup label="<?php echo esc_html( $attribute['name'] ); ?>">
										<?php
										foreach ( $attribute['elements'] as $value ) {
											$option_id = $attribute['id'] . '|' . $value;
											echo '<option value="' . esc_html( $option_id ) . '" ';
											selected( $option_id, $attrprod );
											echo '>' . esc_html( $value ) . '</option>';

											if ( $option_id === $attrprod ) {
												$attrprod_label = $attribute['name'];
											}
										}
										?>
									</optgroup>
									<?php
								}
								?>
							</select>
						</div>
						<span class="attrprod-label">
							<?php
							if ( ! empty( $attrprod_label ) ) {
								echo esc_html( $attrprod_label );
							}
							?>
						</span>
						<span class="dashicons dashicons-arrow-right-alt2"></span>
						<div class="save-item">
							<?php
							$saved_custom_field = isset( $saved_attr[ $idx ]['custom_field'] ) ? $saved_attr[ $idx ]['custom_field'] : '';
							$all_fields = array_merge( $product_fields, $product_cat_terms, $custom_taxonomies, $custom_fields );
							if ( ! array_key_exists( $saved_custom_field, $all_fields ) ) {
								$custom_fields[] = $saved_custom_field;
							}
							?>
							<select name='connect_ecommerce_prod_mergevars[prod_mergevars][<?php echo esc_html( $idx ); ?>][custom_field]' class="source-cf" onchange="chargeother(this)">
								<option value=''></option>
								<optgroup label="<?php esc_html_e( 'Product Fields', 'woocommerce-es' ); ?>">
									<?php
									foreach ( $product_fields as $key => $value ) {
										echo '<option value="' . esc_html( $key ) . '" ';
										selected( $key, $saved_custom_field );
										echo '>' . esc_html( $value ) . '</option>';
									}
									?>
								</optgroup>
								<optgroup label="<?php esc_html_e( 'Product Category values', 'woocommerce-es' ); ?>">
									<?php
									foreach ( $product_cat_terms as $key => $value ) {
										echo '<option value="' . esc_attr( $key ) . '" ';
										selected( $key, $saved_custom_field );
										echo '>' . esc_html( $value ) . '</option>';
									}
									?>
								</optgroup>
								<optgroup label="<?php esc_html_e( 'Taxonomy Fields', 'woocommerce-es' ); ?>">
									<?php
									foreach ( $custom_taxonomies as $key => $value ) {
										echo '<option value="' . esc_html( $key ) . '" ';
										selected( $key, $saved_custom_field );
										echo '>' . esc_html( $value ) . '</option>';
									}
									?>
								</optgroup>
								<optgroup label="<?php esc_html_e( 'Custom Fields', 'woocommerce-es' ); ?>">
								<?php
								foreach ( $custom_fields as $key => $value ) {
									$key = empty( $key ) ? $value : $key;
									echo '<option value="' . esc_html( $key ) . '" ';
									selected( $key, $saved_custom_field );
									echo '>' . esc_html( $value ) . '</option>';
								}
								echo '<option value="custom">' . esc_html__( 'Customized', 'woocommerce-es' ) . '</option>';
								?>
								</optgroup>
							</select>
						</div>
						<div class="save-item">
							<a href="#" class="button alt remove"><span class="dashicons dashicons-remove"></span><?php esc_html_e( 'Remove', 'woocommerce-es' ); ?></a>
						</div>
					</div>
					<?php
				}
				?>
				<a href="#" class="button repeat"><span class="dashicons dashicons-insert"></span><?php esc_html_e( 'Add Another', 'woocommerce-es' ); ?></a>
			</div>
		</div>
		<?php
	}

	/**
	 * ## AI
	 * --------------------------- */

	/**
	 * Sanitize fiels before saves in DB
	 *
	 * @param array $input Input fields.
	 * @return array
	 */
	public function sanitize_fields_ai( $input ) {
		$sanitary_values          = array();
		$sanitary_values['model'] = isset( $input['model'] ) ? sanitize_text_field( $input['model'] ) : '';
		$sanitary_values['prompt'] = isset( $input['prompt'] ) ? sanitize_textarea_field( $input['prompt'] ) : '';
		return $sanitary_values;
	}

	/**
	 * Info for AI section.
	 *
	 * @return void
	 */
	public function section_info_ai() {
		if ( ! AI::has_wp_ai() ) {
			printf(
				'<div class="notice notice-error inline"><p>%s</p></div>',
				esc_html__( 'WordPress AI is not available. Please upgrade to WordPress 7.0 or later and ensure AI support is enabled in your environment.', 'woocommerce-es' )
			);
		}
	}

	/**
	 * Model selection field.
	 *
	 * @return void
	 */
	public function ai_model_callback() {
		$model  = isset( $this->settings_ai['model'] ) ? $this->settings_ai['model'] : '';
		$groups = AI::get_available_models();
		?>
		<select name="connect_ecommerce_ai[model]" id="connect_ecommerce_ai_model">
			<option value=""><?php esc_html_e( '— Auto (best available) —', 'woocommerce-es' ); ?></option>
			<?php foreach ( $groups as $provider_label => $models ) : ?>
				<optgroup label="<?php echo esc_attr( $provider_label ); ?>">
					<?php foreach ( $models as $option ) : ?>
						<option value="<?php echo esc_attr( $option['value'] ); ?>" <?php selected( $model, $option['value'] ); ?>>
							<?php echo esc_html( $option['label'] ); ?>
						</option>
					<?php endforeach; ?>
				</optgroup>
			<?php endforeach; ?>
			<?php if ( empty( $groups ) && '' !== $model ) : ?>
				<option value="<?php echo esc_attr( $model ); ?>" selected="selected"><?php echo esc_html( $model ); ?></option>
			<?php endif; ?>
		</select>
		<p class="description"><?php esc_html_e( 'Models are loaded from your active WordPress AI connectors.', 'woocommerce-es' ); ?></p>
		<?php
	}

	/**
	 * Callback sync field.
	 *
	 * @return void
	 */
	public function ai_prompt_callback() {
		$prompt = isset( $this->settings_ai['prompt'] ) ? $this->settings_ai['prompt'] : __( 'Here is the information about a product. I need you to write a description for an online store, highlighting the main features. Don\'t use prices in the description.', 'woocommerce-es' );
		?>
		<textarea class="regular-text" rows="5" style="width: 100%;" name="connect_ecommerce_ai[prompt]" id="wcpimh_prompt"><?php echo esc_textarea( $prompt ); ?></textarea>
		<p><?php esc_html_e( 'After prompt, we add the format to retrieve the contact', 'woocommerce-es' ); ?></p>
		<?php
	}

	/**
	 * ## Alerts
	 * --------------------------- */

	/**
	 * Section info for alerts
	 *
	 * @return void
	 */
	public function section_info_alerts() {
		?>
		<p><?php esc_html_e( 'Configure how you want to receive alerts when there are errors in order submissions or product imports.', 'woocommerce-es' ); ?></p>
		<?php
	}

	/**
	 * Alert enabled callback
	 *
	 * @return void
	 */
	public function alert_enabled_callback() {
		$settings = get_option( 'connect_ecommerce_alerts' );
		$enabled  = isset( $settings['alert_enabled'] ) ? $settings['alert_enabled'] : 'no';
		?>
		<select name="connect_ecommerce_alerts[alert_enabled]" id="alert_enabled">
			<option value="no" <?php selected( $enabled, 'no' ); ?>><?php esc_html_e( 'No', 'woocommerce-es' ); ?></option>
			<option value="yes" <?php selected( $enabled, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woocommerce-es' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Alert product synced callback.
	 *
	 * @return void
	 */
	public function alert_product_synced_callback() {
		$settings = get_option( 'connect_ecommerce_alerts' );
		$value    = isset( $settings['alert_product_synced'] ) ? $settings['alert_product_synced'] : 'no';
		?>
		<select name="connect_ecommerce_alerts[alert_product_synced]" id="alert_product_synced">
			<option value="no" <?php selected( $value, 'no' ); ?>><?php esc_html_e( 'No', 'woocommerce-es' ); ?></option>
			<option value="yes" <?php selected( $value, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woocommerce-es' ); ?></option>
		</select>
		<p class="description"><?php esc_html_e( 'Receive an email when all products have been synced.', 'woocommerce-es' ); ?></p>
		<?php
	}

	/**
	 * Alert method callback
	 *
	 * @return void
	 */
	public function alert_method_callback() {
		$settings = get_option( 'connect_ecommerce_alerts' );
		$method   = isset( $settings['alert_method'] ) ? $settings['alert_method'] : 'email';
		?>
		<select name="connect_ecommerce_alerts[alert_method]" id="alert_method">
			<option value="email" <?php selected( $method, 'email' ); ?>><?php esc_html_e( 'Email', 'woocommerce-es' ); ?></option>
			<option value="slack" <?php selected( $method, 'slack' ); ?>><?php esc_html_e( 'Slack', 'woocommerce-es' ); ?></option>
		</select>
		<p class="description"><?php esc_html_e( 'Choose how you want to receive alert notifications.', 'woocommerce-es' ); ?></p>
		<?php
	}

	/**
	 * Alert email callback
	 *
	 * @return void
	 */
	public function alert_email_callback() {
		$settings = get_option( 'connect_ecommerce_alerts' );
		$email    = isset( $settings['alert_email'] ) ? $settings['alert_email'] : get_option( 'admin_email' );
		?>
		<input type="email" class="regular-text" name="connect_ecommerce_alerts[alert_email]" id="alert_email" value="<?php echo esc_attr( $email ); ?>">
		<p class="description"><?php esc_html_e( 'Email address to receive alert notifications. Leave empty to use admin email.', 'woocommerce-es' ); ?></p>
		<?php
	}

	/**
	 * Slack webhook callback
	 *
	 * @return void
	 */
	public function slack_webhook_callback() {
		$settings    = get_option( 'connect_ecommerce_alerts' );
		$webhook_url = isset( $settings['slack_webhook'] ) ? $settings['slack_webhook'] : '';
		?>
		<input type="url" class="regular-text" name="connect_ecommerce_alerts[slack_webhook]" id="slack_webhook" value="<?php echo esc_attr( $webhook_url ); ?>" placeholder="https://hooks.slack.com/services/...">
		<p class="description">
			<?php esc_html_e( 'Enter your Slack webhook URL to receive notifications in Slack.', 'woocommerce-es' ); ?>
			<br>
			<?php
			printf(
				// translators: %s is URL to Slack documentation.
				esc_html__( 'Learn how to create a webhook: %s', 'woocommerce-es' ),
				'<a href="https://api.slack.com/messaging/webhooks" target="_blank">https://api.slack.com/messaging/webhooks</a>'
			);
			?>
		</p>
		<?php
	}

	/**
	 * Sanitize alerts fields
	 *
	 * @param array $input Input fields.
	 * @return array
	 */
	public function sanitize_fields_alerts( $input ) {
		$sanitary_values = array();

		if ( isset( $input['alert_enabled'] ) ) {
			$sanitary_values['alert_enabled'] = sanitize_text_field( $input['alert_enabled'] );
		}

		if ( isset( $input['alert_product_synced'] ) ) {
			$sanitary_values['alert_product_synced'] = sanitize_text_field( $input['alert_product_synced'] );
		}

		if ( isset( $input['alert_method'] ) ) {
			$sanitary_values['alert_method'] = sanitize_text_field( $input['alert_method'] );
		}

		if ( isset( $input['alert_email'] ) ) {
			$sanitary_values['alert_email'] = sanitize_email( $input['alert_email'] );
		}

		if ( isset( $input['slack_webhook'] ) ) {
			$sanitary_values['slack_webhook'] = esc_url_raw( $input['slack_webhook'] );
		}

		return $sanitary_values;
	}

	/**
	 * Test alert callback
	 *
	 * @return void
	 */
	public function test_alert_callback() {
		if ( ! check_ajax_referer( 'test_alert_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ) );
			return;
		}

		$result = ALERT::send_test_alert();

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Test alert sent successfully!', 'woocommerce-es' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to send test alert. Please check your settings.', 'woocommerce-es' ) ) );
		}
	}

	/**
	 * ## Public
	 * --------------------------- */

	/**
	 * Sanitize fiels before saves in DB
	 *
	 * @param array $input Input fields.
	 * @return array
	 */
	public function sanitize_fields_public( $input ) {
		$sanitary_values = array();

		if ( isset( $input['vat_show'] ) ) {
			$sanitary_values['vat_show'] = sanitize_text_field( $input['vat_show'] );
		}

		if ( isset( $input['vat_mandatory'] ) ) {
			$sanitary_values['vat_mandatory'] = $input['vat_mandatory'];
		}

		if ( isset( $input['company_field'] ) ) {
			$sanitary_values['company_field'] = $input['company_field'];
		}

		if ( isset( $input['opt_checkout'] ) ) {
			$sanitary_values['opt_checkout'] = $input['opt_checkout'];
		}

		if ( isset( $input['terms_registration'] ) ) {
			$sanitary_values['terms_registration'] = $input['terms_registration'];
		}

		if ( isset( $input['remove_free'] ) ) {
			$sanitary_values['remove_free'] = $input['remove_free'];
		}

		if ( isset( $input['vat_vies_enabled'] ) ) {
			$sanitary_values['vat_vies_enabled'] = $input['vat_vies_enabled'];
		}

		if ( isset( $input['vatsense_api_key'] ) ) {
			$sanitary_values['vatsense_api_key'] = sanitize_text_field( $input['vatsense_api_key'] );
		}

		if ( isset( $input['vat_vies_mandatory'] ) ) {
			$sanitary_values['vat_vies_mandatory'] = $input['vat_vies_mandatory'];
		}

		return $sanitary_values;
	}

	/**
	 * Info for holded automate section.
	 *
	 * @return void
	 */
	public function section_info_public() {
		esc_html_e( 'Please select the following settings in order customize your eCommerce. ', 'woocommerce-es' );
	}

	/**
	 * Vat show setting
	 *
	 * @return void
	 */
	public function vat_show_callback() {
		$vat_show = isset( $this->settings_public['vat_show'] ) ? $this->settings_public['vat_show'] : 'yes';
		?>
		<select name="connect_ecommerce_public[vat_show]" id="vat_show">
			<option value="no" <?php selected( $vat_show, 'no' ); ?>><?php esc_html_e( 'No', 'woocommerce-es' ); ?></option>		<option value="yes" <?php selected( $vat_show, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woocommerce-es' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Vat show mandatory setting
	 *
	 * @return void
	 */
	public function vat_mandatory_callback() {
		$vat_mandatory = isset( $this->settings_public['vat_mandatory'] ) ? $this->settings_public['vat_mandatory'] : 'no';
		?>
		<select name="connect_ecommerce_public[vat_mandatory]" id="vat_mandatory">
			<option value="no" <?php selected( $vat_mandatory, 'no' ); ?>><?php esc_html_e( 'No', 'woocommerce-es' ); ?></option>		<option value="yes" <?php selected( $vat_mandatory, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woocommerce-es' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Vat show company field
	 *
	 * @return void
	 */
	public function company_field_callback() {
		$company_field = isset( $this->settings_public['company_field'] ) ? $this->settings_public['company_field'] : 'no';
		?>
		<select name="connect_ecommerce_public[company_field]" id="company_field">
			<option value="no" <?php selected( $company_field, 'no' ); ?>><?php esc_html_e( 'No', 'woocommerce-es' ); ?></option>		<option value="yes" <?php selected( $company_field, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woocommerce-es' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Vat show term conditions
	 *
	 * @return void
	 */
	public function terms_registration_callback() {
		$terms_registration = isset( $this->settings_public['terms_registration'] ) ? $this->settings_public['terms_registration'] : 'no';
		?>
		<select name="connect_ecommerce_public[terms_registration]" id="terms_registration">
			<option value="no" <?php selected( $terms_registration, 'no' ); ?>><?php esc_html_e( 'No', 'woocommerce-es' ); ?></option>		<option value="yes" <?php selected( $terms_registration, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woocommerce-es' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Vat show free shipping
	 *
	 * @return void
	 */
	public function remove_free_others_callback() {
		$remove_free = isset( $this->settings_public['remove_free'] ) ? $this->settings_public['remove_free'] : 'yes';
		?>
		<select name="connect_ecommerce_public[remove_free]" id="remove_free">
			<option value="no" <?php selected( $remove_free, 'no' ); ?>><?php esc_html_e( 'No', 'woocommerce-es' ); ?></option>		<option value="yes" <?php selected( $remove_free, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woocommerce-es' ); ?></option>
		</select>
		<?php
	}

	/**
	 * VAT VIES validation enabled callback
	 *
	 * @return void
	 */
	public function vat_vies_enabled_callback() {
		$vat_vies_enabled = isset( $this->settings_public['vat_vies_enabled'] ) ? $this->settings_public['vat_vies_enabled'] : 'yes';
		?>
		<select name="connect_ecommerce_public[vat_vies_enabled]" id="vat_vies_enabled">
			<option value="no" <?php selected( $vat_vies_enabled, 'no' ); ?>><?php esc_html_e( 'No', 'woocommerce-es' ); ?></option>
			<option value="yes" <?php selected( $vat_vies_enabled, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woocommerce-es' ); ?></option>
		</select>
		<p class="description">
			<?php esc_html_e( 'Enable VAT number validation through the VIES (VAT Information Exchange System) service. Requires dragonbe/vies library.', 'woocommerce-es' ); ?>
		</p>
		<?php
	}

	/**
	 * VAT VIES validation mandatory callback
	 *
	 * @return void
	 */
	public function vat_vies_mandatory_callback() {
		$vat_vies_mandatory = isset( $this->settings_public['vat_vies_mandatory'] ) ? $this->settings_public['vat_vies_mandatory'] : 'no';
		?>
		<select name="connect_ecommerce_public[vat_vies_mandatory]" id="vat_vies_mandatory">
			<option value="no" <?php selected( $vat_vies_mandatory, 'no' ); ?>><?php esc_html_e( 'No', 'woocommerce-es' ); ?></option>
			<option value="yes" <?php selected( $vat_vies_mandatory, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woocommerce-es' ); ?></option>
		</select>
		<p class="description">
			<?php esc_html_e( 'If enabled, customers will not be able to complete their order if the VAT number is invalid. If disabled, invalid VAT numbers will be accepted with a warning.', 'woocommerce-es' ); ?>
		</p>
		<?php
	}

	/**
	 * VATSense API Key callback
	 *
	 * @return void
	 */
	public function vatsense_api_key_callback() {
		$vatsense_api_key = isset( $this->settings_public['vatsense_api_key'] ) ? $this->settings_public['vatsense_api_key'] : '';
		?>
		<input 
			type="text" 
			name="connect_ecommerce_public[vatsense_api_key]" 
			id="vatsense_api_key" 
			value="<?php echo esc_attr( $vatsense_api_key ); ?>" 
			size="40"
			placeholder="<?php esc_attr_e( 'Enter your VATSense API key', 'woocommerce-es' ); ?>"
		/>
		<p class="description">
			<?php
			echo wp_kses_post(
				sprintf(
					// translators: 1: VATSense link.
					__( 'VATSense is a commercial VAT validation service with higher reliability than VIES. Used as fallback if VIES fails. Also supports Norway and Switzerland. <a href="%s" target="_blank">Sign up for VATSense</a> (free tier available).', 'woocommerce-es' ),
					'https://vatsense.com/signup?referral=CLOSEMARKETING'
				)
			);
			?>
		</p>
		<?php
	}
}
