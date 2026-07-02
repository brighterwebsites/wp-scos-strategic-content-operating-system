/* global window, document */
(function () {
	'use strict';

	var wrap = document.getElementById('scos-sa-backfill-wrap');
	if (!wrap) { return; }

	var runBtn = document.getElementById('scos-sa-run-backfill');
	var statusEl = document.getElementById('scos-sa-backfill-status');
	var resultsEl = document.getElementById('scos-sa-backfill-results');
	var modeInputs = document.querySelectorAll('input[name="scos_sa_backfill_mode"]');
	var modePanels = document.querySelectorAll('.scos-sa-backfill-mode');

	function setStatus(msg, ok) {
		statusEl.hidden = false;
		statusEl.textContent = msg;
		statusEl.classList.remove('is-success', 'is-error');
		statusEl.classList.add(ok ? 'is-success' : 'is-error');
	}

	function activeMode() {
		for (var i = 0; i < modeInputs.length; i++) {
			if (modeInputs[i].checked) { return modeInputs[i].value; }
		}
		return 'date';
	}

	function updatePanels() {
		var mode = activeMode();
		for (var i = 0; i < modePanels.length; i++) {
			modePanels[i].style.display = (modePanels[i].getAttribute('data-mode') === mode) ? '' : 'none';
		}
	}

	function getPayload() {
		var payload = {
			secret: wrap.getAttribute('data-secret') || ''
		};
		var mode = activeMode();

		if (mode === 'posts') {
			var boxes = document.querySelectorAll('.scos-sa-backfill-post-id:checked');
			var ids = [];
			for (var i = 0; i < boxes.length; i++) {
				ids.push(parseInt(boxes[i].value, 10));
			}
			payload.post_ids = ids;
			payload.limit = Math.max(1, ids.length || 1);
		} else {
			payload.date_from = (document.getElementById('scos_sa_backfill_from') || {}).value || '';
			payload.date_to = (document.getElementById('scos_sa_backfill_to') || {}).value || '';
			payload.limit = parseInt((document.getElementById('scos_sa_backfill_limit') || {}).value || '5', 10);
		}

		var gapField = wrap.getAttribute('data-slot-gap-field');
		if (gapField) {
			var gapEl = document.getElementById(gapField);
			if (gapEl) {
				var gapVal = parseInt(gapEl.value || '0', 10);
				if (gapVal > 0) { payload.slot_gap_days = gapVal; }
			}
		}

		return payload;
	}

	function renderResults(rows) {
		if (!Array.isArray(rows) || !rows.length) {
			resultsEl.innerHTML = '<p>No posts processed.</p>';
			return;
		}
		var html = '<table class="widefat striped" style="margin-top:10px;">';
		html += '<thead><tr><th>Post</th><th>Status</th><th>Slot 1</th><th>Slot 2</th><th>Slot 3</th></tr></thead><tbody>';
		for (var i = 0; i < rows.length; i++) {
			var item = rows[i] || {};
			var slots = item.posts || [];
			var s1 = slots[0] ? (slots[0].scheduled || '-') : '-';
			var s2 = slots[1] ? (slots[1].scheduled || '-') : '-';
			var s3 = slots[2] ? (slots[2].scheduled || '-') : '-';
			var label = (item.title || ('#' + (item.post_id || '?')));
			var status = item.status || '-';
			if (item.error) {
				status += ': ' + item.error;
			}
			html += '<tr><td>' + label + '</td><td>' + status + '</td><td>' + s1 + '</td><td>' + s2 + '</td><td>' + s3 + '</td></tr>';
		}
		html += '</tbody></table>';
		resultsEl.innerHTML = html;
	}

	function runBackfill() {
		var endpoint = wrap.getAttribute('data-rest');
		if (!endpoint) {
			setStatus('Backfill endpoint URL is missing.', false);
			return;
		}
		var payload = getPayload();
		if (!payload.secret) {
			setStatus('Missing webhook secret in settings.', false);
			return;
		}

		runBtn.disabled = true;
		setStatus('Running backfill...', true);
		resultsEl.innerHTML = '';

		window.fetch(endpoint, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json'
			},
			body: JSON.stringify(payload)
		}).then(function (res) {
			return res.json().then(function (json) {
				return { ok: res.ok, json: json };
			});
		}).then(function (resp) {
			if (!resp.ok || !resp.json || !resp.json.success) {
				var err = (resp.json && (resp.json.error || (resp.json.data && resp.json.data.message))) || 'Backfill failed.';
				setStatus(err, false);
				return;
			}
			setStatus('Backfill complete. Processed ' + (resp.json.count || 0) + ' post(s).', true);
			renderResults(resp.json.data || []);
		}).catch(function () {
			setStatus('Request failed.', false);
		}).finally(function () {
			runBtn.disabled = false;
		});
	}

	for (var i = 0; i < modeInputs.length; i++) {
		modeInputs[i].addEventListener('change', updatePanels);
	}
	runBtn.addEventListener('click', runBackfill);
	updatePanels();
}());

