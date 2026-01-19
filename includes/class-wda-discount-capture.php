<?php
/**
 * Discount Capture class.
 *
 * Captures discount data at order creation/completion and stores it in order item meta.
 *
 * @package WooDiscountAnalytics
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WDA_Discount_Capture
 *
 * Handles capturing discount snapshots when orders are placed.
 */
class WDA_Discount_Capture {

	/**
	 * Single instance of the class.
	 *
	 * @var WDA_Discount_Capture
	 */
	private static $instance = null;

	/**
	 * Meta key prefix for discount data.
	 */
	const META_PREFIX = '_wda_';

	/**
	 * Meta keys for discount data.
	 */
	const META_REGULAR_PRICE     = '_wda_regular_price';
	const META_SALE_PRICE        = '_wda_sale_price';
	const META_DISCOUNT_AMOUNT   = '_wda_discount_amount';
	const META_DISCOUNT_PCT      = '_wda_discount_pct';
	const META_WAS_ON_SALE       = '_wda_was_on_sale';
	const META_CAPTURED          = '_wda_captured';

	/**
	 * Get the single instance.
	 *
	 * @return WDA_Discount_Capture
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
		// Hook into order status changes.
		add_action( 'woocommerce_order_status_changed', array( $this, 'on_order_status_changed' ), 10, 4 );

		// Also capture on order creation for immediate processing orders.
		add_action( 'woocommerce_checkout_order_created', array( $this, 'on_order_created' ), 10, 1 );
	}

	/**
	 * Handle order status changes.
	 *
	 * @param int      $order_id    Order ID.
	 * @param string   $old_status  Old status.
	 * @param string   $new_status  New status.
	 * @param WC_Order $order       Order object.
	 */
	public function on_order_status_changed( $order_id, $old_status, $new_status, $order ) {
		// Only capture on processing or completed status.
		if ( ! in_array( $new_status, array( 'processing', 'completed' ), true ) ) {
			return;
		}

		$this->capture_discount_data( $order );
	}

	/**
	 * Handle order creation.
	 *
	 * @param WC_Order $order Order object.
	 */
	public function on_order_created( $order ) {
		// Only capture if order is already in processing/completed state.
		$status = $order->get_status();
		if ( in_array( $status, array( 'processing', 'completed' ), true ) ) {
			$this->capture_discount_data( $order );
		}
	}

