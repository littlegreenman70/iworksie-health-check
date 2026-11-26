<?php
/**
 * Build a redacted Markdown report.
 *
 * @package IWorksIE\HealthCheck
 */

namespace IWorksIE\HealthCheck\Support;

use IWorksIE\HealthCheck\Vendor\IWorksIE\Core\Checks\Result;
use IWorksIE\HealthCheck\Vendor\IWorksIE\Core\Report;

/**
 * Assembles environment + results without secrets.
 */
final class ReportBuilder {

	/**
	 * Patterns / keys that must never appear in a report.
	 *
	 * @var array<int,string>
	 */
	const SECRET_MARKERS = array(
		'DB_PASSWORD',
		'AUTH_KEY',
		'SECURE_AUTH_KEY',
		'LOGGED_IN_KEY',
		'NONCE_KEY',
		'AUTH_SALT',
		'SECURE_AUTH_SALT',
		'LOGGED_IN_SALT',
		'NONCE_SALT',
		'api_key',
		'apikey',
		'secret',
		'password',
		'passwd',
		'private_key',
	);

	/**
	 * Build Markdown from cached results.
	 *
	 * @param array<string,Result|array<string,mixed>> $results Results.
	 * @param array<string,mixed>|null                 $disk    Disk summary.
	 * @return string
	 */
	public static function build( array $results, $disk = null ) {
		$objects = array();
		foreach ( $results as $id => $result ) {
			if ( $result instanceof Result ) {
				$objects[ $id ] = $result;
			} elseif ( is_array( $result ) ) {
				$objects[ $id ] = Result::from_array( $result );
			}
		}

		$meta = array(
			'site' => home_url( '/' ),
			'wp'   => get_bloginfo( 'version' ),
			'php'  => PHP_VERSION,
			'date' => gmdate( 'c' ),
		);

		$markdown = Report::from_results(
			'iWorks.ie Health Check report',
			$objects,
			$meta
		);

		$markdown .= "\n## Environment\n\n";
		$markdown .= '- Theme: ' . self::safe_theme() . "\n";
		$markdown .= '- Active plugins: ' . self::safe_plugins() . "\n";
		$markdown .= '- Admin email domain: ' . self::admin_email_domain() . "\n";

		if ( is_array( $disk ) && ! empty( $disk ) ) {
			$markdown .= "\n## Disk scan summary\n\n";
			if ( isset( $disk['total_bytes'] ) ) {
				$markdown .= '- Total scanned: ' . Bytes::format( (int) $disk['total_bytes'] ) . "\n";
			}
			if ( isset( $disk['dead_bytes'] ) ) {
				$markdown .= '- Dead weight: ' . Bytes::format( (int) $disk['dead_bytes'] ) . "\n";
			}
			if ( ! empty( $disk['dead_overflow'] ) ) {
				$markdown .= '- Dead-weight overflow: ' . (int) $disk['dead_overflow'] . ' more files, ' . Bytes::format( isset( $disk['dead_overflow_bytes'] ) ? (int) $disk['dead_overflow_bytes'] : 0 ) . "\n";
			}
			if ( ! empty( $disk['dead_items'] ) && is_array( $disk['dead_items'] ) ) {
				$markdown .= "- Dead-weight paths:\n";
				foreach ( array_slice( $disk['dead_items'], 0, 20 ) as $item ) {
					$path      = isset( $item['path'] ) ? (string) $item['path'] : '';
					$size      = isset( $item['bytes'] ) ? Bytes::format( (int) $item['bytes'] ) : '';
					$markdown .= '  - ' . DiskSummary::relative_path( $path ) . ' (' . $size . ")\n";
				}
			}
			if ( ! empty( $disk['web_root_risks'] ) && is_array( $disk['web_root_risks'] ) ) {
				$markdown .= "- Backup files or folders under the web root:\n";
				$shown     = 0;
				foreach ( $disk['web_root_risks'] as $risk ) {
					if ( $shown >= 10 ) {
						break;
					}
					if ( is_string( $risk ) ) {
						$path   = $risk;
						$bytes  = 0;
						$is_dir = false;
					} elseif ( is_array( $risk ) ) {
						$path   = isset( $risk['path'] ) ? (string) $risk['path'] : '';
						$bytes  = isset( $risk['bytes'] ) ? (int) $risk['bytes'] : 0;
						$is_dir = ! empty( $risk['is_dir'] );
					} else {
						continue;
					}
					if ( '' === $path ) {
						continue;
					}
					$kind      = $is_dir ? 'folder' : 'file';
					$markdown .= '  - ' . $kind . ': ' . DiskSummary::relative_path( $path ) . ' (' . Bytes::format( $bytes ) . ")\n";
					++$shown;
				}
				$more = count( $disk['web_root_risks'] ) - $shown;
				if ( $more > 0 ) {
					$markdown .= '  - and ' . $more . " more\n";
				}
			}
		}

		$markdown .= "\n---\n";
		$markdown .= "This report lists plugin names and versions. It does not include passwords, salts, API keys, or full email addresses.\n";

		return self::redact( $markdown );
	}

	/**
	 * Strip any accidental secret material.
	 *
	 * @param string $markdown Report body.
	 * @return string
	 */
	public static function redact( $markdown ) {
		$out = (string) $markdown;
		foreach ( self::SECRET_MARKERS as $marker ) {
			$out = preg_replace(
				'/' . preg_quote( $marker, '/' ) . '\s*[:=]\s*\S+/i',
				$marker . '=[REDACTED]',
				$out
			);
		}
		// Never include full admin email.
		$admin = get_option( 'admin_email' );
		if ( is_string( $admin ) && '' !== $admin && false !== strpos( $out, $admin ) ) {
			$out = str_replace( $admin, self::admin_email_domain(), $out );
		}
		if ( defined( 'DB_PASSWORD' ) && is_string( DB_PASSWORD ) && '' !== DB_PASSWORD ) {
			$out = str_replace( DB_PASSWORD, '[REDACTED]', $out );
		}
		return $out;
	}

	/**
	 * @return string
	 */
	private static function safe_theme() {
		$theme = wp_get_theme();
		return $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' );
	}

	/**
	 * @return string
	 */
	private static function safe_plugins() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$active = get_option( 'active_plugins', array() );
		if ( ! is_array( $active ) ) {
			return '';
		}
		$all  = get_plugins();
		$list = array();
		foreach ( $active as $file ) {
			if ( ! isset( $all[ $file ] ) ) {
				continue;
			}
			$name   = isset( $all[ $file ]['Name'] ) ? $all[ $file ]['Name'] : $file;
			$ver    = isset( $all[ $file ]['Version'] ) ? $all[ $file ]['Version'] : '';
			$list[] = $name . ' ' . $ver;
		}
		return implode( ', ', $list );
	}

	/**
	 * @return string
	 */
	public static function admin_email_domain() {
		$email = (string) get_option( 'admin_email' );
		$pos   = strpos( $email, '@' );
		if ( false === $pos ) {
			return '(unknown)';
		}
		return '@' . substr( $email, $pos + 1 );
	}
}
