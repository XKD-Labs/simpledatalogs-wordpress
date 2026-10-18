<?php
/**
 * Turns WordPress and WooCommerce actions into server-side events.
 *
 * @package SimpleDataLogs
 */

// Stop when loaded outside WordPress.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Listens to the enabled actions and records one event per action, with no personal data.
 */
class SimpleDataLogs_Server_Events {

	/**
	 * The sender that batches and posts the events.
	 *
	 * @var SimpleDataLogs_Sender
	 */
	private $sender;

	/**
	 * Keeps the sender used for every event.
	 *
	 * @param SimpleDataLogs_Sender $sender The event sender.
	 */
	public function __construct( SimpleDataLogs_Sender $sender ) {
		$this->sender = $sender;
	}

	/**
	 * Tells whether WooCommerce is active on this site.
	 *
	 * @return bool
	 */
	public static function is_woocommerce_active() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Hooks into the actions behind each event; WooCommerce hooks only when it is active.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'user_register', array( $this, 'handle_user_register' ), 10, 1 );
		add_action( 'wp_login', array( $this, 'handle_wp_login' ), 10, 2 );
		add_action( 'comment_post', array( $this, 'handle_comment_post' ), 10, 2 );

		if ( self::is_woocommerce_active() ) {
			add_action( 'woocommerce_order_status_completed', array( $this, 'handle_order_completed' ), 10, 1 );
			add_action( 'woocommerce_add_to_cart', array( $this, 'handle_add_to_cart' ), 10, 4 );
		}
	}

	/**
	 * Tells whether an event type is on and the acting user is not excluded.
	 *
	 * @param string       $event_type  The setting value of the event type.
	 * @param WP_User|null $acting_user The user behind the action, or null for a visitor.
	 * @return bool
	 */
	public function should_send( $event_type, $acting_user ) {
		if ( ! SimpleDataLogs_Settings::get( 'server_events' ) ) {
			return false;
		}

		// Only the event types ticked on the settings page.
		if ( ! in_array( $event_type, (array) SimpleDataLogs_Settings::get( 'server_event_types' ), true ) ) {
			return false;
		}

		return ! SimpleDataLogs_Settings::is_user_excluded( $acting_user );
	}

	/**
	 * Records user_registered with the new user's role.
	 *
	 * @param int $user_id The new user's id.
	 * @return void
	 */
	public function handle_user_register( $user_id ) {
		if ( ! $this->should_send( 'user_register', wp_get_current_user() ) ) {
			return;
		}

		$this->sender->track( 'user_registered', array( 'role' => $this->primary_role( get_userdata( $user_id ) ) ) );
	}

	/**
	 * Records user_logged_in with the user's role.
	 *
	 * @param string  $user_login The login name, never sent.
	 * @param WP_User $user       The user who logged in.
	 * @return void
	 */
	public function handle_wp_login( $user_login, $user ) {
		if ( ! $this->should_send( 'wp_login', $user ) ) {
			return;
		}

		$this->sender->track( 'user_logged_in', array( 'role' => $this->primary_role( $user ) ) );
	}

	/**
	 * Records comment_posted with the post type and moderation status.
	 *
	 * @param int        $comment_id       The new comment's id.
	 * @param int|string $comment_approved 1, 0, 'spam' or 'trash'.
	 * @return void
	 */
	public function handle_comment_post( $comment_id, $comment_approved ) {
		if ( ! $this->should_send( 'comment_post', wp_get_current_user() ) ) {
			return;
		}

		$comment = get_comment( $comment_id );
		$post_type = $comment ? (string) get_post_type( (int) $comment->comment_post_ID ) : '';

		$this->sender->track(
			'comment_posted',
			array(
				'status' => $this->comment_status( $comment_approved ),
				'post_type' => $post_type,
			)
		);
	}

	/**
	 * Records order_completed with the total, currency, item count and payment method.
	 *
	 * @param int $order_id The completed order's id.
	 * @return void
	 */
	public function handle_order_completed( $order_id ) {
		if ( ! $this->should_send( 'woocommerce_order_completed', wp_get_current_user() ) ) {
			return;
		}

		$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : false;

		if ( ! $order ) {
			return;
		}

		$this->sender->track(
			'order_completed',
			array(
				'total' => (float) $order->get_total(),
				'currency' => (string) $order->get_currency(),
				'item_count' => (int) $order->get_item_count(),
				'payment_method' => (string) $order->get_payment_method(),
			)
		);
	}

	/**
	 * Records add_to_cart with the product id and quantity.
	 *
	 * @param string $cart_item_key The cart line key, never sent.
	 * @param int    $product_id    The product added.
	 * @param int    $quantity      How many were added.
	 * @param int    $variation_id  The variation added, or 0.
	 * @return void
	 */
	public function handle_add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id = 0 ) {
		if ( ! $this->should_send( 'woocommerce_add_to_cart', wp_get_current_user() ) ) {
			return;
		}

		$cart_props = array(
			'product_id' => (int) $product_id,
			'quantity' => (int) $quantity,
		);

		// Variations are kept only when one was chosen.
		if ( (int) $variation_id > 0 ) {
			$cart_props['variation_id'] = (int) $variation_id;
		}

		$this->sender->track( 'add_to_cart', $cart_props );
	}

	/**
	 * Returns the first role of a user, or an empty string.
	 *
	 * @param WP_User|false|null $user The user.
	 * @return string
	 */
	private function primary_role( $user ) {
		if ( ! $user instanceof WP_User || array() === (array) $user->roles ) {
			return '';
		}

		$user_roles = array_values( (array) $user->roles );

		return (string) $user_roles[0];
	}

	/**
	 * Maps comment_post's approval value to a readable status.
	 *
	 * @param int|string $comment_approved 1, 0, 'spam' or 'trash'.
	 * @return string
	 */
	private function comment_status( $comment_approved ) {
		if ( 1 === $comment_approved || '1' === $comment_approved ) {
			return 'approved';
		}

		if ( 0 === $comment_approved || '0' === $comment_approved ) {
			return 'pending';
		}

		return sanitize_key( (string) $comment_approved );
	}
}
