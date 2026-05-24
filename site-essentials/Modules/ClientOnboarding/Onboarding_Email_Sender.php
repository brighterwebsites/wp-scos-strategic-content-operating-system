<?php
/**
 * Client Onboarding email sender.
 *
 * Core logic for resolving tokens, generating password-set links,
 * rendering, and dispatching the onboarding email. Interface-agnostic
 * so REST / WP-CLI / MCP tooling can call send() directly.
 *
 * v1.0 | 2026-05-22
 *
 * @package    SiteEssentials
 * @subpackage Modules\ClientOnboarding
 */

namespace SiteEssentials\Modules\ClientOnboarding;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generates and sends the onboarding email for a given user.
 */
class Onboarding_Email_Sender {

	public const OPT_SUBJECT     = 'se_onboarding_subject_template';
	public const OPT_BODY        = 'se_onboarding_html_template';
	public const OPT_EXPIRY_DAYS = 'se_onboarding_password_link_expiry_days';

	public const DEFAULT_EXPIRY_DAYS = 7;

	/**
	 * Send the onboarding email to a user.
	 *
	 * @param int    $user_id  WP user ID.
	 * @param string $context  'live' or 'test'. Test sends to the calling admin's email
	 *                         and prefixes the subject with [TEST] for safety.
	 * @return true|\WP_Error
	 */
	public function send( int $user_id, string $context = 'live' ) {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return new \WP_Error( 'se_onboarding_no_user', __( 'Selected user could not be found.', 'site-essentials' ) );
		}

		$is_test = ( 'test' === $context );

		$recipient = $is_test ? wp_get_current_user()->user_email : $user->user_email;
		if ( ! is_email( $recipient ) ) {
			return new \WP_Error( 'se_onboarding_bad_email', __( 'Recipient email is not valid.', 'site-essentials' ) );
		}

		$subject_tpl = self::get_subject_template();
		$body_tpl    = self::get_body_template();

		$subject = self::resolve_tokens( $subject_tpl, $user );
		$body    = self::resolve_tokens( $body_tpl, $user );

		if ( $is_test ) {
			$subject = '[TEST] ' . $subject;
		}

