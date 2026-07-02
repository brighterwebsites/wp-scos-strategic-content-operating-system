/**
 * SCOS Fill Image Meta — Media Library async runner
 *
 * Drives the "Fill All Empty" and "Fill + Overwrite All" flows on upload.php.
 * Sequentially processes one parent-group at a time so the browser stays
 * responsive and a failure in one group does not abort the rest.
 *
 * v1.0 | 2026-07-01
 */
/* global ScosFillImageMeta, jQuery */
( function ( $ ) {
	'use strict';

	var cfg      = window.ScosFillImageMeta || {};
	var i18n     = cfg.i18n || {};
	var ajaxUrl  = cfg.ajaxUrl || '';
	var nonce    = cfg.nonce  || '';
	var running  = false;

	// ── DOM refs (resolved after ready) ──────────────────────────────────────

	var $panel, $runBtn, $overwriteBtn, $status, $progressWrap, $bar, $log;

	// ── Init ──────────────────────────────────────────────────────────────────

	$( document ).ready( function () {
		$panel        = $( '#scos-fim-panel' );
		$runBtn       = $( '#scos-fim-run' );
		$overwriteBtn = $( '#scos-fim-run-overwrite' );
		$status       = $( '#scos-fim-status' );
		$progressWrap = $( '#scos-fim-progress-wrap' );
		$bar          = $( '#scos-fim-progress-bar' );
		$log          = $( '#scos-fim-log' );

		if ( ! $panel.length ) {
			return;
		}

		// Show the panel.
		$panel.show();

		if ( ! cfg.hasAi ) {
			$runBtn.prop( 'disabled', true );
			$overwriteBtn.prop( 'disabled', true );
			$status.text( i18n.noAiPlugin || 'WordPress AI plugin required.' );
			return;
		}

		$runBtn.on( 'click', function () {
			startRun( false );
		} );

		$overwriteBtn.on( 'click', function () {
			startRun( true );
		} );
	} );

	// ── Main flow ─────────────────────────────────────────────────────────────

	function startRun( overwrite ) {
		if ( running ) {
			return;
		}

		running = true;
		setButtonsDisabled( true );
		$progressWrap.show();
		$log.empty();
		setProgress( 0 );
		$status.text( i18n.running || 'Running…' );

		$.post( ajaxUrl, {
			action   : 'scos_fill_image_meta_get_ids',
			nonce    : nonce,
			overwrite: overwrite ? 1 : 0,
		} )
		.done( function ( res ) {
			if ( ! res.success || ! res.data ) {
				finishRun( i18n.noImages || 'No images need updating.' );
				return;
			}

			var groups = res.data.groups || [];
			if ( ! groups.length ) {
				finishRun( i18n.noImages || 'No images need updating.' );
				return;
			}

			processGroups( groups, overwrite, 0, { processed: 0, skipped: 0, errors: 0 }, groups.length );
		} )
		.fail( function () {
			finishRun( 'AJAX error fetching image IDs.' );
		} );
	}

	/**
	 * Recursively process one group at a time.
	 *
	 * @param {Array}  groups    Full list of parent groups.
	 * @param {boolean} overwrite
	 * @param {number} index     Current group index.
	 * @param {Object} totals    Accumulated { processed, skipped, errors }.
	 * @param {number} total     Total number of groups.
	 */
	function processGroups( groups, overwrite, index, totals, total ) {
		if ( index >= groups.length ) {
			var summary = buildSummary( totals );
			finishRun( summary );
			return;
		}

		var group  = groups[ index ];
		var pct    = Math.round( ( index / total ) * 100 );
		setProgress( pct );
		$status.text( i18n.running + ' (' + ( index + 1 ) + '/' + total + ')' );

		$.post( ajaxUrl, {
			action          : 'scos_fill_image_meta_run_batch',
			nonce           : nonce,
			parent_post_id  : group.parent_post_id || 0,
			attachment_ids  : JSON.stringify( group.attachment_ids || [] ),
			overwrite       : overwrite ? 1 : 0,
		} )
		.done( function ( res ) {
			if ( res.success && res.data ) {
				totals.processed += res.data.processed || 0;
				totals.skipped   += res.data.skipped   || 0;
				totals.errors    += res.data.errors    || 0;
				appendLog( buildGroupLog( group, res.data ) );
			} else {
				var errMsg = res.data ? res.data.message : 'Unknown error';
				appendLog( '\u2715 Group (parent ' + group.parent_post_id + '): ' + errMsg );
				totals.errors += ( group.attachment_ids || [] ).length;
			}
		} )
		.fail( function () {
			appendLog( '\u2715 Group (parent ' + group.parent_post_id + '): AJAX error.' );
			totals.errors += ( group.attachment_ids || [] ).length;
		} )
		.always( function () {
			// Next group on next tick to keep the browser unblocked.
			setTimeout( function () {
				processGroups( groups, overwrite, index + 1, totals, total );
			}, 50 );
		} );
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	function setProgress( pct ) {
		$bar.css( 'width', pct + '%' );
	}

	function setButtonsDisabled( state ) {
		$runBtn.prop( 'disabled', state );
		$overwriteBtn.prop( 'disabled', state );
	}

	function appendLog( msg ) {
		$log.append( $( '<div>' ).text( msg ) );
		$log.scrollTop( $log[0].scrollHeight );
	}

	function buildGroupLog( group, data ) {
		var parentLabel = group.parent_post_id > 0 ? 'post #' + group.parent_post_id : 'unattached';
		return '\u2713 ' + parentLabel + ': '
			+ ( data.processed || 0 ) + ' updated, '
			+ ( data.skipped   || 0 ) + ' skipped, '
			+ ( data.errors    || 0 ) + ' errors';
	}

	function buildSummary( totals ) {
		return ( i18n.done || 'Complete' ) + ' \u2014 '
			+ ( i18n.processed || 'Updated' ) + ': ' + totals.processed + ', '
			+ ( i18n.skipped   || 'Skipped' ) + ': ' + totals.skipped   + ', '
			+ ( i18n.errors    || 'Errors'  ) + ': ' + totals.errors;
	}

	function finishRun( message ) {
		running = false;
		setButtonsDisabled( false );
		setProgress( 100 );
		$status.text( message );
	}

} )( jQuery );
