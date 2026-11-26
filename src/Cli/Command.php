<?php
/**
 * WP-CLI command: wp iworksie-health run
 *
 * @package IWorksIE\HealthCheck
 */

namespace IWorksIE\HealthCheck\Cli;

use IWorksIE\HealthCheck\Plugin;
use IWorksIE\HealthCheck\Support\ResultsCache;

/**
 * CLI runner.
 */
final class Command {

	/**
	 * Register the command when WP-CLI is present.
	 *
	 * @return void
	 */
	public static function register() {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}
		\WP_CLI::add_command( 'iworksie-health', self::class );
	}

	/**
	 * Run all health checks.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table or json
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 * ---
	 *
	 * @param array<int,string>    $args Positional.
	 * @param array<string,string> $assoc Assoc.
	 * @return void
	 */
	public function run( $args, $assoc ) {
		$plugin = Plugin::instance();
		$core   = $plugin->core();
		if ( null === $core ) {
			\WP_CLI::error( 'Plugin core is not booted.' );
		}
		$results = $core->checks_runner()->run_all();
		$stored  = array();
		$rows    = array();
		foreach ( $results as $id => $result ) {
			$stored[ $id ] = $result->to_array();
			$rows[]        = array(
				'id'     => $id,
				'status' => $result->status(),
				'label'  => $result->label(),
				'detail' => $result->description(),
			);
		}
		ResultsCache::save( $stored );

		$format = isset( $assoc['format'] ) ? $assoc['format'] : 'table';
		if ( 'json' === $format ) {
			\WP_CLI::print_value( $rows, array( 'format' => 'json' ) );
			return;
		}
		\WP_CLI\Utils\format_items( 'table', $rows, array( 'id', 'status', 'label', 'detail' ) );
	}
}
