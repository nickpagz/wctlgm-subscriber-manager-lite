<?php

namespace Subscriber_Manager_Lite_for_Telegram;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Telegram Channel Invite Links Email
 *
 * An email sent to the customer with their Telegram channel invite links when activation is disabled.
 *
 * @class       Subscriber_Manager_Lite_WCTLGM_Invite_Links_Email
 * @version     1.2.0
 * @extends     WC_Email
 * @since       1.2.0
 */
class Subscriber_Manager_Lite_WCTLGM_Invite_Links_Email extends \WC_Email {

	/**
	 * Channel invites data.
	 *
	 * @var array
	 */
	public $invites;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = 'wctlgm_invite_links';
		$this->customer_email = true;
		$this->title          = __( 'Telegram Channel Invite Links', 'wctlgm-subscriber-manager-lite' );
		$this->description    = __( 'This email is sent to customers with their Telegram channel invite links when activation is disabled.', 'wctlgm-subscriber-manager-lite' );
		$this->template_html  = 'emails/telegram-channel-invite-links.php';
		$this->template_plain = 'emails/plain/telegram-channel-invite-links.php';
		$this->template_base  = WCTLGM_SML_PLUGIN_DIR . 'templates/';

		// Call parent constructor
		parent::__construct();
	}

	/**
	 * Get email subject.
	 *
	 * @return string
	 */
	public function get_default_subject() {
		return __( 'Your Telegram Channel Access - Order #{order_number}', 'wctlgm-subscriber-manager-lite' );
	}

	/**
	 * Get email heading.
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return __( 'Your Telegram Channel Access', 'wctlgm-subscriber-manager-lite' );
	}

	/**
	 * Trigger the sending of this email.
	 *
	 * @param int $order_id The order ID.
	 * @param array $invites The channel invites data.
	 */
	public function trigger( $order_id, $invites ) {
		$this->setup_locale();

		if ( $order_id ) {
			$this->object = \wc_get_order( $order_id );
			if ( is_a( $this->object, 'WC_Order' ) ) {
				$this->recipient = $this->object->get_billing_email();
				$this->invites   = $invites;
			}
		}

		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		$this->restore_locale();
	}

	/**
	 * Get content html.
	 *
	 * @return string
	 */
	public function get_content_html() {
		return \wc_get_template_html(
			$this->template_html,
			array(
				'order'              => $this->object,
				'invites'            => $this->invites,
				'email_heading'      => $this->get_heading(),
				'additional_content' => $this->get_additional_content(),
				'sent_to_admin'      => false,
				'plain_text'         => false,
				'email'              => $this,
			),
			'',
			$this->template_base
		);
	}

	/**
	 * Get content plain.
	 *
	 * @return string
	 */
	public function get_content_plain() {
		return \wc_get_template_html(
			$this->template_plain,
			array(
				'order'              => $this->object,
				'invites'            => $this->invites,
				'email_heading'      => $this->get_heading(),
				'additional_content' => $this->get_additional_content(),
				'sent_to_admin'      => false,
				'plain_text'         => true,
				'email'              => $this,
			),
			'',
			$this->template_base
		);
	}
}
