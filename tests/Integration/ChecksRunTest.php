<?php
/**
 * Every registered check returns a valid Result.
 *
 * @package IWorksIE\HealthCheck
 */

namespace IWorksIE\HealthCheck\Tests\Integration;

use IWorksIE\HealthCheck\Plugin;
use WP_UnitTestCase;

/**
 * Integration: checks on a vanilla install.
 */
final class ChecksRunTest extends WP_UnitTestCase {

	public function test_every_check_returns_valid_result() {
		$plugin = Plugin::instance();
		$core   = $plugin->core();
		$this->assertNotNull( $core );
		$allowed = array( 'good', 'recommended', 'critical', 'info' );
		foreach ( $core->checks()->all() as $id => $check ) {
			$result = $check->run();
			$this->assertContains( $result->status(), $allowed, $id );
			$this->assertNotSame( '', $result->label(), $id );
			$this->assertIsArray( $result->to_array() );
		}
	}
}
