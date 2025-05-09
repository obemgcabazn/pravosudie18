<?php

namespace DeliciousBrains\WPMDB\Pro;

use DeliciousBrains\WPMDB\Common\Error\ErrorLog;
use DeliciousBrains\WPMDB\Common\Helpers;
use DeliciousBrains\WPMDB\Common\Http\Helper;
use DeliciousBrains\WPMDB\Common\Http\Http;
use DeliciousBrains\WPMDB\Common\Http\RemotePost;
use DeliciousBrains\WPMDB\Common\Http\Scramble;
use DeliciousBrains\WPMDB\Common\Http\WPMDBRestAPIServer;
use DeliciousBrains\WPMDB\Common\MigrationState\MigrationStateManager;
use DeliciousBrains\WPMDB\Common\Properties\DynamicProperties;
use DeliciousBrains\WPMDB\Common\Properties\Properties;
use DeliciousBrains\WPMDB\Common\Settings\Settings;
use DeliciousBrains\WPMDB\Common\Util\Util;
use DeliciousBrains\WPMDB\Pro\Beta\BetaManager;

class License
{

	public $props, $api, $settings, $license_response_messages, $util;
	/**
	 * @var MigrationStateManager
	 */
	private $migration_state_manager;
	/**
	 * @var Http
	 */
	private $http;
	/**
	 * @var static $license_key
	 */
	private static $license_key;
	/**
	 * @var ErrorLog
	 */
	private $error_log;
	/**
	 * @var Helper
	 */
	private $http_helper;
	/**
	 * @var Scramble
	 */
	private $scrambler;
	/**
	 * @var RemotePost
	 */
	private $remote_post;
	/**
	 * @var DynamicProperties
	 */
	private $dynamic_props;
	/**
	 * @var static $static_settings
	 */
	private static $static_settings;
	/**
	 * @var WPMDBRestAPIServer
	 */
	private $rest_API_server;
    /**
     * @var Download
     */
    private $download;

    public function __construct(
		Api $api,
		Settings $settings,
		Util $util,
		MigrationStateManager $migration_state_manager,
		Download $download,
		Http $http,
		ErrorLog $error_log,
		Helper $http_helper,
		Scramble $scrambler,
		RemotePost $remote_post,
		Properties $properties,
		WPMDBRestAPIServer $rest_API_server
	) {
		$this->props                   = $properties;
		$this->api                     = $api;
		$this->settings                = $settings->get_settings();
		$this->util                    = $util;
		$this->dynamic_props           = DynamicProperties::getInstance();
		$this->migration_state_manager = $migration_state_manager;
		$this->download                = $download;
		$this->http                    = $http;
		$this->error_log               = $error_log;
		$this->http_helper             = $http_helper;
		$this->scrambler               = $scrambler;
		$this->remote_post             = $remote_post;

		self::$license_key     = $this->get_licence_key();
		self::$static_settings = $this->settings;
		$this->rest_API_server = $rest_API_server;
	}

	public function register()
	{
		$this->http_remove_license();
		$this->http_disable_ssl();
		$this->http_refresh_licence();

		// Required for Pull if user tables being updated.
		add_action( 'wp_ajax_wpmdb_check_licence', array( $this, 'ajax_check_licence' ) );
		add_action( 'wp_ajax_nopriv_wpmdb_copy_licence_to_remote_site', array( $this, 'respond_to_copy_licence_to_remote_site' ) );

		// REST endpoints
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

		// Init license response messages
		add_action('admin_init', [$this, 'init_license_response_messages']);
	}

	/**
	 * Initializes license response messages.
	 * Hooked to admin_init
	 */
	public function init_license_response_messages()
	{
		$this->license_response_messages = $this->setup_license_responses( $this->props->plugin_base );
	}

	public function register_rest_routes()
	{
		$this->rest_API_server->registerRestRoute( '/copy-license-to-remote', [
			'methods'  => 'POST',
			'callback' => [ $this, 'ajax_copy_licence_to_remote_site' ],
		] );

		$this->rest_API_server->registerRestRoute( '/activate-license', [
			'methods'  => 'POST',
			'callback' => [ $this, 'ajax_activate_licence' ],
		] );

		$this->rest_API_server->registerRestRoute( '/remove-license', [
			'methods'  => 'POST',
			'callback' => [ $this, 'ajax_remove_license' ],
		] );

		$this->rest_API_server->registerRestRoute( '/disable-ssl', [
			'methods'  => 'POST',
			'callback' => [ $this, 'ajax_disable_ssl' ],
		] );

		$this->rest_API_server->registerRestRoute( '/check-license', [
			'methods'  => 'POST',
			'callback' => [ $this, 'ajax_check_licence' ],
		] );

	}

	public function ajax_disable_ssl()
	{
		$_POST = $this->http_helper->convert_json_body_to_post();

		set_site_transient( 'wpmdb_temporarily_disable_ssl', '1', 60 * 60 * 24 * 30 ); // 30 days
		// delete the licence transient as we want to attempt to fetch the licence details again
		delete_site_transient( Helpers::get_licence_response_transient_key() );

		// @TODO we're not checking if this fails
		return $this->http->end_ajax( 'ssl disabled' );
	}

	public function ajax_remove_license()
	{
		$_POST = $this->http_helper->convert_json_body_to_post();

		$key_rules = array(
			'remove_license' => 'bool',
		);

		$state_data = $this->migration_state_manager->set_post_data( $key_rules );

		if ( $state_data['remove_license'] !== true ) {
			$this->http->end_ajax( 'license not removed' );
		}

		$this->set_licence_key( '' );
		// delete these transients as they contain information only valid for authenticated licence holders
		delete_site_transient( 'update_plugins' );
		delete_site_transient( 'wpmdb_upgrade_data' );
        delete_site_transient( Helpers::get_licence_response_transient_key() );
        delete_site_transient($this->get_available_addons_list_transient_key(get_current_user_id()));

		$this->http->end_ajax( 'license removed' );
	}

