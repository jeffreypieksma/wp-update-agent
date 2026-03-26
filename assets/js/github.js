/**
 * WP Update Agent — GitHub Token Management.
 */
(function () {
	'use strict';

	var API = wpUpdateAgent.restBase;
	var HDR = {
		'Content-Type': 'application/json',
		'X-WP-Nonce':   wpUpdateAgent.nonce,
	};

	var cardEl = document.getElementById( 'wua-github-token-card' );
	if ( ! cardEl ) return;

	/* ── Load current state ── */
	fetch( API + '/github-token', { headers: HDR } )
		.then( function ( r ) { return r.json(); } )
		.then( render )
		.catch( function () {
			cardEl.innerHTML = '<p class="wua-notice error">Could not load token status.</p>';
		} );

	/* ── Render ── */
	function render( data ) {
		var html = '';

		if ( data.has_token ) {
			var sourceLabel = data.source === 'config' ? 'wp-config.php' : 'Database (encrypted)';
			html += '<div class="wua-github-status">';
			html += '<span class="wua-status-dot wua-status-green"></span>';
			html += '<span class="wua-github-status-text">Token active</span>';
			html += '<span class="wua-github-status-masked" style="margin-left:12px;font-family:monospace;">' + esc( data.masked ) + '</span>';
			html += '<span class="wua-github-status-source" style="margin-left:12px;color:#737373;">Source: ' + esc( sourceLabel ) + '</span>';
			html += '</div>';
		} else {
			html += '<div class="wua-github-status">';
			html += '<span class="wua-status-dot wua-status-red"></span>';
			html += '<span class="wua-github-status-text" style="color:#737373;">No token configured</span>';
			html += '</div>';
		}

		html += '<div class="wua-github-form" style="margin-top:15px;">';
		html += '<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">';
		html += '<input type="password" id="wua-github-token-input" class="wua-test-input" style="max-width:400px;margin-bottom:0;" placeholder="ghp_xxxxxxxxxxxxxxxxxxxx" autocomplete="off" />';
		html += '<button type="button" class="wua-btn wua-btn-primary" id="wua-github-save-btn">Save</button>';
		if ( data.has_token && data.source === 'db' ) {
			html += '<button type="button" class="wua-btn wua-btn-danger" id="wua-github-delete-btn">Delete</button>';
		}
		html += '</div>';
		html += '<p style="margin-top:10px;color:#737373;font-size:13px;">You can also define <code>WP_UPDATE_AGENT_GITHUB_TOKEN</code> in wp-config.php as an alternative.</p>';
		html += '</div>';

		html += '<div id="wua-github-feedback" style="margin-top:10px;"></div>';

		cardEl.innerHTML = html;

		/* ── Bind events ── */
		document.getElementById( 'wua-github-save-btn' ).addEventListener( 'click', saveToken );
		var delBtn = document.getElementById( 'wua-github-delete-btn' );
		if ( delBtn ) delBtn.addEventListener( 'click', deleteToken );
	}

	/* ── Save ── */
	function saveToken() {
		var input = document.getElementById( 'wua-github-token-input' );
		var token = input.value.trim();
		if ( ! token ) {
			showFeedback( 'Please enter a token.', 'error' );
			return;
		}

		showFeedback( 'Saving\u2026', 'saving' );

		fetch( API + '/github-token', {
			method:  'POST',
			headers: HDR,
			body:    JSON.stringify( { token: token } ),
		} )
			.then( function ( r ) { return r.json(); } )
			.then( function ( data ) {
				if ( data.error ) {
					showFeedback( data.error, 'error' );
					return;
				}
				showFeedback( 'Token saved!', 'saved' );
				input.value = '';
				render( data );
			} )
			.catch( function () {
				showFeedback( 'Error saving token.', 'error' );
			} );
	}

	/* ── Delete ── */
	function deleteToken() {
		showFeedback( 'Deleting\u2026', 'saving' );

		fetch( API + '/github-token', {
			method:  'DELETE',
			headers: HDR,
		} )
			.then( function ( r ) { return r.json(); } )
			.then( function ( data ) {
				showFeedback( 'Token deleted.', 'saved' );
				render( data );
			} )
			.catch( function () {
				showFeedback( 'Error deleting token.', 'error' );
			} );
	}

	/* ── Helpers ── */
	function esc( str ) {
		var div = document.createElement( 'div' );
		div.textContent = str;
		return div.innerHTML;
	}

	function showFeedback( msg, type ) {
		var el = document.getElementById( 'wua-github-feedback' );
		if ( ! el ) return;
		if ( type === 'saving' ) {
			el.innerHTML = '<span class="dashicons dashicons-update wua-spin"></span> ' + esc( msg );
			el.style.color = '#737373';
		} else if ( type === 'saved' ) {
			el.innerHTML = '<span class="dashicons dashicons-yes-alt"></span> ' + esc( msg );
			el.style.color = '#059669';
			setTimeout( function () { el.innerHTML = ''; }, 3000 );
		} else {
			el.innerHTML = '<span class="dashicons dashicons-warning"></span> ' + esc( msg );
			el.style.color = '#dc3545';
		}
	}

})();
