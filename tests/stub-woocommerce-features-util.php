<?php
/**
 * A stand-in for WooCommerce's FeaturesUtil that records each compatibility declaration.
 *
 * @package SimpleDataLogs
 */

namespace Automattic\WooCommerce\Utilities;

/**
 * Records declare_compatibility calls instead of telling WooCommerce.
 */
class FeaturesUtil {

	/**
	 * Every declaration made, as feature, plugin file and whether it is compatible.
	 *
	 * @var array<int, array{0: string, 1: string, 2: bool}>
	 */
	public static $declarations = array();

	/**
	 * Records one declaration.
	 *
	 * @param string $feature_id    The WooCommerce feature.
	 * @param string $plugin_file   The plugin's main file.
	 * @param bool   $is_compatible Whether the plugin works with it.
	 * @return bool
	 */
	public static function declare_compatibility( $feature_id, $plugin_file, $is_compatible = true ) {
		self::$declarations[] = array( $feature_id, $plugin_file, $is_compatible );
		return true;
	}
}
