<?php
/**
 * Bytes parser tests.
 *
 * @package IWorksIE\HealthCheck
 */

namespace IWorksIE\HealthCheck\Tests\Unit;

use IWorksIE\HealthCheck\Support\Bytes;
use PHPUnit\Framework\TestCase;

/**
 * @covers \IWorksIE\HealthCheck\Support\Bytes
 */
final class BytesTest extends TestCase {

	public function test_parses_common_units() {
		$this->assertSame( 128 * 1024 * 1024, Bytes::parse( '128M' ) );
		$this->assertSame( 1024 * 1024 * 1024, Bytes::parse( '1G' ) );
		$this->assertSame( 2048, Bytes::parse( '2K' ) );
		$this->assertSame( -1, Bytes::parse( '-1' ) );
	}
}
