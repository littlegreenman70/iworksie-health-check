<?php
/**
 * Backup / SQL files sitting in the web root (security).
 *
 * Uses stored disk scan results when available; otherwise a shallow scan of ABSPATH.
 *
 * @package IWorksIE\HealthCheck
 */

namespace IWorksIE\HealthCheck\Checks\Disk;

use IWorksIE\HealthCheck\Support\ResultsCache;
use IWorksIE\HealthCheck\Vendor\IWorksIE\Core\Checks\Check;
use IWorksIE\HealthCheck\Vendor\IWorksIE\Core\Checks\Result;

/**
 * Dangerous archives in the public tree.
 */
final class DeadWeightWebRootCheck implements Check {

	/** @return string */
	public function id() {
		return 'dead_weight_web_root';
	}

	/** @return string */
	public function label() {
		return __( 'Backup files in the web root', 'iworksie-health-check' );
	}

	/** @return string */
	public function group() {
		return 'disk';
	}

	/** @return Result */
	public function run() {
		$why = __( 'Anyone who guesses the file name can download a database dump or full site archive if it sits inside the public web root.', 'iworksie-health-check' );
		$fix = __( 'Move backups outside the public folder (or off the server), then delete the public copy after you confirm the safe copy works. Take care: this plugin never deletes files.', 'iworksie-health-check' );

		$found = array();
		$disk  = ResultsCache::load_disk();
		if ( is_array( $disk ) && ! empty( $disk['web_root_risks'] ) && is_array( $disk['web_root_risks'] ) ) {
			$found = $disk['web_root_risks'];
		} else {
			$found = self::shallow_find( defined( 'ABSPATH' ) ? ABSPATH : '' );
		}

		if ( ! empty( $found ) ) {
			return Result::critical(
				$this->label(),
				sprintf(
					/* translators: %d: file count */
					_n( '%d backup or SQL file was found under the web root.', '%d backup or SQL files were found under the web root.', count( $found ), 'iworksie-health-check' ),
					count( $found )
				),
				array(
					'why'  => $why,
					'fix'  => $fix,
					'data' => array( 'files' => $found ),
				)
			);
		}
		return Result::good(
			$this->label(),
			__( 'No obvious backup or SQL dumps were found in a shallow scan of the web root. Run a full Disk scan for a deeper look.', 'iworksie-health-check' ),
			array(
				'why'  => $why,
				'fix'  => __( 'No action needed unless a full disk scan finds more.', 'iworksie-health-check' ),
				'data' => array( 'files' => array() ),
			)
		);
	}

	/**
	 * Shallow scan: ABSPATH and wp-content top level only.
	 *
	 * @param string $root Root path.
	 * @return array<int,string>
	 */
	private static function shallow_find( $root ) {
		$root = rtrim( (string) $root, '/\\' );
		if ( '' === $root || ! is_dir( $root ) ) {
			return array();
		}
		$dirs  = array( $root, $root . '/wp-content' );
		$found = array();
		$exts  = array( 'sql', 'zip', 'tar', 'gz', 'tgz', 'rar', '7z' );
		foreach ( $dirs as $dir ) {
			if ( ! is_readable( $dir ) ) {
				continue;
			}
			$entries = @scandir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( ! is_array( $entries ) ) {
				continue;
			}
			foreach ( $entries as $entry ) {
				if ( '.' === $entry || '..' === $entry ) {
					continue;
				}
				$path = $dir . '/' . $entry;
				if ( ! is_file( $path ) || is_link( $path ) ) {
					continue;
				}
				$ext = strtolower( pathinfo( $entry, PATHINFO_EXTENSION ) );
				if ( in_array( $ext, $exts, true ) || false !== stripos( $entry, 'backup' ) ) {
					$found[] = $path;
				}
			}
		}
		return $found;
	}
}
