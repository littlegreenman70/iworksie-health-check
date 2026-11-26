<?php
/**
 * Web server and database version info.
 *
 * @package IWorksIE\HealthCheck
 */

namespace IWorksIE\HealthCheck\Checks\Server;

use IWorksIE\HealthCheck\Support\Thresholds;
use IWorksIE\HealthCheck\Vendor\IWorksIE\Core\Checks\Check;
use IWorksIE\HealthCheck\Vendor\IWorksIE\Core\Checks\Result;

/**
 * Server software and DB version.
 */
final class ServerSoftwareCheck implements Check {

	/** @return string */
	public function id() {
		return 'server_software';
	}

	/** @return string */
	public function label() {
		return __( 'Server software', 'iworksie-health-check' );
	}

	/** @return string */
	public function group() {
		return 'server';
	}

	/** @return Result */
	public function run() {
		global $wpdb;
		$server   = isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['SERVER_SOFTWARE'] ) ) : __( 'unknown', 'iworksie-health-check' );
		$db_ver   = '';
		$db_label = 'MySQL';
		if ( isset( $wpdb ) && is_object( $wpdb ) && method_exists( $wpdb, 'db_server_info' ) ) {
			$db_ver = (string) $wpdb->db_server_info();
		} elseif ( isset( $wpdb ) && is_object( $wpdb ) && method_exists( $wpdb, 'get_var' ) ) {
			$db_ver = (string) $wpdb->get_var( 'SELECT VERSION()' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		$is_maria = false !== stripos( $db_ver, 'mariadb' );
		$db_label = $is_maria ? 'MariaDB' : 'MySQL';
		$numeric  = preg_replace( '/[^0-9.].*/', '', $db_ver );
		$min      = $is_maria
			? (string) Thresholds::get( 'mariadb_min_version', '10.6' )
			: (string) Thresholds::get( 'mysql_min_version', '8.0' );

		$desc = sprintf(
			/* translators: 1: web server software, 2: database label, 3: database version */
			__( 'Web server: %1$s. Database: %2$s %3$s.', 'iworksie-health-check' ),
			$server,
			$db_label,
			$db_ver ? $db_ver : __( 'unknown', 'iworksie-health-check' )
		);
		$why = __( 'Older database servers miss performance and security fixes WordPress relies on.', 'iworksie-health-check' );
		$fix = sprintf(
			/* translators: 1: database label, 2: minimum version */
			__( 'Ask your host to upgrade %1$s to %2$s or newer.', 'iworksie-health-check' ),
			$db_label,
			$min
		);
		$data = array(
			'server'   => $server,
			'db'       => $db_ver,
			'db_label' => $db_label,
		);

		if ( $numeric && version_compare( $numeric, $min, '<' ) ) {
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
				'fix'  => __( 'No action needed unless your host offers a newer supported version.', 'iworksie-health-check' ),
				'data' => $data,
			)
		);
	}
}
