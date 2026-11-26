<?php
/**
 * Guess which plugin owns an option by name prefix.
 *
 * @package IWorksIE\HealthCheck
 */

namespace IWorksIE\HealthCheck\Support;

/**
 * Heuristic option ownership.
 */
final class OptionOwner {

	/**
	 * Guess owner from option name against plugin slugs.
	 *
	 * @param string            $option_name Option name.
	 * @param array<int,string> $plugin_slugs Plugin directory slugs.
	 * @return string|null Slug or null.
	 */
	public static function guess( $option_name, array $plugin_slugs ) {
		$option_name = strtolower( (string) $option_name );
		$best        = null;
		$best_len    = 0;
		foreach ( $plugin_slugs as $slug ) {
			$slug = strtolower( (string) $slug );
			if ( '' === $slug ) {
				continue;
			}
			$variants = array(
				$slug,
				str_replace( '-', '_', $slug ),
				str_replace( '-', '', $slug ),
			);
			foreach ( $variants as $variant ) {
				$len = strlen( $variant );
				if ( $len < 3 || $len <= $best_len ) {
					continue;
				}
				if ( 0 === strpos( $option_name, $variant ) || false !== strpos( $option_name, '_' . $variant ) ) {
					$best     = $slug;
					$best_len = $len;
				}
			}
		}
		return $best;
	}
}
