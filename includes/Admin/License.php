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

use CLOSE\ConnectEcommerce\Helpers\HELPER;

/**
 * License.
 *
 * @since 1.0.0
 */
class License {
	/**
	 * License key.
	 *
	 * @var string
	 */
	private $options;

	/**
	 * Construct of Class
	 */
	public function __construct( $options = [] ) {
		$settings_base = get_option( 'connect_ecommerce' );
		if ( empty( $settings_base ) ) {
			return;
		}
		$connector     = ! empty( $settings_base['connector'] ) ? $settings_base['connector'] : '';
		$this->options = $options[ $connector ];
		add_action( 'connect_ecommerce_settings_tabs', array( $this, 'add_settings_tab' ) );
		add_action( 'connect_ecommerce_settings_tabs_content', array( $this, 'add_license_content' ) );
		add_action( 'admin_init', array( $this, 'page_init' ) );

		// Creates license activation.
		register_activation_hook( CONECOM_FILE, array( $this, 'license_instance_activation' ) );

		// Creates sync table.
		register_activation_hook( CONECOM_FILE, array( $this, 'process_activation_premium' ) );
	}

	/**
	 * Add settings tab.
	 *
	 * @param array $active_tab Tabs.
	 */
	public function add_settings_tab( $active_tab ) {
		?>
		<a href="?page=connect_ecommerce&tab=license" class="nav-tab <?php echo 'license' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'License', 'connect-ecommerce' ); ?></a>
		<?php
	}

	/**
	 * Add settings tab content.
	 *
	 * @param string $active_tab Active tab.
	 */
	public function add_license_content( $active_tab ) {
		if ( 'license' !== $active_tab ) {
			return;
		}
		echo '<div class="connect-woocommerce-settings license">';
		echo '<div class="license">';

		echo '<form method="post" action="options.php">';
		settings_fields( 'connect_woocommerce_license' );
		do_settings_sections( 'connwoo_settings_admin_license' );
		wp_nonce_field( 'Update_CONN_License_Options', 'wpauto_nonce' );
		submit_button(
			__( 'Save', 'connect-ecommerce' ),
			'primary',
			'submit_license'
		);
		echo '</form>';

		echo '</div>';
		echo '<div class="settings">';
		echo '<h2>' . esc_html__( 'What is the license for?', 'connect-ecommerce' ) . '</h2>';
		echo '<p>';
		$plugin_url = 'https://www.close.technology/wordpress-plugins/connect-woocommerce-' . strtolower( $this->options['name'] ) . '/';
		echo sprintf(
			// translators: %1$s Plugin URL %2$s Name of plugin.
			__( 'With the <a href="%1$s" target="_blank">Connect WooCommerce for %2$s</a> license, you\'ll have updates and automatic fixes to what\'s new or change in your system, so you\'ll always have the latest functionalities for the plugin.', 'connect-ecommerce' ),
			esc_url( $plugin_url ),
			esc_html( $this->options['name'] )
		);
		echo '</p>';
		echo '</div><div class="help">';
		echo '<h2>' . esc_html__( 'How do I get a license?', 'connect-ecommerce' ) . '</h2>';
		echo '<p>';
		echo sprintf(
			// translators: %1$s Plugin URL %2$s Name of plugin.
			__( 'Visit the <a href="%1$s" target="_blank">Connect WooCommerce for %2$s</a> page and purchase the licenses you need, depending on the number of WordPress MultiSites you\'re using.', 'connect-ecommerce' ),
			esc_url( $plugin_url ),
			esc_html( $this->options['name'] )
		);
		echo '</p>';
		echo '<p style="color:#50575e;">' . esc_html__( 'Instance:', 'connect-ecommerce' ) . ' ' . esc_html( get_option( $this->options['slug'] . '_license_instance' ) ) . '</p>';
		echo '</div></div>';
	}

