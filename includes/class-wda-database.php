<?php
/**
 * Database class.
 *
 * Handles custom table creation, schema management, and migrations.
 *
 * @package WooDiscountAnalytics
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WDA_Database
 *
 * Manages the custom discount data table.
 */
class WDA_Database {

	/**
	 * Single instance of the class.
	 *
	 * @var WDA_Database
	 */
	private static $instance = null;

	/**
	 * Table name.
	 *
	 * @var string
	 */
	const TABLE_NAME = 'wc_sale_price_discounts';

	/**
	 * Get the single instance.
	 *
	 * @return WDA_Database
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
		// Table creation handled via activation hook.
	}

	/**
	 * Get table name with WordPress prefix.
	 *
	 * @return string
	 */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}

	/**
	 * Create the custom table.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function create_table() {
		global $wpdb;

		$table_name = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			order_id BIGINT UNSIGNED NOT NULL,
			order_item_id BIGINT UNSIGNED NOT NULL,
			product_id BIGINT UNSIGNED NOT NULL,
			variation_id BIGINT UNSIGNED DEFAULT 0,
			regular_price DECIMAL(19,4) NOT NULL,
			sale_price DECIMAL(19,4) NOT NULL,
			discount_amount DECIMAL(19,4) NOT NULL,
			discount_percentage DECIMAL(5,2) NOT NULL,
			quantity DECIMAL(10,2) NOT NULL,
			currency VARCHAR(3) NOT NULL,
			created_at DATETIME NOT NULL,
			refund_id BIGINT UNSIGNED DEFAULT 0,
			refunded_at DATETIME DEFAULT NULL,
			INDEX idx_order_id (order_id),
			INDEX idx_product_id (product_id),
			INDEX idx_created_at (created_at),
			INDEX idx_refund_id (refund_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Check if table was created successfully.
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;

		if ( $table_exists ) {
			update_option( 'wda_db_version', '1.1.0' );
		}

		return $table_exists;
	}

	/**
	 * Check if table exists.
	 *
	 * @return bool
	 */
	public function table_exists() {
		global $wpdb;
		$table_name = self::get_table_name();
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
	}

	/**
	 * Insert discount data into custom table.
	 *
	 * @param array $data Discount data.
	 * @return int|false Insert ID on success, false on failure.
	 */
	public function insert_discount( $data ) {
		global $wpdb;

		if ( ! $this->table_exists() ) {
			return false;
		}

		$table_name = self::get_table_name();

		$defaults = array(
			'order_id'          => 0,
			'order_item_id'     => 0,
			'product_id'        => 0,
			'variation_id'      => 0,
			'regular_price'     => 0,
			'sale_price'        => 0,
			'discount_amount'   => 0,
			'discount_percentage' => 0,
			'quantity'          => 0,
			'currency'          => get_woocommerce_currency(),
			'created_at'        => current_time( 'mysql' ),
			'refund_id'         => 0,
			'refunded_at'       => null,
		);

		$data = wp_parse_args( $data, $defaults );

		// Sanitize and validate data.
		$insert_data = array(
			'order_id'          => absint( $data['order_id'] ),
			'order_item_id'     => absint( $data['order_item_id'] ),
			'product_id'        => absint( $data['product_id'] ),
			'variation_id'      => absint( $data['variation_id'] ),
			'regular_price'     => floatval( $data['regular_price'] ),
			'sale_price'        => floatval( $data['sale_price'] ),
			'discount_amount'   => floatval( $data['discount_amount'] ),
			'discount_percentage' => floatval( $data['discount_percentage'] ),
			'quantity'          => floatval( $data['quantity'] ),
			'currency'          => sanitize_text_field( $data['currency'] ),
			'created_at'        => sanitize_text_field( $data['created_at'] ),
			'refund_id'         => absint( $data['refund_id'] ),
			'refunded_at'       => $data['refunded_at'] ? sanitize_text_field( $data['refunded_at'] ) : null,
		);

		$result = $wpdb->insert( $table_name, $insert_data );

		if ( $result === false ) {
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Get discount data by order item ID.
	 *
	 * @param int $order_item_id Order item ID.
	 * @return array|false Discount data or false if not found.
	 */
	public function get_discount_by_item_id( $order_item_id ) {
		global $wpdb;

		if ( ! $this->table_exists() ) {
			return false;
		}

		$table_name = self::get_table_name();
		$order_item_id = absint( $order_item_id );

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE order_item_id = %d AND refund_id = 0 ORDER BY id DESC LIMIT 1",
				$order_item_id
			),
			ARRAY_A
		);

		return $result ? $result : false;
	}

	/**
	 * Get discount data by order ID.
	 *
	 * @param int $order_id Order ID.
	 * @return array Discount data array.
	 */
	public function get_discounts_by_order_id( $order_id ) {
		global $wpdb;

		if ( ! $this->table_exists() ) {
			return array();
		}

		$table_name = self::get_table_name();
		$order_id = absint( $order_id );

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE order_id = %d AND refund_id = 0 ORDER BY id ASC",
				$order_id
			),
			ARRAY_A
		);

		return $results ? $results : array();
	}

	/**
	 * Mark discount entries as refunded.
	 *
	 * @param int    $order_item_id Original order item ID.
	 * @param int    $refund_id Refund ID.
	 * @param string $refunded_at Refund timestamp.
	 * @return bool True on success, false on failure.
	 */
	public function mark_as_refunded( $order_item_id, $refund_id, $refunded_at = null ) {
		global $wpdb;

		if ( ! $this->table_exists() ) {
			return false;
		}

		$table_name = self::get_table_name();
		$order_item_id = absint( $order_item_id );
		$refund_id = absint( $refund_id );
		$refunded_at = $refunded_at ? sanitize_text_field( $refunded_at ) : current_time( 'mysql' );

		$result = $wpdb->update(
			$table_name,
			array(
				'refund_id'   => $refund_id,
				'refunded_at' => $refunded_at,
			),
			array(
				'order_item_id' => $order_item_id,
				'refund_id'     => 0, // Only update non-refunded entries.
			),
			array( '%d', '%s' ),
			array( '%d', '%d' )
		);

		return $result !== false;
	}

	/**
	 * Migrate existing order item meta data to custom table.
	 *
	 * @return array Migration results.
	 */
	public function migrate_meta_to_table() {
		global $wpdb;

		if ( ! $this->table_exists() ) {
			return array(
				'success' => false,
				'message' => __( 'Custom table does not exist.', 'woo-discount-analytics' ),
			);
		}

		$results = array(
			'success'    => true,
			'migrated'   => 0,
			'skipped'    => 0,
			'errors'     => 0,
			'message'    => '',
		);

		// Get all order items with discount meta.
		$meta_key = WDA_Discount_Capture::META_WAS_ON_SALE;
		$order_item_meta = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT order_item_id, meta_value FROM {$wpdb->prefix}woocommerce_order_itemmeta 
				WHERE meta_key = %s AND meta_value = 'yes'",
				$meta_key
			),
			ARRAY_A
		);

		if ( empty( $order_item_meta ) ) {
			$results['message'] = __( 'No discount data found to migrate.', 'woo-discount-analytics' );
			return $results;
		}

		foreach ( $order_item_meta as $meta ) {
			$order_item_id = absint( $meta['order_item_id'] );

			// Check if already migrated.
			if ( $this->get_discount_by_item_id( $order_item_id ) ) {
				$results['skipped']++;
				continue;
			}

			// Get order item.
			$order_item = new WC_Order_Item_Product( $order_item_id );
			if ( ! $order_item->get_id() ) {
				$results['errors']++;
				continue;
			}

			// Get order ID from order item meta or direct query.
			global $wpdb;
			$order_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT order_id FROM {$wpdb->prefix}woocommerce_order_items WHERE order_item_id = %d",
					$order_item_id
				)
			);

			if ( ! $order_id ) {
				$results['errors']++;
				continue;
			}

			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				$results['errors']++;
				continue;
			}

			// Get discount data from meta.
			$discount_data = WDA_Discount_Capture::get_item_discount_data( $order_item_id );
			if ( ! $discount_data || $discount_data['was_on_sale'] !== 'yes' ) {
				$results['skipped']++;
				continue;
			}

			// Prepare data for insertion.
			$insert_data = array(
				'order_id'          => $order_id,
				'order_item_id'     => $order_item_id,
				'product_id'        => $order_item->get_product_id(),
				'variation_id'      => $order_item->get_variation_id(),
				'regular_price'     => floatval( $discount_data['regular_price'] ),
				'sale_price'        => floatval( $discount_data['sale_price'] ),
				'discount_amount'   => floatval( $discount_data['discount_amount'] ),
				'discount_percentage' => floatval( $discount_data['discount_pct'] ),
				'quantity'          => floatval( $order_item->get_quantity() ),
				'currency'          => $order->get_currency(),
				'created_at'        => $order->get_date_created() ? $order->get_date_created()->format( 'Y-m-d H:i:s' ) : current_time( 'mysql' ),
			);

			$insert_id = $this->insert_discount( $insert_data );

			if ( $insert_id ) {
				$results['migrated']++;
			} else {
				$results['errors']++;
			}
		}

		$results['message'] = sprintf(
			/* translators: 1: migrated count, 2: skipped count, 3: errors count */
			__( 'Migration complete: %1$d migrated, %2$d skipped, %3$d errors.', 'woo-discount-analytics' ),
			$results['migrated'],
			$results['skipped'],
			$results['errors']
		);

		return $results;
	}

	/**
	 * Drop the custom table.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function drop_table() {
		global $wpdb;

		$table_name = self::get_table_name();
		$result = $wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

		delete_option( 'wda_db_version' );

		return $result !== false;
	}
}
