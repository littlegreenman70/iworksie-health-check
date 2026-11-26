<?php
/**
 * Option owner guessing.
 *
 * @package IWorksIE\HealthCheck
 */

namespace IWorksIE\HealthCheck\Tests\Unit;

use IWorksIE\HealthCheck\Support\OptionOwner;
use PHPUnit\Framework\TestCase;

/**
 * @covers \IWorksIE\HealthCheck\Support\OptionOwner
 */
final class OptionOwnerTest extends TestCase {

	public function test_guesses_by_slug_prefix() {
		$slugs = array( 'woocommerce', 'wordfence', 'iworksie-health-check' );
		$this->assertSame( 'woocommerce', OptionOwner::guess( 'woocommerce_queue_flush_rewrite_rules', $slugs ) );
		$this->assertSame( 'wordfence', OptionOwner::guess( 'wordfence_version', $slugs ) );
		$this->assertNull( OptionOwner::guess( 'siteurl', $slugs ) );
	}
}