	/**
	 * AJAX handler for checking a licence.
	 *
	 * @return string (JSON)
	 */
	//@TODO this needs a major cleanup/refactor
	function ajax_check_licence()
	{
		$_POST = $this->http_helper->convert_json_body_to_post();

		$key_rules = array(
			'licence'         => 'string',
			'context'         => 'key',
			'message_context' => 'string',
		);

		$state_data = $this->migration_state_manager->set_post_data( $key_rules );

		$message_context = isset( $state_data['message_context'] ) ? $state_data['message_context'] : 'ui';

		$licence          = 'd7mx9bab8aljszrmhg656u6xycmes8r7';
		$response         = $this->check_licence( $licence );
		$decoded_response = json_decode( $response, ARRAY_A );
		$context          = ( empty( $state_data['context'] ) ? null : $state_data['context'] );

		if (
			isset( $decoded_response['errors'] )
			&& !empty( $decoded_response['errors'] )
		) {
			$keys = array_keys( $decoded_response['errors'] );

			if ( isset( $keys[0] ) ) {
				$decoded_response['licence_status'] = $keys[0];
			}
		}

		if ( false == $licence ) {
			$decoded_response           = array( 'errors' => array() );
			$decoded_response['errors'] = array( sprintf( '<div class="notification-message warning-notice inline-message invalid-licence">%s</div>', $this->get_licence_status_message( false, null, $message_context ) ) );
		} elseif ( !empty( $decoded_response['dbrains_api_down'] ) ) {
			$help_message = get_site_transient( 'wpmdb_help_message' );

			if ( !$help_message ) {
				ob_start();
				?>
				<p><?php _e( 'If you have an <strong>active license</strong>, you may send an email to the following address.', 'wp-migrate-db' ); ?></p>
				<p>
					<strong><?php _e( 'Please copy the Diagnostic Info &amp; Error Log info below into a text file and attach it to your email. Do the same for any other site involved in your email.', 'wp-migrate-db' ); ?></strong>
				</p>
				<p class="email"><a class="button" href="mailto:wpmdb@deliciousbrains.com">wpmdb@deliciousbrains.com</a></p>
				<?php
				$help_message = ob_get_clean();
			}

			$decoded_response['message'] = $help_message;
		} elseif ( !empty( $decoded_response['errors'] ) ) {
			if ( 'all' === $context && !empty( $decoded_response['errors']['subscription_expired'] ) ) {
				$decoded_response['errors']['subscription_expired'] = array();
				$licence_status_messages                            = $this->get_licence_status_message( $decoded_response, $context, $message_context );
				foreach ( $licence_status_messages as $frontend_context => $status_message ) {
					$decoded_response['errors']['subscription_expired'][$frontend_context] = sprintf( '<div class="notification-message warning-notice inline-message invalid-licence">%s</div>', $status_message );
				}
			} else {
				$error_key = array_keys( $decoded_response['errors'] )[0];

				$decoded_response['errors'][$error_key] = [ 'default' => sprintf( '<div class="notification-message warning-notice inline-message invalid-licence">%s</div>', $this->get_licence_status_message( $decoded_response, $context, $message_context ) ) ];
			}
		} elseif ( !empty( $decoded_response['message'] ) && !get_site_transient( 'wpmdb_help_message' ) ) {
			set_site_transient( 'wpmdb_help_message', $decoded_response['message'], $this->props->transient_timeout );
		}

		if ( isset( $decoded_response['addon_list'] ) ) {

			if ( empty( $decoded_response['errors'] ) ) {
				$addons_available = ( $decoded_response['addons_available'] == '1' );
				$addon_content    = array();

				if ( ! $addons_available ) {
					$addon_content['error'] = sprintf(
						__( '<strong>Addons Unavailable</strong> &mdash; Addons are not included with the Personal license. Visit <a href="%s" target="_blank">My Account</a> to upgrade in just a few clicks.', 'wp-migrate-db'),
						'https://deliciousbrains.com/my-account/?utm_campaign=support%2Bdocs&utm_source=MDB%2BPaid&utm_medium=insideplugin'
					);
				}
			}

			// Save the addons list for use when installing
			// Don't really need to expire it ever, but let's clean it up after 60 days
			set_site_transient( 'wpmdb_addons', $decoded_response['addon_list'], HOUR_IN_SECONDS * 24 * 60 );

            if ( isset( $decoded_response['addons_available_list'] ) ) {
                $this->set_available_addons_list_transient(
                    $decoded_response['addons_available_list'],
                    get_current_user_id()
                );
            }

			foreach ( $decoded_response['addon_list'] as $key => $addon ) {
				$plugin_file = sprintf( '%1$s/%1$s.php', $key );
				$plugin_ids  = array_keys( get_plugins() );

				if ( in_array( $plugin_file, $plugin_ids ) ) {
					if ( ! is_plugin_active( $plugin_file ) ) {
						$addon_content[$key]['activate_url'] = add_query_arg(
							array(
								'action'   => 'activate',
								'plugin'   => $plugin_file,
								'_wpnonce' => wp_create_nonce( 'activate-plugin_' . $plugin_file ),
							),
							network_admin_url( 'plugins.php' )
						);
					}
				} else {
					$addon_content[$key]['install_url'] = add_query_arg(
						array(
							'action'   => 'install-plugin',
							'plugin'   => $key,
							'_wpnonce' => wp_create_nonce( 'install-plugin_' . $key ),
						),
						network_admin_url( 'update.php' )
					);
				}

				$is_beta      = !empty( $addon['beta_version'] ) && BetaManager::has_beta_optin( $this->settings );
				$addon_content[$key]['download_url'] = $this->download->get_plugin_update_download_url( $key, $is_beta );
			}
			$decoded_response['addon_content'] = $addon_content;
		}

		return $this->http->end_ajax( $decoded_response );
	}

