<?php
/**
 * Summarise SizeScanner finish payload for UI and report.
 *
 * @package IWorksIE\HealthCheck
 */

namespace IWorksIE\HealthCheck\Support;

/**
 * Disk scan post-processing. Never deletes.
 *
 * Compatible with tools-core 1.0.1+ results (tree, top_files, dead_weight)
 * without relying on per-file nodes.
 */
final class DiskSummary {

	/**
	 * Build a summary from a finished job result (core 1.0.1 shape).
	 *
	 * Prefer passing `$state['result']` from the job option. A bare finish
	 * array is also accepted for tests and CLI.
	 *
	 * @param array<string,mixed> $payload Job finish array or state with result.
	 * @return array<string,mixed>
	 */
	public static function from_job( array $payload ) {
		$result = isset( $payload['result'] ) && is_array( $payload['result'] ) ? $payload['result'] : $payload;

		$total_bytes = isset( $result['total_bytes'] ) ? (int) $result['total_bytes'] : 0;
		$tree        = isset( $result['tree'] ) && is_array( $result['tree'] ) ? $result['tree'] : array();
		$top_files   = isset( $result['top_files'] ) && is_array( $result['top_files'] ) ? $result['top_files'] : array();
		$dead        = isset( $result['dead_weight'] ) && is_array( $result['dead_weight'] ) ? $result['dead_weight'] : array();
		if ( empty( $dead ) && isset( $result['dead_files'] ) && is_array( $result['dead_files'] ) ) {
			$dead = $result['dead_files'];
		}

		$overflow       = isset( $result['dead_files_overflow'] ) ? (int) $result['dead_files_overflow'] : 0;
		$overflow_bytes = isset( $result['dead_files_overflow_bytes'] ) ? (int) $result['dead_files_overflow_bytes'] : 0;

		$breakdown            = self::wp_content_breakdown_from_tree( $tree );
		$breakdown['outside'] = max( 0, $total_bytes - ( isset( $breakdown['content'] ) ? (int) $breakdown['content'] : 0 ) );
		$top                  = self::top_folders_from_tree( $tree, 20 );
		$risks                = self::web_root_risks_from_lists( $dead, $top_files );

		$dead_bytes = 0;
		$dead_items = array();
		foreach ( $dead as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$bytes       = isset( $item['bytes'] ) ? (int) $item['bytes'] : 0;
			$dead_bytes += $bytes;
			$reason      = '';
			if ( isset( $item['dead'] ) && is_array( $item['dead'] ) && isset( $item['dead']['reason'] ) ) {
				$reason = (string) $item['dead']['reason'];
			} elseif ( isset( $item['reason'] ) ) {
				$reason = (string) $item['reason'];
			}
			$dead_items[] = array(
				'path'   => isset( $item['path'] ) ? (string) $item['path'] : '',
				'bytes'  => $bytes,
				'reason' => $reason,
				'id'     => ( isset( $item['dead']['id'] ) ? (string) $item['dead']['id'] : ( isset( $item['id'] ) ? (string) $item['id'] : '' ) ),
			);
		}
		$dead_bytes += $overflow_bytes;

		return array(
			'time'                => time(),
			'total_bytes'         => $total_bytes,
			'total_files'         => isset( $result['total_files'] ) ? (int) $result['total_files'] : 0,
			'breakdown'           => $breakdown,
			'top_folders'         => $top,
			'top_files'           => $top_files,
			'dead_items'          => $dead_items,
			'dead_bytes'          => $dead_bytes,
			'dead_overflow'       => $overflow,
			'dead_overflow_bytes' => $overflow_bytes,
			'web_root_risks'      => $risks,
			'unreadable'          => isset( $result['unreadable'] ) ? $result['unreadable'] : array(),
			'unreadable_count'    => isset( $result['unreadable_count'] ) ? (int) $result['unreadable_count'] : 0,
		);
	}

	/**
	 * Path relative to the site root (ABSPATH), for display.
	 *
	 * @param string $path Absolute or relative path.
	 * @return string
	 */
	public static function relative_path( $path ) {
		$abs  = defined( 'ABSPATH' ) ? rtrim( wp_normalize_path( ABSPATH ), '/' ) : '';
		$path = rtrim( wp_normalize_path( (string) $path ), '/' );
		if ( '' === $abs ) {
			return $path;
		}
		if ( $path === $abs ) {
			return '.';
		}
		$prefix = $abs . '/';
		if ( 0 === strpos( $path, $prefix ) ) {
			return substr( $path, strlen( $prefix ) );
		}
		return $path;
	}

	/**
	 * Plain-English "you could free about X" line, including overflow.
	 *
	 * @param array<string,mixed> $summary Summary from from_job().
	 * @return string
	 */
	public static function freeable_message( array $summary ) {
		$dead = isset( $summary['dead_bytes'] ) ? (int) $summary['dead_bytes'] : 0;
		if ( $dead <= 0 ) {
			return '';
		}
		$message = sprintf(
			/* translators: %s: size */
			__( 'You could free about %s by removing these (after a backup and with your developer). This plugin never deletes files.', 'iworksie-health-check' ),
			Bytes::format( $dead )
		);
		$overflow = isset( $summary['dead_overflow'] ) ? (int) $summary['dead_overflow'] : 0;
		$obytes   = isset( $summary['dead_overflow_bytes'] ) ? (int) $summary['dead_overflow_bytes'] : 0;
		if ( $overflow > 0 ) {
			$message .= ' ' . sprintf(
				/* translators: 1: file count, 2: size */
				__( 'and %1$s more files, %2$s', 'iworksie-health-check' ),
				number_format_i18n( $overflow ),
				Bytes::format( $obytes )
			);
		}
		return $message;
	}

