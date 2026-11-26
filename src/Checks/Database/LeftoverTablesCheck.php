<?php
/**
 * Heuristic leftover database tables.
 *
 * @package IWorksIE\HealthCheck
 */

namespace IWorksIE\HealthCheck\Checks\Database;

use IWorksIE\HealthCheck\Vendor\IWorksIE\Core\Checks\Check;
use IWorksIE\HealthCheck\Vendor\IWorksIE\Core\Checks\Result;

/**
 * Tables that do not match core or installed plugin prefixes.
 */
final class LeftoverTablesCheck implements Check {

	/** @return string */
	public function id() {
		return 'leftover_tables';
	}

	/** @return string */
	public function label() {
		return __( 'Possible leftover tables', 'iworksie-health-check' );
	}

	/** @return string */
	public function group() {
		return 'database';
	}

	/** @return Result */
	public function run() {
		global $wpdb;
		$db_name = defined( 'DB_NAME' ) ? DB_NAME : '';
		$why     = __( 'Uninstalled plugins sometimes leave tables behind. They waste space and confuse audits. This check is a guess only.', 'iworksie-health-check' );
		$fix     = __( 'Compare the list with plugins you used to run. Only drop tables after a backup and with developer help. This plugin never deletes tables.', 'iworksie-health-check' );

		if ( '' === $db_name ) {
			return Result::info(
				$this->label(),
				__( 'Could not scan tables.', 'iworksie-health-check' ),
				array(
					'why' => $why,
					'fix' => $fix,
				)
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$tables = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT table_name FROM information_schema.TABLES WHERE table_schema = %s',
				$db_name
			)
		);
		if ( ! is_array( $tables ) ) {
			$tables = array();
		}

		$known = self::known_prefixes();
		$left  = array();
		foreach ( $tables as $table ) {
			$table = (string) $table;
			$ok    = false;
			foreach ( $known as $prefix ) {
				if ( 0 === strpos( $table, $prefix ) ) {
					$ok = true;
					break;
				}
			}
			if ( ! $ok ) {
				$left[] = $table;
			}
		}

		$data = array( 'tables' => $left );
		if ( empty( $left ) ) {
			return Result::info(
				$this->label(),
				__( 'No obvious leftover tables found by prefix heuristics.', 'iworksie-health-check' ),
				array(
					'why'  => $why,
					'fix'  => __( 'No action needed.', 'iworksie-health-check' ),
					'data' => $data,
				)
			);
		}
		return Result::info(
			$this->label(),
			sprintf(
				/* translators: %d: table count */
				_n( '%d table may not belong to core or an installed plugin.', '%d tables may not belong to core or an installed plugin.', count( $left ), 'iworksie-health-check' ),
				count( $left )
			),
			array(
				'why'  => $why,
				'fix'  => $fix,
				'data' => $data,
			)
		);
	}

	/**
	 * @return array<int,string>
	 */
	private static function known_prefixes() {
		global $wpdb;
		$prefixes = array( $wpdb->prefix );
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		foreach ( array_keys( get_plugins() ) as $file ) {
			$slug = dirname( $file );
			if ( '.' === $slug ) {
				$slug = basename( $file, '.php' );
			}
			$slug = preg_replace( '/[^a-z0-9_]+/i', '_', (string) $slug );
			if ( '' !== $slug ) {
				$prefixes[] = $wpdb->prefix . $slug;
				$prefixes[] = $slug . '_';
			}
		}
		// Common shared tables.
		$prefixes[] = $wpdb->prefix;
		return array_values( array_unique( $prefixes ) );
	}
}
