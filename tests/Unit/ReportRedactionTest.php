<?php
/**
 * Report redaction must never leak secrets.
 *
 * @package IWorksIE\HealthCheck
 */

namespace IWorksIE\HealthCheck\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use IWorksIE\HealthCheck\Support\ReportBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @covers \IWorksIE\HealthCheck\Support\ReportBuilder
 */
final class ReportRedactionTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'get_option' )->justReturn( 'admin@example.com' );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_redacts_password_and_keys() {
		if ( ! defined( 'DB_PASSWORD' ) ) {
			define( 'DB_PASSWORD', 'super-secret-db-pass' );
		}
		$raw = "Site report\nDB_PASSWORD=super-secret-db-pass\nAUTH_KEY: abcdef\napi_key=xyz\nadmin@example.com\n";
		$out = ReportBuilder::redact( $raw );
		$this->assertStringNotContainsString( 'super-secret-db-pass', $out );
		$this->assertStringNotContainsString( 'abcdef', $out );
		$this->assertStringNotContainsString( 'api_key=xyz', $out );
		$this->assertStringNotContainsString( 'admin@example.com', $out );
		$this->assertStringContainsString( '@example.com', $out );
	}
}
