<?php
/**
 * Persistent object cache presence.
 *
 * @package IWorksIE\HealthCheck
 */

namespace IWorksIE\HealthCheck\Checks\WordPress;

use IWorksIE\HealthCheck\Vendor\IWorksIE\Core\Checks\Check;
use IWorksIE\HealthCheck\Vendor\IWorksIE\Core\Checks\Result;

/**
 * Object cache drop-in.
 */
final class ObjectCacheCheck implements Check {

	/** @return string */
	public function id() {
		return 'object_cache';
	}

	/** @return string */
	public function label() {
		return __( 'Persistent object cache', 'iworksie-health-check' );
	}

	/** @return string */
	public function group() {
		return 'WordPress';
	}

	/** @return Result */
	public function run() {
		$using = wp_using_ext_object_cache();
		$why   = __( 'A persistent object cache (Redis, Memcached) reduces database load on busier sites.', 'iworksie-health-check' );
		$fix   = __( 'Ask your host whether Redis or Memcached is available, then install a compatible object-cache.php drop-in. Small brochure sites often do not need this.', 'iworksie-health-check' );

		if ( $using ) {
			return Result::good(
				$this->label(),
				__( 'A persistent object cache is in use.', 'iworksie-health-check' ),
				array(
					'why'  => $why,
					'fix'  => __( 'No action needed.', 'iworksie-health-check' ),
					'data' => array( 'using' => true ),
				)
			);
		}
		return Result::info(
			$this->label(),
			__( 'No persistent object cache detected. Fine for small sites; recommended for larger or high-traffic ones.', 'iworksie-health-check' ),
			array(
				'why'  => $why,
				'fix'  => $fix,
				'data' => array( 'using' => false ),
			)
		);
	}
}
