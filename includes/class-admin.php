<?php
/**
 * Library for admin settings
 *
 * @package    WordPress
 * @author     David Perez <david@closemarketing.es>
 * @copyright  2019 Closemarketing
 * @version    1.0
 */
defined( 'ABSPATH' ) || exit;
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
class WCIMPH_Admin {
	/**
	 * Settings
	 *
	 * @var array
	 */
	private $wces_settings;

	/**
	 * Label for premium features
	 *
	 * @var string
	 */
	private $label_premium;

	/**
	 * Is Woocommerce active?
	 *
	 * @var boolean
	 */
	private $is_woocommerce_active;

	/**
	 * Construct of class
	 */
	public function __construct() {
		$this->label_premium = __( '(ONLY PREMIUM VERSION)', 'woocommerce-es' );
		add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
		add_action( 'admin_init', array( $this, 'page_init' ) );
		add_action( 'admin_head', array( $this, 'custom_css' ) );
	}

	/**
	 * Adds plugin page.
	 *
	 * @return void
	 */
	public function add_plugin_page() {
		add_menu_page(
			__( 'WPSPA eCommerce', 'woocommerce-es' ),
			__( 'Settings', 'woocommerce-es' ),
			'manage_options',
			'wces',
			array( $this, 'create_admin_page' ),
			'dashicons-index-card',
			99
		);
	}

	/**
	 * Create admin page.
	 *
	 * @return void
	 */
	public function create_admin_page() {
		$this->imh_settings = get_option( 'wces_settings' );
		?>

			<div class="wrap">
				<h2><?php esc_html_e( 'Holded Product Importing Settings', 'woocommerce-es' ); ?></h2>
				<p></p>
				<?php 
				settings_errors();
				?>

				<form method="post" action="options.php">
					<?php 
					settings_fields( 'wces_settings' );
					do_settings_sections( 'woocommerce-es-admin' );
					submit_button( __( 'Save Settings', 'woocommerce-es' ), 'primary', 'submit_automate' );
					?>
				</form>
			</div>
			<?php 
	}

	/**
	 * Init for page
	 *
	 * @return void
	 */
	public function page_init() {
		register_setting( 'wces_settings', 'wces_settings', array( $this, 'sanitize_fields' ) );

		add_settings_section(
			'wces_setting_section',
			__( 'Settings for Spanish Enhacements in WooCommerce', 'woocommerce-es' ),
			array( $this, 'wces_section_info' ),
			'woocommerce-es-admin'
		);
		/*
				$updated_settings[] = array(
					'name'    => __( 'Ask for VAT in Checkout?', 'woocommerce-es' ),
					'desc'    => __( 'This controls if VAT field will be shown in checkout.', 'woocommerce-es' ),
					'id'      => 'wces_vat_show',
					'std'     => 'yes', // WooCommerce < 2.0
					'default' => 'yes', // WooCommerce >= 2.0
					'type'    => 'checkbox',
				);
				$updated_settings[] = array(
					'name'    => __( 'VAT info mandatory?', 'woocommerce-es' ),
					'desc'    => __( 'This controls if VAT info would be mandatory in checkout.', 'woocommerce-es' ),
					'id'      => 'wces_vat_mandatory',
					'std'     => 'no', // WooCommerce < 2.0
					'default' => 'no', // WooCommerce >= 2.0
					'type'    => 'checkbox',
				);
				$updated_settings[] = array(
					'name'    => __( 'Show Company field?', 'woocommerce-es' ),
					'desc'    => __( 'This controls if company field will be shown', 'woocommerce-es' ),
					'id'      => 'wces_company',
					'std'     => 'no', // WooCommerce < 2.0
					'default' => 'no', // WooCommerce >= 2.0
					'type'    => 'checkbox',
				);
				$updated_settings[] = array(
					'name'    => __( 'Optimize Checkout?', 'woocommerce-es' ),
					'desc'    => __( 'Optimizes your checkout to better conversion.', 'woocommerce-es' ),
					'id'      => 'wces_opt_checkout',
					'std'     => 'no', // WooCommerce < 2.0
					'default' => 'no', // WooCommerce >= 2.0
					'type'    => 'checkbox',
				);*/
		
		add_settings_field(
			'wces_vat_show',
			__( 'Ask for VAT in Checkout?', 'woocommerce-es' ),
			array( $this, 'wces_vat_show_callback' ),
			'woocommerce-es-admin',
			'wces_setting_section'
		);
		add_settings_field(
			'wces_vat_mandatory',
			__( 'VAT info mandatory?', 'woocommerce-es' ),
			array( $this, 'wces_vat_mandatory_callback' ),
			'woocommerce-es-admin',
			'wces_setting_section'
		);

		add_settings_field(
			'wces_company_field',
			__( 'Show Company field?', 'woocommerce-es' ),
			array( $this, 'wces_company_field_callback' ),
			'woocommerce-es-admin',
			'wces_setting_section'
		);

		add_settings_field(
			'wces_company_field',
			__( 'Optimize Checkout?', 'woocommerce-es' ),
			array( $this, 'wces_company_field_callback' ),
			'woocommerce-es-admin',
			'wces_setting_section'
		);

		add_settings_field(
			'wces_remove_free_others',
			__( 'Remove other shipping methods when free is possible?', 'woocommerce-es' ),
			array( $this, 'wces_remove_free_others_callback' ),
			'woocommerce-es-admin',
			'wces_setting_section'
		);

		add_settings_field(
			'wces_terms_registration',
			__( 'Adds terms and conditions in registration page?', 'woocommerce-es' ),
			array( $this, 'wces_terms_registration_callback' ),
			'woocommerce-es-admin',
			'wces_setting_section'
		);
	}

