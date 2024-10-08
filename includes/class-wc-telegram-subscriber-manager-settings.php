<?php

namespace WC_Telegram_Subscriber_Manager_Lite;

/**
 * Class WC_Telegram_Subscriber_Manager_Settings
 *
 * The main plugin class.
 *
 * @package WC_Telegram_Subscriber_Manager_Lite
 */
class WC_Telegram_Subscriber_Manager_Settings {

	/**
	 * Constructor for the settings class.
	 */
	public function __construct() {
		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_tab' ), 50 );
		add_action( 'woocommerce_settings_tabs_telegram_subscriber_manager', array( $this, 'settings_tab' ) );
		add_filter( 'woocommerce_admin_settings_sanitize_option_wctlgm_bot_url', array( $this, 'sanitize_url' ), 10, 3 );
		add_action( 'woocommerce_update_options_telegram_subscriber_manager', array( $this, 'update_settings' ) );
		add_action( 'woocommerce_admin_field_custom_button', array( $this, 'wctlgm_custom_button_html' ) );
		add_action( 'wp_ajax_wctlgm_set_webhook', array( $this, 'handle_set_webhook' ) );
		add_action( 'woocommerce_admin_field_custom_channels', array( $this, 'custom_channels_input' ) );
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'wctlgm_add_product_data_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'wctlgm_telegram_product_data_fields' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'wctlgm_save_telegram_meta_box_data' ) );
		add_action( 'wp_ajax_check_and_set_channel_id', array( $this, 'check_and_set_channel_id' ) );
	}

	/**
	 * Add a new settings tab to the WooCommerce settings tabs.
	 *
	 * @param array $settings_tabs Array of WooCommerce setting tabs & their labels, excluding the Subscription tab.
	 * @return array
	 */
	public function add_settings_tab( $settings_tabs ) {
		$settings_tabs['telegram_subscriber_manager'] = __( 'Telegram Subscriber Manager', 'wctlgm-subscriber-manager-lite' );
		return $settings_tabs;
	}

	/**
	 * Uses the WooCommerce admin fields API to output settings via the @woocommerce_admin_fields() function.
	 */
	public function settings_tab() {
		woocommerce_admin_fields( $this->get_settings() );
	}

