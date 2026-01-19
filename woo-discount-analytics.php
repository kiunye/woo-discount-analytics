<?php
/**
 * Plugin Name: WooCommerce Discount Analytics
 * Plugin URI: https://github.com/kiunye/woo-discount-analytics
 * Description: Provides visibility and reporting for products discounted via sale prices in WooCommerce.
 * Version: 1.1.0
 * Author: Chris Mucheke
 * Author URI: https://github.com/kiunye
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: woo-discount-analytics
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 9.5
 *
 * @package WooDiscountAnalytics
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin constants.
 */
define( 'WDA_VERSION', '1.1.0' );
define( 'WDA_PLUGIN_FILE', __FILE__ );
define( 'WDA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WDA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WDA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class.
 */
final class WooDiscountAnalytics {

	/**
	 * Single instance of the class.
	 *
	 * @var WooDiscountAnalytics
	 */
	private static $instance = null;

	/**
	 * Get the single instance.
	 *
	 * @return WooDiscountAnalytics
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ), 10 );
		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
		register_activation_hook( WDA_PLUGIN_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( WDA_PLUGIN_FILE, array( $this, 'deactivate' ) );
	}

	/**
	 * Declare HPOS compatibility.
	 */
	public function declare_hpos_compatibility() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WDA_PLUGIN_FILE, true );
		}
	}

	/**
	 * Load plugin after WooCommerce is loaded.
	 */
	public function on_plugins_loaded() {
		if ( ! $this->check_requirements() ) {
			return;
		}

		$this->includes();
		$this->init_classes();

		// Load textdomain.
		load_plugin_textdomain( 'woo-discount-analytics', false, dirname( WDA_PLUGIN_BASENAME ) . '/languages' );

		do_action( 'wda_loaded' );
	}

	/**
	 * Check plugin requirements.
	 *
	 * @return bool
	 */
	private function check_requirements() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			return false;
		}

		return true;
	}

	/**
	 * Admin notice for missing WooCommerce.
	 */
	public function woocommerce_missing_notice() {
		?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'WooCommerce Discount Analytics requires WooCommerce to be installed and active.', 'woo-discount-analytics' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Include required files.
	 */
	private function includes() {
		require_once WDA_PLUGIN_DIR . 'includes/class-wda-database.php';
		require_once WDA_PLUGIN_DIR . 'includes/class-wda-discount-capture.php';
		require_once WDA_PLUGIN_DIR . 'includes/class-wda-admin-reports.php';
		require_once WDA_PLUGIN_DIR . 'includes/class-wda-rest-reports.php';
	}

	/**
	 * Initialize classes.
	 */
	private function init_classes() {
		WDA_Database::instance();
		WDA_Discount_Capture::instance();
		WDA_Admin_Reports::instance();
		WDA_REST_Reports::instance();

		// Check for database migration on plugin load.
		$this->check_database_migration();
	}

	/**
	 * Plugin activation.
	 */
	public function activate() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		// Load database class for table creation.
		require_once WDA_PLUGIN_DIR . 'includes/class-wda-database.php';

		// Create custom table.
		$database = WDA_Database::instance();
		$database->create_table();

		// Set version option.
		$old_version = get_option( 'wda_version', '0.0.0' );
		update_option( 'wda_version', WDA_VERSION );

		// Run migration if upgrading from 1.0.0.
		if ( version_compare( $old_version, '1.1.0', '<' ) && version_compare( $old_version, '1.0.0', '>=' ) ) {
			// Migration will be handled on next page load via check_database_migration.
			update_option( 'wda_migration_pending', true );
		}

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation.
	 */
	public function deactivate() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Check if database migration is needed.
	 */
	private function check_database_migration() {
		// Only run in admin and if migration is pending.
		if ( ! is_admin() ) {
			return;
		}

		// Ensure database class is loaded.
		if ( ! class_exists( 'WDA_Database' ) ) {
			return;
		}

		try {
			$migration_pending = get_option( 'wda_migration_pending', false );
			$db_version = get_option( 'wda_db_version', '0.0.0' );

			// If table doesn't exist or migration is pending, ensure table exists.
			$database = WDA_Database::instance();
			if ( $database && ! $database->table_exists() ) {
				$database->create_table();
			}

			// Run migration if pending and table exists.
			if ( $migration_pending && $database && $database->table_exists() ) {
				// Run migration in background (non-blocking).
				add_action( 'admin_init', array( $this, 'run_migration' ), 20 );
			}
		} catch ( Exception $e ) {
			// Silently fail - don't break the plugin if migration has issues.
			error_log( 'WDA Migration Error: ' . $e->getMessage() );
		}
	}

	/**
	 * Run database migration.
	 */
	public function run_migration() {
		// Only run once per request.
		if ( get_transient( 'wda_migration_running' ) ) {
			return;
		}

		set_transient( 'wda_migration_running', true, 60 );

		$database = WDA_Database::instance();
		$results = $database->migrate_meta_to_table();

		// Clear migration pending flag.
		delete_option( 'wda_migration_pending' );

		// Show admin notice if migration completed.
		if ( $results['success'] && $results['migrated'] > 0 ) {
			add_action( 'admin_notices', function() use ( $results ) {
				?>
				<div class="notice notice-success is-dismissible">
					<p><?php echo esc_html( $results['message'] ); ?></p>
				</div>
				<?php
			} );
		}

		delete_transient( 'wda_migration_running' );
	}
}

/**
 * Returns the main instance of WooDiscountAnalytics.
 *
 * @return WooDiscountAnalytics
 */
function WDA() {
	return WooDiscountAnalytics::instance();
}

// Initialize the plugin.
WDA();