	/**
	 * Sanitize fiels before saves in DB
	 *
	 * @param array $input Input fields.
	 * @return array
	 */
	public function sanitize_fields( $input )
	{
		$sanitary_values = array();
		$wces_settings   = get_option( 'wces_settings' );

		if ( isset( $input['vat_show'] ) ) {
			$sanitary_values['vat_show'] = sanitize_text_field( $input['vat_show'] );
		}

		if ( isset( $input['vat_mandatory'] ) ) {
			$sanitary_values['vat_mandatory'] = $input['vat_mandatory'];
		}

		if ( isset( $input['company_field'] ) ) {
			$sanitary_values['company_field'] = $input['company_field'];
		}

		if ( isset( $input['terms_registration'] ) ) {
			$sanitary_values['terms_registration'] = $input['terms_registration'];
		}

		if ( isset( $input['remove_free'] ) ) {
			$sanitary_values['remove_free'] = $input['remove_free'];
		}

		return $sanitary_values;
	}

	private function show_get_premium()
	{
		// Purchase notification.
		$purchase_url = 'https://checkout.freemius.com/mode/dialog/plugin/5133/plan/8469/';
		$get_pro = sprintf( wp_kses( __( '<a href="%s">Get Pro version</a> to enable', 'woocommerce-es' ), array(
			'a' => array(
			'href'   => array(),
			'target' => array(),
		),
		) ), esc_url( $purchase_url ) );
		return $get_pro;
	}

	/**
	 * Info for holded automate section.
	 *
	 * @return void
	 */
	public function wces_section_info() {
		echo sprintf( __( 'Put the connection API key settings in order to connect and sync products. You can go here <a href="%s" target = "_blank">App Holded API</a>. ', 'woocommerce-es' ), 'https://app.holded.com/api' ) ;
      	echo $this->show_get_premium() ;
	}

