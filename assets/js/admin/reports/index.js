/**
 * WooCommerce Discount Analytics - Admin Reports JS
 *
 * Handles the admin report page functionality using vanilla JavaScript.
 *
 * @package WooDiscountAnalytics
 */

( function( $, wdaSettings ) {
	'use strict';

	/**
	 * Format currency value.
	 */
	function formatCurrency( value ) {
		const num = parseFloat( value ) || 0;
		return wdaSettings.symbol + num.toFixed( 2 );
	}

	/**
	 * Format percentage value.
	 */
	function formatPercent( value ) {
		const num = parseFloat( value ) || 0;
		return num.toFixed( 2 ) + '%';
	}

	/**
	 * Format date for display.
	 */
	function formatDate( dateStr ) {
		if ( ! dateStr ) return '—';
		const date = new Date( dateStr );
		return date.toLocaleDateString();
	}

	/**
	 * Escape HTML.
	 */
	function escapeHtml( str ) {
		if ( ! str ) return '';
		const div = document.createElement( 'div' );
		div.textContent = str;
		return div.innerHTML;
	}

	/**
	 * Make API request.
	 */
	function apiFetch( endpoint, params = {} ) {
		const url = new URL( wdaSettings.restUrl + endpoint, window.location.origin );
		Object.keys( params ).forEach( key => {
			if ( params[ key ] !== '' && params[ key ] !== null && params[ key ] !== undefined ) {
				url.searchParams.append( key, params[ key ] );
			}
		} );

		return fetch( url.toString(), {
			method: 'GET',
			headers: {
				'X-WP-Nonce': wdaSettings.nonce,
				'Content-Type': 'application/json'
			}
		} ).then( response => {
			if ( ! response.ok ) {
				throw new Error( 'HTTP error! status: ' + response.status );
			}
			return response.json();
		} );
	}

	/**
	 * Current Discounts Report
	 */
	const CurrentDiscountsReport = {
		container: null,
		state: {
			items: [],
			total: 0,
			totalPages: 0,
			page: 1,
			perPage: 25,
			loading: true,
			error: null,
			filters: {
				category: 0,
				product_type: '',
				discount_min: 0,
				discount_max: 100,
				sale_status: 'all',
				orderby: 'discount_pct',
				order: 'DESC'
			}
		},

		init: function( containerId ) {
			this.container = document.getElementById( containerId );
			if ( ! this.container ) return;
			this.fetchData();
		},

		fetchData: function() {
			const self = this;
			self.state.loading = true;
			self.state.error = null;
			self.render();

			const params = {
				page: self.state.page,
				per_page: self.state.perPage,
				...self.state.filters
			};

			apiFetch( 'current-discounts', params )
				.then( function( data ) {
					if ( data.code ) {
						// WordPress error response
						throw new Error( data.message || 'Unknown error' );
					}
					self.state.items = data.items || [];
					self.state.total = data.total || 0;
					self.state.totalPages = data.total_pages || 0;
					self.state.loading = false;
					self.state.error = null;
					self.render();
				} )
				.catch( function( error ) {
					console.error( 'Error fetching current discounts:', error );
					self.state.loading = false;
					self.state.error = error.message || 'Failed to load data';
					self.render();
				} );
		},

		handleFilterChange: function( key, value ) {
			this.state.filters[ key ] = value;
			this.state.page = 1;
			this.fetchData();
		},

		handlePageChange: function( newPage ) {
			this.state.page = newPage;
			this.fetchData();
		},

		handleSort: function( column ) {
			const filters = this.state.filters;
			filters.order = ( filters.orderby === column && filters.order === 'DESC' ) ? 'ASC' : 'DESC';
			filters.orderby = column;
			this.fetchData();
		},

		handleExport: function() {
			window.location.href = wdaSettings.restUrl + 'export/current-discounts?_wpnonce=' + wdaSettings.nonce;
		},

		renderStatusBadge: function( status ) {
			const label = status.charAt( 0 ).toUpperCase() + status.slice( 1 );
			return '<span class="wda-status-badge ' + status + '">' + label + '</span>';
		},

		renderStockBadge: function( status ) {
			const labels = {
				instock: 'In Stock',
				outofstock: 'Out of Stock',
				onbackorder: 'On Backorder'
			};
			return '<span class="wda-stock-badge ' + status + '">' + ( labels[ status ] || status ) + '</span>';
		},

		render: function() {
			const self = this;
			const state = self.state;

			if ( state.loading ) {
				self.container.innerHTML = '<div class="wda-loading"><div class="wda-loading-spinner"></div></div>';
				return;
			}

			if ( state.error ) {
				self.container.innerHTML = '<div class="wda-empty-state">' +
					'<div class="wda-empty-state-icon">⚠️</div>' +
					'<div class="wda-empty-state-title">Error Loading Data</div>' +
					'<div class="wda-empty-state-description">' + escapeHtml( state.error ) + '</div>' +
					'<button type="button" class="wda-btn" id="wda-retry-btn" style="margin-top: 16px;">Retry</button>' +
					'</div>';
				const retryBtn = document.getElementById( 'wda-retry-btn' );
				if ( retryBtn ) {
					retryBtn.addEventListener( 'click', function() {
						self.fetchData();
					} );
				}
				return;
			}

			let html = '';

			// Filters
			html += '<div class="wda-report-filters">';
			html += '<div class="wda-filter-group">';
			html += '<label>Sale Status</label>';
			html += '<select id="wda-filter-status">';
			html += '<option value="all"' + ( state.filters.sale_status === 'all' ? ' selected' : '' ) + '>All</option>';
			html += '<option value="active"' + ( state.filters.sale_status === 'active' ? ' selected' : '' ) + '>Active</option>';
			html += '<option value="scheduled"' + ( state.filters.sale_status === 'scheduled' ? ' selected' : '' ) + '>Scheduled</option>';
			html += '<option value="expired"' + ( state.filters.sale_status === 'expired' ? ' selected' : '' ) + '>Expired</option>';
			html += '</select></div>';

			html += '<div class="wda-filter-group">';
			html += '<label>Min Discount %</label>';
			html += '<input type="number" id="wda-filter-min" value="' + state.filters.discount_min + '" min="0" max="100">';
			html += '</div>';

			html += '<div class="wda-filter-group">';
			html += '<label>Max Discount %</label>';
			html += '<input type="number" id="wda-filter-max" value="' + state.filters.discount_max + '" min="0" max="100">';
			html += '</div>';

			html += '<div class="wda-filter-group" style="align-self: flex-end;">';
			html += '<button type="button" class="wda-btn secondary" id="wda-export-btn">Export CSV</button>';
			html += '</div>';
			html += '</div>';

			// Results count
			html += '<p style="margin-bottom: 12px; color: #757575;">' + state.total + ' products found</p>';

			if ( state.items.length > 0 ) {
				// Table
				html += '<div class="wda-report-table-container">';
				html += '<table class="wda-report-table">';
				html += '<thead><tr>';
				html += '<th>Product</th>';
				html += '<th>Type</th>';
				html += '<th class="col-numeric sortable" data-sort="regular_price">Regular</th>';
				html += '<th class="col-numeric sortable" data-sort="sale_price">Sale</th>';
				html += '<th class="col-numeric sortable" data-sort="discount_amount">Discount</th>';
				html += '<th class="col-numeric sortable" data-sort="discount_pct">Discount %</th>';
				html += '<th>Sale Period</th>';
				html += '<th>Status</th>';
				html += '<th>Stock</th>';
				html += '</tr></thead>';
				html += '<tbody>';

				state.items.forEach( function( item ) {
					html += '<tr>';
					html += '<td><a href="' + escapeHtml( item.edit_link ) + '" target="_blank">' + escapeHtml( item.name ) + '</a></td>';
					html += '<td>' + escapeHtml( item.type ) + '</td>';
					html += '<td class="col-numeric">' + formatCurrency( item.regular_price ) + '</td>';
					html += '<td class="col-numeric">' + formatCurrency( item.sale_price ) + '</td>';
					html += '<td class="col-numeric">' + formatCurrency( item.discount_amount ) + '</td>';
					html += '<td class="col-numeric">' + formatPercent( item.discount_pct ) + '</td>';
					html += '<td>' + ( item.sale_start || item.sale_end ? formatDate( item.sale_start ) + ' - ' + formatDate( item.sale_end ) : 'No dates set' ) + '</td>';
					html += '<td>' + self.renderStatusBadge( item.sale_status ) + '</td>';
					html += '<td>' + self.renderStockBadge( item.stock_status ) + '</td>';
					html += '</tr>';
				} );

				html += '</tbody></table>';

				// Pagination
				if ( state.totalPages > 1 ) {
					html += '<div class="wda-pagination">';
					html += '<div class="wda-pagination-info">Page ' + state.page + ' of ' + state.totalPages + '</div>';
					html += '<div class="wda-pagination-controls">';
					html += '<button class="wda-pagination-btn" id="wda-prev-page"' + ( state.page <= 1 ? ' disabled' : '' ) + '>Previous</button>';
					html += '<button class="wda-pagination-btn" id="wda-next-page"' + ( state.page >= state.totalPages ? ' disabled' : '' ) + '>Next</button>';
					html += '</div></div>';
				}

				html += '</div>';
			} else {
				html += '<div class="wda-empty-state">';
				html += '<div class="wda-empty-state-icon">📦</div>';
				html += '<div class="wda-empty-state-title">No discounted products found</div>';
				html += '<div class="wda-empty-state-description">Try adjusting your filters or add sale prices to your products.</div>';
				html += '</div>';
			}

			self.container.innerHTML = html;
			self.bindEvents();
		},

		bindEvents: function() {
			const self = this;

			const statusSelect = document.getElementById( 'wda-filter-status' );
			if ( statusSelect ) {
				statusSelect.addEventListener( 'change', function() {
					self.handleFilterChange( 'sale_status', this.value );
				} );
			}

			const minInput = document.getElementById( 'wda-filter-min' );
			if ( minInput ) {
				minInput.addEventListener( 'change', function() {
					self.handleFilterChange( 'discount_min', parseInt( this.value ) || 0 );
				} );
			}

			const maxInput = document.getElementById( 'wda-filter-max' );
			if ( maxInput ) {
				maxInput.addEventListener( 'change', function() {
					self.handleFilterChange( 'discount_max', parseInt( this.value ) || 100 );
				} );
			}

			const exportBtn = document.getElementById( 'wda-export-btn' );
			if ( exportBtn ) {
				exportBtn.addEventListener( 'click', function() {
					self.handleExport();
				} );
			}

			const prevBtn = document.getElementById( 'wda-prev-page' );
			if ( prevBtn ) {
				prevBtn.addEventListener( 'click', function() {
					if ( self.state.page > 1 ) {
						self.handlePageChange( self.state.page - 1 );
					}
				} );
			}

			const nextBtn = document.getElementById( 'wda-next-page' );
			if ( nextBtn ) {
				nextBtn.addEventListener( 'click', function() {
					if ( self.state.page < self.state.totalPages ) {
						self.handlePageChange( self.state.page + 1 );
					}
				} );
			}

			const sortHeaders = self.container.querySelectorAll( '.sortable' );
			sortHeaders.forEach( function( header ) {
				header.addEventListener( 'click', function() {
					self.handleSort( this.dataset.sort );
				} );
			} );
		}
	};

	/**
	 * Discount History Report
	 */
	const DiscountHistoryReport = {
		container: null,
		state: {
			items: [],
			total: 0,
			totalPages: 0,
			page: 1,
			perPage: 25,
			loading: true,
			filters: {
				date_from: '',
				date_to: '',
				product_id: 0,
				category: 0,
				group_by: ''
			}
		},

		init: function( containerId ) {
			this.container = document.getElementById( containerId );
			if ( ! this.container ) return;
			this.fetchData();
		},

		fetchData: function() {
			const self = this;
			self.state.loading = true;
			self.render();

			const params = {
				page: self.state.page,
				per_page: self.state.perPage,
				...self.state.filters
			};

			apiFetch( 'discount-history', params )
				.then( function( data ) {
					self.state.items = data.items || [];
					self.state.total = data.total || 0;
					self.state.totalPages = data.total_pages || 0;
					self.state.loading = false;
					self.render();
				} )
				.catch( function( error ) {
					console.error( 'Error fetching discount history:', error );
					self.state.loading = false;
					self.render();
				} );
		},

		handleFilterChange: function( key, value ) {
			this.state.filters[ key ] = value;
			this.state.page = 1;
			this.fetchData();
		},

		handlePageChange: function( newPage ) {
			this.state.page = newPage;
			this.fetchData();
		},

		handleExport: function() {
			const filters = this.state.filters;
			let url = wdaSettings.restUrl + 'export/discount-history?_wpnonce=' + wdaSettings.nonce;
			if ( filters.date_from ) url += '&date_from=' + filters.date_from;
			if ( filters.date_to ) url += '&date_to=' + filters.date_to;
			window.location.href = url;
		},

		renderGroupedTable: function() {
			const self = this;
			const state = self.state;
			const groupBy = state.filters.group_by;
			let html = '<table class="wda-report-table">';

			if ( groupBy === 'product' ) {
				html += '<thead><tr>';
				html += '<th>Product</th>';
				html += '<th class="col-numeric">Units Sold</th>';
				html += '<th class="col-numeric">Total Discount</th>';
				html += '<th class="col-numeric">Avg Discount %</th>';
				html += '<th class="col-numeric">Revenue</th>';
				html += '</tr></thead><tbody>';

				state.items.forEach( function( item ) {
					html += '<tr>';
					html += '<td>' + escapeHtml( item.product_name ) + '</td>';
					html += '<td class="col-numeric">' + item.units_sold + '</td>';
					html += '<td class="col-numeric">' + formatCurrency( item.total_discount ) + '</td>';
					html += '<td class="col-numeric">' + formatPercent( item.avg_discount_pct ) + '</td>';
					html += '<td class="col-numeric">' + formatCurrency( item.total_revenue ) + '</td>';
					html += '</tr>';
				} );
			} else if ( groupBy === 'category' ) {
				html += '<thead><tr>';
				html += '<th>Category</th>';
				html += '<th class="col-numeric">Units Sold</th>';
				html += '<th class="col-numeric">Total Discount</th>';
				html += '<th class="col-numeric">Avg Discount %</th>';
				html += '<th class="col-numeric">Revenue</th>';
				html += '</tr></thead><tbody>';

				state.items.forEach( function( item ) {
					html += '<tr>';
					html += '<td>' + escapeHtml( item.category_name ) + '</td>';
					html += '<td class="col-numeric">' + item.units_sold + '</td>';
					html += '<td class="col-numeric">' + formatCurrency( item.total_discount ) + '</td>';
					html += '<td class="col-numeric">' + formatPercent( item.avg_discount_pct ) + '</td>';
					html += '<td class="col-numeric">' + formatCurrency( item.total_revenue ) + '</td>';
					html += '</tr>';
				} );
			} else if ( groupBy === 'date' ) {
				html += '<thead><tr>';
				html += '<th>Date</th>';
				html += '<th class="col-numeric">Units Sold</th>';
				html += '<th class="col-numeric">Total Discount</th>';
				html += '<th class="col-numeric">Avg Discount %</th>';
				html += '<th class="col-numeric">Revenue</th>';
				html += '</tr></thead><tbody>';

				state.items.forEach( function( item ) {
					html += '<tr>';
					html += '<td>' + escapeHtml( item.date ) + '</td>';
					html += '<td class="col-numeric">' + item.units_sold + '</td>';
					html += '<td class="col-numeric">' + formatCurrency( item.total_discount ) + '</td>';
					html += '<td class="col-numeric">' + formatPercent( item.avg_discount_pct ) + '</td>';
					html += '<td class="col-numeric">' + formatCurrency( item.total_revenue ) + '</td>';
					html += '</tr>';
				} );
			} else {
				// Ungrouped
				html += '<thead><tr>';
				html += '<th>Order</th>';
				html += '<th>Date</th>';
				html += '<th>Product</th>';
				html += '<th class="col-numeric">Qty</th>';
				html += '<th class="col-numeric">Regular</th>';
				html += '<th class="col-numeric">Sale</th>';
				html += '<th class="col-numeric">Discount</th>';
				html += '<th class="col-numeric">Discount %</th>';
				html += '<th class="col-numeric">Line Total</th>';
				html += '</tr></thead><tbody>';

				state.items.forEach( function( item ) {
					html += '<tr>';
					html += '<td><a href="' + wdaSettings.adminUrl + 'post.php?post=' + item.order_id + '&action=edit" target="_blank">#' + item.order_id + '</a></td>';
					html += '<td>' + formatDate( item.order_date ) + '</td>';
					html += '<td>' + escapeHtml( item.product_name ) + '</td>';
					html += '<td class="col-numeric">' + item.quantity + '</td>';
					html += '<td class="col-numeric">' + formatCurrency( item.regular_price ) + '</td>';
					html += '<td class="col-numeric">' + formatCurrency( item.sale_price ) + '</td>';
					html += '<td class="col-numeric">' + formatCurrency( item.discount_amount ) + '</td>';
					html += '<td class="col-numeric">' + formatPercent( item.discount_pct ) + '</td>';
					html += '<td class="col-numeric">' + formatCurrency( item.line_total ) + '</td>';
					html += '</tr>';
				} );
			}

			html += '</tbody></table>';
			return html;
		},

		render: function() {
			const self = this;
			const state = self.state;

			if ( state.loading ) {
				self.container.innerHTML = '<div class="wda-loading"><div class="wda-loading-spinner"></div></div>';
				return;
			}

			let html = '';

			// Filters
			html += '<div class="wda-report-filters">';

			html += '<div class="wda-filter-group">';
			html += '<label>Date From</label>';
			html += '<input type="date" id="wda-filter-from" value="' + state.filters.date_from + '">';
			html += '</div>';

			html += '<div class="wda-filter-group">';
			html += '<label>Date To</label>';
			html += '<input type="date" id="wda-filter-to" value="' + state.filters.date_to + '">';
			html += '</div>';

			html += '<div class="wda-filter-group">';
			html += '<label>Group By</label>';
			html += '<select id="wda-filter-group">';
			html += '<option value=""' + ( state.filters.group_by === '' ? ' selected' : '' ) + '>None</option>';
			html += '<option value="product"' + ( state.filters.group_by === 'product' ? ' selected' : '' ) + '>Product</option>';
			html += '<option value="category"' + ( state.filters.group_by === 'category' ? ' selected' : '' ) + '>Category</option>';
			html += '<option value="date"' + ( state.filters.group_by === 'date' ? ' selected' : '' ) + '>Date</option>';
			html += '</select></div>';

			html += '<div class="wda-filter-group" style="align-self: flex-end;">';
			html += '<button type="button" class="wda-btn secondary" id="wda-export-btn">Export CSV</button>';
			html += '</div>';
			html += '</div>';

			// Results count
			html += '<p style="margin-bottom: 12px; color: #757575;">' + state.total + ' records found</p>';

			if ( state.items.length > 0 ) {
				html += '<div class="wda-report-table-container">';
				html += self.renderGroupedTable();

				// Pagination
				if ( state.totalPages > 1 ) {
					html += '<div class="wda-pagination">';
					html += '<div class="wda-pagination-info">Page ' + state.page + ' of ' + state.totalPages + '</div>';
					html += '<div class="wda-pagination-controls">';
					html += '<button class="wda-pagination-btn" id="wda-prev-page"' + ( state.page <= 1 ? ' disabled' : '' ) + '>Previous</button>';
					html += '<button class="wda-pagination-btn" id="wda-next-page"' + ( state.page >= state.totalPages ? ' disabled' : '' ) + '>Next</button>';
					html += '</div></div>';
				}

				html += '</div>';
			} else {
				html += '<div class="wda-empty-state">';
				html += '<div class="wda-empty-state-icon">📊</div>';
				html += '<div class="wda-empty-state-title">No discount history found</div>';
				html += '<div class="wda-empty-state-description">Discount data is captured when orders are completed. Try adjusting your date range.</div>';
				html += '</div>';
			}

			self.container.innerHTML = html;
			self.bindEvents();
		},

		bindEvents: function() {
			const self = this;

			const fromInput = document.getElementById( 'wda-filter-from' );
			if ( fromInput ) {
				fromInput.addEventListener( 'change', function() {
					self.handleFilterChange( 'date_from', this.value );
				} );
			}

			const toInput = document.getElementById( 'wda-filter-to' );
			if ( toInput ) {
				toInput.addEventListener( 'change', function() {
					self.handleFilterChange( 'date_to', this.value );
				} );
			}

			const groupSelect = document.getElementById( 'wda-filter-group' );
			if ( groupSelect ) {
				groupSelect.addEventListener( 'change', function() {
					self.handleFilterChange( 'group_by', this.value );
				} );
			}

			const exportBtn = document.getElementById( 'wda-export-btn' );
			if ( exportBtn ) {
				exportBtn.addEventListener( 'click', function() {
					self.handleExport();
				} );
			}

			const prevBtn = document.getElementById( 'wda-prev-page' );
			if ( prevBtn ) {
				prevBtn.addEventListener( 'click', function() {
					if ( self.state.page > 1 ) {
						self.handlePageChange( self.state.page - 1 );
					}
				} );
			}

			const nextBtn = document.getElementById( 'wda-next-page' );
			if ( nextBtn ) {
				nextBtn.addEventListener( 'click', function() {
					if ( self.state.page < self.state.totalPages ) {
						self.handlePageChange( self.state.page + 1 );
					}
				} );
			}
		}
	};

	/**
	 * Discount Summary Report
	 */
	const DiscountSummaryReport = {
		container: null,
		state: {
			data: null,
			loading: true,
			filters: {
				date_from: '',
				date_to: ''
			}
		},

		init: function( containerId ) {
			this.container = document.getElementById( containerId );
			if ( ! this.container ) return;
			this.fetchData();
		},

		fetchData: function() {
			const self = this;
			self.state.loading = true;
			self.render();

			apiFetch( 'discount-summary', self.state.filters )
				.then( function( data ) {
					self.state.data = data;
					self.state.loading = false;
					self.render();
				} )
				.catch( function( error ) {
					console.error( 'Error fetching discount summary:', error );
					self.state.loading = false;
					self.render();
				} );
		},

		handleFilterChange: function( key, value ) {
			this.state.filters[ key ] = value;
			this.fetchData();
		},

		handleExport: function() {
			const filters = this.state.filters;
			let url = wdaSettings.restUrl + 'export/discount-summary?_wpnonce=' + wdaSettings.nonce;
			if ( filters.date_from ) url += '&date_from=' + filters.date_from;
			if ( filters.date_to ) url += '&date_to=' + filters.date_to;
			window.location.href = url;
		},

		render: function() {
			const self = this;
			const state = self.state;

			if ( state.loading ) {
				self.container.innerHTML = '<div class="wda-loading"><div class="wda-loading-spinner"></div></div>';
				return;
			}

			if ( ! state.data ) {
				self.container.innerHTML = '<div class="wda-empty-state"><div class="wda-empty-state-title">Unable to load summary</div></div>';
				return;
			}

			const data = state.data;
			let html = '';

			// Filters
			html += '<div class="wda-report-filters">';

			html += '<div class="wda-filter-group">';
			html += '<label>Date From</label>';
			html += '<input type="date" id="wda-filter-from" value="' + state.filters.date_from + '">';
			html += '</div>';

			html += '<div class="wda-filter-group">';
			html += '<label>Date To</label>';
			html += '<input type="date" id="wda-filter-to" value="' + state.filters.date_to + '">';
			html += '</div>';

			html += '<div class="wda-filter-group" style="align-self: flex-end;">';
			html += '<button type="button" class="wda-btn secondary" id="wda-export-btn">Export CSV</button>';
			html += '</div>';
			html += '</div>';

			// Summary Cards
			html += '<div class="wda-summary-cards">';

			html += '<div class="wda-summary-card">';
			html += '<div class="wda-summary-card-label">Total Discounts Given</div>';
			html += '<div class="wda-summary-card-value">' + formatCurrency( data.total_discount ) + '</div>';
			html += '</div>';

			html += '<div class="wda-summary-card">';
			html += '<div class="wda-summary-card-label">Total Revenue</div>';
			html += '<div class="wda-summary-card-value">' + formatCurrency( data.total_revenue ) + '</div>';
			html += '</div>';

			html += '<div class="wda-summary-card">';
			html += '<div class="wda-summary-card-label">Discount % of Revenue</div>';
			html += '<div class="wda-summary-card-value">' + formatPercent( data.discount_pct_of_revenue ) + '</div>';
			html += '</div>';

			html += '<div class="wda-summary-card">';
			html += '<div class="wda-summary-card-label">Units Sold at Discount</div>';
			html += '<div class="wda-summary-card-value">' + data.discounted_units + '</div>';
			html += '</div>';

			html += '<div class="wda-summary-card">';
			html += '<div class="wda-summary-card-label">Orders Analyzed</div>';
			html += '<div class="wda-summary-card-value">' + data.orders_count + '</div>';
			html += '</div>';

			html += '</div>';

			// Top Discounted Products
			if ( data.top_discounted_products && data.top_discounted_products.length > 0 ) {
				html += '<div class="wda-top-products">';
				html += '<div class="wda-top-products-header">Top Discounted Products</div>';
				html += '<ul class="wda-top-products-list">';

				data.top_discounted_products.forEach( function( product, idx ) {
					html += '<li class="wda-top-products-item">';
					html += '<span class="wda-top-products-item-rank">' + ( idx + 1 ) + '</span>';
					html += '<span class="wda-top-products-item-name">' + escapeHtml( product.product_name ) + '</span>';
					html += '<span class="wda-top-products-item-value">' + formatCurrency( product.total_discount ) + '</span>';
					html += '</li>';
				} );

				html += '</ul></div>';
			}

			self.container.innerHTML = html;
			self.bindEvents();
		},

		bindEvents: function() {
			const self = this;

			const fromInput = document.getElementById( 'wda-filter-from' );
			if ( fromInput ) {
				fromInput.addEventListener( 'change', function() {
					self.handleFilterChange( 'date_from', this.value );
				} );
			}

			const toInput = document.getElementById( 'wda-filter-to' );
			if ( toInput ) {
				toInput.addEventListener( 'change', function() {
					self.handleFilterChange( 'date_to', this.value );
				} );
			}

			const exportBtn = document.getElementById( 'wda-export-btn' );
			if ( exportBtn ) {
				exportBtn.addEventListener( 'click', function() {
					self.handleExport();
				} );
			}
		}
	};

	/**
	 * Initialize on DOM ready.
	 */
	$( document ).ready( function() {
		// Initialize the appropriate report based on the container present
		if ( document.getElementById( 'wda-current-discounts-app' ) ) {
			CurrentDiscountsReport.init( 'wda-current-discounts-app' );
		}

		if ( document.getElementById( 'wda-discount-history-app' ) ) {
			DiscountHistoryReport.init( 'wda-discount-history-app' );
		}

		if ( document.getElementById( 'wda-discount-summary-app' ) ) {
			DiscountSummaryReport.init( 'wda-discount-summary-app' );
		}
	} );

} )( jQuery, window.wdaSettings || {} );
