<?php
/**
 * DiskSummary against a core 1.0.1 finished-result fixture.
 *
 * @package IWorksIE\HealthCheck
 */

namespace IWorksIE\HealthCheck\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use IWorksIE\HealthCheck\Support\DiskSummary;
use PHPUnit\Framework\TestCase;

/**
 * @covers \IWorksIE\HealthCheck\Support\DiskSummary
 */
final class DiskSummaryTest extends TestCase {

	/**
	 * @var array<string,mixed>
	 */
	private $fixture;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', '/var/www/site/' );
		}
		if ( ! defined( 'WP_CONTENT_DIR' ) ) {
			define( 'WP_CONTENT_DIR', '/var/www/site/wp-content' );
		}

		Functions\when( 'wp_normalize_path' )->alias(
			static function ( $path ) {
				return str_replace( '\\', '/', (string) $path );
			}
		);
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'number_format_i18n' )->alias(
			static function ( $n, $decimals = 0 ) {
				return number_format( (float) $n, (int) $decimals );
			}
		);

		$path = dirname( __DIR__ ) . '/fixtures/size-scanner-result.json';
		$raw  = file_get_contents( $path );
		$this->assertNotFalse( $raw );
		$decoded = json_decode( $raw, true );
		$this->assertIsArray( $decoded );
		$this->fixture = $decoded;
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_from_job_result_shape() {
		$summary = DiskSummary::from_job( array( 'result' => $this->fixture ) );

		$this->assertSame( 2383505463, $summary['total_bytes'] );
		$this->assertSame( 49837, $summary['total_files'] );

		$this->assertSame( 1100000000, $summary['breakdown']['uploads'] );
		$this->assertSame( 420000000, $summary['breakdown']['plugins'] );
		$this->assertSame( 80000000, $summary['breakdown']['themes'] );
		$this->assertSame( 1837750042, $summary['breakdown']['content'] );
		$this->assertGreaterThan( 0, $summary['breakdown']['other'] );

		$this->assertGreaterThanOrEqual( 8, count( $summary['top_folders'] ) );
		$this->assertLessThanOrEqual( 20, count( $summary['top_folders'] ) );
		$this->assertSame( '/var/www/site', $summary['top_folders'][0]['path'] );
		$this->assertSame( '/var/www/site/wp-content', $summary['top_folders'][1]['path'] );

		$this->assertNotEmpty( $summary['top_files'] );
		$this->assertSame( '/var/www/site/wp-content/uploads/video/huge.mp4', $summary['top_files'][0]['path'] );

		$listed = 0;
		foreach ( $summary['dead_items'] as $item ) {
			$listed += (int) $item['bytes'];
		}
		$this->assertSame( 312, $summary['dead_overflow'] );
		$this->assertSame( 1288490188, $summary['dead_overflow_bytes'] );
		$this->assertSame( $listed + 1288490188, $summary['dead_bytes'] );

		$risk_paths = array_map(
			static function ( $row ) {
				return is_array( $row ) ? $row['path'] : $row;
			},
			$summary['web_root_risks']
		);
		$this->assertContains( '/var/www/site/backup.sql', $risk_paths );
		$this->assertContains( '/var/www/site/wp-content/uploads/backup-old.zip', $risk_paths );
		$this->assertArrayHasKey( 'outside', $summary['breakdown'] );
		$this->assertSame(
			2383505463 - 1837750042,
			$summary['breakdown']['outside']
		);

		$message = DiskSummary::freeable_message( $summary );
		$this->assertStringContainsString( 'You could free about', $message );
		$this->assertStringContainsString( '312 more files', $message );
		$this->assertStringContainsString( '1.2', $message );
	}

	public function test_from_job_accepts_bare_result() {
		$summary = DiskSummary::from_job( $this->fixture );
		$this->assertSame( 2383505463, $summary['total_bytes'] );
		$this->assertSame( 312, $summary['dead_overflow'] );
	}
}
