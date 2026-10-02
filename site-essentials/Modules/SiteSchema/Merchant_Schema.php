<?php
// v1.0 | 2026-10-03

/**
 * Merchant listing schema — return policy and shipping details.
 *
 * Builds the schema.org MerchantReturnPolicy and OfferShippingDetails objects
 * that Google's merchant listing experiences read from each Offer. Values come
 * from the Site Schema › Merchant tab (scos_site_schema_merchant_* options).
 *
 * Scope is deliberately one destination and one rate: a flat rate (0 = free)
 * to the primary country, optionally free over an order threshold. Per-zone
 * WooCommerce rates are not mirrored — Merchant Center account-level shipping
 * settings override page markup where finer detail is needed.
 *
 * Consumed by Woo_Schema_Tokens::get_offers(). Public/static so WP-CLI, MCP
 * and REST callers get the same objects; settings are plain options
 * (`wp option update scos_site_schema_merchant_return_days 30`).
 *
 * @package    SiteEssentials
 * @subpackage Modules\SiteSchema
 */

namespace SiteEssentials\Modules\SiteSchema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Merchant_Schema {

	const OPTION_PREFIX = 'scos_site_schema_merchant_';

	/**
	 * Return policy categories (schema.org enumeration members).
	 *
	 * @var string[]
	 */
	const RETURN_CATEGORIES = [
		'MerchantReturnFiniteReturnWindow',
		'MerchantReturnUnlimitedWindow',
		'MerchantReturnNotPermitted',
	];

	/**
	 * @var string[]
	 */
	const RETURN_METHODS = [ 'ReturnByMail', 'ReturnInStore', 'ReturnAtKiosk' ];

	/**
	 * @var string[]
	 */
	const RETURN_FEES = [ 'FreeReturn', 'ReturnFeesCustomerResponsibility' ];

	/**
	 * Option suffix → sanitize type. Keys are stored as OPTION_PREFIX . suffix.
	 *
	 * @var array<string, string>
	 */
	const FIELDS = [
		'country'            => 'country',
		'return_category'    => 'return_category',
		'return_days'        => 'int',
		'return_method'      => 'return_method',
		'return_fees'        => 'return_fees',
		'return_url'         => 'url',
		'shipping_rate'      => 'money',
		'shipping_free_over' => 'money',
		'handling_min'       => 'int',
		'handling_max'       => 'int',
		'transit_min'        => 'int',
		'transit_max'        => 'int',
	];

	/**
	 * Full option key for a field suffix.
	 *
	 * @param string $field Suffix from FIELDS.
	 * @return string
	 */
	public static function option_key( string $field ): string {
		return self::OPTION_PREFIX . $field;
	}

	/**
	 * All merchant settings, sanitized, with the country defaulting to the
	 * WooCommerce store base country.
	 *
	 * @return array<string, string>
	 */
	public static function get_settings(): array {
		$settings = [];
		foreach ( array_keys( self::FIELDS ) as $field ) {
			$settings[ $field ] = (string) get_option( self::option_key( $field ), '' );
		}
		if ( '' === $settings['country'] ) {
			$settings['country'] = self::get_store_country();
		}
		return $settings;
	}

	/**
	 * Sanitize and save settings from an associative array (form POST, CLI, MCP).
	 *
	 * Fields absent from $input are left unchanged.
	 *
	 * @param array $input Field suffix → raw value.
	 * @return array<string, string> The saved values.
	 */
	public static function save_settings( array $input ): array {
		$saved = [];
		foreach ( self::FIELDS as $field => $type ) {
			if ( ! array_key_exists( $field, $input ) ) {
				continue;
			}
			$value = self::sanitize_value( $input[ $field ], $type );
			update_option( self::option_key( $field ), $value, false );
			$saved[ $field ] = $value;
		}
		return $saved;
	}

	/**
	 * Sanitize one value by field type. Invalid values become ''.
	 *
	 * @param mixed  $raw
	 * @param string $type
	 * @return string
	 */
	public static function sanitize_value( $raw, string $type ): string {
		$value = trim( sanitize_text_field( (string) $raw ) );
		if ( '' === $value ) {
			return '';
		}
		switch ( $type ) {
			case 'country':
				$value = strtoupper( $value );
				return preg_match( '/^[A-Z]{2}$/', $value ) ? $value : '';
			case 'int':
				return ctype_digit( $value ) ? (string) absint( $value ) : '';
			case 'money':
				return is_numeric( $value ) && (float) $value >= 0 ? number_format( (float) $value, 2, '.', '' ) : '';
			case 'url':
				return esc_url_raw( $value );
			case 'return_category':
				return in_array( $value, self::RETURN_CATEGORIES, true ) ? $value : '';
			case 'return_method':
				return in_array( $value, self::RETURN_METHODS, true ) ? $value : '';
			case 'return_fees':
				return in_array( $value, self::RETURN_FEES, true ) ? $value : '';
		}
		return '';
	}

