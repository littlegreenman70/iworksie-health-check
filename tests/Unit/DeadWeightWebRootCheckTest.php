<?php
/**
 * Backup web-root check: ignore WP upgrade folders, empty dirs, severity split.
 *
 * @package IWorksIE\HealthCheck
 */

namespace IWorksIE\HealthCheck\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use IWorksIE\HealthCheck\Checks\Disk\DeadWeightWebRootCheck;
use PHPUnit\Framework\TestCase;

/**
 * @covers \IWorksIE\HealthCheck\Checks\Disk\DeadWeightWebRootCheck
 */
final class DeadWeightWebRootCheckTest extends TestCase {

	/** @var string */
	private $tmpdir = '';

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( '_n' )->alias(
			static function ( $single, $plural, $number ) {
				return 1 === (int) $number ? $single : $plural;
			}
		);
		Functions\when( 'wp_normalize_path' )->alias(
			static function ( $path ) {
				return str_replace( '\\', '/', (string) $path );
			}
		);

		$this->tmpdir = sys_get_temp_dir() . '/iwiehc-backup-' . uniqid( '', true );
		mkdir( $this->tmpdir . '/wp-content/upgrade-temp-backup', 0777, true );
		mkdir( $this->tmpdir . '/wp-content/upgrade', 0777, true );
	}

	protected function tearDown(): void {
		$this->rmTree( $this->tmpdir );
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * @param string $dir Directory.
	 * @return void
	 */
	private function rmTree( $dir ) {
		if ( '' === $dir || ! is_dir( $dir ) ) {
			return;
		}
		$items = scandir( $dir );
		if ( ! is_array( $items ) ) {
			return;
		}
		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			$path = $dir . '/' . $item;
			if ( is_dir( $path ) ) {
				$this->rmTree( $path );
			} else {
				unlink( $path );
			}
		}
		rmdir( $dir );
	}

	public function test_ignores_upgrade_temp_backup_empty_and_with_files() {
		$empty = $this->tmpdir . '/wp-content/upgrade-temp-backup';
		$this->assertTrue( DeadWeightWebRootCheck::is_ignored_path( $empty ) );

		file_put_contents( $empty . '/rollback.php', '<?php' );
		$found = DeadWeightWebRootCheck::shallow_find( $this->tmpdir );
		foreach ( $found as $row ) {
			$this->assertStringNotContainsString( 'upgrade-temp-backup', $row['path'] );
		}
		$classified = DeadWeightWebRootCheck::classify_matches(
			array(
				array(
					'path'   => $empty,
					'bytes'  => 100,
					'is_dir' => true,
				),
			)
		);
		$this->assertSame( array(), $classified['items'] );
	}

	public function test_ignores_empty_backup_folder() {
		$dir = $this->tmpdir . '/wp-content/backup-old';
		mkdir( $dir, 0777, true );
		$found = DeadWeightWebRootCheck::shallow_find( $this->tmpdir );
		foreach ( $found as $row ) {
			$this->assertStringNotContainsString( 'backup-old', $row['path'] );
		}
		$classified = DeadWeightWebRootCheck::classify_matches(
			array(
				array(
					'path'   => $dir,
					'bytes'  => 0,
					'is_dir' => true,
				),
			)
		);
		$this->assertSame( array(), $classified['items'] );
	}

	public function test_critical_for_sql_zip_and_wpress() {
		file_put_contents( $this->tmpdir . '/site-backup.sql', 'DUMP' );
		file_put_contents( $this->tmpdir . '/wp-content/backup.zip', 'PK' );
		file_put_contents( $this->tmpdir . '/x.wpress', 'AI1WM' );

		$found      = DeadWeightWebRootCheck::shallow_find( $this->tmpdir );
		$classified = DeadWeightWebRootCheck::classify_matches( $found );
		$paths      = array_map(
			static function ( $row ) {
				return basename( $row['path'] );
			},
			$classified['items']
		);

		$this->assertContains( 'site-backup.sql', $paths );
		$this->assertContains( 'backup.zip', $paths );
		$this->assertContains( 'x.wpress', $paths );
		$this->assertTrue( $classified['has_critical'] );
		foreach ( $classified['items'] as $row ) {
			if ( in_array( basename( $row['path'] ), array( 'site-backup.sql', 'backup.zip', 'x.wpress' ), true ) ) {
				$this->assertSame( 'critical', $row['severity'] );
			}
		}
	}

	public function test_recommended_for_elementor_json_export() {
		$file = $this->tmpdir . '/wp-content/backup-home-elementor-data.json';
		file_put_contents( $file, '{"ok":true}' );

		$found      = DeadWeightWebRootCheck::shallow_find( $this->tmpdir );
		$classified = DeadWeightWebRootCheck::classify_matches( $found );

		$this->assertFalse( $classified['has_critical'] );
		$this->assertCount( 1, $classified['items'] );
		$this->assertSame( 'recommended', $classified['items'][0]['severity'] );
		$this->assertSame( $file, $classified['items'][0]['path'] );
	}

	public function test_filter_adds_ignored_path() {
		Functions\when( 'apply_filters' )->alias(
			static function ( $tag, $value ) {
				if ( 'iworks_tools_backup_ignore_paths' === $tag && is_array( $value ) ) {
					$value[] = 'wp-content/my-safe-backups';
				}
				return $value;
			}
		);

		$dir = $this->tmpdir . '/wp-content/my-safe-backups';
		mkdir( $dir, 0777, true );
		file_put_contents( $dir . '/dump.sql', 'DUMP' );

		$this->assertTrue( DeadWeightWebRootCheck::is_ignored_path( $dir ) );
		$found = DeadWeightWebRootCheck::shallow_find( $this->tmpdir );
		foreach ( $found as $row ) {
			$this->assertStringNotContainsString( 'my-safe-backups', $row['path'] );
		}
	}
}
