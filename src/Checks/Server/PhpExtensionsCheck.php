<?php
/**
 * Required / recommended PHP extensions.
 *
 * @package IWorksIE\HealthCheck
 */

namespace IWorksIE\HealthCheck\Checks\Server;

use IWorksIE\HealthCheck\Vendor\IWorksIE\Core\Checks\Check;
use IWorksIE\HealthCheck\Vendor\IWorksIE\Core\Checks\Result;

/**
 * Extension presence.
 */
final class PhpExtensionsCheck implements Check {

	/** @return string */
	public function id() {
		return 'php_extensions';
	}

	/** @return string */
	public function label() {
		return __( 'PHP extensions', 'iworksie-health-check' );
	}

	/** @return string */
	public function group() {
		return 'server';
	}

	/** @return Result */
	public function run() {
		$required    = array( 'json', 'openssl', 'mbstring', 'curl', 'zlib' );
		$recommended = array( 'zip', 'intl', 'exif' );
		$missing_req = array();
		$missing_rec = array();

		foreach ( $required as $ext ) {
			if ( ! extension_loaded( $ext ) ) {
				$missing_req[] = $ext;
			}
		}
		foreach ( $recommended as $ext ) {
			if ( ! extension_loaded( $ext ) ) {
				$missing_rec[] = $ext;
			}
		}
		$has_image = extension_loaded( 'imagick' ) || extension_loaded( 'gd' );
		if ( ! $has_image ) {
			$missing_rec[] = 'imagick/gd';
		}

		$why  = __( 'Extensions unlock features WordPress and plugins expect: HTTPS requests, zip installs, image processing, and international text.', 'iworksie-health-check' );
		$fix  = __( 'Ask your host to enable the missing PHP extensions listed here, then reload this page.', 'iworksie-health-check' );
		$data = array(
			'missing_required'    => $missing_req,
			'missing_recommended' => $missing_rec,
		);

		if ( ! empty( $missing_req ) ) {
			return Result::critical(
				$this->label(),
				sprintf(
					/* translators: %s: comma-separated extension list */
					__( 'Required extensions missing: %s.', 'iworksie-health-check' ),
					implode( ', ', $missing_req )
				),
				array(
					'why'  => $why,
					'fix'  => $fix,
					'data' => $data,
				)
			);
		}
		if ( ! empty( $missing_rec ) ) {
			return Result::recommended(
				$this->label(),
				sprintf(
					/* translators: %s: comma-separated extension list */
					__( 'Recommended extensions missing: %s.', 'iworksie-health-check' ),
					implode( ', ', $missing_rec )
				),
				array(
					'why'  => $why,
					'fix'  => $fix,
					'data' => $data,
				)
			);
		}
		return Result::good(
			$this->label(),
			__( 'All required and recommended PHP extensions are loaded.', 'iworksie-health-check' ),
			array(
				'why'  => $why,
				'fix'  => __( 'No action needed.', 'iworksie-health-check' ),
				'data' => $data,
			)
		);
	}
}