	/**
	 * Page init.
	 */
	public function page_init() {


		/**
		 * ## License
		 * --------------------------- */

		 register_setting(
			'connect_woocommerce_license',
			'connect_ecommerce_license',
			array( $this, 'sanitize_fields_license' )
		);

		add_settings_section(
			'connect_woocommerce_license',
			'',
			'',
			'connwoo_settings_admin_license',
		);
		add_settings_field(
			'connect_ecommerce_license_apikey',
			__( 'License API Key', 'connect-ecommerce' ),
			array( $this, 'license_apikey_callback' ),
			'connwoo_settings_admin_license',
			'connect_woocommerce_license',
		);

		add_settings_field(
			'connect_ecommerce_license_product_id',
			__( 'License Product ID', 'connect-ecommerce' ),
			array( $this, 'license_product_id_callback' ),
			'connwoo_settings_admin_license',
			'connect_woocommerce_license',
		);

		add_settings_field(
			'connect_ecommerce_license_status',
			__( 'License Status', 'connect-ecommerce' ),
			array( $this, 'license_status_callback' ),
			'connwoo_settings_admin_license',
			'connect_woocommerce_license',
		);

		add_settings_field(
			'connect_ecommerce_license_deactivate',
			__( 'Deactivate License', 'connect-ecommerce' ),
			array( $this, 'license_deactivate_callback' ),
			'connwoo_settings_admin_license',
			'connect_woocommerce_license',
		);
	}