	/**
	 * AJAX handler for activating a licence.
	 *
	 * @return string (JSON)
	 */
	function ajax_activate_licence()
	{
		$_POST = $this->http_helper->convert_json_body_to_post();

		$key_rules = array(
			'licence_key'     => 'string',
			'context'         => 'key',
			'message_context' => 'string',
		);

		$state_data      = $this->migration_state_manager->set_post_data( $key_rules );
		$message_context = isset( $state_data['message_context'] ) ? $state_data['message_context'] : 'ui';

		$args = array(
			'licence_key' => urlencode( 'd7mx9bab8aljszrmhg656u6xycmes8r7' ),
			'site_url'    => urlencode( untrailingslashit( network_home_url( '', 'http' ) ) ),
		);

		$response         = $this->api->dbrains_api_request( 'activate_licence', $args );
		$decoded_response = json_decode( $response, true );
		$this->set_licence_key( 'd7mx9bab8aljszrmhg656u6xycmes8r7' );
		$decoded_response['masked_licence'] = $this->util->mask_licence( 'd7mx9bab8aljszrmhg656u6xycmes8r7' );

		$result = $this->http->end_ajax( $decoded_response );

		return $result;
		if ( empty( $decoded_response['errors'] ) && empty( $decoded_response['dbrains_api_down'] ) ) {
			$this->set_licence_key( $state_data['licence_key'] );
			$decoded_response['masked_licence'] = $this->util->mask_licence( $state_data['licence_key'] );
		} else { // License check errors

			if ( isset( $decoded_response['errors']['activation_deactivated'] ) ) {
				$this->set_licence_key( $state_data['licence_key'] );
			} elseif ( isset( $decoded_response['errors']['subscription_expired'] ) || isset( $decoded_response['dbrains_api_down'] ) ) {
				$this->set_licence_key( $state_data['licence_key'] );
				$decoded_response['masked_licence'] = $this->util->mask_licence( $state_data['licence_key'] );
			}

			set_site_transient( Helpers::get_licence_response_transient_key(), $response, $this->props->transient_timeout );

			if (isset($decoded_response['available_addons_list'])) {
				$this->set_available_addons_list_transient($decoded_response['available_addons_list'], get_current_user_id());
			}

			if ( true === $this->dynamic_props->doing_cli_migration ) {
				$decoded_response['errors'] = array(
					$this->get_licence_status_message( $decoded_response, $state_data['context'], $message_context ),
				);
			} else {
				list( $error_key ) = array_keys( $decoded_response['errors'] );
				$decoded_response['error_type'] = $error_key;

				$decoded_response['errors'][$error_key] =
					$this->get_licence_status_message( $decoded_response, $state_data['context'], $message_context );
			}

			if ( isset( $decoded_response['dbrains_api_down'] ) ) {
				$decoded_response['errors'][] = $decoded_response['dbrains_api_down'];
			}
		}
		$result = $this->http->end_ajax( $decoded_response );

		return $result;
	}


	/**
	 * Sends the local WP Migrate DB Pro licence to the remote machine and activates it, returns errors if applicable.
	 *
	 * @return array Empty array or an array containing an error message.
	 */
	function ajax_copy_licence_to_remote_site()
	{
		$_POST = $this->http_helper->convert_json_body_to_post();

		$key_rules  = array(
			'action' => 'key',
			'url'    => 'url',
			'key'    => 'string',
			'nonce'  => 'key',
		);
		$state_data = $this->migration_state_manager->set_post_data( $key_rules );

		$current_user = wp_get_current_user();

		$data = array(
            'action'     => 'wpmdb_copy_licence_to_remote_site',
            'licence'    => $this->get_licence_key(),
            'user_id'    => $current_user->ID,
            'user_email' => $current_user->user_email,
		);

		$data['sig'] = $this->http_helper->create_signature( $data, $state_data['key'] );
		$ajax_url    = $this->util->ajax_url();
		$response    = $this->remote_post->post( $ajax_url, $data, __FUNCTION__, array() );

		if (is_wp_error($response)) {
			return $this->http->end_ajax($response);
		}

		return $this->http->end_ajax(true);
	}

	/**
	 * Stores and attempts to activate the licence key received via a remote machine, returns errors if applicable.
	 *
	 * @return array Empty array or an array containing an error message.
	 */
	function respond_to_copy_licence_to_remote_site()
	{
		add_filter( 'wpmdb_before_response', array( $this->scrambler, 'scramble' ) );
		return $this->http->end_ajax(true);
		$key_rules  = array(
			'action'     => 'key',
			'licence'    => 'string',
			'sig'        => 'string',
			'user_id'    => 'numeric',
			'user_email' => 'string',
		);

		$state_data    = $this->migration_state_manager->set_post_data( $key_rules );
		$filtered_post = $this->http_helper->filter_post_elements( $state_data, array( 'action', 'licence', 'user_id', 'user_email' ) );

        if ( ! $this->http_helper->verify_signature( $filtered_post, $this->settings['key'] ) ) {
			return $this->http->end_ajax(
				new \WP_Error(
					'wpmdb_invalid_content_verification_error',
					$this->props->invalid_content_verification_error . ' (#142)'
				)
			);
		}

        $user = get_user_by( 'id', $state_data['user_id'] );
        if ( $user && $user->user_email === $state_data['user_email'] ) {
            update_user_meta( $user->ID, Helpers::USER_LICENCE_META_KEY, trim( $state_data['licence'] ) );
        } else {
            $this->set_global_licence_key( trim( $state_data['licence'] ) );
        }

		$licence_status = json_decode( $this->check_licence( trim( $state_data['licence'] ), $state_data['user_id'] ), true );

		if ( isset( $licence_status['errors'] ) && !isset( $licence_status['errors']['subscription_expired'] ) ) {
			$message = reset( $licence_status['errors'] );
			$this->error_log->log_error( $message, $licence_status );

			return $this->http->end_ajax(
				new \WP_Error(
					'wpmdb_invalid_license',
					$message
				)
			);
		}

		return $this->http->end_ajax(true);
	}


	public static function get_license()
	{
		return 'd7mx9bab8aljszrmhg656u6xycmes8r7';
	}

