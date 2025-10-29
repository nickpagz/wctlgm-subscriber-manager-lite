// js/subscriber-manager-lite.js

jQuery(document).ready(function($) {
	$('.wctlgm_fetch_channel_id').on('click', function() {
		$.ajax({
			url: ajaxurl,
			method: 'POST',
			data: {
				action: 'check_and_set_channel_id',
				nonce: wctlgm_vars.nonce
			},
			success: function(response) {
				if (response.success) {
					$('input[name="wctlgm_channels[0][id]"]').val(response.data.channel_id);
					alert('ID fetched successfully.');
				} else {
					alert(response.data.message);
				}
			}
		});
	});

	$('#wctlgm_set_webhook_button').on('click', function() {
		if ($(this).is(':disabled')) {
			alert('Please save a valid bot token first.');
			return;
		}

		var $button = $(this);
		var originalText = $button.text();
		$button.prop('disabled', true).text('Setting Webhook...');

		// AJAX call to set webhook
		$.ajax({
			url: ajaxurl,
			method: 'POST',
			data: {
				action: 'wctlgm_set_webhook',
			},
			success: function(response) {
				if (response.success) {
					alert(response.data.message);
					window.location.reload();
				} else {
					alert('Error: ' + response.data.message);
					window.location.reload();
				}
			},
			error: function() {
				alert('Failed to set webhook.');
				window.location.reload();
			},
			complete: function() {
				$button.prop('disabled', false).text(originalText);
			}
		});
	});	
});
