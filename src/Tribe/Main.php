<?php
/**
 * Main Tribe Events Calendar class.
 */

// Don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

if ( ! class_exists( 'Tribe__Events__Main' ) ) {

	/**
	 * The Events Calendar Class
	 *
	 * This is where all the magic happens, the unicorns run wild and the leprechauns use WordPress to schedule events.
	 */
    class Tribe__Events__Main {
		/**
		 * @var bool Prevent autoload initialization
		 */
		private $should_prevent_autoload_init = false;
		// const POSTTYPE            = 'tribe_events';
		const VERSION             = '4.9.8';
		// public $singular_event_label;
		// public $plural_event_label;
		/**
		 * Args for the event post type
		 * @var array
		 */
		// protected $post_type_args = array(
		// 	'public'          => true,
		// 	'rewrite'         => array( 'slug' => 'event', 'with_front' => false ),
		// 	'menu_position'   => 6,
		// 	'supports'        => array(
		// 		'title',
		// 		'editor',
		// 		'excerpt',
		// 		'author',
		// 		'thumbnail',
		// 		'custom-fields',
		// 		'comments',
		// 		'revisions',
		// 	),
		// 	'taxonomies'      => array( 'post_tag' ),
		// 	'capability_type' => array( 'tribe_event', 'tribe_events' ),
		// 	'map_meta_cap'    => true,
		// 	'has_archive'     => true,
		// 	'menu_icon'       => 'dashicons-calendar',
		// );
		/**
		 * @var string tribe-common VERSION regex
		 */
		private $common_version_regex = "/const\s+VERSION\s*=\s*'([^']+)'/m";
        /**
		 * Static Singleton Holder
		 * @var self
		 */
		protected static $instance;

		/**
		 * Get (and instantiate, if necessary) the instance of the class
		 *
		 * @return self
		 */
		public static function instance() {
			if ( ! self::$instance ) {
				self::$instance = new self;
			}
			return self::$instance;
		}
		/**
		 * Initializes plugin variables and sets up WordPress hooks/actions.
		 */
		protected function __construct() {
			$this->plugin_file = TRIBE_EVENTS_FILE;
			$this->pluginPath  = $this->plugin_path = trailingslashit( dirname( $this->plugin_file ) );
			$this->pluginDir   = $this->plugin_dir = trailingslashit( basename( $this->plugin_path ) );
			$this->pluginUrl   = $this->plugin_url = str_replace( basename( $this->plugin_file ), '', plugins_url( basename( $this->plugin_file ), $this->plugin_file ) );

			// Set common lib information, needs to happen file load
			$this->maybe_set_common_lib_info();
			
			// let's initialize tec
			//add_action( 'plugins_loaded', array( $this, 'maybe_bail_if_old_et_is_present' ), -1 );
			// add_action( 'plugins_loaded', array( $this, 'maybe_bail_if_invalid_wp_or_php' ), -1 );
			add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ), 0 );
			
			// Prevents Image Widget Plus from been problematic
			//$this->compatibility_unload_iwplus_v102();
		}
		/**
		 * To avoid duplication of our own methods and to provide a underlying system
		 * Modern Tribe maintains a Library called Common to store a base for our plugins
		 *
		 * Currently we will read the File `common/package.json` to determine which version
		 * of the Common Lib we will pass to the Auto-Loader of PHP.
		 *
		 * In the past we used to parse `common/src/Tribe/Main.php` for the Common Lib version.
		 *
		 * @link https://github.com/moderntribe/tribe-common
		 * @see  self::init_autoloading
		 *
		 * @return void
		 */
		public function maybe_set_common_lib_info() {
			// if there isn't a tribe-common version, bail with a notice
			$common_version = file_get_contents( $this->plugin_path . 'common/src/Tribe/Main.php' );
			if ( ! preg_match( $this->common_version_regex, $common_version, $matches ) ) {
				return add_action( 'admin_head', array( $this, 'missing_common_libs' ) );
			}

			$common_version = $matches[1];

			/**
			 * If we don't have a version of Common or a Older version of the Lib
			 * overwrite what should be loaded by the auto-loader
			 */
			if (
				empty( $GLOBALS['tribe-common-info'] )
				|| version_compare( $GLOBALS['tribe-common-info']['version'], $common_version, '<' )
			) {
				$GLOBALS['tribe-common-info'] = array(
					'dir' => "{$this->plugin_path}common/src/Tribe",
					'version' => $common_version,
				);
			}
		}
		/**
		 * Prevents bootstrapping and autoloading if the version of ET that is running is too old
		 *
		 * @since 4.9.3.2
		 */
		public function maybe_bail_if_old_et_is_present() {
			// early check for an older version of Event Tickets to prevent fatal error
			if ( ! class_exists( 'Tribe__Tickets__Main' ) ) {
				return;
			}

			if ( version_compare( Tribe__Tickets__Main::VERSION, $this->min_et_version, '>=' ) ) {
				return;
			}

			$this->should_prevent_autoload_init = true;

			add_action( 'admin_notices', [ $this, 'compatibility_notice' ] );
			add_action( 'network_admin_notices', [ $this, 'compatibility_notice' ] );
			add_filter( 'tribe_ecp_to_run_or_not_to_run', [ $this, 'disable_pro' ] );
			add_action( 'tribe_plugins_loaded', [ $this, 'remove_exts' ], 0 );
			/*
			* After common was loaded by another source (e.g. Event Tickets) let's append this plugin source files
			* to the ones the Autoloader will search. Since we're appending them the ones registered by the plugin
			* "owning" common will be searched first.
			*/
			add_action( 'tribe_common_loaded', [ $this, 'register_plugin_autoload_paths' ] );

			// if we get in here, we need to reset the global common to ET's version so that we don't cause a fatal
			$this->reset_common_lib_info_back_to_et();

			// Disable older versions of Community Events to prevent fatal Error.
			remove_action( 'plugins_loaded', 'Tribe_CE_Load', 2 );
		}

		/**
		 * Prevents bootstrapping and autoloading if the version of WP or PHP are too old
		 *
		 * @since 4.9.3.2
		 */
		public function maybe_bail_if_invalid_wp_or_php() {
			if ( self::supportedVersion( 'wordpress' ) && self::supportedVersion( 'php' ) ) {
				return;
			}

			add_action( 'admin_notices', array( $this, 'notSupportedError' ) );

			// if we get in here, we need to reset the global common to ET's version so that we don't cause a fatal
			$this->reset_common_lib_info_back_to_et();

			$this->should_prevent_autoload_init = true;
		}
		/**
		 * Plugins shouldn't include their functions before `plugins_loaded` because this will allow
		 * better compatibility with the autoloader methods.
		 *
		 * @return void
		 */
		public function plugins_loaded() {
			if ( $this->should_prevent_autoload_init ) {
				return;
			}
			/**
			 * Before any methods from this plugin are called, we initialize our Autoloading
			 * After this method we can use any `Tribe__` classes
			 */
			$this->init_autoloading();

			Tribe__Main::instance();

			add_action( 'tribe_common_loaded', array( $this, 'bootstrap' ), 0 );
		}		
		/**
		 * To allow easier usage of classes on our files we have a AutoLoader that will match
		 * class names to it's required file inclusion into the Request.
		 *
		 * @return void
		 */
		protected function init_autoloading() {
			$autoloader = $this->get_autoloader_instance();
			$this->register_plugin_autoload_paths( $autoloader );

			// Deprecated classes are registered in a class to path fashion.
			foreach ( glob( $this->plugin_path . 'src/deprecated/*.php', GLOB_NOSORT ) as $file ) {
				$class_name = str_replace( '.php', '', basename( $file ) );
				$autoloader->register_class( $class_name, $file );
			}

			$autoloader->register_autoloader();
		}
		/**
		 * Registers the implementations in the container.
		 *
		 * Classes that should be built at `plugins_loaded` time are also instantiated.
		 *
		 * @since  4.4
		 *
		 * @return void
		 */
		public function bind_implementations(  ) {
			tribe_singleton( 'tec.main', $this );

			// Utils
			// tribe_singleton( 'tec.cost-utils', 'Tribe__Events__Cost_Utils' );

			// // Front page events archive support
			// tribe_singleton( 'tec.front-page-view', 'Tribe__Events__Front_Page_View' );

			// // Metabox for Single Edit
			// tribe_singleton( 'tec.admin.event-meta-box', 'Tribe__Events__Admin__Event_Meta_Box' );

			// // Featured Events
			// tribe_singleton( 'tec.featured_events', 'Tribe__Events__Featured_Events' );
			// tribe_singleton( 'tec.featured_events.query_helper', new Tribe__Events__Featured_Events__Query_Helper );
			// tribe_singleton( 'tec.featured_events.permalinks_helper', new Tribe__Events__Featured_Events__Permalinks_Helper );

			// // Event Aggregator
			// tribe_singleton( 'events-aggregator.main', 'Tribe__Events__Aggregator', array( 'load', 'hook' ) );
			// tribe_singleton( 'events-aggregator.service', 'Tribe__Events__Aggregator__Service' );
			// tribe_singleton( 'events-aggregator.settings', 'Tribe__Events__Aggregator__Settings' );
			// tribe_singleton( 'events-aggregator.records', 'Tribe__Events__Aggregator__Records', array( 'hook' ) );
			// tribe_register_provider( 'Tribe__Events__Aggregator__REST__V1__Service_Provider' );
			// tribe_register_provider( 'Tribe__Events__Aggregator__CLI__Service_Provider' );
			// tribe_register_provider( 'Tribe__Events__Aggregator__Processes__Service_Provider' );
			// tribe_register_provider( 'Tribe__Events__Editor__Provider' );

			// // Shortcodes
			// tribe_singleton( 'tec.shortcodes.event-details', 'Tribe__Events__Shortcode__Event_Details', array( 'hook' ) );

			// // Ignored Events
			// tribe_singleton( 'tec.ignored-events', 'Tribe__Events__Ignored_Events', array( 'hook' ) );

			// // Assets loader
			// tribe_singleton( 'tec.assets', 'Tribe__Events__Assets', array( 'register', 'hook' ) );

			// // Register and start the Customizer Sections
			// tribe_singleton( 'tec.customizer.general-theme', new Tribe__Events__Customizer__General_Theme() );
			// tribe_singleton( 'tec.customizer.global-elements', new Tribe__Events__Customizer__Global_Elements() );
			// tribe_singleton( 'tec.customizer.day-list-view', new Tribe__Events__Customizer__Day_List_View() );
			// tribe_singleton( 'tec.customizer.month-week-view', new Tribe__Events__Customizer__Month_Week_View() );
			// tribe_singleton( 'tec.customizer.single-event', new Tribe__Events__Customizer__Single_Event() );
			// tribe_singleton( 'tec.customizer.widget', new Tribe__Events__Customizer__Widget() );

			// // Tribe Bar
			// tribe_singleton( 'tec.bar', 'Tribe__Events__Bar', array( 'hook' ) );

			// // iCal
			// tribe_singleton( 'tec.iCal', 'Tribe__Events__iCal', array( 'hook' ) );

			// // REST API v1
			// tribe_singleton( 'tec.rest-v1.main', 'Tribe__Events__REST__V1__Main', array( 'bind_implementations', 'hook' ) );
			// tribe( 'tec.rest-v1.main' );

			// // Integrations
			// tribe_singleton( 'tec.integrations.twenty-seventeen', 'Tribe__Events__Integrations__Twenty_Seventeen', array( 'hook' ) );

			// // Linked Posts
			// tribe_singleton( 'tec.linked-posts', 'Tribe__Events__Linked_Posts' );
			// tribe_singleton( 'tec.linked-posts.venue', 'Tribe__Events__Venue' );
			// tribe_singleton( 'tec.linked-posts.organizer', 'Tribe__Events__Organizer' );

			// // Adjacent Events
			// tribe_singleton( 'tec.adjacent-events', 'Tribe__Events__Adjacent_Events' );

			// // Purge Expired events
			// tribe_singleton( 'tec.event-cleaner', new Tribe__Events__Event_Cleaner() );

			// // Gutenberg Extension
			// tribe_singleton( 'tec.gutenberg', 'Tribe__Events__Gutenberg', array( 'hook' ) );

			// // Admin Notices
			// tribe_singleton( 'tec.admin.notice.timezones', 'Tribe__Events__Admin__Notice__Timezones', array( 'hook' ) );
			// tribe_singleton( 'tec.admin.notice.marketing', 'Tribe__Events__Admin__Notice__Marketing', array( 'hook' ) );

			// // GDPR Privacy
			// tribe_singleton( 'tec.privacy', 'Tribe__Events__Privacy', array( 'hook' ) );

			// // The ORM/Repository service provider.
			// tribe_register_provider( 'Tribe__Events__Service_Providers__ORM' );

			// tribe_singleton( 'events.rewrite', Tribe__Events__Rewrite::class );

			// // The Context service provider.
			// tribe_register_provider( Tribe\Events\Service_Providers\Context::class );

			// // The Views v2 service provider.
			// tribe_register_provider( Tribe\Events\Views\V2\Service_Provider::class );

			// /**
			//  * Allows other plugins and services to override/change the bound implementations.
			//  */
			// do_action( 'tribe_events_bound_implementations' );
		}
		/**
		 * Registers the plugin autoload paths in the Common Autoloader instance.
		 *
		 * @since 4.9.2
		 */
		public function register_plugin_autoload_paths( ) {
			$prefixes = array(
				'Tribe__Events__' => $this->plugin_path . 'src/Tribe',
				'ForceUTF8__'     => $this->plugin_path . 'vendor/ForceUTF8',
			);

			$this->get_autoloader_instance()->register_prefixes( $prefixes );
		}
		/**
		 * Returns the autoloader singleton instance to use in a context-aware manner.
		 *
		 * @since 4.9.2
		 *
		 * @return \Tribe__Autoloader Teh singleton common Autoloader instance.
		 */
		public function get_autoloader_instance() {
			if ( ! class_exists( 'Tribe__Autoloader' ) ) {
				require_once $GLOBALS['tribe-common-info']['dir'] . '/Autoloader.php';

				Tribe__Autoloader::instance()->register_prefixes( [
					'Tribe__' => $GLOBALS['tribe-common-info']['dir'],
				] );
			}

			return Tribe__Autoloader::instance();
		}
		/**
		 * Load Text Domain on tribe_common_loaded as it requires common
		 *
		 * @since 4.8
		 *
		 */
		public function bootstrap() {

			Tribe__Main::instance( $this )->load_text_domain( 'the-events-calendar', $this->plugin_dir . 'lang/' );

			$this->bind_implementations();
			// $this->loadLibraries();
			// $this->addHooks();
			// $this->register_active_plugin();

		}
		/**
		 * Generate custom post type lables
		 */
		protected function generatePostTypeLabels() {
			/**
			 * Provides an opportunity to modify the labels used for the event post type.
			 *
			 * @var array
			 */
			$this->post_type_args['labels'] = apply_filters( 'tribe_events_register_event_post_type_labels', array(
				'name'                     => $this->plural_event_label,
				'singular_name'            => $this->singular_event_label,
				'add_new'                  => esc_html__( 'Add New', 'the-events-calendar' ),
				'add_new_item'             => sprintf( esc_html__( 'Add New %s', 'the-events-calendar' ), $this->singular_event_label ),
				'edit_item'                => sprintf( esc_html__( 'Edit %s', 'the-events-calendar' ), $this->singular_event_label ),
				'new_item'                 => sprintf( esc_html__( 'New %s', 'the-events-calendar' ), $this->singular_event_label ),
				'view_item'                => sprintf( esc_html__( 'View %s', 'the-events-calendar' ), $this->singular_event_label ),
				'search_items'             => sprintf( esc_html__( 'Search %s', 'the-events-calendar' ), $this->plural_event_label ),
				'not_found'                => sprintf( esc_html__( 'No %s found', 'the-events-calendar' ), $this->plural_event_label_lowercase ),
				'not_found_in_trash'       => sprintf( esc_html__( 'No %s found in Trash', 'the-events-calendar' ), $this->plural_event_label_lowercase ),
				'item_published'           => sprintf( esc_html__( '%s published.', 'the-events-calendar' ), $this->singular_event_label ),
				'item_published_privately' => sprintf( esc_html__( '%s published privately.', 'the-events-calendar' ), $this->singular_event_label ),
				'item_reverted_to_draft'   => sprintf( esc_html__( '%s reverted to draft.', 'the-events-calendar' ), $this->singular_event_label ),
				'item_scheduled'           => sprintf( esc_html__( '%s scheduled.', 'the-events-calendar' ), $this->singular_event_label ),
				'item_updated'             => sprintf( esc_html__( '%s updated.', 'the-events-calendar' ), $this->singular_event_label ),
			) );

			/**
			 * Provides an opportunity to modify the labels used for the event category taxonomy.
			 *
			 * @var array
			 */
			$this->taxonomyLabels = apply_filters( 'tribe_events_register_category_taxonomy_labels', array(
				'name'              => sprintf( esc_html__( '%s Categories', 'the-events-calendar' ), $this->singular_event_label ),
				'singular_name'     => sprintf( esc_html__( '%s Category', 'the-events-calendar' ), $this->singular_event_label ),
				'search_items'      => sprintf( esc_html__( 'Search %s Categories', 'the-events-calendar' ), $this->singular_event_label ),
				'all_items'         => sprintf( esc_html__( 'All %s Categories', 'the-events-calendar' ), $this->singular_event_label ),
				'parent_item'       => sprintf( esc_html__( 'Parent %s Category', 'the-events-calendar' ), $this->singular_event_label ),
				'parent_item_colon' => sprintf( esc_html__( 'Parent %s Category:', 'the-events-calendar' ), $this->singular_event_label ),
				'edit_item'         => sprintf( esc_html__( 'Edit %s Category', 'the-events-calendar' ), $this->singular_event_label ),
				'update_item'       => sprintf( esc_html__( 'Update %s Category', 'the-events-calendar' ), $this->singular_event_label ),
				'add_new_item'      => sprintf( esc_html__( 'Add New %s Category', 'the-events-calendar' ), $this->singular_event_label ),
				'new_item_name'     => sprintf( esc_html__( 'New %s Category Name', 'the-events-calendar' ), $this->singular_event_label ),
			) );
		}
		public function registerPostType() {
			$this->generatePostTypeLabels();
			$post_type_args = $this->post_type_args;
			$labels = array(
				'name'               => _x( 'Events', 'post type general name', 'your-plugin-textdomain' ),
				'singular_name'      => _x( 'Book', 'post type singular name', 'your-plugin-textdomain' ),
				'menu_name'          => $this->plural_event_label,
				'name_admin_bar'     => _x( 'Book', 'add new on admin bar', 'your-plugin-textdomain' ),
				'add_new'            => _x( 'Add New', 'book', 'your-plugin-textdomain' ),
				'add_new_item'       => __( 'Add New Book', 'your-plugin-textdomain' ),
				'new_item'           => __( 'New Book', 'your-plugin-textdomain' ),
				'edit_item'          => __( 'Edit Book', 'your-plugin-textdomain' ),
				'view_item'          => __( 'View Book', 'your-plugin-textdomain' ),
				'all_items'          => __( 'All Books', 'your-plugin-textdomain' ),
				'search_items'       => __( 'Search Books', 'your-plugin-textdomain' ),
				'parent_item_colon'  => __( 'Parent Books:', 'your-plugin-textdomain' ),
				'not_found'          => __( 'No books found.', 'your-plugin-textdomain' ),
				'not_found_in_trash' => __( 'No books found in Trash.', 'your-plugin-textdomain' )
			);
		
			$args = array(
				'labels'             => $labels,
				'description'        => __( 'Description.', 'your-plugin-textdomain' ),
				'public'             => true,
				'publicly_queryable' => true,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'query_var'          => true,
				'rewrite'            => array( 'slug' => 'event', 'with_front' => false ),
				'capability_type'    => 'post',
				//'capability_type' => array( 'tribe_event', 'tribe_events' ),
				'has_archive'        => true,
				'hierarchical'       => false,
				'menu_position'      => 6,
				'map_meta_cap'    => true,
				'menu_icon'       => 'dashicons-calendar',
				'supports'        => array(
					'title',
					'editor',
					'excerpt',
					'author',
					'thumbnail',
					'custom-fields',
					'comments',
					'revisions',
				)
			);
			$post_type_args['rewrite']['slug'] = $this->getRewriteSlugSingular();
			$post_type_args = apply_filters( 'tribe_events_register_event_type_args', $post_type_args );
			register_post_type( self::POSTTYPE, $args );
		}
		/**
		 * Get the single post rewrite slug
		 *
		 * @return string
		 */
		public function getRewriteSlugSingular() {
			// translators: For compatibility with WPML and other multilingual plugins, not to be translated directly on .mo files.
			return 'event';
			//return sanitize_title( _x( Tribe__Settings_Manager::get_option( 'singleEventSlug', 'event' ), 'Rewrite Singular Slug', 'the-events-calendar' ) );
		}
		
		
		
		/**
		 * Add filters and actions
		 */
		protected function addHooks() {
			/**
			 * It's important that anything related to Text Domain happens at `init`
			 * because of the way $wp_locale works
			 */
			// add_action( 'init', array( $this, 'setup_l10n_strings' ), 5 );

			// // Since TEC is active, change the base page for the Event Settings page
			// Tribe__Settings::$parent_page = 'edit.php';

			// // Load Rewrite
			// add_action( 'plugins_loaded', array( Tribe__Events__Rewrite::instance(), 'hooks' ) );

			add_action( 'init', array( $this, 'init' ), 10 );
			// add_action( 'admin_init', array( $this, 'admin_init' ) );

			// add_filter( 'tribe_events_before_html', array( $this, 'before_html_data_wrapper' ) );
			// add_filter( 'tribe_events_after_html', array( $this, 'after_html_data_wrapper' ) );

			// // Styling
			// add_filter( 'post_class', array( $this, 'post_class' ) );
			//add_filter( 'body_class', array( $this, 'body_class' ) );
			// add_filter( 'admin_body_class', array( $this, 'admin_body_class' ) );

			// add_filter( 'post_type_archive_link', array( $this, 'event_archive_link' ), 10, 2 );
			// add_filter( 'query_vars', array( $this, 'eventQueryVars' ) );
			// add_action( 'parse_query', array( $this, 'setDisplay' ), 51, 1 );
			// add_filter( 'bloginfo_rss', array( $this, 'add_space_to_rss' ) );
			// add_filter( 'post_updated_messages', array( $this, 'updatePostMessage' ) );

			// /* Add nav menu item - thanks to https://wordpress.org/extend/plugins/cpt-archives-in-nav-menus/ */
			// add_filter( 'nav_menu_items_' . self::POSTTYPE, array( $this, 'add_events_checkbox_to_menu' ), null, 3 );
			// add_filter( 'wp_nav_menu_objects', array( $this, 'add_current_menu_item_class_to_events' ), null, 2 );

			// add_action( 'template_redirect', array( $this, 'redirect_past_upcoming_view_urls' ), 9 );

			// /* Setup Tribe Events Bar */
			// add_filter( 'tribe-events-bar-views', array( $this, 'setup_listview_in_bar' ), 1, 1 );
			// add_filter( 'tribe-events-bar-views', array( $this, 'setup_gridview_in_bar' ), 5, 1 );
			// add_filter( 'tribe-events-bar-views', array( $this, 'setup_dayview_in_bar' ), 15, 1 );

			// add_filter( 'tribe-events-bar-filters', array( $this, 'setup_date_search_in_bar' ), 1, 1 );
			// add_filter( 'tribe-events-bar-filters', array( $this, 'setup_keyword_search_in_bar' ), 1, 1 );

			// add_filter( 'tribe-events-bar-views', array( $this, 'remove_hidden_views' ), 9999, 2 );
			// /* End Setup Tribe Events Bar */

			// add_action( 'admin_menu', array( $this, 'addEventBox' ) );
			// add_action( 'wp_insert_post', array( $this, 'addPostOrigin' ), 10, 2 );
			// add_action( 'save_post', array( $this, 'addEventMeta' ), 15, 2 );

			// /* Registers the list widget */
			// add_action( 'widgets_init', array( $this, 'register_list_widget' ), 90 );

			// add_action( 'save_post_' . Tribe__Events__Venue::POSTTYPE, array( $this, 'save_venue_data' ), 16, 2 );
			// add_action( 'save_post_' . Tribe__Events__Organizer::POSTTYPE, array( $this, 'save_organizer_data' ), 16, 2 );
			// add_action( 'save_post_' . self::POSTTYPE, array( Tribe__Events__Dates__Known_Range::instance(), 'maybe_update_known_range' ) );
			// add_action( 'tribe_events_csv_import_complete', array( Tribe__Events__Dates__Known_Range::instance(), 'rebuild_known_range' ) );
			// add_action( 'publish_' . self::POSTTYPE, array( $this, 'publishAssociatedTypes' ), 25, 2 );
			// add_action( 'delete_post', array( Tribe__Events__Dates__Known_Range::instance(), 'maybe_rebuild_known_range' ) );
			// add_action( 'tribe_events_post_errors', array( 'Tribe__Events__Post_Exception', 'displayMessage' ) );
			// add_action( 'tribe_settings_top', array( 'Tribe__Events__Options_Exception', 'displayMessage' ) );
			// add_action( 'trash_' . Tribe__Events__Venue::POSTTYPE, array( $this, 'cleanupPostVenues' ) );
			// add_action( 'trash_' . Tribe__Events__Organizer::POSTTYPE, array( $this, 'cleanupPostOrganizers' ) );
			// add_action( 'wp_ajax_tribe_event_validation', array( $this, 'ajax_form_validate' ) );
			// add_action( 'plugins_loaded', array( 'Tribe__Cache_Listener', 'instance' ) );
			// add_action( 'plugins_loaded', array( 'Tribe__Cache', 'setup' ) );
			// add_action( 'plugins_loaded', array( 'Tribe__Support', 'getInstance' ) );

			// add_filter( 'tribe_tracker_post_types', array( $this, 'filter_tracker_event_post_types' ) );
			// add_filter( 'tribe_tracker_taxonomies', array( $this, 'filter_tracker_event_taxonomies' ) );

			// if ( ! tribe( 'context' )->doing_ajax() ) {
			// 	add_action( 'current_screen', array( $this, 'init_admin_list_screen' ) );
			// } else {
			// 	add_action( 'admin_init', array( $this, 'init_admin_list_screen' ) );
			// }

			// // Load organizer and venue editors
			// add_action( 'admin_menu', array( $this, 'addVenueAndOrganizerEditor' ) );

			// add_action( 'tribe_venue_table_top', array( $this, 'display_rich_snippets_helper' ), 5 );

			// add_action( 'template_redirect', array( $this, 'template_redirect' ) );

			// add_action( 'wp', array( $this, 'issue_noindex' ) );
			// add_action( 'plugin_row_meta', array( $this, 'addMetaLinks' ), 10, 2 );
			// // organizer and venue
			// if ( ! defined( 'TRIBE_HIDE_UPSELL' ) || ! TRIBE_HIDE_UPSELL ) {
			// 	add_action( 'wp_dashboard_setup', array( $this, 'dashboardWidget' ) );
			// 	add_action( 'tribe_events_cost_table', array( $this, 'maybeShowMetaUpsell' ) );
			// }

			// add_action( 'load-tribe_events_page_' . Tribe__Settings::$parent_slug, array( 'Tribe__Events__Amalgamator', 'listen_for_migration_button' ), 10, 0 );
			// add_action( 'tribe_settings_after_save', array( $this, 'flushRewriteRules' ) );

			// add_action( 'update_option_' . Tribe__Main::OPTIONNAME, array( $this, 'fix_all_day_events' ), 10, 2 );

			// add_action( 'wp_before_admin_bar_render', array( $this, 'add_toolbar_items' ), 10 );
			// add_action( 'all_admin_notices', array( $this, 'addViewCalendar' ) );
			// add_action( 'admin_head', array( $this, 'setInitialMenuMetaBoxes' ), 500 );
			// add_action( 'plugin_action_links_' . trailingslashit( $this->plugin_dir ) . 'the-events-calendar.php', array( $this, 'addLinksToPluginActions' ) );

			// // override default wp_terms_checklist arguments to prevent checked items from bubbling to the top. Instead, retain hierarchy.
			// add_filter( 'wp_terms_checklist_args', array( $this, 'prevent_checked_on_top_terms' ), 10, 2 );

			// add_action( 'tribe_events_pre_get_posts', array( $this, 'set_tribe_paged' ) );

			// // Upgrade material.
			// add_action( 'init', array( $this, 'run_updates' ), 0, 0 );

			// if ( defined( 'WP_LOAD_IMPORTERS' ) && WP_LOAD_IMPORTERS ) {
			// 	add_filter( 'wp_import_post_data_raw', array( $this, 'filter_wp_import_data_before' ), 10, 1 );
			// 	add_filter( 'wp_import_post_data_processed', array( $this, 'filter_wp_import_data_after' ), 10, 1 );
			// }

			// add_action( 'plugins_loaded', array( $this, 'init_day_view' ), 2 );

			// add_action( 'plugins_loaded', array( 'Tribe__Events__Templates', 'init' ) );
			// tribe( 'tec.bar' );

			// add_action( 'init', array( $this, 'filter_cron_schedules' ) );

			// add_action( 'plugins_loaded', array( 'Tribe__Events__Event_Tickets__Main', 'instance' ) );

			// // Add support for tickets plugin
			// add_action( 'tribe_tickets_ticket_added', array( 'Tribe__Events__API', 'update_event_cost' ) );
			// add_action( 'tribe_tickets_ticket_deleted', array( 'Tribe__Events__API', 'update_event_cost' ) );
			// add_filter( 'tribe_tickets_default_end_date', array( $this, 'default_end_date_for_tickets' ), 10, 2 );

			// add_filter( 'tribe_post_types', array( $this, 'filter_post_types' ) );
			// add_filter( 'tribe_is_post_type_screen_post_types', array( $this, 'is_post_type_screen_post_types' ) );
			// add_filter( 'tribe_currency_symbol', array( $this, 'maybe_set_currency_symbol_with_post' ), 10, 2 );
			// add_filter( 'tribe_reverse_currency_position', array( $this, 'maybe_set_currency_position_with_post' ), 10, 2 );

			// // Settings page hooks
			// add_action( 'tribe_settings_do_tabs', array( $this, 'do_addons_api_settings_tab' ) );
			// add_filter( 'tribe_general_settings_tab_fields', array( $this, 'general_settings_tab_fields' ) );
			// add_filter( 'tribe_display_settings_tab_fields', array( $this, 'display_settings_tab_fields' ) );
			// add_filter( 'tribe_settings_url', array( $this, 'tribe_settings_url' ) );

			// // Setup Help Tab texting
			// add_action( 'tribe_help_pre_get_sections', array( $this, 'add_help_section_feature_box_content' ) );
			// add_action( 'tribe_help_pre_get_sections', array( $this, 'add_help_section_support_content' ) );
			// add_action( 'tribe_help_pre_get_sections', array( $this, 'add_help_section_extra_content' ) );


			// // Google Maps API key setting
			// $google_maps_api_key = Tribe__Events__Google__Maps_API_Key::instance();
			// add_filter( 'tribe_addons_tab_fields', array( $google_maps_api_key, 'filter_tribe_addons_tab_fields' ) );
			// add_filter( 'tribe_events_google_maps_api', array( $google_maps_api_key, 'filter_tribe_events_google_maps_api' ) );
			// add_filter( 'tribe_events_pro_google_maps_api', array( $google_maps_api_key, 'filter_tribe_events_google_maps_api' ) );
			// add_filter( 'tribe_field_value', array( $google_maps_api_key, 'populate_field_with_default_api_key' ), 10, 2 );
			// add_filter( 'tribe_field_tooltip', array( $google_maps_api_key, 'populate_field_tooltip_with_helper_text' ), 10, 2 );

			// // Preview handling
			// add_action( 'template_redirect', array( Tribe__Events__Revisions__Preview::instance(), 'hook' ) );

			// // Register all of the post types in the chunker and start the chunker
			// add_filter( 'tribe_meta_chunker_post_types', array( $this, 'filter_meta_chunker_post_types' ) );
			// tribe( 'chunker' );

			// // Purge old events
			// add_action( 'update_option_' . Tribe__Main::OPTIONNAME, tribe_callback( 'tec.event-cleaner', 'move_old_events_to_trash' ), 10, 2 );
			// add_action( 'update_option_' . Tribe__Main::OPTIONNAME, tribe_callback( 'tec.event-cleaner', 'permanently_delete_old_events' ), 10, 2 );

			// // Register slug conflict notices (but test to see if tribe_notice() is indeed available, in case another plugin
			// // is hosting an earlier version of tribe-common which is already active)
			// //
			// // @todo remove this safety check when we're confident the risk has diminished
			// if ( function_exists( 'tribe_notice' ) ) {
			// 	tribe_notice( 'archive-slug-conflict', array( $this, 'render_notice_archive_slug_conflict' ), 'dismiss=1&type=error' );
			// }

			// // Prevent duplicate venues and organizers from being created on event preview.
			// add_action( 'tribe_events_after_view', array( $this, 'maybe_add_preview_venues_and_organizers' ) );

			// /**
			//  * Expire notices
			//  */
			// add_action( 'transition_post_status', array( $this, 'action_expire_archive_slug_conflict_notice' ), 10, 3 );

			// tribe( 'tec.featured_events.query_helper' )->hook();
			// tribe( 'tec.featured_events.permalinks_helper' )->hook();

			// // Add support for positioning the main events view on the site homepage
			// tribe( 'tec.front-page-view' )->hook();

			// tribe( 'events-aggregator.main' );
			// tribe( 'tec.shortcodes.event-details' );
			// tribe( 'tec.ignored-events' );
			// tribe( 'tec.assets' );
			// tribe( 'tec.iCal' );
			// tribe( 'tec.rest-v1.main' );
			// tribe( 'tec.gutenberg' );
			// tribe( 'tec.admin.notice.timezones' );
			// tribe( 'tec.admin.notice.marketing' );
			// tribe( 'tec.privacy' );
		}
		public function init() {
			$this->plural_event_label = $this->get_event_label_plural();
		}
		/**
		 * Update body classes
		 *
		 * @param array $classes
		 *
		 * @return array
		 * @TODO move this to template class
		 */
		public function body_class( $classes ) {
			//if ( get_query_var( 'post_type' ) == self::POSTTYPE ) {
				//if ( ! is_admin() && tribe_get_option( 'liveFiltersUpdate', true ) ) {
					$classes[] = 'tribe-filter-live';
				//}
			//}

			return $classes;
		}
		/**
		 * Allow users to specify their own plural label for Events
		 * @return string
		 */
		public function get_event_label_plural() {
			return apply_filters( 'tribe_event_label_plural', esc_html__( 'Events', 'the-events-calendar' ) );
		}
		/**
		 * Prevents Image Widget Plus weird version of Tribe Common Lib to
		 * conflict with The Events Calendar
		 *
		 * It will make IW+ not load on version 1.0.2
		 *
		 * @since   4.8.1
		 *
		 * @return  void
		 */
		private function compatibility_unload_iwplus_v102() {
			if ( ! class_exists( 'Tribe__Image__Plus__Main' ) ) {
				return;
			}

			if ( ! defined( 'Tribe__Image__Plus__Main::VERSION' ) ) {
				return;
			}

			if ( ! version_compare( Tribe__Image__Plus__Main::VERSION, '1.0.2', '<=' ) ) {
				return;
			}

			remove_action( 'plugins_loaded', array( Tribe__Image__Plus__Main::instance(), 'plugins_loaded' ), 0 );
		}
		/**
		 * Display a missing-tribe-common library error
		 */
		public function missing_common_libs() {
			?>
			<div class="error">
				<p>
					<?php
					echo esc_html__(
						'It appears as if the tribe-common libraries cannot be found! The directory should be in the "common/" directory in the events calendar plugin.',
						'the-events-calendar'
					);
					?>
				</p>
			</div>
			<?php
		}

    }
}
