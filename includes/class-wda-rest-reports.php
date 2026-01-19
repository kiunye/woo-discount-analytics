<?php
/**
 * REST Reports class.
 *
 * Handles REST API endpoints for discount reports and CSV export.
 *
 * @package WooDiscountAnalytics
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WDA_REST_Reports
 *
 * Provides REST API endpoints for discount report data.
 */
class WDA_REST_Reports {

	/**
	 * Single instance of the class.
	 *
	 * @var WDA_REST_Reports
	 */
	private static $instance = null;

	/**
	 * REST API namespace.
	 */
	const NAMESPACE = 'wda/v1';

	/**
	 * Get the single instance.
	 *
	 * @return WDA_REST_Reports
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
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		// Current discounted products.
		register_rest_route( self::NAMESPACE, '/current-discounts', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_current_discounts' ),
			'permission_callback' => array( $this, 'check_permission' ),
			'args'                => $this->get_current_discounts_args(),
		) );

		// Historical discount data.
		register_rest_route( self::NAMESPACE, '/discount-history', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_discount_history' ),
			'permission_callback' => array( $this, 'check_permission' ),
			'args'                => $this->get_history_args(),
		) );

		// Discount summary.
		register_rest_route( self::NAMESPACE, '/discount-summary', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_discount_summary' ),
			'permission_callback' => array( $this, 'check_permission' ),
			'args'                => $this->get_summary_args(),
		) );

		// CSV Export.
		register_rest_route( self::NAMESPACE, '/export/(?P<type>[a-z-]+)', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'export_csv' ),
			'permission_callback' => array( $this, 'check_permission' ),
			'args'                => $this->get_export_args(),
		) );
	}

	/**
	 * Check user permission.
	 *
	 * @return bool|WP_Error
	 */
	public function check_permission() {
		if ( ! current_user_can( WDA_Admin_Reports::get_capability() ) ) {
			return new WP_Error(
				'wda_rest_forbidden',
				__( 'You do not have permission to view this data.', 'woo-discount-analytics' ),
				array( 'status' => 403 )
			);
		}
		return true;
	}

