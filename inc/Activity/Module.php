<?php
/**
 * Activity module — wires the agent-activity log: ensures the table exists,
 * exposes the admin REST surface (read + clear), and runs the daily prune cron.
 * Recording itself is done at the endpoint serve-paths via Recorder::record().
 *
 * @package Agentimus
 */

namespace Agentimus\Activity;

use Agentimus\Settings;
use Agentimus\Guard;

defined( 'ABSPATH' ) || exit;

final class Module {

	const CRON = 'agentimus_prune_activity';

	/** @var Settings */
	private $settings;

	/**
	 * @param Settings $settings Settings store.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Hook the table check, REST routes and prune handler.
	 */
	public function register() {
		Table::maybe_install();
		Referrals::maybe_install();
		// Ensure the prune cron exists on THIS site. Scheduling only at activation
		// misses network sub-sites, because a network activation does not run the
		// activation hook per site; this self-heals them (and any site created
		// before this safeguard existed). Cheap: schedule() is a single
		// wp_next_scheduled read once the event is already present.
		self::schedule();
		// Count human visits referred from AI assistants (the mirror of the bot log).
		add_action( 'template_redirect', array( Referrals::class, 'maybe_record' ), 30 );
		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_action( self::CRON, array( Repository::class, 'prune' ) );
		add_action( self::CRON, array( Referrals::class, 'prune' ) );
	}

	/**
	 * REST: GET /activity (stats) and DELETE /activity (clear). Admin-only.
	 */
	public function routes() {
		register_rest_route(
			'agentimus/v1',
			'/activity',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => array( $this, 'can_manage' ),
					'callback'            => function () {
						return rest_ensure_response( Repository::stats( $this->settings ) );
					},
				),
				array(
					'methods'             => 'DELETE',
					'permission_callback' => array( $this, 'can_manage' ),
					'callback'            => function () {
						Repository::clear();
						return rest_ensure_response( Repository::stats( $this->settings ) );
					},
				),
			)
		);

		register_rest_route(
			'agentimus/v1',
			'/activity/day',
			array(
				'methods'             => 'GET',
				'permission_callback' => array( $this, 'can_manage' ),
				'callback'            => function ( \WP_REST_Request $request ) {
					$date = (string) $request->get_param( 'date' );
					if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
						return new \WP_Error(
							'agentimus_bad_date',
							__( 'A valid date (YYYY-MM-DD) is required.', 'agentimus' ),
							array( 'status' => 400 )
						);
					}
					return rest_ensure_response( Repository::day_requests( $date ) );
				},
				'args'                => array(
					'date' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			'agentimus/v1',
			'/activity/block',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( $this, 'can_manage' ),
				'callback'            => array( $this, 'block' ),
				'args'                => array(
					'ua'      => array( 'type' => 'string' ),
					'spoofed' => array( 'type' => 'boolean' ),
				),
			)
		);

		register_rest_route(
			'agentimus/v1',
			'/activity/allow',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( $this, 'can_manage' ),
				'callback'            => array( $this, 'allow' ),
				'args'                => array( 'ua' => array( 'type' => 'string' ) ),
			)
		);
	}

	/**
	 * REST: POST /activity/block — the activity panel's one-click "Block this".
	 * Either arms the spoofed/scanner class (spoofed=true) or appends a safe,
	 * UA-derived token to the denylist; both turn enforcement on. Returns the
	 * refreshed stats so the panel updates the flag / blocked states in place.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function block( \WP_REST_Request $request ) {
		if ( $request->get_param( 'spoofed' ) ) {
			$this->settings->block_spoofed_class();
			return rest_ensure_response( $this->block_payload() );
		}

		$ua    = (string) $request->get_param( 'ua' );
		$token = Guard::suggest_token( $ua );
		if ( '' === $token ) {
			return new \WP_Error(
				'agentimus_no_safe_token',
				__( 'No safe block rule could be derived for this client. Add one under Settings → Block scanners & scrapers.', 'agentimus' ),
				array( 'status' => 422 )
			);
		}
		$this->settings->block_agent( $token );
		return rest_ensure_response( $this->block_payload() );
	}

	/**
	 * REST: POST /activity/allow — the panel's "Allow" / trust action. Adds the
	 * derived token to the owner's allowlist (never blocked, never flagged again),
	 * then returns the refreshed payload so the panel + Settings update in place.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function allow( \WP_REST_Request $request ) {
		$ua    = (string) $request->get_param( 'ua' );
		$token = Guard::suggest_token( $ua );
		if ( '' === $token ) {
			return new \WP_Error(
				'agentimus_no_safe_token',
				__( 'No safe allow rule could be derived for this client.', 'agentimus' ),
				array( 'status' => 422 )
			);
		}
		$this->settings->allow_agent( $token );
		return rest_ensure_response( $this->block_payload() );
	}

	/**
	 * Block response: refreshed activity stats PLUS the updated settings — the block
	 * writes to the same blocked_agents / block_agents the Settings tab shows, so
	 * returning them lets the admin reflect the new denylist entry there immediately,
	 * with no reload and no second request.
	 *
	 * @return array{activity:array,settings:array}
	 */
	private function block_payload() {
		return array(
			'activity' => Repository::stats( $this->settings ),
			'settings' => $this->settings->all(),
		);
	}

	/**
	 * @return bool
	 */
	public function can_manage() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Schedule the daily prune (activation).
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', self::CRON );
		}
	}

	/**
	 * Clear the prune schedule (deactivation).
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( self::CRON );
	}
}
