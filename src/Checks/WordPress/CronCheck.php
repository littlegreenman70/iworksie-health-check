<?php
/**
 * WP-Cron health.
 *
 * @package IWorksIE\HealthCheck
 */

namespace IWorksIE\HealthCheck\Checks\WordPress;

use IWorksIE\HealthCheck\Support\Thresholds;
use IWorksIE\HealthCheck\Vendor\IWorksIE\Core\Checks\Check;
use IWorksIE\HealthCheck\Vendor\IWorksIE\Core\Checks\Result;

/**
 * Overdue cron events and DISABLE_WP_CRON.
 */
final class CronCheck implements Check {

	/** @return string */
	public function id() {
		return 'wp_cron';
	}

	/** @return string */
	public function label() {
		return __( 'Scheduled events (WP-Cron)', 'iworksie-health-check' );
	}

	/** @return string */
	public function group() {
		return 'WordPress';
	}

	/** @return Result */
	public function run() {
		$disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
		$crons    = _get_cron_array();
		if ( ! is_array( $crons ) ) {
			$crons = array();
		}
		$now      = time();
		$total    = 0;
		$overdue  = array();
		$max_late = 0;
		foreach ( $crons as $timestamp => $hooks ) {
			if ( ! is_array( $hooks ) ) {
				continue;
			}
			foreach ( $hooks as $hook => $events ) {
				$total += is_array( $events ) ? count( $events ) : 1;
				$late   = $now - (int) $timestamp;
				if ( $late > 0 ) {
					$overdue[] = array(
						'hook' => (string) $hook,
						'late' => $late,
					);
					$max_late  = max( $max_late, $late );
				}
			}
		}

		$rec  = (int) Thresholds::get( 'cron_overdue_recommended', HOUR_IN_SECONDS );
		$crit = (int) Thresholds::get( 'cron_overdue_critical', DAY_IN_SECONDS );
		$why  = __( 'Scheduled events send emails, clear caches, publish scheduled posts, and run backups. When cron stalls, those jobs pile up.', 'iworksie-health-check' );
		$fix  = __( 'If DISABLE_WP_CRON is true, make sure a real server cron hits wp-cron.php every few minutes. Otherwise visit the site occasionally or ask your host to enable a system cron. Plugins such as WP Crontrol can show stuck events.', 'iworksie-health-check' );
		$data = array(
			'disabled' => $disabled,
			'total'    => $total,
			'overdue'  => count( $overdue ),
			'max_late' => $max_late,
		);

		$parts   = array();
		$parts[] = sprintf(
			/* translators: %d: scheduled event count */
			__( '%d scheduled events.', 'iworksie-health-check' ),
			$total
		);
		if ( $disabled ) {
			$parts[] = __( 'DISABLE_WP_CRON is set (a real server cron is expected).', 'iworksie-health-check' );
		}
		if ( count( $overdue ) > 0 ) {
			$parts[] = sprintf(
				/* translators: 1: overdue count, 2: human time */
				__( '%1$d overdue (latest is %2$s late).', 'iworksie-health-check' ),
				count( $overdue ),
				human_time_diff( $now - $max_late, $now )
			);
		}
		$desc = implode( ' ', $parts );

		if ( $max_late >= $crit ) {
			return Result::critical(
				$this->label(),
				$desc,
				array(
					'why'  => $why,
					'fix'  => $fix,
					'data' => $data,
				)
			);
		}
		if ( $max_late >= $rec ) {
			return Result::recommended(
				$this->label(),
				$desc,
				array(
					'why'  => $why,
					'fix'  => $fix,
					'data' => $data,
				)
			);
		}
		if ( $disabled ) {
			return Result::info(
				$this->label(),
				$desc,
				array(
					'why'  => $why,
					'fix'  => $fix,
					'data' => $data,
				)
			);
		}
		return Result::good(
			$this->label(),
			$desc,
			array(
				'why'  => $why,
				'fix'  => __( 'No action needed.', 'iworksie-health-check' ),
				'data' => $data,
			)
		);
	}
}
