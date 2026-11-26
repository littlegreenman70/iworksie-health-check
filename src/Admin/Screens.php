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
				'tagline' => __( 'Read-only: never changes your site\'s settings, content or files.', 'iworksie-health-check' ),
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
		$cache       = ResultsCache::load();
		$good        = 0;
		$rec         = 0;
		$crit        = 0;
		$info        = 0;
		$grouped     = array(
			'critical'    => array(),
			'recommended' => array(),
			'good'        => array(),
			'info'        => array(),
		);
		$has_results = null !== $cache && ! empty( $cache['results'] );

		if ( $has_results ) {
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
				} elseif ( 'info' === $status ) {
					++$info;
				}
			}
		}

		echo '<p class="iwiehc-last-checked">' . esc_html( ResultsCache::last_checked_label() ) . '</p>';

		if ( ! $has_results ) {
			echo '<div class="iwt-empty" data-iwt-empty>';
			echo '<div class="iwt-empty__icon" aria-hidden="true"><span class="dashicons dashicons-heart"></span></div>';
			echo '<h2 class="iwt-empty__title">' . esc_html( Strings::get( 'empty_title' ) ) . '</h2>';
			echo '<p class="iwt-empty__body">' . esc_html( Strings::get( 'empty_body' ) ) . '</p>';
			echo '<p class="iwt-empty__action"><button type="button" class="button button-primary button-hero" id="iwiehc-run-checks" data-iwiehc-run-checks="1">' . esc_html( Strings::get( 'run_first' ) ) . '</button></p>';
			echo '</div>';
			echo '<div class="iwt-summary-wrap" data-iwt-summary-wrap hidden>';
			echo '<div class="iwt-summary" data-iwt-summary role="status"></div>';
			echo '</div>';
			echo '<div data-iwt-results aria-live="polite" aria-label="' . esc_attr__( 'Health check results', 'iworksie-health-check' ) . '"></div>';
			return;
		}

		echo '<div class="iwt-toolbar">';
		echo '<button type="button" class="button button-primary" id="iwiehc-run-checks" data-iwiehc-run-checks="1">' . esc_html( Strings::get( 'run_checks' ) ) . '</button>';
		echo '</div>';
		echo '<div class="iwt-summary-wrap" data-iwt-summary-wrap>';
		echo UI::summary_bar( $good, $rec, $crit, $info ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';
		echo '<div data-iwt-results aria-live="polite" aria-label="' . esc_attr__( 'Health check results', 'iworksie-health-check' ) . '">';
		foreach ( array( 'critical', 'recommended', 'info', 'good' ) as $status ) {
			if ( empty( $grouped[ $status ] ) ) {
				continue;
			}
			$count = count( $grouped[ $status ] );
			echo '<section class="iwt-group iwt-group--' . esc_attr( $status ) . ( 'good' === $status ? ' is-collapsed' : '' ) . '" data-group="' . esc_attr( $status ) . '">';
			if ( 'good' === $status ) {
				echo '<button type="button" class="iwt-group__toggle" aria-expanded="false" data-iwt-group-toggle>' . esc_html( Strings::get( 'passed_checks', array( 'count' => (string) $count ) ) ) . '</button>';
			} else {
				echo UI::group_heading( $status, $count ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '<div class="iwt-group__results">';
			foreach ( $grouped[ $status ] as $result ) {
				echo UI::result_card( $result, 'critical' === $status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</div></section>';
		}
		echo '</div>';
	}

	/**
	 * @return void
	 */
	private function render_disk() {
		$disk  = ResultsCache::load_disk();
		$state = $this->core->jobs()->load_state( 'size_scanner' );
		$mode  = self::disk_toolbar_mode( $state, is_array( $disk ) );

		echo '<p>' . esc_html__( 'Scan disk usage and look for dead weight such as old backups, caches, and development folders. This plugin never deletes anything.', 'iworksie-health-check' ) . '</p>';
		echo '<div class="iwt-toolbar" data-iwiehc-disk-toolbar data-mode="' . esc_attr( $mode ) . '">';
		$start_label = ( 'finished' === $mode ) ? Strings::get( 'scan_again' ) : __( 'Scan disk', 'iworksie-health-check' );
		echo '<button type="button" class="button button-primary" data-iwt-job-start data-job="size_scanner" data-progress="iwiehc-disk" data-iwiehc-disk-finish="1"' . ( 'running' === $mode ? ' hidden' : '' ) . '>' . esc_html( $start_label ) . '</button>';
		echo '<button type="button" class="button" data-iwt-job-resume data-job="size_scanner" data-progress="iwiehc-disk" data-iwiehc-disk-finish="1"' . ( 'resume' === $mode ? '' : ' hidden' ) . '>' . esc_html( Strings::get( 'resume' ) ) . '</button>';
		echo '<button type="button" class="button" data-iwt-job-cancel' . ( 'running' === $mode ? '' : ' hidden' ) . '>' . esc_html( Strings::get( 'cancel' ) ) . '</button>';
		echo '</div>';

		$progress_html = UI::progress( 'iwiehc-disk' );
		if ( 'finished' === $mode && is_array( $disk ) ) {
			$ago = isset( $disk['time'] ) ? human_time_diff( (int) $disk['time'], time() ) : '';
			$msg = '' !== $ago
				? Strings::get( 'scan_complete_ago', array( 'ago' => $ago ) )
				: Strings::get( 'scan_complete' );
			// Show completed state at 100%, not a misleading 0% bar.
			$progress_html = str_replace(
				array( 'aria-valuenow="0"', 'width:0%', '>0%</span>', 'iwt-progress__message" aria-live="polite"></p>' ),
				array( 'aria-valuenow="100"', 'width:100%', '>100%</span>', 'iwt-progress__message" aria-live="polite">' . esc_html( $msg ) . '</p>' ),
				$progress_html
			);
		} elseif ( 'idle' === $mode || 'resume' === $mode ) {
			$progress_html = str_replace( 'class="iwt-progress"', 'class="iwt-progress is-idle"', $progress_html );
		}
		echo $progress_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- UI::progress escapes; message escaped above.
		echo '<div id="iwiehc-disk-results" class="iwiehc-disk-results" aria-live="polite">';
		if ( is_array( $disk ) ) {
			$this->echo_disk_results( $disk );
		}
		echo '</div>';
	}

	/**
	 * Decide which disk toolbar buttons to show.
	 *
	 * @param array<string,mixed>|null $state Job state.
	 * @param bool                     $has_disk Whether a finished summary exists.
	 * @return string idle|running|resume|finished
	 */
	private static function disk_toolbar_mode( $state, $has_disk ) {
		if ( ! is_array( $state ) ) {
			return $has_disk ? 'finished' : 'idle';
		}
		// Stale unfinished jobs are treated as cancelled/interrupted for Resume.
		$updated = isset( $state['updated_at'] ) ? (int) $state['updated_at'] : 0;
		$stale   = $updated > 0 && ( time() - $updated ) > 3600;
		if ( empty( $state['done'] ) && empty( $state['cancelled'] ) && ! $stale ) {
			return 'running';
		}
		if ( ! empty( $state['cancelled'] ) || ( empty( $state['done'] ) && $stale ) ) {
			return 'resume';
		}
		if ( ! empty( $state['done'] ) && empty( $state['cancelled'] ) ) {
			return 'finished';
		}
		return $has_disk ? 'finished' : 'idle';
	}

	/**
	 * @param array<string,mixed> $disk Disk summary.
	 * @return void
	 */
	private function echo_disk_results( array $disk ) {
		$total      = isset( $disk['total_bytes'] ) ? (int) $disk['total_bytes'] : 0;
		$breakdown  = ( isset( $disk['breakdown'] ) && is_array( $disk['breakdown'] ) ) ? $disk['breakdown'] : array();
		$wp_content = isset( $breakdown['content'] ) ? (int) $breakdown['content'] : 0;
		if ( $wp_content <= 0 ) {
			foreach ( array( 'uploads', 'plugins', 'themes', 'other' ) as $key ) {
				if ( isset( $breakdown[ $key ] ) ) {
					$wp_content += (int) $breakdown[ $key ];
				}
			}
		}
		$could_free = isset( $disk['dead_bytes'] ) ? (int) $disk['dead_bytes'] : 0;

		echo '<div class="iwt-stat-tiles">';
		echo '<div class="iwt-stat-tile"><p class="iwt-stat-tile__label">' . esc_html__( 'Total size', 'iworksie-health-check' ) . '</p>';
		echo '<p class="iwt-stat-tile__value">' . esc_html( Bytes::format( $total ) ) . '</p></div>';
		echo '<div class="iwt-stat-tile"><p class="iwt-stat-tile__label">' . esc_html__( 'wp-content', 'iworksie-health-check' ) . '</p>';
		echo '<p class="iwt-stat-tile__value">' . esc_html( Bytes::format( $wp_content > 0 ? $wp_content : $total ) ) . '</p></div>';
		echo '<div class="iwt-stat-tile"><p class="iwt-stat-tile__label">' . esc_html__( 'Could free', 'iworksie-health-check' ) . '</p>';
		echo '<p class="iwt-stat-tile__value">' . esc_html( Bytes::format( $could_free ) ) . '</p></div>';
		echo '</div>';

		$seg_keys   = array( 'uploads', 'plugins', 'themes', 'other', 'outside' );
		$seg_labels = array(
			'uploads' => __( 'Uploads', 'iworksie-health-check' ),
			'plugins' => __( 'Plugins', 'iworksie-health-check' ),
			'themes'  => __( 'Themes', 'iworksie-health-check' ),
			'other'   => __( 'Other wp-content', 'iworksie-health-check' ),
			'outside' => __( 'Outside wp-content', 'iworksie-health-check' ),
		);
		if ( ! empty( $breakdown ) ) {
			$basis = $total > 0 ? $total : 0;
			if ( $basis <= 0 ) {
				foreach ( $seg_keys as $key ) {
					$basis += isset( $breakdown[ $key ] ) ? (int) $breakdown[ $key ] : 0;
				}
			}
			$basis = max( 1, $basis );
			echo '<div class="iwt-bar-chart" aria-label="' . esc_attr__( 'Site size breakdown', 'iworksie-health-check' ) . '">';
			echo '<div class="iwt-bar-chart__track">';
			foreach ( $seg_keys as $key ) {
				$bytes = isset( $breakdown[ $key ] ) ? (int) $breakdown[ $key ] : 0;
				if ( $bytes <= 0 ) {
					continue;
				}
				$pct   = max( 1, (int) round( ( $bytes / $basis ) * 100 ) );
				$label = isset( $seg_labels[ $key ] ) ? $seg_labels[ $key ] : $key;
				echo '<span class="iwt-bar-chart__seg iwt-bar-chart__seg--' . esc_attr( $key ) . '" style="width:' . esc_attr( (string) $pct ) . '%" title="' . esc_attr( $label . ': ' . Bytes::format( $bytes ) ) . '"></span>';
			}
			echo '</div><ul class="iwt-bar-chart__legend">';
			foreach ( $seg_keys as $key ) {
				$bytes = isset( $breakdown[ $key ] ) ? (int) $breakdown[ $key ] : 0;
				$label = isset( $seg_labels[ $key ] ) ? $seg_labels[ $key ] : $key;
				echo '<li><span class="iwt-bar-chart__swatch iwt-bar-chart__seg--' . esc_attr( $key ) . '" aria-hidden="true"></span>';
				echo esc_html( $label ) . ': ' . esc_html( Bytes::format( $bytes ) ) . '</li>';
			}
			echo '</ul></div>';
		}

		if ( ! empty( $disk['top_folders'] ) && is_array( $disk['top_folders'] ) ) {
			$rows = array();
			foreach ( array_slice( $disk['top_folders'], 0, 20 ) as $folder ) {
				$full   = isset( $folder['path'] ) ? (string) $folder['path'] : '';
				$rows[] = array(
					'path' => array(
						'text'  => DiskSummary::relative_path( $full ),
						'title' => $full,
					),
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
				$full   = isset( $item['path'] ) ? (string) $item['path'] : '';
				$rows[] = array(
					'path'   => array(
						'text'  => DiskSummary::relative_path( $full ),
						'title' => $full,
					),
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
			echo '<div class="notice notice-error inline"><p>' . esc_html__( 'Critical: backup files or folders were found under the web root. Anyone who guesses the name can download them.', 'iworksie-health-check' ) . '</p>';
			echo '<ul class="iwiehc-web-root-risks">';
			$shown = 0;
			foreach ( $disk['web_root_risks'] as $risk ) {
				if ( $shown >= 10 ) {
					break;
				}
				if ( is_string( $risk ) ) {
					$full   = $risk;
					$bytes  = 0;
					$is_dir = is_dir( $full );
				} elseif ( is_array( $risk ) ) {
					$full   = isset( $risk['path'] ) ? (string) $risk['path'] : '';
					$bytes  = isset( $risk['bytes'] ) ? (int) $risk['bytes'] : 0;
					$is_dir = ! empty( $risk['is_dir'] );
				} else {
					continue;
				}
				if ( '' === $full ) {
					continue;
				}
				$kind = $is_dir ? __( 'folder', 'iworksie-health-check' ) : __( 'file', 'iworksie-health-check' );
				$rel  = DiskSummary::relative_path( $full );
				echo '<li title="' . esc_attr( $full ) . '">' . esc_html( $kind . ': ' . $rel . ' (' . Bytes::format( $bytes ) . ')' ) . '</li>';
				++$shown;
			}
			echo '</ul>';
			$more = count( $disk['web_root_risks'] ) - $shown;
			if ( $more > 0 ) {
				echo '<p>' . esc_html(
					sprintf(
						/* translators: %d: additional count */
						__( 'and %d more', 'iworksie-health-check' ),
						$more
					)
				) . '</p>';
			}
			if ( isset( $disk['time'] ) ) {
				$when = function_exists( 'wp_date' )
					? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $disk['time'] )
					: gmdate( 'Y-m-d H:i', (int) $disk['time'] );
				echo '<p class="iwiehc-scan-source">' . esc_html(
					sprintf(
						/* translators: %s: date */
						__( 'From the disk scan on %s', 'iworksie-health-check' ),
						$when
					)
				) . '</p>';
			}
			echo '</div>';
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
		$url      = $this->core->report()->store_for_download( $markdown );

		echo '<div class="notice notice-info inline"><p>' . esc_html__( 'This report lists plugin names and versions. It does not include passwords, salts, API keys, or full email addresses (only the admin email domain).', 'iworksie-health-check' ) . '</p></div>';
		echo '<div class="iwt-report-actions iwt-toolbar">';
		echo '<button type="button" class="button button-primary" data-iwt-copy="iwiehc-report-source">' . esc_html( Strings::get( 'copy_report' ) ) . '</button>';
		echo '<a class="button" href="' . esc_url( $url ) . '" data-iwt-download-report download="iworksie-health-check-report.md">' . esc_html( Strings::get( 'download_report' ) ) . '</a>';
		echo '</div>';
		echo '<pre id="iwiehc-report-source" class="iwt-report-box iwiehc-report">' . esc_html( $markdown ) . '</pre>';
	}

	/**
	 * @return void
	 */
	private function render_tools() {
		$nonce = wp_create_nonce( 'iwiehc_test_email' );
		echo '<div class="iwt-card iwiehc-tools-card">';
		echo '<h2>' . esc_html__( 'Send test email', 'iworksie-health-check' ) . '</h2>';
		echo '<p>' . esc_html__( 'Sends a test message to the site admin email via wp_mail(). “Sent” means WordPress handed the message to the mail system, not that it arrived in an inbox. If mail fails, an SMTP plugin is usually the fix (a future iWorks.ie SMTP plugin is planned).', 'iworksie-health-check' ) . '</p>';
		echo '<p><button type="button" class="button button-primary" id="iwiehc-send-test-email" data-nonce="' . esc_attr( $nonce ) . '">' . esc_html__( 'Send test email', 'iworksie-health-check' ) . '</button></p>';
		echo '<div id="iwiehc-test-email-result" class="iwiehc-test-email-result" aria-live="polite"></div>';
		echo '</div>';
	}
}
