<?php
/**
 * Autoloaded options size.
 *
 * @package IWorksIE\HealthCheck
 */

namespace IWorksIE\HealthCheck\Checks\Database;

use IWorksIE\HealthCheck\Support\Bytes;
use IWorksIE\HealthCheck\Support\OptionOwner;
use IWorksIE\HealthCheck\Support\Thresholds;
use IWorksIE\HealthCheck\Vendor\IWorksIE\Core\Checks\Check;
use IWorksIE\HealthCheck\Vendor\IWorksIE\Core\Checks\Result;

/**
 * Autoload bloat.
 */
final class AutoloadSizeCheck implements Check {

	/** @return string */
	public function id() {
		return 'autoload_size';
	}

	/** @return string */
	public function label() {
		return __( 'Autoloaded options size', 'iworksie-health-check' );
	}

	/** @return string */
	public function group() {
		return 'database';
	}

	/** @return Result */
	public function run() {
		global $wpdb;
		$rec  = (int) Thresholds::get( 'autoload_recommended_bytes' );
		$crit = (int) Thresholds::get( 'autoload_critical_bytes' );
		$why  = __( 'Autoloaded options are fetched on nearly every page load. When that bundle grows large, every visitor pays the cost.', 'iworksie-health-check' );
		$fix  = __( 'Ask a developer to review the largest autoloaded options and stop autoloading data that is only needed occasionally. Do not delete options unless you know what they do. This plugin never changes them.', 'iworksie-health-check' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) $wpdb->get_var(
			"SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload IN ( 'yes', 'on', 'auto', 'auto-on' )"
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			"SELECT option_name, LENGTH(option_value) AS bytes
			FROM {$wpdb->options}
			WHERE autoload IN ( 'yes', 'on', 'auto', 'auto-on' )
			ORDER BY bytes DESC
			LIMIT 10",
			ARRAY_A
		);

		$slugs = self::plugin_slugs();
		$top   = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$name  = isset( $row['option_name'] ) ? (string) $row['option_name'] : '';
				$bytes = isset( $row['bytes'] ) ? (int) $row['bytes'] : 0;
				$owner = OptionOwner::guess( $name, $slugs );
				$top[] = array(
					'name'  => $name,
					'bytes' => $bytes,
					'owner' => $owner,
					'guess' => null !== $owner
						? sprintf(
							/* translators: %s: plugin slug */
							__( 'possibly %s', 'iworksie-health-check' ),
							$owner
						)
						: __( 'unknown', 'iworksie-health-check' ),
				);
			}
		}

		$desc = sprintf(
			/* translators: %s: formatted size */
			__( 'Autoloaded options total about %s.', 'iworksie-health-check' ),
			Bytes::format( $total )
		);
		$data = array(
			'total_bytes' => $total,
			'top'         => $top,
		);

		if ( $total >= $crit ) {
			return Result::critical(
				$this->label(),
				$desc,
				array(
					'why'  => $why,
					'fix'  => $fix,
					'data' => $data,
				)
			);
		}
		if ( $total >= $rec ) {
			return Result::recommended(
				$this->label(),
				$desc,
				array(
					'why'  => $why,
					'fix'  => $fix,
					'data' => $data,
				)
			);
		}
		return Result::good(
			$this->label(),
			$desc,
			array(
				'why'  => $why,
				'fix'  => __( 'No action needed.', 'iworksie-health-check' ),
				'data' => $data,
			)
		);
	}

	/**
	 * @return array<int,string>
	 */
	private static function plugin_slugs() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$slugs = array();
		foreach ( array_keys( get_plugins() ) as $file ) {
			$slugs[] = dirname( $file );
			if ( '.' === dirname( $file ) ) {
				$slugs[] = basename( $file, '.php' );
			}
		}
		return array_values( array_unique( array_filter( $slugs ) ) );
	}
}
