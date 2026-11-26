<?php
/**
 * Core / plugin / theme updates from existing transients only.
 *
 * @package IWorksIE\HealthCheck
 */

namespace IWorksIE\HealthCheck\Checks\WordPress;

use IWorksIE\HealthCheck\Vendor\IWorksIE\Core\Checks\Check;
use IWorksIE\HealthCheck\Vendor\IWorksIE\Core\Checks\Result;

/**
 * Pending updates (no remote HTTP).
 */
final class UpdatesCheck implements Check {

	/** @return string */
	public function id() {
		return 'updates';
	}

	/** @return string */
	public function label() {
		return __( 'Updates pending', 'iworksie-health-check' );
	}

	/** @return string */
	public function group() {
		return 'WordPress';
	}

	/** @return Result */
	public function run() {
		$core    = get_site_transient( 'update_core' );
		$plugins = get_site_transient( 'update_plugins' );
		$themes  = get_site_transient( 'update_themes' );

		$core_updates   = array();
		$plugin_updates = array();
		$theme_updates  = array();
		$major_behind   = false;

		if ( is_object( $core ) && ! empty( $core->updates ) && is_array( $core->updates ) ) {
			foreach ( $core->updates as $update ) {
				if ( ! is_object( $update ) || empty( $update->response ) || 'latest' === $update->response ) {
					continue;
				}
				$core_updates[] = isset( $update->version ) ? (string) $update->version : '';
				if ( 'upgrade' === $update->response && ! empty( $update->current ) && ! empty( $update->version ) ) {
					$cur_major = (int) explode( '.', (string) $update->current )[0];
					$new_major = (int) explode( '.', (string) $update->version )[0];
					if ( $new_major > $cur_major ) {
						$major_behind = true;
					}
				}
			}
		}
		if ( is_object( $plugins ) && ! empty( $plugins->response ) && is_array( $plugins->response ) ) {
			$plugin_updates = array_keys( $plugins->response );
		}
		if ( is_object( $themes ) && ! empty( $themes->response ) && is_array( $themes->response ) ) {
			$theme_updates = array_keys( $themes->response );
		}

		$count = count( $core_updates ) + count( $plugin_updates ) + count( $theme_updates );
		$why   = __( 'Updates often include security fixes. Delaying them leaves known holes open.', 'iworksie-health-check' );
		$fix   = __( 'Go to Dashboard → Updates, take a backup, then apply updates one group at a time and spot-check the site.', 'iworksie-health-check' );
		$data  = array(
			'core'    => $core_updates,
			'plugins' => $plugin_updates,
			'themes'  => $theme_updates,
		);

		if ( $major_behind ) {
			return Result::critical(
				$this->label(),
				__( 'WordPress core is a major version behind. Update as soon as you can.', 'iworksie-health-check' ),
				array(
					'why'  => $why,
					'fix'  => $fix,
					'data' => $data,
				)
			);
		}
		if ( $count > 0 ) {
			return Result::recommended(
				$this->label(),
				sprintf(
					/* translators: %d: number of pending updates */
					_n( '%d update is pending.', '%d updates are pending.', $count, 'iworksie-health-check' ),
					$count
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
			__( 'No pending updates in the cached WordPress update data.', 'iworksie-health-check' ),
			array(
				'why'  => $why,
				'fix'  => __( 'Visit Dashboard → Updates occasionally so WordPress can refresh its update list.', 'iworksie-health-check' ),
				'data' => $data,
			)
		);
	}
}
