<?php
/**
 * Other PHP ini limits that affect imports and uploads.
 *
 * @package IWorksIE\HealthCheck
 */

namespace IWorksIE\HealthCheck\Checks\Server;

use IWorksIE\HealthCheck\Support\Bytes;
use IWorksIE\HealthCheck\Vendor\IWorksIE\Core\Checks\Check;
use IWorksIE\HealthCheck\Vendor\IWorksIE\Core\Checks\Result;

/**
 * max_execution_time, upload_max_filesize, post_max_size.
 */
final class PhpLimitsCheck implements Check {

	/** @return string */
	public function id() {
		return 'php_limits';
	}

	/** @return string */
	public function label() {
		return __( 'PHP execution and upload limits', 'iworksie-health-check' );
	}

	/** @return string */
	public function group() {
		return 'server';
	}

	/** @return Result */
	public function run() {
		$max_exec = (int) ini_get( 'max_execution_time' );
		$upload   = (string) ini_get( 'upload_max_filesize' );
		$post     = (string) ini_get( 'post_max_size' );
		$upload_b = Bytes::parse( $upload );
		$post_b   = Bytes::parse( $post );

		$desc = sprintf(
			/* translators: 1: max_execution_time, 2: upload_max_filesize, 3: post_max_size */
			__( 'max_execution_time is %1$s seconds. upload_max_filesize is %2$s. post_max_size is %3$s.', 'iworksie-health-check' ),
			(string) $max_exec,
			$upload,
			$post
		);
		$why = __( 'These limits control how long a request can run and how large a file you can upload. Migrations, backups, and media imports often need more headroom than the defaults.', 'iworksie-health-check' );
		$fix = __( 'Ask your host to raise max_execution_time (for example 120), upload_max_filesize, and post_max_size (post_max_size should be at least as large as upload_max_filesize).', 'iworksie-health-check' );

		$issues = array();
		if ( $max_exec > 0 && $max_exec < 60 ) {
			$issues[] = 'time';
		}
		if ( $upload_b >= 0 && $upload_b < 32 * 1024 * 1024 ) {
			$issues[] = 'upload';
		}
		if ( $post_b >= 0 && $upload_b >= 0 && $post_b < $upload_b ) {
			$issues[] = 'post';
		}

		$data = array(
			'max_execution_time'  => $max_exec,
			'upload_max_filesize' => $upload,
			'post_max_size'       => $post,
		);

		if ( ! empty( $issues ) ) {
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
				'fix'  => __( 'No action needed for typical site work.', 'iworksie-health-check' ),
				'data' => $data,
			)
		);
	}
}
