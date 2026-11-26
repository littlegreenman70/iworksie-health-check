<?php
/**
 * Inactive plugins and themes.
 *
 * @package IWorksIE\HealthCheck
 */

namespace IWorksIE\HealthCheck\Checks\WordPress;

use IWorksIE\HealthCheck\Vendor\IWorksIE\Core\Checks\Check;
use IWorksIE\HealthCheck\Vendor\IWorksIE\Core\Checks\Result;

/**
 * Lists inactive plugins/themes.
 */
final class InactivePluginsThemesCheck implements Check {

	/** @return string */
	public function id() {
		return 'inactive_plugins_themes';
	}

	/** @return string */
	public function label() {
		return __( 'Inactive plugins and themes', 'iworksie-health-check' );
	}

	/** @return string */
	public function group() {
		return 'WordPress';
	}

	/** @return Result */
	public function run() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$all_plugins    = get_plugins();
		$active_plugins = (array) get_option( 'active_plugins', array() );
		$inactive       = array();
		foreach ( array_keys( $all_plugins ) as $file ) {
			if ( ! in_array( $file, $active_plugins, true ) ) {
				$inactive[] = $file;
			}
		}

		$themes          = wp_get_themes();
		$current         = get_stylesheet();
		$parent          = get_template();
		$inactive_themes = array();
		foreach ( $themes as $slug => $theme ) {
			if ( $slug === $current || $slug === $parent ) {
				continue;
			}
			$inactive_themes[] = $slug;
		}

		$why   = __( 'Inactive plugins and themes still sit on disk. Attackers can target known bugs in unused code, and they clutter updates.', 'iworksie-health-check' );
		$fix   = __( 'Delete plugins and themes you do not need (after a backup). Keep one default theme as a fallback.', 'iworksie-health-check' );
		$data  = array(
			'inactive_plugins' => $inactive,
			'inactive_themes'  => $inactive_themes,
		);
		$count = count( $inactive ) + count( $inactive_themes );

		if ( $count > 0 ) {
			return Result::recommended(
				$this->label(),
				sprintf(
					/* translators: 1: inactive plugin count, 2: inactive theme count */
					__( '%1$d inactive plugin(s) and %2$d inactive theme(s) found.', 'iworksie-health-check' ),
					count( $inactive ),
					count( $inactive_themes )
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
			__( 'No inactive plugins or themes found.', 'iworksie-health-check' ),
			array(
				'why'  => $why,
				'fix'  => __( 'No action needed.', 'iworksie-health-check' ),
				'data' => $data,
			)
		);
	}
}
