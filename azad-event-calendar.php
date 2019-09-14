<?php
/* 
Plugin Name: Azad Event Calendar
Description: A very simple gutenberg practice.
Plugin URi: gittechs.com/plugin/azad-gutenberg 
Author: Md. Abul Kalam Azad
Author URI: gittechs.com/author
Author Email: webdevazad@gmail.com
Version: 0.0.0.1
License: GPL2
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Text Domain: azad-gutenberg
Domain Path: /languages

@package: azad-gutenberg
*/
if(! defined('ABSPATH')) exit;

define( 'TRIBE_EVENTS_FILE', __FILE__ );

// Load the required php min version functions.
require_once dirname( TRIBE_EVENTS_FILE ) . '/src/functions/php-min-version.php';

// Load the Composer autoload file.
require_once dirname( TRIBE_EVENTS_FILE ) . '/vendor/autoload.php';

/**
 * Verifies if we need to warn the user about min PHP version and bail to avoid fatals
 */
if ( tribe_is_not_min_php_version() ) {
//	tribe_not_php_version_textdomain( 'the-events-calendar', TRIBE_EVENTS_FILE );

	/**
	 * Include the plugin name into the correct place
	 *
	 * @since  4.8
	 *
	 * @param  array $names current list of names.
	 *
	 * @return array
	 */
	function tribe_events_not_php_version_plugin_name( $names ) {
		$names['the-events-calendar'] = esc_html__( 'The Events Calendar', 'the-events-calendar' );
		return $names;
	}

	add_filter( 'tribe_not_php_version_names', 'tribe_events_not_php_version_plugin_name' );
	if ( ! has_filter( 'admin_notices', 'tribe_not_php_version_notice' ) ) {
		//add_action( 'admin_notices', 'tribe_not_php_version_notice' );
	}
	return false;
}

/**
 * Loads the action plugin
 */
require_once dirname( TRIBE_EVENTS_FILE ) . '/src/Tribe/Main.php';

//Tribe__Events__Main::instance();

//register_activation_hook( TRIBE_EVENTS_FILE, array( 'Tribe__Events__Main', 'activate' ) );
//register_deactivation_hook( TRIBE_EVENTS_FILE, array( 'Tribe__Events__Main', 'deactivate' ) );

