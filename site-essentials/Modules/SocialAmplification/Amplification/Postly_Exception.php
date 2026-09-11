<?php
/**
 * Postly API failure with retry metadata.
 *
 * Extends RuntimeException so every existing `catch ( \RuntimeException $e )`
 * keeps working. Callers that care can read whether the failure is worth
 * retrying, how long Postly asked us to wait, and whether the request may
 * have been processed before it failed (so a retry could duplicate the post).
 *
 * @package    SiteEssentials
 * @subpackage Modules\SocialAmplification\Amplification
 * v1.0 | 2026-09-11
 */

namespace SiteEssentials\Modules\SocialAmplification\Amplification;

defined( 'ABSPATH' ) || exit;

class Postly_Exception extends \RuntimeException {

	/** @var int HTTP status, or 0 when no response came back (timeout, DNS, TLS). */
	private int $http_status;

	/** @var bool Temporary failure — the same request may succeed later. */
	private bool $retryable;

	/** @var int Seconds to wait before retrying (Postly's own figure for 429s). */
	private int $retry_after;

	/** @var bool Postly may have created the post before the failure (timeout, 502, 504). */
	private bool $uncertain;

	public function __construct(
		string $message,
		int $http_status = 0,
		bool $retryable = false,
		int $retry_after = 0,
		bool $uncertain = false,
		?\Throwable $previous = null
	) {
		parent::__construct( $message, $http_status, $previous );
		$this->http_status = $http_status;
		$this->retryable   = $retryable;
		$this->retry_after = max( 0, $retry_after );
		$this->uncertain   = $uncertain;
	}

	public function http_status(): int {
		return $this->http_status;
	}

	public function is_retryable(): bool {
		return $this->retryable;
	}

	public function retry_after(): int {
		return $this->retry_after;
	}

	public function is_uncertain(): bool {
		return $this->uncertain;
	}
}
