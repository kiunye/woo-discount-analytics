<?php
/**
 * Admin Reports class.
 *
 * Registers custom admin menu and pages for discount reports.
 *
 * @package WooDiscountAnalytics
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WDA_Admin_Reports
 *
 * Handles admin menu registration and report pages.
 */
class WDA_Admin_Reports {

	/**
	 * Single instance of the class.
	 *
	 * @var WDA_Admin_Reports
	 */
	private static $instance = null;

	/**
	 * Menu slug.
	 */
	const MENU_SLUG = 'wda-discount-analytics';

	/**
	 * Get the single instance.
	 *
	 * @return WDA_Admin_Reports
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
		// Register admin menu.
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );

		// Enqueue admin scripts.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		// Add settings link to plugins page.
		add_filter( 'plugin_action_links_' . WDA_PLUGIN_BASENAME, array( $this, 'add_plugin_action_links' ) );
	}

	/**
	 * Register admin menu.
	 */
	public function register_admin_menu() {
		// Main menu.
		add_menu_page(
			__( 'Discount Analytics', 'woo-discount-analytics' ),
			__( 'Discount Analytics', 'woo-discount-analytics' ),
			self::get_capability(),
			self::MENU_SLUG,
			array( $this, 'render_current_discounts_page' ),
			'dashicons-chart-line',
			56
		);

		// Current Discounts submenu (same as parent).
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Current Discounts', 'woo-discount-analytics' ),
			__( 'Current Discounts', 'woo-discount-analytics' ),
			self::get_capability(),
			self::MENU_SLUG,
			array( $this, 'render_current_discounts_page' )
		);

		// Discount History submenu.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Discount History', 'woo-discount-analytics' ),
			__( 'Discount History', 'woo-discount-analytics' ),
			self::get_capability(),
			self::MENU_SLUG . '-history',
			array( $this, 'render_discount_history_page' )
		);

		// Discount Summary submenu.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Discount Summary', 'woo-discount-analytics' ),
			__( 'Discount Summary', 'woo-discount-analytics' ),
			self::get_capability(),
			self::MENU_SLUG . '-summary',
			array( $this, 'render_discount_summary_page' )
		);
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_scripts( $hook ) {
		// Only load on our plugin pages.
		if ( ! $this->is_wda_page( $hook ) ) {
			return;
		}

		// Enqueue styles.
		wp_enqueue_style(
			'wda-admin-reports',
			WDA_PLUGIN_URL . 'assets/css/admin/reports.css',
			array(),
			WDA_VERSION
		);

		// Enqueue scripts.
		wp_enqueue_script(
			'wda-admin-reports',
			WDA_PLUGIN_URL . 'assets/js/admin/reports/index.js',
			array( 'jquery' ),
			WDA_VERSION,
			true
		);

		wp_localize_script( 'wda-admin-reports', 'wdaSettings', array(
			'restUrl'   => rest_url( 'wda/v1/' ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'currency'  => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD',
			'symbol'    => function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$',
			'adminUrl'  => admin_url(),
			'exportUrl' => rest_url( 'wda/v1/export' ),
		) );
	}

	/**
	 * Check if current page is a WDA page.
	 *
	 * @param string $hook Current admin page hook.
	 * @return bool
	 */
	private function is_wda_page( $hook ) {
		// Check if the hook contains our menu slug (more robust than exact match).
		if ( strpos( $hook, self::MENU_SLUG ) !== false ) {
			return true;
		}

		// Also check the page parameter.
		if ( isset( $_GET['page'] ) ) {
			$page = sanitize_text_field( wp_unslash( $_GET['page'] ) );
			if ( strpos( $page, self::MENU_SLUG ) === 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Render Current Discounts page.
	 */
	public function render_current_discounts_page() {
		?>
		<div class="wrap wda-wrap">
			<h1><?php esc_html_e( 'Current Discounted Products', 'woo-discount-analytics' ); ?></h1>
			<div id="wda-current-discounts-app" class="wda-app-container">
				<div class="wda-loading">
					<div class="wda-loading-spinner"></div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Discount History page.
	 */
	public function render_discount_history_page() {
		?>
		<div class="wrap wda-wrap">
			<h1><?php esc_html_e( 'Discounted Sales History', 'woo-discount-analytics' ); ?></h1>
			<div id="wda-discount-history-app" class="wda-app-container">
				<div class="wda-loading">
					<div class="wda-loading-spinner"></div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Discount Summary page.
	 */
	public function render_discount_summary_page() {
		?>
		<div class="wrap wda-wrap">
			<h1><?php esc_html_e( 'Discount Summary', 'woo-discount-analytics' ); ?></h1>
			<div id="wda-discount-summary-app" class="wda-app-container">
				<div class="wda-loading">
					<div class="wda-loading-spinner"></div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Add plugin action links.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function add_plugin_action_links( $links ) {
		$plugin_links = array(
			'<a href="' . esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG ) ) . '">' . __( 'Reports', 'woo-discount-analytics' ) . '</a>',
		);

		return array_merge( $plugin_links, $links );
	}

	/**
	 * Get capability required for viewing reports.
	 *
	 * @return string
	 */
	public static function get_capability() {
		return 'manage_woocommerce';
	}

	/**
	 * Get menu slug.
	 *
	 * @return string
	 */
	public static function get_menu_slug() {
		return self::MENU_SLUG;
	}
}
