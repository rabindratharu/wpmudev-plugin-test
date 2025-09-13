<?php
/**
 * Posts Maintenance class.
 *
 * @link    https://wpmudev.com/
 * @since   1.0.0
 *
 * @author  WPMUDEV[](https://wpmudev.com)
 * @package WPMUDEV\PluginTest
 *
 * @copyright (c) 2025, Incsub[](http://incsub.com)
 */

namespace WPMUDEV\PluginTest\App\Admin_Pages;

// Abort if called directly.
defined( 'WPINC' ) || die;

use WPMUDEV\PluginTest\Base;
use WP_Query;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Posts Maintenance admin page.
 */
class Posts_Maintenance extends Base {

	/**
	 * The page slug.
	 *
	 * @var string
	 */
	private $page_slug = 'wpmudev_plugintest_drive';

	/**
	 * The page title.
	 *
	 * @var string
	 */
	private $submenu_title = '';

	/**
	 * The page slug.
	 *
	 * @var string
	 */
	private $submenu_slug = 'wpmudev_posts_maintenance';

	/**
	 * Assets version.
	 *
	 * @var string
	 */
	private $assets_version = '';

	/**
	 * A unique string id to be used in markup and jsx.
	 *
	 * @var string
	 */
	private $unique_id = '';

	/**
	 * Background processing batch size.
	 *
	 * @var int
	 */
	private $batch_size = 50;

	/**
	 * Initializes the page.
	 *
	 * @return void
	 */
	public function init() {
		$this->submenu_title	= __( 'Posts Maintenance', 'wpmudev-plugin-test' );
		$this->assets_version 	= WPMUDEV_PLUGINTEST_VERSION;
		$this->unique_id      	= "wpmudev_posts_maintenance_wrap-{$this->assets_version}";

		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'admin_body_class', array( $this, 'admin_body_classes' ) );

		// Schedule daily maintenance
		if ( ! wp_next_scheduled( 'wpmudev_posts_maintenance_daily' ) ) {
			wp_schedule_event( time(), 'daily', 'wpmudev_posts_maintenance_daily' );
		}
		add_action( 'wpmudev_posts_maintenance_daily', array( $this, 'run_daily_maintenance' ) );

		// Register REST endpoints
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Ajax handlers for background processing
		add_action( 'wp_ajax_wpmudev_start_maintenance_scan', array( $this, 'ajax_start_scan' ) );
		add_action( 'wp_ajax_wpmudev_check_scan_status', array( $this, 'ajax_check_scan_status' ) );