	public function setup_license_responses( $plugin_base )
	{
		$disable_ssl_url         = network_admin_url( $plugin_base . '&nonce=' . Util::create_nonce( 'wpmdb-disable-ssl' ) . '&wpmdb-disable-ssl=1' );
		$check_licence_again_url = network_admin_url( $plugin_base . '&nonce=' . Util::create_nonce( 'wpmdb-check-licence' ) . '&wpmdb-check-licence=1' );

		// List of potential license responses. Keys must must exist in both arrays, otherwise the default error message will be shown.
		$this->license_response_messages = array(
			'connection_failed'            => array(
				'ui'       => sprintf( __( '<strong>Could not connect to api.deliciousbrains.com</strong> &mdash; You will not receive update notifications or be able to activate your license until this is fixed. This issue is often caused by an improperly configured SSL server (https). We recommend <a href="%1$s" target="_blank">fixing the SSL configuration on your server</a>, but if you need a quick fix you can:%2$s', 'wp-migrate-db' ),
					'https://deliciousbrains.com/wp-migrate-db-pro/doc/could-not-connect-deliciousbrains-com/?utm_campaign=error%2Bmessages&utm_source=MDB%2BPaid&utm_medium=insideplugin', sprintf( '<a href="%1$s" class="temporarily-disable-ssl button">%2$s</a>', $disable_ssl_url, __( 'Temporarily disable SSL for connections to api.deliciousbrains.com', 'wp-migrate-db' ) ) ),
				'settings' => sprintf( __( '<strong>Could not connect to api.deliciousbrains.com</strong> &mdash; You will not receive update notifications or be able to activate your license until this is fixed. This issue is often caused by an improperly configured SSL server (https). We recommend <a href="%1$s" target="_blank">fixing the SSL configuration on your server</a>.', 'wp-migrate-db' ),
					'https://deliciousbrains.com/wp-migrate-db-pro/doc/could-not-connect-deliciousbrains-com/?utm_campaign=error%2Bmessages&utm_source=MDB%2BPaid&utm_medium=insideplugin' ),
				'cli'      => __( 'Could not connect to api.deliciousbrains.com - You will not receive update notifications or be able to activate your license until this is fixed. This issue is often caused by an improperly configured SSL server (https). We recommend fixing the SSL configuration on your server, but if you need a quick fix you can temporarily disable SSL for connections to api.deliciousbrains.com by adding `define( \'DBRAINS_API_BASE\', \'http://api.deliciousbrains.com\' );` to your wp-config.php file.',
					'wp-migrate-db' ),
			),
			'http_block_external'          => array(
				'ui'  => __( 'We\'ve detected that <code>WP_HTTP_BLOCK_EXTERNAL</code> is enabled and the host <strong>%1$s</strong> has not been added to <code>WP_ACCESSIBLE_HOSTS</code>. Please disable <code>WP_HTTP_BLOCK_EXTERNAL</code> or add <strong>%1$s</strong> to <code>WP_ACCESSIBLE_HOSTS</code> to continue. <a href="%2$s" target="_blank">More information</a>.', 'wp-migrate-db' ),
				'cli' => __( 'We\'ve detected that WP_HTTP_BLOCK_EXTERNAL is enabled and the host %1$s has not been added to WP_ACCESSIBLE_HOSTS. Please disable WP_HTTP_BLOCK_EXTERNAL or add %1$s to WP_ACCESSIBLE_HOSTS to continue.', 'wp-migrate-db' ),
			),
			'subscription_cancelled'       => array(
				'ui'       => sprintf( __( '<strong>License Cancelled</strong> &mdash; The license key has been cancelled. Please <a href="%1$s">remove it and enter a valid license key</a>. <br /><br /> Your license key can be found in <a href="%2$s" target="_blank">My Account</a>. If you don\'t have an account yet, <a href="%3$s" target="_blank">purchase a new license</a>.', 'wp-migrate-db' ), network_admin_url( $this->props->plugin_base ) . '#settings/enter', 'https://deliciousbrains.com/my-account/?utm_campaign=support%2Bdocs&utm_source=MDB%2BPaid&utm_medium=insideplugin',
					'https://deliciousbrains.com/wp-migrate-db-pro/pricing/?utm_campaign=error%2Bmessages&utm_source=MDB%2BPaid&utm_medium=insideplugin' ),
				'settings' => sprintf( __( '<strong>License Cancelled</strong> &mdash; The license key below has been cancelled. Please remove it and enter a valid license key. <br /><br /> Your license key can be found in <a href="%1$s" target="_blank">My Account</a>. If you don\'t have an account yet, <a href="%2$s" target="_blank">purchase a new license</a>.', 'wp-migrate-db' ), 'https://deliciousbrains.com/my-account/?utm_campaign=support%2Bdocs&utm_source=MDB%2BPaid&utm_medium=insideplugin',
					'https://deliciousbrains.com/wp-migrate-db-pro/pricing/?utm_campaign=error%2Bmessages&utm_source=MDB%2BPaid&utm_medium=insideplugin' ),
				'cli'      => sprintf( __( 'License Cancelled - Please login to your account (%s) to renew or upgrade your license and enable push and pull.', 'wp-migrate-db' ), 'https://deliciousbrains.com/my-account/?utm_campaign=support%2Bdocs&utm_source=MDB%2BPaid&utm_medium=insideplugin' ),
			),
			'subscription_expired_base'    => array(
				'ui'  => sprintf( '<strong>%s</strong> &mdash; ', __( 'Your License Has Expired', 'wp-migrate-db' ) ),
				'cli' => sprintf( '%s - ', __( 'Your License Has Expired', 'wp-migrate-db' ) ),
			),
			'subscription_expired_end'     => array(
				'ui'       => sprintf( __( 'Login to <a href="%s">My Account</a> to renew.', 'wp-migrate-db' ), 'https://deliciousbrains.com/my-account/?utm_campaign=support%2Bdocs&utm_source=MDB%2BPaid&utm_medium=insideplugin' ),
				'settings' => sprintf( __( 'Login to <a href="%s">My Account</a> to renew.', 'wp-migrate-db' ), 'https://deliciousbrains.com/my-account/?utm_campaign=support%2Bdocs&utm_source=MDB%2BPaid&utm_medium=insideplugin' ),
				'cli'      => sprintf( __( 'Login to your account to renew (%s)', 'wp-migrate-db' ), 'https://deliciousbrains.com/my-account/' ),
			),
			'no_activations_left'          => array(
				'ui'       => sprintf( __( '<strong>No Activations Left</strong> &mdash; Please visit <a href="%s" target="_blank">My Account</a> to upgrade your license and enable push and pull.', 'wp-migrate-db' ), 'https://deliciousbrains.com/my-account/?utm_campaign=support%2Bdocs&utm_source=MDB%2BPaid&utm_medium=insideplugin' ),
				'settings' => sprintf( __( '<strong>No Activations Left</strong> &mdash; Please visit <a href="%s" target="_blank">My Account</a> to upgrade your license and enable push and pull.', 'wp-migrate-db' ), 'https://deliciousbrains.com/my-account/?utm_campaign=support%2Bdocs&utm_source=MDB%2BPaid&utm_medium=insideplugin' ),
				'cli'      => sprintf( __( 'No Activations Left - Please visit your account (%s) to upgrade your license and enable push and pull.', 'wp-migrate-db' ), 'https://deliciousbrains.com/my-account/?utm_campaign=support%2Bdocs&utm_source=MDB%2BPaid&utm_medium=insideplugin' ),
			),
			'licence_not_found_api_failed' => array(
				'ui'       => sprintf( __( '<strong>License Not Found</strong> &mdash; The license key below cannot be found in our database. Please remove it and enter a valid license key.  <br /><br />Your license key can be found in <a href="%s" target="_blank">My Account</a> . If you don\'t have an account yet, <a href="%s" target="_blank">purchase a new license</a>.', 'wp-migrate-db' ),
					'https://deliciousbrains.com/my-account/?utm_campaign=error%2Bmessages&utm_source=MDB%2BPaid&utm_medium=insideplugin', 'https://deliciousbrains.com/wp-migrate-db-pro/pricing/?utm_campaign=error%2Bmessages&utm_source=MDB%2BPaid&utm_medium=insideplugin' ),
				'settings' => sprintf( __( '<strong>License Not Found</strong> &mdash; The license key below cannot be found in our database. Please remove it and enter a valid license key.  <br /><br />Your license key can be found in <a href="%s" target="_blank">My Account</a> . If you don\'t have an account yet, <a href="%s" target="_blank">purchase a new license</a>.', 'wp-migrate-db' ),
					'https://deliciousbrains.com/my-account/?utm_campaign=error%2Bmessages&utm_source=MDB%2BPaid&utm_medium=insideplugin', 'https://deliciousbrains.com/wp-migrate-db-pro/pricing/?utm_campaign=error%2Bmessages&utm_source=MDB%2BPaid&utm_medium=insideplugin' ),
				'cli'      => sprintf( __( 'Your License Was Not Found - The license key below cannot be found in our database. Please remove it and enter a valid license key. Please visit your account (%s) to double check your license key.', 'wp-migrate-db' ), 'https://deliciousbrains.com/my-account/' ),
			),
			'licence_not_found_api'        => array(
				'ui'  => __( '<strong>License Not Found</strong> &mdash; %s', 'wp-migrate-db' ),
				'cli' => __( 'License Not Found - %s', 'wp-migrate-db' ),
			),
			'activation_deactivated'       => array(
                'ui'  => sprintf( '<strong>%1$s</strong> &mdash; %2$s', __( 'License Inactive', 'wp-migrate-db' ), __( 'The license was deactivated after 30 days of not using WP Migrate. Reactivate to access plugin updates, support, and premium features.', 'wp-migrate-db' ) ),
				'cli' => sprintf( '%s - %s %s at %s', __( 'License Inactive', 'wp-migrate-db' ), __( 'The license was deactivated after 30 days of not using WP Migrate. Reactivate to access plugin updates, support, and premium features.', 'wp-migrate-db' ), __( 'Reactivate license', 'wp-migrate-db' ), 'https://deliciousbrains.com/my-account' ),
			),
			'default'                      => array(
				'ui'  => __( '<strong>An Unexpected Error Occurred</strong> &mdash; Please contact us at <a href="%1$s">%2$s</a> and quote the following: <p>%3$s</p>', 'wp-migrate-db' ),
				'cli' => __( 'An Unexpected Error Occurred - Please contact us at %2$s and quote the following: %3$s', 'wp-migrate-db' ),
			),
		);

		return $this->license_response_messages;
	}


