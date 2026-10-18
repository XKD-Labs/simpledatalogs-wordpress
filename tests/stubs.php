<?php
/**
 * Minimal stand-ins for the WordPress classes the plugin type-checks against.
 *
 * @package SimpleDataLogs
 */

/**
 * A user with an id and roles, like WP_User.
 */
class WP_User {

	/**
	 * The user id; 0 means a visitor.
	 *
	 * @var int
	 */
	public $ID = 0;

	/**
	 * The user's role slugs.
	 *
	 * @var string[]
	 */
	public $roles = array();

	/**
	 * Builds a user with an id and roles.
	 *
	 * @param int      $user_id    The user id.
	 * @param string[] $user_roles The role slugs.
	 */
	public function __construct( $user_id = 0, array $user_roles = array() ) {
		$this->ID = $user_id;
		$this->roles = $user_roles;
	}
}

/**
 * A transport error, like WP_Error.
 */
class WP_Error {
}

/**
 * Thrown by the wp_die stub so tests can assert on it.
 */
class SimpleDataLogs_Test_Died extends RuntimeException {
}
