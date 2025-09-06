<?php
/**
 * Google Auth Class.
 *
 * @link          https://wpmudev.com/
 * @since         1.0.0
 *
 * @author        WPMUDEV (https://wpmudev.com)
 * @package       WPMUDEV\PluginTest
 *
 * @copyright (c) 2023, Incsub (http://incsub.com)
 */

namespace WPMUDEV\PluginTest\Core\Google_Auth;

// Abort if called directly.
defined( 'WPINC' ) || die;

use WPMUDEV\PluginTest\Base;
use Google\Client;

/**
 * Class Auth
 *
 * Handles Google authentication functionality.
 *
 * @package WPMUDEV\PluginTest\Core\Google_Auth
 */
class Auth extends Base {
	/**
	 * Google client instance.
	 *
	 * @since 1.0.0
	 * @var Client
	 */
	private $client;

	/**
	 * Cached client ID.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $client_id = '';

	/**
	 * Cached client secret.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $client_secret = '';

	/**
	 * Initialize the authentication.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init() {
		// Initialization logic can be added here if needed.
	}

	/**
	 * Getter method for Client instance.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $new_instance Whether to create a new instance.
	 * @return Client
	 */
	public function client( bool $new_instance = false ): Client {
		if ( $new_instance || ! $this->client instanceof Client ) {
			$this->client = new Client();
			$this->client->setApplicationName( __( 'WPMU DEV Plugin Test', 'wpmudev-plugin-test' ) );
		}

		return $this->client;
	}

	/**
	 * Set up client ID and client secret.
	 *
	 * @since 1.0.0
	 *
	 * @param string $client_id     The client ID.
	 * @param string $client_secret The client secret.
	 * @return bool
	 */
	public function set_up( string $client_id = '', string $client_secret = '' ): bool {
		$client_id     = $client_id ?: $this->get_client_id();
		$client_secret = $client_secret ?: $this->get_client_secret();

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			return false;
		}

		$client = $this->client();
		$client->setClientId( $client_id );
		$client->setClientSecret( $client_secret );
		$client->addScope( 'profile' );
		$client->addScope( 'email' );

		// TODO: Set redirect URI based on endpoint.
		// $client->setRedirectUri();

		return true;
	}

	/**
	 * Get the client ID.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	private function get_client_id(): string {
		if ( empty( $this->client_id ) ) {
			$settings          = $this->get_settings();
			$this->client_id = $settings['client_id'] ?? '';
		}

		return $this->client_id;
	}

	/**
	 * Get the client secret.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	private function get_client_secret(): string {
		if ( empty( $this->client_secret ) ) {
			$settings             = $this->get_settings();
			$this->client_secret = $settings['client_secret'] ?? '';
		}

		return $this->client_secret;
	}

	/**
	 * Check if credentials are configured.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function is_configured(): bool {
		$settings = $this->get_settings();
		return ! empty( $settings['client_id'] ) && ! empty( $settings['client_secret'] );
	}

	/**
	 * Get the authentication URL.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_auth_url(): string {
		if ( ! $this->is_configured() ) {
			return '';
		}

		$this->set_up();
		return $this->client()->createAuthUrl();
	}

	/**
	 * Get the plugin settings.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	protected function get_settings(): array {
		static $settings = null;

		if ( null === $settings ) {
			$settings = get_option( 'wpmudev_plugin_test_settings', [] );
		}

		return $settings;
	}
}