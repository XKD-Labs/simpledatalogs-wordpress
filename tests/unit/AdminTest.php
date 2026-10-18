<?php
/**
 * Tests for SimpleDataLogs_Admin.
 *
 * @package SimpleDataLogs
 */

use Brain\Monkey\Functions;

/**
 * Covers the test button's guards and result messages.
 */
class AdminTest extends SimpleDataLogs_Test_Case {

	/**
	 * Makes wp_die throw so the test can stop there.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'wp_die' )->alias(
			static function () {
				throw new SimpleDataLogs_Test_Died();
			}
		);
	}

	/**
	 * Users without manage_options cannot send the test event.
	 */
	public function test_test_event_requires_capability() {
		$sender = Mockery::mock( SimpleDataLogs_Sender::class );
		$sender->shouldNotReceive( 'send_test_event' );

		Functions\expect( 'current_user_can' )->once()->with( 'manage_options' )->andReturn( false );
		Functions\expect( 'check_admin_referer' )->never();

		$this->expectException( SimpleDataLogs_Test_Died::class );

		( new SimpleDataLogs_Admin( $sender ) )->handle_test_event();
	}

	/**
	 * The test event checks the nonce before sending.
	 */
	public function test_test_event_checks_nonce_first() {
		$sender = Mockery::mock( SimpleDataLogs_Sender::class );
		$sender->shouldNotReceive( 'send_test_event' );

		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\expect( 'check_admin_referer' )->once()->with( 'simpledatalogs_send_test' )->andReturnUsing(
			static function () {
				throw new SimpleDataLogs_Test_Died();
			}
		);

		$this->expectException( SimpleDataLogs_Test_Died::class );

		( new SimpleDataLogs_Admin( $sender ) )->handle_test_event();
	}

	/**
	 * Each stored result maps to a notice class.
	 */
	public function test_result_messages() {
		$this->assertSame( 'notice-success', SimpleDataLogs_Admin::describe_test_result( '202' )[0] );
		$this->assertSame( 'notice-error', SimpleDataLogs_Admin::describe_test_result( '401' )[0] );
		$this->assertSame( 'notice-error', SimpleDataLogs_Admin::describe_test_result( '0' )[0] );
		$this->assertSame( 'notice-error', SimpleDataLogs_Admin::describe_test_result( 'error' )[0] );
		$this->assertStringContainsString( '503', SimpleDataLogs_Admin::describe_test_result( '503' )[1] );
	}

	/**
	 * The settings link points at Settings → simpledatalogs and comes first.
	 */
	public function test_settings_link_comes_first() {
		Functions\when( 'admin_url' )->alias(
			static function ( $admin_path ) {
				return 'https://example.com/wp-admin/' . $admin_path;
			}
		);

		$action_links = ( new SimpleDataLogs_Admin( Mockery::mock( SimpleDataLogs_Sender::class ) ) )->add_settings_link( array( 'deactivate' ) );

		$this->assertStringContainsString( 'options-general.php?page=simpledatalogs', $action_links[0] );
		$this->assertSame( 'deactivate', $action_links[1] );
	}
}
