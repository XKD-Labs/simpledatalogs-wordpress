<?php
/**
 * The plugin's one option: defaults, reading and sanitizing.
 *
 * @package SimpleDataLogs
 */

// Stop when loaded outside WordPress.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and sanitizes the simpledatalogs_settings option.
 */
final class SimpleDataLogs_Settings {

	// The name of the single array option that holds every setting.
	const OPTION_NAME = 'simpledatalogs_settings';

	// The ingest host used when no override is set.
	const DEFAULT_INGEST_ENDPOINT = 'https://edge.simpledatalogs.com';

	// A public key is sdl_pub_ followed by 32 base62 characters.
	const PUBLIC_KEY_PATTERN = '/^sdl_pub_[A-Za-z0-9]{32}$/';

	// A secret key must never be stored here, since it would end up in web pages.
	const SECRET_KEY_PREFIX = 'sdl_sec_';

	/**
	 * Lists the server-side events a site can turn on, keyed by setting value.
	 *
	 * @return array<string, string>
	 */
	public static function server_event_labels() {
		return array(
			'user_register' => __( 'New user registered', 'simpledatalogs' ),
			'wp_login' => __( 'User logged in', 'simpledatalogs' ),
			'comment_post' => __( 'Comment posted', 'simpledatalogs' ),
			'woocommerce_order_completed' => __( 'WooCommerce order completed', 'simpledatalogs' ),
			'woocommerce_add_to_cart' => __( 'WooCommerce add to cart', 'simpledatalogs' ),
		);
	}

	/**
	 * Returns the settings a fresh install starts with.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		return array(
			'ingest_key' => '',
			'ingest_host' => '',
			'autocapture' => false,
			'respect_dnt' => true,
			'excluded_roles' => array( 'administrator' ),
			'server_events' => false,
			'track_page_views' => true,
			'server_event_types' => array_keys( self::server_event_labels() ),
		);
	}

	/**
	 * Returns the saved settings merged over the defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function all() {
		$saved_settings = get_option( self::OPTION_NAME, array() );

		// A broken or foreign value falls back to the defaults.
		if ( ! is_array( $saved_settings ) ) {
			$saved_settings = array();
		}

		return array_merge( self::defaults(), $saved_settings );
	}

	/**
	 * Returns one setting by name.
	 *
	 * @param string $setting_name The key inside the option array.
	 * @return mixed
	 */
	public static function get( $setting_name ) {
		$settings = self::all();

		return isset( $settings[ $setting_name ] ) ? $settings[ $setting_name ] : null;
	}

	/**
	 * Tells whether a valid public key is saved, which every kind of tracking needs.
	 *
	 * @return bool
	 */
	public static function has_valid_key() {
		return self::is_public_key( (string) self::get( 'ingest_key' ) );
	}

	/**
	 * Tells whether a string has the shape of a public ingest key.
	 *
	 * @param string $candidate_key The value to check.
	 * @return bool
	 */
	public static function is_public_key( $candidate_key ) {
		return 1 === preg_match( self::PUBLIC_KEY_PATTERN, $candidate_key );
	}

	/**
	 * Returns the ingest base URL, the override when set or the default host.
	 *
	 * @return string
	 */
	public static function ingest_endpoint() {
		$ingest_host = (string) self::get( 'ingest_host' );

		return '' === $ingest_host ? self::DEFAULT_INGEST_ENDPOINT : $ingest_host;
	}

	/**
	 * Returns the full URL of the native ingest path.
	 *
	 * @return string
	 */
	public static function ingest_url() {
		return self::ingest_endpoint() . '/v1/ingest';
	}

	/**
	 * Tells whether a user holds any role excluded from tracking.
	 *
	 * @param WP_User|null $user The user to check, or null for a visitor.
	 * @return bool
	 */
	public static function is_user_excluded( $user ) {
		if ( ! $user instanceof WP_User || 0 === (int) $user->ID ) {
			return false;
		}

		$excluded_roles = (array) self::get( 'excluded_roles' );

		return array() !== array_intersect( (array) $user->roles, $excluded_roles );
	}

