<?php
/**
 * Google Drive API endpoints using Google Client Library.
 *
 * @link          https://wpmudev.com/
 * @since         1.0.0
 *
 * @author        WPMUDEV[](https://wpmudev.com)
 * @package       WPMUDEV\PluginTest
 *
 * @copyright (c) 2025, Incsub[](http://incsub.com)
 */

namespace WPMUDEV\PluginTest\Endpoints\V1;

// Abort if called directly.
defined( 'WPINC' ) || die;

use WPMUDEV\PluginTest\Endpoint;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use Google_Client;
use Google_Service_Drive;
use Google_Service_Drive_DriveFile;

class Drive_API extends Endpoint {

	/**
	 * The endpoint base.
	 *
	 * @var string
	 */
	protected $endpoint = 'drive';

	/**
	 * Google Client instance.
	 *
	 * @var Google_Client
	 */
	private $client;

	/**
	 * Google Drive service.
	 *
	 * @var Google_Service_Drive
	 */
	private $drive_service;

	/**
	 * OAuth redirect URI.
	 *
	 * @var string
	 */
	private $redirect_uri;

	/**
	 * Google Drive API scopes.
	 *
	 * @var array
	 */
	private $scopes = array(
		Google_Service_Drive::DRIVE_FILE,
		Google_Service_Drive::DRIVE_READONLY,
	);

	/**
	 * Initialize the class.
	 */
	public function init() {
		$this->redirect_uri = home_url( '/wp-json/' . $this->namespace . '/' . $this->endpoint . '/callback' );
		$this->setup_google_client();

		parent::register_hooks(); // Uses inherited rest_api_init
	}

