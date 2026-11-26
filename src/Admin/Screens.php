<?php
/**
 * Admin screens for Health Check tabs.
 *
 * @package IWorksIE\HealthCheck
 */

namespace IWorksIE\HealthCheck\Admin;

use IWorksIE\HealthCheck\Support\Bytes;
use IWorksIE\HealthCheck\Support\DiskSummary;
use IWorksIE\HealthCheck\Support\ReportBuilder;
use IWorksIE\HealthCheck\Support\ResultsCache;
use IWorksIE\HealthCheck\Vendor\IWorksIE\Core\Checks\Result;
use IWorksIE\HealthCheck\Vendor\IWorksIE\Core\Core;
use IWorksIE\HealthCheck\Vendor\IWorksIE\Core\Strings;
use IWorksIE\HealthCheck\Vendor\IWorksIE\Core\UI;

/**
 * Renders Overview, Disk, Report, Tools tabs.
 */
final class Screens {

	/**
	 * @var Core
	 */
	private $core;

	/**
	 * @param Core $core Core.
	 */
	public function __construct( Core $core ) {
		$this->core = $core;
	}

	/**
	 * Menu callback.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( (string) $this->core->config( 'capability' ) ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'iworksie-health-check' ) );
		}

		$tabs = array(
			'overview' => __( 'Overview', 'iworksie-health-check' ),
			'disk'     => __( 'Disk & dead weight', 'iworksie-health-check' ),
			'report'   => __( 'Report', 'iworksie-health-check' ),
			'tools'    => __( 'Tools', 'iworksie-health-check' ),
		);

		$this->core->page()->render(
			array(
				'title'   => (string) $this->core->config( 'name' ),
				'version' => (string) $this->core->config( 'version' ),
				'tagline' => __( 'Read-only health checks with plain-English fixes. This plugin never changes your site.', 'iworksie-health-check' ),
				'tabs'    => $tabs,
			),
			function ( $active ) {
				switch ( $active ) {
					case 'disk':
						$this->render_disk();
						break;
					case 'report':
						$this->render_report();
						break;
					case 'tools':
						$this->render_tools();
						break;
					default:
						$this->render_overview();
						break;
				}
			}
		);
	}

	/**
	 * @return void
	 */
	private function render_overview() {
		$cache   = ResultsCache::load();
		$good    = 0;
		$rec     = 0;
		$crit    = 0;
		$grouped = array(
			'critical'    => array(),
			'recommended' => array(),
			'good'        => array(),
			'info'        => array(),
		);
		if ( null !== $cache ) {
			foreach ( $cache['results'] as $id => $row ) {
				$result = Result::from_array( (array) $row );
				$status = $result->status();
				if ( ! isset( $grouped[ $status ] ) ) {
					$grouped[ $status ] = array();
				}
				$grouped[ $status ][ $id ] = $result;
				if ( 'good' === $status ) {
					++$good;
				} elseif ( 'recommended' === $status ) {
					++$rec;
				} elseif ( 'critical' === $status ) {
					++$crit;
				}
			}
		}

		echo '<p class="iwiehc-last-checked">' . esc_html( ResultsCache::last_checked_label() ) . '</p>';
		echo '<div class="iwt-toolbar">';
		echo '<button type="button" class="button button-primary" id="iwiehc-run-checks" data-iwiehc-run-checks="1">' . esc_html( Strings::get( 'run_checks' ) ) . '</button>';
		echo '</div>';
		echo '<div class="iwt-summary" data-iwt-summary role="status">' . UI::summary_bar( $good, $rec, $crit ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div data-iwt-results aria-live="polite" aria-label="' . esc_attr__( 'Health check results', 'iworksie-health-check' ) . '">';
		foreach ( array( 'critical', 'recommended', 'info', 'good' ) as $status ) {
			if ( empty( $grouped[ $status ] ) ) {
				continue;
			}
			$open = ( 'good' !== $status );
			echo '<section class="iwiehc-group iwiehc-group--' . esc_attr( $status ) . '">';
			echo '<h2>' . esc_html( ucfirst( $status ) ) . '</h2>';
			foreach ( $grouped[ $status ] as $result ) {
				$html = UI::result_card( $result );
				if ( ! $open ) {
					$html = str_replace( '<details', '<details', $html );
					echo '<details class="iwiehc-collapsed-good"><summary>' . esc_html( $result->label() ) . ' — ' . esc_html__( 'Good', 'iworksie-health-check' ) . '</summary>';
					echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo '</details>';
				} else {
					echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
			}
			echo '</section>';
		}
		echo '</div>';
	}

	/**
	 * @return void
	 */
	private function render_disk() {
		$disk = ResultsCache::load_disk();
		echo '<p>' . esc_html__( 'Scan disk usage and look for dead weight such as old backups, caches, and development folders. This plugin never deletes anything.', 'iworksie-health-check' ) . '</p>';
		echo '<div class="iwt-toolbar">';
		echo '<button type="button" class="button button-primary" data-iwt-job-start data-job="size_scanner" data-progress="iwiehc-disk" data-iwiehc-disk-finish="1">' . esc_html__( 'Scan disk', 'iworksie-health-check' ) . '</button>';
		echo '<button type="button" class="button" data-iwt-job-resume data-job="size_scanner" data-progress="iwiehc-disk" data-iwiehc-disk-finish="1">' . esc_html( Strings::get( 'resume' ) ) . '</button>';
		echo '<button type="button" class="button" data-iwt-job-cancel>' . esc_html( Strings::get( 'cancel' ) ) . '</button>';
		echo '</div>';
		echo UI::progress( 'iwiehc-disk' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div id="iwiehc-disk-results" class="iwiehc-disk-results" aria-live="polite">';
		if ( is_array( $disk ) ) {
			$this->echo_disk_results( $disk );
		}
		echo '</div>';
	}

	/**
	 * @param array<string,mixed> $disk Disk summary.
	 * @return void
	 */
	private function echo_disk_results( array $disk ) {
		$total = isset( $disk['total_bytes'] ) ? (int) $disk['total_bytes'] : 0;
		echo '<p><strong>' . esc_html__( 'Total scanned:', 'iworksie-health-check' ) . '</strong> ' . esc_html( Bytes::format( $total ) ) . '</p>';
		if ( ! empty( $disk['breakdown'] ) && is_array( $disk['breakdown'] ) ) {
			$rows = array();
			foreach ( $disk['breakdown'] as $key => $bytes ) {
				$rows[] = array(
					'area' => (string) $key,
					'size' => Bytes::format( (int) $bytes ),
				);
			}
			echo '<h3>' . esc_html__( 'wp-content breakdown', 'iworksie-health-check' ) . '</h3>';
			$breakdown_table = UI::table(
				array(
					'area' => __( 'Area', 'iworksie-health-check' ),
					'size' => __( 'Size', 'iworksie-health-check' ),
				),
				$rows
			);
			echo $breakdown_table; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- UI::table escapes.
		}
		if ( ! empty( $disk['top_folders'] ) && is_array( $disk['top_folders'] ) ) {
			$rows = array();
			foreach ( array_slice( $disk['top_folders'], 0, 20 ) as $folder ) {
				$rows[] = array(
					'path' => isset( $folder['path'] ) ? (string) $folder['path'] : '',
					'size' => Bytes::format( isset( $folder['bytes'] ) ? (int) $folder['bytes'] : 0 ),
				);
			}
			echo '<h3>' . esc_html__( 'Top folders', 'iworksie-health-check' ) . '</h3>';
			$folders_table = UI::table(
				array(
					'path' => __( 'Path', 'iworksie-health-check' ),
					'size' => __( 'Size', 'iworksie-health-check' ),
				),
				$rows
			);
			echo $folders_table; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- UI::table escapes.
		}
		if ( ! empty( $disk['dead_items'] ) && is_array( $disk['dead_items'] ) ) {
			echo '<h3>' . esc_html__( 'Dead weight', 'iworksie-health-check' ) . '</h3>';
			$freeable = DiskSummary::freeable_message( $disk );
			if ( '' !== $freeable ) {
				echo '<p>' . esc_html( $freeable ) . '</p>';
			}
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Warning: take a backup and check with your developer before deleting anything.', 'iworksie-health-check' ) . '</p></div>';
			$rows = array();
			foreach ( $disk['dead_items'] as $item ) {
				$rows[] = array(
					'path'   => isset( $item['path'] ) ? (string) $item['path'] : '',
					'size'   => Bytes::format( isset( $item['bytes'] ) ? (int) $item['bytes'] : 0 ),
					'reason' => isset( $item['reason'] ) ? (string) $item['reason'] : '',
				);
			}
			$dead_table = UI::table(
				array(
					'path'   => __( 'Path', 'iworksie-health-check' ),
					'size'   => __( 'Size', 'iworksie-health-check' ),
					'reason' => __( 'Why', 'iworksie-health-check' ),
				),
				$rows
			);
			echo $dead_table; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- UI::table escapes.
		}
		if ( ! empty( $disk['web_root_risks'] ) && is_array( $disk['web_root_risks'] ) ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html__( 'Critical: backup or SQL files were found under the web root. Anyone who guesses the file name can download them.', 'iworksie-health-check' ) . '</p></div>';
		}
	}

	/**
	 * @return void
	 */
	private function render_report() {
		$cache    = ResultsCache::load();
		$disk     = ResultsCache::load_disk();
		$results  = null !== $cache ? $cache['results'] : array();
		$markdown = ReportBuilder::build( $results, $disk );
		$prefix   = (string) $this->core->config( 'prefix' );
		$url      = $this->core->report()->store_for_download( $markdown );

		echo '<div class="notice notice-info inline"><p>' . esc_html__( 'This report lists plugin names and versions. It does not include passwords, salts, API keys, or full email addresses (only the admin email domain).', 'iworksie-health-check' ) . '</p></div>';
		echo '<div class="iwt-toolbar">';
		echo '<button type="button" class="button button-primary" data-iwt-copy="iwiehc-report-source">' . esc_html( Strings::get( 'copy_report' ) ) . '</button>';
		echo '<a class="button" href="' . esc_url( $url ) . '">' . esc_html( Strings::get( 'download_report' ) ) . '</a>';
		echo '</div>';
		echo '<pre id="iwiehc-report-source" class="iwiehc-report">' . esc_html( $markdown ) . '</pre>';
		unset( $prefix );
	}

	/**
	 * @return void
	 */
	private function render_tools() {
		$nonce = wp_create_nonce( 'iwiehc_test_email' );
		echo '<h2>' . esc_html__( 'Send test email', 'iworksie-health-check' ) . '</h2>';
		echo '<p>' . esc_html__( 'Sends a test message to the site admin email via wp_mail(). “Sent” means WordPress handed the message to the mail system, not that it arrived in an inbox. If mail fails, an SMTP plugin is usually the fix (a future iWorks.ie SMTP plugin is planned).', 'iworksie-health-check' ) . '</p>';
		echo '<p><button type="button" class="button button-primary" id="iwiehc-send-test-email" data-nonce="' . esc_attr( $nonce ) . '">' . esc_html__( 'Send test email', 'iworksie-health-check' ) . '</button></p>';
		echo '<div id="iwiehc-test-email-result" class="iwiehc-test-email-result" aria-live="polite"></div>';
	}
}
