<?php
/**
 * Plugin Name: Advanced COGS & Profit for WooCommerce
 * Description: Adds profit calculation capabilities to WooCommerce
 * Version: 1.0.0
 * Author: Tag Pilot
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * Requires Plugins: woocommerce
 * License:     GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

// https://developer.woocommerce.com/2025/05/21/cost-of-goods-soldcogs-is-ready-to-blossom-out-of-beta-this-summer/

defined('ABSPATH') || exit;

class Advanced_Cogs_Profit_Woo {

	protected $slug;
	protected $slug_snake_case;
	protected $cache;
	protected $settings = array(
		array(
			'name' => 'Advanced Costs',
			'type' => 'title',
			'desc' => 'In addition to costs specify order level costs below.',
		),
		array(
			'name'     => 'Payment processing',
			'desc' => 'Specify the payment processing fee in percents.',
			'id'       => 'advanced_cogs_profit_woo_payment_processing_cost',
			'type'     => 'text',
		),
		array(
			'name'     => 'Fulfilment (percentage)',
			'desc'     => 'Specify the fulfilment costs in percents.',
			'id'       => 'advanced_cogs_profit_woo_fulfilment_cost',
			'type'     => 'text',
		),
		array(
			'type' => 'sectionend',
		),
	);

	public function __construct( $slug) {
		$this->slug = $slug;
		$this->slug_snake_case = str_replace('-', '_', $slug);

		$this->init();
	}

	public function init() {
		// Add menu items
		add_filter( 'woocommerce_get_sections_advanced', [$this, 'add_section_tag'] );
		add_filter( 'woocommerce_get_settings_advanced', [$this, 'add_settings'], 10, 2 );
		add_action( 'woocommerce_settings_advanced', [$this, 'add_section_content'] );
		add_action( 'woocommerce_settings_save_advanced', [$this, 'save_settings'] );

		add_filter( 'woocommerce_product_data_tabs', [$this, 'add_product_data_tab'] );
		add_action( 'woocommerce_product_data_panels', [$this, 'add_product_data_panel'] );

		// Add order meta box
		add_action('add_meta_boxes', [$this, 'add_order_meta_box']);
		
		// Enqueue admin scripts
		add_action( 'admin_enqueue_scripts', [$this, 'enqueue_admin_scripts'] );
	}

	public function enqueue_admin_scripts( $hook ) {
		// Only load on WooCommerce settings page
		if ( 'woocommerce_page_wc-settings' !== $hook ) {
			return;
		}

		// Only load on our specific section
		if ( isset( $_GET['section'] ) && $this->slug === sanitize_text_field( $_GET['section'] ) ) {
			wp_enqueue_script(
				'advanced-cogs-admin-settings',
				plugin_dir_url( __FILE__ ) . 'assets/js/admin-settings.js',
				array( 'jquery' ),
				'1.0.0',
				true
			);
		}
	}



	public function add_section_tag( $sections ) {
		$sections[ $this->slug ] = 'COGS & Profit';
		return $sections;
	}



	public function add_settings( $settings, $current_section ) {
		// we need the fields only on our custom section
		if ( $this->slug !== $current_section ) {
			return $settings;
		}

		return $this->settings;
	}


	public function add_section_content() {
		if ( empty( $_GET[ 'section' ] ) || $this->slug !== sanitize_text_field( $_GET[ 'section' ] ) ) {
			return;
		}
		$cogs_rules_raw = get_option( $this->slug_snake_case . '_product_cogs_rules');

		$rules = json_decode($cogs_rules_raw, true);

		$cogs_rules = $rules ? $rules : [[]];

		?>
		<h2>COGS rules</h2>
		<div id="advanced_page_options-description"><p>Define COGS rules that will apply to products based on condition.</p></div>
		<table id="advanced-cogs-profit-woo-table" class="wc_input_table widefat">
			<thead>
				<tr>
					<th>Product Taxonomy&nbsp;<span class="woocommerce-help-tip" tabindex="0" aria-label=""></span></th>
					<th>Operator&nbsp;<span class="woocommerce-help-tip" tabindex="0" aria-label="Postcode for this rule. Semi-colon (;) separate multiple values. Leave blank to apply to all areas. Wildcards (*) and ranges for numeric postcodes (e.g. 12345...12350) can also be used."></span></th>
					<th>Matching value&nbsp;<span class="woocommerce-help-tip" tabindex="0" aria-label="Cities for this rule. Semi-colon (;) separate multiple values. Leave blank to apply to all cities."></span></th>
					<th>COGS value&nbsp;%&nbsp;<span class="woocommerce-help-tip" tabindex="0" aria-label="Enter a tax rate (percentage) to 4 decimal places."></span></th>
					<th>Enabled&nbsp;<span class="woocommerce-help-tip" tabindex="0" aria-label="Enter a tax rate (percentage) to 4 decimal places."></
						span></th>
					<th></th>
				</tr>
			</thead>
			<tfoot>
				<tr>
					<th colspan="9">
						<a id="advanced-cogs-profit-woo-insert" href="#" class="button plus insert">Insert row</a>
					</th>
				</tr>
			</tfoot>
			<tbody>
				<?php foreach ($cogs_rules as $index => $rule) : ?>
				<?php $sel = fn( $nam, $val) => @$rule[$nam] === $val ? 'selected="selected"' : ''; ?>
				<tr>
					<td style="vertical-align: middle;padding-left: 10px;">
						<select name="product_cogs_rules[<?php echo esc_html($index); ?>][parameter]" class="wc-enhanced-select enhanced" tabindex="-1" >
							<option value="category" <?php echo esc_html($sel('parameter', 'category')); ?>>Category</option>
							<option value="tag" <?php echo esc_html($sel('parameter', 'tag')); ?>>Tag</option>
							<option value="attribute" <?php echo esc_html($sel('parameter', 'attribute')); ?>>Attribute</option>
						</select>
					</td>

					<td style="vertical-align: middle;padding-left: 10px;">
						<select name="product_cogs_rules[<?php echo esc_html($index); ?>][operator]" class="wc-enhanced-select enhanced" tabindex="-1" >
							<option value="equals" <?php echo esc_html($sel('operator', 'equals')); ?>>equals</option>
							<option value="not_equals" <?php echo esc_html($sel('operator', 'not_equals')); ?>>doesn't equal</option>
							<option value="contains" <?php echo esc_html($sel('operator', 'contains')); ?>>contains</option>
							<option value="not_contains" <?php echo esc_html($sel('operator', 'not_contains')); ?>>doesn't contain</option>
						</select>
					</td>

					<td>
						<input type="text" value="<?php echo esc_html(@$rule['matching_value']); ?>" placeholder="matching value" name="product_cogs_rules[<?php echo esc_html($index); ?>][matching_value]">
					</td>

					<td>
						<input type="text" value="<?php echo esc_html(@$rule['cogs_percentage']); ?>" placeholder="%" name="product_cogs_rules[<?php echo esc_html($index); ?>][cogs_percentage]">
					</td>

					<td style="vertical-align: middle;padding-left: 10px;">
						<input type="hidden" name="product_cogs_rules[<?php echo esc_html($index); ?>][enabled]" value="0">
						<input type="checkbox" class="checkbox" name="product_cogs_rules[<?php echo esc_html($index); ?>][enabled]" value="1" <?php echo esc_html(@$rule['enabled']) === '1' ? 'checked="checked"' : ''; ?>>
					</td>
					<td style="vertical-align: middle;padding-left: 10px;">
						<a href="#" class="button minus remove_tax_rates advanced-cogs-profit-woo-remove">Remove</a>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<br class="clear">
		<?php
	}

	public function save_settings() {
		if ( empty( $_REQUEST[ 'section' ] ) || $this->slug !== sanitize_text_field( $_REQUEST[ 'section' ] ) ) {
			return;
		}

		if (!empty($_REQUEST['product_cogs_rules'])) {
			$sanitized_rules = $this->sanitize_cogs_rules( $_REQUEST['product_cogs_rules'] );
			update_option( $this->slug_snake_case . '_product_cogs_rules', wp_json_encode( $sanitized_rules ) );
		}

		WC_Admin_Settings::save_fields( $this->settings );

	}

	private function sanitize_cogs_rules( $rules ) {
		if ( ! is_array( $rules ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( $rules as $key => $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			$sanitized_rule = array();
			
			// Sanitize type field
			if ( isset( $rule['type'] ) ) {
				$sanitized_rule['type'] = sanitize_text_field( $rule['type'] );
			}
			
			// Sanitize value field
			if ( isset( $rule['value'] ) ) {
				$sanitized_rule['value'] = sanitize_text_field( $rule['value'] );
			}
			
			// Sanitize cogs field (numeric value)
			if ( isset( $rule['cogs'] ) ) {
				$sanitized_rule['cogs'] = sanitize_text_field( $rule['cogs'] );
			}
			
			// Sanitize enabled checkbox
			if ( isset( $rule['enabled'] ) ) {
				$sanitized_rule['enabled'] = absint( $rule['enabled'] );
			}

			$sanitized[ absint( $key ) ] = $sanitized_rule;
		}

		return $sanitized;
	}


	public function add_product_data_tab( $product_data_tabs ) {
		$product_data_tabs[$this->slug] = array(
			'label' => __( 'COGS Rules', 'advanced-cogs-profit-for-woocommerce' ),
			'target' => $this->slug_snake_case,
		);
		return $product_data_tabs;
	}

	public function add_product_data_panel() {
		$product = wc_get_product( get_the_ID() );

		?>
		<div id="<?php echo esc_html($this->slug_snake_case); ?>" class="panel woocommerce_options_panel hidden">

			<p class="form-field" style="">
				<label for="_manage_stock">Calculated COGS</label><span class="description"><?php echo esc_html($this->get_cost_from_rules(get_the_ID())); ?></span>
			</p>
		</div>
		<?php
	}

	public function add_order_meta_box() {
		add_meta_box(
			$this->slug_snake_case . '_order_details',
			'COGS & Profit Details',
			[$this, 'render_order_meta_box'],
			'shop_order',
			'side'
		);
	}

	public function render_order_meta_box( $post) {
		$order = wc_get_order($post->ID);
		if (!$order) {
return;
		}

		$profit_data = $this->calculate_order_profit($order);
		?>

			<h4 style="margin-bottom: .1em;">Total revenue<span class="woocommerce-help-tip" aria-label="This is the Customer Lifetime Value, or the total amount you have earned from this customer's orders."></span></h4>
			<span><?php echo esc_html(wc_price($profit_data['revenue'])); ?></span>

			<h4 style="margin-bottom: .1em;">Total costs<span class="woocommerce-help-tip" aria-label="This is the Customer Lifetime Value, or the total amount you have earned from this customer's orders."></span></h4>
			<span><?php echo esc_html(wc_price($profit_data['costs'])); ?></span>

			<h4 style="margin-bottom: .1em;">Net profit<span class="woocommerce-help-tip" aria-label="This is the Customer Lifetime Value, or the total amount you have earned from this customer's orders."></span></h4>
			<span><?php echo esc_html(wc_price($profit_data['profit'])); ?></span>

			<h4 style="margin-bottom: .1em;">Profit margin<span class="woocommerce-help-tip" aria-label="This is the Customer Lifetime Value, or the total amount you have earned from this customer's orders."></span></h4>
			<span><?php echo esc_html(number_format($profit_data['margin'], 2)); ?>%</span>


			<hr />
			<h3>Details</h3>


			<h4 style="margin-bottom: .1em;">Products COGS:</h4>
			<span><?php echo esc_html(wc_price($profit_data['costs_product'])); ?></span>

			<h4 style="margin-bottom: .1em;">Fulfilment costs:</h4>
			<span><?php echo esc_html(wc_price($profit_data['costs_fulfilment'])); ?></span>

			<h4 style="margin-bottom: .1em;">Payment processing costs:</h4>
			<span><?php echo esc_html(wc_price($profit_data['costs_payment_processing'])); ?></span>

			<h4 style="margin-bottom: .1em;">Shipping costs:</h4>
			<span><?php echo esc_html(wc_price($profit_data['costs_shipping'])); ?></span>
		<?php
	}

	private function calculate_order_profit( $order) {
		$revenue = $order->get_total();
		$shipping = $order->get_total_shipping();
		$costs_discounts = $order->get_total_discount();
		$costs_product = 0;

		// Calculate product costs
		foreach ($order->get_items() as $item) {
			$product_id = $item->get_product_id();

			$cost = get_post_meta($product_id, '_product_cost', true);

			if (!$cost) {
				$cost = $this->get_cost_from_rules($product_id);
			}

			$costs_product += floatval($cost) * $item->get_quantity();
		}

		// Add fulfilment costs
		$fulfilment_costs = $this->calculate_fulfilment_costs($order);
		$costs = (float) $shipping + (float) $costs_product + (float) $fulfilment_costs;

		// Add payment processing costs
		$payment_costs = $this->calculate_payment_processing_costs($order);
		$costs += $payment_costs;

		$profit = $revenue - $costs;
		$margin = ( $revenue > 0 ) ? ( $profit / $revenue ) * 100 : 0;

		return [
			'revenue' => $revenue,
			'costs' => $costs,
			'costs_product' => $costs_product,
			'costs_shipping' => $costs_shipping,
			'costs_fulfilment' => $fulfilment_costs,
			'costs_payment_processing' => $payment_costs,
			'costs_discounts' => $costs_discounts,
			'profit' => $profit,
			'margin' => $margin
		];
	}

	private function calculate_profit_for_period( $start_date, $end_date) {
		$args = array(
			'status' => ['processing', 'completed'],
			'date_created' => $start_date . '...' . $end_date,
			'limit' => -1,
		);

		$orders = wc_get_orders($args);


		$total_revenue = 0;
		$total_costs = 0;
		$costs_product = 0;
		$costs_fulfilment = 0;
		$costs_payment_processing = 0;
		$costs_discounts = 0;
		$order_count = count($orders);

		foreach ($orders as $order) {
			$order_data = $this->calculate_order_profit($order);
			$total_revenue += $order_data['revenue'];
			$total_costs += $order_data['costs'];
			$costs_product += $order_data['costs_product'];
			$costs_fulfilment += $order_data['costs_fulfilment'];
			$costs_shipping += $order_data['costs_shipping'];
			$costs_discounts += $order_data['costs_discounts'];
			$costs_payment_processing += $order_data['costs_payment_processing'];
		}

		$total_profit = $total_revenue - $total_costs;
		$avg_profit = $order_count > 0 ? $total_profit / $order_count : 0;

		return [
			'revenue' => $total_revenue,
			'costs_product' => $costs_product,
			'costs_fulfilment' => $costs_fulfilment,
			'costs_shipping' => $costs_shipping,
			'costs_payment_processing' => $costs_payment_processing,
			'costs_discounts' => $costs_discounts,
			'total_costs' => $total_costs,
			'profit' => $total_profit,
			'avg_profit' => $avg_profit
		];
	}

	protected function get_cached_option( $option_name) {
		if (isset($this->cache[$option_name])) {
			return $this->cache[$option_name];
		}
		$option_value = get_option($this->slug_snake_case . '_' . $option_name);
		$this->cache[$option_name] = $option_value;
		return $option_value;
	}

	private function get_cost_from_rules( $product_id) {
		$product_cogs_rules = json_decode($this->get_cached_option('product_cogs_rules'), true);
		$product = wc_get_product($product_id);

		if ($product->get_cogs_total_value() != 0) {
			return $product->get_cogs_total_value();
		}

		$categories = array_map(fn( $t) => $t->name, get_the_terms( $product_id, 'product_cat' ));

		$cost = 0;
		foreach ($product_cogs_rules as $rule) {
			if ('1' !== $rule['enabled']) {
				continue;
			}

			if ('category' === $rule['parameter']) {

				if ('equals' === $rule['operator']) {

					if (in_array($rule['matching_value'], $categories)) {
						$cost = $product->get_price_excluding_tax() * $rule['cogs_percentage'] / 100;
					}
				}
			}
		}

		return $cost;
	}

	private function calculate_fulfilment_costs( $order) {
		$cost = 0;
		$fulfilment_settings = $this->get_cached_option('fulfilment_cost');
		foreach ($order->get_shipping_methods() as $method) {
			$cost += $fulfilment_settings;
		}

		return $cost;
	}

	private function calculate_payment_processing_costs( $order) {
		$payment_settings = $this->get_cached_option('payment_processing_cost');
		if (false === is_int($payment_settings)) {
			$payment_settings = 0;
		}
		$rate = floatval($payment_settings) / 100;
		return $order->get_total() * $rate;
	}
}

new Advanced_Cogs_Profit_Woo('advanced-cogs-profit-for-woocommerce');
