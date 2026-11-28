<?php
/**
 * GA4 Measurement Protocol client
 *
 * Sends events to GA4 from the server, for things the browser can't report
 * reliably: a lead that passed validation, an enquiry an agent sent with no
 * browser at all, an account created at checkout (#319).
 *
 * Needs the measurement ID (brighter_ga4_measurement_id) and a Measurement
 * Protocol API secret (se_ga4_mp_api_secret), created in GA4 under Admin ›
 * Data streams › the web stream › Measurement Protocol API secrets. Without
 * the secret is_configured() is false and nothing is sent.
 *
 * When the request came from a visitor's browser, their _ga client ID and
 * GA4 session ID are read from the cookies, so the event joins their session
 * and keeps its source, medium and campaign. Otherwise a one-off client ID is
 * used.
 *
 * Sends are non-blocking: a slow or failing GA4 never holds up a form.
 *
 * v1.0 | 2026-11-28 (#319)
 *
 * @package    SiteEssentials
 * @subpackage Modules\Analytics
 */

namespace SiteEssentials\Modules\Analytics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GA4_Measurement_Protocol {

	/** Option holding the API secret. Never printed, never logged. */
	const OPTION_SECRET = 'se_ga4_mp_api_secret';

	const ENDPOINT = 'https://www.google-analytics.com/mp/collect';

	public static function measurement_id(): string {
		return trim( (string) get_option( 'brighter_ga4_measurement_id', '' ) );
	}

	public static function is_configured(): bool {
		return '' !== self::measurement_id() && '' !== trim( (string) get_option( self::OPTION_SECRET, '' ) );
	}

	/**
	 * The visitor's GA client ID from the _ga cookie (GA1.1.<random>.<timestamp>
	 * becomes <random>.<timestamp>), or '' when there is none.
	 */
	public static function cookie_client_id(): string {
		$raw = isset( $_COOKIE['_ga'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['_ga'] ) ) : '';
		if ( preg_match( '/^GA\d+\.\d+\.(\d+\.\d+)$/', $raw, $m ) ) {
			return $m[1];
		}
		return '';
	}

	/**
	 * The current GA4 session ID from the _ga_<container> cookie, or 0.
	 * Handles both cookie formats: GS1.1.<session>.… and GS2.1.s<session>$o….
	 */
	public static function cookie_session_id(): int {
		$container = preg_replace( '/^G-/i', '', self::measurement_id() );
		$name      = '_ga_' . $container;
		$raw       = isset( $_COOKIE[ $name ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ $name ] ) ) : '';
		if ( preg_match( '/^GS1\.\d+\.(\d+)\./', $raw, $m ) || preg_match( '/^GS2\.\d+\.s(\d+)/', $raw, $m ) ) {
			return (int) $m[1];
		}
		return 0;
	}

	/** A client ID for an event with no browser behind it (an agent enquiry). */
	public static function one_off_client_id(): string {
		return wp_rand( 100000000, 2147483647 ) . '.' . time();
	}

	/**
	 * Send events. Each event is ['name' => string, 'params' => array].
	 *
	 * @param array  $events
	 * @param string $client_id   '' to use the visitor's cookie, or a one-off ID when there is none.
	 * @param bool   $use_session Join the visitor's current session (browser requests only).
	 * @return bool  False when not configured or the request could not be queued.
	 */
	public static function send( array $events, string $client_id = '', bool $use_session = true ): bool {
		if ( ! self::is_configured() || ! $events ) {
			return false;
		}

		$session_id = $use_session ? self::cookie_session_id() : 0;
		if ( '' === $client_id ) {
			$client_id = self::cookie_client_id();
		}
		if ( '' === $client_id ) {
			$client_id  = self::one_off_client_id();
			$session_id = 0;
		}

		$out = [];
		foreach ( $events as $event ) {
			$params = (array) ( $event['params'] ?? [] );
			if ( $session_id ) {
				$params['session_id'] = $session_id;
			}
			// GA4 only shows an event in reports (and joins it to the session) with engagement time.
			$params['engagement_time_msec'] = $params['engagement_time_msec'] ?? 1;
			$out[] = [
				'name'   => sanitize_key( (string) $event['name'] ),
				'params' => $params,
			];
		}

		$url = add_query_arg(
			[
				'measurement_id' => rawurlencode( self::measurement_id() ),
				'api_secret'     => rawurlencode( trim( (string) get_option( self::OPTION_SECRET, '' ) ) ),
			],
			self::ENDPOINT
		);

		$response = wp_remote_post( $url, [
			'timeout'  => 2,
			'blocking' => false,
			'headers'  => [ 'Content-Type' => 'application/json' ],
			'body'     => wp_json_encode( [ 'client_id' => $client_id, 'events' => $out ] ),
		] );

		if ( is_wp_error( $response ) ) {
			// The message never contains the URL (and so never the secret).
			error_log( 'SCOS GA4 MP: send failed: ' . $response->get_error_code() );
			return false;
		}
		return true;
	}
}
