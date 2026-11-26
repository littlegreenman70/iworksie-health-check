<?php
/**
 * Backup / SQL files sitting in the web root (security).
 *
 * Uses stored disk scan results when available and fresh; otherwise a shallow scan of ABSPATH.
 *
 * @package IWorksIE\HealthCheck
 */

namespace IWorksIE\HealthCheck\Checks\Disk;

use IWorksIE\HealthCheck\Support\Bytes;
use IWorksIE\HealthCheck\Support\DiskSummary;
use IWorksIE\HealthCheck\Support\ResultsCache;
use IWorksIE\HealthCheck\Vendor\IWorksIE\Core\Checks\Check;
use IWorksIE\HealthCheck\Vendor\IWorksIE\Core\Checks\Result;

/**
 * Dangerous archives in the public tree.
 */
final class DeadWeightWebRootCheck implements Check {

	/**
	 * Freshness window for reusing a stored disk scan (7 days).
	 *
	 * @var int
	 */
	const SCAN_MAX_AGE = 604800;

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

		$found      = array();
		$scan_label = '';
		$disk       = ResultsCache::load_disk();
		$disk_time  = ( is_array( $disk ) && isset( $disk['time'] ) ) ? (int) $disk['time'] : 0;
		$disk_fresh = $disk_time > 0 && ( time() - $disk_time ) <= self::SCAN_MAX_AGE;

		if ( $disk_fresh && is_array( $disk ) && ! empty( $disk['web_root_risks'] ) && is_array( $disk['web_root_risks'] ) ) {
			$found      = self::normalize_matches( $disk['web_root_risks'] );
			$scan_label = sprintf(
				/* translators: %s: human time / date */
				__( 'From the disk scan on %s', 'iworksie-health-check' ),
				self::format_scan_date( $disk_time )
			);
		} else {
			$found      = self::normalize_matches( self::shallow_find( defined( 'ABSPATH' ) ? ABSPATH : '' ) );
			$scan_label = __( 'From a shallow scan of the web root', 'iworksie-health-check' );
		}

		if ( ! empty( $found ) ) {
			$count = count( $found );
			return Result::critical(
				$this->label(),
				sprintf(
					/* translators: %d: file/folder count */
					_n(
						'%d backup file or folder was found under the web root.',
						'%d backup files or folders were found under the web root.',
						$count,
						'iworksie-health-check'
					),
					$count
				),
				array(
					'why'  => $why,
					'fix'  => $fix,
					'data' => array(
						'matches'    => $found,
						'files'      => $found,
						'scan_label' => $scan_label,
					),
				)
			);
		}
		return Result::good(
			$this->label(),
			__( 'No obvious backup or SQL dumps were found in a shallow scan of the web root. Run a full Disk scan for a deeper look.', 'iworksie-health-check' ),
			array(
				'why'  => $why,
				'fix'  => __( 'No action needed unless a full disk scan finds more.', 'iworksie-health-check' ),
				'data' => array(
					'matches'    => array(),
					'files'      => array(),
					'scan_label' => $scan_label,
				),
			)
		);
	}

	/**
	 * @param int $timestamp Unix time.
	 * @return string
	 */
	private static function format_scan_date( $timestamp ) {
		$timestamp = (int) $timestamp;
		if ( $timestamp <= 0 ) {
			return '';
		}
		if ( function_exists( 'wp_date' ) ) {
			return (string) wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
		}
		return gmdate( 'Y-m-d H:i', $timestamp ) . ' UTC';
	}

	/**
	 * Normalize stored risks or shallow paths into match rows for the UI.
	 *
	 * @param array<int,mixed> $raw Raw matches.
	 * @return array<int,array{path:string,relative:string,bytes:int,size:string,kind:string}>
	 */
	private static function normalize_matches( array $raw ) {
		$out = array();
		foreach ( $raw as $item ) {
			if ( is_string( $item ) ) {
				$path   = $item;
				$bytes  = is_file( $path ) ? (int) @filesize( $path ) : 0; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				$is_dir = is_dir( $path );
			} elseif ( is_array( $item ) ) {
				$path   = isset( $item['path'] ) ? (string) $item['path'] : '';
				$bytes  = isset( $item['bytes'] ) ? (int) $item['bytes'] : 0;
				$is_dir = ! empty( $item['is_dir'] );
			} else {
				continue;
			}
			if ( '' === $path ) {
				continue;
			}
			$out[] = array(
				'path'     => $path,
				'relative' => DiskSummary::relative_path( $path ),
				'bytes'    => $bytes,
				'size'     => Bytes::format( $bytes ),
				'kind'     => $is_dir ? __( 'folder', 'iworksie-health-check' ) : __( 'file', 'iworksie-health-check' ),
			);
		}
		return $out;
	}

	/**
	 * Shallow scan: ABSPATH and wp-content top level only (files and backup-named folders).
	 *
	 * @param string $root Root path.
	 * @return array<int,array{path:string,bytes:int,is_dir:bool}>
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
				$path    = $dir . '/' . $entry;
				$is_dir  = is_dir( $path ) && ! is_link( $path );
				$is_file = is_file( $path ) && ! is_link( $path );
				if ( ! $is_dir && ! $is_file ) {
					continue;
				}
				$ext   = strtolower( pathinfo( $entry, PATHINFO_EXTENSION ) );
				$risky = false !== stripos( $entry, 'backup' )
					|| ( $is_file && in_array( $ext, $exts, true ) );
				if ( ! $risky ) {
					continue;
				}
				$bytes = 0;
				if ( $is_file ) {
					$bytes = (int) @filesize( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				}
				$found[] = array(
					'path'   => $path,
					'bytes'  => $bytes,
					'is_dir' => $is_dir,
				);
			}
		}
		return $found;
	}
}
