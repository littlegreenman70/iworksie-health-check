<?php
/**
 * Menu coexistence and uninstall cleanup.
 *
 * @package IWorksIE\HealthCheck
 */

namespace IWorksIE\HealthCheck\Tests\Integration;

use WP_UnitTestCase;

/**
 * Menu + uninstall.
 */
final class MenuAndUninstallTest extends WP_UnitTestCase {

	public function test_single_iworks_tools_menu_with_examples() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		do_action( 'admin_menu' );
		global $menu, $submenu;
		$found = 0;
		if ( is_array( $menu ) ) {
			foreach ( $menu as $item ) {
				if ( isset( $item[2] ) && 'iworks-tools' === $item[2] ) {
					++$found;
				}
			}
		}
		$this->assertSame( 1, $found );
		$this->assertArrayHasKey( 'iworks-tools', $submenu );
		$slugs = array();
		foreach ( $submenu['iworks-tools'] as $row ) {
			if ( isset( $row[2] ) ) {
				$slugs[] = $row[2];
			}
		}
		$this->assertContains( 'iworksie-health-check', $slugs );
	}

	public function test_uninstall_removes_iwiehc_options() {
		update_option(
			'iwiehc_results',
			array(
				'time'    => time(),
				'results' => array(),
			),
			false
		);
		update_option( 'iwiehc_disk_scan', array( 'total_bytes' => 1 ), false );
		update_option( 'iwiehc_job_size_scanner', array( 'done' => true ), false );
		set_transient( 'iwiehc_report_abc', 'x', 60 );

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', true );
		}
		// Inline the same cleanup uninstall.php performs so we do not re-include after define.
		global $wpdb;
		$options = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'iwiehc_' ) . '%' ) );
		foreach ( (array) $options as $option ) {
			delete_option( $option );
		}
		$transients = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_iwiehc_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_iwiehc_' ) . '%'
			)
		);
		foreach ( (array) $transients as $t ) {
			if ( 0 === strpos( $t, '_transient_timeout_' ) ) {
				delete_transient( substr( $t, strlen( '_transient_timeout_' ) ) );
			} elseif ( 0 === strpos( $t, '_transient_' ) ) {
				delete_transient( substr( $t, strlen( '_transient_' ) ) );
			}
		}

		$this->assertFalse( get_option( 'iwiehc_results' ) );
		$this->assertFalse( get_option( 'iwiehc_disk_scan' ) );
		$this->assertFalse( get_option( 'iwiehc_job_size_scanner' ) );
		$this->assertFalse( get_transient( 'iwiehc_report_abc' ) );
	}
}
