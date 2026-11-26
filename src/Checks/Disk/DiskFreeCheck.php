<?php
/**
 * Free disk space.
 *
 * @package IWorksIE\HealthCheck
 */

namespace IWorksIE\HealthCheck\Checks\Disk;

use IWorksIE\HealthCheck\Support\Bytes;
use IWorksIE\HealthCheck\Support\Thresholds;
use IWorksIE\HealthCheck\Vendor\IWorksIE\Core\Checks\Check;
use IWorksIE\HealthCheck\Vendor\IWorksIE\Core\Checks\Result;

/**
 * disk_free_space around ABSPATH.
 */
final class DiskFreeCheck implements Check {

	/** @return string */
	public function id() {
		return 'disk_free';
	}

	/** @return string */
	public function label() {
		return __( 'Free disk space', 'iworksie-health-check' );
	}

	/** @return string */
	public function group() {
		return 'disk';
	}

	/** @return Result */
	public function run() {
		$path = defined( 'ABSPATH' ) ? ABSPATH : '/';
		$free = function_exists( 'disk_free_space' ) ? @disk_free_space( $path ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$why  = __( 'When the disk fills up, WordPress cannot write uploads, caches, or updates, and the site can go down.', 'iworksie-health-check' );
		$fix  = __( 'Ask your host for more disk space, remove unused backups and large media, and run the Disk & dead weight scan in this plugin to find heavy folders. Never delete files without a backup.', 'iworksie-health-check' );

		if ( false === $free ) {
			return Result::info(
				$this->label(),
				__( 'Could not read free disk space on this host.', 'iworksie-health-check' ),
				array(
					'why'  => $why,
					'fix'  => $fix,
					'data' => array( 'available' => false ),
				)
			);
		}

		$free = (int) $free;
		$rec  = (int) Thresholds::get( 'disk_free_recommended' );
		$crit = (int) Thresholds::get( 'disk_free_critical' );
		$desc = sprintf(
			/* translators: %s: formatted free space */
			__( 'About %s free on the disk that holds WordPress.', 'iworksie-health-check' ),
			Bytes::format( $free )
		);
		$data = array( 'bytes' => $free );

		if ( $free < $crit ) {
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
		if ( $free < $rec ) {
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