	/**
	 * Use the WooCommerce options API to save settings via the @woocommerce_update_options() function.
	 */
	public function update_settings() {
		// Check if the nonce is set
		if ( ! isset( $_POST['wctlgm_settings_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wctlgm_settings_nonce'] ) ), 'wctlgm_update_settings' ) ) {
			return;
		}

		// Handle the bot token
		if ( isset( $_POST['wctlgm_bot_token'] ) ) {
			$bot_token = sanitize_text_field( wp_unslash( $_POST['wctlgm_bot_token'] ) );
			update_option( 'wctlgm_bot_token', $bot_token );
		}

		// Handle the bot url
		if ( isset( $_POST['wctlgm_bot_url'] ) ) {
			$bot_url = esc_url_raw( wp_unslash( $_POST['wctlgm_bot_url'] ) );
			update_option( 'wctlgm_bot_url', $bot_url );
		}

		// Handle the custom channels data
		if ( isset( $_POST['wctlgm_channel'] ) ) {
			$channel = array();
			if ( ! empty( $_POST['wctlgm_channel']['name'] ) && ! empty( $_POST['wctlgm_channel']['id'] ) ) {
				$channel = array(
					'name' => sanitize_text_field( wp_unslash( $_POST['wctlgm_channel']['name'] ) ),
					'id'   => sanitize_text_field( wp_unslash( $_POST['wctlgm_channel']['id'] ) ),
				);
			}
			update_option( 'wctlgm_channels', array( $channel ) );
		}
	}

	/**
	 * Get all the settings for this plugin for @woocommerce_admin_fields() function.
	 *
	 * @return array Array of settings for @woocommerce_admin_fields() function.
	 */
	public function get_settings() {
		$settings = array(
			'section_title' => array(
				'name' => __( 'Telegram Integration Settings', 'wctlgm-subscriber-manager-lite' ),
				'type' => 'title',
				'desc' => '',
				'id'   => 'wctlgm_settings_section_title',
			),
			'bot_token'     => array(
				'name' => __( 'Telegram Bot Token', 'wctlgm-subscriber-manager-lite' ),
				'type' => 'password',
				'desc' => __( 'Enter your Telegram bot token here.', 'wctlgm-subscriber-manager-lite' ),
				'id'   => 'wctlgm_bot_token',
			),
			array(
				'type' => 'custom_button',
				'id'   => 'wctlgm_set_webhook_button',
				'desc' => 'Set Webhook',
			),
			'bot_url'       => array(
				'name' => __( 'Telegram Bot URL', 'wctlgm-subscriber-manager-lite' ),
				'type' => 'text',
				'desc' => __( 'Enter your Telegram bot URL here.', 'wctlgm-subscriber-manager-lite' ),
				'id'   => 'wctlgm_bot_url',
			),
			'channels'      => array(
				'name' => __( 'Telegram Channel ID\'s', 'wctlgm-subscriber-manager-lite' ),
				'type' => 'custom_channels',
				'desc' => __( 'Enter the Telegram channel names and ID\'s for the plugin to manage.', 'wctlgm-subscriber-manager-lite' ),
				'id'   => 'wctlgm_channels',
			),
			'section_end'   => array(
				'type' => 'sectionend',
				'id'   => 'wctlgm_settings_section_end',
			),
		);

		return apply_filters( 'wctlgm_settings', $settings );
	}

	public function custom_channels_input() {
		// Get the saved value from the database
		$channels = get_option( 'wctlgm_channels', array() );
		$channel  = ! empty( $channels ) ? $channels[0] : array(
			'name' => '',
			'id'   => '',
		);
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="wctlgm_channel"><?php esc_html_e( 'Channel Name and ID', 'wctlgm-subscriber-manager-lite' ); ?></label>
			</th>
			<td class="forminp">
			<?php wp_nonce_field( 'wctlgm_update_settings', 'wctlgm_settings_nonce' ); ?>
				<table id="wctlgm_channel_table">
					<tbody>
						<tr>
							<td><input type="text" name="wctlgm_channel[name]" value="<?php echo esc_attr( $channel['name'] ); ?>" /></td>
							<td><input type="text" name="wctlgm_channel[id]" value="<?php echo esc_attr( $channel['id'] ); ?>" /></td>
							<td>
								<button type="button" class="button wctlgm_fetch_channel_id"><?php esc_html_e( 'Get Channel ID', 'wctlgm-subscriber-manager-lite' ); ?></button>
							</td>
						</tr>
					</tbody>
				</table>
			</td>
		</tr>
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				$('.wctlgm_fetch_channel_id').on('click', function() {
					$.ajax({
						url: ajaxurl,
						method: 'POST',
						data: {
							action: 'check_and_set_channel_id',
							nonce: '<?php echo esc_attr( wp_create_nonce( 'check_set_channel_id_nonce' ) ); ?>'
						},
						success: function(response) {
							if (response.success) {
								$('input[name="wctlgm_channel[id]"]').val(response.data.channel_id);
								alert('Channel ID fetched successfully.');
							} else {
								alert(response.data.message);
							}
						}
					});
				});
			});
		</script>
		<?php
	}

	public function wctlgm_add_product_data_tab( $tabs ) {
		$tabs['telegram'] = array(
			'label'    => __( 'Telegram Channels', 'wctlgm-subscriber-manager-lite' ),
			'target'   => 'telegram_product_data',
			'class'    => array( 'show_if_simple', 'hide_if_subscription' ),
			'priority' => 80,
		);

		return $tabs;
	}

	public function wctlgm_telegram_product_data_fields() {
		global $post;

		$saved_channels = get_post_meta( $post->ID, '_telegram_channel_ids', true );
		$saved_channels = ! empty( $saved_channels ) ? $saved_channels : array();
		$channels       = get_option( 'wctlgm_channels', array() );
		?>
		<div id='telegram_product_data' class='panel woocommerce_options_panel'>
			<div class='options_group'>
				<p class="form-field">
					<label for="telegram_channel_ids"><?php esc_html_e( 'Select Channels', 'wctlgm-subscriber-manager-lite' ); ?></label>
					<select class="wc-enhanced-select" multiple="multiple" id="telegram_channel_ids" name="telegram_channel_ids[]" style="width: 50%;">
						<?php foreach ( $channels as $channel ) : ?>
							<option value="<?php echo esc_attr( $channel['id'] ); ?>" <?php echo in_array( $channel['id'], $saved_channels, true ) ? 'selected' : ''; ?>>
								<?php echo esc_html( $channel['name'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</p>
			</div>
		</div>
		<?php
	}

	public function wctlgm_save_telegram_meta_box_data( $post_id ) {
		if ( isset( $_POST['woocommerce_meta_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ), 'woocommerce_save_data' ) ) {
			if ( isset( $_POST['telegram_channel_ids'] ) ) {
				$channel_ids = array_map( 'sanitize_text_field', wp_unslash( $_POST['telegram_channel_ids'] ) );
				update_post_meta( $post_id, '_telegram_channel_ids', $channel_ids );
			} else {
				delete_post_meta( $post_id, '_telegram_channel_ids' );
			}
		}
	}

	public function wctlgm_custom_button_html( $value ) {
		$bot_token = get_option( 'wctlgm_bot_token' );
		$disabled  = empty( $bot_token ) ? 'disabled' : '';

		printf(
			'<tr valign="top"><th scope="row" class="titledesc"><label>%s</label></th><td class="forminp"><button type="button" class="button-primary" id="%s" %s>%s</button><p class="description">%s</p></td></tr>',
			esc_html( $value['desc'] ),
			esc_attr( $value['id'] ),
			esc_attr( $disabled ),
			esc_html__( 'Set Webhook', 'wctlgm-subscriber-manager-lite' ),
			empty( $bot_token ) ? esc_html__( 'Enter a bot token and Save settings to activate button.', 'wctlgm-subscriber-manager-lite' ) : ''
		);
		?>
		<script type="text/javascript">
			jQuery('#<?php echo esc_js( $value['id'] ); ?>').on('click', function() {
				if (jQuery(this).is(':disabled')) {
					alert('Please save a valid bot token first.');
					return;
				}
				// AJAX call to set webhook
				jQuery.ajax({
					url: ajaxurl,
					method: 'POST',
					data: {
						action: 'wctlgm_set_webhook',
					},
					success: function(response) {
						if (response.success) {
							alert(response.data.message);
						} else {
							alert('Error: ' + response.data.message);
						}
					},
					error: function() {
						alert('Failed to set webhook.');
					}
				});
			});
		</script>
		<?php
	}

	public function wctlgm_generate_secret_token() {
		$allowed_chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_-';
		$token         = substr( str_shuffle( $allowed_chars ), 0, 32 );
		update_option( 'wctlgm_secret_token', $token );
		return $token;
	}

	public function handle_set_webhook() {
		$secret_token = $this->wctlgm_generate_secret_token();
		$webhook_url  = rest_url( 'wctlgm/v1/telegram-bot/' );
		$api_handler  = new \WC_Telegram_Subscriber_Manager_Lite\WC_Telegram_API_Handler();
		$result       = $api_handler->handle_set_webhook_actions( $webhook_url, $secret_token );

		if ( is_wp_error( $result ) ) {
			$error_message = $result->get_error_message();
			wp_send_json_error( array( 'message' => $error_message ) );
		} else {
			wp_send_json_success( array( 'message' => 'Webhook set successfully.' ) );
		}
	}

	public function check_and_set_channel_id() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
			return;
		}

		$channel_id = get_transient( 'channel_id_temp_store' );
		if ( $channel_id ) {
			delete_transient( 'channel_id_temp_store' );
			wp_send_json_success( array( 'channel_id' => $channel_id ) );
		} else {
			set_transient( 'telegram_fetch_channel_id_active', true, HOUR_IN_SECONDS );
			wp_send_json_error( array( 'message' => 'Please post a message in your Telegram channel and then edit it. Then click "Get Channel ID" again.' ) );
		}
	}
}
