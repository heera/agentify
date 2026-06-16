<?php
/**
 * Hub — assembles the data the admin "Discovery Hub" screen renders: the live
 * envelope projected into a UI-friendly shape, the built-in adapters and whether
 * they're active, and the validation notices. Shared by the admin bootstrap and
 * the REST re-scan route so both see identical data.
 *
 * @package Agentify
 */

namespace Agentify\Discovery;

use Agentify\Settings;

defined( 'ABSPATH' ) || exit;

final class Hub {

	/**
	 * Built-in adapters — first-party callers of the public registration hook.
	 * Each MUST expose a static info(): {id,title,available}.
	 *
	 * @var string[]
	 */
	const ADAPTERS = array(
		Adapters\RestApi::class,
		Adapters\AbilitiesApi::class,
	);

	/**
	 * Build the Discovery Hub payload.
	 *
	 * @param Settings $settings Settings store.
	 * @param Registry $registry Collector (collected as a side effect).
	 * @return array
	 */
	public static function data( Settings $settings, Registry $registry ) {
		$builder  = new Envelope( $settings, $registry );
		$envelope = $builder->build();
		// tools + mcp are not part of the public discovery.json core; pull them from
		// the builder for the admin screen and the mcp.json document.
		$surface  = $builder->mcp_surface();

		return array(
			'endpoints'    => array(
				'discovery' => home_url( '/.well-known/discovery.json' ),
				'agentCard' => home_url( '/.well-known/agent-card.json' ),
				'agentJson' => home_url( '/.well-known/agent.json' ),
				'mcp'       => home_url( '/.well-known/mcp.json' ),
				'rest'      => rest_url( 'agentify/v1/discovery' ),
			),
			'site'         => $envelope['site'],
			'resources'    => array_map( array( __CLASS__, 'resource_row' ), $envelope['resources'] ),
			'capabilities' => $envelope['capabilities'],
			'apis'         => $envelope['apis'],
			'agents'       => array_map(
				static function ( $agent ) {
					return array(
						'id'     => isset( $agent['id'] ) ? $agent['id'] : '',
						'name'   => isset( $agent['name'] ) ? $agent['name'] : '',
						'skills' => isset( $agent['skills'] ) ? count( (array) $agent['skills'] ) : 0,
					);
				},
				$envelope['agents']
			),
			'wellKnown'    => $envelope['well_known'],
			'tools'        => $surface['tools'],
			'mcp'          => $surface['mcp'],
			'adapters'     => self::adapters(),
			'notices'      => $registry->notices(),
			'counts'       => array(
				'resources'    => count( $envelope['resources'] ),
				'capabilities' => count( $envelope['capabilities'] ),
				'apis'         => count( $envelope['apis'] ),
				'tools'        => count( $surface['tools'] ),
				'errors'       => count(
					array_filter(
						$registry->notices(),
						static function ( $n ) {
							return 'error' === $n['level'];
						}
					)
				),
			),
		);
	}

	/**
	 * Trim a full envelope resource to what the UI shows.
	 *
	 * @param array $resource Envelope resource.
	 * @return array
	 */
	private static function resource_row( $resource ) {
		return array(
			'id'           => $resource['id'],
			'title'        => $resource['title'],
			'type'         => $resource['type'],
			'description'  => $resource['description'],
			'version'      => $resource['version'],
			'provider'     => isset( $resource['provider']['plugin'] ) ? $resource['provider']['plugin'] : '',
			'capabilities' => $resource['capabilities'],
			'endpoints'    => array_map(
				static function ( $endpoint ) {
					return array(
						'url'  => $endpoint['url'],
						'type' => $endpoint['type'],
						'auth' => '' !== $endpoint['auth'] ? $endpoint['auth'] : 'none',
					);
				},
				$resource['endpoints']
			),
			'hasAgent'     => ! empty( $resource['agent'] ),
			'tools'        => count( $resource['tools'] ),
			'abilities'    => $resource['abilities'],
		);
	}

	/**
	 * Built-in adapter descriptors.
	 *
	 * @return array[]
	 */
	private static function adapters() {
		$out = array();
		foreach ( self::ADAPTERS as $class ) {
			if ( is_callable( array( $class, 'info' ) ) ) {
				$out[] = call_user_func( array( $class, 'info' ) );
			}
		}
		return $out;
	}
}
