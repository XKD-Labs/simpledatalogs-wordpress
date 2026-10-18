<?php
/**
 * Loads the bundled browser SDK on public pages.
 *
 * @package SimpleDataLogs
 */

// Stop when loaded outside WordPress.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueues the SDK script tag with its data-* attributes and the small init script.
 */
class SimpleDataLogs_Frontend {

	// The handle of the bundled SDK script.
	const SDK_HANDLE = 'simpledatalogs-sdk';

	// The handle of the plugin's init script, which runs after the SDK.
	const INIT_HANDLE = 'simpledatalogs-init';

	/**
	 * Registers the front-end hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_filter( 'script_loader_tag', array( $this, 'add_sdk_attributes' ), 10, 2 );
	}

	/**
	 * Tells whether this page view should load the SDK at all.
	 *
	 * @return bool
	 */
	public function should_track_current_request() {
		// Never track wp-admin, the customizer preview or feeds.
		if ( is_admin() || is_customize_preview() || is_feed() ) {
			return false;
		}

		// Without a valid public key there is nothing to load.
		if ( ! SimpleDataLogs_Settings::has_valid_key() ) {
			return false;
		}

		// Excluded roles are never tracked.
		return ! SimpleDataLogs_Settings::is_user_excluded( wp_get_current_user() );
	}

	/**
	 * Returns the settings the init script reads from window.simpledatalogsWordPress.
	 *
	 * @return array<string, bool>
	 */
	public function init_config() {
		return array(
			'autocapture' => (bool) SimpleDataLogs_Settings::get( 'autocapture' ),
			'pageViews' => (bool) SimpleDataLogs_Settings::get( 'track_page_views' ),
			'respectDoNotTrack' => (bool) SimpleDataLogs_Settings::get( 'respect_dnt' ),
		);
	}

	/**
	 * Enqueues the SDK and the init script, both deferred, in the page head.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		if ( ! $this->should_track_current_request() ) {
			return;
		}

		$script_arguments = array(
			'strategy' => 'defer',
			'in_footer' => false,
		);

		// The SDK reads its key and options from its own tag, added in add_sdk_attributes.
		wp_enqueue_script(
			self::SDK_HANDLE,
			plugins_url( 'assets/js/simpledatalogs.min.js', SIMPLEDATALOGS_PLUGIN_FILE ),
			array(),
			SIMPLEDATALOGS_SDK_VERSION,
			$script_arguments
		);

		// The init script runs after the SDK, since deferred scripts keep their order.
		wp_enqueue_script(
			self::INIT_HANDLE,
			plugins_url( 'assets/js/simpledatalogs-init.js', SIMPLEDATALOGS_PLUGIN_FILE ),
			array( self::SDK_HANDLE ),
			SIMPLEDATALOGS_VERSION,
			$script_arguments
		);

		// The settings go in a "before" inline script, which keeps both scripts deferred.
		wp_add_inline_script(
			self::INIT_HANDLE,
			'window.simpledatalogsWordPress = ' . wp_json_encode( $this->init_config() ) . ';',
			'before'
		);
	}

	/**
	 * Returns the data-* attributes the SDK script tag reads.
	 *
	 * @return array<string, string>
	 */
	public function sdk_attributes() {
		$attributes = array( 'data-key' => (string) SimpleDataLogs_Settings::get( 'ingest_key' ) );

		// Only an override needs data-endpoint; the SDK defaults to the edge host.
		if ( '' !== (string) SimpleDataLogs_Settings::get( 'ingest_host' ) ) {
			$attributes['data-endpoint'] = SimpleDataLogs_Settings::ingest_endpoint();
		}

		// The script tag turns autocapture on by default, so opting out is explicit.
		if ( ! SimpleDataLogs_Settings::get( 'autocapture' ) ) {
			$attributes['data-autocapture'] = 'false';
		}

		return $attributes;
	}

	/**
	 * Adds the data-* attributes to the SDK's script tag.
	 *
	 * @param string $script_tag The script tag WordPress built.
	 * @param string $handle     The script's handle.
	 * @return string
	 */
	public function add_sdk_attributes( $script_tag, $handle ) {
		if ( self::SDK_HANDLE !== $handle ) {
			return $script_tag;
		}

		$attribute_markup = '';

		foreach ( $this->sdk_attributes() as $attribute_name => $attribute_value ) {
			$attribute_markup .= sprintf( ' %s="%s"', $attribute_name, esc_attr( $attribute_value ) );
		}

		// Insert the attributes into the tag that loads the file, not into any inline script.
		return preg_replace_callback(
			'/<script\b(?=[^>]*\ssrc=)/',
			static function () use ( $attribute_markup ) {
				return '<script' . $attribute_markup;
			},
			$script_tag,
			1
		);
	}
}
