<?php
/**
 * Plugin-specific AJAX endpoints.
 *
 * @package IWorksIE\HealthCheck
 */

namespace IWorksIE\HealthCheck\Admin;

use IWorksIE\HealthCheck\Support\DiskSummary;
use IWorksIE\HealthCheck\Support\ResultsCache;

/**
 * Cache results, finalise disk scan, test email.
 */
final class Ajax {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'wp_ajax_iwiehc_cache_results', array( $this, 'cache_results' ) );
		add_action( 'wp_ajax_iwiehc_save_disk_summary', array( $this, 'save_disk_summary' ) );
		add_action( 'wp_ajax_iwiehc_test_email', array( $this, 'test_email' ) );
	}

	/**
	 * Persist overview results after a full run.
	 *
	 * @return void
	 */
	public function cache_results() {
		check_ajax_referer( 'iwiehc_checks', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_network_options' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		$raw = isset( $_POST['results'] ) ? wp_unslash( $_POST['results'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( is_string( $raw ) ) {
			$decoded = json_decode( $raw, true );
		} elseif ( is_array( $raw ) ) {
			$decoded = $raw;
		} else {
			$decoded = null;
		}
		if ( ! is_array( $decoded ) ) {
			wp_send_json_error( array( 'message' => 'invalid' ), 400 );
		}
		$clean = array();
		foreach ( $decoded as $id => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$clean[ sanitize_key( (string) $id ) ] = array(
				'status'      => isset( $row['status'] ) ? sanitize_key( (string) $row['status'] ) : 'info',
				'label'       => isset( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : '',
				'description' => isset( $row['description'] ) ? sanitize_textarea_field( (string) $row['description'] ) : '',
				'why'         => isset( $row['why'] ) ? sanitize_textarea_field( (string) $row['why'] ) : '',
				'fix'         => isset( $row['fix'] ) ? sanitize_textarea_field( (string) $row['fix'] ) : '',
				'data'        => isset( $row['data'] ) && is_array( $row['data'] ) ? $row['data'] : array(),
				'actions'     => isset( $row['actions'] ) && is_array( $row['actions'] ) ? $row['actions'] : array(),
			);
		}
		ResultsCache::save( $clean );
		wp_send_json_success(
			array(
				'last_checked' => ResultsCache::last_checked_label(),
			)
		);
	}

	/**
	 * Persist disk scan summary from job state.
	 *
	 * @return void
	 */
	public function save_disk_summary() {
		check_ajax_referer( 'iwiehc_jobs', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_network_options' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		$state = get_option( 'iwiehc_job_size_scanner', null );
		if ( ! is_array( $state ) ) {
			wp_send_json_error( array( 'message' => 'no_state' ), 400 );
		}
		$summary = DiskSummary::from_job( $state );
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
