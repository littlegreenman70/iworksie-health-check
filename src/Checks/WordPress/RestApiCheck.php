<?php
/**
 * REST API reachability via rest_do_request (no HTTP).
 *
 * @package IWorksIE\HealthCheck
 */

namespace IWorksIE\HealthCheck\Checks\WordPress;

use IWorksIE\HealthCheck\Vendor\IWorksIE\Core\Checks\Check;
use IWorksIE\HealthCheck\Vendor\IWorksIE\Core\Checks\Result;

/**
 * Internal REST probe.
 */
final class RestApiCheck implements Check {

	/** @return string */
	public function id() {
		return 'rest_api';
	}

	/** @return string */
	public function label() {
		return __( 'REST API', 'iworksie-health-check' );
	}

	/** @return string */
	public function group() {
		return 'WordPress';
	}

	/** @return Result */
	public function run() {
		$why = __( 'The REST API powers the block editor, many plugins, and mobile apps. If it fails, editing and integrations break.', 'iworksie-health-check' );
		$fix = __( 'Disable security plugins briefly to test, check that pretty permalinks work, and look for PHP fatals in the debug log. Ask your host if a firewall is blocking /wp-json/.', 'iworksie-health-check' );

		$request  = new \WP_REST_Request( 'GET', '/wp/v2/types' );
		$response = rest_do_request( $request );
		// rest_do_request() is typed as WP_REST_Response; still guard for WP_Error at runtime.
		if ( ! ( $response instanceof \WP_REST_Response ) ) {
			$msg = ( is_object( $response ) && method_exists( $response, 'get_error_message' ) )
				? (string) $response->get_error_message()
				: __( 'REST request failed.', 'iworksie-health-check' );
			return Result::critical(
				$this->label(),
				$msg,
				array(
					'why'  => $why,
					'fix'  => $fix,
					'data' => array(),
				)
			);
		}
		$status = (int) $response->get_status();
		if ( $status >= 400 ) {
			$data = $response->get_data();
			$msg  = is_array( $data ) && isset( $data['message'] ) ? (string) $data['message'] : (string) $status;
			return Result::critical(
				$this->label(),
				sprintf(
					/* translators: 1: HTTP status, 2: message */
					__( 'REST request failed with status %1$s: %2$s', 'iworksie-health-check' ),
					(string) $status,
					$msg
				),
				array(
					'why'  => $why,
					'fix'  => $fix,
					'data' => array( 'status' => $status ),
				)
			);
		}
		return Result::good(
			$this->label(),
			__( 'REST API responded successfully to an internal /wp/v2/types request.', 'iworksie-health-check' ),
			array(
				'why'  => $why,
				'fix'  => __( 'No action needed.', 'iworksie-health-check' ),
				'data' => array( 'status' => $status ),
			)
		);
	}
}
