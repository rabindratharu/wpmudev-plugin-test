<?php
/**
 * Unit tests for Posts Maintenance.
 */

class Test_Posts_Maintenance extends WP_UnitTestCase {

	/**
	 * Test basic scan updates meta on published posts.
	 */
	public function test_scan_updates_published_posts() {
		$post_id = $this->factory->post->create( array( 'post_status' => 'publish' ) );

		WPMUDEV\PluginTest\Posts_Maintenance::scan_posts( array( 'post' ) ); // Static method for testing.

		$last_scan = get_post_meta( $post_id, 'wpmudev_test_last_scan', true );
		$this->assertNotEmpty( $last_scan );
		$this->assertEquals( current_time( 'timestamp' ), $last_scan );
	}

	/**
	 * Test non-published posts are skipped.
	 */
	public function test_scan_skips_non_published() {
		$post_id = $this->factory->post->create( array( 'post_status' => 'draft' ) );

		WPMUDEV\PluginTest\Posts_Maintenance::scan_posts( array( 'post' ) );

		$last_scan = get_post_meta( $post_id, 'wpmudev_test_last_scan', true );
		$this->assertEmpty( $last_scan );
	}

	/**
	 * Test invalid post type is handled.
	 */
	public function test_scan_invalid_post_type() {
		$initial_option = get_option( 'wpmudev_posts_last_maintenance', 0 );
		WPMUDEV\PluginTest\Posts_Maintenance::scan_posts( array( 'invalid_type' ) );
		$this->assertEquals( $initial_option, get_option( 'wpmudev_posts_last_maintenance', 0 ) ); // No update if no valid types.
	}

	/**
	 * Test multiple post types.
	 */
	public function test_scan_multiple_types() {
		$post_id1 = $this->factory->post->create( array( 'post_type' => 'post', 'post_status' => 'publish' ) );
		$post_id2 = $this->factory->post->create( array( 'post_type' => 'page', 'post_status' => 'publish' ) );

		WPMUDEV\PluginTest\Posts_Maintenance::scan_posts( array( 'post', 'page' ) );

		$this->assertNotEmpty( get_post_meta( $post_id1, 'wpmudev_test_last_scan', true ) );
		$this->assertNotEmpty( get_post_meta( $post_id2, 'wpmudev_test_last_scan', true ) );
	}

	/**
	 * Test cron schedules correctly.
	 */
	public function test_cron_scheduling() {
		$this->assertNotEmpty( wp_next_scheduled( 'wpmudev_posts_maintenance_cron' ) );
	}
}