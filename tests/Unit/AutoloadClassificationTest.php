<?php
/**
 * Autoload threshold classification.
 *
 * @package IWorksIE\HealthCheck
 */

namespace IWorksIE\HealthCheck\Tests\Unit;

use IWorksIE\HealthCheck\Support\Thresholds;
use PHPUnit\Framework\TestCase;

/**
 * @covers \IWorksIE\HealthCheck\Support\Thresholds
 */
final class AutoloadClassificationTest extends TestCase {

	public function test_thresholds_classify_sizes() {
		$rec  = (int) Thresholds::defaults()['autoload_recommended_bytes'];
		$crit = (int) Thresholds::defaults()['autoload_critical_bytes'];
		$this->assertSame( 800 * 1024, $rec );
		$this->assertSame( 2 * 1024 * 1024, $crit );
		$this->assertTrue( 1000 * 1024 >= $rec && 1000 * 1024 < $crit );
		$this->assertTrue( 3 * 1024 * 1024 >= $crit );
	}
}
