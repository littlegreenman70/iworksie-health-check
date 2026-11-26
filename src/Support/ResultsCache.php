<?php
/**
 * Persist check results between admin loads.
 *
 * @package IWorksIE\HealthCheck
 */

namespace IWorksIE\HealthCheck\Support;

/**
 * Non-autoloaded option cache for check results.
 */
final class ResultsCache {

	const OPTION = 'iwiehc_results';
	const DISK   = 'iwiehc_disk_scan';

	/**
	 * Store results.
	 *
	 * @param array<string,array<string,mixed>> $results Results keyed by check id.
	 * @return void
	 */
	public static function save( array $results ) {
		update_option(
			self::OPTION,
			array(
				'time'    => time(),
				'results' => $results,
			),
			false
		);
	}

	/**
	 * Load cached payload.
	 *
	 * @return array{time:int,results:array<string,array<string,mixed>>}|null
	 */
	public static function load() {
		$data = get_option( self::OPTION, null );
		if ( ! is_array( $data ) || empty( $data['results'] ) || ! is_array( $data['results'] ) ) {
			return null;
		}
		return array(
			'time'    => isset( $data['time'] ) ? (int) $data['time'] : 0,
			'results' => $data['results'],
		);
	}

	/**
	 * Human "Last checked …" string.
	 *
	 * @return string
	 */
	public static function last_checked_label() {
		$data = self::load();
		if ( null === $data || empty( $data['time'] ) ) {
			return __( 'Not checked yet.', 'iworksie-health-check' );
		}
		return sprintf(
			/* translators: %s: human time difference */
			__( 'Last checked %s ago', 'iworksie-health-check' ),
			human_time_diff( (int) $data['time'], time() )
		);
	}

	/**
	 * Save disk scan summary for the report.
	 *
	 * @param array<string,mixed> $summary Summary.
	 * @return void
	 */
	public static function save_disk( array $summary ) {
		update_option( self::DISK, $summary, false );
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public static function load_disk() {
		$data = get_option( self::DISK, null );
		return is_array( $data ) ? $data : null;
	}
}