		$from_address = self::get_from_address();
		$from_name    = self::get_from_name();

		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];
		if ( $from_address && is_email( $from_address ) ) {
			$headers[] = sprintf( 'From: %s <%s>', $from_name ?: get_bloginfo( 'name' ), $from_address );
		}
		if ( $from_address && is_email( $from_address ) ) {
			$headers[] = 'Reply-To: ' . $from_address;
		}

		$sent = wp_mail( $recipient, $subject, $body, $headers );

		if ( ! $sent ) {
			error_log( '[Client Onboarding] wp_mail() returned false for user_id=' . $user_id . ' context=' . $context );
			return new \WP_Error( 'se_onboarding_send_fail', __( 'wp_mail() reported failure. Check the email delivery configuration.', 'site-essentials' ) );
		}

		do_action( 'se_onboarding_email_sent', $user_id, $context, wp_get_current_user()->ID );

		return true;
	}

	/**
	 * Render the email body HTML for preview (no email sent).
	 *
	 * @param int|null $user_id Optional. If null, uses the current admin user
	 *                          so preview shows realistic substitutions.
	 * @return string Rendered HTML.
	 */
	public function preview_html( ?int $user_id = null ): string {
		$user = $user_id ? get_user_by( 'id', $user_id ) : wp_get_current_user();
		if ( ! $user ) {
			$user = wp_get_current_user();
		}

		$body_tpl = self::get_body_template();
		return self::resolve_tokens( $body_tpl, $user );
	}

	/**
	 * Replace {token} placeholders.
	 *
	 * @param string    $template Source string with {tokens}.
	 * @param \WP_User  $user     Target user for personalisation.
	 * @return string
	 */
	public static function resolve_tokens( string $template, \WP_User $user ): string {
		$expiry_days = (int) get_option( self::OPT_EXPIRY_DAYS, self::DEFAULT_EXPIRY_DAYS );
		if ( $expiry_days < 1 ) {
			$expiry_days = self::DEFAULT_EXPIRY_DAYS;
		}

		$agency_logo_id  = (int) get_option( 'se_agency_logo', 0 );
		$agency_logo_url = $agency_logo_id ? wp_get_attachment_image_url( $agency_logo_id, 'medium' ) : '';

		$site_logo_id  = (int) get_theme_mod( 'custom_logo', 0 );
		$site_logo_url = $site_logo_id ? wp_get_attachment_image_url( $site_logo_id, 'medium' ) : '';

		$tokens = [
			'{site_name}'                 => get_bloginfo( 'name' ),
			'{site_url}'                  => home_url(),
			'{site_logo_url}'             => $site_logo_url ?: '',
			'{user_first_name}'           => $user->first_name ?: $user->display_name ?: $user->user_login,
			'{user_login}'                => $user->user_login,
			'{user_email}'                => $user->user_email,
			'{user_display_name}'         => $user->display_name,
			'{password_set_link}'         => self::get_password_set_link( $user ),
			'{password_link_expiry_days}' => (string) $expiry_days,
			'{support_page_url}'          => admin_url( 'admin.php?page=site-essentials-support' ),
			'{login_url}'                 => wp_login_url(),
			'{agency_name}'               => get_option( 'se_agency_name', '' ),
			'{agency_email}'              => get_option( 'se_agency_email', '' ),
			'{agency_phone}'              => get_option( 'se_agency_phone', '' ),
			'{agency_url}'                => get_option( 'se_agency_url', '' ),
			'{agency_logo_url}'           => $agency_logo_url ?: '',
			'{current_year}'              => date_i18n( 'Y' ),
		];

		return strtr( $template, $tokens );
	}

	/**
	 * Generate a password reset link for the user using WP's native flow.
	 *
	 * Returns an empty string if key generation fails (rare; usually a hook abort).
	 * The link expiry is governed by the password_reset_expiration filter installed
	 * in ClientOnboarding_Module.
	 *
	 * @param \WP_User $user Target user.
	 * @return string Absolute URL or '' on failure.
	 */
	public static function get_password_set_link( \WP_User $user ): string {
		$key = get_password_reset_key( $user );
		if ( is_wp_error( $key ) ) {
			error_log( '[Client Onboarding] get_password_reset_key failed for user ' . $user->ID . ': ' . $key->get_error_message() );
			return '';
		}

		return network_site_url(
			'wp-login.php?action=rp&key=' . rawurlencode( $key ) . '&login=' . rawurlencode( $user->user_login ),
			'login'
		);
	}

	/**
	 * Subject template — falls back to a sensible default.
	 *
	 * @return string
	 */
	public static function get_subject_template(): string {
		$saved = (string) get_option( self::OPT_SUBJECT, '' );
		if ( $saved !== '' ) {
			return $saved;
		}
		return 'Welcome to {site_name} — your website access is ready';
	}

	/**
	 * Body template — falls back to the default HTML in default-template.php.
	 *
	 * @return string
	 */
	public static function get_body_template(): string {
		$saved = (string) get_option( self::OPT_BODY, '' );
		if ( trim( $saved ) !== '' ) {
			return $saved;
		}
		return self::load_default_template();
	}

	/**
	 * Load the default template file.
	 *
	 * @return string
	 */
	public static function load_default_template(): string {
		$path = __DIR__ . '/default-template.php';
		if ( ! file_exists( $path ) ) {
			return '';
		}
		ob_start();
		include $path;
		return (string) ob_get_clean();
	}

	/**
	 * Resolve the From address. Prefers configured se_email_from_address,
	 * falls back to se_agency_email, then WP admin_email.
	 *
	 * @return string
	 */
	public static function get_from_address(): string {
		$candidates = [
			get_option( 'se_email_from_address', '' ),
			get_option( 'se_agency_email', '' ),
			get_bloginfo( 'admin_email' ),
		];
		foreach ( $candidates as $c ) {
			$c = (string) $c;
			if ( $c !== '' && is_email( $c ) ) {
				return $c;
			}
		}
		return '';
	}

	/**
	 * Resolve the From name. Prefers se_email_from_name, then se_agency_name,
	 * then site name.
	 *
	 * @return string
	 */
	public static function get_from_name(): string {
		$candidates = [
			get_option( 'se_email_from_name', '' ),
			get_option( 'se_agency_name', '' ),
			get_bloginfo( 'name' ),
		];
		foreach ( $candidates as $c ) {
			$c = trim( (string) $c );
			if ( $c !== '' ) {
				return $c;
			}
		}
		return '';
	}

	/**
	 * Eligible recipient roles for the user picker.
	 *
	 * @return string[]
	 */
	public static function eligible_roles(): array {
		return [ 'administrator', 'editor', 'shop_manager' ];
	}

	/**
	 * Users eligible to receive an onboarding email.
	 *
	 * @return \WP_User[]
	 */
	public static function eligible_users(): array {
		$users = get_users(
			[
				'role__in' => self::eligible_roles(),
				'orderby'  => 'display_name',
				'order'    => 'ASC',
				'number'   => 200,
			]
		);
		return is_array( $users ) ? $users : [];
	}
}
