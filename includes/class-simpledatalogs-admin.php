<?php
/**
 * The Settings → simpledatalogs page and its "Send test event" button.
 *
 * @package SimpleDataLogs
 */

// Stop when loaded outside WordPress.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the settings with the Settings API and handles the test event.
 */
class SimpleDataLogs_Admin {

	// The slug of the settings page under Settings.
	const PAGE_SLUG = 'simpledatalogs';

	// The settings group passed to settings_fields() and register_setting().
	const SETTINGS_GROUP = 'simpledatalogs';

	// The admin-post action and nonce name of the test button.
	const TEST_ACTION = 'simpledatalogs_send_test';

	// The transient prefix that carries the test result across the redirect.
	const TEST_RESULT_TRANSIENT = 'simpledatalogs_test_result_';

	// Only site administrators may change tracking.
	const REQUIRED_CAPABILITY = 'manage_options';

	/**
	 * The sender used by the test button.
	 *
	 * @var SimpleDataLogs_Sender
	 */
	private $sender;

	/**
	 * Keeps the sender used by the test button.
	 *
	 * @param SimpleDataLogs_Sender $sender The event sender.
	 */
	public function __construct( SimpleDataLogs_Sender $sender ) {
		$this->sender = $sender;
	}

	/**
	 * Registers the admin hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_' . self::TEST_ACTION, array( $this, 'handle_test_event' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( SIMPLEDATALOGS_PLUGIN_FILE ), array( $this, 'add_settings_link' ) );
	}

	/**
	 * Adds Settings → simpledatalogs.
	 *
	 * @return void
	 */
	public function add_settings_page() {
		add_options_page(
			__( 'simpledatalogs', 'simpledatalogs' ),
			__( 'simpledatalogs', 'simpledatalogs' ),
			self::REQUIRED_CAPABILITY,
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Adds a Settings link next to Deactivate on the Plugins screen.
	 *
	 * @param string[] $action_links The existing links.
	 * @return string[]
	 */
	public function add_settings_link( $action_links ) {
		$settings_url = admin_url( 'options-general.php?page=' . self::PAGE_SLUG );
		$settings_link = sprintf( '<a href="%s">%s</a>', esc_url( $settings_url ), esc_html__( 'Settings', 'simpledatalogs' ) );

		array_unshift( $action_links, $settings_link );

		return $action_links;
	}

	/**
	 * Registers the option, its sections and its fields.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			self::SETTINGS_GROUP,
			SimpleDataLogs_Settings::OPTION_NAME,
			array(
				'type' => 'array',
				'default' => SimpleDataLogs_Settings::defaults(),
				'sanitize_callback' => array( 'SimpleDataLogs_Settings', 'sanitize' ),
			)
		);

		// Connection: the key and the host.
		add_settings_section( 'simpledatalogs_connection', __( 'Connection', 'simpledatalogs' ), '__return_false', self::PAGE_SLUG );
		add_settings_field( 'ingest_key', __( 'Public key', 'simpledatalogs' ), array( $this, 'render_key_field' ), self::PAGE_SLUG, 'simpledatalogs_connection', array( 'label_for' => 'simpledatalogs-ingest-key' ) );
		add_settings_field( 'ingest_host', __( 'Ingest host', 'simpledatalogs' ), array( $this, 'render_host_field' ), self::PAGE_SLUG, 'simpledatalogs_connection', array( 'label_for' => 'simpledatalogs-ingest-host' ) );

		// Browser: what the SDK captures on public pages.
		add_settings_section( 'simpledatalogs_browser', __( 'Browser', 'simpledatalogs' ), '__return_false', self::PAGE_SLUG );
		add_settings_field( 'browser_toggles', __( 'Capture', 'simpledatalogs' ), array( $this, 'render_browser_fields' ), self::PAGE_SLUG, 'simpledatalogs_browser' );
		add_settings_field( 'excluded_roles', __( 'Never track', 'simpledatalogs' ), array( $this, 'render_roles_field' ), self::PAGE_SLUG, 'simpledatalogs_browser' );

		// Server: events sent by WordPress itself.
		add_settings_section( 'simpledatalogs_server', __( 'Server-side events', 'simpledatalogs' ), '__return_false', self::PAGE_SLUG );
		add_settings_field( 'server_events', __( 'Send', 'simpledatalogs' ), array( $this, 'render_server_fields' ), self::PAGE_SLUG, 'simpledatalogs_server' );
	}

	/**
	 * Renders the public key input.
	 *
	 * @return void
	 */
	public function render_key_field() {
		printf(
			'<input type="text" id="simpledatalogs-ingest-key" name="%s[ingest_key]" value="%s" class="regular-text code" placeholder="sdl_pub_…" autocomplete="off" spellcheck="false" />',
			esc_attr( SimpleDataLogs_Settings::OPTION_NAME ),
			esc_attr( (string) SimpleDataLogs_Settings::get( 'ingest_key' ) )
		);

		printf( '<p class="description">%s</p>', esc_html__( 'Find it in your project settings. Only public keys belong in web pages.', 'simpledatalogs' ) );
	}

	/**
	 * Renders the ingest host override input.
	 *
	 * @return void
	 */
	public function render_host_field() {
		printf(
			'<input type="text" id="simpledatalogs-ingest-host" name="%s[ingest_host]" value="%s" class="regular-text code" placeholder="%s" spellcheck="false" />',
			esc_attr( SimpleDataLogs_Settings::OPTION_NAME ),
			esc_attr( (string) SimpleDataLogs_Settings::get( 'ingest_host' ) ),
			esc_attr( SimpleDataLogs_Settings::DEFAULT_INGEST_ENDPOINT )
		);

		printf( '<p class="description">%s</p>', esc_html__( 'Leave empty unless support gave you another host.', 'simpledatalogs' ) );
	}

	/**
	 * Renders the page view, autocapture and Do Not Track toggles.
	 *
	 * @return void
	 */
	public function render_browser_fields() {
		echo '<fieldset>';

		$this->render_checkbox( 'track_page_views', __( 'Page views', 'simpledatalogs' ) );
		$this->render_checkbox( 'autocapture', __( 'Autocapture: page views, sessions and JavaScript errors', 'simpledatalogs' ) );
		$this->render_checkbox( 'respect_dnt', __( 'Respect Do Not Track and Global Privacy Control', 'simpledatalogs' ) );

		echo '</fieldset>';

		printf( '<p class="description">%s</p>', esc_html__( 'No cookies are set. Autocapture always respects Do Not Track.', 'simpledatalogs' ) );
	}

	/**
	 * Renders one checkbox per role; ticked roles are never tracked.
	 *
	 * @return void
	 */
	public function render_roles_field() {
		$excluded_roles = (array) SimpleDataLogs_Settings::get( 'excluded_roles' );

		echo '<fieldset>';

		foreach ( wp_roles()->get_names() as $role_slug => $role_name ) {
			printf(
				'<label><input type="checkbox" name="%s[excluded_roles][]" value="%s" %s /> %s</label><br />',
				esc_attr( SimpleDataLogs_Settings::OPTION_NAME ),
				esc_attr( $role_slug ),
				checked( in_array( $role_slug, $excluded_roles, true ), true, false ),
				esc_html( translate_user_role( $role_name ) )
			);
		}

		echo '</fieldset>';

		printf( '<p class="description">%s</p>', esc_html__( 'Logged-in users with these roles are not tracked, in the browser or on the server.', 'simpledatalogs' ) );
	}

	/**
	 * Renders the server-side master toggle and one checkbox per event type.
	 *
	 * @return void
	 */
	public function render_server_fields() {
		$enabled_event_types = (array) SimpleDataLogs_Settings::get( 'server_event_types' );
		$is_woocommerce_active = SimpleDataLogs_Server_Events::is_woocommerce_active();

		echo '<fieldset>';

		$this->render_checkbox( 'server_events', __( 'Send server-side events', 'simpledatalogs' ) );

		echo '<div style="margin: 8px 0 0 24px;">';

		foreach ( SimpleDataLogs_Settings::server_event_labels() as $event_type => $event_label ) {
			$is_woocommerce_event = 0 === strpos( $event_type, 'woocommerce_' );

			// WooCommerce events stay visible but say when WooCommerce is missing.
			if ( $is_woocommerce_event && ! $is_woocommerce_active ) {
				/* translators: %s: the event label, such as "WooCommerce add to cart". */
				$event_label = sprintf( __( '%s (WooCommerce is not active)', 'simpledatalogs' ), $event_label );
			}

			printf(
				'<label><input type="checkbox" name="%s[server_event_types][]" value="%s" %s /> %s</label><br />',
				esc_attr( SimpleDataLogs_Settings::OPTION_NAME ),
				esc_attr( $event_type ),
				checked( in_array( $event_type, $enabled_event_types, true ), true, false ),
				esc_html( $event_label )
			);
		}

		echo '</div></fieldset>';

		printf( '<p class="description">%s</p>', esc_html__( 'Sent from your server with the public key. No names, emails or IP addresses are sent.', 'simpledatalogs' ) );
	}

	/**
	 * Renders the settings page with its form and the test button.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( self::REQUIRED_CAPABILITY ) ) {
			return;
		}

		echo '<div class="wrap">';
		printf( '<h1>%s</h1>', esc_html( get_admin_page_title() ) );

		// The result of the last test, carried across the redirect.
		$this->render_test_result_notice();

		// The settings form, saved through options.php with its own nonce.
		echo '<form action="options.php" method="post">';
		settings_fields( self::SETTINGS_GROUP );
		do_settings_sections( self::PAGE_SLUG );
		submit_button();
		echo '</form>';

		// The test button posts to admin-post.php with its own nonce.
		printf( '<h2>%s</h2>', esc_html__( 'Test', 'simpledatalogs' ) );
		printf( '<form action="%s" method="post">', esc_url( admin_url( 'admin-post.php' ) ) );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::TEST_ACTION ) );
		wp_nonce_field( self::TEST_ACTION );
		submit_button( __( 'Send test event', 'simpledatalogs' ), 'secondary', 'submit', false, SimpleDataLogs_Settings::has_valid_key() ? array() : array( 'disabled' => 'disabled' ) );
		printf( '<p class="description">%s</p>', esc_html__( 'Sends one setup_test event with the saved key and shows the answer.', 'simpledatalogs' ) );
		echo '</form>';

		echo '</div>';
	}

	/**
	 * Sends the test event, stores the answer for the redirect, and goes back to the page.
	 *
	 * @return void
	 */
	public function handle_test_event() {
		// Only administrators, and only from the settings page form.
		if ( ! current_user_can( self::REQUIRED_CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'simpledatalogs' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( self::TEST_ACTION );

		// Send only when a valid key is saved.
		$test_result = SimpleDataLogs_Settings::has_valid_key() ? $this->sender->send_test_event() : 0;
		$stored_result = is_wp_error( $test_result ) ? 'error' : (string) $test_result;

		set_transient( self::TEST_RESULT_TRANSIENT . get_current_user_id(), $stored_result, MINUTE_IN_SECONDS );

		wp_safe_redirect( admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) );
		exit;
	}

	/**
	 * Returns the notice class and text for a stored test result.
	 *
	 * @param string $stored_result An HTTP status, '0' for no key, or 'error'.
	 * @return array{0: string, 1: string}
	 */
	public static function describe_test_result( $stored_result ) {
		if ( '202' === $stored_result ) {
			return array( 'notice-success', __( 'Test event accepted (202). It shows up in your project within a few seconds.', 'simpledatalogs' ) );
		}

		if ( '401' === $stored_result ) {
			return array( 'notice-error', __( 'The key was refused (401). Check that it is a current public key.', 'simpledatalogs' ) );
		}

		if ( '0' === $stored_result ) {
			return array( 'notice-error', __( 'Save a valid public key first.', 'simpledatalogs' ) );
		}

		if ( 'error' === $stored_result ) {
			return array( 'notice-error', __( 'The ingest host could not be reached from this server.', 'simpledatalogs' ) );
		}

		/* translators: %s: the HTTP status code the ingest host answered. */
		return array( 'notice-error', sprintf( __( 'Ingest answered %s. Try again in a minute.', 'simpledatalogs' ), $stored_result ) );
	}

	/**
	 * Renders and clears the notice for the last test result.
	 *
	 * @return void
	 */
	private function render_test_result_notice() {
		$transient_name = self::TEST_RESULT_TRANSIENT . get_current_user_id();
		$stored_result = get_transient( $transient_name );

		if ( false === $stored_result ) {
			return;
		}

		delete_transient( $transient_name );

		list( $notice_class, $notice_text ) = self::describe_test_result( (string) $stored_result );

		printf( '<div class="notice %s is-dismissible"><p>%s</p></div>', esc_attr( $notice_class ), esc_html( $notice_text ) );
	}

	/**
	 * Renders one labelled checkbox bound to a boolean setting.
	 *
	 * @param string $setting_name The key inside the option array.
	 * @param string $label_text   The visible label.
	 * @return void
	 */
	private function render_checkbox( $setting_name, $label_text ) {
		printf(
			'<label><input type="checkbox" name="%s[%s]" value="1" %s /> %s</label><br />',
			esc_attr( SimpleDataLogs_Settings::OPTION_NAME ),
			esc_attr( $setting_name ),
			checked( (bool) SimpleDataLogs_Settings::get( $setting_name ), true, false ),
			esc_html( $label_text )
		);
	}
}
