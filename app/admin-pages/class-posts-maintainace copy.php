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

class Posts_Maintenance extends Base {

	/**
	 * The page slug.
	 *
	 * @var string
	 */
	private $page_slug = 'wpmudev_plugintest_drive';

	/**
	 * The submenu slug.
	 *
	 * @var string
	 */
	private $submenu_slug = 'wpmudev_plugintest_drive_maintenance';

	/**
	 * Initialize the maintenance functionality.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		add_action( 'wp_ajax_wpmudev_scan_posts', array( $this, 'handle_scan_ajax' ) );
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		// Schedule daily cron if not already scheduled.
		if ( ! wp_next_scheduled( 'wpmudev_posts_maintenance_cron' ) ) {
			wp_schedule_event( time(), 'daily', 'wpmudev_posts_maintenance_cron' );
		}
		add_action( 'wpmudev_posts_maintenance_cron', array( $this, 'run_maintenance' ) );

		// WP-CLI integration.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			// Note: WP_CLI class needs to be imported or used with namespace
			\WP_CLI::add_command( 'wpmudev posts-maintenance', array( $this, 'cli_scan' ) );
		}
	}

	/**
	 * Add admin submenu page under Google Drive Test.
	 *
	 * @since 1.0.0
	 */
	public function add_admin_menu() {
		$hook = add_submenu_page(
			$this->page_slug,
			__( 'Posts Maintenance', 'wpmudev-plugin-test' ),
			__( 'Posts Maintenance', 'wpmudev-plugin-test' ),
			'manage_options',
			$this->submenu_slug,
			array( $this, 'render_admin_page' )
		);

		add_action( "load-{$hook}", array( $this, 'prepare_admin_scripts' ) );
	}