	/**
	 * # Library Updater
	 * ---------------------------------------------------------------------------------------------------- */
	/**
	 * Sanitize fiels before saves in DB
	 *
	 * @param array $input Input fields.
	 * @return void
	 */
	public function sanitize_fields_license( $input ) {
		if ( isset( $_POST[ $this->options['slug'] . '_license_apikey' ] ) ) {
			update_option( $this->options['slug'] . '_license_apikey', sanitize_text_field( $input[ $this->options['slug'] . '_license_apikey' ] ) );
		}

		if ( isset( $_POST[ $this->options['slug'] . '_license_product_id' ] ) ) {
			update_option( $this->options['slug'] . '_license_product_id', sanitize_text_field( $input[ $this->options['slug'] . '_license_product_id' ] ) );
		}

		$this->validate_license( $_POST );
	}/**
		* Callback for Setting License API Key
		*
		* @return void
		*/
	public function license_apikey_callback() {
		$value = get_option( $this->options['slug'] . '_license_apikey' );
		echo '<input type="text" class="regular-text" name="' . esc_html( $this->options['slug'] ) . '_license_apikey" id="connwoo_license_apikey" value="' . esc_html( $value ) . '">';
	}/**
		* Callback for Setting license Folder
		*
		* @return void
		*/
	public function license_product_id_callback() {
		$value = get_option( $this->options['slug'] . '_license_product_id' );
		echo '<input type="text" class="regular-text" name="' . esc_html( $this->options['slug'] ) . '_license_product_id" size="25" id="connwoo_license_product_id" value="' . esc_html( $value ) . '">';
	}/**
		* Callback for Setting license API key
		*
		* @return void
		*/
	public function license_status_callback() {
		if ( $this->get_api_key_status( true ) ) {
			$license_status_check = esc_html__( 'Activated', 'connect-ecommerce' );
			update_option( $this->options['slug'] . '_license_activated', 'Activated' );
			update_option( $this->options['slug'] . '_license_deactivate_checkbox', 'off' );
		} else {
			$license_status_check = esc_html__( 'Deactivated', 'connect-ecommerce' );
		}

		echo esc_attr( $license_status_check );
	}/**
		* Callback for Setting license Secret key
		*
		* @return void
		*/
	public function license_deactivate_callback() {
		echo '<input type="checkbox" id="connwoo_license_deactivate_checkbox" name="' . esc_html( $this->options['slug'] ) . '_license_deactivate_checkbox" value="on"';
		echo checked( get_option( $this->options['slug'] . '_license_deactivate_checkbox' ), 'on' );
		echo '/>';
		echo '<span class="description">';
		esc_html_e( 'Deactivates License so it can be used on another site.', 'connect-ecommerce' );
		echo '</span>';
	}
	/**
	 * Validates license option
	 *
	 * @param array $input Settings input option.
	 * @return mixed|string
	 */
	public function validate_license( $input ) {
		// Load existing options, validate, and update with changes from input before returning.
		$api_key           = trim( $input[ $this->options['slug'] . '_license_apikey' ] );
		$activation_status = get_option( $this->options['slug'] . '_license_activated' );
		$checkbox_status   = get_option( $this->options['slug'] . '_license_deactivate_checkbox' );
		$current_api_key   = ! empty( get_option( $this->options['slug'] . '_license_apikey' ) ) ? get_option( $this->options['slug'] . '_license_apikey' ) : '';

		// @since 2.3
		if ( isset( $input[ $this->options['slug'] . '_license_product_id' ] ) ) {
			$new_product_id = absint( $input[ $this->options['slug'] . '_license_product_id' ] );		if ( ! empty( $new_product_id ) ) {
				update_option( $this->options['slug'] . '_license_product_id', $new_product_id );
			}
		}

		// Deactivates API Key key activation.
		if ( isset( $input[ $this->options['slug'] . '_license_deactivate_checkbox' ] ) && 'on' === $input[ $this->options['slug'] . '_license_deactivate_checkbox' ] ) {
			$args = array(
				'api_key' => ! empty( $api_key ) ? $api_key : '',
			);
			$deactivation_result = $this->license_deactivate( $args );
			
			if ( ! empty( $deactivation_result ) && is_array( $deactivation_result ) ) {
				if ( true === $deactivation_result['success'] && true === $deactivation_result['deactivated'] ) {
					update_option( $this->options['slug'] . '_license_activated', 'Deactivated' );
					update_option( $this->options['slug'] . '_license_apikey', '' );
					update_option( $this->options['slug'] . '_license_product_id', '' );
					add_settings_error( 'wc_am_deactivate_text', 'deactivate_msg', esc_html__( 'License Connect WooCommerce deactivated. ', 'connect-ecommerce' ) . esc_attr( "{$deactivation_result['activations_remaining']}." ), 'updated' );
		
					return;
				}
	
				if ( isset( $deactivation_result['data']['error_code'] ) && ! empty( $this->data ) && ! empty( $this->options['slug'] . '_license_activated' ) ) {
					add_settings_error( 'wc_am_client_error_text', 'wc_am_client_error', esc_attr( "{$deactivation_result['data']['error']}" ), 'error' );
					update_option( $this->options['slug'] . '_license_activated', 'Deactivated' );
				}
			}
			// Remove anyway.
			update_option( $this->options['slug'] . '_license_activated', 'Deactivated' );
			update_option( $this->options['slug'] . '_license_apikey', '' );
			update_option( $this->options['slug'] . '_license_product_id', '' );
			return;
		}

		// Should match the settings_fields() value.
		if ( 'Deactivated' == $activation_status || '' == $activation_status || '' == $api_key || 'on' == $checkbox_status || $current_api_key != $api_key ) {		/**
			* If this is a new key, and an existing key already exists in the database,
			* try to deactivate the existing key before activating the new key.
			*/
			if ( ! empty( $current_api_key ) && $current_api_key != $api_key ) {
				$this->replace_license_key( $current_api_key );
			}		$activation_result = $this->license_activate( $api_key );			if ( ! empty( $activation_result ) ) {
				$activate_results = json_decode( $activation_result, true );
	
				if ( true === $activate_results['success'] && true === $activate_results['activated'] ) {
					add_settings_error( 'activate_text', 'activate_msg', __( 'Connect WooCommerce activated. ', 'connect-ecommerce' ) . esc_attr( "{$activate_results['message']}." ), 'updated' );
		
					update_option( $this->options['slug'] . '_license_apikey', $api_key );
					update_option( $this->options['slug'] . '_license_activated', 'Activated' );
					update_option( $this->options['slug'] . '_license_deactivate_checkbox', 'off' );
				}
	
				if ( false == $activate_results && ! empty( get_option( $this->options['slug'] . '_license_activated' ) ) ) {
					add_settings_error( 'api_key_check_text', 'api_key_check_error', esc_html__( 'Connection failed to the License Key API server. Try again later. There may be a problem on your server preventing outgoing requests, or the store is blocking your request to activate the plugin/theme.', 'connect-ecommerce' ), 'error' );
					update_option( $this->options['slug'] . '_license_activated', 'Deactivated' );
				}
	
				if ( isset( $activate_results['data']['error_code'] ) && ! empty( get_option( $this->options['slug'] . '_license_activated' ) ) ) {
					add_settings_error( 'wc_am_client_error_text', 'wc_am_client_error', esc_attr( "{$activate_results['data']['error']}" ), 'error' );
					update_option( $this->options['slug'] . '_license_activated', 'Deactivated' );
				}
			} else {
				add_settings_error( 'not_activated_empty_response_text', 'not_activated_empty_response_error', esc_html__( 'The API Key activation could not be commpleted due to an unknown error possibly on the store server The activation results were empty.', 'connect-ecommerce' ), 'updated' );
			}
		} // End Plugin Activation
	}
	/**
	 * Sends the request to activate to the API Manager.
	 *
	 * @param array $api_key API Key to activate.
	 *
	 * @return string
	 */
	public function license_activate( $api_key ) {
		if ( empty( $api_key ) ) {
			add_settings_error( 'not_activated_text', 'not_activated_error', esc_html__( 'The API Key is missing from the deactivation request.', 'connect-ecommerce' ), 'updated' );		return '';
		}

		$defaults            = $this->get_license_defaults( 'activate', true );
		$defaults['api_key'] = $api_key;
		$target_url          = esc_url_raw( $this->create_software_api_url( $defaults ) );
		$request             = wp_safe_remote_post( $target_url, array( 'timeout' => 15 ) );

		if ( is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) != 200 ) {
			// Request failed.
			return '';
		}

