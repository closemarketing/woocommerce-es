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
			__( 'WPSPA eCommerce', 'woocommerce-es' ),
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
					do_settings_sections( 'import-holded-automate' );
					submit_button( __( 'Save automate', 'wpautotranslate' ), 'primary', 'submit_automate' );
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
			__( 'Settings for Importing in Easy Digital Downloads', 'woocommerce-es' ),
			array( $this, 'wces_section_info' ),
			'import-holded-admin'
		);
		add_settings_field(
			'wcpimh_api',
			__( 'Holded API Key', 'woocommerce-es' ),
			array( $this, 'wcpimh_api_callback' ),
			'import-holded-admin',
			'wces_setting_section'
		);
		add_settings_field(
			'wcpimh_prodst',
			__( 'Default status for new products?', 'woocommerce-es' ),
			array( $this, 'wcpimh_prodst_callback' ),
			'import-holded-admin',
			'wces_setting_section'
		);
		
		$label_cat = __( 'Category separator', 'woocommerce-es' );
		if ( cmk_fs()->is_not_paying() ) {
			$label_cat .= ' ' . $this->label_premium;
		}
		add_settings_field(
			'wcpimh_catsep',
			$label_cat,
			array( $this, 'wcpimh_catsep_callback' ),
			'import-holded-admin',
			'wces_setting_section'
		);
		add_settings_field(
			'wcpimh_filter',
			__( 'Filter products by tag?', 'woocommerce-es' ),
			array( $this, 'wcpimh_filter_callback' ),
			'import-holded-admin',
			'wces_setting_section'
		);
		$label_filter = __( 'Product price rate for this eCommerce', 'woocommerce-es' );
		$desc_tip = __( 'Copy and paste the ID of the rates for publishing in the web', 'woocommerce-es' );
		if ( cmk_fs()->is_not_paying() ) {
			$label_filter .= ' ' . $this->label_premium;
		}
		add_settings_field(
			'wcpimh_rates',
			$label_filter,
			array( $this, 'wcpimh_rates_callback' ),
			'import-holded-admin',
			'wces_setting_section'
		);
		$name_catnp = __( 'Import category only in new products?', 'woocommerce-es' );
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

		if ( isset( $input['wcpimh_api'] ) ) {
			$sanitary_values['wcpimh_api'] = sanitize_text_field( $input['wcpimh_api'] );
		}
		if ( isset( $input['wcpimh_stock'] ) ) {
			$sanitary_values['wcpimh_stock'] = $input['wcpimh_stock'];
		}
		if ( isset( $input['wcpimh_prodst'] ) ) {
		$sanitary_values['wcpimh_prodst'] = $input['wcpimh_prodst'];
		}
		if ( isset( $input['wcpimh_virtual'] ) ) {
		$sanitary_values['wcpimh_virtual'] = $input['wcpimh_virtual'];
		}
		if ( isset( $input['wcpimh_backorders'] ) ) {
		$sanitary_values['wcpimh_backorders'] = $input['wcpimh_backorders'];
		}
		if ( isset( $input['wcpimh_catsep'] ) ) {
		$sanitary_values['wcpimh_catsep'] = sanitize_text_field( $input['wcpimh_catsep'] );
		}
		if ( isset( $input['wcpimh_filter'] ) ) {
		$sanitary_values['wcpimh_filter'] = sanitize_text_field( $input['wcpimh_filter'] );
		}
		if ( isset( $input['wcpimh_rates'] ) ) {
		$sanitary_values['wcpimh_rates'] = $input['wcpimh_rates'];
		}
		if ( isset( $input['wcpimh_catnp'] ) ) {
		$sanitary_values['wcpimh_catnp'] = $input['wcpimh_catnp'];
		}
		// Other tab.
		$sanitary_values['wcpimh_sync'] = ( isset( $wces_settings['wcpimh_sync'] ) ? $wces_settings['wcpimh_sync'] : 'no' );
		$sanitary_values['wcpimh_sync_num'] = ( isset( $wces_settings['wcpimh_sync_num'] ) ? $wces_settings['wcpimh_sync_num'] : 5 );
		$sanitary_values['wcpimh_sync_email'] = ( isset( $wces_settings['wcpimh_sync_email'] ) ? $wces_settings['wcpimh_sync_email'] : 'yes' );

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
	 * Info for holded section.
	 *
	 * @return void
	 */
	public function wces_section_automate()
	{
		esc_html_e( 'Section only for Premium version', 'woocommerce-es' );
		echo  $this->show_get_premium() ;
	}
	
	/**
	 * Info for holded automate section.
	 *
	 * @return void
	 */
	public function wces_section_info()
	{
        echo  sprintf( __( 'Put the connection API key settings in order to connect and sync products. You can go here <a href = "%s" target = "_blank">App Holded API</a>. ', 'woocommerce-es' ), 'https://app.holded.com/api' ) ;
        echo  $this->show_get_premium() ;
    }
    
    public function wcpimh_api_callback()
    {
        printf( '<input class="regular-text" type="password" name="wces_settings[wcpimh_api]" id="wcpimh_api" value="%s">', ( isset( $this->imh_settings['wcpimh_api'] ) ? esc_attr( $this->imh_settings['wcpimh_api'] ) : '' ) );
    }
    
    public function wcpimh_stock_callback()
    {
        ?>
		<select name="wces_settings[wcpimh_stock]" id="wcpimh_stock">
			<?php 
        $selected = ( isset( $this->imh_settings['wcpimh_stock'] ) && $this->imh_settings['wcpimh_stock'] === 'yes' ? 'selected' : '' );
        ?>
			<option value="yes" <?php 
        echo  esc_html( $selected ) ;
        ?>><?php 
        esc_html_e( 'Yes', 'woocommerce-es' );
        ?></option>
			<?php 
        $selected = ( isset( $this->imh_settings['wcpimh_stock'] ) && $this->imh_settings['wcpimh_stock'] === 'no' ? 'selected' : '' );
        ?>
			<option value="no" <?php 
        echo  esc_html( $selected ) ;
        ?>><?php 
        esc_html_e( 'No', 'woocommerce-es' );
        ?></option>
		</select>
		<?php 
    }
    
    public function wcpimh_prodst_callback()
    {
        ?>
		<select name="wces_settings[wcpimh_prodst]" id="wcpimh_prodst">
			<?php 
        $selected = ( isset( $this->imh_settings['wcpimh_prodst'] ) && 'draft' === $this->imh_settings['wcpimh_prodst'] ? 'selected' : '' );
        ?>
			<option value="draft" <?php 
        echo  esc_html( $selected ) ;
        ?>><?php 
        esc_html_e( 'Draft', 'woocommerce-es' );
        ?></option>
			<?php 
        $selected = ( isset( $this->imh_settings['wcpimh_prodst'] ) && 'publish' === $this->imh_settings['wcpimh_prodst'] ? 'selected' : '' );
        ?>
			<option value="publish" <?php 
        echo  esc_html( $selected ) ;
        ?>><?php 
        esc_html_e( 'Publish', 'woocommerce-es' );
        ?></option>
			<?php 
        $selected = ( isset( $this->imh_settings['wcpimh_prodst'] ) && 'pending' === $this->imh_settings['wcpimh_prodst'] ? 'selected' : '' );
        ?>
			<option value="pending" <?php 
        echo  esc_html( $selected ) ;
        ?>><?php 
        esc_html_e( 'Pending', 'woocommerce-es' );
        ?></option>
			<?php 
        $selected = ( isset( $this->imh_settings['wcpimh_prodst'] ) && 'private' === $this->imh_settings['wcpimh_prodst'] ? 'selected' : '' );
        ?>
			<option value="private" <?php 
        echo  esc_html( $selected ) ;
        ?>><?php 
        esc_html_e( 'Private', 'woocommerce-es' );
        ?></option>
		</select>
		<?php 
    }
    
    public function wcpimh_virtual_callback()
    {
        ?>
		<select name="wces_settings[wcpimh_virtual]" id="wcpimh_virtual">
			<?php 
        $selected = ( isset( $this->imh_settings['wcpimh_virtual'] ) && $this->imh_settings['wcpimh_virtual'] === 'no' ? 'selected' : '' );
        ?>
			<option value="no" <?php 
        echo  esc_html( $selected ) ;
        ?>><?php 
        esc_html_e( 'No', 'woocommerce-es' );
        ?></option>
			<?php 
        $selected = ( isset( $this->imh_settings['wcpimh_virtual'] ) && $this->imh_settings['wcpimh_virtual'] === 'yes' ? 'selected' : '' );
        ?>
			<option value="yes" <?php 
        echo  esc_html( $selected ) ;
        ?>><?php 
        esc_html_e( 'Yes', 'woocommerce-es' );
        ?></option>
		</select>
		<?php 
    }
    
    public function wcpimh_backorders_callback()
    {
        ?>
		<select name="wces_settings[wcpimh_backorders]" id="wcpimh_backorders">
			<?php 
        $selected = ( isset( $this->imh_settings['wcpimh_backorders'] ) && $this->imh_settings['wcpimh_backorders'] === 'no' ? 'selected' : '' );
        ?>
			<option value="no" <?php 
        echo  esc_html( $selected ) ;
        ?>><?php 
        esc_html_e( 'No', 'woocommerce-es' );
        ?></option>
			<?php 
        $selected = ( isset( $this->imh_settings['wcpimh_backorders'] ) && $this->imh_settings['wcpimh_backorders'] === 'yes' ? 'selected' : '' );
        ?>
			<option value="yes" <?php 
        echo  esc_html( $selected ) ;
        ?>><?php 
        esc_html_e( 'Yes', 'woocommerce-es' );
        ?></option>
			<?php 
        $selected = ( isset( $this->imh_settings['wcpimh_backorders'] ) && $this->imh_settings['wcpimh_backorders'] === 'notify' ? 'selected' : '' );
        ?>
			<option value="notify" <?php 
        echo  esc_html( $selected ) ;
        ?>><?php 
        esc_html_e( 'Notify', 'woocommerce-es' );
        ?></option>
		</select>
		<?php 
    }
    
    /**
     * Call back for category separation
     *
     * @return void
     */
    public function wcpimh_catsep_callback()
    {
        printf( '<input class="regular-text" type="text" name="wces_settings[wcpimh_catsep]" id="wcpimh_catsep" value="%s">', ( isset( $this->imh_settings['wcpimh_catsep'] ) ? esc_attr( $this->imh_settings['wcpimh_catsep'] ) : '' ) );
    }
    
    public function wcpimh_filter_callback()
    {
        printf( '<input class="regular-text" type="text" name="wces_settings[wcpimh_filter]" id="wcpimh_filter" value="%s">', ( isset( $this->imh_settings['wcpimh_filter'] ) ? esc_attr( $this->imh_settings['wcpimh_filter'] ) : '' ) );
    }
    
    public function wcpimh_rates_callback()
    {
        $rates_options = $this->get_rates();
        if ( false == $rates_options ) {
            return false;
        }
        ?>
		<select name="wces_settings[wcpimh_rates]" id="wcpimh_rates">
			<?php 
        foreach ( $rates_options as $value => $label ) {
            $selected = ( isset( $this->imh_settings['wcpimh_rates'] ) && $this->imh_settings['wcpimh_rates'] === $value ? 'selected' : '' );
            echo  '<option value="' . esc_html( $value ) . '" ' . esc_html( $selected ) . '>' . esc_html( $label ) . '</option>' ;
        }
        ?>
		</select>
		<?php 
    }
    
    public function wcpimh_catnp_callback()
    {
        ?>
		<select name="wces_settings[wcpimh_catnp]" id="wcpimh_catnp">
			<?php 
        $selected = ( isset( $this->imh_settings['wcpimh_catnp'] ) && $this->imh_settings['wcpimh_catnp'] === 'yes' ? 'selected' : '' );
        ?>
			<option value="yes" <?php 
        echo  esc_html( $selected ) ;
        ?>><?php 
        esc_html_e( 'Yes', 'woocommerce-es' );
        ?></option>
			<?php 
        $selected = ( isset( $this->imh_settings['wcpimh_catnp'] ) && $this->imh_settings['wcpimh_catnp'] === 'no' ? 'selected' : '' );
        ?>
			<option value="no" <?php 
        echo  esc_html( $selected ) ;
        ?>><?php 
        esc_html_e( 'No', 'woocommerce-es' );
        ?></option>
		</select>
		<?php 
    }
    
    /**
     * Callback sync field.
     *
     * @return void
     */
    public function wcpimh_sync_callback()
    {
        ?>
		<select name="wces_settings[wcpimh_sync]" id="wcpimh_sync">
			<?php 
        $selected = ( isset( $this->imh_settings['wcpimh_sync'] ) && 'no' === $this->imh_settings['wcpimh_sync'] ? 'selected' : '' );
        ?>
			<option value="no" <?php 
        echo  esc_html( $selected ) ;
        ?>><?php 
        esc_html_e( 'No', 'woocommerce-es' );
        ?></option>

			<?php 
        $selected = ( isset( $this->imh_settings['wcpimh_sync'] ) && 'wcpimh_cron_daily' === $this->imh_settings['wcpimh_sync'] ? 'selected' : '' );
        ?>
			<option value="wcpimh_cron_daily" <?php 
        echo  esc_html( $selected ) ;
        ?>><?php 
        esc_html_e( 'Every day', 'woocommerce-es' );
        ?></option>

			<?php 
        $selected = ( isset( $this->imh_settings['wcpimh_sync'] ) && 'wcpimh_cron_twelve_hours' === $this->imh_settings['wcpimh_sync'] ? 'selected' : '' );
        ?>
			<option value="wcpimh_cron_twelve_hours" <?php 
        echo  esc_html( $selected ) ;
        ?>><?php 
        esc_html_e( 'Every twelve hours', 'woocommerce-es' );
        ?></option>

			<?php 
        $selected = ( isset( $this->imh_settings['wcpimh_sync'] ) && 'wcpimh_cron_six_hours' === $this->imh_settings['wcpimh_sync'] ? 'selected' : '' );
        ?>
			<option value="wcpimh_cron_six_hours" <?php 
        echo  esc_html( $selected ) ;
        ?>><?php 
        esc_html_e( 'Every six hours', 'woocommerce-es' );
        ?></option>

			<?php 
        $selected = ( isset( $this->imh_settings['wcpimh_sync'] ) && 'wcpimh_cron_three_hours' === $this->imh_settings['wcpimh_sync'] ? 'selected' : '' );
        ?>
			<option value="wcpimh_cron_three_hours" <?php 
        echo  esc_html( $selected ) ;
        ?>><?php 
        esc_html_e( 'Every three hours', 'woocommerce-es' );
        ?></option>

			<?php 
        $selected = ( isset( $this->imh_settings['wcpimh_sync'] ) && 'wcpimh_cron_one_hour' === $this->imh_settings['wcpimh_sync'] ? 'selected' : '' );
        ?>
			<option value="wcpimh_cron_one_hour" <?php 
        echo  esc_html( $selected ) ;
        ?>><?php 
        esc_html_e( 'Every hour', 'woocommerce-es' );
        ?></option>

			<?php 
        $selected = ( isset( $this->imh_settings['wcpimh_sync'] ) && 'wcpimh_cron_thirty_minutes' === $this->imh_settings['wcpimh_sync'] ? 'selected' : '' );
        ?>
			<option value="wcpimh_cron_thirty_minutes" <?php 
        echo  esc_html( $selected ) ;
        ?>><?php 
        esc_html_e( 'Every thirty minutes', 'woocommerce-es' );
        ?></option>

			<?php 
        $selected = ( isset( $this->imh_settings['wcpimh_sync'] ) && 'wcpimh_cron_fifteen_minutes' === $this->imh_settings['wcpimh_sync'] ? 'selected' : '' );
        ?>
			<option value="wcpimh_cron_fifteen_minutes" <?php 
        echo  esc_html( $selected ) ;
        ?>><?php 
        esc_html_e( 'Every fifteen minutes', 'woocommerce-es' );
        ?></option>

			<?php 
        $selected = ( isset( $this->imh_settings['wcpimh_sync'] ) && 'wcpimh_cron_five_minutes' === $this->imh_settings['wcpimh_sync'] ? 'selected' : '' );
        ?>
			<option value="wcpimh_cron_five_minutes" <?php 
        echo  esc_html( $selected ) ;
        ?>><?php 
        esc_html_e( 'Every five minutes', 'woocommerce-es' );
        ?></option>
		</select>
		<?php 
    }
    
    /**
     * Callback sync field.
     *
     * @return void
     */
    public function wcpimh_sync_num_callback()
    {
        printf( '<input class="regular-text" type="text" name="wces_settings[wcpimh_sync_num]" id="wcpimh_sync_num" value="%s">', ( isset( $this->imh_settings['wcpimh_sync_num'] ) ? esc_attr( $this->imh_settings['wcpimh_sync_num'] ) : 5 ) );
    }
    
    public function wcpimh_sync_email_callback()
    {
        ?>
		<select name="wces_settings[wcpimh_sync_email]" id="wcpimh_sync_email">
			<?php 
        $selected = ( isset( $this->imh_settings['wcpimh_sync_email'] ) && $this->imh_settings['wcpimh_sync_email'] === 'yes' ? 'selected' : '' );
        ?>
			<option value="yes" <?php 
        echo  esc_html( $selected ) ;
        ?>><?php 
        esc_html_e( 'Yes', 'woocommerce-es' );
        ?></option>
			<?php 
        $selected = ( isset( $this->imh_settings['wcpimh_sync_email'] ) && $this->imh_settings['wcpimh_sync_email'] === 'no' ? 'selected' : '' );
        ?>
			<option value="no" <?php 
        echo  esc_html( $selected ) ;
        ?>><?php 
        esc_html_e( 'No', 'woocommerce-es' );
        ?></option>
		</select>
		<?php 
    }
    
    /**
     * Custom CSS for admin
     *
     * @return void
     */
    public function custom_css()
    {
        // Free Version.
        echo  '
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
			.wp-admin.wcpimh-plugin #wcpimh_prodst {
				width: 150px;
			}
			.wp-admin.wcpimh-plugin #wcpimh_api,
			.wp-admin.wcpimh-plugin #wcpimh_taxinc {
				width: 270px;
			}' ;
        // Not premium version.
        if ( cmk_fs()->is_not_paying() ) {
            echo  '.wp-admin.wcpimh-plugin #wcpimh_catsep, .wp-admin.wcpimh-plugin #wcpimh_filter, .wp-admin.wcpimh-plugin #wcpimh_sync  {
				pointer-events:none;
			}' ;
        }
        echo  '</style>' ;
    }

}
if ( is_admin() ) {
    $wces = new WCIMPH_Admin();
}