<?php
/**
 * Tests for SimpleDataLogs_Sender.
 *
 * @package SimpleDataLogs
 */

use Brain\Monkey\Functions;

/**
 * Covers event stamping, batching and the request shape.
 */
class SenderTest extends SimpleDataLogs_Test_Case {

	/**
	 * Gives every test a valid key and a UUID generator.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->use_settings( array( 'ingest_key' => self::VALID_KEY ) );

		Functions\when( 'wp_generate_uuid4' )->alias(
			static function () {
				return sprintf( '%08x-0000-4000-8000-%012x', random_int( 0, 0xffffffff ), random_int( 0, 0xffffffff ) );
			}
		);
	}

	/**
	 * An event carries its name, a UUID event_id, an ISO 8601 UTC ts and clean props.
	 */
	public function test_build_event_stamps_id_and_time() {
		$sender = new SimpleDataLogs_Sender();

		$event = $sender->build_event(
			'order_completed',
			array(
				'total' => 29.5,
				'currency' => 'EUR',
				'is_guest' => false,
				'items' => array( 1, 2 ),
				'nothing' => null,
				'infinite' => INF,
			)
		);

		$this->assertSame( 'order_completed', $event['event'] );
		$this->assertMatchesRegularExpression( '/^[0-9a-f-]{36}$/', $event['event_id'] );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/', $event['ts'] );
		$this->assertSame(
			array(
				'total' => 29.5,
				'currency' => 'EUR',
				'is_guest' => false,
			),
			$event['props']
		);
	}

	/**
	 * An event without usable props has no props field.
	 */
	public function test_build_event_omits_empty_props() {
		$event = ( new SimpleDataLogs_Sender() )->build_event( 'ping', array( 'list' => array() ) );

		$this->assertArrayNotHasKey( 'props', $event );
	}

	/**
	 * Tracking queues events and registers one shutdown flush.
	 */
	public function test_track_queues_and_schedules_one_flush() {
		$sender = new SimpleDataLogs_Sender();

		Functions\expect( 'add_action' )->once()->with( 'shutdown', array( $sender, 'flush' ) );

		$sender->track( 'user_logged_in' );
		$sender->track( 'comment_posted' );

		$this->assertCount( 2, $sender->pending_events() );
	}

	/**
	 * Without a valid key nothing is queued.
	 */
	public function test_track_without_key_queues_nothing() {
		$this->use_settings( array( 'ingest_key' => '' ) );

		Functions\expect( 'add_action' )->never();

		$sender = new SimpleDataLogs_Sender();
		$sender->track( 'user_logged_in' );

		$this->assertSame( array(), $sender->pending_events() );
	}

	/**
	 * Flushing sends non-blocking JSON batches of at most 50 with the key and User-Agent.
	 */
	public function test_flush_sends_non_blocking_batches() {
		Functions\when( 'add_action' )->justReturn( true );

		$sender = new SimpleDataLogs_Sender();

		for ( $event_number = 0; $event_number < 51; $event_number++ ) {
			$sender->track( 'tick', array( 'number' => $event_number ) );
		}

		$sent_requests = array();

		Functions\when( 'wp_remote_post' )->alias(
			static function ( $request_url, $request_arguments ) use ( &$sent_requests ) {
				$sent_requests[] = array( $request_url, $request_arguments );

				return array();
			}
		);

		$sender->flush();

		$this->assertCount( 2, $sent_requests );

		list( $request_url, $request_arguments ) = $sent_requests[0];

		$this->assertSame( 'https://edge.simpledatalogs.com/v1/ingest', $request_url );
		$this->assertFalse( $request_arguments['blocking'] );
		$this->assertSame( 'Bearer ' . self::VALID_KEY, $request_arguments['headers']['Authorization'] );
		$this->assertSame( 'application/json', $request_arguments['headers']['Content-Type'] );
		$this->assertSame( 'simpledatalogs-wordpress/0.1.0', $request_arguments['user-agent'] );
		$this->assertCount( 50, json_decode( $request_arguments['body'], true ) );
		$this->assertCount( 1, json_decode( $sent_requests[1][1]['body'], true ) );
		$this->assertSame( array(), $sender->pending_events() );
	}

	/**
	 * The test event blocks and returns the HTTP status.
	 */
	public function test_send_test_event_returns_status() {
		Functions\expect( 'wp_remote_post' )->once()->andReturnUsing(
			function ( $request_url, $request_arguments ) {
				$sent_events = json_decode( $request_arguments['body'], true );

				$this->assertTrue( $request_arguments['blocking'] );
				$this->assertSame( 'setup_test', $sent_events[0]['event'] );

				return array( 'response' => array( 'code' => 202 ) );
			}
		);
		Functions\when( 'wp_remote_retrieve_response_code' )->alias(
			static function ( $response ) {
				return $response['response']['code'];
			}
		);

		$this->assertSame( 202, ( new SimpleDataLogs_Sender() )->send_test_event() );
	}

	/**
	 * A transport error is returned, never thrown.
	 */
	public function test_send_test_event_returns_transport_errors() {
		Functions\when( 'wp_remote_post' )->justReturn( new WP_Error() );

		$this->assertInstanceOf( WP_Error::class, ( new SimpleDataLogs_Sender() )->send_test_event() );
	}
}
