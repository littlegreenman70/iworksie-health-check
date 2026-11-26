<?php
/**
 * Plugin Name:       iWorks.ie Health Check
 * Plugin URI:        https://www.iworks.ie
 * Description:       Read-only site health checks with plain-English fixes, disk and dead-weight finder, and a shareable report. Never changes your site.
 * Version:           1.1.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            iWorks.ie
 * Author URI:        https://www.iworks.ie
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       iworksie-health-check
 * Domain Path:       /languages
 *
 * @package IWorksIE\HealthCheck
 */

defined( 'ABSPATH' ) || exit;

define( 'IWIEHC_VERSION', '1.1.0' );
define( 'IWIEHC_FILE', __FILE__ );
define( 'IWIEHC_PATH', plugin_dir_path( __FILE__ ) );
define( 'IWIEHC_URL', plugin_dir_url( __FILE__ ) );
define( 'IWIEHC_MIN_PHP', '7.4' );
define( 'IWIEHC_MIN_WP', '6.5' );

/**
 * Admin notice when requirements are not met.
 *
 * @param string $message Notice text.
 * @return void
 */
function iwiehc_requirements_notice( $message ) {
	add_action(
		'admin_notices',
		static function () use ( $message ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
		}
	);
}

if ( version_compare( PHP_VERSION, IWIEHC_MIN_PHP, '<' ) ) {
	iwiehc_requirements_notice(
		sprintf(
			/* translators: 1: required PHP version, 2: current PHP version */
			__( 'iWorks.ie Health Check requires PHP %1$s or higher. This site is running PHP %2$s.', 'iworksie-health-check' ),
			IWIEHC_MIN_PHP,
			PHP_VERSION
		)
	);
	return;
}

global $wp_version;
if ( isset( $wp_version ) && version_compare( $wp_version, IWIEHC_MIN_WP, '<' ) ) {
	iwiehc_requirements_notice(
		sprintf(
			/* translators: 1: required WordPress version, 2: current WordPress version */
			__( 'iWorks.ie Health Check requires WordPress %1$s or higher. This site is running WordPress %2$s.', 'iworksie-health-check' ),
			IWIEHC_MIN_WP,
			$wp_version
		)
	);
	return;
}

$iwiehc_prefixed = IWIEHC_PATH . 'vendor-prefixed/autoload.php';
if ( ! is_readable( $iwiehc_prefixed ) ) {
	iwiehc_requirements_notice(
		__( 'iWorks.ie Health Check is missing its vendor-prefixed library. Run composer install, or install the release zip from wordpress.org.', 'iworksie-health-check' )
	);
	return;
}
require_once $iwiehc_prefixed;

$iwiehc_autoload = IWIEHC_PATH . 'vendor/autoload.php';
if ( is_readable( $iwiehc_autoload ) ) {
	require_once $iwiehc_autoload;
} else {
	spl_autoload_register(
		static function ( $class_name ) {
			$prefix = 'IWorksIE\\HealthCheck\\';
			if ( 0 !== strpos( $class_name, $prefix ) ) {
				return;
			}
			$relative = substr( $class_name, strlen( $prefix ) );
			$file     = IWIEHC_PATH . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
			if ( is_readable( $file ) ) {
				require_once $file;
			}
		}
	);
}

add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( '\IWorksIE\HealthCheck\Vendor\IWorksIE\Core\Core' ) ) {
			iwiehc_requirements_notice(
				__( 'iWorks.ie Health Check could not load its shared tools library.', 'iworksie-health-check' )
			);
			return;
		}
		// Boot after init so __() / translate() are not called too early (WP 6.7+).
		add_action(
			'init',
			static function () {
				\IWorksIE\HealthCheck\Plugin::instance()->boot();
			},
			5
		);
	},
	5
);
