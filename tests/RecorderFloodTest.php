<?php
/**
 * Recorder flood policy — the pure decision that keeps a burst of disposable
 * user-agents from drowning the bounded activity log, while never throttling a
 * genuinely-recognised crawler. The transient counter and RNG plumbing live in
 * record(); the policy itself is isolated in survives_flood() so it can be
 * exercised deterministically (the roll is injected).
 *
 * @package Agentimus\Tests
 */

namespace Agentimus\Tests;

use Agentimus\Activity\Recorder;
use PHPUnit\Framework\TestCase;

final class RecorderFloodTest extends TestCase {

	/** A recognised crawler is ALWAYS logged — every roll, even far past the flood
	 *  threshold — so real agents can hit as fast as they like. */
	public function test_recognised_agent_always_survives_however_fast() {
		$over = Recorder::FLOOD_THRESHOLD + 10_000;
		$this->assertTrue( Recorder::survives_flood( true, $over, 2 ) ); // a losing roll…
		$this->assertTrue( Recorder::survives_flood( true, $over, 1 ) ); // …and a winning one.
	}

	/** Below the per-window threshold, even unrecognised traffic is kept in full —
	 *  sampling only ever engages once a window is actually flooding. */
	public function test_unrecognised_traffic_under_threshold_is_kept() {
		$this->assertTrue( Recorder::survives_flood( false, 1, 2 ) );
		$this->assertTrue( Recorder::survives_flood( false, Recorder::FLOOD_THRESHOLD, 7 ) );
	}

	/** Over the threshold, an unrecognised hit survives only on a winning roll
	 *  (≈ 1 in FLOOD_SAMPLE) — so the flood is sampled, not fully dropped. */
	public function test_unrecognised_flood_is_sampled_not_silenced() {
		$over = Recorder::FLOOD_THRESHOLD + 1;
		$this->assertTrue( Recorder::survives_flood( false, $over, 1 ), 'winning roll keeps a sample' );
		$this->assertFalse( Recorder::survives_flood( false, $over, 2 ), 'losing roll is dropped' );
		$this->assertFalse( Recorder::survives_flood( false, $over, Recorder::FLOOD_SAMPLE ), 'losing roll is dropped' );
	}
}
