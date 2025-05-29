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
	 * Construct of class
	 *
	 * @param array $options Options.
	 * @return void
	 */
	public function __construct( $options = array() ) {
		$this->settings_all = get_option( 'connect_ecommerce' );
		$this->connector    = isset( $this->settings_all['connector'] ) ? $this->settings_all['connector'] : '';
		$this->settings     = $this->settings_all[ $this->connector ] ?? array();
		$this->all_options  = $options;

		if ( ! empty( $this->connector ) ) {
			$this->options            = $options[ $this->connector ];
			if ( empty( $this->options['name'] ) ) {
				$this->settings_all['connector'] = '';
				update_option( 'connect_ecommerce', $this->settings_all );
				return;
			}
			$apiname                  = 'Connect_Ecommerce_' . $this->options['name'];
			$this->connapi_erp        = new $apiname( $options );
			$this->is_mergevars       = method_exists( $this->connapi_erp, 'get_product_attributes' ) ? true : false;
			$this->is_disabled_orders = isset( $this->options['disable_modules'] ) && in_array( 'order', $this->options['disable_modules'], true ) ? true : false;
			$this->is_disabled_ai     = isset( $this->options['disable_modules'] ) && in_array( 'ai', $this->options['disable_modules'], true ) ? true : false;
		}

		add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
		add_action( 'admin_init', array( $this, 'page_init' ) );
	}
	/**
	 * Adds plugin page.
	 *
	 * @return void
	 */
	public function add_plugin_page() {
		add_submenu_page(
			'woocommerce',
			__( 'Connect Ecommerce', 'connect-ecommerce' ),
			__( 'Connect Ecommerce', 'connect-ecommerce' ),
			'manage_woocommerce',
			'connect_ecommerce',
			array( $this, 'create_admin_page' ),
		);
	}

	/**
	 * Create admin page.
	 *
	 * @return void
	 */
	public function create_admin_page() {
		if ( $this->connector ) {
			$this->settings_public         = get_option( 'connect_ecommerce_public' );
			$this->settings_prod_mergevars = get_option( 'connect_ecommerce_prod_mergevars' );
			$this->settings_ai             = get_option( 'connect_ecommerce_ai' );
			$special_tabs                  = ! empty( $this->options['settings_special_tabs'] ) ? $this->options['settings_special_tabs'] : array();
		}
		$plugin_logo = $this->options['settings_logo'] ?? CONECOM_PLUGIN_URL . 'includes/assets/logo.svg';
		?>
		<div class="header-wrap">
			<div class="wrapper">
				<h2 style="display: none;"></h2>
				<div id="nag-container"></div>
				<div class="header connwoo-header">
					<div class="logo">
						<img src="<?php echo esc_url( $plugin_logo ); ?>" height="35" width="154"/>
						<h2>
							<?php
							esc_html_e( 'WooCommerce Connection Settings ', 'connect-ecommerce' );
							?>
						</h2>
					</div>
				</div>
			</div>
		</div>
		<div class="wrap">
			<?php settings_errors(); ?>

			<?php
			$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'sync_products';
			if ( ! isset( $_GET['tab'] ) && ! $this->connector ) {
				$active_tab = 'settings';
			}
			?>
			<h2 class="nav-tab-wrapper">
				<?php
				if ( $this->connector ) {
					?>
					<a href="?page=connect_ecommerce&tab=sync_products" class="nav-tab <?php echo 'sync_products' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Sync products', 'connect-ecommerce' ); ?></a>
					<?php
				}
				if ( $this->connector && $this->is_mergevars ) {
					?>
					<a href="?page=connect_ecommerce&tab=prod_mergevars" class="nav-tab <?php echo 'prod_mergevars' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Merge Vars', 'connect-ecommerce' ); ?></a>
					<?php
				}
				if ( ! $this->is_disabled_orders && $this->connector ) {
					?>
					<a href="?page=connect_ecommerce&tab=sync_orders" class="nav-tab <?php echo 'sync_orders' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Sync Orders', 'connect-ecommerce' ); ?></a>
					<?php
				}
				if ( $this->connector && in_array( 'subscriptions', $special_tabs, true ) ) {
					?>
					<a href="?page=connect_ecommerce&tab=subscriptions" class="nav-tab <?php echo 'subscriptions' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Subscriptions', 'connect-ecommerce' ); ?></a>
					<?php
				}
				if ( $this->connector ) {
					?>
					<a href="?page=connect_ecommerce&tab=automate" class="nav-tab <?php echo 'automate' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Automate', 'connect-ecommerce' ); ?></a>
					<?php
				}
				?>
				<a href="?page=connect_ecommerce&tab=settings" class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Settings', 'connect-ecommerce' ); ?></a>
				<a href="?page=connect_ecommerce&tab=public" class="nav-tab <?php echo 'public' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Frontend Settings', 'connect-ecommerce' ); ?></a>
				<?php
				if ( ! $this->is_disabled_ai && $this->connector ) {
					?>
					<a href="?page=connect_ecommerce&tab=ai" class="nav-tab <?php echo 'ai' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'AI', 'connect-ecommerce' ); ?></a>
					<?php
				}
				do_action( 'connect_ecommerce_settings_tabs', $active_tab );
				?>
			</h2>

			<?php
			if ( 'sync_products' === $active_tab || 'sync_orders' === $active_tab ) {
				$this->page_get_sync( $active_tab );
			}

			if ( 'settings' === $active_tab ) {
				?>
				<form method="post" action="options.php">
					<?php
						settings_fields( 'connect_ecommerce_settings' );
						do_settings_sections( 'connect_ecommerce_admin' );
						submit_button(
							__( 'Save settings', 'connect-ecommerce' ),
							'primary',
							'submit_settings'
						);
					?>
				</form>
			<?php } ?>
			<?php	if ( 'automate' === $active_tab ) { ?>
				<form method="post" action="options.php">
					<?php
					settings_fields( 'connect_ecommerce_settings' );
					do_settings_sections( 'connect_ecommerce_automate' );
					submit_button(
						__( 'Save automate', 'connect-ecommerce' ),
						'primary',
						'submit_automate'
					);
					?>
				</form>
			<?php } ?>
			<?php	if ( 'public' === $active_tab ) { ?>
				<form method="post" action="options.php">
					<?php
					settings_fields( 'connect_ecommerce_settings_public' );
					do_settings_sections( 'connect_ecommerce_public' );
					submit_button(
						__( 'Save public', 'connect-ecommerce' ),
						'primary',
						'submit_public'
					);
					?>
				</form>
				<?php
			}

			if ( 'prod_mergevars' === $active_tab && $this->is_mergevars ) {
				?>
				<form method="post" action="options.php">
					<?php
					settings_fields( 'connect_ecommerce_settings_prod_mergevars' );
					do_settings_sections( 'connect_ecommerce_prod_mergevars' );
					submit_button(
						__( 'Save merge', 'connect-ecommerce' ),
						'primary',
						'submit_prod_mergevars'
					);
					?>
				</form>
				<?php
			}

			if ( 'ai' === $active_tab ) {
				?>
				<form method="post" action="options.php">
					<?php
					settings_fields( 'connect_ecommerce_settings_ai' );
					do_settings_sections( 'connect_ecommerce_ai' );
					submit_button(
						__( 'Save AI', 'connect-ecommerce' ),
						'primary',
						'submit_ai'
					);
					?>
				</form>
				<?php
			}

			if ( 'subscriptions' === $active_tab ) {
				$this->page_get_subscriptions();
			}

			do_action( 'connect_ecommerce_settings_tabs_content', $active_tab );
			?>
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
			__( 'Settings for Importing in WooCommerce', 'connect-ecommerce' ),
			array( $this, 'connect_woocommerce_section_info' ),
			'connect_ecommerce_admin'
		);

		add_settings_section(
			'connect_woocommerce_setting_section',
			__( 'Settings for Importing in WooCommerce', 'connect-ecommerce' ),
			array( $this, 'connect_woocommerce_section_info' ),
			'connect_ecommerce_admin'
		);

		add_settings_field(
			'conecom_connector',
			__( 'Connector', 'connect-ecommerce' ),
			array( $this, 'connector_callback' ),
			'connect_ecommerce_admin',
			'connect_woocommerce_setting_section'
		);

		if ( $this->connector ) {
			if ( 'connwoo_neo' === $this->options['slug'] ) {
				add_settings_field(
					'wcpimh_idcentre',
					__( 'NEO ID Centre', 'connect-ecommerce' ),
					array( $this, 'idcentre_callback' ),
					'connect_ecommerce_admin',
					'connect_woocommerce_setting_section'
				);
			}

			// URL.
			if ( in_array( 'url', $settings_fields, true ) ) {
				add_settings_field(
					'wcpimh_url',
					__( 'URL', 'connect-ecommerce' ),
					array( $this, 'url_callback' ),
					'connect_ecommerce_admin',
					'connect_woocommerce_setting_section'
				);
			}

			// DB Name.
			if ( in_array( 'dbname', $settings_fields, true ) ) {
				add_settings_field(
					'wcpimh_dbname',
					__( 'DB Name', 'connect-ecommerce' ),
					array( $this, 'dbname_callback' ),
					'connect_ecommerce_admin',
					'connect_woocommerce_setting_section'
				);
			}

			// Username.
			if ( in_array( 'username', $settings_fields, true ) ) {
				add_settings_field(
					'wcpimh_username',
					__( 'Username', 'connect-ecommerce' ),
					array( $this, 'username_callback' ),
					'connect_ecommerce_admin',
					'connect_woocommerce_setting_section'
				);
			}

			// Password.
			if ( in_array( 'password', $settings_fields, true ) ) {
				add_settings_field(
					'wcpimh_password',
					__( 'Password', 'connect-ecommerce' ),
					array( $this, 'password_callback' ),
					'connect_ecommerce_admin',
					'connect_woocommerce_setting_section'
				);
			}

			// Company.
			if ( in_array( 'company', $settings_fields, true ) ) {
				add_settings_field(
					'wcpimh_company',
					__( 'Company', 'connect-ecommerce' ),
					array( $this, 'company_callback' ),
					'connect_ecommerce_admin',
					'connect_woocommerce_setting_section'
				);
			}

			// Domain.
			if ( in_array( 'domain', $settings_fields, true ) ) {
				add_settings_field(
					'wcpimh_domain',
					__( 'domain', 'connect-ecommerce' ),
					array( $this, 'domain_callback' ),
					'connect_ecommerce_admin',
					'connect_woocommerce_setting_section'
				);
			}

			// API Password.
			if ( in_array( 'apipassword', $settings_fields, true ) ) {
				add_settings_field(
					'wcpimh_api',
					__( 'API Key', 'connect-ecommerce' ),
					array( $this, 'api_callback' ),
					'connect_ecommerce_admin',
					'connect_woocommerce_setting_section'
				);
			}

			if ( $this->options['product_option_stock'] ) {
				add_settings_field(
					'wcpimh_stock',
					__( 'Import stock?', 'connect-ecommerce' ),
					array( $this, 'stock_callback' ),
					'connect_ecommerce_admin',
					'connect_woocommerce_setting_section'
				);
			}

			add_settings_field(
				'wcpimh_prodst',
				__( 'Default status for new products?', 'connect-ecommerce' ),
				array( $this, 'prodst_callback' ),
				'connect_ecommerce_admin',
				'connect_woocommerce_setting_section'
			);

			add_settings_field(
				'wcpimh_virtual',
				__( 'Virtual products?', 'connect-ecommerce' ),
				array( $this, 'virtual_callback' ),
				'connect_ecommerce_admin',
				'connect_woocommerce_setting_section'
			);

			add_settings_field(
				'wcpimh_backorders',
				__( 'Allow backorders?', 'connect-ecommerce' ),
				array( $this, 'backorders_callback' ),
				'connect_ecommerce_admin',
				'connect_woocommerce_setting_section'
			);

			add_settings_field(
				'wcpimh_catsep',
				__( 'Category separator', 'connect-ecommerce' ),
				array( $this, 'catsep_callback' ),
				'connect_ecommerce_admin',
				'connect_woocommerce_setting_section'
			);

			add_settings_field(
				'wcpimh_catattr',
				__( 'Attribute to use as category', 'connect-ecommerce' ),
				array( $this, 'catattr_callback' ),
				'connect_ecommerce_admin',
				'connect_woocommerce_setting_section'
			);

			add_settings_field(
				'wcpimh_catnp',
				__( 'Import category only in new products?', 'connect-ecommerce' ),
				array( $this, 'catnp_callback' ),
				'connect_ecommerce_admin',
				'connect_woocommerce_setting_section'
			);

			add_settings_field(
				'wcpimh_filter',
				__( 'Filter products by tags? Only import this tags (separated by comma and no space)', 'connect-ecommerce' ),
				array( $this, 'filter_callback' ),
				'connect_ecommerce_admin',
				'connect_woocommerce_setting_section'
			);

			add_settings_field(
				'wcpimh_filter_sku',
				__( 'Filter products by SKU? Only the products that complies these formula (use * for formula)', 'connect-ecommerce' ),
				array( $this, 'filter_sku_callback' ),
				'connect_ecommerce_admin',
				'connect_woocommerce_setting_section'
			);

			if ( $this->options['product_price_tax_option'] ) {
				add_settings_field(
					'wcpimh_tax_option',
					__( 'Get prices with Tax?', 'connect-ecommerce' ),
					array( $this, 'tax_option_callback' ),
					'connect_ecommerce_admin',
					'connect_woocommerce_setting_section'
				);
			}

			add_settings_field(
				'wcpimh_make_discount',
				__( 'Percentage to Make a discount from prices and save in sale price?', 'connect-ecommerce' ),
				array( $this, 'pricesale_discount_option_callback' ),
				'connect_ecommerce_admin',
				'connect_woocommerce_setting_section'
			);

			if ( $this->options['product_price_rate_option'] ) {
				$desc_tip     = __( 'Copy and paste the ID of the rates for publishing in the web', 'connect-ecommerce' );
				add_settings_field(
					'wcpimh_rates',
					__( 'Product price rate for this eCommerce', 'connect-ecommerce' ),
					array( $this, 'rates_callback' ),
					'connect_ecommerce_admin',
					'connect_woocommerce_setting_section'
				);
			}

			if ( ( isset( $this->options['order_series_number'] ) && $this->options['order_series_number'] ) || 'Holded' === $this->options['name'] ) {
				add_settings_field(
					'wcpimh_serie_number',
					__( 'Serie number', 'connect-ecommerce' ),
					array( $this, 'serie_number_callback' ),
					'connect_ecommerce_admin',
					'connect_woocommerce_setting_section'
				);
			}

			if ( 'Holded' === $this->options['name'] ) {
				$name_docorder = __( 'Document to create after order completed?', 'connect-ecommerce' );
				add_settings_field(
					'wcpimh_doctype',
					$name_docorder,
					array( $this, 'doctype_callback' ),
					'connect_ecommerce_admin',
					'connect_woocommerce_setting_section'
				);

				add_settings_field(
					'wcpimh_design_id',
					__( 'ID Holded design for document', 'connect-ecommerce' ),
					array( $this, 'wcpimh_design_id_callback' ),
					'connect_ecommerce_admin',
					'connect_woocommerce_setting_section'
				);
			}

			if ( ! $this->is_disabled_orders ) {
				add_settings_field(
					'wcpimh_freeorder',
					__( 'Create document for free Orders?', 'connect-ecommerce' ),
					array( $this, 'freeorder_callback' ),
					'connect_ecommerce_admin',
					'connect_woocommerce_setting_section'
				);

				add_settings_field(
					'wcpimh_ecstatus',
					__( 'Status to sync Orders?', 'connect-ecommerce' ),
					array( $this, 'ecstatus_callback' ),
					'connect_ecommerce_admin',
					'connect_woocommerce_setting_section'
				);
			}

			if ( ! empty( $this->options['order_tags'] ) ) {
				add_settings_field(
					'wcpimh_order_tags',
					__( 'Order Tag by default (separated by coma)?', 'connect-ecommerce' ),
					array( $this, 'order_tags_callback' ),
					'connect_ecommerce_admin',
					'connect_woocommerce_setting_section'
				);
			}

			if ( ! empty( $this->options['product_weight_equivalence'] ) ) {
				$attribute_fields = $this->connapi_erp->get_product_attributes();
				if ( ! empty( $attribute_fields ) ) {
					add_settings_field(
						'wcpimh_product_weight_equivalence',
						__( 'Custom field for Equivalence with weight', 'connect-ecommerce' ),
						array( $this, 'product_weight_equivalence_callback' ),
						'connect_ecommerce_admin',
						'connect_woocommerce_setting_section'
					);
				}
			}
			add_settings_field(
				'wcpimh_debug_log',
				__( 'Debug Mode', 'connect-ecommerce' ),
				array( $this, 'debug_log_callback' ),
				'connect_ecommerce_admin',
				'connect_woocommerce_setting_section'
			);
		}

		/**
		 * ## Automate
		 * --------------------------- */

		add_settings_section(
			'connect_woocommerce_setting_automate',
			__( 'Automate', 'connect-ecommerce' ),
			array( $this, 'section_automate' ),
			'connect_ecommerce_automate'
		);

		add_settings_field(
			'wcpimh_sync',
			__( 'When do you want to sync?', 'connect-ecommerce' ),
			array( $this, 'sync_callback' ),
			'connect_ecommerce_automate',
			'connect_woocommerce_setting_automate'
		);

		if ( ! empty( $this->options['table_sync'] ) ) {
			add_settings_field(
				'wcpimh_sync_num',
				__( 'How many products do you want to sync each time?', 'connect-ecommerce' ),
				array( $this, 'sync_num_callback' ),
				'connect_ecommerce_automate',
				'connect_woocommerce_setting_automate'
			);
			add_settings_field(
				'wcpimh_sync_email',
				__( 'Do you want to receive an email when all products are synced?', 'connect-ecommerce' ),
				array( $this, 'sync_email_callback' ),
				'connect_ecommerce_automate',
				'connect_woocommerce_setting_automate'
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
			'imhset_prod_mergevars_setting_section',
			__( 'Merge variables from product attributes to custom fields', 'connect-ecommerce' ),
			array( $this, 'section_info_prod_mergevars' ),
			'connect_ecommerce_prod_mergevars'
		);

		add_settings_field(
			'wcpimh_prod_mergevars',
			__( 'Merge fields with product', 'connect-ecommerce' ),
			array( $this, 'prod_mergevars_callback' ),
			'connect_ecommerce_prod_mergevars',
			'imhset_prod_mergevars_setting_section'
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
			__( 'Settings for Woocommerce Shop', 'connect-ecommerce' ),
			array( $this, 'section_info_public' ),
			'connect_ecommerce_public'
		);

		add_settings_field(
			'wcpimh_vat_show',
			__( 'Ask for VAT in Checkout?', 'connect-ecommerce' ),
			array( $this, 'vat_show_callback' ),
			'connect_ecommerce_public',
			'imhset_pub_setting_section'
		);
		add_settings_field(
			'wcpimh_vat_mandatory',
			__( 'VAT info mandatory?', 'connect-ecommerce' ),
			array( $this, 'vat_mandatory_callback' ),
			'connect_ecommerce_public',
			'imhset_pub_setting_section'
		);

		add_settings_field(
			'wcpimh_company_field',
			__( 'Show Company field?', 'connect-ecommerce' ),
			array( $this, 'company_field_callback' ),
			'connect_ecommerce_public',
			'imhset_pub_setting_section'
		);

		add_settings_field(
			'wcpimh_remove_free_others',
			__( 'Remove other shipping methods when free is possible?', 'connect-ecommerce' ),
			array( $this, 'remove_free_others_callback' ),
			'connect_ecommerce_public',
			'imhset_pub_setting_section'
		);

		add_settings_field(
			'wcpimh_terms_registration',
			__( 'Adds terms and conditions in registration page?', 'connect-ecommerce' ),
			array( $this, 'terms_registration_callback' ),
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
			__( 'Options to Use AI generating description for products', 'connect-ecommerce' ),
			array( $this, 'section_info_ai' ),
			'connect_ecommerce_ai'
		);

		add_settings_field(
			'connect_ecommerce_ai_provider',
			__( 'AI Provider', 'connect-ecommerce' ),
			array( $this, 'ai_provider_callback' ),
			'connect_ecommerce_ai',
			'imhset_ai_setting_section'
		);

		add_settings_field(
			'connect_ecommerce_ai_apikey',
			__( 'API Key', 'connect-ecommerce' ),
			array( $this, 'token_ai_callback' ),
			'connect_ecommerce_ai',
			'imhset_ai_setting_section'
		);

		add_settings_field(
			'connect_ecommerce_ai_model',
			__( 'Model (need login first)', 'connect-ecommerce' ),
			array( $this, 'ai_model_callback' ),
			'connect_ecommerce_ai',
			'imhset_ai_setting_section'
		);

		add_settings_field(
			'connect_ecommerce_ai_prompt',
			__( 'Prompt', 'connect-ecommerce' ),
			array( $this, 'ai_prompt_callback' ),
			'connect_ecommerce_ai',
			'imhset_ai_setting_section'
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
		$login_api   = $this->connapi_erp->check_can_sync();
		$can_sync    = false;
		if ( is_array( $login_api ) ) {
			$message  = $login_api['message'] ?? '';
			$can_sync = 'ok' === $login_api['status'] ? true : false;
		} else {
			$can_sync = $login_api;
			$message = $login_api ? '' : __( 'We couln\'t connect to the API', 'connect-ecommerce' );
		}
		?>
		<div class="connwoo-sync-engine">
			<div class="sync-wrapper">
				<?php 
				if ( empty( $can_sync ) ) {
					?>
					<div class="error notice">
						<p>
							<?php esc_html_e( 'You need to set the API settings before importing products.', 'connect-ecommerce' ); ?>
							<br/>
							<?php echo esc_html( $message ); ?>
						</p>
					</div>
					<?php
				} else {
				?>
					<h2>
						<?php
						echo sprintf(
							esc_html__( 'Import Products from %s', 'connect-ecommerce' ),
							esc_html( $this->options['name'] )
						);
						?>
					</h2>
					<p><?php esc_html_e( 'After you fillup the API settings, use the button below to import the products. The importing process may take a while and you need to keep this page open to complete it.', 'connect-ecommerce' ); ?>
					</p>
					<br/>
					<div id="sync-products" name="sync-products" class="button button-large button-primary" onclick="syncManualItems(this, '<?php echo esc_attr( $ajax_action ); ?>', 0);" ><?php esc_html_e( 'Start Import', 'connect-ecommerce' ); ?></div>
					<?php if ( ! $this->is_disabled_ai ) { ?>
						<p>
						<label for="<?php echo esc_attr( 'connect_ecommerce_ai' ); ?>"><?php esc_html_e( 'AI generation SEO options for products:', 'connect-ecommerce' ); ?></label>
						<select name="connwoo-sync-product-ai" id="<?php echo esc_attr( 'connect_ecommerce_ai' ); ?>">
							<option value="none"><?php esc_html_e( 'None', 'connect-ecommerce' ); ?></option>
							<option value="new"><?php esc_html_e( 'NEW Products', 'connect-ecommerce' ); ?></option>
							<option value="all"><?php esc_html_e( 'ALL Products', 'connect-ecommerce' ); ?></option>
						</select>
						</p>
					<?php } ?>
				</div>
				<fieldset id="logwrapper">
					<legend><?php esc_html_e( 'Log', 'connect-ecommerce' ); ?></legend>
					<div id="loglist"></div>
				</fieldset>
				<?php
				}
				?>
		</div>
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
				'api'            => '',
				'idcentre'       => '',
				'url'            => '',
				'username'       => '',
				'password'       => '',
				'company'        => '',
				'domain'         => '',
				'dbname'         => '',
				'stock'          => 'no',
				'prodst'         => 'draft',
				'virtual'        => 'no',
				'backorders'     => 'no',
				'catsep'         => '',
				'catattr'        => '',
				'filter'         => '',
				'pricesale_discount' => '',
				'filter_sku'     => '',
				'tax_option'     => 'no',
				'rates'          => 'default',
				'catnp'          => 'yes',
				'doctype'        => 'invoice',
				'series'         => '',
				'freeorder'      => 'no',
				'ecstatus'       => 'all',
				'order_tags'     => '',
				'design_id'      => '',
				'sync'           => 'no',
				'sync_num'       => 5,
				'sync_email'     => 'yes',
				'prod_weight_eq' => '',
				'debug_log'      => 'no',
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
	 * Info for holded section.
	 *
	 * @return void
	 */
	public function section_automate() {
		?>
		<input type="hidden" name="connect_ecommerce[connector]" value="<?php echo esc_attr( $this->connector ); ?>" />
		<?php
		if ( empty( $this->options['table_sync'] ) ) {
			return;
		}
		global $wpdb;
		$table_sync = $this->options['table_sync'];
		HELPER::check_table_sync( $table_sync );
		$count        = $wpdb->get_var( "SELECT COUNT(*) FROM $table_sync WHERE synced = 1" );
		$total_count  = $wpdb->get_var( "SELECT COUNT(*) FROM $table_sync" );
		$count_return = $count . ' / ' . $total_count;

		$total_api_products = (int) get_option( 'connect_ecommerce_total_api_products' );
		if ( $total_api_products || $total_count !== $total_api_products ) {
			$count_return .= ' ' . esc_html__( 'filtered', 'connect-ecommerce' );
			$count_return .= ' ( ' . $total_api_products . ' ' . esc_html__( 'total', 'connect-ecommerce' ) . ' )';
		}
		$percentage = 0 < $total_count ? intval( $count / $total_count * 100 ) : 0;
		esc_html_e( 'Make your settings to automate the sync.', 'connect-ecommerce' );
		echo '<div class="sync-status" style="text-align:right;">';
		echo '<strong>';
		esc_html_e( 'Actual Automate status:', 'connect-ecommerce' );
		echo '</strong> ' . esc_html( $count_return ) . ' ';
		esc_html_e( 'products synced with ', 'connect-ecommerce' );
		echo esc_html( $this->options['name'] );
		echo '</div>';
		echo '<div class="progress-bar blue">
		<span style="width:' . esc_html( $percentage ) . '%"></span>
		<div class="progress-text">' . esc_html( $percentage ) . '%</div>
		</div>';
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
			<option value="" <?php selected( $connector, '' ); ?>><?php esc_html_e( 'Select the ERP/CRM that you wish to connect', 'connect-ecommerce' ); ?></option>
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
			<option value="yes" <?php selected( $stock_option, 'yes' ); ?>><?php esc_html_e( 'Yes', 'connect-ecommerce' ); ?></option>
			<option value="no" <?php selected( $stock_option, 'no' ); ?>><?php esc_html_e( 'No', 'connect-ecommerce' ); ?></option>
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
			<option value="draft" <?php selected( $product_status, 'draft' ); ?>><?php esc_html_e( 'Draft', 'connect-ecommerce' ); ?></option>
			<option value="publish" <?php selected( $product_status, 'publish' ); ?>><?php esc_html_e( 'Publish', 'connect-ecommerce' ); ?></option>
			<option value="pending" <?php selected( $product_status, 'pending' ); ?>><?php esc_html_e( 'Pending', 'connect-ecommerce' ); ?></option>
			<option value="private" <?php selected( $product_status, 'private' ); ?>><?php esc_html_e( 'Private', 'connect-ecommerce' ); ?></option>
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
			<option value="no" <?php selected( $virtual_option, 'no' ); ?>><?php esc_html_e( 'No', 'connect-ecommerce' ); ?></option>
			<option value="yes" <?php selected( $virtual_option, 'yes' ); ?>><?php esc_html_e( 'Yes', 'connect-ecommerce' ); ?></option>
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
			<option value="no" <?php selected( $backorders, 'no' ); ?>><?php esc_html_e( 'No', 'connect-ecommerce' ); ?></option>
			<option value="yes" <?php selected( $backorders, 'yes' ); ?>><?php esc_html_e( 'Yes', 'connect-ecommerce' ); ?></option>
			<option value="notify" <?php selected( $backorders, 'notify' ); ?>><?php esc_html_e( 'Notify', 'connect-ecommerce' ); ?></option>
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
			'<input class="regular-text" type="text" name="connect_ecommerce[<?php echo esc_html( $this->connector ); ?>][catsep]" id="wcpimh_catsep" value="%s" %s>',
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
			<option value="yes" <?php selected( $tax_price, 'yes' ); ?>><?php esc_html_e( 'Yes, tax included', 'connect-ecommerce' ); ?></option>
			<option value="no" <?php selected( $tax_price, 'no' ); ?>><?php esc_html_e( 'No, tax not included', 'connect-ecommerce' ); ?></option>
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
			<option value="yes" <?php selected( $categorynp, 'yes' ); ?>><?php esc_html_e( 'Yes', 'connect-ecommerce' ); ?></option>		<option value="no" <?php selected( $categorynp, 'no' ); ?>><?php esc_html_e( 'No', 'connect-ecommerce' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Document type
	 *
	 * @return void
	 */
	public function doctype_callback() {
		$doctype = isset( $this->settings['doctype'] ) ? $this->settings['doctype'] : 'invoice';
		?>
		<select name="connect_ecommerce[<?php echo esc_html( $this->connector ); ?>][doctype]" id="wcpimh_doctype">
			<option value="nosync" <?php selected( $doctype, 'nosync' ); ?>><?php esc_html_e( 'Not sync', 'connect-ecommerce' ); ?></option>		<option value="invoice" <?php selected( $doctype, 'invoice' ); ?>><?php esc_html_e( 'Invoice', 'connect-ecommerce' ); ?></option>			<option value="salesreceipt" <?php selected( $doctype, 'salesreceipt' ); ?>><?php esc_html_e( 'Sales receipt', 'connect-ecommerce' ); ?></option>			<option value="salesorder" <?php selected( $doctype, 'salesorder' ); ?>><?php esc_html_e( 'Sales order', 'connect-ecommerce' ); ?></option>			<option value="waybill" <?php selected( $doctype, 'waybill' ); ?>><?php esc_html_e( 'Waybill', 'connect-ecommerce' ); ?></option>
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
			<option value="no" <?php selected( $freeorder, 'no' ); ?>><?php esc_html_e( 'No', 'connect-ecommerce' ); ?></option>		<option value="yes" <?php selected( $freeorder, 'yes' ); ?>><?php esc_html_e( 'Yes', 'connect-ecommerce' ); ?></option>

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
			<option value="all" <?php selected( $ecstatus, 'all' ); ?>><?php esc_html_e( 'All status orders', 'connect-ecommerce' ); ?></option>

			<option value="paid" <?php selected( $ecstatus, 'paid' ); ?>><?php esc_html_e( 'Paid orders', 'connect-ecommerce' ); ?></option>

			<option value="completed" <?php selected( $ecstatus, 'completed' ); ?>><?php esc_html_e( 'Only Completed', 'connect-ecommerce' ); ?></option>
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
			echo '<option value="">' . esc_html__( 'No', 'connect-ecommerce' ) . '</option>';
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
		esc_html_e( 'Activates debug mode to save logs.', 'connect-ecommerce' );
		echo '</label>';
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
			<option value="no" <?php selected( $sync, 'no' ); ?>><?php esc_html_e( 'No', 'connect-ecommerce' ); ?></option>
			<?php
			if ( ! empty( $this->options['cron'] ) ) {
				foreach ( $this->options['cron'] as $cron_option ) {
					echo '<option value="' . esc_html( $cron_option['cron'] ) . '" ';
					selected( $sync, $cron_option['cron'] );
					echo '>' . esc_html( $cron_option['display'] ) . '</option>';
				}
			}
			?>
		</select>
		<?php
	}

	/**
	 * Callback sync field.
	 *
	 * @return void
	 */
	public function sync_num_callback() {
		printf(
			'<input class="regular-text" type="text" name="connect_ecommerce[' . esc_html( $this->connector ) . '][sync_num]" id="wcpimh_sync_num" value="%s">',
			isset( $this->settings['sync_num'] ) ? esc_attr( $this->settings['sync_num'] ) : 5
		);
	}

	/**
	 * Sync email options
	 *
	 * @return void
	 */
	public function sync_email_callback() {
		$sync_email = isset( $this->settings['sync_email'] ) ? $this->settings['sync_email'] : 'no';
		?>
		<select name="connect_ecommerce[<?php echo esc_html( $this->connector ); ?>][sync_email]" id="wcpimh_sync_email">
			<option value="yes" <?php selected( $sync_email, 'yes' ); ?>><?php esc_html_e( 'Yes', 'connect-ecommerce' ); ?></option>
			<option value="no" <?php selected( $sync_email, 'no' ); ?>><?php esc_html_e( 'No', 'connect-ecommerce' ); ?></option>
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
		esc_html_e( 'Please select the following settings in order customize your eCommerce. ', 'connect-ecommerce' );
	}

	/**
	 * Page get Merge Product variables
	 *
	 * @return void
	 */
	public function prod_mergevars_callback() {
		$product_fields    = PROD::get_all_product_fields();
		$custom_fields     = PROD::get_all_custom_fields();
		$custom_taxonomies = TAX::get_all_custom_taxonomies();
		$attribute_fields  = $this->connapi_erp->get_product_attributes();

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
					<div class="save-item"><strong><?php esc_html_e( 'Field from ', 'connect-ecommerce' ); echo ' ' . esc_html( $this->options['name'] ); ?></strong></div>
					<div></div>
					<div class="save-item"><strong><?php esc_html_e( 'WooCommerce Field', 'connect-ecommerce' );?></strong></div>
				</div>
				<?php
				$size = isset( $settings_mergevars ) ? count( $settings_mergevars ) : 0;
				for ( $idx = 0, $size; $idx <= $size; ++$idx ) {
					$attrprod = isset( $saved_attr[ $idx ]['attrprod'] ) ? $saved_attr[ $idx ]['attrprod'] : '';
					?>
					<div class="product-mergevars repeating" style="border: 1px solid #ccc; padding: 10px; margin-bottom: 10px;">
						<div class="save-item">
							<select name='connect_ecommerce_prod_mergevars[prod_mergevars][<?php echo esc_html( $idx ); ?>][attrprod]' class="attrprod-publish" data-row="<?php echo esc_html( $idx ); ?>">
								<option value=''></option>
								<?php
								foreach ( $attribute_fields as $attribute ) {
									?>
									<optgroup label="<?php echo esc_html( $attribute['name'] ); ?>">
										<?php
										foreach ( $attribute['elements'] as $value ) {
											$option_id = $attribute['id'] . '|' . $value;
											echo '<option value="' . esc_html( $option_id ) . '" ';
											selected( $value, $attrprod );
											echo '>' . esc_html( $value ) . '</option>';
										}
										?>
									</optgroup>
									<?php
								}
								?>
							</select>
						</div>
						<span class="dashicons dashicons-arrow-right-alt2"></span>
						<div class="save-item">
							<?php 
							$saved_custom_field = isset( $saved_attr[ $idx ]['custom_field'] ) ? $saved_attr[ $idx ]['custom_field'] : '';
							$all_fields = array_merge( $product_fields, $custom_taxonomies, $custom_fields );
							if ( ! array_key_exists( $saved_custom_field, $all_fields ) ) {
								$custom_fields[] = $saved_custom_field;
							}
							?>
							<select name='connect_ecommerce_prod_mergevars[prod_mergevars][<?php echo esc_html( $idx ); ?>][custom_field]' class="source-cf" onchange="chargeother(this)">
								<option value=''></option>
								<optgroup label="<?php esc_html_e( 'Product Fields', 'connect-ecommerce' ); ?>">
									<?php
									foreach ( $product_fields as $key => $value ) {
										echo '<option value="' . esc_html( $key ) . '" ';
										selected( $key, $saved_custom_field );
										echo '>' . esc_html( $value ) . '</option>';
									}
									?>
								</optgroup>
								<optgroup label="<?php esc_html_e( 'Product Category values', 'connect-ecommerce' ); ?>">
									<?php
									$terms = get_terms( array(
										'taxonomy'   => 'product_cat',
										'hide_empty' => false,
									) );
									if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
										foreach ( $terms as $term ) {
											$key = 'product_cat|' . $term->term_id;
											echo '<option value="' . esc_attr( $key ) . '" ';
											selected( $key, $saved_custom_field );
											echo '>' . esc_html( $term->name ) . '</option>';
										}
									}
									?>
								</optgroup>
								<optgroup label="<?php esc_html_e( 'Taxonomy Fields', 'connect-ecommerce' ); ?>">
									<?php
									foreach ( $custom_taxonomies as $key => $value ) {
										echo '<option value="' . esc_html( $key ) . '" ';
										selected( $key, $saved_custom_field );
										echo '>' . esc_html( $value ) . '</option>';
									}
									?>
								</optgroup>
								<optgroup label="<?php esc_html_e( 'Custom Fields', 'connect-ecommerce' ); ?>">
								<?php
								foreach ( $custom_fields as $key => $value ) {
									$key = empty( $key ) ? $value : $key;
									echo '<option value="' . esc_html( $key ) . '" ';
									selected( $key, $saved_custom_field );
									echo '>' . esc_html( $value ) . '</option>';
								}
								echo '<option value="custom">' . esc_html__( 'Customized', 'connect-ecommerce' ) . '</option>';
								?>
								</optgroup>
							</select>
						</div>
						<div class="save-item">
							<a href="#" class="button alt remove"><span class="dashicons dashicons-remove"></span><?php esc_html_e( 'Remove', 'connect-ecommerce' ); ?></a>
						</div>
					</div>
					<?php
				}
				?>
				<a href="#" class="button repeat"><span class="dashicons dashicons-insert"></span><?php esc_html_e( 'Add Another', 'connect-ecommerce' ); ?></a>
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
		$sanitary_values = array();

		$admin_settings = array(
			'provider' => 'chatgpt',
			'token'    => '',
			'model'    => '',
			'prompt'   => '',
		);

		foreach ( $admin_settings as $setting => $default_value ) {
			if ( isset( $input[ $setting ] ) ) {
				$sanitary_values[ $setting ] = sanitize_text_field( $input[ $setting ] );
			} else {
				$sanitary_values[ $setting ] = $default_value;
			}
		}

		return $sanitary_values;
	}

	/**
	 * Info for AI.
	 *
	 * @return void
	 */
	public function section_info_ai() {
		esc_html_e( 'Select the provider and options for AI generating. ', 'connect-ecommerce' );
	}

	/**
	 * Vat show setting
	 *
	 * @return void
	 */
	public function ai_provider_callback() {
		$provider = isset( $this->settings_ai['provider'] ) ? $this->settings_ai['provider'] : 'chatgpt';
		?>
		<select name="connect_ecommerce_ai[provider]" id="provider">
			<option value="chatgpt" <?php selected( $provider, 'chatgpt' ); ?>><?php esc_html_e( 'ChatGPT', 'connect-ecommerce' ); ?></option>
			<option value="deepseek" <?php selected( $provider, 'deepseek' ); ?>><?php esc_html_e( 'DeepSeek', 'connect-ecommerce' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Vat show setting
	 *
	 * @return void
	 */
	public function ai_model_callback() {
		$model    = isset( $this->settings_ai['model'] ) ? $this->settings_ai['model'] : '';
		$provider = isset( $this->settings_ai['provider'] ) ? $this->settings_ai['provider'] : 'chatgpt';
		$token    = isset( $this->settings_ai['token'] ) ? $this->settings_ai['token'] : '';
		$options  = AI::get_models( $provider, $token );
		?>

		<select name="connect_ecommerce_ai[model]" id="cwc_ai_model">
			<?php
			foreach ( $options as $key => $label ) {
				echo '<option value="' . esc_html( $key ) . '" ' . selected( $key, $model ) . ' >' . esc_html( $label ) . '</option>';
			}
			?>
		</select>
		<?php
	}

	/**
	 * Callback sync field.
	 *
	 * @return void
	 */
	public function token_ai_callback() {
		printf(
			'<input class="regular-text" type="password" name="connect_ecommerce_ai[token]" id="wcpimh_token" value="%s">',
			isset( $this->settings_ai['token'] ) ? esc_attr( $this->settings_ai['token'] ) : ''
		);
	}

	/**
	 * Callback sync field.
	 *
	 * @return void
	 */
	public function ai_prompt_callback() {
		$prompt = isset( $this->settings_ai['prompt'] ) ? $this->settings_ai['prompt'] : __( 'Here is the information about a product. I need you to write a description for an online store, highlighting the main features. Don\'t use prices in the description.', 'connect-ecommerce' );
		?>
		<textarea class="regular-text" rows="5" style="width: 100%;" name="connect_ecommerce_ai[prompt]" id="wcpimh_prompt"><?php echo esc_textarea( $prompt ); ?></textarea>
		<p><?php esc_html_e( 'After prompt, we add the format to retrieve the contact', 'connect-ecommerce' ); ?></p>
		<?php
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

		return $sanitary_values;
	}

	/**
	 * Info for holded automate section.
	 *
	 * @return void
	 */
	public function section_info_public() {
		esc_html_e( 'Please select the following settings in order customize your eCommerce. ', 'connect-ecommerce' );
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
			<option value="no" <?php selected( $vat_show, 'no' ); ?>><?php esc_html_e( 'No', 'connect-ecommerce' ); ?></option>		<option value="yes" <?php selected( $vat_show, 'yes' ); ?>><?php esc_html_e( 'Yes', 'connect-ecommerce' ); ?></option>
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
			<option value="no" <?php selected( $vat_mandatory, 'no' ); ?>><?php esc_html_e( 'No', 'connect-ecommerce' ); ?></option>		<option value="yes" <?php selected( $vat_mandatory, 'yes' ); ?>><?php esc_html_e( 'Yes', 'connect-ecommerce' ); ?></option>
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
			<option value="no" <?php selected( $company_field, 'no' ); ?>><?php esc_html_e( 'No', 'connect-ecommerce' ); ?></option>		<option value="yes" <?php selected( $company_field, 'yes' ); ?>><?php esc_html_e( 'Yes', 'connect-ecommerce' ); ?></option>
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
			<option value="no" <?php selected( $terms_registration, 'no' ); ?>><?php esc_html_e( 'No', 'connect-ecommerce' ); ?></option>		<option value="yes" <?php selected( $terms_registration, 'yes' ); ?>><?php esc_html_e( 'Yes', 'connect-ecommerce' ); ?></option>
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
			<option value="no" <?php selected( $remove_free, 'no' ); ?>><?php esc_html_e( 'No', 'connect-ecommerce' ); ?></option>		<option value="yes" <?php selected( $remove_free, 'yes' ); ?>><?php esc_html_e( 'Yes', 'connect-ecommerce' ); ?></option>
		</select>
		<?php
	}
}