	/**
	 * Render the admin page.
	 *
	 * @since 1.0.0
	 */
	public function render_admin_page() {
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		$last_run   = get_option( 'wpmudev_posts_last_maintenance', 0 );
		?>
		<div class="wrap sui-wrap">
			<h1><?php esc_html_e( 'Posts Maintenance', 'wpmudev-plugin-test' ); ?></h1>
			<?php if ( $last_run ) : ?>
				<div class="notice notice-info">
					<p><?php printf( esc_html__( 'Last maintenance run: %s', 'wpmudev-plugin-test' ), esc_html( date( 'Y-m-d H:i:s', $last_run ) ) ); ?></p>
				</div>
			<?php endif; ?>
			<form id="posts-maintenance-form">
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Post Types', 'wpmudev-plugin-test' ); ?></th>
						<td>
							<?php foreach ( $post_types as $pt ) : ?>
								<label>
									<input type="checkbox" name="post_types[]" value="<?php echo esc_attr( $pt->name ); ?>" checked="checked" />
									<?php echo esc_html( $pt->label ); ?>
								</label><br />
							<?php endforeach; ?>
							<p class="description"><?php esc_html_e( 'Select post types to include in the scan. Only published posts will be processed.', 'wpmudev-plugin-test' ); ?></p>
						</td>
					</tr>
				</table>
				<?php wp_nonce_field( 'wpmudev_scan_posts', 'scan_nonce' ); ?>
				<p class="submit">
					<button type="button" id="scan-posts-btn" class="button button-primary"><?php esc_html_e( 'Scan Posts', 'wpmudev-plugin-test' ); ?></button>
					<span id="scan-progress" style="display: none;">
						<span class="spinner" style="visibility: visible;"></span>
						<span id="progress-text"></span>
					</span>
				</p>
			</form>
			<div id="scan-results"></div>
		</div>
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				$('#scan-posts-btn').on('click', function() {
					var postTypes = [];
					$('input[name="post_types[]"]:checked').each(function() {
						postTypes.push($(this).val());
					});
					if (postTypes.length === 0) {
						alert('<?php esc_js( __( 'Please select at least one post type.', 'wpmudev-plugin-test' ) ); ?>');
						return;
					}
					$(this).prop('disabled', true);
					$('#scan-progress').show();
					$('#progress-text').text('<?php esc_js( __( 'Starting scan...', 'wpmudev-plugin-test' ) ); ?>');
					processBatch(postTypes, 0, 0);
				});

				function processBatch(postTypes, currentOffset, processed) {
					$.post(ajaxurl, {
						action: 'wpmudev_scan_posts',
						post_types: postTypes,
						offset: currentOffset,
						nonce: $('#scan_nonce').val()
					}, function(response) {
						if (response.success) {
							processed += response.data.processed;
							if (response.data.done) {
								$('#progress-text').text('<?php esc_js( __( 'Scan completed! Processed', 'wpmudev-plugin-test' ) ); ?> ' + processed + ' <?php esc_js( __( 'posts.', 'wpmudev-plugin-test' ) ); ?>');
								$('#scan-posts-btn').prop('disabled', false).after('<div class="notice notice-success"><p><?php esc_js( __( 'Maintenance completed successfully.', 'wpmudev-plugin-test' ) ); ?></p></div>');
								location.reload(); // Refresh to show last run time.
							} else {
								$('#progress-text').text('<?php esc_js( __( 'Processed', 'wpmudev-plugin-test' ) ); ?> ' + processed + ' <?php esc_js( __( 'posts...', 'wpmudev-plugin-test' ) ); ?>');
								processBatch(postTypes, currentOffset + 20, processed);
							}
						} else {
							$('#progress-text').text('<?php esc_js( __( 'Error:', 'wpmudev-plugin-test' ) ); ?> ' + (response.data || '<?php esc_js( __( 'Unknown error', 'wpmudev-plugin-test' ) ); ?>'));
							$('#scan-posts-btn').prop('disabled', false);
						}
					}).fail(function() {
						$('#progress-text').text('<?php esc_js( __( 'AJAX request failed.', 'wpmudev-plugin-test' ) ); ?>');
						$('#scan-posts-btn').prop('disabled', false);
					});
				}
			});
		</script>
		<?php
	}

	/**
	 * Handle AJAX scan request.
	 *
	 * @since 1.0.0
	 */
	public function handle_scan_ajax() {
		// Verify nonce and permissions.
		if ( ! check_ajax_referer( 'wpmudev_scan_posts', 'nonce', false ) || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'wpmudev-plugin-test' ) );
		}

		$post_types = isset( $_POST['post_types'] ) ? array_map( 'sanitize_text_field', (array) $_POST['post_types'] ) : array();
		$offset     = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;
		$batch_size = 20;

		if ( empty( $post_types ) ) {
			wp_send_json_error( __( 'No post types selected.', 'wpmudev-plugin-test' ) );
		}

		$processed = 0;
		$done      = true;

		foreach ( $post_types as $post_type ) {
			if ( ! post_type_exists( $post_type ) ) {
				continue;
			}

			$query_args = array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => $batch_size,
				'offset'         => $offset,
				'fields'         => 'ids',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			);

			$query = new WP_Query( $query_args );

			foreach ( $query->posts as $post_id ) {
				update_post_meta( $post_id, 'wpmudev_test_last_scan', current_time( 'timestamp' ) );
				$processed++;
			}

			// If we got the full batch size, we're not done yet
			if ( count( $query->posts ) === $batch_size ) {
				$done = false;
			}
		}

		// Update overall last run only when done
		if ( $done ) {
			update_option( 'wpmudev_posts_last_maintenance', current_time( 'timestamp' ) );
		}

		wp_send_json_success(
			array(
				'processed' => $processed,
				'done'      => $done,
			)
		);
	}

	/**
	 * Run full maintenance (for cron).
	 *
	 * @since 1.0.0
	 */
	public function run_maintenance() {
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		$this->scan_all_posts( $post_types );
		update_option( 'wpmudev_posts_last_maintenance', current_time( 'timestamp' ) );
	}

	/**
	 * Scan all posts for given types (full, non-batched).
	 *
	 * @param array $post_types Post types to scan.
	 * @since 1.0.0
	 */
	private function scan_all_posts( $post_types ) {
		foreach ( $post_types as $post_type ) {
			if ( ! post_type_exists( $post_type ) ) {
				continue;
			}

			$posts = get_posts(
				array(
					'post_type'      => $post_type,
					'post_status'    => 'publish',
					'numberposts'    => -1,
					'fields'         => 'ids',
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					'no_found_rows'  => true,
				)
			);

			foreach ( $posts as $post_id ) {
				update_post_meta( $post_id, 'wpmudev_test_last_scan', current_time( 'timestamp' ) );
			}
		}
	}

	/**
	 * WP-CLI command handler.
	 *
	 * ## OPTIONS
	 *
	 * [--post_types=<types>]
	 * : Comma-separated list of post types to scan. Defaults to all public post types.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wpmudev posts-maintenance scan
	 *     wp wpmudev posts-maintenance scan --post_types=post,page
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @since 1.0.0
	 */
	public function cli_scan( $args, $assoc_args ) {
		// Check if WP_CLI is available
		if ( ! class_exists( 'WP_CLI' ) ) {
			return;
		}

		$post_types = isset( $assoc_args['post_types'] ) ? $assoc_args['post_types'] : '';
		if ( $post_types ) {
			$post_types = array_map( 'trim', explode( ',', $post_types ) );
		} else {
			$post_types = get_post_types( array( 'public' => true ), 'names' );
		}

		\WP_CLI::log( 'Starting Posts Maintenance scan for: ' . implode( ', ', $post_types ) );

		$start_time = time();
		$processed  = 0;

		foreach ( $post_types as $post_type ) {
			if ( ! post_type_exists( $post_type ) ) {
				\WP_CLI::warning( "Post type '{$post_type}' does not exist. Skipping." );
				continue;
			}

			$count = wp_count_posts( $post_type );
			$published_count = isset( $count->publish ) ? $count->publish : 0;

			\WP_CLI::log( "Scanning '{$post_type}': {$published_count} published posts." );

			$posts = get_posts(
				array(
					'post_type'      => $post_type,
					'post_status'    => 'publish',
					'numberposts'    => -1,
					'fields'         => 'ids',
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);

			foreach ( $posts as $post_id ) {
				update_post_meta( $post_id, 'wpmudev_test_last_scan', current_time( 'timestamp' ) );
				$processed++;
				if ( $processed % 100 === 0 ) {
					\WP_CLI::log( "Processed {$processed} posts so far..." );
				}
			}
		}

		$duration = time() - $start_time;
		update_option( 'wpmudev_posts_last_maintenance', current_time( 'timestamp' ) );

		\WP_CLI::success( "Scan completed! Processed {$processed} posts in {$duration} seconds." );
	}

	/**
	 * Prepare admin scripts.
	 *
	 * @since 1.0.0
	 */
	public function prepare_admin_scripts() {
		wp_enqueue_script( 'jquery' );
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook The current admin page.
	 * @since 1.0.0
	 */
	public function enqueue_admin_scripts( $hook ) {
		// Check if we're on our specific submenu page
		if ( strpos( $hook, $this->submenu_slug ) === false ) {
			return;
		}
		
		// Enqueue jQuery if not already enqueued
		wp_enqueue_script( 'jquery' );
	}

	/**
	 * Add SUI classes to admin body for styling consistency.
	 *
	 * @param string $classes Existing body classes.
	 * @return string
	 * @since 1.0.0
	 */
	public function admin_body_classes( $classes = '' ) {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return $classes;
		}

		$current_screen = get_current_screen();

		if ( empty( $current_screen->id ) || strpos( $current_screen->id, $this->submenu_slug ) === false ) {
			return $classes;
		}

		$classes .= ' sui-' . str_replace( '.', '-', WPMUDEV_PLUGINTEST_SUI_VERSION ) . ' ';

		return $classes;
	}
}