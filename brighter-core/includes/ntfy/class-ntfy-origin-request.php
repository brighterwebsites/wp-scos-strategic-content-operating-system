<?php
// v1.0 | 2026-09-27
/**
 * Origin-pinned HTTP GET for ntfy monitors.
 *
 * File: class-ntfy-origin-request.php
 * Version: 1.0.0
 *
 * Public hostnames on Cloudflare resolve to the edge. A monitor running on the
 * origin then looks like a datacenter client, and Bot Fight Mode returns 403
 * even when the sitemap is valid. CURLOPT_RESOLVE keeps the real hostname for
 * SNI and the Host header, and sends the packet to the origin IP.
 *
 * @package BrighterCore
 * @subpackage Ntfy
 */

if (!defined('ABSPATH')) exit;

class Brighter_Ntfy_Origin_Request {

    /**
     * Resolve entries for the in-flight request.
     *
     * @var string[]
     */
    private static $resolve = [];

    /**
     * Whether the cURL transport applied the pin.
     *
     * @var bool
     */
    private static $pinned = false;

    /**
     * GET a URL from this machine's origin, not via Cloudflare.
     *
     * @param string $url  Absolute URL on the public hostname.
     * @param array  $args wp_remote_get arguments. sslverify defaults to true.
     * @return array|WP_Error
     */
    public static function get($url, array $args = []) {
        $ip = self::origin_ip();
        if ($ip === '') {
            return new WP_Error(
                'ntfy_origin_ip',
                'Origin IP unavailable. Refusing to request the public URL because Cloudflare would challenge this server.'
            );
        }

        $host = wp_parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return new WP_Error('ntfy_origin_url', 'Could not parse host from URL.');
        }

        $resolve_ip = (strpos($ip, ':') !== false) ? '[' . $ip . ']' : $ip;
        self::$resolve = [
            $host . ':443:' . $resolve_ip,
            $host . ':80:' . $resolve_ip,
        ];
        self::$pinned = false;

        $args = array_merge([
            'timeout'   => 10,
            'sslverify' => true,
        ], $args);

        add_action('http_api_curl', [self::class, 'pin_curl']);
        $response = wp_remote_get($url, $args);
        remove_action('http_api_curl', [self::class, 'pin_curl']);
        self::$resolve = [];

        if (!is_wp_error($response) && !self::$pinned) {
            return new WP_Error(
                'ntfy_origin_unpinned',
                'Origin pin did not apply (HTTP transport is not cURL). Refusing the public-URL response.'
            );
        }

        return $response;
    }

    /**
     * cURL hook. Named so it can be removed after the request.
     *
     * @param \CurlHandle|resource $handle
     * @return void
     */
    public static function pin_curl($handle) {
        self::$pinned = true;
        if (self::$resolve !== []) {
            curl_setopt($handle, CURLOPT_RESOLVE, self::$resolve);
        }
    }

    /**
     * IP of this server, never the public DNS name (that is Cloudflare).
     *
     * Order: NTFY_ORIGIN_IP, then SERVER_ADDR, then the machine hostname.
     *
     * @return string Empty when no usable IP was found.
     */
    public static function origin_ip() {
        if (defined('NTFY_ORIGIN_IP') && is_string(NTFY_ORIGIN_IP) && self::is_usable_ip(NTFY_ORIGIN_IP)) {
            return NTFY_ORIGIN_IP;
        }

        $addr = isset($_SERVER['SERVER_ADDR']) ? (string) $_SERVER['SERVER_ADDR'] : '';
        if (self::is_usable_ip($addr)) {
            return $addr;
        }

        $hostname = gethostname();
        if (is_string($hostname) && $hostname !== '') {
            $by_host = gethostbyname($hostname);
            if (self::is_usable_ip($by_host) && $by_host !== $hostname) {
                return $by_host;
            }
        }

        return '';
    }

    /**
     * True when the body is a Cloudflare interstitial rather than the origin response.
     *
     * @param mixed $body
     * @return bool
     */
    public static function is_cloudflare_challenge($body) {
        if (!is_string($body) || $body === '') {
            return false;
        }

        return stripos($body, 'cf-mitigated') !== false
            || stripos($body, 'Just a moment') !== false
            || stripos($body, 'cf-browser-verification') !== false;
    }

    /**
     * @param string $ip
     * @return bool
     */
    private static function is_usable_ip($ip) {
        if (!is_string($ip) || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        return !in_array($ip, ['127.0.0.1', '::1', '0.0.0.0'], true);
    }
}
