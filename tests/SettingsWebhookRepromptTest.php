<?php

use Brain\Monkey\Functions;
use Subscriber_Manager_Lite_for_Telegram\Subscriber_Manager_Lite_WCTLGM_Settings;

require_once WCTLGM_SML_PLUGIN_DIR . 'includes/class-subscriber-manager-lite-wctlgm-settings.php';

/**
 * Covers maybe_reprompt_webhook_after_upgrade(): on upgrade from a release that
 * predates the mandatory webhook secret, the "Set Webhook" banner must be
 * re-surfaced (by clearing wctlgm_webhook_clicked) so the admin re-registers the
 * webhook — but never on a fresh install, and never twice.
 */
class SettingsWebhookRepromptTest extends WCTLGM_Lite_TestCase {

	protected function setUp(): void {
		parent::setUp();
		if ( ! defined( 'WCTLGM_SML_VERSION' ) ) {
			define( 'WCTLGM_SML_VERSION', '2.1.1' );
		}
	}

	/**
	 * Build the settings object without running its (heavy) constructor — the
	 * method under test only reads/writes options and the version constant.
	 */
	private function make_settings() {
		$rc = new ReflectionClass( Subscriber_Manager_Lite_WCTLGM_Settings::class );
		return $rc->newInstanceWithoutConstructor();
	}

	/**
	 * Stub the options the method reads, and record every delete_option /
	 * update_option call so tests can assert on them.
	 *
	 * @param mixed  $version   Value for wctlgm_version.
	 * @param string $bot_token Value for wctlgm_bot_token.
	 */
	private function stub_and_record( $version, $bot_token, &$deleted, &$updated ) {
		$deleted = array();
		$updated = array();

		Functions\when( 'get_option' )->alias(
			function ( $key, $default = false ) use ( $version, $bot_token ) {
				if ( 'wctlgm_version' === $key ) {
					return $version;
				}
				if ( 'wctlgm_bot_token' === $key ) {
					return $bot_token;
				}
				return $default;
			}
		);
		Functions\when( 'delete_option' )->alias(
			function ( $key ) use ( &$deleted ) {
				$deleted[] = $key;
				return true;
			}
		);
		Functions\when( 'update_option' )->alias(
			function ( $key, $value = null ) use ( &$updated ) {
				$updated[ $key ] = $value;
				return true;
			}
		);
	}

	/**
	 * @test
	 * Unversioned install (upgrade from pre-2.1.1) with a configured bot →
	 * clears webhook_clicked and records the current version.
	 */
	public function upgrade_from_unversioned_with_bot_token_reprompts() {
		$this->stub_and_record( false, 'a-real-bot-token', $deleted, $updated );

		$this->make_settings()->maybe_reprompt_webhook_after_upgrade();

		$this->assertContains( 'wctlgm_webhook_clicked', $deleted );
		$this->assertSame( '2.1.1', $updated['wctlgm_version'] ?? null );
	}

	/**
	 * @test
	 * A versioned-but-below-threshold install (2.1.0) with a bot also reprompts.
	 */
	public function upgrade_from_below_threshold_version_reprompts() {
		$this->stub_and_record( '2.1.0', 'a-real-bot-token', $deleted, $updated );

		$this->make_settings()->maybe_reprompt_webhook_after_upgrade();

		$this->assertContains( 'wctlgm_webhook_clicked', $deleted );
		$this->assertSame( '2.1.1', $updated['wctlgm_version'] ?? null );
	}

	/**
	 * @test
	 * Fresh install (no bot token yet) → records the version but never shows the
	 * banner.
	 */
	public function fresh_install_records_version_but_does_not_reprompt() {
		$this->stub_and_record( false, '', $deleted, $updated );

		$this->make_settings()->maybe_reprompt_webhook_after_upgrade();

		$this->assertNotContains( 'wctlgm_webhook_clicked', $deleted );
		$this->assertSame( '2.1.1', $updated['wctlgm_version'] ?? null );
	}

	/**
	 * @test
	 * Already on the current version → no-op (no writes at all).
	 */
	public function already_current_version_is_a_noop() {
		$this->stub_and_record( '2.1.1', 'a-real-bot-token', $deleted, $updated );

		$this->make_settings()->maybe_reprompt_webhook_after_upgrade();

		$this->assertSame( array(), $deleted );
		$this->assertSame( array(), $updated );
	}
}
