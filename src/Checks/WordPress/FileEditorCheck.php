<?php
/**
 * Theme/plugin file editor enabled.
 *
 * @package IWorksIE\HealthCheck
 */

namespace IWorksIE\HealthCheck\Checks\WordPress;

use IWorksIE\HealthCheck\Vendor\IWorksIE\Core\Checks\Check;
use IWorksIE\HealthCheck\Vendor\IWorksIE\Core\Checks\Result;

/**
 * DISALLOW_FILE_EDIT.
 */
final class FileEditorCheck implements Check {

	/** @return string */
	public function id() {
		return 'file_editor';
	}

	/** @return string */
	public function label() {
		return __( 'Plugin and theme file editor', 'iworksie-health-check' );
	}

	/** @return string */
	public function group() {
		return 'WordPress';
	}

	/** @return Result */
	public function run() {
		$disallowed = defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT;
		$why        = __( 'The built-in file editor lets anyone with admin access change plugin and theme code from the browser. If an admin account is compromised, that becomes an easy way to plant malware.', 'iworksie-health-check' );
		$fix        = __( 'Add define( \'DISALLOW_FILE_EDIT\', true ); to wp-config.php. Edit code via SFTP, Git, or your host\'s file manager instead.', 'iworksie-health-check' );

		if ( $disallowed ) {
			return Result::good(
				$this->label(),
				__( 'DISALLOW_FILE_EDIT is enabled. The plugin/theme editor is blocked.', 'iworksie-health-check' ),
				array(
					'why'  => $why,
					'fix'  => __( 'No action needed.', 'iworksie-health-check' ),
					'data' => array( 'disallowed' => true ),
				)
			);
		}
		return Result::recommended(
			$this->label(),
			__( 'The plugin and theme file editor is still enabled.', 'iworksie-health-check' ),
			array(
				'why'  => $why,
				'fix'  => $fix,
				'data' => array( 'disallowed' => false ),
			)
		);
	}
}
