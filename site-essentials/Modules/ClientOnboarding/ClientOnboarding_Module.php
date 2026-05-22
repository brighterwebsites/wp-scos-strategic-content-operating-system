<?php
/**
 * Client Onboarding module bootstrap.
 *
 * Installs the password_reset_expiration filter so that links generated
 * via the onboarding flow honour the configured expiry window (default 7 days).
 *
 * NOTE: This filter affects ALL password reset links generated on the site,
 * not just onboarding ones. That's intentional — clients get more time to
 * complete the flow whether they were sent it as an onboarding email or
 * via the normal lost-password page. To narrow it to onboarding only, key
 * the filter on a transient set right before generating the key (future work).
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
 * Lightweight bootstrap. No admin UI of its own — the form is mounted
 * inside the Agency admin page as a tab. See Views/agency-onboarding-tab.php
 * and Core/Admin_UI::render_agency_page().
 */
class ClientOnboarding_Module {

	/**
	 * Wire global filters / actions.
	 *
	 * @return void
	 */
	public function boot(): void {
		add_filter( 'password_reset_expiration', [ $this, 'filter_password_reset_expiration' ] );
	}

	/**
	 * Extend the password reset key lifetime to the configured number of days.
	 *
	 * Default: 7 days. Configurable via se_onboarding_password_link_expiry_days.
	 *
	 * @param int $seconds Default seconds (WP default = DAY_IN_SECONDS).
	 * @return int
	 */
	public function filter_password_reset_expiration( $seconds ): int {
		$days = (int) get_option(
			Onboarding_Email_Sender::OPT_EXPIRY_DAYS,
			Onboarding_Email_Sender::DEFAULT_EXPIRY_DAYS
		);
		if ( $days < 1 ) {
			$days = Onboarding_Email_Sender::DEFAULT_EXPIRY_DAYS;
		}
		if ( $days > 30 ) {
			$days = 30; // sanity cap
		}
		return $days * DAY_IN_SECONDS;
	}
}
