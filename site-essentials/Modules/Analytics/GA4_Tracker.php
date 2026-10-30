<?php
/**
 * GA4 Tracker
 *
 * Outputs the SCOS gtag bootstrap and enqueues the enhanced event tracking
 * script (assets/js/brighter-ga4-enhanced.js). Moved from the mu-plugins root
 * loader brighter-ga4-tracking.php v3.2.1 (#93), so tracking now runs only
 * when the Analytics module is enabled.
 *
 * - gtag.js is fetched on idle after `load` (#205). The gtag() stub and config
 *   run at once, so queued events are sent when it arrives. Never wait for
 *   user interaction: visitors who don't scroll or tap would vanish from GA4.
 * - `scos_ga4_load_immediately` skips the idle wait (#206), e.g. on the
 *   WooCommerce order-received page so `purchase` isn't lost.
 * - The bootstrap sends no events of its own (#158): clicks, scroll and
 *   downloads are left to the enhanced script and GA4 enhanced measurement.
 * - Logged-in editors are not tracked, except on a ?seedEvents=true request,
 *   where gtag loads so GA4_Seeder can send its seed events. The enhanced
 *   script still skips them.
 *
 * v1.0 | 2026-10-23
 *
 * @package    SiteEssentials
 * @subpackage Modules\Analytics
 */

namespace SiteEssentials\Modules\Analytics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GA4_Tracker {

	/** The tag carries data-no-optimize (defer_enhanced), so LiteSpeed needs no JS exclude for it. */
	const HANDLE = 'brighter-ga4-enhanced';

	/** Keep in step with the JS header — busts LiteSpeed/browser cache. */
	const SCRIPT_VERSION = '5.5.0';

	public static function init() {
		add_action( 'wp_head', [ __CLASS__, 'output_config' ], 5 );     // early: the bootstrap and enhanced script read window.brighterGA4
		add_action( 'wp_head', [ __CLASS__, 'output_bootstrap' ], 99 );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_enhanced' ], 99 );
		add_filter( 'script_loader_tag', [ __CLASS__, 'defer_enhanced' ], 10, 2 );
	}

	public static function measurement_id() {
		return (string) get_option( 'brighter_ga4_measurement_id', '' );
	}

	public static function output_config() {
		$ga4_id = self::measurement_id();
		if ( '' === $ga4_id ) {
			echo "<!-- SCOS GA4: no measurement ID configured. Set it in Site Essentials › Analytics. -->\n";
			return;
		}

		$config = [
			'measurementId' => $ga4_id,
			'loaded'        => false,
			'loadNow'       => (bool) apply_filters( 'scos_ga4_load_immediately', false ),
			'skipTracking'  => current_user_can( 'edit_posts' ),
			'seed'          => GA4_Seeder::is_seed_request(),
		];
		?>
<!-- Google Analytics 4 -->
<script data-no-optimize="1" data-cfasync="false" data-litespeed-no-optimize="1">
window.brighterGA4 = <?php echo wp_json_encode( $config ); ?>;
</script>
		<?php
	}

	public static function output_bootstrap() {
		if ( '' === self::measurement_id() ) {
			return;
		}
		?>
<script data-no-optimize="1" data-cfasync="false" data-litespeed-no-optimize="1">
(function(){'use strict';
	var cfg=window.brighterGA4;
	if(!cfg||cfg.loaded){return;}
	// Editors aren't tracked; a seed request still needs gtag for the seeder.
	if(cfg.skipTracking===true&&cfg.seed!==true){return;}
	window.dataLayer=window.dataLayer||[];
	function gtag(){dataLayer.push(arguments);}
	window.gtag=gtag;
	gtag('js',new Date());
	gtag('consent','default',{'analytics_storage':'granted','ad_storage':'denied'});
	var s=document.createElement('script');
	s.async=true;
	s.src='https://www.googletagmanager.com/gtag/js?id='+cfg.measurementId;
	// Fetch gtag.js once the page is idle after load: keeps its layout reads out of the load window.
	var inject=function(){document.head.appendChild(s);};
	var idle=function(){('requestIdleCallback' in window)?requestIdleCallback(inject,{timeout:1500}):setTimeout(inject,1500);};
	// loadNow (filter scos_ga4_load_immediately): pages whose hits can't wait, e.g. the order-received purchase.
	if(cfg.loadNow===true){inject();}
	else if(document.readyState==='complete'){idle();}else{window.addEventListener('load',idle,{once:true});}
	gtag('config',cfg.measurementId,{'send_page_view':false});
	cfg.loaded=true;
})();
</script>
		<?php
	}

	public static function enqueue_enhanced() {
		if ( '' === self::measurement_id() ) {
			return;
		}
		wp_enqueue_script(
			self::HANDLE,
			plugin_dir_url( __FILE__ ) . 'assets/js/brighter-ga4-enhanced.js',
			[],
			self::SCRIPT_VERSION,
			true
		);
	}

	/**
	 * Defer the enhanced script and keep optimisation plugins (LiteSpeed,
	 * Autoptimize, Cloudflare Rocket Loader) from combining or delaying it.
	 */
	public static function defer_enhanced( $tag, $handle ) {
		if ( self::HANDLE !== $handle ) {
			return $tag;
		}
		if ( false === strpos( $tag, 'defer' ) ) {
			$tag = str_replace( '<script', '<script defer', $tag );
		}
		if ( false === strpos( $tag, 'data-no-optimize' ) ) {
			$tag = str_replace( '<script', '<script data-no-optimize="1" data-cfasync="false"', $tag );
		}
		return $tag;
	}
}
