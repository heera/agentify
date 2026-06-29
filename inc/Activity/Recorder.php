<?php
/**
 * Recorder — logs one agent hit on a discovery/llms endpoint.
 *
 * First-party, local-only (never transmitted anywhere). Stores the endpoint,
 * the classified agent, and a truncated User-Agent — and DELIBERATELY no IP
 * address, so there's no PII/GDPR footprint by default. Called only from the
 * discovery/agent serve paths (low-frequency), so a single INSERT per hit is
 * negligible.
 *
 * @package Agentimus
 */

namespace Agentimus\Activity;

use Agentimus\Settings;

defined( 'ABSPATH' ) || exit;

final class Recorder {

	/** Sample rate for the opportunistic row-count cap: roughly one insert in this
	 *  many runs the bounded trim (see Repository::trim_to_cap), keeping the table
	 *  near its ceiling mid-day without a per-request COUNT. */
	const CAP_CHECK_ODDS = 200;

	/** Flood window (seconds): throttle-eligible hits are counted per window. */
	const FLOOD_WINDOW = 60;

	/** Unrecognised hits allowed in a window before flood sampling engages. */
	const FLOOD_THRESHOLD = 60;

	/** Once a window is flooding, keep roughly one unrecognised hit in this many. */
	const FLOOD_SAMPLE = 20;

	/** Transient key prefix for the per-window unrecognised-hit counter. */
	const RATE_PREFIX = 'agentimus_rec_rate_';

	/** @var bool|null Per-request cache of the enable flag. */
	private static $enabled = null;

	/**
	 * Record a hit on the named endpoint, if logging is enabled.
	 *
	 * @param string $endpoint Short endpoint label (e.g. "discovery.json").
	 */
	public static function record( $endpoint ) {
		if ( ! self::enabled() ) {
			return;
		}

		/**
		 * Skip logging the site owner inspecting their own endpoints — a logged-in
		 * administrator opening discovery.json in a browser is not agent traffic and
		 * would otherwise bury the log in "Browser" self-noise. Filter to false to
		 * log every request regardless.
		 *
		 * @param bool $skip Whether to skip this request. Default true for admins.
		 */
		if ( apply_filters( 'agentimus_activity_skip_self', is_user_logged_in() && current_user_can( 'manage_options' ) ) ) {
			return;
		}

		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- read-only logging of a public endpoint hit.

		// Flood guard. A recognised crawler (GPTBot, Googlebot, ClaudeBot…) is ALWAYS
		// logged, however fast it hits — that traffic is the signal we want. Every
		// other client (unknown, script/tool, legacy-device spoof, no-UA) is
		// throttle-eligible: once such hits exceed FLOOD_THRESHOLD in a window we keep
		// only a 1-in-FLOOD_SAMPLE sample, so a burst of disposable user-agents
		// (EvilBot-1, EvilBot-2, …) can't drown real agents out of a bounded log.
		// Recognition is SPOOF-AWARE (Classifier::is_recognised_agent), so a scanner
		// can't buy the fast-pass by pasting a known bot's name into a forged UA.
		$recognised = Classifier::is_recognised_agent( $ua );
		$count      = $recognised ? 0 : self::note_unrecognised_hit();
		if ( ! self::survives_flood( $recognised, $count, wp_rand( 1, self::FLOOD_SAMPLE ) ) ) {
			return;
		}

		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB -- single insert into our own table.
			Table::name(),
			array(
				'endpoint' => substr( (string) $endpoint, 0, 64 ),
				'agent'    => substr( Classifier::classify( $ua ), 0, 64 ),
				'ua'       => substr( $ua, 0, 255 ),
				'hit_at'   => current_time( 'mysql', true ), // GMT.
			),
			array( '%s', '%s', '%s', '%s' )
		);

		// Opportunistic backstop: most inserts pay only a cheap rand(); roughly one
		// in CAP_CHECK_ODDS runs a single bounded DELETE, so the table can't bank
		// unbounded rows on an extreme-traffic day before the daily prune cron.
		if ( 1 === wp_rand( 1, self::CAP_CHECK_ODDS ) ) {
			Repository::trim_to_cap();
		}
	}

	/**
	 * Count one throttle-eligible (unrecognised) hit in the current flood window and
	 * return the running total. A coarse per-minute transient bucket — approximate
	 * under concurrency (a lost increment only ever UNDER-counts, i.e. errs toward
	 * logging), which is fine here: the row cap (trim_to_cap) is the hard backstop.
	 * Honours an external object cache automatically (it is just the Transients API).
	 *
	 * @return int Unrecognised-hit count so far this window.
	 */
	private static function note_unrecognised_hit() {
		$key   = self::RATE_PREFIX . (int) floor( time() / self::FLOOD_WINDOW );
		$count = (int) get_transient( $key ) + 1;
		// Outlive the window so a flood straddling the bucket boundary still reads as
		// elevated; the next window simply starts counting from zero again.
		set_transient( $key, $count, self::FLOOD_WINDOW * 2 );
		return $count;
	}

	/**
	 * Whether a hit survives flood sampling. Pure, with the random roll injected, so
	 * the policy is unit-testable without transients or RNG. A recognised agent, or
	 * any traffic below the window threshold, is always kept; over the threshold an
	 * unrecognised hit is kept only when the roll lands (≈ 1 in FLOOD_SAMPLE).
	 *
	 * @param bool $recognised Recognised crawler → always kept (the fast-pass).
	 * @param int  $count      Unrecognised-hit count this window.
	 * @param int  $roll       A 1..FLOOD_SAMPLE roll.
	 * @return bool True to log the hit.
	 */
	public static function survives_flood( $recognised, $count, $roll ) {
		if ( $recognised || $count <= self::FLOOD_THRESHOLD ) {
			return true;
		}
		return 1 === (int) $roll;
	}

	/**
	 * Whether activity logging is on (cached for the request).
	 *
	 * @return bool
	 */
	private static function enabled() {
		if ( null === self::$enabled ) {
			self::$enabled = (bool) ( new Settings() )->enabled( 'enable_activity' );
		}
		return self::$enabled;
	}
}
