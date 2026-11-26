<?php
/**
 * PHPUnit bootstrap.
 *
 * @package IWorksIE\HealthCheck
 */

$iwiehc_root = dirname( __DIR__ );

require_once $iwiehc_root . '/vendor/autoload.php';

$iwiehc_prefixed = $iwiehc_root . '/vendor-prefixed/autoload.php';
if ( is_readable( $iwiehc_prefixed ) ) {
	require_once $iwiehc_prefixed;
}

$iwiehc_suite = getenv( 'IWIEHC_TESTSUITE' );
if ( ! $iwiehc_suite ) {
	$iwiehc_suite = 'unit';
}

if ( 'integration' === $iwiehc_suite ) {
	$iwiehc_wp_tests = getenv( 'WP_TESTS_DIR' );
	if ( ! $iwiehc_wp_tests ) {
		$iwiehc_wp_tests = $iwiehc_root . '/tests/wordpress/wordpress-tests-lib';
	}
	if ( ! is_readable( $iwiehc_wp_tests . '/includes/functions.php' ) ) {
		fwrite( STDERR, "WP test suite not found at {$iwiehc_wp_tests}. Run bin/install-wp-tests.sh first.\n" );
		exit( 1 );
	}
	require_once $iwiehc_wp_tests . '/includes/functions.php';

	tests_add_filter(
		'muplugins_loaded',
		static function () use ( $iwiehc_root ) {
			$plugin = $iwiehc_root . '/iworksie-health-check.php';
			if ( is_readable( $plugin ) ) {
				require $plugin;
			}
			$ex1 = dirname( $iwiehc_root ) . '/iworks-tools-core/examples/iworksie-example-one/iworksie-example-one.php';
			$ex2 = dirname( $iwiehc_root ) . '/iworks-tools-core/examples/iworksie-example-two/iworksie-example-two.php';
			if ( is_readable( $ex1 ) ) {
				require $ex1;
			}
			if ( is_readable( $ex2 ) ) {
				require $ex2;
			}
		}
	);

	require_once $iwiehc_wp_tests . '/includes/bootstrap.php';
}