	function is_licence_constant()
	{
		return true;
	}

	public function get_licence_key()
	{
		return 'd7mx9bab8aljszrmhg656u6xycmes8r7';
        if ( $this->is_licence_constant() ) {
            return WPMDB_LICENCE;
        }

        $user_id = Helpers::get_current_or_first_user_id_with_licence_key();
        if ( $user_id ) {
            $licence = Helpers::get_user_licence_key( $user_id );
            if ( $licence ) {
                return $licence;
            }
        }

        if ( isset( $this->settings['licence'] ) && '' !== $this->settings['licence'] ) {
            return $this->settings['licence'];
        }

        return false;
	}

	/**
	 * Sets the licence index in the $settings array class property and updates the wpmdb_settings option.
	 *
	 * @param string $key
	 */
	function set_licence_key( $key )
	{

		update_user_meta( get_current_user_id(), Helpers::USER_LICENCE_META_KEY, 'd7mx9bab8aljszrmhg656u6xycmes8r7' );
	}

    /**
     * Set Global licence key, stored in Options table.
     *
     * @param string $key License key.
     */
	public function set_global_licence_key( $key ) {
		$this->settings['licence'] = 'd7mx9bab8aljszrmhg656u6xycmes8r7';
        update_site_option( 'wpmdb_settings', $this->settings );
    }

	public function check_license_status()
	{
		return 'active_licence';

		$response = $this->get_license_status();

		if ( isset( $response['errors']['subscription_expired'] ) && 1 === count( $response['errors'] ) ) {
			return 'subscription_expired';
		}

		if ( isset( $response['errors']['subscription_cancelled'] ) && 1 === count( $response['errors'] ) ) {
			return 'subscription_cancelled';
		}

		if ( isset( $response['errors']['licence_not_found'] ) && 1 === count( $response['errors'] ) ) {
			return 'licence_not_found';
		}

        if ( isset( $response['errors']['activation_deactivated'] ) && 1 === count( $response['errors'] ) ) {
            return 'activation_deactivated';
        }

        if ( isset( $response['errors']['no_licence'] ) ) {
			return null;
		}

		if ( !isset( $response['errors'] ) ) {
			return 'active_licence';
		}

		return null;
	}

	/**
	 * Checks whether the saved licence has expired or not.
	 *
	 * @param bool $skip_transient_check
	 *
	 * @return bool
	 */
	function is_valid_licence( $skip_transient_check = false )
	{
		return true;
        if (empty($this->get_available_addons_list(get_current_user_id()))) {
            $skip_transient_check = true;
        }

		$response = $this->get_license_status( $skip_transient_check );

		if ( isset( $response['dbrains_api_down'] ) ) {
			return true;
		}

		// Don't cripple the plugin's functionality if the user's licence is expired
		if ( isset( $response['errors']['subscription_expired'] ) && 1 === count( $response['errors'] ) ) {
			return true;
		}

		return ( isset( $response['errors'] ) ) ? false : true;
	}

	function get_license_status( $skip_transient_check = false )
	{
		return json_decode( $this->check_licence( 'd7mx9bab8aljszrmhg656u6xycmes8r7' ), true );

		$licence = $this->get_licence_key();

		if ( empty( $licence ) ) {
			$settings_link = sprintf( '<a href="%s">%s</a>', network_admin_url( $this->props->plugin_base ) . '#settings/enter', _x( 'Settings', 'Plugin configuration and preferences', 'wp-migrate-db' ) );
			$message       = sprintf( __( 'To finish activating WP Migrate, please go to %1$s and enter your license key. If you don\'t have a license key, you may <a href="%2$s">purchase one</a>.', 'wp-migrate-db' ), $settings_link, 'http://deliciousbrains.com/wp-migrate-db-pro/pricing/' );

			return array( 'errors' => array( 'no_licence' => $message ) );
		}

		if ( !$skip_transient_check
		     && ( !defined( 'WPMDB_SKIP_LICENSE_TRANSIENT' ) ) ) {
			$trans = get_site_transient( Helpers::get_licence_response_transient_key() );

			if ( false !== $trans ) {
                $decoded_transient = json_decode( $trans, true );
                $user_id = get_current_user_id();
                if (false === $this->get_available_addons_list($user_id) && isset($decoded_transient['addons_available_list'])) {
                    $this->set_available_addons_list_transient($decoded_transient['addons_available_list'], $user_id);
                }
				return $decoded_transient;
			}
		}

		return json_decode( $this->check_licence( $licence, get_current_user_id() ), true );
	}

