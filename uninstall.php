<?php
/**
 * Uninstall cleanup for iWorks.ie Health Check.
 *
 * @package IWorksIE\HealthCheck
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$iwiehc_prefix = 'iwiehc_';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$iwiehc_options = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( $iwiehc_prefix ) . '%'
	)
);

if ( is_array( $iwiehc_options ) ) {
	foreach ( $iwiehc_options as $iwiehc_option ) {
		delete_option( $iwiehc_option );
	}
}

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$iwiehc_transients = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_' . $iwiehc_prefix ) . '%',
		$wpdb->esc_like( '_transient_timeout_' . $iwiehc_prefix ) . '%'
	)
);

if ( is_array( $iwiehc_transients ) ) {
	foreach ( $iwiehc_transients as $iwiehc_transient_option ) {
		if ( 0 === strpos( $iwiehc_transient_option, '_transient_timeout_' ) ) {
			$iwiehc_key = substr( $iwiehc_transient_option, strlen( '_transient_timeout_' ) );
			delete_transient( $iwiehc_key );
			continue;
		}
		if ( 0 === strpos( $iwiehc_transient_option, '_transient_' ) ) {
			$iwiehc_key = substr( $iwiehc_transient_option, strlen( '_transient_' ) );
			delete_transient( $iwiehc_key );
		}
	}
}

if ( is_multisite() ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	$iwiehc_site_options = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT meta_key FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s",
			$wpdb->esc_like( $iwiehc_prefix ) . '%'
		)
	);
	if ( is_array( $iwiehc_site_options ) ) {
		foreach ( $iwiehc_site_options as $iwiehc_site_option ) {
			delete_site_option( $iwiehc_site_option );
		}
	}
}