	/**
	 * Setup Google Client.
	 */
	private function setup_google_client() {
		$auth_creds = get_option( 'wpmudev_plugin_tests_auth', array() );
		
		if ( empty( $auth_creds['client_id'] ) || empty( $auth_creds['client_secret'] ) ) {
			return;
		}

		$this->client = new Google_Client();
		$this->client->setClientId( $auth_creds['client_id'] );
		$this->client->setClientSecret( $auth_creds['client_secret'] );
		$this->client->setRedirectUri( $this->redirect_uri );
		$this->client->setScopes( $this->scopes );
		$this->client->setAccessType( 'offline' );
		$this->client->setPrompt( 'consent' );

		// Set access token if available
		$access_token = get_option( 'wpmudev_drive_access_token', '' );
		if ( ! empty( $access_token ) ) {
			$this->client->setAccessToken( $access_token );
		}

		$this->drive_service = new Google_Service_Drive( $this->client );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		// Save credentials endpoint
		register_rest_route( $this->namespace, '/' . $this->endpoint . '/save-credentials', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'save_credentials' ),
			'permission_callback' => array( $this, 'edit_permission' ),
		) );

		// Authentication endpoint
		register_rest_route( $this->namespace, '/' . $this->endpoint . '/auth', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'start_auth' ),
			'permission_callback' => array( $this, 'edit_permission' ),
		) );

		// OAuth callback (public, no permission check needed as it's called by Google)
		register_rest_route( $this->namespace, '/' . $this->endpoint . '/callback', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'handle_callback' ),
			'permission_callback' => '__return_true',
		) );

		// List files
		register_rest_route( $this->namespace, '/' . $this->endpoint . '/files', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'list_files' ),
			'permission_callback' => array( $this, 'edit_permission' ),
		) );

		// Upload file
		register_rest_route( $this->namespace, '/' . $this->endpoint . '/upload', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'upload_file' ),
			'permission_callback' => array( $this, 'edit_permission' ),
		) );

		// Download file
		register_rest_route( $this->namespace, '/' . $this->endpoint . '/download/(?P<file_id>[^/]+)', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'download_file' ),
			'permission_callback' => array( $this, 'edit_permission' ),
		) );

		// Create folder
		register_rest_route( $this->namespace, '/' . $this->endpoint . '/create-folder', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'create_folder' ),
			'permission_callback' => array( $this, 'edit_permission' ),
		) );
	}

	/**
	 * Save Google OAuth credentials.
	 */
	public function save_credentials( WP_REST_Request $request ) {
		// Get JSON parameters
		$params = $request->get_json_params();
		
		$client_id = isset( $params['client_id'] ) ? $params['client_id'] : '';
		$client_secret = isset( $params['client_secret'] ) ? $params['client_secret'] : '';

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			return new WP_Error( 'missing_params', 'Client ID and Secret are required', array( 'status' => 400 ) );
		}

		// Save credentials
		$credentials = array(
			'client_id'     => sanitize_text_field( $client_id ),
			'client_secret' => sanitize_text_field( $client_secret ),
		);

		update_option( 'wpmudev_plugin_tests_auth', $credentials );
		
		// Reinitialize Google Client with new credentials
		$this->setup_google_client();

		$response = new WP_REST_Response( array(
			'success' => true,
			'message' => 'Credentials saved successfully',
		), 200 );
		
		// Set proper headers for JSON response
		$response->header( 'Content-Type', 'application/json' );
		
		return $response;
	}

	/**
	 * Start Google OAuth flow.
	 */
	public function start_auth( WP_REST_Request $request ) {
		if ( ! $this->client ) {
			return new WP_Error( 'missing_credentials', 'Google OAuth credentials not configured', array( 'status' => 400 ) );
		}

		$auth_url = $this->client->createAuthUrl();
		return new WP_REST_Response( array(
			'success' => true,
			'auth_url' => $auth_url,
		), 200 );
	}

	/**
	 * Handle OAuth callback.
	 */
	public function handle_callback( WP_REST_Request $request ) {
		$code  = $request->get_param( 'code' );
		$state = $request->get_param( 'state' );

		if ( empty( $code ) ) {
			wp_die( 'Authorization code not received' );
		}

		try {
			// Exchange code for access token
			$access_token = $this->client->fetchAccessTokenWithAuthCode( $code );

			if ( isset( $access_token['error'] ) ) {
				throw new Exception( $access_token['error_description'] ?? 'Token exchange failed' );
			}

			// Store tokens
			update_option( 'wpmudev_drive_access_token', $access_token );
			if ( isset( $access_token['refresh_token'] ) ) {
				update_option( 'wpmudev_drive_refresh_token', $access_token['refresh_token'] );
			}
			$expires_in = isset( $access_token['expires_in'] ) ? (int) $access_token['expires_in'] : 3600;
			update_option( 'wpmudev_drive_token_expires', time() + $expires_in );

			// Redirect back to admin page
			wp_redirect( admin_url( 'admin.php?page=wpmudev_plugintest_drive&auth=success' ) );
			exit;

		} catch ( Exception $e ) {
			wp_die( 'Failed to get access token: ' . esc_html( $e->getMessage() ) );
		}
	}

	/**
	 * Ensure we have a valid access token.
	 */
	private function ensure_valid_token() {
		if ( ! $this->client ) {
			return false;
		}

		if ( $this->client->isAccessTokenExpired() ) {
			$refresh_token = get_option( 'wpmudev_drive_refresh_token', '' );
			if ( ! empty( $refresh_token ) ) {
				try {
					$new_token = $this->client->fetchAccessTokenWithRefreshToken( $refresh_token );
					if ( isset( $new_token['access_token'] ) ) {
						update_option( 'wpmudev_drive_access_token', $new_token );
						$expires_in = isset( $new_token['expires_in'] ) ? (int) $new_token['expires_in'] : 3600;
						update_option( 'wpmudev_drive_token_expires', time() + $expires_in );
					}
					return true;
				} catch ( Exception $e ) {
					// Refresh failed, clear tokens
					delete_option( 'wpmudev_drive_access_token' );
					delete_option( 'wpmudev_drive_refresh_token' );
					delete_option( 'wpmudev_drive_token_expires' );
					return false;
				}
			}
			return false;
		}

		return true;
	}

	/**
	 * List files in Google Drive.
	 */
	public function list_files( WP_REST_Request $request ) {
		if ( ! $this->ensure_valid_token() ) {
			return new WP_Error( 'no_access_token', 'Not authenticated with Google Drive', array( 'status' => 401 ) );
		}

		try {
			$page_size = (int) $request->get_param( 'page_size' ) ?: 20;
			$query     = sanitize_text_field( $request->get_param( 'q' ) ?: 'trashed=false' );

			$options = array(
				'pageSize' => $page_size,
				'q'        => $query,
				'fields'   => 'files(id,name,mimeType,size,modifiedTime,webViewLink)',
			);

			$results = $this->drive_service->files->listFiles( $options );
			$files   = $results->getFiles();

			$file_list = array();
			foreach ( $files as $file ) {
				$file_list[] = array(
					'id'           => $file->getId(),
					'name'         => $file->getName(),
					'mimeType'     => $file->getMimeType(),
					'size'         => $file->getSize(),
					'modifiedTime' => $file->getModifiedTime(),
					'webViewLink'  => $file->getWebViewLink(),
				);
			}

			return new WP_REST_Response( array(
				'success' => true,
				'data'    => $file_list,
			), 200 );

		} catch ( Exception $e ) {
			return new WP_Error( 'api_error', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * Upload file to Google Drive.
	 */
	public function upload_file( WP_REST_Request $request ) {
		if ( ! $this->ensure_valid_token() ) {
			return new WP_Error( 'no_access_token', 'Not authenticated with Google Drive', array( 'status' => 401 ) );
		}

		$files = $request->get_file_params();
		
		if ( empty( $files['file'] ) ) {
			return new WP_Error( 'no_file', 'No file provided', array( 'status' => 400 ) );
		}

		$file = $files['file'];
		
		if ( $file['error'] !== UPLOAD_ERR_OK ) {
			return new WP_Error( 'upload_error', 'File upload error', array( 'status' => 400 ) );
		}

		try {
			// Create file metadata
			$drive_file = new Google_Service_Drive_DriveFile();
			$drive_file->setName( $file['name'] );

			// Upload file
			$result = $this->drive_service->files->create(
				$drive_file,
				array(
					'data'       => file_get_contents( $file['tmp_name'] ),
					'mimeType'   => $file['type'],
					'uploadType' => 'multipart',
					'fields'     => 'id,name,mimeType,size,webViewLink',
				)
			);

			return new WP_REST_Response( array(
				'success' => true,
				'file'    => array(
					'id'          => $result->getId(),
					'name'        => $result->getName(),
					'mimeType'    => $result->getMimeType(),
					'size'        => $result->getSize(),
					'webViewLink' => $result->getWebViewLink(),
				),
			), 200 );

		} catch ( Exception $e ) {
			return new WP_Error( 'upload_failed', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * Download file from Google Drive.
	 */
	public function download_file( WP_REST_Request $request ) {
		if ( ! $this->ensure_valid_token() ) {
			return new WP_Error( 'no_access_token', 'Not authenticated with Google Drive', array( 'status' => 401 ) );
		}

		$file_id = $request->get_param( 'file_id' );
		
		if ( empty( $file_id ) ) {
			return new WP_Error( 'missing_file_id', 'File ID is required', array( 'status' => 400 ) );
		}

		try {
			// Get file metadata
			$file = $this->drive_service->files->get( $file_id, array(
				'fields' => 'id,name,mimeType,size',
			) );

			// Download file content
			$response = $this->drive_service->files->get( $file_id, array(
				'alt' => 'media',
			) );

			$content = $response->getBody()->getContents();

			// Return file content as base64 for JSON response
			return new WP_REST_Response( array(
				'success'  => true,
				'content'  => base64_encode( $content ),
				'filename' => $file->getName(),
				'mimeType' => $file->getMimeType(),
			), 200 );

		} catch ( Exception $e ) {
			return new WP_Error( 'download_failed', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * Create folder in Google Drive.
	 */
	public function create_folder( WP_REST_Request $request ) {
		if ( ! $this->ensure_valid_token() ) {
			return new WP_Error( 'no_access_token', 'Not authenticated with Google Drive', array( 'status' => 401 ) );
		}

		$name = sanitize_text_field( $request->get_param( 'name' ) );
		
		if ( empty( $name ) ) {
			return new WP_Error( 'missing_name', 'Folder name is required', array( 'status' => 400 ) );
		}

		try {
			$folder = new Google_Service_Drive_DriveFile();
			$folder->setName( $name );
			$folder->setMimeType( 'application/vnd.google-apps.folder' );

			$result = $this->drive_service->files->create( $folder, array(
				'fields' => 'id,name,mimeType,webViewLink',
			) );

			return new WP_REST_Response( array(
				'success' => true,
				'folder'  => array(
					'id'          => $result->getId(),
					'name'        => $result->getName(),
					'mimeType'    => $result->getMimeType(),
					'webViewLink' => $result->getWebViewLink(),
				),
			), 200 );

		} catch ( Exception $e ) {
			return new WP_Error( 'create_failed', $e->getMessage(), array( 'status' => 500 ) );
		}
	}
}