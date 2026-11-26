<?php
/**
 * Configurable thresholds for health checks.
 *
 * @package IWorksIE\HealthCheck
 */

namespace IWorksIE\HealthCheck\Support;

/**
 * Central thresholds, filterable via `iwiehc_thresholds`.
 */
final class Thresholds {

	/**
	 * Default threshold map.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'memory_recommended_bytes'   => 256 * 1024 * 1024,
			'memory_critical_bytes'      => 128 * 1024 * 1024,
			'autoload_recommended_bytes' => 800 * 1024,
			'autoload_critical_bytes'    => 2 * 1024 * 1024,
			'disk_free_recommended'      => 2 * 1024 * 1024 * 1024,
			'disk_free_critical'         => 500 * 1024 * 1024,
			'cron_overdue_recommended'   => defined( 'HOUR_IN_SECONDS' ) ? HOUR_IN_SECONDS : 3600,
			'cron_overdue_critical'      => defined( 'DAY_IN_SECONDS' ) ? DAY_IN_SECONDS : 86400,
			'mysql_min_version'          => '8.0',
			'mariadb_min_version'        => '10.6',
		);
	}

	/**
	 * Filtered thresholds.
	 *
	 * @return array<string,mixed>
	 */
	public static function all() {
		$defaults = self::defaults();
		$filtered = apply_filters( 'iwiehc_thresholds', $defaults );
		return is_array( $filtered ) ? array_merge( $defaults, $filtered ) : $defaults;
	}

	/**
	 * One threshold value.
	 *
	 * @param string $key Threshold key.
	 * @param mixed  $fallback Fallback.
	 * @return mixed
	 */
	public static function get( $key, $fallback = null ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $fallback;
	}
}
