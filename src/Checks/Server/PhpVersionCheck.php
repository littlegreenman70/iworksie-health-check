<?php
/**
 * PHP version and EOL check.
 *
 * @package IWorksIE\HealthCheck
 */

namespace IWorksIE\HealthCheck\Checks\Server;

use IWorksIE\HealthCheck\Support\PhpEol;
use IWorksIE\HealthCheck\Vendor\IWorksIE\Core\Checks\Check;
use IWorksIE\HealthCheck\Vendor\IWorksIE\Core\Checks\Result;

/**
 * PHP version status.
 */
final class PhpVersionCheck implements Check {

	/** @return string */
	public function id() {
		return 'php_version';
	}

	/** @return string */
	public function label() {
		return __( 'PHP version', 'iworksie-health-check' );
	}

	/** @return string */
	public function group() {
		return 'server';
	}

	/** @return Result */
	public function run() {
		$version = PHP_VERSION;
		$status  = PhpEol::classify( $version );
		$branch  = PhpEol::branch_key( $version );
		$why     = __( 'PHP is the language WordPress runs on. Unsupported versions stop receiving security fixes.', 'iworksie-health-check' );
		$fix     = __( 'Ask your host to move this site to a currently supported PHP version (8.2 or newer is a good target). Take a backup first.', 'iworksie-health-check' );

		if ( 'critical' === $status ) {
			return Result::critical(
				$this->label(),
				sprintf(
					/* translators: %s: PHP version */
					__( 'PHP %s is below the supported range or past end of life.', 'iworksie-health-check' ),
					$version
				),
				array(
					'why'  => $why,
					'fix'  => $fix,
					'data' => array(
						'version' => $version,
						'branch'  => $branch,
					),
				)
			);
		}
		if ( 'recommended' === $status ) {
			return Result::recommended(
				$this->label(),
				sprintf(
					/* translators: %s: PHP version */
					__( 'PHP %s is in security-only support. Plan an upgrade.', 'iworksie-health-check' ),
					$version
				),
				array(
					'why'  => $why,
					'fix'  => $fix,
					'data' => array(
						'version' => $version,
						'branch'  => $branch,
					),
				)
			);
		}
		return Result::good(
			$this->label(),
			sprintf(
				/* translators: %s: PHP version */
				__( 'PHP %s is actively supported.', 'iworksie-health-check' ),
				$version
			),
			array(
				'why'  => $why,
				'fix'  => __( 'No action needed right now. Keep an eye on PHP release calendars once a year.', 'iworksie-health-check' ),
				'data' => array(
					'version' => $version,
					'branch'  => $branch,
				),
			)
		);
	}
}
