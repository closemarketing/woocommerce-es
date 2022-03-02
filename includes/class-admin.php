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
class WPSPA_WCES_Admin {
	/**
	 * Settings
	 *
	 * @var array
	 */
	private $wces_settings;

	/**
	 * Construct of class
	 */
	public function __construct() {
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
			__( 'WPSPA', 'woocommerce-es' ),
			__( 'WPSPA', 'woocommerce-es' ),
			'manage_options',
			'wces',
			array( $this, 'create_admin_page' )
		); 

	}

	/**
	 * Create admin page.
	 *
	 * @return void
	 */
	public function create_admin_page() {
		$this->wces_settings = get_option( 'wces_settings' );
		?>
			<div class="wrap">
				<h2><?php esc_html_e( 'Spanish eCommerce Importing Settings', 'woocommerce-es' ); ?></h2>
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
			'wces_opt_checkout',
			__( 'Optimize Checkout?', 'woocommerce-es' ),
			array( $this, 'wces_opt_checkout_callback' ),
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
	public function sanitize_fields( $input ) {
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
	public function wces_section_info() {
		$source_shop = 'es' === strtok( get_locale(), '_' ) ? 'https://close.technology/' : 'https://en.close.technology/';
		echo sprintf(
			/* translators: %s: url admin for addons */
			esc_html__( 'Please select the following settings in order customize your eCommerce. Don\'t miss the <a href="%s">Premium Version</a> to add new translations. ', 'woocommerce-es' ),
			$source_shop . 'wordpress-plugins/woocommerce-es-trans/' // phpcs:ignore
		);
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
			$selected = ( isset( $this->wces_settings['vat_show'] ) && $this->wces_settings['vat_show'] === 'no' ? 'selected' : '' );
			?>
			<option value="no" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'No', 'woocommerce-es' ); ?></option>
			<?php 
			$selected = ( isset( $this->wces_settings['vat_show'] ) && $this->wces_settings['vat_show'] === 'yes' ? 'selected' : '' );
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
		$wces_vat_mandatory = get_option( 'wces_vat_mandatory' );
		?>
		<select name="wces_settings[vat_mandatory]" id="vat_mandatory">
			<?php 
			$selected = ( isset( $this->wces_settings['vat_mandatory'] ) && $this->wces_settings['vat_mandatory'] === 'no' ? 'selected' : '' );
			?>
			<option value="no" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'No', 'woocommerce-es' ); ?></option>
			<?php 
			$selected = ( isset( $this->wces_settings['vat_mandatory'] ) && $this->wces_settings['vat_mandatory'] === 'yes' ? 'selected' : '' );
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
			$selected = ( isset( $this->wces_settings['company_field'] ) && $this->wces_settings['company_field'] === 'no' ? 'selected' : '' );
			?>
			<option value="no" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'No', 'woocommerce-es' ); ?></option>
			<?php 
			$selected = ( isset( $this->wces_settings['company_field'] ) && $this->wces_settings['company_field'] === 'yes' ? 'selected' : '' );
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
	public function wces_opt_checkout_callback() {
		?>
		<select name="wces_settings[opt_checkout]" id="opt_checkout">
			<?php 
			$selected = ( isset( $this->wces_settings['opt_checkout'] ) && $this->wces_settings['opt_checkout'] === 'no' ? 'selected' : '' );
			?>
			<option value="no" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'No', 'woocommerce-es' ); ?></option>
			<?php 
			$selected = ( isset( $this->wces_settings['opt_checkout'] ) && $this->wces_settings['opt_checkout'] === 'yes' ? 'selected' : '' );
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
			$selected = ( isset( $this->wces_settings['terms_registration'] ) && $this->wces_settings['terms_registration'] === 'no' ? 'selected' : '' );
			?>
			<option value="no" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'No', 'woocommerce-es' ); ?></option>
			<?php 
			$selected = ( isset( $this->wces_settings['terms_registration'] ) && $this->wces_settings['terms_registration'] === 'yes' ? 'selected' : '' );
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
			$selected = ( isset( $this->wces_settings['remove_free'] ) && $this->wces_settings['remove_free'] === 'no' ? 'selected' : '' );
			?>
			<option value="no" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'No', 'woocommerce-es' ); ?></option>
			<?php 
			$selected = ( isset( $this->wces_settings['remove_free'] ) && $this->wces_settings['remove_free'] === 'yes' ? 'selected' : '' );
			?>
			<option value="yes" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Yes', 'woocommerce-es' ); ?></option>
		</select>
		<?php
	}
}
if ( is_admin() ) {
	$wces = new WPSPA_WCES_Admin();
}
