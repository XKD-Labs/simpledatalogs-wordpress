<?php
/**
 * Tests for SimpleDataLogs_Settings.
 *
 * @package SimpleDataLogs
 */

use Brain\Monkey\Functions;

/**
 * Covers defaults, key and host sanitizing, and role exclusion.
 */
class SettingsTest extends SimpleDataLogs_Test_Case {

	/**
	 * Stubs wp_roles() with the default WordPress roles.
	 *
	 * @return void
	 */
	private function stub_site_roles() {
		$site_roles = Mockery::mock( 'WP_Roles' );
		$site_roles->shouldReceive( 'get_names' )->andReturn(
			array(
				'administrator' => 'Administrator',
				'editor' => 'Editor',
				'subscriber' => 'Subscriber',
			)
		);

		Functions\when( 'wp_roles' )->justReturn( $site_roles );
	}

	/**
	 * A fresh install tracks page views only, respects DNT and excludes admins.
	 */
	public function test_defaults_are_privacy_friendly() {
		$settings = SimpleDataLogs_Settings::all();

		$this->assertTrue( $settings['track_page_views'] );
		$this->assertFalse( $settings['autocapture'] );
		$this->assertTrue( $settings['respect_dnt'] );
		$this->assertFalse( $settings['server_events'] );
		$this->assertSame( array( 'administrator' ), $settings['excluded_roles'] );
		$this->assertSame( '', $settings['ingest_key'] );
	}

	/**
	 * A corrupt option falls back to the defaults.
	 */
	public function test_non_array_option_falls_back_to_defaults() {
		Functions\when( 'get_option' )->justReturn( 'garbage' );

		$this->assertSame( SimpleDataLogs_Settings::defaults(), SimpleDataLogs_Settings::all() );
	}

	/**
	 * Only sdl_pub_ plus 32 base62 characters is a public key.
	 */
	public function test_public_key_shape() {
		$this->assertTrue( SimpleDataLogs_Settings::is_public_key( self::VALID_KEY ) );
		$this->assertFalse( SimpleDataLogs_Settings::is_public_key( 'sdl_pub_short' ) );
		$this->assertFalse( SimpleDataLogs_Settings::is_public_key( 'sdl_sec_abcdefghijklmnopqrstuvwxyz012345' ) );
		$this->assertFalse( SimpleDataLogs_Settings::is_public_key( self::VALID_KEY . '!' ) );
	}

	/**
	 * A valid key is saved trimmed and an empty field clears it.
	 */
	public function test_sanitize_key_accepts_valid_and_empty() {
		$this->assertSame( self::VALID_KEY, SimpleDataLogs_Settings::sanitize_ingest_key( '  ' . self::VALID_KEY . ' ', '' ) );
		$this->assertSame( '', SimpleDataLogs_Settings::sanitize_ingest_key( '', self::VALID_KEY ) );
	}

	/**
	 * A secret key is refused with an error and the previous key is kept.
	 */
	public function test_sanitize_key_refuses_secret_keys() {
		Functions\expect( 'add_settings_error' )->once()->with( 'simpledatalogs_settings', 'secret_key', Mockery::type( 'string' ) );

		$this->assertSame( 'previous', SimpleDataLogs_Settings::sanitize_ingest_key( 'sdl_sec_abc', 'previous' ) );
	}

	/**
	 * A malformed key is refused with an error and the previous key is kept.
	 */
	public function test_sanitize_key_refuses_malformed_keys() {
		Functions\expect( 'add_settings_error' )->once()->with( 'simpledatalogs_settings', 'invalid_key', Mockery::type( 'string' ) );

		$this->assertSame( 'previous', SimpleDataLogs_Settings::sanitize_ingest_key( 'hello', 'previous' ) );
	}

