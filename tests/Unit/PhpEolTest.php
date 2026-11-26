<?php
/**
 * PHP EOL classification tests.
 *
 * @package IWorksIE\HealthCheck
 */

namespace IWorksIE\HealthCheck\Tests\Unit;

use IWorksIE\HealthCheck\Support\PhpEol;
use PHPUnit\Framework\TestCase;

/**
 * @covers \IWorksIE\HealthCheck\Support\PhpEol
 */
final class PhpEolTest extends TestCase {

	public function test_below_74_is_critical() {
		$this->assertSame( 'critical', PhpEol::classify( '7.3.33', '2026-11-26' ) );
	}

	public function test_eol_branch_is_critical() {
		$this->assertSame( 'critical', PhpEol::classify( '8.0.30', '2026-11-26' ) );
	}

	public function test_security_only_is_recommended() {
		$this->assertSame( 'recommended', PhpEol::classify( '8.1.0', '2024-06-01' ) );
	}

	public function test_actively_supported_is_good() {
		$this->assertSame( 'good', PhpEol::classify( '8.3.0', '2025-06-01' ) );
	}
}
