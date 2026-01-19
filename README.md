# WooCommerce Discount Analytics

A WordPress plugin that provides visibility and reporting for products discounted via sale prices in WooCommerce.

## Description

WooCommerce Discount Analytics helps store owners understand the impact of their sale pricing strategy by:

- Showing all products currently on sale with their discount percentages
- Tracking historical discount data when orders are placed
- Providing summary metrics on total discounts given

The plugin adds a dedicated "Discount Analytics" menu to your WordPress admin with three report pages.

## Features

### Current Discounts Report
Scans your entire product catalog to find products and variations with sale prices configured.

- View all discounted products in a sortable table
- See regular price, sale price, discount amount, and discount percentage
- Filter by sale status (Active, Scheduled, Expired, or All)
- Filter by minimum/maximum discount percentage
- Export results to CSV

### Discount History Report
Shows historical data on discounted products that have been sold.

- View order-by-order breakdown of discounted sales
- Group data by Product, Category, or Date
- Filter by date range
- See units sold, total discount given, and revenue
- Export results to CSV

### Discount Summary Report
Provides aggregate metrics on your discount strategy.

- Total discounts given
- Total revenue
- Discount as percentage of revenue
- Units sold at discount
- Top 10 most discounted products
- Filter by date range
- Export results to CSV

## Requirements

- WordPress 6.0 or higher
- WooCommerce 7.0 or higher
- PHP 7.4 or higher (compatible with PHP 8.0 - 8.3)

## Installation

1. Upload the `woo-discount-analytics` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to **Discount Analytics** in the admin menu

## Usage

### Viewing Current Discounts

1. Go to **Discount Analytics > Current Discounts**
2. The page displays all products with sale prices set
3. Use filters to narrow results:
   - **Sale Status**: Active (currently on sale), Scheduled (future sale), Expired (past sale), or All
   - **Min/Max Discount %**: Filter by discount percentage range
4. Click column headers to sort
5. Click **Export CSV** to download the data

### Viewing Discount History

1. Go to **Discount Analytics > Discount History**
2. Set date range filters to view specific periods
3. Use **Group By** to aggregate data:
   - **None**: Shows individual order line items
   - **Product**: Groups by product with totals
   - **Category**: Groups by product category
   - **Date**: Groups by order date
4. Click **Export CSV** to download the data

### Viewing Discount Summary

1. Go to **Discount Analytics > Discount Summary**
2. Set date range filters if needed
3. View summary cards with key metrics
4. See the top 10 most discounted products
5. Click **Export CSV** to download the data

## How It Works

### Data Capture

When an order status changes to "Processing" or "Completed", the plugin captures discount data for each line item:

- Regular price at time of sale
- Sale price (actual price paid)
- Discount amount and percentage
- Whether the product was on sale

This data is stored in order item meta and used for historical reporting.

### Current Discounts

The Current Discounts report queries your product catalog directly using WooCommerce's product API. It:

1. Fetches all published products and variations
2. Filters to find those with sale prices set
3. Calculates discount amounts and percentages
4. Determines sale status based on sale date ranges

### HPOS Compatibility

This plugin is fully compatible with WooCommerce's High-Performance Order Storage (HPOS). It uses WooCommerce's data APIs exclusively and does not make direct database queries to order tables.

## File Structure

```
woo-discount-analytics/
├── woo-discount-analytics.php    # Main plugin file
├── includes/
│   ├── class-wda-admin-reports.php   # Admin menu and pages
│   ├── class-wda-discount-capture.php # Order data capture
│   └── class-wda-rest-reports.php    # REST API endpoints
├── assets/
│   ├── css/admin/reports.css     # Admin styles
│   └── js/admin/reports/index.js # Admin JavaScript
└── README.md
```

## REST API Endpoints

The plugin registers the following REST API endpoints under the `wda/v1` namespace:

| Endpoint | Description |
|----------|-------------|
| `GET /current-discounts` | Get products with sale prices |
| `GET /discount-history` | Get historical discount data |
| `GET /discount-summary` | Get aggregate discount metrics |
| `GET /export/{type}` | Export report data as CSV |

All endpoints require `manage_woocommerce` capability.

## Hooks & Filters

### Actions

- `wda_loaded` - Fired after the plugin is fully loaded
- `wda_discount_data_captured` - Fired after discount data is captured for an order
- `wda_item_discount_captured` - Fired after discount data is captured for a single item

### Meta Keys

The plugin stores the following order item meta:

| Meta Key | Description |
|----------|-------------|
| `_wda_regular_price` | Product regular price at time of order |
| `_wda_sale_price` | Actual price paid |
| `_wda_discount_amount` | Discount amount per unit |
| `_wda_discount_pct` | Discount percentage |
| `_wda_was_on_sale` | Whether product was on sale (yes/no/unknown) |

Order-level meta:

| Meta Key | Description |
|----------|-------------|
| `_wda_captured` | Whether discount data has been captured (yes) |

## Changelog

### 1.0.0
- Initial release
- Current Discounts report
- Discount History report with grouping
- Discount Summary report
- CSV export functionality
- HPOS compatibility

## License

GPL-2.0+