	/**
	 * @param array<string,mixed> $tree Tree.
	 * @return array<string,int>
	 */
	private static function wp_content_breakdown_from_tree( array $tree ) {
		$abs     = defined( 'ABSPATH' ) ? wp_normalize_path( ABSPATH ) : '';
		$content = defined( 'WP_CONTENT_DIR' ) ? wp_normalize_path( WP_CONTENT_DIR ) : ( $abs . 'wp-content' );
		$keys    = array(
			'uploads' => wp_normalize_path( $content . '/uploads' ),
			'plugins' => wp_normalize_path( $content . '/plugins' ),
			'themes'  => wp_normalize_path( $content . '/themes' ),
			'other'   => wp_normalize_path( $content ),
		);
		$out     = array(
			'uploads' => 0,
			'plugins' => 0,
			'themes'  => 0,
			'other'   => 0,
			'content' => 0,
			'outside' => 0,
		);
		foreach ( $keys as $key => $path ) {
			$node = self::find_tree_node( $tree, $path );
			if ( null !== $node ) {
				$out[ $key ] = isset( $node['bytes'] ) ? (int) $node['bytes'] : 0;
			}
		}
		$out['content'] = $out['other'];
		$out['other']   = max( 0, $out['content'] - $out['uploads'] - $out['plugins'] - $out['themes'] );
		return $out;
	}

	/**
	 * @param array<string,mixed> $tree  Tree.
	 * @param string              $path  Absolute path.
	 * @return array<string,mixed>|null
	 */
	private static function find_tree_node( array $tree, $path ) {
		$path      = wp_normalize_path( $path );
		$node_path = isset( $tree['path'] ) ? wp_normalize_path( (string) $tree['path'] ) : '';
		if ( $node_path === $path ) {
			return $tree;
		}
		if ( empty( $tree['children'] ) || ! is_array( $tree['children'] ) ) {
			return null;
		}
		foreach ( $tree['children'] as $child ) {
			if ( ! is_array( $child ) ) {
				continue;
			}
			$found = self::find_tree_node( $child, $path );
			if ( null !== $found ) {
				return $found;
			}
		}
		return null;
	}

	/**
	 * @param array<string,mixed> $tree  Tree.
	 * @param int                 $limit Limit.
	 * @return array<int,array<string,mixed>>
	 */
	private static function top_folders_from_tree( array $tree, $limit ) {
		$dirs = array();
		self::collect_dirs( $tree, $dirs );
		usort(
			$dirs,
			static function ( $a, $b ) {
				return $b['bytes'] <=> $a['bytes'];
			}
		);
		return array_slice( $dirs, 0, (int) $limit );
	}

	/**
	 * @param array<string,mixed>            $node Node.
	 * @param array<int,array<string,mixed>> $dirs Accumulator.
	 * @return void
	 */
	private static function collect_dirs( array $node, array &$dirs ) {
		if ( ! empty( $node['is_dir'] ) || ( isset( $node['children'] ) && is_array( $node['children'] ) ) ) {
			$dirs[] = array(
				'path'  => isset( $node['path'] ) ? (string) $node['path'] : '',
				'bytes' => isset( $node['bytes'] ) ? (int) $node['bytes'] : 0,
			);
		}
		if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
			foreach ( $node['children'] as $child ) {
				if ( is_array( $child ) ) {
					self::collect_dirs( $child, $dirs );
				}
			}
		}
	}

	/**
	 * @param array<int,array<string,mixed>> $dead Dead weight.
	 * @param array<int,array<string,mixed>> $top  Top files.
	 * @return array<int,array{path:string,bytes:int,is_dir:bool}>
	 */
	private static function web_root_risks_from_lists( array $dead, array $top ) {
		$abs  = defined( 'ABSPATH' ) ? wp_normalize_path( ABSPATH ) : '';
		$out  = array();
		$seen = array();
		$exts = array( 'sql', 'zip', 'tar', 'gz', 'tgz', 'rar', '7z' );
		$pool = array_merge( $dead, $top );
		foreach ( $pool as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$path = isset( $item['path'] ) ? wp_normalize_path( (string) $item['path'] ) : '';
			if ( '' === $path || '' === $abs || 0 !== strpos( $path, $abs ) ) {
				continue;
			}
			$base    = basename( $path );
			$ext     = strtolower( pathinfo( $base, PATHINFO_EXTENSION ) );
			$is_dir  = ! empty( $item['is_dir'] );
			$dead_id = '';
			if ( isset( $item['dead']['id'] ) ) {
				$dead_id = (string) $item['dead']['id'];
			} elseif ( isset( $item['id'] ) ) {
				$dead_id = (string) $item['id'];
			}
			$backup_dead = ( 0 === strpos( $dead_id, 'backup' ) );
			$risky       = $backup_dead
				|| false !== stripos( $base, 'backup' )
				|| ( ! $is_dir && ( in_array( $ext, $exts, true ) || in_array( $dead_id, array( 'archive', 'sql_large' ), true ) ) );
			if ( ! $risky || isset( $seen[ $path ] ) ) {
				continue;
			}
			$seen[ $path ] = true;
			$out[]         = array(
				'path'   => $path,
				'bytes'  => isset( $item['bytes'] ) ? (int) $item['bytes'] : 0,
				'is_dir' => $is_dir,
			);
		}
		return $out;
	}
}
