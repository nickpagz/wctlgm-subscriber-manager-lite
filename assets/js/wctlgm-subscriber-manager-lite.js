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

	// Only allow Telegram tab on simple and variable product types
	var allowedTypes = ['simple', 'variable'];
	var variableTypes = (typeof wctlgm_vars !== 'undefined' && wctlgm_vars.variable_product_types) || [];

	function toggleTelegramTab() {
		var productType = $('#product-type').val();
		var $tab = $('li.telegram_options');

		if (allowedTypes.indexOf(productType) === -1) {
			$tab.hide();
		} else {
			$tab.show();
		}
	}

	function toggleTelegramFields() {
		var productType = $('#product-type').val();
		var isVariable = variableTypes.indexOf(productType) !== -1;

		if (isVariable) {
			$('.wctlgm-variable-message').show();
			$('.wctlgm-standard-fields').hide();
			$('.wctlgm-variation-fields').show();
		} else {
			$('.wctlgm-variable-message').hide();
			$('.wctlgm-standard-fields').show();
			$('.wctlgm-variation-fields').hide();
		}
	}

	function initVariationSelects() {
		$('.wctlgm-variation-channel-select').each(function() {
			if (!$(this).hasClass('select2-hidden-accessible')) {
				if ($.fn.selectWoo) {
					$(this).selectWoo();
				} else if ($.fn.select2) {
					$(this).select2();
				}
			}
		});
		// Hide variation Telegram fields if product type is not variable.
		toggleTelegramFields();
	}

	// Run on page load
	toggleTelegramTab();
	toggleTelegramFields();

	// Run when product type changes
	$('#product-type').on('change', function() {
		toggleTelegramTab();
		toggleTelegramFields();
	});

	// Initialize when variations are loaded via AJAX
	$('#woocommerce-product-data').on('woocommerce_variations_loaded', initVariationSelects);

	// Initialize when a new variation is added
	$('#variable_product_options').on('woocommerce_variations_added', initVariationSelects);

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
				nonce: wctlgm_vars.webhook_nonce
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
