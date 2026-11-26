<?php
/**
 * WP_DEBUG / WP_DEBUG_DISPLAY on production.
 *
 * @package IWorksIE\HealthCheck
 */

namespace IWorksIE\HealthCheck\Checks\WordPress;

use IWorksIE\HealthCheck\Vendor\IWorksIE\Core\Checks\Check;
use IWorksIE\HealthCheck\Vendor\IWorksIE\Core\Checks\Result;

/**
 * Debug display on production.
 */
final class DebugDisplayCheck implements Check {

	/** @return string */
	public function id() {
		return 'debug_display';
	}

	/** @return string */
	public function label() {
		return __( 'Debug display', 'iworksie-health-check' );
	}

	/** @return string */
	public function group() {
		return 'WordPress';
	}

	/** @return Result */
	public function run() {
		$env     = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
		$debug   = defined( 'WP_DEBUG' ) && WP_DEBUG;
		$display = defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY;
		$why     = __( 'Showing PHP errors on a live site can leak paths, plugin names, and other details useful to attackers, and it looks broken to visitors.', 'iworksie-health-check' );
		$fix     = __( 'In wp-config.php set WP_DEBUG_DISPLAY to false on production. You can keep WP_DEBUG_LOG true to write errors to a private log file instead.', 'iworksie-health-check' );
		$data    = array(
			'environment'      => $env,
			'WP_DEBUG'         => $debug,
			'WP_DEBUG_DISPLAY' => $display,
		);

		if ( 'production' === $env && $display ) {
			return Result::critical(
				$this->label(),
				__( 'Error display is enabled on a production environment.', 'iworksie-health-check' ),
				array(
					'why'  => $why,
					'fix'  => $fix,
					'data' => $data,
				)
			);
		}
		if ( 'production' === $env && $debug ) {
			return Result::recommended(
				$this->label(),
				__( 'WP_DEBUG is on in production. Prefer logging instead of displaying errors.', 'iworksie-health-check' ),
				array(
					'why'  => $why,
					'fix'  => $fix,
					'data' => $data,
				)
			);
		}
		return Result::good(
			$this->label(),
			sprintf(
				/* translators: %s: environment type */
				__( 'Debug display looks fine for the %s environment.', 'iworksie-health-check' ),
				$env
			),
			array(
				'why'  => $why,
				'fix'  => __( 'No action needed.', 'iworksie-health-check' ),
				'data' => $data,
			)
		);
	}
}