	/**
	 * schema.org MerchantReturnPolicy, or null when no policy is configured.
	 *
	 * Finite windows without a day count are not emitted — Google rejects them.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function get_return_policy(): ?array {
		$s = self::get_settings();
		if ( '' === $s['return_category'] || '' === $s['country'] ) {
			return null;
		}

		$policy = [
			'@type'                => 'MerchantReturnPolicy',
			'@id'                  => home_url( '/#merchant-return-policy' ),
			'applicableCountry'    => $s['country'],
			'returnPolicyCategory' => 'https://schema.org/' . $s['return_category'],
		];

		if ( 'MerchantReturnFiniteReturnWindow' === $s['return_category'] ) {
			if ( '' === $s['return_days'] ) {
				return null;
			}
			$policy['merchantReturnDays'] = (int) $s['return_days'];
		}

		if ( 'MerchantReturnNotPermitted' !== $s['return_category'] ) {
			if ( '' !== $s['return_method'] ) {
				$policy['returnMethod'] = 'https://schema.org/' . $s['return_method'];
			}
			if ( '' !== $s['return_fees'] ) {
				$policy['returnFees'] = 'https://schema.org/' . $s['return_fees'];
			}
		}

		if ( '' !== $s['return_url'] ) {
			$policy['merchantReturnLink'] = $s['return_url'];
		}

		return $policy;
	}

	/**
	 * schema.org OfferShippingDetails for an offer at a given price, or null
	 * when no shipping rate is configured.
	 *
	 * The rate is 0 when the price meets the free-shipping threshold.
	 * deliveryTime is only emitted when both handling and transit maxima are set
	 * (Google requires both together); a missing minimum defaults to 0.
	 *
	 * @param float  $price    Offer price.
	 * @param string $currency ISO 4217 currency code.
	 * @return array<string, mixed>|null
	 */
	public static function get_shipping_details( float $price, string $currency ): ?array {
		$s = self::get_settings();
		if ( '' === $s['shipping_rate'] || '' === $s['country'] || '' === $currency ) {
			return null;
		}

		$rate = (float) $s['shipping_rate'];
		if ( '' !== $s['shipping_free_over'] && $price >= (float) $s['shipping_free_over'] ) {
			$rate = 0.0;
		}

		$details = [
			'@type'               => 'OfferShippingDetails',
			'shippingRate'        => [
				'@type'    => 'MonetaryAmount',
				'value'    => number_format( $rate, 2, '.', '' ),
				'currency' => $currency,
			],
			'shippingDestination' => [
				'@type'          => 'DefinedRegion',
				'addressCountry' => $s['country'],
			],
		];

		if ( '' !== $s['handling_max'] && '' !== $s['transit_max'] ) {
			$details['deliveryTime'] = [
				'@type'        => 'ShippingDeliveryTime',
				'handlingTime' => self::day_range( $s['handling_min'], $s['handling_max'] ),
				'transitTime'  => self::day_range( $s['transit_min'], $s['transit_max'] ),
			];
		}

		return $details;
	}

	/**
	 * QuantitativeValue day range; min is clamped to max.
	 *
	 * @param string $min
	 * @param string $max
	 * @return array<string, mixed>
	 */
	private static function day_range( string $min, string $max ): array {
		$max_days = (int) $max;
		return [
			'@type'    => 'QuantitativeValue',
			'minValue' => min( (int) $min, $max_days ),
			'maxValue' => $max_days,
			'unitCode' => 'DAY',
		];
	}

	/**
	 * WooCommerce store base country, or '' without WooCommerce.
	 *
	 * @return string
	 */
	public static function get_store_country(): string {
		if ( ! function_exists( 'WC' ) || ! WC()->countries ) {
			return '';
		}
		$country = WC()->countries->get_base_country();
		return is_string( $country ) ? $country : '';
	}
}