		// Background processing hook
		add_action( 'wpmudev_process_next_batch', array( $this, 'process_next_batch' ), 10, 2 );
	}

	/**
	 * Register admin menu page.
	 */
	public function register_admin_page() {
		$page = add_submenu_page(
			$this->page_slug,
			__( 'Posts Maintenance', 'wpmudev-plugin-test' ),
			$this->submenu_title,
			'manage_options',
			$this->submenu_slug,
			array( $this, 'callback' )
		);

		add_action( 'load-' . $page, array( $this, 'prepare_assets' ) );
	}

	/**
	 * The admin page callback method.
	 *
	 * @return void
	 */
	public function callback() {
		$this->view();
	}

	/**
	 * Prepares assets.
	 *
	 * @return void
	 */
	public function prepare_assets() {
		$handle       = 'wpmudev_posts_maintenance';
		$src          = WPMUDEV_PLUGINTEST_ASSETS_URL . '/js/postsmaintenance.min.js';
		$style_src    = WPMUDEV_PLUGINTEST_ASSETS_URL . '/css/postsmaintenance.min.css';
		$dependencies = array(
			'react',
			'wp-element',
			'wp-i18n',
			'wp-api-fetch',
		);

		wp_register_script(
			$handle,
			$src,
			$dependencies,
			$this->assets_version,
			true
		);

		wp_localize_script(
			$handle,
			'wpmudevPostsMaintenance',
			array(
				'dom_element_id'    => $this->unique_id,
				'restEndpointScan'  => rest_url( 'wpmudev/v1/maintenance/scan' ),
				'restEndpointStatus' => rest_url( 'wpmudev/v1/maintenance/status' ),
				'nonce'             => wp_create_nonce( 'wp_rest' ), // For REST API
				'ajax_nonce'        => wp_create_nonce( 'wpmudev_maintenance_nonce' ), // For AJAX
				'ajaxurl'           => admin_url( 'admin-ajax.php' ),
				'postTypes'         => $this->get_public_post_types(),
			)
		);

		wp_enqueue_script( $handle );
		wp_enqueue_style( $handle, $style_src, array(), $this->assets_version );
	}

	/**
	 * Register REST routes for background processing.
	 */
	public function register_rest_routes() {
		register_rest_route(
			'wpmudev/v1',
			'/maintenance/scan',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'process_scan' ),
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
			)
		);

		register_rest_route(
			'wpmudev/v1',
			'/maintenance/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_scan_status' ),
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	/**
	 * Process the scan request.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function process_scan( $request ) {
		$post_types = $request->get_param( 'post_types' ) ?: array( 'post', 'page' );
		$post_types = array_filter( $post_types, 'post_type_exists' );

		if ( empty( $post_types ) ) {
			return new WP_REST_Response( array(
				'error' => __( 'No valid post types selected', 'wpmudev-plugin-test' )
			), 400 );
		}

		$offset = (int) get_option( 'wpmudev_maintenance_offset', 0 );
		$total = $this->count_posts( $post_types );

		// Initialize scan if starting fresh
		if ( $offset === 0 ) {
			update_option( 'wpmudev_maintenance_scan_params', array(
				'post_types' => $post_types,
				'started_at' => current_time( 'timestamp' ),
				'status'     => 'processing',
				'processed'  => 0,
				'total'      => $total,
			) );
		}

		$processed = $this->process_batch( $post_types, $offset );

		if ( $processed === false ) {
			return new WP_REST_Response( array(
				'error' => __( 'Failed to process batch', 'wpmudev-plugin-test' )
			), 500 );
		}

		$new_offset = $offset + $processed;
		$status = get_option( 'wpmudev_maintenance_scan_params', array() );

		if ( $new_offset >= $total ) {
			$status['status'] = 'completed';
			$status['completed_at'] = current_time( 'timestamp' );
			$status['processed'] = $total;
			$status['percentage'] = 100;
			delete_option( 'wpmudev_maintenance_offset' );
		} else {
			$status['processed'] = $new_offset;
			$status['percentage'] = $total > 0 ? round( ( $new_offset / $total ) * 100 ) : 0;
			update_option( 'wpmudev_maintenance_offset', $new_offset );
		}

		update_option( 'wpmudev_maintenance_scan_params', $status );

		return new WP_REST_Response( $status );
	}

	/**
	 * Get scan status.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_scan_status( $request ) {
		$status = get_option( 'wpmudev_maintenance_scan_params', array() );

		if ( empty( $status ) ) {
			return new WP_REST_Response( array( 'status' => 'not_started' ) );
		}

		return new WP_REST_Response( $status );
	}

	/**
	 * Ajax handler to start scan.
	 */
	public function ajax_start_scan() {

		check_ajax_referer( 'wpmudev_maintenance_nonce', 'nonce' );
	
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized', 'wpmudev-plugin-test' ), 403 );
		}
	
		// Handle both array and string input
		$post_types = isset( $_POST['post_types'] ) ? (array) $_POST['post_types'] : array();
		
		// If it's sent as post_types[] array, use that
		if ( empty( $post_types ) && isset( $_POST['post_types[]'] ) ) {
			$post_types = (array) $_POST['post_types[]'];
		}
		
		// Fallback to default if empty
		if ( empty( $post_types ) ) {
			$post_types = array( 'post', 'page' );
		}
	
		$post_types = array_filter( $post_types, 'post_type_exists' );
	
		if ( empty( $post_types ) ) {
			wp_send_json_error( __( 'No valid post types selected', 'wpmudev-plugin-test' ), 400 );
		}
	
		// Initialize scan
		$total = $this->count_posts( $post_types );
		
		update_option( 'wpmudev_maintenance_scan_params', array(
			'post_types' => $post_types,
			'started_at' => current_time( 'timestamp' ),
			'status'     => 'processing',
			'processed'  => 0,
			'total'      => $total,
			'percentage' => 0,
		) );

		update_option( 'wpmudev_maintenance_offset', 0 );

		// Start first batch immediately - this is the correct place
		$this->process_batch( $post_types, 0 );

		wp_send_json_success( array(
			'message' => __( 'Scan started successfully', 'wpmudev-plugin-test' ),
			'total'   => $total,
		) );
	}

	/**
	 * Ajax handler to check scan status.
	 */
	public function ajax_check_scan_status() {
		check_ajax_referer( 'wpmudev_maintenance_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized', 'wpmudev-plugin-test' ), 403 );
		}

		$status = get_option( 'wpmudev_maintenance_scan_params', array() );

		if ( empty( $status ) ) {
			wp_send_json_success( array( 'status' => 'not_started' ) );
		}

		wp_send_json_success( $status );
	}

	/**
	 * Process next batch.
	 *
	 * @param array $post_types
	 * @param int $offset
	 */
	public function process_next_batch( $post_types, $offset ) {
		$this->process_batch( $post_types, $offset );
	}

	/**
	 * Process a batch of posts.
	 *
	 * @param array $post_types
	 * @param int $offset
	 * @return int Number of posts processed
	 */
	private function process_batch( $post_types, $offset ) {
		$args = array(
			'post_type'      => $post_types,
			'posts_per_page' => $this->batch_size,
			'offset'         => $offset,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
		);

		// Handle media (attachment) post type differently
		if ( in_array( 'attachment', $post_types ) ) {
			// For media, we need to include 'inherit' status
			if ( count( $post_types ) === 1 ) {
				// Only media selected
				$args['post_status'] = 'inherit';
			} else {
				// Mixed post types including media
				$args['post_status'] = array( 'publish', 'inherit' );
			}
		} else {
			// Regular post types
			$args['post_status'] = 'publish';
		}

		$query = new WP_Query( $args );
		$posts = $query->posts;

		if ( empty( $posts ) ) {
			return 0;
		}

		foreach ( $posts as $post_id ) {
			update_post_meta( $post_id, 'wpmudev_test_last_scan', current_time( 'timestamp' ) );
		}

		$processed = count( $posts );
		$new_offset = $offset + $processed;
		$total = $this->count_posts( $post_types );

		// Update status
		$status = get_option( 'wpmudev_maintenance_scan_params', array() );
		$status['processed'] = $new_offset;
		$status['percentage'] = $total > 0 ? round( ( $new_offset / $total ) * 100 ) : 0;

		if ( $new_offset >= $total ) {
			$status['status'] = 'completed';
			$status['completed_at'] = current_time( 'timestamp' );
			delete_option( 'wpmudev_maintenance_offset' );
			update_option( 'wpmudev_last_maintenance_completed', current_time( 'timestamp' ) );
		} else {
			update_option( 'wpmudev_maintenance_offset', $new_offset );
			// Schedule next batch - this is the correct place to do it
			if ( ! wp_next_scheduled( 'wpmudev_process_next_batch', array( $post_types, $new_offset ) ) ) {
				wp_schedule_single_event( time() + 1, 'wpmudev_process_next_batch', array( $post_types, $new_offset ) );
			}
		}

		update_option( 'wpmudev_maintenance_scan_params', $status );

		return $processed;
	}

	/**
	 * Count total posts for given post types.
	 *
	 * @param array $post_types
	 * @return int
	 */
	private function count_posts( $post_types ) {
		$count = 0;
		foreach ( $post_types as $post_type ) {
			$post_counts = wp_count_posts( $post_type );
			
			if ( $post_type === 'attachment' ) {
				// Media uses 'inherit' status instead of 'publish'
				$count += (int) $post_counts->inherit;
			} else {
				$count += (int) $post_counts->publish;
			}
		}
		return $count;
	}

	/**
	 * Run daily maintenance.
	 */
	public function run_daily_maintenance() {
		$post_types = apply_filters( 'wpmudev_maintenance_daily_post_types', array( 'post', 'page' ) );
		$post_types = array_filter( $post_types, 'post_type_exists' );

		if ( empty( $post_types ) ) {
			return;
		}

		$this->process_scan_complete( $post_types );
	}

	/**
	 * Complete scan process.
	 *
	 * @param array $post_types
	 */
	private function process_scan_complete( $post_types ) {
		$offset = 0;
		$total = $this->count_posts( $post_types );

		while ( $offset < $total ) {
			$this->process_batch( $post_types, $offset );
			$offset += $this->batch_size;
		}

		update_option( 'wpmudev_last_maintenance_completed', current_time( 'timestamp' ) );
	}

	/**
	 * Get public post types.
	 *
	 * @return array
	 */
	private function get_public_post_types() {
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		$result = array();
		foreach ( $post_types as $post_type ) {
			$result[] = array(
				'value' => $post_type->name,
				'label' => $post_type->labels->singular_name,
			);
		}
		return $result;
	}

	/**
	 * Prints the wrapper element which React will use as root.
	 *
	 * @return void
	 */
	protected function view() {
		echo '<div id="' . esc_attr( $this->unique_id ) . '" class="sui-wrap"></div>';
	}

	/**
	 * Enqueue assets for the maintenance page.
	 */
	public function enqueue_assets() {
		$current_screen = get_current_screen();

		if ( empty( $current_screen->id ) || ! strpos( $current_screen->id, $this->submenu_slug ) ) {
			return;
		}

		$this->prepare_assets();
	}

	/**
	 * Adds the SUI class on markup body.
	 *
	 * @param string $classes
	 *
	 * @return string
	 */
	public function admin_body_classes( $classes = '' ) {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return $classes;
		}

		$current_screen = get_current_screen();

		if ( empty( $current_screen->id ) || ! strpos( $current_screen->id, $this->submenu_slug ) ) {
			return $classes;
		}

		$classes .= ' sui-' . str_replace( '.', '-', WPMUDEV_PLUGINTEST_SUI_VERSION ) . ' ';

		return $classes;
	}
}