	/**
	 * Get current discounts arguments.
	 *
	 * @return array
	 */
	private function get_current_discounts_args() {
		return array(
			'page'            => array(
				'type'              => 'integer',
				'default'           => 1,
				'sanitize_callback' => 'absint',
			),
			'per_page'        => array(
				'type'              => 'integer',
				'default'           => 25,
				'sanitize_callback' => 'absint',
			),
			'category'        => array(
				'type'              => 'integer',
				'default'           => 0,
				'sanitize_callback' => 'absint',
			),
			'product_type'    => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'discount_min'    => array(
				'type'              => 'number',
				'default'           => 0,
				'sanitize_callback' => function( $value ) {
					return floatval( $value );
				},
			),
			'discount_max'    => array(
				'type'              => 'number',
				'default'           => 100,
				'sanitize_callback' => function( $value ) {
					return floatval( $value );
				},
			),
			'sale_status'     => array(
				'type'              => 'string',
				'default'           => 'all',
				'enum'              => array( 'active', 'scheduled', 'expired', 'all' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'orderby'         => array(
				'type'              => 'string',
				'default'           => 'discount_pct',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'order'           => array(
				'type'              => 'string',
				'default'           => 'DESC',
				'enum'              => array( 'ASC', 'DESC' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Get history arguments.
	 *
	 * @return array
	 */
	private function get_history_args() {
		return array(
			'page'       => array(
				'type'              => 'integer',
				'default'           => 1,
				'sanitize_callback' => 'absint',
			),
			'per_page'   => array(
				'type'              => 'integer',
				'default'           => 25,
				'sanitize_callback' => 'absint',
			),
			'date_from'  => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'date_to'    => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'product_id' => array(
				'type'              => 'integer',
				'default'           => 0,
				'sanitize_callback' => 'absint',
			),
			'category'   => array(
				'type'              => 'integer',
				'default'           => 0,
				'sanitize_callback' => 'absint',
			),
			'group_by'   => array(
				'type'              => 'string',
				'default'           => '',
				'enum'              => array( '', 'product', 'category', 'date' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Get summary arguments.
	 *
	 * @return array
	 */
	private function get_summary_args() {
		return array(
			'date_from' => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'date_to'   => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Get export arguments.
	 *
	 * @return array
	 */
	private function get_export_args() {
		return array(
			'type'      => array(
				'type'              => 'string',
				'required'          => true,
				'enum'              => array( 'current-discounts', 'discount-history', 'discount-summary' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'date_from' => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'date_to'   => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Get current discounted products.
	 *
	 * Scans ALL products in the catalog to find those with sale prices set.
	 * This does not depend on order history - it looks at current product data.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_current_discounts( $request ) {
		$page         = $request->get_param( 'page' );
		$per_page     = min( $request->get_param( 'per_page' ), 100 );
		$category     = $request->get_param( 'category' );
		$product_type = $request->get_param( 'product_type' );
		$discount_min = $request->get_param( 'discount_min' );
		$discount_max = $request->get_param( 'discount_max' );
		$sale_status  = $request->get_param( 'sale_status' );
		$orderby      = $request->get_param( 'orderby' );
		$order        = $request->get_param( 'order' );

		// Query ALL products (we'll filter by sale price in PHP for reliability).
		$args = array(
			'status' => 'publish',
			'limit'  => -1,
			'return' => 'objects',
			'type'   => array( 'simple', 'variable', 'external', 'grouped' ),
		);

		// Add category filter.
		if ( $category > 0 ) {
			$term = get_term( $category, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$args['category'] = array( $term->slug );
			}
		}

		// Add product type filter.
		if ( ! empty( $product_type ) && $product_type !== 'variation' ) {
			$args['type'] = $product_type;
		}

		$products = wc_get_products( $args );

		// Also get ALL variations.
		$variation_args = array(
			'status' => 'publish',
			'limit'  => -1,
			'type'   => 'variation',
			'return' => 'objects',
		);

		$variations = wc_get_products( $variation_args );

		// Merge all products and variations.
		$all_products = array_merge( $products, $variations );
		$results      = array();
		$now          = time();

		foreach ( $all_products as $product ) {
			$regular_price = floatval( $product->get_regular_price() );
			$sale_price    = floatval( $product->get_sale_price() );

			if ( empty( $sale_price ) || $sale_price >= $regular_price || $regular_price <= 0 ) {
				continue;
			}

			$discount_amount = $regular_price - $sale_price;
			$discount_pct    = ( $discount_amount / $regular_price ) * 100;

			// Filter by discount percentage.
			if ( $discount_pct < $discount_min || $discount_pct > $discount_max ) {
				continue;
			}

			// Get sale dates.
			$date_on_sale_from = $product->get_date_on_sale_from();
			$date_on_sale_to   = $product->get_date_on_sale_to();

			$sale_from = $date_on_sale_from ? $date_on_sale_from->getTimestamp() : 0;
			$sale_to   = $date_on_sale_to ? $date_on_sale_to->getTimestamp() : 0;

			// Determine sale status.
			$current_status = 'active';
			if ( $sale_from > 0 && $sale_from > $now ) {
				$current_status = 'scheduled';
			} elseif ( $sale_to > 0 && $sale_to < $now ) {
				$current_status = 'expired';
			}

			// Filter by sale status.
			if ( $sale_status !== 'all' && $current_status !== $sale_status ) {
				continue;
			}

			$parent_id = 0;
			$name      = $product->get_name();

			if ( $product->is_type( 'variation' ) ) {
				$parent_id = $product->get_parent_id();
			}

			$results[] = array(
				'id'                 => $product->get_id(),
				'parent_id'          => $parent_id,
				'name'               => $name,
				'type'               => $product->get_type(),
				'sku'                => $product->get_sku(),
				'regular_price'      => $regular_price,
				'sale_price'         => $sale_price,
				'discount_amount'    => round( $discount_amount, 2 ),
				'discount_pct'       => round( $discount_pct, 2 ),
				'sale_start'         => $date_on_sale_from ? $date_on_sale_from->format( 'Y-m-d H:i:s' ) : null,
				'sale_end'           => $date_on_sale_to ? $date_on_sale_to->format( 'Y-m-d H:i:s' ) : null,
				'sale_status'        => $current_status,
				'stock_status'       => $product->get_stock_status(),
				'stock_quantity'     => $product->get_stock_quantity(),
				'edit_link'          => get_edit_post_link( $parent_id ? $parent_id : $product->get_id(), 'raw' ),
			);
		}

		// Sort results.
		usort( $results, function( $a, $b ) use ( $orderby, $order ) {
			$val_a = isset( $a[ $orderby ] ) ? $a[ $orderby ] : 0;
			$val_b = isset( $b[ $orderby ] ) ? $b[ $orderby ] : 0;

			if ( $order === 'ASC' ) {
				return $val_a <=> $val_b;
			}
			return $val_b <=> $val_a;
		} );

		$total = count( $results );

		// Apply pagination after filtering and sorting.
		$offset  = ( $page - 1 ) * $per_page;
		$results = array_slice( $results, $offset, $per_page );

		return new WP_REST_Response( array(
			'items'       => $results,
			'total'       => $total,
			'total_pages' => (int) ceil( $total / $per_page ),
			'page'        => $page,
			'per_page'    => $per_page,
		), 200 );
	}

	/**
	 * Get discount history.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_discount_history( $request ) {
		$page       = $request->get_param( 'page' );
		$per_page   = min( $request->get_param( 'per_page' ), 100 );
		$date_from  = $request->get_param( 'date_from' );
		$date_to    = $request->get_param( 'date_to' );
		$product_id = $request->get_param( 'product_id' );
		$category   = $request->get_param( 'category' );
		$group_by   = $request->get_param( 'group_by' );

		$results = array();

		// Try to read from custom table first (preferred method).
		if ( class_exists( 'WDA_Database' ) ) {
			$database = WDA_Database::instance();
			if ( $database && $database->table_exists() ) {
				$results = $this->get_discount_history_from_table( $date_from, $date_to, $product_id, $category );
			} else {
				// Fallback to order item meta method (backward compatibility).
				$results = $this->get_discount_history_from_meta( $date_from, $date_to, $product_id, $category );
			}
		} else {
			// Fallback to order item meta method (backward compatibility).
			$results = $this->get_discount_history_from_meta( $date_from, $date_to, $product_id, $category );
		}

		// Handle grouping.
		if ( ! empty( $group_by ) ) {
			$results = $this->group_history_results( $results, $group_by );
		}

		$total = count( $results );

		// Paginate.
		$offset  = ( $page - 1 ) * $per_page;
		$results = array_slice( $results, $offset, $per_page );

		return new WP_REST_Response( array(
			'items'       => array_values( $results ),
			'total'       => $total,
			'total_pages' => ceil( $total / $per_page ),
			'page'        => $page,
			'per_page'    => $per_page,
		), 200 );
	}

	/**
	 * Get discount history from custom table.
	 *
	 * @param string $date_from  Date from.
	 * @param string $date_to    Date to.
	 * @param int    $product_id Product ID filter.
	 * @param int    $category   Category ID filter.
	 * @return array
	 */
	private function get_discount_history_from_table( $date_from, $date_to, $product_id, $category ) {
		global $wpdb;

		if ( ! class_exists( 'WDA_Database' ) ) {
			return array();
		}

		$database = WDA_Database::instance();
		$table_name = WDA_Database::get_table_name();
		$results = array();

		// Build WHERE clause.
		$where = array( 'refund_id = 0' ); // Only non-refunded items.

		if ( ! empty( $date_from ) ) {
			$where[] = $wpdb->prepare( 'created_at >= %s', $date_from );
		}

		if ( ! empty( $date_to ) ) {
			$where[] = $wpdb->prepare( 'created_at <= %s', $date_to . ' 23:59:59' );
		}

		if ( $product_id > 0 ) {
			$where[] = $wpdb->prepare( 'product_id = %d', $product_id );
		}

		$where_clause = 'WHERE ' . implode( ' AND ', $where );

		// Query custom table.
		$discounts = $wpdb->get_results(
			"SELECT * FROM {$table_name} {$where_clause} ORDER BY created_at DESC",
			ARRAY_A
		);

		foreach ( $discounts as $discount ) {
			$order_id = absint( $discount['order_id'] );
			$order = wc_get_order( $order_id );

			if ( ! $order ) {
				continue;
			}

			// Filter by category if specified.
			if ( $category > 0 ) {
				$product_categories = wp_get_post_terms( $discount['product_id'], 'product_cat', array( 'fields' => 'ids' ) );
				if ( ! in_array( $category, $product_categories, true ) ) {
					continue;
				}
			}

			// Get product name.
			$product = wc_get_product( $discount['product_id'] );
			$product_name = $product ? $product->get_name() : sprintf( __( 'Product #%d', 'woo-discount-analytics' ), $discount['product_id'] );

			// Get order item for line total.
			$order_item = $order->get_item( $discount['order_item_id'] );
			$line_total = $order_item ? floatval( $order_item->get_total() ) : 0;

			// Calculate ERP-ready price decomposition.
			$gross_unit_price = floatval( $discount['regular_price'] );
			$line_discount = floatval( $discount['discount_amount'] );
			$net_unit_price = floatval( $discount['sale_price'] );
			$net_line_amount = $net_unit_price * floatval( $discount['quantity'] );

			$results[] = array(
				'order_id'          => $order_id,
				'order_date'        => $discount['created_at'],
				'item_id'           => absint( $discount['order_item_id'] ),
				'product_id'        => absint( $discount['product_id'] ),
				'variation_id'      => absint( $discount['variation_id'] ),
				'product_name'      => $product_name,
				'quantity'          => floatval( $discount['quantity'] ),
				'regular_price'     => $gross_unit_price,
				'sale_price'        => $net_unit_price,
				'discount_amount'   => $line_discount,
				'discount_pct'      => floatval( $discount['discount_percentage'] ),
				'line_total'        => $line_total,
				'currency'          => $discount['currency'],
				// ERP-ready price decomposition.
				'gross_unit_price'  => $gross_unit_price,
				'line_discount'     => $line_discount,
				'net_unit_price'    => $net_unit_price,
				'net_line_amount'   => $net_line_amount,
			);
		}

		return $results;
	}

	/**
	 * Get discount history from order item meta (backward compatibility).
	 *
	 * @param string $date_from  Date from.
	 * @param string $date_to    Date to.
	 * @param int    $product_id Product ID filter.
	 * @param int    $category   Category ID filter.
	 * @return array
	 */
	private function get_discount_history_from_meta( $date_from, $date_to, $product_id, $category ) {
		// Build order query.
		$order_args = array(
			'status'   => array( 'wc-processing', 'wc-completed' ),
			'limit'    => -1,
			'return'   => 'ids',
			'orderby'  => 'date',
			'order'    => 'DESC',
		);

		if ( ! empty( $date_from ) ) {
			$order_args['date_created'] = '>=' . strtotime( $date_from );
		}

		if ( ! empty( $date_to ) ) {
			if ( isset( $order_args['date_created'] ) ) {
				$order_args['date_created'] .= '...' . strtotime( $date_to . ' 23:59:59' );
			} else {
				$order_args['date_created'] = '<=' . strtotime( $date_to . ' 23:59:59' );
			}
		}

		$order_ids = wc_get_orders( $order_args );
		$results   = array();

		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				continue;
			}

			$items = $order->get_items( 'line_item' );

			foreach ( $items as $item_id => $item ) {
				$discount_data = WDA_Discount_Capture::get_item_discount_data( $item_id );

				if ( ! $discount_data || $discount_data['was_on_sale'] !== 'yes' ) {
					continue;
				}

				$item_product_id = $item->get_product_id();

				// Filter by product.
				if ( $product_id > 0 && $item_product_id !== $product_id ) {
					continue;
				}

				// Filter by category.
				if ( $category > 0 ) {
					$product_categories = wp_get_post_terms( $item_product_id, 'product_cat', array( 'fields' => 'ids' ) );
					if ( ! in_array( $category, $product_categories, true ) ) {
						continue;
					}
				}

				$product      = $item->get_product();
				$product_name = $product ? $product->get_name() : $item->get_name();

				$date_created = $order->get_date_created();

				// Calculate ERP-ready price decomposition.
				$gross_unit_price = floatval( $discount_data['regular_price'] );
				$line_discount = floatval( $discount_data['discount_amount'] );
				$net_unit_price = floatval( $discount_data['sale_price'] );
				$quantity = floatval( $item->get_quantity() );
				$net_line_amount = $net_unit_price * $quantity;

				$results[] = array(
					'order_id'          => $order_id,
					'order_date'        => $date_created ? $date_created->format( 'Y-m-d H:i:s' ) : '',
					'item_id'           => $item_id,
					'product_id'        => $item_product_id,
					'variation_id'      => $item->get_variation_id(),
					'product_name'      => $product_name,
					'quantity'          => $quantity,
					'regular_price'     => $gross_unit_price,
					'sale_price'        => $net_unit_price,
					'discount_amount'   => $line_discount,
					'discount_pct'      => floatval( $discount_data['discount_pct'] ),
					'line_total'        => floatval( $item->get_total() ),
					'currency'          => $order->get_currency(),
					// ERP-ready price decomposition.
					'gross_unit_price'  => $gross_unit_price,
					'line_discount'     => $line_discount,
					'net_unit_price'    => $net_unit_price,
					'net_line_amount'   => $net_line_amount,
				);
			}
		}

		return $results;
	}

	/**
	 * Group history results.
	 *
	 * @param array  $results  Raw results.
	 * @param string $group_by Grouping method.
	 * @return array
	 */
	private function group_history_results( $results, $group_by ) {
		$grouped = array();

		foreach ( $results as $row ) {
			switch ( $group_by ) {
				case 'product':
					$key = $row['product_id'];
					if ( ! isset( $grouped[ $key ] ) ) {
						$grouped[ $key ] = array(
							'product_id'          => $row['product_id'],
							'product_name'        => $row['product_name'],
							'units_sold'          => 0,
							'total_discount'      => 0,
							'total_revenue'       => 0,
							'avg_discount_pct'    => 0,
							'discount_pct_sum'    => 0,
							'count'               => 0,
						);
					}
					$grouped[ $key ]['units_sold']       += $row['quantity'];
					$grouped[ $key ]['total_discount']   += $row['discount_amount'] * $row['quantity'];
					$grouped[ $key ]['total_revenue']    += $row['line_total'];
					$grouped[ $key ]['discount_pct_sum'] += $row['discount_pct'];
					$grouped[ $key ]['count']++;
					break;

				case 'category':
					$categories = wp_get_post_terms( $row['product_id'], 'product_cat', array( 'fields' => 'all' ) );
					foreach ( $categories as $cat ) {
						$key = $cat->term_id;
						if ( ! isset( $grouped[ $key ] ) ) {
							$grouped[ $key ] = array(
								'category_id'         => $cat->term_id,
								'category_name'       => $cat->name,
								'units_sold'          => 0,
								'total_discount'      => 0,
								'total_revenue'       => 0,
								'avg_discount_pct'    => 0,
								'discount_pct_sum'    => 0,
								'count'               => 0,
							);
						}
						$grouped[ $key ]['units_sold']       += $row['quantity'];
						$grouped[ $key ]['total_discount']   += $row['discount_amount'] * $row['quantity'];
						$grouped[ $key ]['total_revenue']    += $row['line_total'];
						$grouped[ $key ]['discount_pct_sum'] += $row['discount_pct'];
						$grouped[ $key ]['count']++;
					}
					break;

				case 'date':
					$key = substr( $row['order_date'], 0, 10 );
					if ( ! isset( $grouped[ $key ] ) ) {
						$grouped[ $key ] = array(
							'date'                => $key,
							'units_sold'          => 0,
							'total_discount'      => 0,
							'total_revenue'       => 0,
							'avg_discount_pct'    => 0,
							'discount_pct_sum'    => 0,
							'count'               => 0,
						);
					}
					$grouped[ $key ]['units_sold']       += $row['quantity'];
					$grouped[ $key ]['total_discount']   += $row['discount_amount'] * $row['quantity'];
					$grouped[ $key ]['total_revenue']    += $row['line_total'];
					$grouped[ $key ]['discount_pct_sum'] += $row['discount_pct'];
					$grouped[ $key ]['count']++;
					break;
			}
		}

		// Calculate averages.
		foreach ( $grouped as &$group ) {
			if ( $group['count'] > 0 ) {
				$group['avg_discount_pct'] = round( $group['discount_pct_sum'] / $group['count'], 2 );
			}
			$group['total_discount'] = round( $group['total_discount'], 2 );
			$group['total_revenue']  = round( $group['total_revenue'], 2 );
			unset( $group['discount_pct_sum'], $group['count'] );
		}

		return array_values( $grouped );
	}

	/**
	 * Get discount summary.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_discount_summary( $request ) {
		$date_from = $request->get_param( 'date_from' );
		$date_to   = $request->get_param( 'date_to' );

		// Try to read from custom table first (preferred method).
		if ( class_exists( 'WDA_Database' ) ) {
			$database = WDA_Database::instance();
			if ( $database && $database->table_exists() ) {
				return $this->get_discount_summary_from_table( $date_from, $date_to );
			}
		}

		// Fallback to order item meta method (backward compatibility).
		return $this->get_discount_summary_from_meta( $date_from, $date_to );
	}

	/**
	 * Get discount summary from custom table.
	 *
	 * @param string $date_from Date from.
	 * @param string $date_to   Date to.
	 * @return WP_REST_Response
	 */
	private function get_discount_summary_from_table( $date_from, $date_to ) {
		global $wpdb;

		if ( ! class_exists( 'WDA_Database' ) ) {
			return $this->get_discount_summary_from_meta( $date_from, $date_to );
		}

		$database = WDA_Database::instance();
		$table_name = WDA_Database::get_table_name();

		// Build WHERE clause.
		$where = array( 'refund_id = 0' ); // Only non-refunded items.

		if ( ! empty( $date_from ) ) {
			$where[] = $wpdb->prepare( 'created_at >= %s', $date_from );
		}

		if ( ! empty( $date_to ) ) {
			$where[] = $wpdb->prepare( 'created_at <= %s', $date_to . ' 23:59:59' );
		}

		$where_clause = 'WHERE ' . implode( ' AND ', $where );

		// Get all discount entries.
		$discounts = $wpdb->get_results(
			"SELECT * FROM {$table_name} {$where_clause}",
			ARRAY_A
		);

		$total_discount     = 0;
		$total_revenue      = 0;
		$discounted_units   = 0;
		$product_discounts = array();
		$order_ids         = array();

		foreach ( $discounts as $discount ) {
			$order_id = absint( $discount['order_id'] );
			if ( ! in_array( $order_id, $order_ids, true ) ) {
				$order_ids[] = $order_id;
			}

			$quantity = floatval( $discount['quantity'] );
			$line_discount = floatval( $discount['discount_amount'] ) * $quantity;
			$net_line_amount = floatval( $discount['sale_price'] ) * $quantity;

			$total_discount   += $line_discount;
			$discounted_units += $quantity;
			$total_revenue    += $net_line_amount;

			$product_id = absint( $discount['product_id'] );
			if ( ! isset( $product_discounts[ $product_id ] ) ) {
				$product = wc_get_product( $product_id );
				$product_name = $product ? $product->get_name() : sprintf( __( 'Product #%d', 'woo-discount-analytics' ), $product_id );
				$product_discounts[ $product_id ] = array(
					'product_id'     => $product_id,
					'product_name'   => $product_name,
					'total_discount' => 0,
					'units_sold'     => 0,
				);
			}
			$product_discounts[ $product_id ]['total_discount'] += $line_discount;
			$product_discounts[ $product_id ]['units_sold']     += $quantity;
		}

		// Sort products by discount amount.
		usort( $product_discounts, function( $a, $b ) {
			return $b['total_discount'] <=> $a['total_discount'];
		} );

		// Get top 10.
		$top_discounted = array_slice( $product_discounts, 0, 10 );

		// Round values.
		foreach ( $top_discounted as &$product ) {
			$product['total_discount'] = round( $product['total_discount'], 2 );
		}

		$discount_pct_of_revenue = $total_revenue > 0 ? ( $total_discount / $total_revenue ) * 100 : 0;

		return new WP_REST_Response( array(
			'total_discount'          => round( $total_discount, 2 ),
			'total_revenue'           => round( $total_revenue, 2 ),
			'discount_pct_of_revenue' => round( $discount_pct_of_revenue, 2 ),
			'discounted_units'        => $discounted_units,
			'orders_count'            => count( $order_ids ),
			'top_discounted_products' => $top_discounted,
		), 200 );
	}

	/**
	 * Get discount summary from order item meta (backward compatibility).
	 *
	 * @param string $date_from Date from.
	 * @param string $date_to   Date to.
	 * @return WP_REST_Response
	 */
	private function get_discount_summary_from_meta( $date_from, $date_to ) {
		// Build order query.
		$order_args = array(
			'status'  => array( 'wc-processing', 'wc-completed' ),
			'limit'   => -1,
			'return'  => 'ids',
		);

		if ( ! empty( $date_from ) ) {
			$order_args['date_created'] = '>=' . strtotime( $date_from );
		}

		if ( ! empty( $date_to ) ) {
			if ( isset( $order_args['date_created'] ) ) {
				$order_args['date_created'] .= '...' . strtotime( $date_to . ' 23:59:59' );
			} else {
				$order_args['date_created'] = '<=' . strtotime( $date_to . ' 23:59:59' );
			}
		}

		$order_ids = wc_get_orders( $order_args );

		$total_discount     = 0;
		$total_revenue      = 0;
		$discounted_units   = 0;
		$product_discounts  = array();

		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				continue;
			}

			$total_revenue += floatval( $order->get_total() );
			$items          = $order->get_items( 'line_item' );

			foreach ( $items as $item_id => $item ) {
				$discount_data = WDA_Discount_Capture::get_item_discount_data( $item_id );

				if ( ! $discount_data || $discount_data['was_on_sale'] !== 'yes' ) {
					continue;
				}

				$quantity        = $item->get_quantity();
				$discount_amount = floatval( $discount_data['discount_amount'] ) * $quantity;

				$total_discount   += $discount_amount;
				$discounted_units += $quantity;

				$product_id = $item->get_product_id();
				if ( ! isset( $product_discounts[ $product_id ] ) ) {
					$product      = $item->get_product();
					$product_name = $product ? $product->get_name() : $item->get_name();
					$product_discounts[ $product_id ] = array(
						'product_id'     => $product_id,
						'product_name'   => $product_name,
						'total_discount' => 0,
						'units_sold'     => 0,
					);
				}
				$product_discounts[ $product_id ]['total_discount'] += $discount_amount;
				$product_discounts[ $product_id ]['units_sold']     += $quantity;
			}
		}

		// Sort products by discount amount.
		usort( $product_discounts, function( $a, $b ) {
			return $b['total_discount'] <=> $a['total_discount'];
		} );

		// Get top 10.
		$top_discounted = array_slice( $product_discounts, 0, 10 );

		// Round values.
		foreach ( $top_discounted as &$product ) {
			$product['total_discount'] = round( $product['total_discount'], 2 );
		}

		$discount_pct_of_revenue = $total_revenue > 0 ? ( $total_discount / $total_revenue ) * 100 : 0;

		return new WP_REST_Response( array(
			'total_discount'          => round( $total_discount, 2 ),
			'total_revenue'           => round( $total_revenue, 2 ),
			'discount_pct_of_revenue' => round( $discount_pct_of_revenue, 2 ),
			'discounted_units'        => $discounted_units,
			'orders_count'            => count( $order_ids ),
			'top_discounted_products' => $top_discounted,
		), 200 );
	}

	/**
	 * Export CSV.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|void
	 */
	public function export_csv( $request ) {
		$type      = $request->get_param( 'type' );
		$date_from = $request->get_param( 'date_from' );
		$date_to   = $request->get_param( 'date_to' );

		// Set unlimited request params for export.
		$request->set_param( 'page', 1 );
		$request->set_param( 'per_page', 10000 );

		switch ( $type ) {
			case 'current-discounts':
				$response = $this->get_current_discounts( $request );
				$data     = $response->get_data();
				$items    = $data['items'];
				$filename = 'current-discounts-' . gmdate( 'Y-m-d' ) . '.csv';
				$headers  = array( 'ID', 'Name', 'Type', 'SKU', 'Regular Price', 'Sale Price', 'Discount Amount', 'Discount %', 'Sale Start', 'Sale End', 'Status', 'Stock Status' );
				$rows     = array();
				foreach ( $items as $item ) {
					$rows[] = array(
						$item['id'],
						$item['name'],
						$item['type'],
						$item['sku'],
						$item['regular_price'],
						$item['sale_price'],
						$item['discount_amount'],
						$item['discount_pct'],
						$item['sale_start'],
						$item['sale_end'],
						$item['sale_status'],
						$item['stock_status'],
					);
				}
				break;

			case 'discount-history':
				$response = $this->get_discount_history( $request );
				$data     = $response->get_data();
				$items    = $data['items'];
				$filename = 'discount-history-' . gmdate( 'Y-m-d' ) . '.csv';
				$headers  = array( 'Order ID', 'Order Date', 'Product ID', 'Product Name', 'Quantity', 'Regular Price', 'Sale Price', 'Discount Amount', 'Discount %', 'Line Total' );
				$rows     = array();
				foreach ( $items as $item ) {
					$rows[] = array(
						$item['order_id'],
						$item['order_date'],
						$item['product_id'],
						$item['product_name'],
						$item['quantity'],
						$item['regular_price'],
						$item['sale_price'],
						$item['discount_amount'],
						$item['discount_pct'],
						$item['line_total'],
					);
				}
				break;

			case 'discount-summary':
				$response = $this->get_discount_summary( $request );
				$data     = $response->get_data();
				$filename = 'discount-summary-' . gmdate( 'Y-m-d' ) . '.csv';
				$headers  = array( 'Metric', 'Value' );
				$rows     = array(
					array( 'Total Discount', $data['total_discount'] ),
					array( 'Total Revenue', $data['total_revenue'] ),
					array( 'Discount % of Revenue', $data['discount_pct_of_revenue'] ),
					array( 'Discounted Units', $data['discounted_units'] ),
					array( 'Orders Count', $data['orders_count'] ),
				);

				// Add top products section.
				$rows[] = array( '', '' );
				$rows[] = array( 'Top Discounted Products', '' );
				$rows[] = array( 'Product Name', 'Total Discount' );
				foreach ( $data['top_discounted_products'] as $product ) {
					$rows[] = array( $product['product_name'], $product['total_discount'] );
				}
				break;

			default:
				return new WP_REST_Response( array( 'error' => 'Invalid export type.' ), 400 );
		}

		// Output CSV.
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, $headers );

		foreach ( $rows as $row ) {
			fputcsv( $output, $row );
		}

		fclose( $output );
		exit;
	}
}
