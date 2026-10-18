<?php
/**
 * Tests for SimpleDataLogs_Frontend.
 *
 * @package SimpleDataLogs
 */

use Brain\Monkey\Functions;

/**
 * Covers when the SDK loads and what its script tag carries.
 */
class FrontendTest extends SimpleDataLogs_Test_Case {

	/**
	 * Stubs a public page viewed by a visitor, with a valid key saved.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->use_settings( array( 'ingest_key' => self::VALID_KEY ) );

		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'is_customize_preview' )->justReturn( false );
		Functions\when( 'is_feed' )->justReturn( false );
		Functions\when( 'wp_get_current_user' )->justReturn( new WP_User( 0 ) );
		Functions\when( 'plugins_url' )->alias(
			static function ( $asset_path ) {
				return 'https://example.com/wp-content/plugins/simpledatalogs/' . $asset_path;
			}
		);
	}

	/**
	 * A visitor on a public page with a key is tracked.
	 */
	public function test_tracks_visitors_on_public_pages() {
		$this->assertTrue( ( new SimpleDataLogs_Frontend() )->should_track_current_request() );
	}

	/**
	 * The wp-admin screens are never tracked.
	 */
	public function test_never_tracks_wp_admin() {
		Functions\when( 'is_admin' )->justReturn( true );

		$this->assertFalse( ( new SimpleDataLogs_Frontend() )->should_track_current_request() );
	}

	/**
	 * Without a valid key nothing loads.
	 */
	public function test_needs_a_valid_key() {
		$this->use_settings( array( 'ingest_key' => 'sdl_pub_nope' ) );

		$this->assertFalse( ( new SimpleDataLogs_Frontend() )->should_track_current_request() );
	}

	/**
	 * Administrators are excluded by default.
	 */
	public function test_excludes_administrators_by_default() {
		Functions\when( 'wp_get_current_user' )->justReturn( new WP_User( 1, array( 'administrator' ) ) );

		$this->assertFalse( ( new SimpleDataLogs_Frontend() )->should_track_current_request() );
	}

	/**
	 * With autocapture off the tag opts out of it, and no endpoint is set by default.
	 */
	public function test_attributes_with_defaults() {
		$attributes = ( new SimpleDataLogs_Frontend() )->sdk_attributes();

		$this->assertSame(
			array(
				'data-key' => self::VALID_KEY,
				'data-autocapture' => 'false',
			),
			$attributes
		);
	}

	/**
	 * With autocapture on and a host override, the tag carries the endpoint and no opt-out.
	 */
	public function test_attributes_with_autocapture_and_host() {
		$this->use_settings(
			array(
				'ingest_key' => self::VALID_KEY,
				'ingest_host' => 'https://eu.example.com',
				'autocapture' => true,
			)
		);

		$attributes = ( new SimpleDataLogs_Frontend() )->sdk_attributes();

		$this->assertSame( 'https://eu.example.com', $attributes['data-endpoint'] );
		$this->assertArrayNotHasKey( 'data-autocapture', $attributes );
	}

	/**
	 * The attributes go into the SDK tag that has a src, not into inline scripts or other handles.
	 */
	public function test_adds_attributes_only_to_the_sdk_tag() {
		$frontend = new SimpleDataLogs_Frontend();
		$sdk_tag = "<script id=\"x-js-before\">var a = 1;</script>\n<script src=\"https://example.com/sdk.js\" id=\"simpledatalogs-sdk-js\" defer data-wp-strategy=\"defer\"></script>\n";
		$other_tag = '<script src="https://example.com/theme.js"></script>';

		$filtered_tag = $frontend->add_sdk_attributes( $sdk_tag, 'simpledatalogs-sdk' );

		$this->assertStringContainsString( '<script id="x-js-before">', $filtered_tag );
		$this->assertStringContainsString( '<script data-key="' . self::VALID_KEY . '" data-autocapture="false" src="https://example.com/sdk.js"', $filtered_tag );
		$this->assertSame( $other_tag, $frontend->add_sdk_attributes( $other_tag, 'theme' ) );
	}

	/**
	 * Both scripts are enqueued deferred in the head, with the settings in a "before" inline script.
	 */
	public function test_enqueues_deferred_scripts_with_config() {
		$this->use_settings(
			array(
				'ingest_key' => self::VALID_KEY,
				'track_page_views' => true,
				'respect_dnt' => true,
			)
		);

		$deferred_in_head = array(
			'strategy' => 'defer',
			'in_footer' => false,
		);

		Functions\expect( 'wp_enqueue_script' )->once()->with( 'simpledatalogs-sdk', Mockery::pattern( '#assets/js/simpledatalogs\.min\.js$#' ), array(), '0.3.0', $deferred_in_head );
		Functions\expect( 'wp_enqueue_script' )->once()->with( 'simpledatalogs-init', Mockery::pattern( '#assets/js/simpledatalogs-init\.js$#' ), array( 'simpledatalogs-sdk' ), '0.1.0', $deferred_in_head );
		Functions\expect( 'wp_add_inline_script' )->once()->with(
			'simpledatalogs-init',
			'window.simpledatalogsWordPress = {"autocapture":false,"pageViews":true,"respectDoNotTrack":true};',
			'before'
		);

		( new SimpleDataLogs_Frontend() )->enqueue_scripts();
	}

	/**
	 * Nothing is enqueued when the request is not tracked.
	 */
	public function test_enqueues_nothing_when_not_tracked() {
		Functions\when( 'is_customize_preview' )->justReturn( true );
		Functions\expect( 'wp_enqueue_script' )->never();

		( new SimpleDataLogs_Frontend() )->enqueue_scripts();
	}
}
