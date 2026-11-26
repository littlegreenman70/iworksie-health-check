<?php
/**
 * ResultsCache sanitises actions to admin URLs.
 *
 * @package IWorksIE\HealthCheck
 */

namespace IWorksIE\HealthCheck\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use IWorksIE\HealthCheck\Support\ResultsCache;
use PHPUnit\Framework\TestCase;

/**
 * @covers \IWorksIE\HealthCheck\Support\ResultsCache
 */
final class ResultsCacheSanitizeTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'sanitize_key' )->alias(
			static function ( $v ) {
				return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $v ) );
			}
		);
		Functions\when( 'sanitize_text_field' )->returnArg( 1 );
		Functions\when( 'sanitize_textarea_field' )->returnArg( 1 );
		Functions\when( 'esc_url_raw' )->returnArg( 1 );
		Functions\when( 'admin_url' )->justReturn( 'https://example.com/wp-admin/' );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_sanitize_result_keeps_only_admin_actions() {
		$row = ResultsCache::sanitize_result(
			array(
				'status'      => 'recommended',
				'label'       => 'Updates',
				'description' => 'There are updates',
				'why'         => 'Security',
				'fix'         => 'Update now',
				'data'        => array( 'count' => 2 ),
				'actions'     => array(
					array(
						'label' => 'Update',
						'url'   => 'https://example.com/wp-admin/update-core.php',
					),
					array(
						'label' => 'Evil',
						'url'   => 'https://evil.example/phish',
					),
				),
			)
		);

		$this->assertSame( 'recommended', $row['status'] );
		$this->assertCount( 1, $row['actions'] );
		$this->assertSame( 'https://example.com/wp-admin/update-core.php', $row['actions'][0]['url'] );
	}
}
