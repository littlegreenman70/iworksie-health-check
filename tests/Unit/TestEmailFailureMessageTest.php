<?php
/**
 * Test-email failure copy strips a trailing full stop from the detail.
 *
 * @package IWorksIE\HealthCheck
 */

namespace IWorksIE\HealthCheck\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use IWorksIE\HealthCheck\Admin\Ajax;
use PHPUnit\Framework\TestCase;

/**
 * @covers \IWorksIE\HealthCheck\Admin\Ajax::format_test_email_failure
 */
final class TestEmailFailureMessageTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( '__' )->alias(
			static function ( $text ) {
				return $text;
			}
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_strips_trailing_full_stop_from_detail() {
		$message = Ajax::format_test_email_failure( 'wp_mail() returned false without a detailed error.' );
		$this->assertStringNotContainsString( 'error..', $message );
		$this->assertStringContainsString(
			'The test email failed or was blocked: wp_mail() returned false without a detailed error. On local development',
			$message
		);
	}
}
