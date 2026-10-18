<?php
/**
 * The base test case: Brain Monkey setup and a settings helper.
 *
 * @package SimpleDataLogs
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Sets up Brain Monkey and the WordPress functions every test needs.
 */
abstract class SimpleDataLogs_Test_Case extends TestCase {

	// A well-formed public key used across tests.
	const VALID_KEY = 'sdl_pub_abcdefghijklmnopqrstuvwxyz012345';

	/**
	 * Starts Brain Monkey and stubs translation, escaping and helpers.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();

		// Plain stand-ins for the small WordPress helpers the plugin calls.
		Functions\when( 'sanitize_text_field' )->alias( 'trim' );
		Functions\when( 'sanitize_key' )->alias(
			static function ( $raw_key ) {
				return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $raw_key ) );
			}
		);
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'is_wp_error' )->alias(
			static function ( $candidate ) {
				return $candidate instanceof WP_Error;
			}
		);

		$this->use_settings( array() );
	}

	/**
	 * Stops Brain Monkey and checks expectations.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		// Count Mockery and Brain Monkey expectations as assertions.
		$this->addToAssertionCount( Mockery::getContainer()->mockery_getExpectationCount() );

		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Makes get_option return these settings for the plugin's option.
	 *
	 * @param array<string, mixed> $saved_settings The saved option value.
	 * @return void
	 */
	protected function use_settings( array $saved_settings ) {
		Functions\when( 'get_option' )->justReturn( $saved_settings );
	}
}
