<?php
/**
 * PHP and WordPress memory limit check.
 *
 * @package IWorksIE\HealthCheck
 */

namespace IWorksIE\HealthCheck\Checks\Server;

use IWorksIE\HealthCheck\Support\Bytes;
use IWorksIE\HealthCheck\Support\Thresholds;
use IWorksIE\HealthCheck\Vendor\IWorksIE\Core\Checks\Check;
use IWorksIE\HealthCheck\Vendor\IWorksIE\Core\Checks\Result;

/**
 * Memory limits.
 */
final class MemoryLimitCheck implements Check {

	/** @return string */
	public function id() {
		return 'memory_limit';
	}

	/** @return string */
	public function label() {
		return __( 'Memory limit', 'iworksie-health-check' );
	}

	/** @return string */
	public function group() {
		return 'server';
	}

	/** @return Result */
	public function run() {
		$php_raw = (string) ini_get( 'memory_limit' );
		$php     = Bytes::parse( $php_raw );
		$wp      = defined( 'WP_MEMORY_LIMIT' ) ? Bytes::parse( WP_MEMORY_LIMIT ) : 0;
		$wp_max  = defined( 'WP_MAX_MEMORY_LIMIT' ) ? Bytes::parse( WP_MAX_MEMORY_LIMIT ) : 0;
		$eff     = $php;
		if ( $php < 0 ) {
			$eff = PHP_INT_MAX;
		}

		$crit = (int) Thresholds::get( 'memory_critical_bytes' );
		$rec  = (int) Thresholds::get( 'memory_recommended_bytes' );
		$why  = __( 'Low memory causes white screens, failed imports, and broken page builders. WordPress and plugins share this budget.', 'iworksie-health-check' );
		$fix  = __( 'Raise memory_limit in php.ini or ask your host. You can also define WP_MEMORY_LIMIT and WP_MAX_MEMORY_LIMIT in wp-config.php (for example 256M).', 'iworksie-health-check' );
		$desc = sprintf(
			/* translators: 1: PHP memory_limit, 2: WP_MEMORY_LIMIT, 3: WP_MAX_MEMORY_LIMIT */
			__( 'PHP memory_limit is %1$s. WP_MEMORY_LIMIT is %2$s. WP_MAX_MEMORY_LIMIT is %3$s.', 'iworksie-health-check' ),
			$php_raw,
			defined( 'WP_MEMORY_LIMIT' ) ? (string) WP_MEMORY_LIMIT : __( 'not set', 'iworksie-health-check' ),
			defined( 'WP_MAX_MEMORY_LIMIT' ) ? (string) WP_MAX_MEMORY_LIMIT : __( 'not set', 'iworksie-health-check' )
		);
		$data = array(
			'php'    => $php_raw,
			'wp'     => $wp,
			'wp_max' => $wp_max,
		);

		if ( $php >= 0 && $eff < $crit ) {
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
		if ( $php >= 0 && $eff < $rec ) {
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
}
