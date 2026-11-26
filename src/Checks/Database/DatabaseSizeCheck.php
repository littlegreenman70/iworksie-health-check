<?php
/**
 * Database size and top tables.
 *
 * @package IWorksIE\HealthCheck
 */

namespace IWorksIE\HealthCheck\Checks\Database;

use IWorksIE\HealthCheck\Support\Bytes;
use IWorksIE\HealthCheck\Vendor\IWorksIE\Core\Checks\Check;
use IWorksIE\HealthCheck\Vendor\IWorksIE\Core\Checks\Result;

/**
 * Total DB size from information_schema.
 */
final class DatabaseSizeCheck implements Check {

	/** @return string */
	public function id() {
		return 'database_size';
	}

	/** @return string */
	public function label() {
		return __( 'Database size', 'iworksie-health-check' );
	}

	/** @return string */
	public function group() {
		return 'database';
	}

	/** @return Result */
	public function run() {
		global $wpdb;
		$db_name = defined( 'DB_NAME' ) ? DB_NAME : '';
		$why     = __( 'A large database slows backups and clones. Knowing the heaviest tables helps you decide what to clean (revisions, logs, old data).', 'iworksie-health-check' );
		$fix     = __( 'Review the largest tables with your developer. Safe clean-ups often include old post revisions and expired transients. Always take a backup first. This plugin never deletes data.', 'iworksie-health-check' );

		if ( '' === $db_name ) {
			return Result::info(
				$this->label(),
				__( 'Could not determine the database name.', 'iworksie-health-check' ),
				array(
					'why' => $why,
					'fix' => $fix,
				)
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT table_name AS name, (data_length + index_length) AS bytes
				FROM information_schema.TABLES
				WHERE table_schema = %s
				ORDER BY bytes DESC
				LIMIT 10',
				$db_name
			),
			ARRAY_A
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT SUM(data_length + index_length)
				FROM information_schema.TABLES
				WHERE table_schema = %s',
				$db_name
			)
		);

		$top = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$top[] = array(
					'name'  => isset( $row['name'] ) ? (string) $row['name'] : '',
					'bytes' => isset( $row['bytes'] ) ? (int) $row['bytes'] : 0,
				);
			}
		}

		$desc = sprintf(
			/* translators: %s: formatted size */
			__( 'Total database size is about %s. Top tables are listed in the report data.', 'iworksie-health-check' ),
			Bytes::format( $total )
		);

		return Result::info(
			$this->label(),
			$desc,
			array(
				'why'  => $why,
				'fix'  => $fix,
				'data' => array(
					'total_bytes' => $total,
					'top_tables'  => $top,
				),
			)
		);
	}
}