	/**
	 * Vat show setting
	 *
	 * @return void
	 */
	public function wces_vat_show_callback() {
		?>
		<select name="wces_settings[vat_show]" id="vat_show">
			<?php 
			$selected = ( isset( $this->imh_settings['vat_show'] ) && $this->imh_settings['vat_show'] === 'no' ? 'selected' : '' );
			?>
			<option value="no" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'No', 'woocommerce-es' ); ?></option>
			<?php 
			$selected = ( isset( $this->imh_settings['vat_show'] ) && $this->imh_settings['vat_show'] === 'yes' ? 'selected' : '' );
			?>
			<option value="yes" <?php echo  esc_html( $selected ); ?>><?php esc_html_e( 'Yes', 'woocommerce-es' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Vat show mandatory setting
	 *
	 * @return void
	 */
	public function wces_vat_mandatory_callback() {
		?>
		<select name="wces_settings[vat_mandatory]" id="vat_mandatory">
			<?php 
			$selected = ( isset( $this->imh_settings['vat_mandatory'] ) && $this->imh_settings['vat_mandatory'] === 'no' ? 'selected' : '' );
			?>
			<option value="no" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'No', 'woocommerce-es' ); ?></option>
			<?php 
			$selected = ( isset( $this->imh_settings['vat_mandatory'] ) && $this->imh_settings['vat_mandatory'] === 'yes' ? 'selected' : '' );
			?>
			<option value="yes" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Yes', 'woocommerce-es' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Vat show company field
	 *
	 * @return void
	 */
	public function wces_company_field_callback() {
		?>
		<select name="wces_settings[company_field]" id="company_field">
			<?php 
			$selected = ( isset( $this->imh_settings['company_field'] ) && $this->imh_settings['company_field'] === 'no' ? 'selected' : '' );
			?>
			<option value="no" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'No', 'woocommerce-es' ); ?></option>
			<?php 
			$selected = ( isset( $this->imh_settings['company_field'] ) && $this->imh_settings['company_field'] === 'yes' ? 'selected' : '' );
			?>
			<option value="yes" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Yes', 'woocommerce-es' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Vat show term conditions
	 *
	 * @return void
	 */
	public function wces_terms_registration_callback() {
		?>
		<select name="wces_settings[terms_registration]" id="terms_registration">
			<?php 
			$selected = ( isset( $this->imh_settings['terms_registration'] ) && $this->imh_settings['terms_registration'] === 'no' ? 'selected' : '' );
			?>
			<option value="no" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'No', 'woocommerce-es' ); ?></option>
			<?php 
			$selected = ( isset( $this->imh_settings['terms_registration'] ) && $this->imh_settings['terms_registration'] === 'yes' ? 'selected' : '' );
			?>
			<option value="yes" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Yes', 'woocommerce-es' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Vat show free shipping
	 *
	 * @return void
	 */
	public function wces_remove_free_others_callback() {
		?>
		<select name="wces_settings[remove_free]" id="remove_free">
			<?php 
			$selected = ( isset( $this->imh_settings['remove_free'] ) && $this->imh_settings['remove_free'] === 'no' ? 'selected' : '' );
			?>
			<option value="no" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'No', 'woocommerce-es' ); ?></option>
			<?php 
			$selected = ( isset( $this->imh_settings['remove_free'] ) && $this->imh_settings['remove_free'] === 'yes' ? 'selected' : '' );
			?>
			<option value="yes" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Yes', 'woocommerce-es' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Custom CSS for admin
	 *
	 * @return void
	 */
	public function custom_css() {
		echo '
			<style>
			.wp-admin .wcpimh-plugin span.wcpimh-premium{ 
				color: #b4b9be;
			}
			.wp-admin.wcpimh-plugin #wcpimh_catnp,
			.wp-admin.wcpimh-plugin #wcpimh_stock,
			.wp-admin.wcpimh-plugin #wcpimh_catsep {
				width: 70px;
			}
			.wp-admin.wcpimh-plugin #wcpimh_sync_num {
				width: 50px;
			}
			.wp-admin.wcpimh-plugin #wces_vat_mandatory {
				width: 150px;
			}
			.wp-admin.wcpimh-plugin #wces_vat_show,
			.wp-admin.wcpimh-plugin #wcpimh_taxinc {
				width: 270px;
			}</style>';
	}

}
if ( is_admin() ) {
	$wces = new WCIMPH_Admin();
}