	/**
	 * Capture discount data for an order.
	 *
	 * @param WC_Order $order Order object.
	 */
	public function capture_discount_data( $order ) {
		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order );
		}

		if ( ! $order ) {
			return;
		}

		// Check if already captured to avoid duplicates.
		if ( $order->get_meta( self::META_CAPTURED ) === 'yes' ) {
			return;
		}

		$items = $order->get_items( 'line_item' );

		foreach ( $items as $item_id => $item ) {
			$this->capture_item_discount_data( $item_id, $item, $order );
		}

		// Mark order as captured.
		$order->update_meta_data( self::META_CAPTURED, 'yes' );
		$order->save();

		do_action( 'wda_discount_data_captured', $order );
	}

	/**
	 * Capture discount data for a single order item.
	 *
	 * @param int                   $item_id Item ID.
	 * @param WC_Order_Item_Product $item    Order item.
	 * @param WC_Order              $order   Order object.
	 */
	private function capture_item_discount_data( $item_id, $item, $order ) {
		if ( ! $item instanceof WC_Order_Item_Product ) {
			return;
		}

		$product_id   = $item->get_product_id();
		$variation_id = $item->get_variation_id();
		$product      = $item->get_product();

		if ( ! $product ) {
			// Product may have been deleted; use line item data.
			$this->capture_from_line_item( $item_id, $item );
			return;
		}

		// Get prices at time of order (from line item).
		$line_subtotal = $item->get_subtotal();
		$line_total    = $item->get_total();
		$quantity      = $item->get_quantity();

		if ( $quantity <= 0 ) {
			return;
		}

		// Calculate per-unit prices from line item.
		$sold_price = $line_total / $quantity;

		// Get regular price from product (snapshot from current, but ideally from order).
		// For historical accuracy, we check if the product was on sale.
		$regular_price = $product->get_regular_price();
		$sale_price    = $product->get_sale_price();

		// Determine if this was a sale price purchase.
		// We use line subtotal vs line total to detect if there was a product-level discount.
		// Line subtotal is before coupons, line total is after coupons.
		// If product had sale price, the subtotal already reflects sale price.
		$unit_subtotal = $line_subtotal / $quantity;

		// Check if the product appears to have been sold at a discount.
		$was_on_sale    = false;
		$discount_amount = 0;
		$discount_pct    = 0;

		if ( ! empty( $regular_price ) && $regular_price > 0 ) {
			// Compare what was paid vs regular price.
			if ( $unit_subtotal < floatval( $regular_price ) ) {
				$was_on_sale     = true;
				$discount_amount = floatval( $regular_price ) - $unit_subtotal;
				$discount_pct    = ( $discount_amount / floatval( $regular_price ) ) * 100;
			}
		}

		// Store the captured data.
		wc_update_order_item_meta( $item_id, self::META_REGULAR_PRICE, $regular_price );
		wc_update_order_item_meta( $item_id, self::META_SALE_PRICE, $unit_subtotal );
		wc_update_order_item_meta( $item_id, self::META_DISCOUNT_AMOUNT, round( $discount_amount, 4 ) );
		wc_update_order_item_meta( $item_id, self::META_DISCOUNT_PCT, round( $discount_pct, 2 ) );
		wc_update_order_item_meta( $item_id, self::META_WAS_ON_SALE, $was_on_sale ? 'yes' : 'no' );

		do_action( 'wda_item_discount_captured', $item_id, $item, $order, array(
			'regular_price'   => $regular_price,
			'sale_price'      => $unit_subtotal,
			'discount_amount' => $discount_amount,
			'discount_pct'    => $discount_pct,
			'was_on_sale'     => $was_on_sale,
		) );
	}

	/**
	 * Capture data from line item when product is unavailable.
	 *
	 * @param int                   $item_id Item ID.
	 * @param WC_Order_Item_Product $item    Order item.
	 */
	private function capture_from_line_item( $item_id, $item ) {
		$line_subtotal = $item->get_subtotal();
		$quantity      = $item->get_quantity();

		if ( $quantity <= 0 ) {
			return;
		}

		$unit_price = $line_subtotal / $quantity;

		// Without the product, we can't determine regular price or discount.
		// Store what we have for reference.
		wc_update_order_item_meta( $item_id, self::META_REGULAR_PRICE, '' );
		wc_update_order_item_meta( $item_id, self::META_SALE_PRICE, $unit_price );
		wc_update_order_item_meta( $item_id, self::META_DISCOUNT_AMOUNT, 0 );
		wc_update_order_item_meta( $item_id, self::META_DISCOUNT_PCT, 0 );
		wc_update_order_item_meta( $item_id, self::META_WAS_ON_SALE, 'unknown' );
	}

	/**
	 * Get discount data for an order item.
	 *
	 * @param int $item_id Item ID.
	 * @return array|false Discount data or false if not captured.
	 */
	public static function get_item_discount_data( $item_id ) {
		$was_on_sale = wc_get_order_item_meta( $item_id, self::META_WAS_ON_SALE, true );

		if ( '' === $was_on_sale ) {
			return false;
		}

		return array(
			'regular_price'   => wc_get_order_item_meta( $item_id, self::META_REGULAR_PRICE, true ),
			'sale_price'      => wc_get_order_item_meta( $item_id, self::META_SALE_PRICE, true ),
			'discount_amount' => wc_get_order_item_meta( $item_id, self::META_DISCOUNT_AMOUNT, true ),
			'discount_pct'    => wc_get_order_item_meta( $item_id, self::META_DISCOUNT_PCT, true ),
			'was_on_sale'     => $was_on_sale,
		);
	}

	/**
	 * Check if an order has been captured.
	 *
	 * @param WC_Order|int $order Order object or ID.
	 * @return bool
	 */
	public static function is_order_captured( $order ) {
		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order );
		}

		if ( ! $order ) {
			return false;
		}

		return $order->get_meta( self::META_CAPTURED ) === 'yes';
	}
}
