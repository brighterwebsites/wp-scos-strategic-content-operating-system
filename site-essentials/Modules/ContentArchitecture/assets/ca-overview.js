/* global scosCA, jQuery */
(function ($) {
	'use strict';

	var statusCache = null;

	function bar(pct) {
		var color = pct >= 90 ? '#15803d' : pct >= 50 ? '#ca8a04' : '#dc2626';
		return (
			'<div style="display:inline-block;width:80px;background:#e5e7eb;border-radius:3px;height:8px;vertical-align:middle;overflow:hidden">' +
			'<div style="background:' + color + ';height:100%;width:' + Math.round(pct) + '%"></div>' +
			'</div> <span style="font-size:11px;color:#6b7280">' + Math.round(pct) + '%</span>'
		);
	}

	function loadStatus() {
		$.post(scosCA.ajaxUrl, { action: 'scos_analysis_status', nonce: scosCA.nonce }, function (res) {
			if (!res.success) return;
			statusCache = res.data;
			renderTable(res.data);
		});
	}

	function renderTable(data) {
		var rows = data.rows;
		var $tbody = $('#scos-analysis-rows').empty();
		var $foot  = $('#scos-analysis-foot');

		if (!rows.length) {
			$tbody.append('<tr><td colspan="6" style="text-align:center;color:#6b7280">No post types found.</td></tr>');
			return;
		}

		$.each(rows, function (i, r) {
			var pct = r.total > 0 ? (r.analyzed / r.total) * 100 : 100;
			var $btn = r.unanalyzed > 0
				? $('<button class="button button-small scos-run-type" data-type="' + r.type + '">' +
					'Run (' + r.unanalyzed + ')</button>')
				: $('<span style="color:#15803d;font-size:12px">✓ All done</span>');

			$tbody.append(
				$('<tr>').append(
					$('<td>').text(r.label + ' (' + r.type + ')'),
					$('<td style="text-align:center">').text(r.total),
					$('<td style="text-align:center">').text(r.analyzed),
					$('<td style="text-align:center;color:' + (r.unanalyzed > 0 ? '#ca8a04' : '#15803d') + ';font-weight:600">').text(r.unanalyzed || '—'),
					$('<td style="text-align:center">').html(bar(pct)),
					$('<td>').append($btn)
				)
			);
		});

		var totals = data.totals;
		var totalPct = totals.total > 0 ? (totals.analyzed / totals.total) * 100 : 100;
		$('#scos-ft-total').text(totals.total);
		$('#scos-ft-done').text(totals.analyzed);
		$('#scos-ft-pend').text(Math.max(0, totals.total - totals.analyzed) || '—');
		$('#scos-ft-bar').html(bar(totalPct));
		$foot.show();

		// Update run-all button visibility.
		var totalPending = totals.total - totals.analyzed;
		if (totalPending <= 0) {
			$('#scos-run-all').prop('disabled', true).text('✓ All posts analysed');
		} else {
			$('#scos-run-all').prop('disabled', false).text('▶ Run Analysis (' + totalPending + ' pending)');
		}
	}

	function runBatch(postType, onDone) {
		var payload = { action: 'scos_run_analysis_batch', nonce: scosCA.nonce };
		if (postType) payload.post_type = postType;

		$.post(scosCA.ajaxUrl, payload, function (res) {
			if (!res.success) {
				$('#scos-analysis-msg').text('Error: ' + (res.data || 'unknown'));
				return;
			}
			onDone(res.data.processed, res.data.remaining);
		});
	}

	function runAll(postType) {
		var $btn     = postType ? $('.scos-run-type[data-type="' + postType + '"]') : $('#scos-run-all');
		var $msg     = $('#scos-analysis-msg');
		var $bar     = $('#scos-analysis-bar');
		var $pbar    = $('#scos-analysis-progress');
		var $plabel  = $('#scos-analysis-progress-label');

		var initial  = null;
		var done     = 0;

		$btn.prop('disabled', true);
		$pbar.show();
		$msg.text('Starting…');

		function next(processed, remaining) {
			done += processed;
			if (initial === null) initial = done + remaining;

			var pct = initial > 0 ? Math.round((done / initial) * 100) : 100;
			$bar.css('width', pct + '%');
			$plabel.text(done + ' / ' + initial + ' processed');
			$msg.text(remaining + ' remaining…');

			if (remaining > 0 && processed > 0) {
				setTimeout(function () { runBatch(postType, next); }, 200);
			} else {
				$msg.text(done + ' posts analysed.');
				$btn.prop('disabled', false);
				loadStatus(); // refresh table
			}
		}

		runBatch(postType, next);
	}

	// ── Boot ────────────────────────────────────────────────────────────────

	$(function () {
		loadStatus();

		$('#scos-run-all').on('click', function () { runAll(null); });

		$(document).on('click', '.scos-run-type', function () {
			runAll($(this).data('type'));
		});

		$('#scos-force-all').on('click', function () {
			if ( ! window.confirm( 'This will clear stored analysis data and re-analyse every post from scratch. Use this to fix incorrect word counts or image counts (e.g. after Breakdance content was not being read). Continue?' ) ) {
				return;
			}
			var $btn = $(this);
			var $msg = $('#scos-analysis-msg');
			$btn.prop('disabled', true);
			$msg.text('Clearing analysis cache…');

			$.post( scosCA.ajaxUrl, {
				action: 'scos_clear_analysis_cache',
				nonce:  scosCA.clearNonce,
			} ).done( function (res) {
				if ( res.success ) {
					$msg.text('Cache cleared (' + res.data.deleted + ' records). Starting re-analysis…');
					// Re-load status so the table shows all posts as pending, then run all.
					loadStatus();
					setTimeout( function () { runAll(null); }, 600 );
				} else {
					$msg.text('Error clearing cache.');
					$btn.prop('disabled', false);
				}
			} ).fail( function () {
				$msg.text('Request failed.');
				$btn.prop('disabled', false);
			} );
		});
	});

}(jQuery));