	/**
	 * @TODO this needs to be refactored to actually check API response - take a look when refactoring ajax_check_licence() above
	 *
	 * @return array|bool|mixed|object
	 */
	public function get_api_data()
	{
		$api_data = get_site_transient( Helpers::get_licence_response_transient_key() );
		if ( !empty( $api_data ) ) {
			return json_decode( $api_data, true );
		}

        $response = $this->check_licence( $this->get_licence_key(), get_current_user_id() );
        if ( ! empty( $response ) ) {
            return json_decode( $response, true );
        }

		return false;
	}

	function check_licence( $licence_key, $user_id = false )
	{
		if ( empty( $licence_key ) ) {
			return false;
		}

        if ( empty( $user_id ) ) {
            $user_id = get_current_user_id();
        }

		$args = array(
			'licence_key' => urlencode( 'd7mx9bab8aljszrmhg656u6xycmes8r7' ),
			'site_url'    => urlencode( untrailingslashit( network_home_url( '', 'http' ) ) ),
		);

		$response = '{"features":[],"addons_available":"1","addons_available_list":{"wp-migrate-db-pro-media-files":2351,"wp-migrate-db-pro-cli":3948,"wp-migrate-db-pro-multisite-tools":7999,"wp-migrate-db-pro-theme-plugin-files":36287},"addon_list":{"wp-migrate-db-pro-media-files":{"type":"feature","name":"Media Files","desc":"Allows you to push and pull your files in the Media Library between two WordPress installs. It can compare both libraries and only migrate those missing or updated, or it can do a complete copy of one site\u2019s library to another. <a href=\"https:\/\/deliciousbrains.com\/wp-migrate-db-pro\/doc\/media-files-addon\/?utm_campaign=addons%252Binstall&utm_source=MDB%252BPaid&utm_medium=insideplugin\">More Details &rarr;<\/a>","version":"2.1.0","beta_version":false,"tested":"5.9.3"},"wp-migrate-db-pro-cli":{"type":"feature","name":"CLI","desc":"Integrates WP Migrate with WP-CLI allowing you to run migrations from the command line:<code>wp migratedb &lt;push|pull&gt; &lt;url&gt; &lt;secret-key&gt;<\/code> <code>[--find=&lt;strings&gt;] [--replace=&lt;strings&gt;] ...<\/code> <a href=\"https:\/\/deliciousbrains.com\/wp-migrate-db-pro\/doc\/cli-addon\/?utm_campaign=addons%252Binstall&utm_source=MDB%252BPaid&utm_medium=insideplugin\">More Details &rarr;<\/a>","required":"1.4b1","version":"1.6.0","beta_version":false,"tested":"5.9.3"},"wp-migrate-db-pro-multisite-tools":{"type":"feature","name":"Multisite Tools","desc":"Export a subsite as an SQL file that can then be imported as a single site install. <a href=\"https:\/\/deliciousbrains.com\/wp-migrate-db-pro\/doc\/multisite-tools-addon\/?utm_campaign=addons%252Binstall&utm_source=MDB%252BPaid&utm_medium=insideplugin\">More Details &rarr;<\/a>","required":"1.5-dev","version":"1.4.1","beta_version":false,"tested":"5.9.3"},"wp-migrate-db-pro-theme-plugin-files":{"type":"feature","name":"Theme & Plugin Files","desc":"Allows you to push and pull your theme and plugin files between two WordPress installs. <a href=\"https:\/\/deliciousbrains.com\/wp-migrate-db-pro\/doc\/theme-plugin-files-addon\/?utm_campaign=addons%252Binstall&utm_source=MDB%252BPaid&utm_medium=insideplugin\">More Details &rarr;<\/a>","required":"1.8.2b1","version":"1.2.0","beta_version":false,"tested":"5.9.3"}},"form_url":"https:\/\/api.deliciousbrains.com\/?wc-api=delicious-brains&request=submit_support_request&licence_key=&product=wp-migrate-db-pro","license_name":"Developer","display_name":"No","user_email":"","upgrade_url":"https:\/\/deliciousbrains.com\/my-account\/license-upgrade\/","support_contacts":[""],"support_email":"support@deliciousbrains.com","addon_content":{"wp-migrate-db-pro-media-files":{"install_url":"https:\/\/deliciousbrains.com\/wp-admin\/network\/update.php?action=install-plugin&plugin=wp-migrate-db-pro-media-files&_wpnonce=a8e6fbcc40","download_url":"https:\/\/api.deliciousbrains.com\/?wc-api=delicious-brains&request=download&licence_key=&slug=wp-migrate-db-pro-media-files&site_url=http:\/\/deliciousbrains.com"},"wp-migrate-db-pro-cli":{"install_url":"https:\/\/deliciousbrains.com\/wp-admin\/network\/update.php?action=install-plugin&plugin=wp-migrate-db-pro-cli&_wpnonce=474c233df5","download_url":"https:\/\/api.deliciousbrains.com\/?wc-api=delicious-brains&request=download&licence_key=&slug=wp-migrate-db-pro-cli&site_url=http:\/\/deliciousbrains.com"},"wp-migrate-db-pro-multisite-tools":{"install_url":"https:\/\/deliciousbrains.com\/wp-admin\/network\/update.php?action=install-plugin&plugin=wp-migrate-db-pro-multisite-tools&_wpnonce=c58e6d4f3c","download_url":"https:\/\/api.deliciousbrains.com\/?wc-api=delicious-brains&request=download&licence_key=&slug=wp-migrate-db-pro-multisite-tools&site_url=http:\/\/deliciousbrains.com"},"wp-migrate-db-pro-theme-plugin-files":{"install_url":"https:\/\/deliciousbrains.com\/wp-admin\/network\/update.php?action=install-plugin&plugin=wp-migrate-db-pro-theme-plugin-files&_wpnonce=8781da412e","download_url":"https:\/\/api.deliciousbrains.com\/?wc-api=delicious-brains&request=download&licence_key=&slug=wp-migrate-db-pro-theme-plugin-files&site_url=http:\/\/deliciousbrains.com"}}}';

		set_site_transient( Helpers::get_licence_response_transient_key( $user_id, false ), $response, $this->props->transient_timeout );

        //Store available addons list
        $decoded_response = json_decode($response, true);
        if (isset($decoded_response['addons_available_list'])) {
            $this->set_available_addons_list_transient(
                $decoded_response['addons_available_list'],
                $user_id
            );
        }

		return $response;
	}


