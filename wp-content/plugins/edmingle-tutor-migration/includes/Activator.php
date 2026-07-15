<?php
/**
 * Fired during plugin activation
 *
 * @package Edmingle_Tutor_Migration\Includes
 */

namespace ETM\Includes;

class Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		// Flush rewrite rules, setup initial options, create custom database tables, etc.
		require_once ETM_PLUGIN_DIR . 'includes/class-database.php';
		\ETM\Includes\ETM_Database::create_tables();
	}
}
