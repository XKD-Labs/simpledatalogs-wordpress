<?php
/**
 * Tests for SimpleDataLogs_Server_Events.
 *
 * @package SimpleDataLogs
 */

use Brain\Monkey\Functions;

/**
 * Covers the toggles, role exclusion and the props of each event.
 */
class ServerEventsTest extends SimpleDataLogs_Test_Case {

	/**
	 * The mocked sender that receives tracked events.
	 *
	 * @var Mockery\MockInterface
	 */
	private $sender;

	/**
	 * Turns server events on with every type and a visitor as the acting user.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->use_settings(
			array(
				'ingest_key' => self::VALID_KEY,
				'server_events' => true,
			)
		);

		$this->sender = Mockery::mock( SimpleDataLogs_Sender::class );

		Functions\when( 'wp_get_current_user' )->justReturn( new WP_User( 0 ) );
	}

	/**
	 * Builds the class under test around the mocked sender.
	 *
	 * @return SimpleDataLogs_Server_Events
	 */
	private function server_events() {
		return new SimpleDataLogs_Server_Events( $this->sender );
	}

	/**
	 * With the master toggle off nothing is sent.
	 */
	public function test_master_toggle_off_sends_nothing() {
		$this->use_settings( array( 'server_events' => false ) );
		$this->sender->shouldNotReceive( 'track' );

		$this->server_events()->handle_wp_login( 'someone', new WP_User( 5, array( 'subscriber' ) ) );
	}

	/**
	 * An unticked event type is not sent.
	 */
	public function test_unticked_event_type_sends_nothing() {
		$this->use_settings(
			array(
				'server_events' => true,
				'server_event_types' => array( 'comment_post' ),
			)
		);
		$this->sender->shouldNotReceive( 'track' );

		$this->server_events()->handle_wp_login( 'someone', new WP_User( 5, array( 'subscriber' ) ) );
	}

	/**
	 * An administrator logging in is excluded by default.
	 */
	public function test_excluded_role_sends_nothing() {
		$this->sender->shouldNotReceive( 'track' );

		$this->server_events()->handle_wp_login( 'admin', new WP_User( 1, array( 'administrator' ) ) );
	}

	/**
	 * A login sends the role and never the login name.
	 */
	public function test_login_sends_role_only() {
		$this->sender->shouldReceive( 'track' )->once()->with( 'user_logged_in', array( 'role' => 'subscriber' ) );

		$this->server_events()->handle_wp_login( 'jane', new WP_User( 5, array( 'subscriber' ) ) );
	}

	/**
	 * A registration sends the new user's role.
	 */
	public function test_register_sends_new_users_role() {
		Functions\when( 'get_userdata' )->justReturn( new WP_User( 9, array( 'customer' ) ) );
		$this->sender->shouldReceive( 'track' )->once()->with( 'user_registered', array( 'role' => 'customer' ) );

		$this->server_events()->handle_user_register( 9 );
	}

	/**
	 * A comment sends its moderation status and post type.
	 */
	public function test_comment_sends_status_and_post_type() {
		Functions\when( 'get_comment' )->justReturn( (object) array( 'comment_post_ID' => 12 ) );
		Functions\when( 'get_post_type' )->justReturn( 'post' );

		$this->sender->shouldReceive( 'track' )->once()->with(
			'comment_posted',
			array(
				'status' => 'pending',
				'post_type' => 'post',
			)
		);
		$this->sender->shouldReceive( 'track' )->once()->with(
			'comment_posted',
			array(
				'status' => 'spam',
				'post_type' => 'post',
			)
		);

		$this->server_events()->handle_comment_post( 3, 0 );
		$this->server_events()->handle_comment_post( 4, 'spam' );
	}

	/**
	 * Add to cart sends the product, quantity and a chosen variation.
	 */
	public function test_add_to_cart_sends_product_and_quantity() {
		$this->sender->shouldReceive( 'track' )->once()->with(
			'add_to_cart',
			array(
				'product_id' => 42,
				'quantity' => 2,
				'variation_id' => 43,
			)
		);

		$this->server_events()->handle_add_to_cart( 'cart-key', 42, 2, 43 );
	}

	/**
	 * A completed order sends totals and the gateway, not the customer.
	 */
	public function test_order_completed_sends_totals() {
		$order = Mockery::mock();
		$order->shouldReceive( 'get_total' )->andReturn( '59.90' );
		$order->shouldReceive( 'get_currency' )->andReturn( 'EUR' );
		$order->shouldReceive( 'get_item_count' )->andReturn( 3 );
		$order->shouldReceive( 'get_payment_method' )->andReturn( 'stripe' );

		Functions\when( 'wc_get_order' )->justReturn( $order );

		$this->sender->shouldReceive( 'track' )->once()->with(
			'order_completed',
			array(
				'total' => 59.9,
				'currency' => 'EUR',
				'item_count' => 3,
				'payment_method' => 'stripe',
			)
		);

		$this->server_events()->handle_order_completed( 100 );
	}

	/**
	 * Without WooCommerce only the core hooks are registered.
	 */
	public function test_woocommerce_hooks_need_woocommerce() {
		$registered_hooks = array();

		Functions\when( 'add_action' )->alias(
			static function ( $hook_name ) use ( &$registered_hooks ) {
				$registered_hooks[] = $hook_name;
			}
		);

		$this->server_events()->register_hooks();

		$this->assertFalse( SimpleDataLogs_Server_Events::is_woocommerce_active() );
		$this->assertSame( array( 'user_register', 'wp_login', 'comment_post' ), $registered_hooks );
	}
}