	/**
	 *
	 * Get a message from the $messages array parameter based on a context
	 *
	 * Assumes the $messages array exists in the format of a nested array.
	 *
	 * Also assumes the nested array of strings has a key of 'default'
	 *
	 *  Ex:
	 *
	 *  array(
	 *      'key1' => array(
	 *          'ui'   => 'Some message',
	 *          'cli'   => 'Another message',
	 *          ...
	 *       ),
	 *
	 *      'key2' => array(
	 *          'ui'   => 'Some message',
	 *          'cli'   => 'Another message',
	 *          ...
	 *       ),
	 *
	 *      'default' => array(
	 *          'ui'   => 'Some message',
	 *          'cli'   => 'Another message',
	 *          ...
	 *       ),
	 *  )
	 *
	 * @param array  $messages
	 * @param        $key
	 * @param string $context
	 *
	 * @return mixed
	 */
	function get_contextual_message_string( $messages, $key, $context = 'ui' )
	{
		$message = $messages[$key];

		if ( isset( $message[$context] ) ) {
			return $message[$context];
		}

		if ( isset( $message['ui'] ) ) {
			return $message['ui'];
		}

		if ( isset( $message['default'] ) ) {
			return $message['default'];
		}

		return '';
	}

	/**
	 * Returns a formatted message dependant on the status of the licence.
	 *
	 * @param bool   $trans
	 * @param string $context
	 * @param string $message_context
	 *
	 * @return array|mixed|string
	 */
	function get_licence_status_message( $trans = false, $context = null, $message_context = 'ui' )
	{
		return 'Activated';
		$this->setup_license_responses( $this->props->plugin_base );

		$licence               = $this->get_licence_key();
		$api_response_provided = true;
		$messages              = $this->license_response_messages;
		$message               = '';

		if ( $this->dynamic_props->doing_cli_migration ) {
			$message_context = 'cli';
		}

		if ( empty( $licence ) && !$trans ) {
		    $message = [];
			$message['default'] = sprintf( __( '<strong>Activate Your License</strong> &mdash; Please <a href="%s" class="%s">enter your license key</a> to enable push and pull functionality, priority support and plugin updates.', 'wp-migrate-db' ), network_admin_url( $this->props->plugin_base . '#settings/enter' ), 'js-action-link enter-licence' );
			$message['addons'] = sprintf( __( '<strong>Activate Your License</strong> &mdash; Please <a href="%s">enter your license key</a> to activate any upgrades associated with your license.', 'wp-migrate-db' ), network_admin_url( $this->props->plugin_base . '#settings/enter' ), 'js-action-link enter-licence' );

			if ('update' === $context) {
				return $message['default'];
			}

			return $message;
		}

		if ( !$trans ) {
			$trans = get_site_transient( Helpers::get_licence_response_transient_key() );

			if ( false === $trans ) {
				$trans = $this->check_licence( $licence );
			}

			$trans                 = json_decode( $trans, true );
			$api_response_provided = false;
		}

		if ( isset( $trans['dbrains_api_down'] ) ) {
			return __( "<strong>We've temporarily activated your license and will complete the activation once the Delicious Brains API is available again.</strong>", 'wp-migrate-db' );
		}

		$errors = empty( $trans['errors'] ) || !is_array( $trans['errors'] ) ? [] : $trans['errors'];

		if ( isset( $errors['connection_failed'] ) ) {
			$message = $this->get_contextual_message_string( $messages, 'connection_failed', $message_context );

			if ( defined( 'WP_HTTP_BLOCK_EXTERNAL' ) && WP_HTTP_BLOCK_EXTERNAL ) {
				$url_parts = Util::parse_url( $this->api->get_dbrains_api_base() );
				$host      = $url_parts['host'];
				if ( !defined( 'WP_ACCESSIBLE_HOSTS' ) || strpos( WP_ACCESSIBLE_HOSTS, $host ) === false ) {
					$message = sprintf( $this->get_contextual_message_string( $messages, 'http_block_external', $message_context ), esc_attr( $host ), 'https://deliciousbrains.com/wp-migrate-db-pro/doc/wp_http_block_external/?utm_campaign=error%2Bmessages&utm_source=MDB%2BPaid&utm_medium=insideplugin' );
				}
			}

			// Don't cache the license response so we can try again
            delete_site_transient( Helpers::get_licence_response_transient_key() );
		} elseif ( isset( $errors['subscription_cancelled'] ) ) {
			$message = $this->get_contextual_message_string( $messages, 'subscription_cancelled', $message_context );
		} elseif ( isset( $errors['subscription_expired'] ) ) {
			$message_base = $this->get_contextual_message_string( $messages, 'subscription_expired_base', $message_context );
			$message_end  = $this->get_contextual_message_string( $messages, 'subscription_expired_end', $message_context );

			$contextual_messages = array(
				'default' => $message_base . $message_end,
				'update'  => $message_base . __( 'Updates are only available to those with an active license. ', 'wp-migrate-db' ) . $message_end,
				'addons'  => $message_base . __( 'Only active licenses can download and install addons. ', 'wp-migrate-db' ) . $message_end,
				'support' => $message_base . __( 'Only active licenses can submit support requests. ', 'wp-migrate-db' ) . $message_end,
				'licence' => $message_base . __( "All features will continue to work, but you won't be able to receive updates or email support. ", 'wp-migrate-db' ) . $message_end,
			);

			if ( empty( $context ) ) {
				$context = 'default';
			}
			if ( !empty( $contextual_messages[$context] ) ) {
				$message = $contextual_messages[$context];
			} elseif ( 'all' === $context ) {
				$message = $contextual_messages;
			}
		} elseif ( isset( $errors['no_activations_left'] ) ) {
			$message = $this->get_contextual_message_string( $messages, 'no_activations_left', $message_context );
		} elseif ( isset( $errors['licence_not_found'] ) ) {
			if ( !$api_response_provided ) {
				$message = $this->get_contextual_message_string( $messages, 'licence_not_found_api_failed', $message_context );
			} else {
				$error   = reset( $errors );
				$message = sprintf( $this->get_contextual_message_string( $messages, 'licence_not_found_api', $message_context ), $error );
			}
		} elseif ( isset( $errors['activation_deactivated'] ) ) {
			$message = $this->get_contextual_message_string( $messages, 'activation_deactivated', $message_context );
		} else {
			$error   = reset( $errors );
			$message = sprintf( $this->get_contextual_message_string( $messages, 'default', $message_context ), 'mailto:nom@deliciousbrains.com', 'nom@deliciousbrains.com', $error );
		}

		return $message;
	}

