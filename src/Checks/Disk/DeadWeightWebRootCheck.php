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
use IWorksIE\HealthCheck\Vendor\IWorksIE\Core\Filesystem\DeadWeight;

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

	/**
	 * Extensions that mean a real dump or archive (Critical).
	 *
	 * @var array<int,string>
	 */
	const CRITICAL_EXTENSIONS = array( 'sql', 'zip', 'tar', 'gz', 'tgz', 'rar', '7z', 'wpress', 'bak' );

	/**
	 * Compound suffixes checked before the single extension.
	 *
	 * @var array<int,string>
	 */
	const CRITICAL_SUFFIXES = array( '.sql.gz', '.tar.gz' );

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

		$raw        = array();
		$scan_label = '';
		$disk       = ResultsCache::load_disk();
		$disk_time  = ( is_array( $disk ) && isset( $disk['time'] ) ) ? (int) $disk['time'] : 0;
		$disk_fresh = $disk_time > 0 && ( time() - $disk_time ) <= self::SCAN_MAX_AGE;

		if ( $disk_fresh && is_array( $disk ) && ! empty( $disk['web_root_risks'] ) && is_array( $disk['web_root_risks'] ) ) {
			$raw        = $disk['web_root_risks'];
			$scan_label = sprintf(
				/* translators: %s: human time / date */
				__( 'From the disk scan on %s', 'iworksie-health-check' ),
				self::format_scan_date( $disk_time )
			);
		} else {
			$raw        = self::shallow_find( defined( 'ABSPATH' ) ? ABSPATH : '' );
			$scan_label = __( 'From a shallow scan of the web root', 'iworksie-health-check' );
		}

		$classified = self::classify_matches( $raw );
		$found      = self::normalize_matches( $classified['items'] );

		if ( empty( $found ) ) {
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

		$count = count( $found );
		$data  = array(
			'why'  => $why,
			'fix'  => $fix,
			'data' => array(
				'matches'    => $found,
				'files'      => $found,
				'scan_label' => $scan_label,
			),
		);

		if ( $classified['has_critical'] ) {
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
				$data
			);
		}

		return Result::recommended(
			__( 'Possible backup files in the web root', 'iworksie-health-check' ),
			sprintf(
				/* translators: %d: file/folder count */
				_n(
					'%d possible backup file or folder was found under the web root.',
					'%d possible backup files or folders were found under the web root.',
					$count,
					'iworksie-health-check'
				),
				$count
			),
			$data
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
	 * Whether a path is under an ignored WordPress core / filtered location.
	 *
	 * @param string $path Absolute or relative path.
	 * @return bool
	 */
	public static function is_ignored_path( $path ) {
		return DeadWeight::is_ignored_backup_path( $path );
	}

	/**
	 * Whether a file name is a real dump or archive (Critical).
	 *
	 * @param string $name File basename.
	 * @return bool
	 */
	public static function is_critical_filename( $name ) {
		$lower = strtolower( (string) $name );
		foreach ( self::CRITICAL_SUFFIXES as $suffix ) {
			$len = strlen( $suffix );
			if ( $len > 0 && substr( $lower, -$len ) === $suffix ) {
				return true;
			}
		}
		$ext = strtolower( pathinfo( $lower, PATHINFO_EXTENSION ) );
		return in_array( $ext, self::CRITICAL_EXTENSIONS, true );
	}

	/**
	 * Whether a directory contains any Critical dump/archive files.
	 *
	 * @param string $dir Directory path.
	 * @return bool
	 */
	public static function directory_contains_critical( $dir ) {
		$dir = (string) $dir;
		if ( '' === $dir || ! is_dir( $dir ) || ! is_readable( $dir ) ) {
			return false;
		}
		try {
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS )
			);
			foreach ( $iterator as $file ) {
				if ( $file->isFile() && self::is_critical_filename( $file->getFilename() ) ) {
					return true;
				}
			}
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Unreadable trees are treated as non-critical.
		}
		return false;
	}

	/**
	 * Whether a directory has any files inside (empty folders never count).
	 *
	 * @param string $dir Directory path.
	 * @return bool
	 */
	public static function directory_has_files( $dir ) {
		$dir = (string) $dir;
		if ( '' === $dir || ! is_dir( $dir ) || ! is_readable( $dir ) ) {
			return false;
		}
		try {
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS )
			);
			foreach ( $iterator as $file ) {
				if ( $file->isFile() ) {
					return true;
				}
			}
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			return false;
		}
		return false;
	}

	/**
	 * Classify raw matches into kept items plus whether any are Critical.
	 *
	 * @param array<int,mixed> $raw Raw matches (paths or {path,bytes,is_dir}).
	 * @return array{items:array<int,array{path:string,bytes:int,is_dir:bool,severity:string}>,has_critical:bool}
	 */
	public static function classify_matches( array $raw ) {
		$items        = array();
		$has_critical = false;
		$seen         = array();

		foreach ( $raw as $item ) {
			if ( is_string( $item ) ) {
				$path   = $item;
				$is_dir = is_dir( $path );
				$bytes  = ( ! $is_dir && is_file( $path ) ) ? (int) @filesize( $path ) : 0; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			} elseif ( is_array( $item ) ) {
				$path   = isset( $item['path'] ) ? (string) $item['path'] : '';
				$bytes  = isset( $item['bytes'] ) ? (int) $item['bytes'] : 0;
				$is_dir = ! empty( $item['is_dir'] );
				if ( ! $is_dir && 0 === $bytes && is_file( $path ) ) {
					$bytes = (int) @filesize( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				}
				if ( $is_dir && ! isset( $item['is_dir'] ) ) {
					$is_dir = is_dir( $path );
				}
			} else {
				continue;
			}

			if ( '' === $path || isset( $seen[ $path ] ) ) {
				continue;
			}
			if ( self::is_ignored_path( $path ) ) {
				continue;
			}

			$base = basename( $path );
			if ( $is_dir ) {
				if ( $bytes <= 0 && ! self::directory_has_files( $path ) ) {
					continue;
				}
				// Backup-named folders only (extension-only dirs are not a thing here).
				if ( false === stripos( $base, 'backup' ) ) {
					continue;
				}
				$severity = self::directory_contains_critical( $path ) ? 'critical' : 'recommended';
			} else {
				$backup_name = false !== stripos( $base, 'backup' );
				$critical    = self::is_critical_filename( $base );
				if ( ! $backup_name && ! $critical ) {
					continue;
				}
				$severity = $critical ? 'critical' : 'recommended';
			}

			$seen[ $path ] = true;
			if ( 'critical' === $severity ) {
				$has_critical = true;
			}
			$items[] = array(
				'path'     => $path,
				'bytes'    => $bytes,
				'is_dir'   => $is_dir,
				'severity' => $severity,
			);
		}

		return array(
			'items'        => $items,
			'has_critical' => $has_critical,
		);
	}

	/**
	 * Normalize classified matches into UI rows.
	 *
	 * @param array<int,array{path:string,bytes:int,is_dir:bool,severity?:string}> $raw Matches.
	 * @return array<int,array{path:string,relative:string,bytes:int,size:string,kind:string,severity:string}>
	 */
	private static function normalize_matches( array $raw ) {
		$out = array();
		foreach ( $raw as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$path     = isset( $item['path'] ) ? (string) $item['path'] : '';
			$bytes    = isset( $item['bytes'] ) ? (int) $item['bytes'] : 0;
			$is_dir   = ! empty( $item['is_dir'] );
			$severity = isset( $item['severity'] ) ? (string) $item['severity'] : 'recommended';
			if ( '' === $path ) {
				continue;
			}
			$out[] = array(
				'path'     => $path,
				'relative' => DiskSummary::relative_path( $path ),
				'bytes'    => $bytes,
				'size'     => Bytes::format( $bytes ),
				'kind'     => $is_dir ? __( 'folder', 'iworksie-health-check' ) : __( 'file', 'iworksie-health-check' ),
				'severity' => $severity,
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
	public static function shallow_find( $root ) {
		$root = rtrim( (string) $root, '/\\' );
		if ( '' === $root || ! is_dir( $root ) ) {
			return array();
		}
		$dirs  = array( $root, $root . '/wp-content' );
		$found = array();
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
				if ( self::is_ignored_path( $path ) ) {
					continue;
				}
				$backup_name = false !== stripos( $entry, 'backup' );
				$critical    = $is_file && self::is_critical_filename( $entry );
				if ( ! $backup_name && ! $critical ) {
					continue;
				}
				if ( $is_dir && ! self::directory_has_files( $path ) ) {
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
