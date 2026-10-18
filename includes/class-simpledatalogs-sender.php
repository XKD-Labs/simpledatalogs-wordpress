<?php
/**
 * Sends server-side events to the ingest endpoint with wp_remote_post.
 *
 * @package SimpleDataLogs
 */

// Stop when loaded outside WordPress.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stamps events when they happen and sends them in one non-blocking batch at the end of the request.
 */
class SimpleDataLogs_Sender {

	// Ingest takes at most 50 events per batch under the sender rules.
	const MAX_BATCH_EVENTS = 50;

	// Seconds a blocking request may take; only the test button blocks.
	const BLOCKING_TIMEOUT_SECONDS = 10;

	// Seconds WordPress waits to hand off a non-blocking request.
	const NON_BLOCKING_TIMEOUT_SECONDS = 1;

	/**
	 * Events recorded during this request and not yet sent.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private $pending_events = array();

	/**
	 * Whether the shutdown flush is already registered.
	 *
	 * @var bool
	 */
	private $is_flush_scheduled = false;

	/**
	 * Builds one event with its event_id and ts set now, when it happens.
	 *
	 * @param string               $event_name The event name, such as user_register.
	 * @param array<string, mixed> $props      String, number or boolean props.
	 * @return array<string, mixed>
	 */
	public function build_event( $event_name, array $props = array() ) {
		$event = array(
			'event' => $event_name,
			'event_id' => wp_generate_uuid4(),
			'ts' => $this->current_timestamp(),
		);

		// Props are kept only when at least one value survives cleaning.
		$clean_props = $this->clean_props( $props );

		if ( array() !== $clean_props ) {
			$event['props'] = $clean_props;
		}

		return $event;
	}

	/**
	 * Records an event now and sends it with the rest at the end of the request.
	 *
	 * @param string               $event_name The event name.
	 * @param array<string, mixed> $props      String, number or boolean props.
	 * @return void
	 */
	public function track( $event_name, array $props = array() ) {
		// Without a valid key there is nowhere to send, so nothing is queued.
		if ( ! SimpleDataLogs_Settings::has_valid_key() ) {
			return;
		}

		$this->pending_events[] = $this->build_event( $event_name, $props );

		// One shutdown flush per request sends every event in a single batch.
		if ( ! $this->is_flush_scheduled ) {
			$this->is_flush_scheduled = true;
			add_action( 'shutdown', array( $this, 'flush' ) );
		}
	}

	/**
	 * Returns the events waiting for the shutdown flush.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function pending_events() {
		return $this->pending_events;
	}

	/**
	 * Sends the pending events without waiting for the answer, at most 50 per request.
	 *
	 * @return void
	 */
	public function flush() {
		$event_batches = array_chunk( $this->pending_events, self::MAX_BATCH_EVENTS );
		$this->pending_events = array();

		foreach ( $event_batches as $event_batch ) {
			$this->post_events( $event_batch, false );
		}
	}

	/**
	 * Sends one test event and waits for the answer, for the settings page button.
	 *
	 * @return int|WP_Error The HTTP status, or the transport error.
	 */
	public function send_test_event() {
		$test_event = $this->build_event( 'setup_test', array( 'source' => 'wordpress' ) );
		$response = $this->post_events( array( $test_event ), true );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return (int) wp_remote_retrieve_response_code( $response );
	}

	/**
	 * Posts a JSON array of events to the ingest endpoint.
	 *
	 * @param array<int, array<string, mixed>> $events      The events to send.
	 * @param bool                             $is_blocking Whether to wait for the answer.
	 * @return array|WP_Error The wp_remote_post result.
	 */
	private function post_events( array $events, $is_blocking ) {
		$request_arguments = array(
			'body' => wp_json_encode( $events ),
			'timeout' => $is_blocking ? self::BLOCKING_TIMEOUT_SECONDS : self::NON_BLOCKING_TIMEOUT_SECONDS,
			'blocking' => $is_blocking,
			'headers' => array(
				'Content-Type' => 'application/json',
				'Authorization' => 'Bearer ' . SimpleDataLogs_Settings::get( 'ingest_key' ),
			),
			'user-agent' => 'simpledatalogs-wordpress/' . SIMPLEDATALOGS_VERSION,
		);

		// Analytics must never break the site, so transport errors are returned, never thrown.
		return wp_remote_post( SimpleDataLogs_Settings::ingest_url(), $request_arguments );
	}

	/**
	 * Returns the current UTC time in ISO 8601 with milliseconds.
	 *
	 * @return string
	 */
	private function current_timestamp() {
		$now = microtime( true );
		$milliseconds = (int) floor( ( $now - floor( $now ) ) * 1000 );

		return gmdate( 'Y-m-d\TH:i:s', (int) floor( $now ) ) . sprintf( '.%03dZ', $milliseconds );
	}

	/**
	 * Keeps only string, finite number and boolean props, as ingest charts them.
	 *
	 * @param array<string, mixed> $props The raw props.
	 * @return array<string, mixed>
	 */
	private function clean_props( array $props ) {
		$clean_props = array();

		foreach ( $props as $prop_name => $prop_value ) {
			$is_finite_number = ( is_int( $prop_value ) || is_float( $prop_value ) ) && is_finite( (float) $prop_value );

			if ( is_string( $prop_value ) || is_bool( $prop_value ) || $is_finite_number ) {
				$clean_props[ (string) $prop_name ] = $prop_value;
			}
		}

		return $clean_props;
	}
}
