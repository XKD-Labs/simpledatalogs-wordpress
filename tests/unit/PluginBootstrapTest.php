<?php
/**
 * Tests for the plugin bootstrap: the WooCommerce compatibility declaration.
 *
 * @package SimpleDataLogs
 */

use Automattic\WooCommerce\Utilities\FeaturesUtil;

/**
 * Checks what the bootstrap tells WooCommerce.
 */
class PluginBootstrapTest extends SimpleDataLogs_Test_Case {

	/**
	 * The plugin declares order tables (HPOS) support for its own main file.
	 *
	 * @return void
	 */
	public function test_declares_woocommerce_order_tables_support() {
		FeaturesUtil::$declarations = array();

		SimpleDataLogs_Plugin_Bootstrap::declare_woocommerce_compatibility();

		$this->assertSame( array( array( 'custom_order_tables', SIMPLEDATALOGS_PLUGIN_FILE, true ) ), FeaturesUtil::$declarations );
	}
}
