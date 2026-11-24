<?php
/**
 * Agentic — Breakdance form action "SCOS: Save lead"
 *
 * v1.0 | 2026-11-24
 *
 * Ticking this action on a Breakdance form is what makes that form's
 * submissions count as leads (#269): an enquiry or quote form gets it, a
 * newsletter or job form doesn't. It uses Breakdance's documented Form
 * Actions API. Breakdance runs its honeypot, CSRF and reCAPTCHA checks and
 * field validation before any action, so spam never reaches the table.
 *
 * A submission from an agent's pre-filled link carries a hidden marker added
 * by Enquiry_Prefill (fields[scos_lead_channel]=link), so it is recorded as
 * channel link, not form.
 *
 * Agent-sent enquiries never run this action (Enquiry_Send::ACTIONS doesn't
 * list it): Enquiry_Send writes their row itself, so nothing is counted twice.
 *
 * This file is loaded only when Breakdance's Action class exists; see
 * Lead_Store::register_breakdance_action().
 *
 * @package    SiteEssentials
 * @subpackage Modules\Agentic
 */

namespace SiteEssentials\Modules\Agentic;

defined( 'ABSPATH' ) || exit;

class Lead_Form_Action extends \Breakdance\Forms\Actions\Action {

	const SLUG   = Lead_Store::BREAKDANCE_ACTION;
	const MARKER = Lead_Store::LINK_MARKER;

	/** @return string */
	public static function name() {
		return 'SCOS: Save lead';
	}

	/** @return string */
	public static function slug() {
		return self::SLUG;
	}

	/** @return bool */
	public static function proOnly() {
		return false;
	}

	/**
	 * @param array $form     Fields: type, name (label), value, advanced.id.
	 * @param array $settings The form's builder settings.
	 * @param array $extra    formId, postId, fields (as submitted), ip, referer, userAgent, userId.
	 * @return array{type: string, message: string}
	 */
	public function run( $form, $settings, $extra ) {
		try {
			$raw     = (array) ( $extra['fields'] ?? [] );
			$channel = 'link' === ( $raw[ self::MARKER ] ?? '' ) ? 'link' : 'form';

			$fields = [];
			foreach ( (array) $form as $field ) {
				$id = (string) ( $field['advanced']['id'] ?? '' );
				if ( '' === $id || self::MARKER === $id ) {
					continue;
				}
				$fields[] = [
					'name'  => $id,
					'label' => (string) ( $field['name'] ?? '' ),
					'type'  => (string) ( $field['type'] ?? 'text' ),
					'value' => $field['value'] ?? '',
				];
			}

			$ref = Lead_Store::insert( [
				'channel' => $channel,
				'status'  => 'verified', // A person pressed send.
				'source'  => 'breakdance',
				'post_id' => (int) ( $extra['postId'] ?? 0 ),
				'form_id' => (int) ( $extra['formId'] ?? 0 ),
				'fields'  => $fields,
			] );

			if ( is_wp_error( $ref ) ) {
				error_log( 'SCOS leads: form submission not saved: ' . $ref->get_error_message() );
			}
		} catch ( \Throwable $e ) {
			error_log( 'SCOS leads: form submission not saved: ' . $e->getMessage() );
		}

		// Always success: a lead the table missed must never make the visitor
		// think their enquiry failed. The other actions (email) still ran.
		return [ 'type' => 'success', 'message' => '' ];
	}
}
