<?php
/**
 * Plugin bootstrap.
 *
 * @package IWorksIE\HealthCheck
 */

namespace IWorksIE\HealthCheck;

use IWorksIE\HealthCheck\Admin\Ajax;
use IWorksIE\HealthCheck\Admin\Screens;
use IWorksIE\HealthCheck\Checks\Database\AutoloadSizeCheck;
use IWorksIE\HealthCheck\Checks\Database\DatabaseSizeCheck;
use IWorksIE\HealthCheck\Checks\Database\LeftoverTablesCheck;
use IWorksIE\HealthCheck\Checks\Database\RevisionsTransientsCheck;
use IWorksIE\HealthCheck\Checks\Disk\DeadWeightWebRootCheck;
use IWorksIE\HealthCheck\Checks\Disk\DiskFreeCheck;
use IWorksIE\HealthCheck\Checks\Server\HttpsCheck;
use IWorksIE\HealthCheck\Checks\Server\MemoryLimitCheck;
use IWorksIE\HealthCheck\Checks\Server\PhpExtensionsCheck;
use IWorksIE\HealthCheck\Checks\Server\PhpLimitsCheck;
use IWorksIE\HealthCheck\Checks\Server\PhpVersionCheck;
use IWorksIE\HealthCheck\Checks\Server\ServerSoftwareCheck;
use IWorksIE\HealthCheck\Checks\WordPress\CronCheck;
use IWorksIE\HealthCheck\Checks\WordPress\DebugDisplayCheck;
use IWorksIE\HealthCheck\Checks\WordPress\FileEditorCheck;
use IWorksIE\HealthCheck\Checks\WordPress\InactivePluginsThemesCheck;
use IWorksIE\HealthCheck\Checks\WordPress\ObjectCacheCheck;
use IWorksIE\HealthCheck\Checks\WordPress\RestApiCheck;
use IWorksIE\HealthCheck\Checks\WordPress\UpdatesCheck;
use IWorksIE\HealthCheck\Cli\Command;
use IWorksIE\HealthCheck\Vendor\IWorksIE\Core\Checks\SiteHealthBridge;
use IWorksIE\HealthCheck\Vendor\IWorksIE\Core\Core;
use IWorksIE\HealthCheck\Vendor\IWorksIE\Core\Filesystem\SizeScanner;

/**
 * Singleton plugin entry.
 */
final class Plugin {

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @var Core|null
	 */
	private $core = null;

	/**
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * @return Core|null
	 */
	public function core() {
		return $this->core;
	}

	/**
	 * Boot core, checks, jobs, menu, bridges.
	 *
	 * @return void
	 */
	public function boot() {
		if ( null !== $this->core ) {
			return;
		}

		$cap = is_multisite() ? 'manage_network_options' : 'manage_options';

		$this->core = Core::boot(
			array(
				'plugin_file' => IWIEHC_FILE,
				'slug'        => 'iworksie-health-check',
				'prefix'      => 'iwiehc',
				'name'        => __( 'Health Check', 'iworksie-health-check' ),
				'version'     => IWIEHC_VERSION,
				'text_domain' => 'iworksie-health-check',
				'capability'  => $cap,
				'wporg_url'   => 'https://wordpress.org/plugins/iworksie-health-check/',
				'description' => __( 'Read-only health check: server, WordPress, database, disk space and dead weight, with plain-English fixes.', 'iworksie-health-check' ),
				'icon'        => 'dashicons-heart',
				'tagline'     => __( 'Read-only. Never changes your site.', 'iworksie-health-check' ),
			)
		);

		foreach ( $this->checks() as $check ) {
			$this->core->checks()->add( $check );
		}

		$this->core->jobs()->add( new SizeScanner() );

		$screens = new Screens( $this->core );
		$this->core->menu()->add_submenu(
			__( 'Health Check', 'iworksie-health-check' ),
			__( 'Health Check', 'iworksie-health-check' ),
			'iworksie-health-check',
			array( $screens, 'render' )
		);

		$bridge = new SiteHealthBridge(
			$this->core->checks(),
			array( 'php_version', 'autoload_size', 'wp_cron', 'dead_weight_web_root' ),
			'iWorks.ie Health Check'
		);
		$bridge->hooks();

		$ajax = new Ajax();
		$ajax->hooks();

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		Command::register();
	}

	/**
	 * Plugin JS for caching results, disk finish, test email.
	 *
	 * @param string $hook Hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( null === $this->core || ! $this->core->is_our_screen( $hook ) ) {
			return;
		}
		wp_enqueue_script(
			'iwiehc-admin',
			IWIEHC_URL . 'assets/js/admin.js',
			array( 'iwiehc-iwt-core' ),
			IWIEHC_VERSION,
			true
		);
		wp_localize_script(
			'iwiehc-admin',
			'iwiehcAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'strings' => array(
					'emailSending' => __( 'Sending…', 'iworksie-health-check' ),
					'diskSaving'   => __( 'Saving disk summary…', 'iworksie-health-check' ),
				),
			)
		);
		wp_enqueue_style(
			'iwiehc-admin',
			IWIEHC_URL . 'assets/css/admin.css',
			array( 'iwiehc-iwt-core' ),
			IWIEHC_VERSION
		);
	}

	/**
	 * @return array<int,object>
	 */
	private function checks() {
		return array(
			new PhpVersionCheck(),
			new MemoryLimitCheck(),
			new PhpLimitsCheck(),
			new PhpExtensionsCheck(),
			new HttpsCheck(),
			new ServerSoftwareCheck(),
			new UpdatesCheck(),
			new InactivePluginsThemesCheck(),
			new DebugDisplayCheck(),
			new CronCheck(),
			new RestApiCheck(),
			new ObjectCacheCheck(),
			new FileEditorCheck(),
			new DatabaseSizeCheck(),
			new AutoloadSizeCheck(),
			new LeftoverTablesCheck(),
			new RevisionsTransientsCheck(),
			new DiskFreeCheck(),
			new DeadWeightWebRootCheck(),
		);
	}
}
