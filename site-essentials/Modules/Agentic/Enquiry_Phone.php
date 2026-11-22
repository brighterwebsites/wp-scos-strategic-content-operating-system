<?php
/**
 * Agentic — phone numbers in agent-sent enquiries
 *
 * v1.0 | 2026-11-23
 *
 * WHAT THIS IS FOR
 * ----------------
 * A typed form relies on the person to notice a wrong number. An agent
 * copies whatever it was told, so a missing digit reaches the business as a
 * lead nobody can call back. Phone (tel) fields are checked when an agent
 * sends an enquiry or builds the pre-filled link, and a bad number comes
 * back as a structured error the agent can fix by asking the person.
 *
 * The rules follow the business's country (scos_biz_country_code):
 *   AU    Mobiles and landlines become +61 E.164 (0412 345 678 → +61412345678).
 *         1300, 1800 and 13 numbers are kept as written, digits only.
 *         An 8-digit number without an area code is rejected.
 *         Other countries' numbers are accepted in + form.
 *   other Formatting is stripped. Anything that isn't 6–15 digits is rejected.
 *
 * Sites can replace the result with the filter scos_enquiry_phone.
 *
 * @package    SiteEssentials
 * @subpackage Modules\Agentic
 */

namespace SiteEssentials\Modules\Agentic;

defined( 'ABSPATH' ) || exit;

class Enquiry_Phone {

	/**
	 * Check and normalise one phone number.
	 *
	 * @param  string $raw     As the agent sent it.
	 * @param  string $country ISO 3166 alpha-2. Blank = the business's country.
	 * @return string|\WP_Error The number to send, or why it was rejected.
	 */
	public static function normalise( string $raw, string $country = '' ) {
		$country = strtoupper( '' !== $country ? $country : self::country() );
		$result  = 'AU' === $country ? self::normalise_au( $raw ) : self::normalise_any( $raw );

		/**
		 * Filter a checked phone number.
		 *
		 * @param string|\WP_Error $result  The number to send, or a WP_Error to reject it.
		 * @param string           $raw     As the agent sent it.
		 * @param string           $country ISO 3166 alpha-2.
		 */
		return apply_filters( 'scos_enquiry_phone', $result, $raw, $country );
	}

	/**
	 * What a good number looks like, for agents (schema and errors).
	 */
	public static function format_hint( string $country = '' ): string {
		$country = strtoupper( '' !== $country ? $country : self::country() );
		if ( 'AU' === $country ) {
			return __( 'An Australian phone number with its area code, e.g. 0412 345 678 or 03 5333 1234. Numbers outside Australia start with + and the country code.', 'site-essentials' );
		}
		return __( 'A phone number with its area code. Numbers in another country start with + and the country code.', 'site-essentials' );
	}

	public static function country(): string {
		$code = strtoupper( trim( (string) get_option( 'scos_biz_country_code', '' ) ) );
		return preg_match( '/^[A-Z]{2}$/', $code ) ? $code : '';
	}

	// ── Rules ────────────────────────────────────────────────────────────────

	private static function normalise_au( string $raw ) {
		$digits = self::strip( $raw );
		if ( is_wp_error( $digits ) ) {
			return $digits;
		}

		// 0011 is Australia's international dialling prefix.
		if ( 0 === strpos( $digits, '0011' ) ) {
			$digits = '+' . substr( $digits, 4 );
		}

		if ( '+' === $digits[0] ) {
			if ( 0 !== strpos( $digits, '+61' ) ) {
				return self::international( $digits );
			}
			$national = ltrim( substr( $digits, 3 ), '0' ); // +61 (0)412 … is common.
		} elseif ( 0 === strpos( $digits, '61' ) && 11 === strlen( $digits ) ) {
			$national = substr( $digits, 2 );
		} elseif ( preg_match( '/^1[38]00\d{6}$|^13\d{4}$/', $digits ) ) {
			return $digits;
		} elseif ( '0' === $digits[0] ) {
			$national = substr( $digits, 1 );
		} else {
			$national = $digits;
		}

		// Mobiles (4) and landlines (2, 3, 7, 8): nine digits after the 0.
		if ( preg_match( '/^[234578]\d{8}$/', $national ) ) {
			return '+61' . $national;
		}

		if ( preg_match( '/^\d{8}$/', $digits ) ) {
			return self::error( $raw, __( 'This looks like a landline without its area code. Ask the person for the area code (02, 03, 07 or 08).', 'site-essentials' ) );
		}

		return self::error( $raw, strlen( $national ) < 9
			? __( 'This number is too short for an Australian phone number, which has 10 digits starting with 0. Ask the person to check it.', 'site-essentials' )
			: __( 'This is not a valid Australian phone number. Ask the person to check it.', 'site-essentials' )
		);
	}

	private static function normalise_any( string $raw ) {
		$digits = self::strip( $raw );
		if ( is_wp_error( $digits ) ) {
			return $digits;
		}
		if ( '+' === $digits[0] ) {
			return self::international( $digits );
		}
		if ( ! preg_match( '/^\d{6,15}$/', $digits ) ) {
			return self::error( $raw, __( 'This is not a valid phone number. Ask the person to check it.', 'site-essentials' ) );
		}
		return $digits;
	}

	/** +<country code><number>: 8 to 15 digits in all (E.164). */
	private static function international( string $digits ) {
		if ( preg_match( '/^\+[1-9]\d{7,14}$/', $digits ) ) {
			return $digits;
		}
		return self::error( $digits, __( 'This is not a valid international phone number: + then the country code and number, 8 to 15 digits. Ask the person to check it.', 'site-essentials' ) );
	}

	/**
	 * Drop spaces, dashes, dots, brackets and a tel: prefix. Anything else
	 * left over (letters, extensions, notes) is rejected.
	 *
	 * @return string|\WP_Error
	 */
	private static function strip( string $raw ) {
		$digits = preg_replace( '/[\s\-\.\(\)\x{2010}-\x{2015}]+/u', '', preg_replace( '/^tel:/i', '', trim( $raw ) ) );
		if ( '' === $digits || ! preg_match( '/^\+?\d+$/', $digits ) ) {
			return self::error( $raw, __( 'A phone number can only contain digits, spaces, dashes, brackets and a leading +. Put any notes (best time to call, extension) in the message.', 'site-essentials' ) );
		}
		return $digits;
	}

	private static function error( string $raw, string $reason ): \WP_Error {
		return new \WP_Error( 'scos_enquiry_invalid_phone', $reason, [ 'value' => $raw ] );
	}
}
