<?php
/**
 * PHPStan bootstrap stubs.
 *
 * @package IWorksIE\HealthCheck
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}
if ( ! defined( 'IWIEHC_VERSION' ) ) {
	define( 'IWIEHC_VERSION', '1.0.1' );
}
if ( ! defined( 'IWIEHC_FILE' ) ) {
	define( 'IWIEHC_FILE', '/tmp/iworksie-health-check/iworksie-health-check.php' );
}
if ( ! defined( 'IWIEHC_PATH' ) ) {
	define( 'IWIEHC_PATH', '/tmp/iworksie-health-check/' );
}
if ( ! defined( 'IWIEHC_URL' ) ) {
	define( 'IWIEHC_URL', 'https://example.com/wp-content/plugins/iworksie-health-check/' );
}
if ( ! defined( 'IWIEHC_MIN_PHP' ) ) {
	define( 'IWIEHC_MIN_PHP', '7.4' );
}
if ( ! defined( 'IWIEHC_MIN_WP' ) ) {
	define( 'IWIEHC_MIN_WP', '6.5' );
}

if ( ! class_exists( '\IWorksIE\HealthCheck\Vendor\IWorksIE\Core\Core' ) && is_readable( dirname( __DIR__ ) . '/vendor-prefixed/autoload.php' ) ) {
	require_once dirname( __DIR__ ) . '/vendor-prefixed/autoload.php';
}