	/**
	 * Hosts are reduced to scheme, host and port, with https by default.
	 */
	public function test_sanitize_host_normalizes() {
		$this->assertSame( 'https://eu.example.com', SimpleDataLogs_Settings::sanitize_ingest_host( 'EU.example.com', '' ) );
		$this->assertSame( 'https://eu.example.com:8443', SimpleDataLogs_Settings::sanitize_ingest_host( 'https://eu.example.com:8443/v1/ingest?x=1', '' ) );
		$this->assertSame( 'http://localhost:4001', SimpleDataLogs_Settings::sanitize_ingest_host( 'http://localhost:4001', '' ) );
		$this->assertSame( 'http://ingest.test', SimpleDataLogs_Settings::sanitize_ingest_host( 'http://ingest.test', '' ) );
		$this->assertSame( '', SimpleDataLogs_Settings::sanitize_ingest_host( '', 'https://old.example.com' ) );
	}

	/**
	 * Plain http to a public host and odd schemes are refused.
	 */
	public function test_sanitize_host_refuses_insecure_hosts() {
		Functions\expect( 'add_settings_error' )->twice();

		$this->assertSame( 'https://old.example.com', SimpleDataLogs_Settings::sanitize_ingest_host( 'http://example.com', 'https://old.example.com' ) );
		$this->assertSame( '', SimpleDataLogs_Settings::sanitize_ingest_host( 'javascript://alert(1)', '' ) );
	}

	/**
	 * The ingest URL uses the override when set and the edge host otherwise.
	 */
	public function test_ingest_url() {
		$this->assertSame( 'https://edge.simpledatalogs.com/v1/ingest', SimpleDataLogs_Settings::ingest_url() );

		$this->use_settings( array( 'ingest_host' => 'http://localhost:4001' ) );

		$this->assertSame( 'http://localhost:4001/v1/ingest', SimpleDataLogs_Settings::ingest_url() );
	}

	/**
	 * The whole form is cleaned: toggles, unknown roles and unknown event types.
	 */
	public function test_sanitize_cleans_the_whole_form() {
		$this->stub_site_roles();

		$clean_settings = SimpleDataLogs_Settings::sanitize(
			array(
				'ingest_key' => self::VALID_KEY,
				'ingest_host' => '',
				'autocapture' => '1',
				'excluded_roles' => array( 'editor', 'ghost', array( 'nested' ) ),
				'server_event_types' => array( 'wp_login', 'drop_tables' ),
			)
		);

		$this->assertSame( self::VALID_KEY, $clean_settings['ingest_key'] );
		$this->assertTrue( $clean_settings['autocapture'] );
		$this->assertFalse( $clean_settings['track_page_views'] );
		$this->assertFalse( $clean_settings['respect_dnt'] );
		$this->assertSame( array( 'editor' ), $clean_settings['excluded_roles'] );
		$this->assertSame( array( 'wp_login' ), $clean_settings['server_event_types'] );
	}

	/**
	 * A non-array submission becomes an all-off settings array.
	 */
	public function test_sanitize_handles_non_array_input() {
		$this->stub_site_roles();

		$clean_settings = SimpleDataLogs_Settings::sanitize( 'nope' );

		$this->assertSame( '', $clean_settings['ingest_key'] );
		$this->assertSame( array(), $clean_settings['excluded_roles'] );
	}

	/**
	 * Only logged-in users holding an excluded role are excluded.
	 */
	public function test_user_exclusion() {
		$this->use_settings( array( 'excluded_roles' => array( 'administrator', 'editor' ) ) );

		$this->assertTrue( SimpleDataLogs_Settings::is_user_excluded( new WP_User( 1, array( 'administrator' ) ) ) );
		$this->assertTrue( SimpleDataLogs_Settings::is_user_excluded( new WP_User( 2, array( 'editor' ) ) ) );
		$this->assertFalse( SimpleDataLogs_Settings::is_user_excluded( new WP_User( 3, array( 'subscriber' ) ) ) );
		$this->assertFalse( SimpleDataLogs_Settings::is_user_excluded( new WP_User( 0 ) ) );
		$this->assertFalse( SimpleDataLogs_Settings::is_user_excluded( null ) );
	}
}
