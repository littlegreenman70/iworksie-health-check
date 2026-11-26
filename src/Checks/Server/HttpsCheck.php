<?php
/**
 * HTTPS / SSL check.
 *
 * @package IWorksIE\HealthCheck
 */

namespace IWorksIE\HealthCheck\Checks\Server;

use IWorksIE\HealthCheck\Vendor\IWorksIE\Core\Checks\Check;
use IWorksIE\HealthCheck\Vendor\IWorksIE\Core\Checks\Result;

/**
 * Admin SSL and site URL scheme.
 */
final class HttpsCheck implements Check {

	/** @return string */
	public function id() {
		return 'https';
	}

	/** @return string */
	public function label() {
		return __( 'HTTPS', 'iworksie-health-check' );
	}

	/** @return string */
	public function group() {
		return 'server';
	}

	/** @return Result */
	public function run() {
		$admin_ssl  = is_ssl();
		$home       = home_url( '/' );
		$site       = site_url( '/' );
		$home_https = 0 === strpos( $home, 'https://' );
		$site_https = 0 === strpos( $site, 'https://' );

		$why  = __( 'HTTPS encrypts traffic between visitors and your site. Without it, browsers warn users and some features (payments, logins) are unsafe.', 'iworksie-health-check' );
		$fix  = __( 'Install an SSL certificate with your host, then set WordPress Address and Site Address to https:// in Settings → General. Force HTTPS in your host panel if available.', 'iworksie-health-check' );
		$data = array(
			'admin_ssl'  => $admin_ssl,
			'home_https' => $home_https,
			'site_https' => $site_https,
		);

		if ( ! $home_https || ! $site_https ) {
			return Result::critical(
				$this->label(),
				__( 'The site or home URL is not using https://.', 'iworksie-health-check' ),
				array(
					'why'  => $why,
					'fix'  => $fix,
					'data' => $data,
				)
			);
		}
		if ( ! $admin_ssl ) {
			return Result::recommended(
				$this->label(),
				__( 'Site URLs use HTTPS, but this admin request is not over SSL (common on some local setups).', 'iworksie-health-check' ),
				array(
					'why'  => $why,
					'fix'  => $fix,
					'data' => $data,
				)
			);
		}
		return Result::good(
			$this->label(),
			__( 'Admin and site URLs are using HTTPS.', 'iworksie-health-check' ),
			array(
				'why'  => $why,
				'fix'  => __( 'No action needed.', 'iworksie-health-check' ),
				'data' => $data,
			)
		);
	}
}