		return wp_remote_retrieve_body( $request );
	}/**
		* Sends the request to deactivate to the API Manager.
		*
		* @param array $args Arguments to deactive.
		*
		* @return string
		*/
	public function license_deactivate( $args ) {
		if ( empty( $args ) ) {
			add_settings_error( 'not_deactivated_text', 'not_deactivated_error', esc_html__( 'The API Key is missing from the deactivation request.', 'connect-ecommerce' ), 'updated' );		return '';
		}

		$defaults   = $this->get_license_defaults( 'deactivate' );
		$args       = wp_parse_args( $defaults, $args );
		$target_url = esc_url_raw( $this->create_software_api_url( $args ) );
		$request    = wp_safe_remote_post( $target_url, array( 'timeout' => 15 ) );
		$body_json  = wp_remote_retrieve_body( $request );
		$result_api = json_decode( $body_json, true );

		$error = ! empty( $result_api['error'] ) ? $result_api['error'] : '';

		if ( is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) != 200 || $error ) {
			// Request failed.
			add_settings_error(
				'not_deactivated_empty_response_text',
				'not_deactivated_empty_response_error',
				$error,
				'error'
			);
			return;
		}

		return $result_api;
	}
	/**
	 * Returns true if the API Key status is Activated.
	 *
	 * @since 2.1
	 *
	 * @param bool $live Do not set to true if using to activate software. True is for live status checks after activation.
	 *
	 * @return bool
	 */
	public function get_api_key_status( $live = false ) {
		/**
		 * Real-time result.
		 *
		 * @since 2.5.1
		 */
		if ( $live ) {
			$license_status = $this->license_key_status();

			return ! empty( $license_status ) && ! empty( $license_status['data']['activated'] ) && $license_status['data']['activated'];
		}

		/**
		 * If $live === false.
		 *
		 * Stored result when first activating software.
		 */
		return get_option( $this->options['slug'] . '_license_activated' ) == 'Activated';
	}

	/**
	 * Returns the API Key status by querying the Status API function from the WooCommerce API Manager on the server.
	 *
	 * @return array|mixed|object
	 */
	public function license_key_status() {
		$status = $this->status();

		return ! empty( $status ) ? json_decode( $this->status(), true ) : $status;
	}

	/**
	 * Sends the status check request to the API Manager.
	 *
	 * @return bool|string
	 */
	public function status() {
		if ( empty( get_option( $this->options['slug'] . '_license_apikey' ) ) ) {
			return '';
		}

		$defaults   = $this->get_license_defaults( 'status' );
		$target_url = esc_url_raw( $this->create_software_api_url( $defaults ) );
		$request    = wp_safe_remote_post( $target_url, array( 'timeout' => 15 ) );

		if ( is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) != 200 ) {
			// Request failed.
			return '';
		}

		return wp_remote_retrieve_body( $request );
	}

	/**
	 * Get license defaults
	 *
	 * @param string $action            Action to license defaults.
	 * @param string $software_version Software version.
	 * @return array
	 */
	private function get_license_defaults( $action, $software_version = false ) {
		$api_key    = get_option( $this->options['slug'] . '_license_apikey' );
		$product_id = get_option( $this->options['slug'] . '_license_product_id' );

		$defaults = array(
			'wc_am_action' => $action,
			'api_key'      => $api_key,
			'product_id'   => $product_id,
			'instance'     => get_option( $this->options['slug'] . '_license_instance' ),
			'object'       => str_ireplace( array( 'http://', 'https://' ), '', home_url() ),
		);

		if ( $software_version ) {
			$defaults['software_version'] = $this->options['version'];
		}

		return $defaults;
	}

	/**
	 * Builds the URL containing the API query string for activation, deactivation, and status requests.
	 *
	 * @param array $args Arguments data.
	 *
	 * @return string
	 */
	public function create_software_api_url( $args ) {
		return add_query_arg( 'wc-api', 'wc-am-api', $this->options['api_url'] ) . '&' . http_build_query( $args );
	}

	/**
	 * Generate the default data.
	 */
	public function license_instance_activation() {
		$instance_exists = get_option( $this->options['slug'] . '_license_instance' );

		if ( ! $instance_exists ) {
			update_option( $this->options['slug'] . '_license_instance', wp_generate_password( 20, false ) );
		}
	}

	/**
	 * Deactivate the current API Key before activating the new API Key
	 *
	 * @param string $current_api_key current api key.
	 */
	public function replace_license_key( $current_api_key ) {
		$args = array(
			'api_key' => $current_api_key,
		);

		$this->license_deactivate( $args );
	}

	/**
	 * Sends and receives data to and from the server API
	 *
	 * @since  2.0
	 *
	 * @param array $args Arguments for query.
	 *
	 * @return bool|string $response
	 */
	public function send_query( $args ) {
		$target_url = esc_url_raw( add_query_arg( 'wc-api', 'wc-am-api', $this->options['api_url'] ) . '&' . http_build_query( $args ) );
		$request    = wp_safe_remote_post( $target_url, array( 'timeout' => 15 ) );

		if ( is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) != 200 ) {
			return false;
		}

		$response = wp_remote_retrieve_body( $request );

		return ! empty( $response ) ? $response : false;
	}

	/**
	 * Check for updates against the remote server.
	 *
	 * @since  2.0
	 *
	 * @param object $transient Transient plugins.
	 *
	 * @return object
	 */
	public function update_check( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$args = array(
			'wc_am_action' => 'update',
			'slug'         => $this->options['plugin_slug'],
			'plugin_name'  => $this->options['plugin_name'],
			'version'      => $this->options['version'],
			'product_id'   => get_option( $this->options['slug'] . '_license_product_id' ),
			'api_key'      => get_option( $this->options['slug'] . '_license_apikey' ),
			'instance'     => get_option( $this->options['slug'] . '_license_instance' ),
		);

		// Check for a plugin update.
		$response = json_decode( $this->send_query( $args ), true );

		if ( isset( $response['data']['error_code'] ) ) {
			add_settings_error( 'wc_am_client_error_text', 'wc_am_client_error', "{$response['data']['error']}", 'error' );
		}

		if ( false !== $response && true === $response['success'] ) {
			$new_version  = (string) $response['data']['package']['new_version'];
			$curr_version = (string) $this->options['version'];		$package = array(
				'id'             => $response['data']['package']['id'],
				'slug'           => $response['data']['package']['slug'],
				'plugin'         => $response['data']['package']['plugin'],
				'new_version'    => $response['data']['package']['new_version'],
				'url'            => $response['data']['package']['url'],
				'tested'         => $response['data']['package']['tested'],
				'package'        => $response['data']['package']['package'],
				'upgrade_notice' => $response['data']['package']['upgrade_notice'],
			);
			if ( ! empty( $new_version ) && ! empty( $curr_version ) ) {
				if ( version_compare( $new_version, $curr_version, '>' ) ) {
					$transient->response[ $this->options['plugin_name'] ] = (object) $package;
					unset( $transient->no_update[ $this->options['plugin_name'] ] );
				}
			}
		}

		return $transient;
	}
	
	/**
	 * API request for informatin.
	 *
	 * If `$action` is 'query_plugins' or 'plugin_information', an object MUST be passed.
	 * If `$action` is 'hot_tags` or 'hot_categories', an array should be passed.
	 *
	 * @param false|object|array $result The result object or array. Default false.
	 * @param string             $action The type of information being requested from the Plugin Install API.
	 * @param object             $args   Arguments of object.
	 *
	 * @return object
	 */
	public function information_request( $result, $action, $args ) {
		// Check if this plugins API is about this plugin.
		if ( isset( $args->slug ) ) {
			if ( $this->options['plugin_slug'] != $args->slug ) {
				return $result;
			}
		} else {
			return $result;
		}

		$args = array(
			'wc_am_action' => 'plugininformation',
			'plugin_name'  => $this->options['plugin_slug'],
			'version'      => $this->options['version'],
			'product_id'   => get_option( $this->options['slug'] . '_license_product_id' ),
			'api_key'      => get_option( $this->options['slug'] . '_license_apikey' ),
			'instance'     => get_option( $this->options['slug'] . '_license_instance' ),
			'object'       => str_ireplace( array( 'http://', 'https://' ), '', home_url() ),
		);

		$response = unserialize( $this->send_query( $args ) );

		if ( isset( $response ) && is_object( $response ) && false !== $response ) {
			return $response;
		}

		return $result;
	}

	/**
	 * Check for external blocking contstant.
	 */
	public function check_external_blocking() {
		// show notice if external requests are blocked through the WP_HTTP_BLOCK_EXTERNAL constant.
		if ( defined( 'WP_HTTP_BLOCK_EXTERNAL' ) && true === WP_HTTP_BLOCK_EXTERNAL ) {
			// check if our API endpoint is in the allowed hosts.
			$host = parse_url( $this->options['api_url'], PHP_URL_HOST );

			if ( ! defined( 'WP_ACCESSIBLE_HOSTS' ) || stristr( WP_ACCESSIBLE_HOSTS, $host ) === false ) {
				?>
				<div class="notice notice-error">
					<p>
						<?php
						printf(
							// translators: %1$s Name of library %2$s host %3$s Accesible hosts.
							esc_html__( '<b>Warning!</b> You\'re blocking external requests which means you won\'t be able to get %1$s updates. Please add %2$s to %3$s.', 'connect-ecommerce' ),
							'Connect WooCommerce',
							'<strong>' . esc_html( $host ) . '</strong>',
							'<code>WP_ACCESSIBLE_HOSTS</code>'
						);
						?>
					</p>
				</div>
				<?php
			}
		}
	}

	/**
	 * Creates the database
	 *
	 * @since  1.0
	 * @access private
	 * @return void
	 */
	private function process_activation_premium() {
		HELPER::create_sync_table( $this->options['table_sync'] );

		// Migrates options.
		$old_settings = get_option( 'imhset' );
		if ( ! empty( $old_settings ) ) {
			$new_settings = array();
			foreach ( $old_settings as $key => $value ) {
				$new_settings[ str_replace( 'wcpimh_', '', $key ) ] = $value;
			}

			update_option( $this->options['slug'], $new_settings );
			delete_option( 'imhset' );
		}

		$old_settings_public = get_option( 'imhset_public' );
		if ( ! empty( $old_settings_public ) ) {
			update_option( 'connect_ecommerce_public', $old_settings_public );
			delete_option( 'imhset_public' );
		}
	}
}