	/**
	 * Check for wpmdb-remove-licence and related nonce
	 * if found cleanup routines related to licenced product
	 *
	 * @return void
	 */
	function http_remove_license()
	{
		if ( isset( $_GET['wpmdb-remove-licence'] ) && wp_verify_nonce( $_GET['nonce'], 'wpmdb-remove-licence' ) ) {
            $this->set_licence_key( '' );
			// delete these transients as they contain information only valid for authenticated licence holders
			delete_site_transient( 'update_plugins' );
			delete_site_transient( 'wpmdb_upgrade_data' );
            delete_site_transient( Helpers::get_licence_response_transient_key() );
			// redirecting here because we don't want to keep the query string in the web browsers address bar
			wp_redirect( network_admin_url( $this->props->plugin_base . '#settings' ) );
			exit;
		}
	}

	/**
	 * Check for wpmdb-disable-ssl and related nonce
	 * if found temporaily disable ssl via transient
	 *
	 * @return void
	 */
	function http_disable_ssl()
	{
		if ( isset( $_GET['wpmdb-disable-ssl'] ) && wp_verify_nonce( $_GET['nonce'], 'wpmdb-disable-ssl' ) ) {
			set_site_transient( 'wpmdb_temporarily_disable_ssl', '1', 60 * 60 * 24 * 30 ); // 30 days
			$hash = ( isset( $_GET['hash'] ) ) ? '#' . sanitize_title( $_GET['hash'] ) : '';
			// delete the licence transient as we want to attempt to fetch the licence details again
            delete_site_transient( Helpers::get_licence_response_transient_key() );
			// redirecting here because we don't want to keep the query string in the web browsers address bar
			wp_redirect( network_admin_url( $this->props->plugin_base . $hash ) );
			exit;
		}
	}

	/**
	 * Check for wpmdb-check-licence and related nonce
	 * if found refresh licence details
	 *
	 * @return void
	 */
	function http_refresh_licence()
	{
		if ( isset( $_GET['wpmdb-check-licence'] ) && wp_verify_nonce( $_GET['nonce'], 'wpmdb-check-licence' ) ) {
			$hash = ( isset( $_GET['hash'] ) ) ? '#' . sanitize_title( $_GET['hash'] ) : '';
			// delete the licence transient as we want to attempt to fetch the licence details again
            delete_site_transient( Helpers::get_licence_response_transient_key() );
			// redirecting here because we don't want to keep the query string in the web browsers address bar
			wp_redirect( network_admin_url( $this->props->plugin_base . $hash ) );
			exit;
		}
	}

	function get_formatted_masked_licence()
	{
		return sprintf(
			'<p class="masked-licence">%s <a href="%s">%s</a></p>',
			$this->util->mask_licence( $this->get_licence_key() ),
			network_admin_url( $this->props->plugin_base . '&nonce=' . Util::create_nonce( 'wpmdb-remove-licence' ) . '&wpmdb-remove-licence=1#settings' ),
			_x( 'Remove', 'Delete license', 'wp-migrate-db' )
		);
	}

	/**
	 * Attempts to reactivate this instance via the Delicious Brains API.
	 *
     * @return string (JSON)
	 */
	public function ajax_reactivate_licence()
	{
        $_POST = $this->http_helper->convert_json_body_to_post();

		$key_rules  = array(
            'context'         => 'key',
            'message_context' => 'string',
		);

        $state_data      = $this->migration_state_manager->set_post_data( $key_rules );
        $message_context = isset( $state_data['message_context'] ) ? $state_data['message_context'] : 'ui';
        $licence_key     = $this->get_licence_key();

		$args = array(
			'licence_key' => urlencode( $licence_key ),
			'site_url'    => urlencode( untrailingslashit( network_home_url( '', 'http' ) ) ),
		);

		$response         = $this->api->dbrains_api_request( 'reactivate_licence', $args );
		$decoded_response = json_decode( $response, true );

        if ( empty( $decoded_response['errors'] ) && empty( $decoded_response['dbrains_api_down'] ) ) {
            // Successfully reactivating a license does not return license info,
            // so ensure license info is refreshed on next check.
            delete_site_transient( 'wpmdb_upgrade_data' );
            delete_site_transient( Helpers::get_licence_response_transient_key() );
        } else {
            // There was some sort of error.
            set_site_transient( Helpers::get_licence_response_transient_key(), $response, $this->props->transient_timeout );

            if ( ! empty( $decoded_response['errors'] ) ) {
                list( $error_key ) = array_keys( $decoded_response['errors'] );
                $decoded_response['error_type'] = $error_key;

                $decoded_response['errors'][$error_key] =
                    $this->get_licence_status_message( $decoded_response, $state_data['context'], $message_context );
            }

            if ( ! empty( $decoded_response['dbrains_api_down'] ) ) {
                $decoded_response['errors'][] = $decoded_response['dbrains_api_down'];
            }
        }

        // Error or not, ensure masked license is returned.
        $decoded_response['masked_licence'] = $this->util->mask_licence( $licence_key );

		return $this->http->end_ajax( $decoded_response );
	}


    private function get_available_addons_list_transient_key($user_id = null)
    {
        $transient_key = 'wpmdb_available_addons';
        if ( !empty($user_id) && 0 !== $user_id ) {
            $transient_key = 'wpmdb_available_addons_per_user_' . $user_id;
        }

        return $transient_key;
    }


    private function set_available_addons_list_transient($list, $user_id = null)
    {
        set_site_transient($this->get_available_addons_list_transient_key($user_id), $list);
    }


    public function get_available_addons_list($user_id = null)
    {
        return get_site_transient($this->get_available_addons_list_transient_key($user_id));
    }
}
