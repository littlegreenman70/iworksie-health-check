<?php
/**
 * Summarise SizeScanner finish payload for UI and report.
 *
 * @package IWorksIE\HealthCheck
 */

namespace IWorksIE\HealthCheck\Support;

/**
 * Disk scan post-processing. Never deletes.
 */
final class DiskSummary {

	/**
	 * Build a summary from job result / state.
	 *
	 * @param array<string,mixed> $payload Job finish or state with nodes/result.
	 * @return array<string,mixed>
	 */
	public static function from_job( array $payload ) {
		$result = isset( $payload['result'] ) && is_array( $payload['result'] ) ? $payload['result'] : $payload;
		$nodes  = array();
		if ( isset( $payload['nodes'] ) && is_array( $payload['nodes'] ) ) {
			$nodes = $payload['nodes'];
		}

		$total_bytes = isset( $result['total_bytes'] ) ? (int) $result['total_bytes'] : ( isset( $payload['total_bytes'] ) ? (int) $payload['total_bytes'] : 0 );
		$tree        = isset( $result['tree'] ) && is_array( $result['tree'] ) ? $result['tree'] : ( isset( $payload['tree'] ) && is_array( $payload['tree'] ) ? $payload['tree'] : array() );

		$breakdown = self::wp_content_breakdown( $nodes, $tree );
		$top       = self::top_folders( $nodes, 20 );
		$dead      = self::dead_weight_list( $nodes );
		$risks     = self::web_root_risks( $nodes );

		$dead_bytes = 0;
		foreach ( $dead as $item ) {
			$dead_bytes += isset( $item['bytes'] ) ? (int) $item['bytes'] : 0;
		}

		return array(
			'time'           => time(),
			'total_bytes'    => $total_bytes,
			'total_files'    => isset( $result['total_files'] ) ? (int) $result['total_files'] : 0,
			'breakdown'      => $breakdown,
			'top_folders'    => $top,
			'dead_items'     => $dead,
			'dead_bytes'     => $dead_bytes,
			'web_root_risks' => $risks,
			'unreadable'     => isset( $result['unreadable'] ) ? $result['unreadable'] : array(),
		);
	}

	/**
	 * @param array<string,array<string,mixed>> $nodes Nodes.
	 * @param array<string,mixed>               $tree  Tree.
	 * @return array<string,int>
	 */
	private static function wp_content_breakdown( array $nodes, array $tree ) {
		$abs     = defined( 'ABSPATH' ) ? wp_normalize_path( ABSPATH ) : '';
		$content = defined( 'WP_CONTENT_DIR' ) ? wp_normalize_path( WP_CONTENT_DIR ) : ( $abs . 'wp-content' );
		$keys    = array(
			'uploads' => $content . '/uploads',
			'plugins' => $content . '/plugins',
			'themes'  => $content . '/themes',
			'other'   => $content,
		);
		$out     = array(
			'uploads' => 0,
			'plugins' => 0,
			'themes'  => 0,
			'other'   => 0,
			'content' => 0,
		);
		foreach ( $keys as $key => $path ) {
			$path = wp_normalize_path( $path );
			if ( isset( $nodes[ $path ] ) ) {
				$out[ $key ] = self::node_total_bytes( $path, $nodes );
			}
		}
		$out['content'] = $out['other'];
		$out['other']   = max( 0, $out['content'] - $out['uploads'] - $out['plugins'] - $out['themes'] );
		unset( $tree );
		return $out;
	}

	/**
	 * @param string                            $path  Path.
	 * @param array<string,array<string,mixed>> $nodes Nodes.
	 * @return int
	 */
	private static function node_total_bytes( $path, array $nodes ) {
		if ( ! isset( $nodes[ $path ] ) ) {
			return 0;
		}
		$node  = $nodes[ $path ];
		$total = isset( $node['bytes'] ) ? (int) $node['bytes'] : 0;
		if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
			foreach ( $node['children'] as $child ) {
				$total += self::node_total_bytes( (string) $child, $nodes );
			}
		}
		return $total;
	}

	/**
	 * @param array<string,array<string,mixed>> $nodes Nodes.
	 * @param int                               $limit Limit.
	 * @return array<int,array<string,mixed>>
	 */
	private static function top_folders( array $nodes, $limit ) {
		$dirs = array();
		foreach ( $nodes as $path => $node ) {
			if ( empty( $node['is_dir'] ) ) {
				continue;
			}
			$dirs[] = array(
				'path'  => (string) $path,
				'bytes' => self::node_total_bytes( (string) $path, $nodes ),
			);
		}
		usort(
			$dirs,
			static function ( $a, $b ) {
				return $b['bytes'] <=> $a['bytes'];
			}
		);
		return array_slice( $dirs, 0, (int) $limit );
	}

	/**
	 * @param array<string,array<string,mixed>> $nodes Nodes.
	 * @return array<int,array<string,mixed>>
	 */
	private static function dead_weight_list( array $nodes ) {
		$items = array();
		foreach ( $nodes as $path => $node ) {
			if ( empty( $node['dead'] ) || ! is_array( $node['dead'] ) ) {
				continue;
			}
			$bytes   = ! empty( $node['is_dir'] ) ? self::node_total_bytes( (string) $path, $nodes ) : ( isset( $node['bytes'] ) ? (int) $node['bytes'] : 0 );
			$items[] = array(
				'path'   => (string) $path,
				'bytes'  => $bytes,
				'reason' => isset( $node['dead']['reason'] ) ? (string) $node['dead']['reason'] : '',
				'id'     => isset( $node['dead']['id'] ) ? (string) $node['dead']['id'] : '',
			);
		}
		usort(
			$items,
			static function ( $a, $b ) {
				return $b['bytes'] <=> $a['bytes'];
			}
		);
		return $items;
	}

	/**
	 * Backup/SQL archives under ABSPATH.
	 *
	 * @param array<string,array<string,mixed>> $nodes Nodes.
	 * @return array<int,string>
	 */
	private static function web_root_risks( array $nodes ) {
		$abs  = defined( 'ABSPATH' ) ? wp_normalize_path( ABSPATH ) : '';
		$out  = array();
		$exts = array( 'sql', 'zip', 'tar', 'gz', 'tgz', 'rar', '7z' );
		foreach ( $nodes as $path => $node ) {
			if ( ! empty( $node['is_dir'] ) ) {
				continue;
			}
			$path_n = wp_normalize_path( (string) $path );
			if ( '' === $abs || 0 !== strpos( $path_n, $abs ) ) {
				continue;
			}
			$base    = basename( $path_n );
			$ext     = strtolower( pathinfo( $base, PATHINFO_EXTENSION ) );
			$dead_id = isset( $node['dead']['id'] ) ? (string) $node['dead']['id'] : '';
			$risky   = in_array( $ext, $exts, true ) || false !== stripos( $base, 'backup' ) || in_array( $dead_id, array( 'archive', 'sql_large' ), true );
			if ( $risky ) {
				$out[] = $path_n;
			}
		}
		return array_values( array_unique( $out ) );
	}
}
