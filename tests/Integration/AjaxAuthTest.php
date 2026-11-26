<?php
/**
 * AJAX endpoints reject bad nonce / non-admin.
 *
 * @package IWorksIE\HealthCheck
 */

namespace IWorksIE\HealthCheck\Tests\Integration;

use WP_Ajax_UnitTestCase;

/**
 * Auth guards using the WP ajax test harness.
 */
final class AjaxAuthTest extends WP_Ajax_UnitTestCase {

	/**
	 * Bad nonce on run_check dies with -1.
	 *
	 * @return void
	 */
	public function test_run_check_rejects_bad_nonce() {
		$this->_setRole( 'administrator' );
		$_POST['nonce']    = 'bad';
		$_POST['check_id'] = 'php_version';
		try {
			$this->_handleAjax( 'iwiehc_run_check' );
			$this->fail( 'Expected ajax die for bad nonce' );
		} catch ( \WPAjaxDieStopException $e ) {
			$this->assertSame( '-1', $e->getMessage() );
		}
	}

	/**
	 * Subscriber cannot send test email.
	 *
	 * @return void
	 */
	public function test_test_email_rejects_subscriber() {
		$this->_setRole( 'subscriber' );
		$_POST['nonce'] = wp_create_nonce( 'iwiehc_test_email' );
		try {
			$this->_handleAjax( 'iwiehc_test_email' );
			$this->fail( 'Expected ajax die for subscriber' );
		} catch ( \WPAjaxDieStopException $e ) {
			$this->assertTrue( true );
		} catch ( \WPAjaxDieContinueException $e ) {
			$response = json_decode( $this->_last_response, true );
			$this->assertTrue( empty( $response['success'] ) || false === $response['success'] );
		}
	}
}
