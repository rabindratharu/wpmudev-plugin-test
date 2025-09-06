<?php
/**
 * Google Auth Shortcode.
 *
 * @link          https://wpmudev.com/
 * @since         1.0.0
 *
 * @author        WPMUDEV (https://wpmudev.com)
 * @package       WPMUDEV\PluginTest
 *
 * @copyright (c) 2023, Incsub (http://incsub.com)
 */

namespace WPMUDEV\PluginTest\Endpoints\V1;

// Abort if called directly.
defined( 'WPINC' ) || die;

use WPMUDEV\PluginTest\Endpoint;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;

class Auth extends Endpoint {
	/**
	 * API endpoint for the current endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @var string $endpoint
	 */
	protected $endpoint = 'auth/auth-url';

	/**
	 * Register the routes for handling auth functionality.
	 *
	 * @return void
	 * @since 1.0.0
	 *
	 */
	public function register_routes() {
		// Route to save credentials (POST)
		register_rest_route(
			$this->get_namespace(),
			$this->get_endpoint(),
			array(
				array(
					'methods' 					=> WP_REST_Server::CREATABLE,
					'callback'            		=> array( $this, 'save_credentials' ),
					'permission_callback' 		=> function() {
						return current_user_can( 'manage_options' );
					},
					'args'                		=> array(
						'client_id'     		=> array(
							'required'          => true,
							'type'              => 'string',
							'description' 		=> __( 'The client ID from Google API project.', 'wpmudev-plugin-test' ),
							'sanitize_callback' => 'sanitize_text_field',
						),
						'client_secret' 		=> array(
							'required'          => true,
							'type'              => 'string',
							'description' 		=> __( 'The client secret from Google API project.', 'wpmudev-plugin-test' ),
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		// Route to get credentials (GET)
		register_rest_route(
			$this->get_namespace(),
			$this->get_endpoint(),
			array(
				array(
					'methods' 					=> WP_REST_Server::READABLE,
					'callback'            		=> array( $this, 'get_credentials' ),
					'permission_callback' 		=> function() {
						return current_user_can( 'manage_options' );
					},
				),
			)
		);
	}

	/**
	 * Save the client id and secret.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 * @since 1.0.0
	 */
	public function save_credentials( WP_REST_Request $request ) {
		$client_id = $request->get_param( 'client_id' );
		$client_secret = $request->get_param( 'client_secret' );

		// Validate the credentials
		if ( empty( $client_id ) || empty( $client_secret ) ) {
			return new WP_REST_Response( 
				array( 
					'success' => false, 
					'message' => __( 'Client ID and Client Secret are required.', 'wpmudev-plugin-test' ) 
				), 
				400 
			);
		}

		// Get existing settings
		$settings = get_option( 'wpmudev_plugin_test_settings', array() );
		
		// Update the credentials
		$settings['client_id'] = $client_id;
		$settings['client_secret'] = $client_secret;
		
		// Save the settings as an object
		update_option( 'wpmudev_plugin_test_settings', $settings );

		return new WP_REST_Response( 
			array( 
				'success' => true, 
				'message' => __( 'Credentials saved successfully.', 'wpmudev-plugin-test' ),
			), 
			200 
		);
	}

	/**
	 * Get the saved client id and secret.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response Response object with credentials.
	 * @since 1.0.0
	 */
	public function get_credentials( WP_REST_Request $request ) {
		$settings = get_option( 'wpmudev_plugin_test_settings', array() );
		
		$client_id = isset( $settings['client_id'] ) ? $settings['client_id'] : '';
		$client_secret = isset( $settings['client_secret'] ) ? $settings['client_secret'] : '';

		return new WP_REST_Response( 
			array( 
				'success' => true,
				'client_id' => $client_id,
				'client_secret' => $client_secret,
			), 
			200 
		);
	}
}