/**
 * Social Amplification Meta Box — JavaScript
 * Handles the Create Social Post button — calls the existing bw_trigger_social_webhook AJAX action.
 */
/* global scosSA, jQuery */
(function ($) {
	'use strict';

	$(document).on('click', '#scos-sa-trigger-btn', function (e) {
		e.preventDefault();

		var $btn    = $(this);
		var $status = $('#scos-sa-status-msg');
		var postId  = $btn.data('post-id');

		if ($btn.prop('disabled')) { return; }

		$btn.prop('disabled', true)
			.find('span.dashicons').removeClass('dashicons-megaphone').addClass('dashicons-update');

		$status.removeAttr('hidden').removeClass('is-success is-error').text(scosSA.i18n.sending);

		$.post(
			scosSA.ajaxurl,
			{
				action:   'bw_trigger_social_webhook',
				post_id:  postId,
				nonce:    scosSA.nonce,
			},
			function (resp) {
				if (resp.success) {
					$status.addClass('is-success').text(resp.data.message || scosSA.i18n.sent);

					// Update "last sent" text inline
					var $last = $btn.closest('.scos-sa-wrap').find('.scos-sa-last-trigger');
					if ($last.length) {
						$last.text('Last sent: just now');
					} else {
						$('<p class="scos-sa-last-trigger">Last sent: just now</p>').insertAfter($status);
					}
				} else {
					$status.addClass('is-error').text(
						(scosSA.i18n.error + ': ') + (resp.data && resp.data.message ? resp.data.message : 'Unknown error')
					);
				}
			}
		).fail(function () {
			$status.addClass('is-error').text(scosSA.i18n.error + ': Request failed');
		}).always(function () {
			$btn.prop('disabled', false)
				.find('span.dashicons').removeClass('dashicons-update').addClass('dashicons-megaphone');
		});
	});

}(jQuery));
