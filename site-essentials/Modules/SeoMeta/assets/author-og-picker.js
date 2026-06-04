/**
 * Author OG Image — media picker on user profile/edit screens.
 */
/* global jQuery, wp */
(function ($) {
	'use strict';

	var frame;

	$('#scos-author-og-select').on('click', function (e) {
		e.preventDefault();

		if (frame) {
			frame.open();
			return;
		}

		frame = wp.media({
			title:    'Select Author OG Image',
			button:   { text: 'Use this image' },
			multiple: false,
			library:  { type: 'image' },
		});

		frame.on('select', function () {
			var attachment = frame.state().get('selection').first().toJSON();
			$('#scos_author_og_image_id').val(attachment.id);
			$('#scos-author-og-select').text('Change image');

			var previewUrl = attachment.sizes && attachment.sizes.medium
				? attachment.sizes.medium.url
				: attachment.url;

			$('#scos-author-og-preview').html(
				'<img src="' + previewUrl + '" style="max-width:300px;height:auto;display:block;border:1px solid #ddd">'
			);

			if (!$('#scos-author-og-remove').length) {
				$('#scos-author-og-select').after(
					' <button type="button" class="button" id="scos-author-og-remove">Remove</button>'
				);
				bindRemove();
			}
		});

		frame.open();
	});

	function bindRemove() {
		$(document).on('click', '#scos-author-og-remove', function (e) {
			e.preventDefault();
			$('#scos_author_og_image_id').val('');
			$('#scos-author-og-preview').html('');
			$('#scos-author-og-select').text('Select image');
			$(this).remove();
		});
	}

	bindRemove();

}(jQuery));
