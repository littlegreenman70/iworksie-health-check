<?php
/**
 * Post revisions and expired transients counts.
 *
 * @package IWorksIE\HealthCheck
 */

namespace IWorksIE\HealthCheck\Checks\Database;

use IWorksIE\HealthCheck\Vendor\IWorksIE\Core\Checks\Check;
use IWorksIE\HealthCheck\Vendor\IWorksIE\Core\Checks\Result;

/**
 * Revisions / transients.
 */
final class RevisionsTransientsCheck implements Check {

	/** @return string */
	public function id() {
		return 'revisions_transients';
	}

	/** @return string */
	public function label() {
		return __( 'Revisions and expired transients', 'iworksie-health-check' );
	}

	/** @return string */
	public function group() {
		return 'database';
	}

	/** @return Result */
	public function run() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$revisions = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", 'revision' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$expired = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d",
				$wpdb->esc_like( '_transient_timeout_' ) . '%',
				time()
			)
		);

		$why  = __( 'Revisions and expired transients are normal, but large piles waste database space and slow backups.', 'iworksie-health-check' );
		$fix  = __( 'Use a trusted cleanup plugin or WP-CLI (wp transient delete --expired) after a backup. Limit revisions with WP_POST_REVISIONS in wp-config.php. This plugin never deletes data.', 'iworksie-health-check' );
		$desc = sprintf(
			/* translators: 1: revision count, 2: expired transient count */
			__( '%1$d post revisions and %2$d expired transients found.', 'iworksie-health-check' ),
			$revisions,
			$expired
		);
		$data = array(
			'revisions' => $revisions,
			'expired'   => $expired,
		);

		if ( $revisions > 500 || $expired > 200 ) {
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
		return Result::info(
			$this->label(),
			$desc,
			array(
				'why'  => $why,
				'fix'  => $fix,
				'data' => $data,
			)
		);
	}
}
