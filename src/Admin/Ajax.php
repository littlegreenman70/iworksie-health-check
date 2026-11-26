<?php
/**
 * Plugin-specific AJAX endpoints.
 *
 * @package IWorksIE\HealthCheck
 */

namespace IWorksIE\HealthCheck\Admin;

use IWorksIE\HealthCheck\Plugin;
use IWorksIE\HealthCheck\Support\DiskSummary;
use IWorksIE\HealthCheck\Support\ResultsCache;
use IWorksIE\HealthCheck\Vendor\IWorksIE\Core\Checks\Result;
use IWorksIE\HealthCheck\Vendor\IWorksIE\Core\Strings;

/**
 * Run/cache checks, finalise disk scan, test email.
 */
final class Ajax {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		// Priority 5 so we run (and die) before core's iwiehc_run_check handler.
		add_action( 'wp_ajax_iwiehc_run_check', array( $this, 'run_check' ), 5 );
		add_action( 'wp_ajax_iwiehc_cache_results', array( $this, 'cache_results' ) );
		add_action( 'wp_ajax_iwiehc_save_disk_summary', array( $this, 'save_disk_summary' ) );
		add_action( 'wp_ajax_iwiehc_test_email', array( $this, 'test_email' ) );
	}

	/**
	 * Run one check via core's registry and store Result::to_array() server-side.
	 *
	 * @return void
	 */
	public function run_check() {
		check_ajax_referer( 'iwiehc_checks', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_network_options' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}

		$core = Plugin::instance()->core();
		if ( null === $core ) {
			wp_send_json_error( array( 'message' => 'not_booted' ), 500 );
		}

		$id    = isset( $_POST['check_id'] ) ? sanitize_key( wp_unslash( (string) $_POST['check_id'] ) ) : '';
		$check = $core->checks()->get( $id );
		if ( null === $check ) {
			wp_send_json_error( array( 'message' => 'unknown_check' ), 404 );
		}

		try {
			$result = $check->run();
		} catch ( \Throwable $e ) {
			$result = Result::critical(
				$check->label(),
				Strings::get( 'check_failed', array( 'message' => $e->getMessage() ) )
			);
		}

		$payload = $result->to_array();
		ResultsCache::save_one( $id, $payload );
		wp_send_json_success( $payload );
	}

	/**
	 * Stamp "last checked" after a finished UI run. Does not accept client results.
	 *
	 * @return void
	 */
	public function cache_results() {
		check_ajax_referer( 'iwiehc_checks', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_network_options' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		ResultsCache::touch();
		wp_send_json_success(
			array(
				'last_checked' => ResultsCache::last_checked_label(),
			)
		);
	}

	/**
	 * Persist disk scan summary from the finished job result only.
	 *
	 * @return void
	 */
	public function save_disk_summary() {
		check_ajax_referer( 'iwiehc_jobs', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_network_options' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		$state = get_option( 'iwiehc_job_size_scanner', null );
		if ( ! is_array( $state ) || empty( $state['result'] ) || ! is_array( $state['result'] ) ) {
			wp_send_json_error( array( 'message' => 'no_result' ), 400 );
		}
		$summary = DiskSummary::from_job( array( 'result' => $state['result'] ) );
		ResultsCache::save_disk( $summary );
		wp_send_json_success( $summary );
	}

	/**
	 * Send a test email to the admin address.
	 *
	 * @return void
	 */
	public function test_email() {
		check_ajax_referer( 'iwiehc_test_email', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_network_options' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}

		$to = (string) get_option( 'admin_email' );
		if ( ! is_email( $to ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'The site admin email address is not valid.', 'iworksie-health-check' ),
				),
				400
			);
		}

		$error_message = '';
		$failed        = static function ( $error ) use ( &$error_message ) {
			if ( is_wp_error( $error ) ) {
				$error_message = $error->get_error_message();
			}
		};
		add_action( 'wp_mail_failed', $failed );

		$subject = sprintf(
			/* translators: %s: site name */
			__( '[%s] iWorks.ie Health Check test email', 'iworksie-health-check' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
		);
		$body = __( "This is a test email from iWorks.ie Health Check.\n\nIf you received this, WordPress was able to hand the message to the mail system. That does not always mean an inbox accepted it.\n", 'iworksie-health-check' );
		$sent = wp_mail( $to, $subject, $body );
		remove_action( 'wp_mail_failed', $failed );

		$domain = '';
		$pos    = strpos( $to, '@' );
		if ( false !== $pos ) {
			$domain = substr( $to, $pos );
		}

		if ( $sent && '' === $error_message ) {
			wp_send_json_success(
				array(
					'message' => sprintf(
						/* translators: %s: email domain */
						__( 'WordPress reported the test email as sent to the admin address (%s). “Sent” means it was handed off to the mail system, not that it arrived. If you do not see it, check spam or configure SMTP.', 'iworksie-health-check' ),
						$domain
					),
				)
			);
		}

		$detail = '' !== $error_message ? $error_message : __( 'wp_mail() returned false without a detailed error.', 'iworksie-health-check' );
		wp_send_json_error(
			array(
				'message' => sprintf(
					/* translators: %s: error detail */
					__( 'The test email failed or was blocked: %s. On local development sites a safety plugin often blocks mail on purpose. On live sites, SMTP is the usual fix.', 'iworksie-health-check' ),
					$detail
				),
			)
		);
	}
}
