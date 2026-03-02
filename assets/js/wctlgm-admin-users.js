/**
 * Subscriber Manager - Admin Users Table JavaScript
 *
 * Handles tab switching, user details modal, and subscriber actions.
 *
 * @since 2.0.0
 */
jQuery( document ).ready( function( $ ) {

	// ── Tab Switching ─────────────────────────────────────────

	var $tabs     = $( '.wctlgm-content-tab' );
	var $navTabs  = $( '.nav-tab-wrapper .wctlgm-tab' );

	function switchTab( hash ) {
		var tabId = hash || '#settings';

		$navTabs.removeClass( 'nav-tab-active' );
		$navTabs.filter( '[href="' + tabId + '"]' ).addClass( 'nav-tab-active' );

		$tabs.removeClass( 'wctlgm-tab-active' );
		$( tabId + '-content' ).addClass( 'wctlgm-tab-active' );

		if ( history.replaceState ) {
			history.replaceState( null, null, tabId );
		}
	}

	$navTabs.on( 'click', function( e ) {
		e.preventDefault();
		switchTab( $( this ).attr( 'href' ) );
	});

	// Initialize from URL hash or search/filter params.
	var params = new URLSearchParams( window.location.search );
	if ( window.location.hash && $( window.location.hash + '-content' ).length ) {
		switchTab( window.location.hash );
	} else if ( params.get( 's' ) || params.get( 'channel_filter' ) || params.get( 'orderby' ) || params.get( 'paged' ) ) {
		switchTab( '#subscribers' );
	} else {
		switchTab( '#settings' );
	}

	// Append #subscribers hash to pagination links so tab persists on page change.
	$( '#subscribers-content' ).find( '.tablenav-pages a' ).each( function() {
		var href = $( this ).attr( 'href' );
		if ( href && href.indexOf( '#' ) === -1 ) {
			$( this ).attr( 'href', href + '#subscribers' );
		}
	});

	// Ensure the search/filter form submits with the hash.
	$( '#wctlgm-subscriber-table-wrap form' ).on( 'submit', function() {
		var $form   = $( this );
		var action  = $form.attr( 'action' ) || window.location.pathname + window.location.search;
		if ( action.indexOf( '#' ) === -1 ) {
			$form.attr( 'action', action + '#subscribers' );
		}
	});

	// ── Modal ─────────────────────────────────────────────────

	var $overlay = $( '#wctlgm-subscriber-modal' );
	var $body    = $( '#wctlgm-modal-body' );

	function openModal( telegramId ) {
		$body.html( '<p><span class="wctlgm-spinner"></span> ' + wctlgm_users_vars.i18n.loading + '</p>' );
		$overlay.addClass( 'wctlgm-modal-visible' );

		$.ajax({
			url:    ajaxurl,
			method: 'POST',
			data:   {
				action:      'wctlgm_get_user_details',
				nonce:       wctlgm_users_vars.nonce,
				telegram_id: telegramId
			},
			success: function( response ) {
				if ( response.success ) {
					renderModal( response.data );
				} else {
					$body.html( '<p>' + ( response.data.message || wctlgm_users_vars.i18n.error ) + '</p>' );
				}
			},
			error: function() {
				$body.html( '<p>' + wctlgm_users_vars.i18n.error + '</p>' );
			}
		});
	}

	function renderModal( data ) {
		var html = '';

		// User details.
		html += '<div class="wctlgm-detail-row"><span class="wctlgm-detail-label">' + wctlgm_users_vars.i18n.username + '</span><span class="wctlgm-detail-value">' + escHtml( data.username || '—' ) + '</span></div>';
		html += '<div class="wctlgm-detail-row"><span class="wctlgm-detail-label">' + wctlgm_users_vars.i18n.name + '</span><span class="wctlgm-detail-value">' + escHtml( data.name || '—' ) + '</span></div>';
		html += '<div class="wctlgm-detail-row"><span class="wctlgm-detail-label">' + wctlgm_users_vars.i18n.telegram_id + '</span><span class="wctlgm-detail-value">' + escHtml( data.telegram_id ) + '</span></div>';

		// Orders.
		if ( data.orders && data.orders.length ) {
			var orderLinks = [];
			for ( var i = 0; i < data.orders.length; i++ ) {
				var o = data.orders[i];
				if ( o.edit_url ) {
					orderLinks.push( '<a href="' + escAttr( o.edit_url ) + '">#' + o.id + '</a> (' + escHtml( o.status ) + ')' );
				} else {
					orderLinks.push( '#' + o.id + ' (' + escHtml( o.status ) + ')' );
				}
			}
			html += '<div class="wctlgm-detail-row"><span class="wctlgm-detail-label">' + wctlgm_users_vars.i18n.orders + '</span><span class="wctlgm-detail-value">' + orderLinks.join( ', ' ) + '</span></div>';
		} else {
			html += '<div class="wctlgm-detail-row"><span class="wctlgm-detail-label">' + wctlgm_users_vars.i18n.orders + '</span><span class="wctlgm-detail-value"><em>' + wctlgm_users_vars.i18n.external + '</em></span></div>';
		}

		// Channels.
		if ( data.channels && data.channels.length ) {
			html += '<div class="wctlgm-modal-channels-header">' + wctlgm_users_vars.i18n.channel_access + '</div>';
			for ( var c = 0; c < data.channels.length; c++ ) {
				var ch = data.channels[c];
				html += '<div class="wctlgm-channel-row">';
				html += '<div class="wctlgm-channel-info">';
				html += '<span class="wctlgm-channel-badge">' + escHtml( ch.name ) + '</span>';
				html += '<span class="wctlgm-status-badge wctlgm-status-' + escAttr( ch.status ) + '">' + escHtml( ch.status_label ) + '</span>';
				html += '</div>';
				html += '<div class="wctlgm-channel-actions">';

				if ( ch.status === 'pending' ) {
					html += '<button class="button wctlgm-action-revoke" data-channel-id="' + escAttr( ch.channel_id ) + '" data-telegram-id="' + escAttr( data.telegram_id ) + '">' + wctlgm_users_vars.i18n.revoke_invite + '</button>';
				} else {
					// Show Remove/Unban button: "Unban" for banned users, "Remove" for all others.
					var removeLabel = ( ch.status === 'banned' ) ? wctlgm_users_vars.i18n.unban : wctlgm_users_vars.i18n.remove;
					html += '<button class="button wctlgm-action-remove" data-channel-id="' + escAttr( ch.channel_id ) + '" data-telegram-id="' + escAttr( data.telegram_id ) + '">' + removeLabel + '</button>';
					// Show Ban button for non-banned users.
					if ( ch.status !== 'banned' ) {
						html += '<button class="button button-link-delete wctlgm-action-ban" data-channel-id="' + escAttr( ch.channel_id ) + '" data-telegram-id="' + escAttr( data.telegram_id ) + '">' + wctlgm_users_vars.i18n.ban + '</button>';
					}
				}

				html += '</div>';
				html += '</div>';
			}
		}

		$body.html( html );
	}

	// Close modal.
	$overlay.on( 'click', '.wctlgm-modal-close', function() {
		$overlay.removeClass( 'wctlgm-modal-visible' );
	});
	$overlay.on( 'click', function( e ) {
		if ( $( e.target ).hasClass( 'wctlgm-modal-overlay' ) ) {
			$overlay.removeClass( 'wctlgm-modal-visible' );
		}
	});
	$( document ).on( 'keydown', function( e ) {
		if ( 27 === e.keyCode ) {
			$overlay.removeClass( 'wctlgm-modal-visible' );
		}
	});

	// Open modal on "View Details" click.
	$( document ).on( 'click', '.wctlgm-view-user', function( e ) {
		e.preventDefault();
		var telegramId = $( this ).data( 'telegram-id' );
		openModal( telegramId );
	});

	// ── Actions ───────────────────────────────────────────────

	function executeAction( action, telegramId, channelId ) {
		$.ajax({
			url:    ajaxurl,
			method: 'POST',
			data:   {
				action:      'wctlgm_subscriber_action',
				nonce:       wctlgm_users_vars.nonce,
				sub_action:  action,
				telegram_id: telegramId,
				channel_id:  channelId
			},
			beforeSend: function() {
				$body.find( '.wctlgm-channel-actions .button' ).prop( 'disabled', true );
			},
			success: function( response ) {
				if ( response.success ) {
					// Refresh modal.
					openModal( telegramId );
				} else {
					alert( response.data.message || wctlgm_users_vars.i18n.error );
					$body.find( '.wctlgm-channel-actions .button' ).prop( 'disabled', false );
				}
			},
			error: function() {
				alert( wctlgm_users_vars.i18n.error );
				$body.find( '.wctlgm-channel-actions .button' ).prop( 'disabled', false );
			}
		});
	}

	$( document ).on( 'click', '.wctlgm-action-remove', function() {
		var channelId  = $( this ).data( 'channel-id' );
		var telegramId = $( this ).data( 'telegram-id' );
		var isUnban    = $( this ).text() === wctlgm_users_vars.i18n.unban;
		var message    = isUnban ? wctlgm_users_vars.i18n.confirm_unban : wctlgm_users_vars.i18n.confirm_remove;
		if ( ! confirm( message ) ) {
			return;
		}
		executeAction( 'remove', telegramId, channelId );
	});

	$( document ).on( 'click', '.wctlgm-action-ban', function() {
		var channelId  = $( this ).data( 'channel-id' );
		var telegramId = $( this ).data( 'telegram-id' );
		if ( ! confirm( wctlgm_users_vars.i18n.confirm_ban ) ) {
			return;
		}
		executeAction( 'ban', telegramId, channelId );
	});

	$( document ).on( 'click', '.wctlgm-action-revoke', function() {
		var channelId  = $( this ).data( 'channel-id' );
		var telegramId = $( this ).data( 'telegram-id' );
		if ( ! confirm( wctlgm_users_vars.i18n.confirm_revoke ) ) {
			return;
		}
		executeAction( 'revoke', telegramId, channelId );
	});

	// ── Pending Invite Revoke ─────────────────────────────────

	$( document ).on( 'click', '.wctlgm-pending-revoke', function() {
		var $btn      = $( this );
		var recordId  = $btn.data( 'record-id' );
		var channelId = $btn.data( 'channel-id' );

		if ( ! confirm( wctlgm_users_vars.i18n.confirm_revoke ) ) {
			return;
		}

		$btn.prop( 'disabled', true ).text( wctlgm_users_vars.i18n.loading );

		$.ajax({
			url:    ajaxurl,
			method: 'POST',
			data:   {
				action:     'wctlgm_subscriber_action',
				nonce:      wctlgm_users_vars.nonce,
				sub_action: 'revoke',
				record_id:  recordId,
				channel_id: channelId
			},
			success: function( response ) {
				if ( response.success ) {
					$btn.closest( 'tr' ).fadeOut( 300, function() {
						$( this ).remove();
						var $section   = $( '#wctlgm-pending-invites-section' );
						var remaining  = $section.find( 'tbody tr' ).length;
						if ( 0 === remaining ) {
							$section.fadeOut( 300 );
						} else {
							$section.find( 'h3' ).text(
								$section.find( 'h3' ).text().replace( /\(\d+\)/, '(' + remaining + ')' )
							);
						}
					});
				} else {
					alert( response.data.message || wctlgm_users_vars.i18n.error );
					$btn.prop( 'disabled', false ).text( wctlgm_users_vars.i18n.revoke_invite );
				}
			},
			error: function() {
				alert( wctlgm_users_vars.i18n.error );
				$btn.prop( 'disabled', false ).text( wctlgm_users_vars.i18n.revoke_invite );
			}
		});
	});

	// ── Sync Status ──────────────────────────────────────────

	$( document ).on( 'click', '.wctlgm-sync-user', function( e ) {
		e.preventDefault();
		var $link      = $( this );
		var telegramId = $link.data( 'telegram-id' );
		var $row       = $link.closest( 'tr' );

		$link.text( wctlgm_users_vars.i18n.syncing );
		$row.css( 'opacity', '0.5' );

		$.ajax({
			url:    ajaxurl,
			method: 'POST',
			data:   {
				action:      'wctlgm_sync_user_status',
				nonce:       wctlgm_users_vars.nonce,
				telegram_id: telegramId
			},
			success: function( response ) {
				if ( response.success ) {
					// Reload page to show updated statuses.
					window.location.reload();
				} else {
					alert( response.data.message || wctlgm_users_vars.i18n.sync_error );
					$link.text( wctlgm_users_vars.i18n.sync_status );
					$row.css( 'opacity', '1' );
				}
			},
			error: function() {
				alert( wctlgm_users_vars.i18n.sync_error );
				$link.text( wctlgm_users_vars.i18n.sync_status );
				$row.css( 'opacity', '1' );
			}
		});
	});

	// ── Helpers ───────────────────────────────────────────────

	function escHtml( str ) {
		if ( ! str ) return '';
		var div = document.createElement( 'div' );
		div.appendChild( document.createTextNode( str ) );
		return div.innerHTML;
	}

	function escAttr( str ) {
		if ( ! str ) return '';
		return String( str ).replace( /&/g, '&amp;' ).replace( /"/g, '&quot;' ).replace( /'/g, '&#39;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' );
	}

});