	/**
	 * Cleans the submitted settings form before WordPress saves it.
	 *
	 * @param mixed $submitted_settings The raw value from the settings form.
	 * @return array<string, mixed>
	 */
	public static function sanitize( $submitted_settings ) {
		$submitted_settings = is_array( $submitted_settings ) ? $submitted_settings : array();
		$previous_settings = self::all();

		// Each toggle is a checkbox: present means on, absent means off.
		$clean_settings = array(
			'autocapture' => ! empty( $submitted_settings['autocapture'] ),
			'respect_dnt' => ! empty( $submitted_settings['respect_dnt'] ),
			'server_events' => ! empty( $submitted_settings['server_events'] ),
			'track_page_views' => ! empty( $submitted_settings['track_page_views'] ),
		);

		// The key: refuse secret keys and malformed values, keeping the previous key.
		$clean_settings['ingest_key'] = self::sanitize_ingest_key(
			isset( $submitted_settings['ingest_key'] ) ? $submitted_settings['ingest_key'] : '',
			(string) $previous_settings['ingest_key']
		);

		// The host override: a bare host or a URL, reduced to scheme, host and port.
		$clean_settings['ingest_host'] = self::sanitize_ingest_host(
			isset( $submitted_settings['ingest_host'] ) ? $submitted_settings['ingest_host'] : '',
			(string) $previous_settings['ingest_host']
		);

		// Roles: keep only slugs of roles that exist on this site.
		$clean_settings['excluded_roles'] = self::filter_known_values(
			isset( $submitted_settings['excluded_roles'] ) ? $submitted_settings['excluded_roles'] : array(),
			array_keys( wp_roles()->get_names() )
		);

		// Server events: keep only the event types this plugin knows.
		$clean_settings['server_event_types'] = self::filter_known_values(
			isset( $submitted_settings['server_event_types'] ) ? $submitted_settings['server_event_types'] : array(),
			array_keys( self::server_event_labels() )
		);

		return $clean_settings;
	}

	/**
	 * Returns a cleaned public key, or the previous key with an error when the input is unusable.
	 *
	 * @param mixed  $submitted_key The raw key from the form.
	 * @param string $previous_key  The key saved before this submission.
	 * @return string
	 */
	public static function sanitize_ingest_key( $submitted_key, $previous_key ) {
		$cleaned_key = trim( sanitize_text_field( is_string( $submitted_key ) ? $submitted_key : '' ) );

		// An empty field clears the key and turns tracking off.
		if ( '' === $cleaned_key ) {
			return '';
		}

		// A secret key in a web page would let anyone read it, so it is never saved.
		if ( 0 === strpos( $cleaned_key, self::SECRET_KEY_PREFIX ) ) {
			add_settings_error( self::OPTION_NAME, 'secret_key', __( 'That is a secret key. Use a public key (sdl_pub_…) here; secret keys must never be in web pages.', 'simpledatalogs' ) );

			return $previous_key;
		}

		// Anything else that is not a public key keeps the previous value.
		if ( ! self::is_public_key( $cleaned_key ) ) {
			add_settings_error( self::OPTION_NAME, 'invalid_key', __( 'The public key should look like sdl_pub_ followed by 32 letters and digits.', 'simpledatalogs' ) );

			return $previous_key;
		}

		return $cleaned_key;
	}

	/**
	 * Returns the ingest host as scheme://host[:port], or the previous value with an error when it does not parse.
	 *
	 * @param mixed  $submitted_host The raw host or URL from the form.
	 * @param string $previous_host  The host saved before this submission.
	 * @return string
	 */
	public static function sanitize_ingest_host( $submitted_host, $previous_host ) {
		$cleaned_host = trim( sanitize_text_field( is_string( $submitted_host ) ? $submitted_host : '' ) );

		// An empty field means the default host.
		if ( '' === $cleaned_host ) {
			return '';
		}

		// A bare host gets https.
		if ( false === strpos( $cleaned_host, '://' ) ) {
			$cleaned_host = 'https://' . $cleaned_host;
		}

		$url_parts = wp_parse_url( $cleaned_host );
		$scheme = isset( $url_parts['scheme'] ) ? strtolower( $url_parts['scheme'] ) : '';
		$host = isset( $url_parts['host'] ) ? strtolower( $url_parts['host'] ) : '';

		// Plain http is allowed only for local development hosts.
		$is_local_host = in_array( $host, array( 'localhost', '127.0.0.1' ), true ) || '.test' === substr( $host, -5 );
		$is_scheme_allowed = 'https' === $scheme || ( 'http' === $scheme && $is_local_host );

		if ( '' === $host || ! $is_scheme_allowed || 1 !== preg_match( '/^[a-z0-9.-]+$/', $host ) ) {
			add_settings_error( self::OPTION_NAME, 'invalid_host', __( 'The ingest host should be a host name such as edge.simpledatalogs.com, served over https.', 'simpledatalogs' ) );

			return $previous_host;
		}

		// Paths and queries are dropped; the plugin adds /v1/ingest itself.
		$port_suffix = isset( $url_parts['port'] ) ? ':' . (int) $url_parts['port'] : '';

		return $scheme . '://' . $host . $port_suffix;
	}

	/**
	 * Keeps only the submitted strings that appear in the allowed list.
	 *
	 * @param mixed    $submitted_values The raw list from the form.
	 * @param string[] $allowed_values   The values that may be kept.
	 * @return string[]
	 */
	public static function filter_known_values( $submitted_values, $allowed_values ) {
		if ( ! is_array( $submitted_values ) ) {
			return array();
		}

		$cleaned_values = array_map( 'sanitize_key', array_filter( $submitted_values, 'is_string' ) );

		return array_values( array_intersect( $cleaned_values, $allowed_values ) );
	}
}
