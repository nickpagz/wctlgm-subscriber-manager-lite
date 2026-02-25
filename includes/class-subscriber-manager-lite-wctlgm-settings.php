<?php

namespace Subscriber_Manager_Lite_for_Telegram;

/**
 * Class Subscriber_Manager_Lite_WCTLGM_Settings
 *
 * The main plugin class.
 *
 * @package Subscriber_Manager_Lite_for_Telegram
 */
class Subscriber_Manager_Lite_WCTLGM_Settings {

	private $logger;

	/**
	 * Constructor for the settings class.
	 */
	public function __construct() {
		$this->logger = new \Subscriber_Manager_Lite_for_Telegram\Subscriber_Manager_Lite_WCTLGM_Logger();
		add_action( 'wp_ajax_wctlgm_set_webhook', array( $this, 'handle_set_webhook' ) );
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'wctlgm_add_product_data_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'wctlgm_telegram_product_data_fields' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'wctlgm_save_telegram_meta_box_data' ) );
		add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'wctlgm_variation_telegram_fields' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( $this, 'wctlgm_save_variation_telegram_data' ), 10, 2 );
		add_action( 'wp_ajax_check_and_set_channel_id', array( $this, 'check_and_set_channel_id' ) );
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_subscriber_manager_scripts' ) );
		add_action( 'init', array( $this, 'migrate_activation_flow_setting' ) );
		add_action( 'admin_notices', array( $this, 'display_webhook_warning_notice' ) );
		add_action( 'update_option_wctlgm_require_activation_flow', array( $this, 'handle_activation_flow_change' ), 10, 2 );
	}

	/**
	 * Migrate activation flow setting from legacy option.
	 * Runs once on plugin initialization to ensure all users get migrated.
	 */
	public function migrate_activation_flow_setting() {
		if ( get_option( 'wctlgm_activation_flow_migrated', false ) ) {
			return;
		}

		$legacy_activation  = get_option( 'wctlgm_force_activation_flow', false );
		$require_activation = get_option( 'wctlgm_require_activation_flow', false );

		// Only migrate if new option doesn't exist (hasn't been set by user)
		// and legacy option exists
		if ( false === $require_activation && false !== $legacy_activation ) {
			update_option( 'wctlgm_require_activation_flow', $legacy_activation );
			delete_option( 'wctlgm_force_activation_flow' );
		}

		update_option( 'wctlgm_activation_flow_migrated', true );
	}

	/**
	 * Enqueue the JavaScript for the settings page.
	 *
	 * @return void
	 */
	public function enqueue_subscriber_manager_scripts( $hook ) {
		$screen = get_current_screen();

		// Only enqueue on plugin settings page and WooCommerce product screens.
		$is_settings_page = ( 'settings_page_wctlgm-subscriber-manager-lite' === $hook );
		$is_product_page  = ( $screen && 'product' === $screen->id );

		if ( ! $is_settings_page && ! $is_product_page ) {
			return;
		}

		$deps = array( 'jquery' );
		if ( wp_script_is( 'wc-enhanced-select', 'registered' ) ) {
			$deps[] = 'wc-enhanced-select';
		}

		wp_enqueue_script(
			'subscriber-manager-lite-js',
			plugin_dir_url( __FILE__ ) . '../assets/js/wctlgm-subscriber-manager-lite.js',
			$deps,
			filemtime( plugin_dir_path( __FILE__ ) . '../assets/js/wctlgm-subscriber-manager-lite.js' ),
			true
		);

		wp_localize_script(
			'subscriber-manager-lite-js',
			'wctlgm_vars',
			array(
				'nonce'                  => wp_create_nonce( 'check_set_channel_id_nonce' ),
				'variable_product_types' => array( 'variable' ),
			)
		);
	}

	public function custom_channels_input() {
		$channels = get_option( 'wctlgm_channels', array() );

		$channel = array(
			'name' => isset( $channels[0]['name'] ) ? $channels[0]['name'] : '',
			'id'   => isset( $channels[0]['id'] ) ? $channels[0]['id'] : '',
		);
		?>
		<tr valign="top">
			<td class="forminp" colspan="2" style="padding-top: 0;">
				<?php wp_nonce_field( 'wctlgm_update_settings', 'wctlgm_settings_nonce' ); ?>
				<table id="wctlgm_channel_table">
					<tbody>
						<tr>
							<td style="padding-top: 0;">
								<input type="text" name="wctlgm_channels[0][name]" value="<?php echo esc_attr( $channel['name'] ); ?>" />
								<p class="description"><?php esc_html_e( 'Channel/Group Name', 'wctlgm-subscriber-manager-lite' ); ?></p>
							</td>
							<td style="padding-top: 0;">
								<input type="text" name="wctlgm_channels[0][id]" value="<?php echo esc_attr( $channel['id'] ); ?>" />
								<p class="description"><?php esc_html_e( 'Channel/Group ID', 'wctlgm-subscriber-manager-lite' ); ?></p>
							</td>
							<td style="vertical-align: top; padding-top: 0;">
								<button type="button" class="button wctlgm_fetch_channel_id"><?php esc_html_e( 'Get ID', 'wctlgm-subscriber-manager-lite' ); ?></button>
							</td>
						</tr>
					</tbody>
				</table>
				<?php
				echo wp_kses_post( sprintf( '<em>Need to add multiple channels or groups? <a href="%s">Upgrade to Pro Now!</a></em>', wctlgm_fs()->get_upgrade_url() ) );
				echo '</section>';
				?>
			</td>
		</tr>
		<?php
	}

	public function wctlgm_add_product_data_tab( $tabs ) {
		$tabs['telegram'] = array(
			'label'    => __( 'Telegram Access', 'wctlgm-subscriber-manager-lite' ),
			'target'   => 'telegram_product_data',
			'class'    => array(
				'show_if_simple',
				'show_if_variable',
				'hide_if_subscription',
				'hide_if_variable-subscription',
				'hide_if_simple-subscription',
			),
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
			<!-- Message for variable product types (hidden by default, toggled by JS) -->
			<div class="options_group wctlgm-variable-message" style="display:none;">
				<div style="margin: 20px 0 0 0; padding: 12px; background: #f0f6fc; border-left: 4px solid #0073aa;">
					<p style="margin: 0; font-size: 13px;">
						<?php esc_html_e( 'Telegram channel access settings are configured on individual variations. Open each variation below to set channel access.', 'wctlgm-subscriber-manager-lite' ); ?>
					</p>
				</div>
			</div>

			<!-- Standard fields for simple products (hidden for variable types by JS) -->
			<div class='options_group wctlgm-standard-fields'>
				<p class="form-field">
					<label for="telegram_channel_ids"><?php esc_html_e( 'Select Channels/Groups', 'wctlgm-subscriber-manager-lite' ); ?></label>
					<select class="wc-enhanced-select" multiple="multiple" id="telegram_channel_ids" name="telegram_channel_ids[]" style="width: 50%;">
						<?php foreach ( $channels as $channel ) : ?>
							<option value="<?php echo esc_attr( $channel['id'] ); ?>" <?php echo in_array( $channel['id'], $saved_channels, true ) ? 'selected' : ''; ?>>
								<?php echo esc_html( $channel['name'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</p>
				<?php
					echo wp_kses_post( sprintf( '<p><em>Need to set access expiry? Automatic user removal? Works with subscriptions? <a href="%s">Upgrade to Pro Now!</a></em></p>', wctlgm_fs()->get_upgrade_url() ) );
				?>
			</div>

			<div class="options_group">
				<div class="wctlgm-support-link" style="margin: 20px 0 0 0; padding: 12px; background: #f9f9f9; border-left: 4px solid #0073aa;">
					<p style="margin: 0; font-size: 13px;">
						<strong><?php esc_html_e( 'Need Help?', 'wctlgm-subscriber-manager-lite' ); ?></strong>
						<?php esc_html_e( 'Check our comprehensive setup guide and documentation.', 'wctlgm-subscriber-manager-lite' ); ?>
						<a href="https://wctlgm.com/kb-category/getting-started/" target="_blank" style="margin-left: 8px;">
							<?php esc_html_e( 'View Documentation', 'wctlgm-subscriber-manager-lite' ); ?> →
						</a>
					</p>
				</div>
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

	/**
	 * Render Telegram access fields for each product variation.
	 *
	 * @param int     $loop           Variation loop index.
	 * @param array   $variation_data Variation data array.
	 * @param WP_Post $variation      Variation post object.
	 */
	public function wctlgm_variation_telegram_fields( $loop, $variation_data, $variation ) {
		// Only render for plain variable products (not variable-subscription, etc.).
		$parent_product = wc_get_product( $variation->post_parent );
		if ( ! $parent_product || ! $parent_product->is_type( 'variable' ) ) {
			return;
		}

		$variation_id   = $variation->ID;
		$channels       = get_option( 'wctlgm_channels', array() );
		$saved_channels = get_post_meta( $variation_id, '_telegram_channel_ids', true );
		$saved_channels = ! empty( $saved_channels ) ? $saved_channels : array();

		?>
		<div class="wctlgm-variation-fields" style="border-top: 1px solid #eee; margin-top: 1em; padding-top: 0.5em;">
			<p class="form-row form-row-full" style="margin-bottom: 0;">
				<strong><?php esc_html_e( 'Telegram Access', 'wctlgm-subscriber-manager-lite' ); ?></strong>
			</p>
			<p class="form-row form-row-full">
				<label for="telegram_channel_ids_<?php echo esc_attr( $loop ); ?>">
					<?php esc_html_e( 'Telegram Channels/Groups', 'wctlgm-subscriber-manager-lite' ); ?>
				</label>
				<select class="wc-enhanced-select wctlgm-variation-channel-select"
						multiple="multiple"
						id="telegram_channel_ids_<?php echo esc_attr( $loop ); ?>"
						name="wctlgm_variation_channel_ids[<?php echo esc_attr( $loop ); ?>][]"
						style="width: 100%;">
					<?php foreach ( $channels as $channel ) : ?>
						<option value="<?php echo esc_attr( $channel['id'] ); ?>"
							<?php echo in_array( $channel['id'], $saved_channels, true ) ? 'selected' : ''; ?>>
							<?php echo esc_html( $channel['name'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>
			<?php
				echo wp_kses_post( sprintf( '<p><em>Need access expiry or automatic removal? <a href="%s">Upgrade to Pro!</a></em></p>', wctlgm_fs()->get_upgrade_url() ) );
			?>
		</div>
		<?php
	}

	/**
	 * Save Telegram access meta for a product variation.
	 *
	 * @param int $variation_id The variation ID.
	 * @param int $loop         The variation loop index.
	 */
	public function wctlgm_save_variation_telegram_data( $variation_id, $loop ) {
		// Only save for plain variable products (not variable-subscription, etc.).
		$variation = get_post( $variation_id );
		if ( $variation ) {
			$parent_product = wc_get_product( $variation->post_parent );
			if ( ! $parent_product || ! $parent_product->is_type( 'variable' ) ) {
				return;
			}
		}

		// Verify nonce — variations save via AJAX (save-variations) or main product Update (woocommerce_save_data).
		$nonce_valid = false;
		if ( isset( $_POST['security'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['security'] ) ), 'save-variations' ) ) {
			$nonce_valid = true;
		} elseif ( isset( $_POST['woocommerce_meta_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ), 'woocommerce_save_data' ) ) {
			$nonce_valid = true;
		}

		if ( ! $nonce_valid ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $variation_id ) ) {
			return;
		}

		// Channel IDs.
		$channel_ids_raw = array();
		if ( isset( $_POST['wctlgm_variation_channel_ids'][ $loop ] ) ) {
			$channel_ids_raw = wp_unslash( $_POST['wctlgm_variation_channel_ids'][ $loop ] );
		}
		$channel_ids_raw = (array) $channel_ids_raw;
		$channel_ids     = array_map( 'sanitize_text_field', $channel_ids_raw );
		update_post_meta( $variation_id, '_telegram_channel_ids', $channel_ids );
	}

	/**
	 * Display the custom button for setting the webhook.
	 */
	public function wctlgm_custom_button_html() {
		$bot_token = get_option( 'wctlgm_bot_token' );
		$disabled  = empty( $bot_token ) ? 'disabled' : '';

		printf(
			'<tr valign="top"><th scope="row" class="titledesc"><label>%s</label></th><td class="forminp"><button type="button" class="button-primary" id="wctlgm_set_webhook_button" %s>%s</button><p class="description">%s</p></td></tr>',
			esc_html__( 'Set Webhook', 'wctlgm-subscriber-manager-lite' ),
			esc_attr( $disabled ),
			esc_html__( 'Set Webhook', 'wctlgm-subscriber-manager-lite' ),
			empty( $bot_token ) ? esc_html__( 'Enter a bot token and Save settings to activate button.', 'wctlgm-subscriber-manager-lite' ) : ''
		);
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
		$api_handler  = new \Subscriber_Manager_Lite_for_Telegram\Subscriber_Manager_Lite_WCTLGM_API_Handler();
		$result       = $api_handler->handle_set_webhook_actions( $webhook_url, $secret_token );

		if ( is_wp_error( $result ) ) {
			$error_message = $result->get_error_message();
			$this->logger->error( __( 'Failed to set webhook: ', 'wctlgm-subscriber-manager-lite' ) . $error_message );
			wp_send_json_error( array( 'message' => $error_message ) );
		} else {
			// Set the webhook clicked option to true
			update_option( 'wctlgm_webhook_clicked', true );
			delete_transient( 'wctlgm_activation_flow_changed' );
			wp_send_json_success( array( 'message' => 'Webhook set successfully.' ) );
		}
	}

	public function check_and_set_channel_id() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
			return;
		}

		$this->logger->info( __( 'Channel ID fetch initiated from settings', 'wctlgm-subscriber-manager-lite' ) );

		$channel_id = get_transient( 'wctlgm_channel_id_temp_store' );
		if ( $channel_id ) {
			// translators: %s is the stored channel ID
			$this->logger->info( sprintf( __( 'Found stored channel ID: %s', 'wctlgm-subscriber-manager-lite' ), $channel_id ) );
			delete_transient( 'wctlgm_channel_id_temp_store' );
			wp_send_json_success( array( 'channel_id' => $channel_id ) );
		} else {
			$this->logger->info( __( 'No stored channel ID found, setting active transient for 1 hour', 'wctlgm-subscriber-manager-lite' ) );
			set_transient( 'wctlgm_telegram_fetch_channel_id_active', true, HOUR_IN_SECONDS );
			wp_send_json_error( array( 'message' => 'Please post a message in your Telegram channel or group and then edit it. Then click "Get ID" again.' ) );
		}
	}

	/**
	 * Add a new settings page under the Settings menu.
	 */
	public function add_settings_page() {
		add_options_page(
			__( 'Telegram Subscriber Manager', 'wctlgm-subscriber-manager-lite' ),
			__( 'Telegram Subscriber Manager', 'wctlgm-subscriber-manager-lite' ),
			'manage_options',
			'wctlgm-settings',
			array( $this, 'settings_page' )
		);
	}

	/**
	 * Display the settings page.
	 */
	public function settings_page() {
		?>
		<div class="wrap fs-section">
			<h1><?php esc_html_e( 'Telegram Subscriber Manager Settings', 'wctlgm-subscriber-manager-lite' ); ?></h1>
			<h2 class="nav-tab-wrapper">
				<a href="#" class="nav-tab fs-tab nav-tab-active home">Settings</a>
			</h2>
			
			<form method="post" action="options.php">
				<?php
				settings_fields( 'wctlgm_settings_group' );

				do_settings_sections( 'wctlgm-settings' );

				submit_button();
				?>
			</form>

			<div class="wctlgm-support-link" style="margin: 20px 0; padding: 15px; background: #f9f9f9; border-left: 4px solid #0073aa;">
				<p style="margin: 0;">
					<strong><?php esc_html_e( 'Need Help?', 'wctlgm-subscriber-manager-lite' ); ?></strong>
					<?php esc_html_e( 'Check out our comprehensive setup guide and documentation.', 'wctlgm-subscriber-manager-lite' ); ?>
					<a href="https://wctlgm.com/kb-category/getting-started/" target="_blank" style="margin-left: 10px;">
						<?php esc_html_e( 'View Support Documentation', 'wctlgm-subscriber-manager-lite' ); ?> →
					</a>
				</p>
			</div>

		</div>
		<?php
	}

	/**
	 * Register settings and add settings sections and fields.
	 */
	public function register_settings() {
		register_setting( 'wctlgm_settings_group', 'wctlgm_bot_token', array( $this, 'sanitize_text_field' ) );
		register_setting( 'wctlgm_settings_group', 'wctlgm_bot_url', array( $this, 'sanitize_url' ) );
		register_setting( 'wctlgm_settings_group', 'wctlgm_allow_external_invites', array( $this, 'sanitize_checkbox' ) );
		register_setting( 'wctlgm_settings_group', 'wctlgm_require_activation_flow', array( $this, 'sanitize_activation_flow' ) );
		register_setting( 'wctlgm_settings_group', 'wctlgm_channels', array( $this, 'sanitize_channels' ) );

		add_settings_section(
			'wctlgm_settings_section',
			__( 'Telegram Integration Settings', 'wctlgm-subscriber-manager-lite' ),
			null,
			'wctlgm-settings'
		);

		add_settings_field(
			'wctlgm_bot_token',
			__( 'Telegram Bot Token', 'wctlgm-subscriber-manager-lite' ),
			array( $this, 'bot_token_field' ),
			'wctlgm-settings',
			'wctlgm_settings_section'
		);

		add_settings_field(
			'wctlgm_bot_url',
			__( 'Telegram Bot URL', 'wctlgm-subscriber-manager-lite' ),
			array( $this, 'bot_url_field' ),
			'wctlgm-settings',
			'wctlgm_settings_section'
		);

		add_settings_field(
			'wctlgm_allow_external_invites',
			__( 'Allow External Invites', 'wctlgm-subscriber-manager-lite' ),
			array( $this, 'allow_external_invites_field' ),
			'wctlgm-settings',
			'wctlgm_settings_section'
		);

		add_settings_field(
			'wctlgm_require_activation_flow',
			__( 'Require Activation Step', 'wctlgm-subscriber-manager-lite' ),
			array( $this, 'require_activation_field' ),
			'wctlgm-settings',
			'wctlgm_settings_section'
		);

		add_settings_field(
			'wctlgm_channels',
			__( 'Telegram Channels/Groups:', 'wctlgm-subscriber-manager-lite' ),
			array( $this, 'channels_field' ),
			'wctlgm-settings',
			'wctlgm_settings_section'
		);
	}

	/**
	 * Sanitize text fields.
	 */
	public function sanitize_text_field( $input ) {
		return sanitize_text_field( $input );
	}

	/**
	 * Sanitize URL fields.
	 */
	public function sanitize_url( $input ) {
		return esc_url_raw( $input );
	}

	/**
	 * Sanitize checkbox fields.
	 */
	public function sanitize_checkbox( $input ) {
		return isset( $input ) ? true : false;
	}

	/**
	 * Sanitize activation flow field.
	 */
	public function sanitize_activation_flow( $input ) {
		return isset( $input ) ? true : false;
	}

	/**
	 * Handle activation flow setting changes.
	 *
	 * @param mixed $_old_value The old option value.
	 * @param mixed $_new_value The new option value.
	 */
	public function handle_activation_flow_change( $_old_value, $_new_value ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		delete_option( 'wctlgm_webhook_clicked' );
	}

	/**
	 * Sanitize channels.
	 */
	public function sanitize_channels( $input ) {
		$channel = array();
		if ( ! empty( $input[0]['name'] ) && ! empty( $input[0]['id'] ) ) {
			$channel = array(
				'name' => sanitize_text_field( $input[0]['name'] ),
				'id'   => sanitize_text_field( $input[0]['id'] ),
			);
		}
		return array( $channel );
	}

	/**
	 * Display the bot token field with a description.
	 */
	public function bot_token_field() {
		$bot_token = get_option( 'wctlgm_bot_token' );
		echo '<input type="password" id="wctlgm_bot_token" name="wctlgm_bot_token" value="' . esc_attr( $bot_token ) . '" />';
		echo '<p class="description">' . esc_html__( 'Enter your Telegram bot token here.', 'wctlgm-subscriber-manager-lite' ) . '</p>';
		$this->wctlgm_custom_button_html();
	}

	/**
	 * Display the bot URL field.
	 */
	public function bot_url_field() {
		$bot_url = get_option( 'wctlgm_bot_url' );
		echo '<input type="text" name="wctlgm_bot_url" value="' . esc_attr( $bot_url ) . '" />';
	}

	/**
	 * Display the Allow External Invites field.
	 */
	public function allow_external_invites_field() {
		$allow_external_invites = get_option( 'wctlgm_allow_external_invites', false );
		echo '<input type="checkbox" name="wctlgm_allow_external_invites" value="1" ' . checked( $allow_external_invites, true, false ) . ' />';
		echo '<p class="description">' . esc_html__( 'When enabled, join requests from external or manually created invite links will skip order validation checks.', 'wctlgm-subscriber-manager-lite' ) . '</p>';
	}

	/**
	 * Display the channels field.
	 */
	public function channels_field() {
		$this->custom_channels_input();
	}

	public function require_activation_field() {
		$require_activation = get_option( 'wctlgm_require_activation_flow', false );

		echo '<input type="checkbox" name="wctlgm_require_activation_flow" value="1" ' . checked( $require_activation, true, false ) . ' />';
		echo '<p class="description">' . esc_html__( 'Use the Telegram bot chat to validate users and generate invite links. Not recommended for large or active groups.', 'wctlgm-subscriber-manager-lite' ) . '</p>';
	}

	/**
	 * Display webhook warning notice across all wp-admin pages.
	 */
	public function display_webhook_warning_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$bot_token = get_option( 'wctlgm_bot_token' );
		if ( empty( $bot_token ) ) {
			return;
		}

		$webhook_clicked = get_option( 'wctlgm_webhook_clicked', false );

		if ( ! $webhook_clicked ) {
			$settings_url = admin_url( 'options-general.php?page=wctlgm-settings' );
			?>
			<div class="notice notice-warning is-dismissible" id="wctlgm-webhook-notice">
				<p>
					<strong><?php esc_html_e( 'Action Required:', 'wctlgm-subscriber-manager-lite' ); ?></strong>
					<?php esc_html_e( 'Please click the "Set Webhook" button in your Telegram Subscriber Manager settings to ensure proper functionality.', 'wctlgm-subscriber-manager-lite' ); ?>
					<a href="<?php echo esc_url( $settings_url ); ?>" style="margin-left: 8px;">
						<?php esc_html_e( 'Go to Settings', 'wctlgm-subscriber-manager-lite' ); ?> →
					</a>
				</p>
			</div>
			<?php
		}
	}
}
