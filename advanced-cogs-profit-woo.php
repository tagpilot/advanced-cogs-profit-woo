<?php
/**
 * Plugin Name: Advanced COGS & Profit for WooCommerce
 * Description: Adds profit calculation capabilities to WooCommerce
 * Version: 1.0.0
 * Author: Tag Pilot
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 */

defined('ABSPATH') || exit;

class Advanced_Cogs_Profit_Woo {

    protected $slug;
    protected $slug_snake_case;
    protected $wp_settings_util;
    protected $cache;

    public function __construct($slug, $wp_settings_util) {
        $this->slug = $slug;
        $this->slug_snake_case = str_replace('-', '_', $slug);
        $this->wp_settings_util = $wp_settings_util;

        $this->init();
    }

    public function init() {
        // Add menu items
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'settings']);

        // Add product meta box
        add_action('add_meta_boxes', [$this, 'add_product_meta_box']);
        add_action('save_post_product', [$this, 'save_product_cost']);

        // Add order meta box
        add_action('add_meta_boxes', [$this, 'add_order_meta_box']);
    }

    public function settings() {

        $this->wp_settings_util->add_settings_section('settings', 'product_cost', 'Product Cost', 'Define product cost');
        $this->wp_settings_util->add_settings_field('product_cost', 'product_cost_metadata', 'Product Cost Metadata', 'input');

        $this->wp_settings_util->add_settings_field(
            'product_cost',
            'product_cost_rules',
            'Product Cost Rules',
            [$this, 'array_field'],
            '',
            [
                'fields_config' => [
                    'parameter' => [
                        'title' => 'Rule parameter',
                        'callback' => 'select_field',
                        'options' => [
                            'shipping_class' => 'Shipping Class'
                        ]
                    ],
                    'operator' => [
                        'title' => 'Operator',
                        'callback' => 'select_field',
                        'options' => [
                            'equals' => 'equals'
                        ]
                    ],
                    'value' => [
                        'title' => 'Rule value',
                        'callback' => 'input_field',
                        'type' => 'text',
                        'placeholder' => '',
                    ],
                    'percentage' => [
                        'title' => 'Percentage Cost',
                        'callback' => 'input_field',
                        'type' => 'number',
                        'placeholder' => '',
                    ],
                    'absolute' => [
                        'title' => 'Absolute Cost',
                        'callback' => 'input_field',
                        'type' => 'number',
                        'placeholder' => '',
                    ],
                ]
            ]
        );

        $this->wp_settings_util->add_settings_section('settings', 'fulfillment_cost', 'Fulfillment Cost', 'Define fulfillment cost');
        $this->wp_settings_util->add_settings_field('fulfillment_cost', 'fulfillment_cost_per_order', 'Fulfillment Cost per order', 'input', '', ['type' => 'number']);

        $this->wp_settings_util->add_settings_section('settings', 'payment_processing_cost', 'Payment Processing Cost', 'Define fulfillment cost');

        $this->wp_settings_util->add_settings_field('payment_processing_cost', 'payment_processing_cost', 'Payment Processing Cost', 'input', '', ['type' => 'number']);
    }

    public function add_admin_menu() {
        $this->wp_settings_util->add_tab('reports', 'Reports', true, false, [$this, 'render_reports_page']);
        $this->wp_settings_util->add_tab('settings', 'Settings');
        $this->wp_settings_util->add_submenu_page( 'woocommerce', 'Simple Profit', 'Simple Profit');
    }

    public function array_field( $args ) {
        $name = $args['label_for'];
        $fields_config = $args['fields_config'];

        $value = $args['value'] ?? get_option( $args['label_for'] );

        if (false === is_array($value)) {
            $value = [];
        }

        if (count($value) === 0) {
            $value[] = array_fill_keys(array_keys($fields_config), '');
        }

        echo '<table class="widefat">';
        echo '<thead>';
        echo '<tr>';
        foreach ($fields_config as $index => $field) {
            echo '<th>';
            echo $field['title'];
            echo '</th>';
        }
        echo '<th>';
        ?>
        <a href="#" onclick="arguments[0].preventDefault(); var table = this.parentNode.parentNode.parentNode.parentNode; jQuery('tbody tr:last-child', table).clone().appendTo(jQuery('tbody', table)); jQuery('tbody tr:last-child :input', table).not(':button, :submit, :reset, :hidden').val('').prop('checked', false).prop('selected', false).each(function(i, inp) { var name = inp.name; var match = name.match(/\[(\d*)\]/i); jQuery(inp).attr('name', name.replace(match[0], '[' + (parseInt(match[1]) + 1) + ']')) });">Add Row</a>
        <?php
        echo '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        foreach ($value as $index => $rule) {
            echo '<tr>';

            foreach ($fields_config as $name => $field_config) {
                $field_name = sprintf('%s[%d][%s]', $args['label_for'], $index, $name);
                $value = $rule[$name];
                // $field_config = $fields_config[$name] ?? null;

                if (null === $field_config) {
                    continue;
                }
                echo '<td style="vertical-align: top">';
                $this->wp_settings_util->{$field_config['callback']}(array_merge($field_config, [
                    'label_for' => $field_name,
                    'value' => $value,
                ]));
                echo '</td>';
            }
            echo '<td><a href="#" onclick="arguments[0].preventDefault(); this.parentNode.parentNode.remove();">Remove</a></td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
    }

    public function render_reports_page() {
        $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : date('Y-m-d', strtotime('-30 days'));
        $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : date('Y-m-d');

        $profit_data = $this->calculate_profit_for_period($start_date, $end_date);
        ?>
        <div class="profit-reports">
            <form method="get">
                <input type="hidden" name="page" value="simple-profit-woocommerce">
                <input type="date" name="start_date" value="<?php echo esc_attr($start_date); ?>">
                <input type="date" name="end_date" value="<?php echo esc_attr($end_date); ?>">
                <input type="submit" class="button" value="Filter">
            </form>

            <div class="profit-summary">
                <h3>Profit Summary</h3>
                <p>Total Revenue: <?php echo wc_price($profit_data['revenue']); ?></p>
                <p>Total Costs: <?php echo wc_price($profit_data['total_costs']); ?></p>
                <p>Costs of products: <?php echo wc_price($profit_data['costs_product']); ?></p>
                <p>Costs of fullfilment: <?php echo wc_price($profit_data['costs_fullfilment']); ?></p>
                <p>Costs of discounts: <?php echo wc_price($profit_data['costs_discounts']); ?></p>
                <p>Costs of shipping: <?php echo wc_price($profit_data['costs_shipping']); ?></p>
                <p>Costs of payment processing: <?php echo wc_price($profit_data['costs_payment_processing']); ?></p>
                <p>Total Profit: <?php echo wc_price($profit_data['profit']); ?></p>
                <p>Average Profit per Order: <?php echo wc_price($profit_data['avg_profit']); ?></p>
            </div>
        </div>
        <?php
    }

    public function add_product_meta_box() {
        add_meta_box(
            'simple_profit_product_cost',
            'Product Cost',
            [$this, 'render_product_meta_box'],
            'product',
            'normal',
            'high'
        );
    }

    public function render_product_meta_box($post) {
        $cost = get_post_meta($post->ID, '_product_cost', true);
        ?>
        <div class="product-cost-fields">
            <p>
                <label>Product Cost:</label>
                <input type="number" step="0.01" name="product_cost" value="<?php echo esc_attr($cost); ?>">
            </p>
        </div>
        <?php
    }

    public function save_product_cost($post_id) {
        if (isset($_POST['product_cost'])) {
            update_post_meta($post_id, '_product_cost', sanitize_text_field($_POST['product_cost']));
        }
    }

    public function add_order_meta_box() {
        add_meta_box(
            'simple_profit_order_details',
            'Profit Details',
            [$this, 'render_order_meta_box'],
            'shop_order',
            'normal',
            'high'
        );
    }

    public function render_order_meta_box($post) {
        $order = wc_get_order($post->ID);
        if (!$order) return;

        $profit_data = $this->calculate_order_profit($order);
        ?>
        <div class="order-profit-details">
            <p>
                <strong>Total Revenue:</strong> <?php echo wc_price($profit_data['revenue']); ?>
            </p>
            <p>
                <strong>Total Costs:</strong> <?php echo wc_price($profit_data['costs']); ?>
            </p>
            <p>
                <strong>Net Profit:</strong> <?php echo wc_price($profit_data['profit']); ?>
            </p>
            <p>
                <strong>Profit Margin:</strong> <?php echo number_format($profit_data['margin'], 2); ?>%
            </p>
        </div>
        <?php
    }

    private function calculate_order_profit($order) {
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

        // Add fulfillment costs
        $fulfillment_costs = $this->calculate_fulfillment_costs($order);
        $costs = (float) $shipping + (float) $costs_product + (float) $fulfillment_costs;

        // Add payment processing costs
        $payment_costs = $this->calculate_payment_processing_costs($order);
        $costs += $payment_costs;

        $profit = $revenue - $costs;
        $margin = ($revenue > 0) ? ($profit / $revenue) * 100 : 0;

        return [
            'revenue' => $revenue,
            'costs' => $costs,
            'costs_product' => $costs_product,
            'costs_fullfilment' => $fulfillment_costs,
            'costs_payment_processing' => $payment_costs,
            'costs_shipping' => $shipping,
            'costs_discounts' => $costs_discounts,
            'profit' => $profit,
            'margin' => $margin
        ];
    }

    private function calculate_profit_for_period($start_date, $end_date) {
        $args = array(
            'status' => ['processing', 'completed'],
            'date_created' => $start_date . '...' . $end_date,
            'limit' => -1,
        );

        $orders = wc_get_orders($args);


        $total_revenue = 0;
        $total_costs = 0;
        $costs_product = 0;
        $costs_fullfilment = 0;
        $costs_payment_processing = 0;
        $costs_discounts = 0;
        $order_count = count($orders);

        foreach ($orders as $order) {
            $order_data = $this->calculate_order_profit($order);
            $total_revenue += $order_data['revenue'];
            $total_costs += $order_data['costs'];
            $costs_product += $order_data['costs_product'];
            $costs_fullfilment += $order_data['costs_fullfilment'];
            $costs_shipping += $order_data['costs_shipping'];
            $costs_discounts += $order_data['costs_discounts'];
            $costs_payment_processing += $order_data['costs_payment_processing'];
        }

        $total_profit = $total_revenue - $total_costs;
        $avg_profit = $order_count > 0 ? $total_profit / $order_count : 0;

        return [
            'revenue' => $total_revenue,
            'costs_product' => $costs_product,
            'costs_fullfilment' => $costs_fullfilment,
            'costs_shipping' => $costs_shipping,
            'costs_payment_processing' => $costs_payment_processing,
            'costs_discounts' => $costs_discounts,
            'total_costs' => $total_costs,
            'profit' => $total_profit,
            'avg_profit' => $avg_profit
        ];
    }

    protected function get_cached_option($option_name) {
        if (isset($this->cache[$option_name])) {
            return $this->cache[$option_name];
        }
        $option_value = $this->wp_settings_util->get_option($option_name);
        $this->cache[$option_name] = $option_value;
        return $option_value;
    }

    private function get_cost_from_rules($product_id) {
        $product_cost_rules = $this->get_cached_option('product_cost_rules');
        $product = wc_get_product($product_id);
        $shipping_class = $product->get_shipping_class();
        $cost = 0;
        foreach ($product_cost_rules as $rule) {
            if ($rule['parameter'] === 'shipping_class') {
                if ($shipping_class === $rule['value']) {
                    $cost += $rule['absolute'];
                }
            }
        }

        return $cost;
    }

    private function calculate_fulfillment_costs($order) {
        $cost = 0;
        $fulfillment_settings = $this->get_cached_option('fulfillment_cost_per_order');
        foreach ($order->get_shipping_methods() as $method) {
            $cost += $fulfillment_settings;
        }

        return $cost;
    }

    private function calculate_payment_processing_costs($order) {
        $payment_settings = $this->get_cached_option('payment_processing_cost');
        $rate = floatval($payment_settings ?? 0) / 100;
        return $order->get_total() * $rate;
    }
}

class WP_Settings_Util {

    /** @var string */
    protected $slug_snake_case;
    /** @var string */
    protected $slug;

    /** @var array */
    protected $tabs = [];
    /** @var array */
    protected $sections = [];

    const WP_KSES_ALLOWED_HTML = [
        'a' => [
            'id' => [],
            'href' => [],
            'target' => [],
            'class' => [],
            'style' => [],
            'data-target' => [],
        ],
        'input' => [
            'type' => [],
            'value' => [],
            'style' => [],
        ],
        'br' => [],
        'div' => [
            'id' => [],
            'style' => [],
            'class' => [],
        ],
        'p' => [
            'id' => [],
            'style' => [],
            'class' => [],
        ],
        'span' => [
            'id' => [],
            'style' => [],
            'class' => [],
            'data-section' => []
        ],
        'b' => [],
        'h3' => [
            'id' => [],
            'style' => [],
            'class' => [],
        ],
        'pre' => [
            'style' => []
        ],
        'strong' => [],
        'img' => [],
        'script' => [],
        'noscript' => [],
        'iframe' => [
            'src' => [],
            'height' => [],
            'width' => [],
            'style' => [],
        ],
    ];

    const WP_KSES_ALLOWED_PROTOCOLS = [
        'http', 'https'
    ];

    public function __construct( string $slug ) {
        $this->slug = $slug;
        $this->slug_snake_case = str_replace('-', '_', $slug);
    }

    public function get_option( $option_name ) {
        return get_option($this->slug_snake_case . '_' . $option_name);
    }

    public function delete_option( $option_name) {
        return delete_option($this->slug_snake_case . '_' . $option_name);
    }

    public function update_option( $option_name, $option_value) {
        return update_option($this->slug_snake_case . '_' . $option_name, $option_value);
    }

    public function register_setting( $setting_name ) {
        return register_setting( $this->slug_snake_case, $this->slug_snake_case . '_' . $setting_name );
    }

    public function add_tab( $tabName, $tabTitle, $showSaveButton = true, $inactive = false, $callback = null) {
        $this->tabs[$tabName] = [
            'name' => $tabName,
            'title' => $tabTitle,
            'show_save_button' => $showSaveButton,
            'inactive' => $inactive,
            'callback' => $callback
        ];
    }

    public function add_settings_section( $tab, $sectionName, $sectionTitle, $description, $extra = null): void {
        $this->sections[$sectionName] = [
            'name' => $sectionName,
            'tab' => $tab
        ];
        $args = [
            'before_section' => '',
            'after_section' => '',
        ];

        $grid = isset($extra['grid']) ? $extra['grid'] : null;
        $badge = isset($extra['badge']) ? $extra['badge'] : null;

        if ( 'start' === $grid || 'single' === $grid ) {
            $args['before_section'] = '<div class="metabox-holder"><div class="postbox-container" style="float: none; display: flex; flex-wrap:wrap;">';
        }
        if ( null !== $grid ) {
            $args['before_section'] .= '<div style="margin-left: 4%; width: 45%" class="postbox"><div class="inside">';
            $args['after_section'] = '</div></div>';
        }

        if ( 'end' === $grid || 'single' === $grid ) {
            $args['after_section'] .= '</div></div><br />';
        }

        $title = __( $sectionTitle, $this->slug );
        if ($badge) {
            $title .= ' <code>' . strtoupper($badge) . '</code>';
        }

        add_settings_section(
            $this->slug_snake_case . '_' . $sectionName,
            $title,
            static function( $args) use ( $description, $grid ) {
                ?>
              <p id="<?php echo esc_attr( $args['id'] ); ?>"><?php echo wp_kses($description, self::WP_KSES_ALLOWED_HTML, self::WP_KSES_ALLOWED_PROTOCOLS); ?></p>
              <?php
            },
            $this->slug_snake_case . '_' . $tab,
            $args
        );
    }

    public function add_settings_field( $fieldSection, $fieldName, $fieldTitle, $fieldCallback, $fieldDescription = '', $extraAttrs = []) {
        $attrs = array_merge([
            'label_for'   => $this->slug_snake_case . '_' . $fieldName,
            'description' => $fieldDescription,
        ], $extraAttrs);
        $section = $this->sections[$fieldSection];
        register_setting( $this->slug_snake_case . '_' . $section['tab'], $this->slug_snake_case . '_' . $fieldName );

        $callback = is_callable($fieldCallback) ? $fieldCallback : [$this, $fieldCallback . '_field'];

        add_settings_field(
            $this->slug_snake_case . '_' . $fieldName, // As of WP 4.6 this value is used only internally.
            // Use $args' label_for to populate the id inside the callback.
            __( $fieldTitle, $this->slug ),
            $callback,
            $this->slug_snake_case . '_' . $section['tab'],
            $this->slug_snake_case . '_' . $fieldSection,
            $attrs
        );
    }

    public function add_submenu_page( $parent_slug, $page_title, $menu_title, $capabilities = 'manage_options' ) {
        $slug_snake_case = $this->slug_snake_case;
        $slug = $this->slug;
        $activeTab = isset( $_GET[ 'tab' ] ) ? sanitize_key($_GET[ 'tab' ]) : array_keys($this->tabs)[0];
        add_submenu_page(
            $parent_slug,
            $page_title,
            $menu_title,
            $capabilities,
            $this->slug,
            function() use ( $capabilities, $slug_snake_case, $slug, $activeTab ) {
                // check user capabilities
                if ( ! current_user_can( $capabilities ) ) {
                    return;
                }
                // show error/update messages
                settings_errors( $slug_snake_case . '_messages' );
                ?>
              <div class="wrap">
                <div id="icon-themes" class="icon32"></div>
                <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

                <h2 class="nav-tab-wrapper">
                    <?php foreach ($this->tabs as $tab) : ?>
                    <?php
                        $link = sprintf('?page=%s&tab=%s', $this->slug, $tab['name']);
                        if (true === @$tab['inactive']) {
                            $link = '#';
                        }
                        ?>
                    <a
                        href="<?php echo esc_url($link); ?>"
                        class="nav-tab
                        <?php if ($activeTab === $tab['name']) : ?>
                            nav-tab-active
                        <?php endif; ?>
                    "><?php echo wp_kses($tab['title'], self::WP_KSES_ALLOWED_HTML, self::WP_KSES_ALLOWED_PROTOCOLS); ?></a>
                    <?php endforeach; ?>
                </h2>
                  <?php
                    if (is_callable($this->tabs[$activeTab]['callback'])) {
                        call_user_func($this->tabs[$activeTab]['callback']);
                    } else {
                        ?>
                        <form action="options.php" method="post">
                        <?php
                        // output security fields for the registered setting "wporg_options"
                        settings_fields( $slug_snake_case . '_' . $activeTab );
                        // output setting sections and their fields
                        // (sections are registered for "wporg", each field is registered to a specific section)
                        do_settings_sections( $slug_snake_case . '_' . $activeTab );
                        // output save settings button
                        if (false !== $this->tabs[$activeTab]['show_save_button']) {
                            submit_button( __( 'Save Settings', $slug ) );
                        }
                        ?>
                        </form>
                        <?php
                    }
                    ?>
              </div>
                <?php
            }
        );
    }

    public function checkbox_field( $args ) {
        // Get the value of the setting we've registered with register_setting()
        $value = $args['value'] ?? get_option( $args['label_for'] );
        ?>
      <input
        type="checkbox"
        id="<?php echo esc_attr( $args['label_for'] ); ?>"
        name="<?php echo esc_attr( $args['label_for'] ); ?>"
        <?php if (true === @$args['disabled']) : ?>
        disabled="disabled"
        <?php endif; ?>
        <?php if (@$args['title']) : ?>
        title="<?php echo esc_attr($args['title']); ?>"
        <?php endif; ?>
        value="1"
        <?php checked( $value, 1 ); ?> />
      <p class="description">
        <?php echo wp_kses($args['description'], self::WP_KSES_ALLOWED_HTML, self::WP_KSES_ALLOWED_PROTOCOLS); ?>
      </p>
        <?php
    }

    public function select_field( $args ) {
        // Get the value of the setting we've registered with register_setting()
        $selectedValue = $args['value'] ?? get_option( $args['label_for'] );
        ?>
      <select
        type="checkbox"
        id="<?php echo esc_attr( $args['label_for'] ); ?>"
        name="<?php echo esc_attr( $args['label_for'] ); ?>"
        <?php if (true === @$args['disabled']) : ?>
        disabled="disabled"
        <?php endif; ?>
        >
        <?php foreach ($args['options'] as $value => $label) : ?>
            <option value="<?php echo esc_attr($value); ?>"
                <?php if ($selectedValue == $value) : ?>
                selected
                <?php endif; ?>
                ><?php echo esc_html($label); ?></option>
        <?php endforeach ?>
        </select>
      <p class="description">
        <?php echo wp_kses($args['description'], self::WP_KSES_ALLOWED_HTML, self::WP_KSES_ALLOWED_PROTOCOLS); ?>
      </p>
        <?php
    }


    public function textarea_field( $args ) {
        // Get the value of the setting we've registered with register_setting()
        $value = $args['value'] ?? get_option( $args['label_for'] );
        ?>
      <textarea
        id="<?php echo esc_attr( $args['label_for'] ); ?>"
        class="large-text code"
        rows="<?php echo esc_html( $args['rows'] ); ?>"
        name="<?php echo esc_attr( $args['label_for'] ); ?>"><?php echo wp_kses($value, self::WP_KSES_ALLOWED_HTML, self::WP_KSES_ALLOWED_PROTOCOLS); ?></textarea>
      <p class="description">
        <?php echo esc_html( $args['description'] ); ?>
      </p>
        <?php
    }

    public function input_field( $args ) {
        // Get the value of the setting we've registered with register_setting()
        $value = $args['value'] ?? get_option( $args['label_for'] );
        ?>
      <input
        id="<?php echo esc_attr( $args['label_for'] ); ?>"
        class="large-text code"
        type="<?php echo esc_html( $args['type'] ); ?>"
        <?php if (true === @$args['disabled']) : ?>
        disabled="disabled"
        <?php endif; ?>
        value="<?php echo esc_html($value); ?>"
        placeholder="<?php echo esc_html( @$args['placeholder'] ); ?>"
        name="<?php echo esc_attr( $args['label_for'] ); ?>" />
      <p class="description">
        <?php echo esc_html( $args['description'] ); ?>
      </p>
        <?php
    }
}

$wp_settings_util = new WP_Settings_Util("simple-profit-woocommerce");

new Advanced_Cogs_Profit_Woo("simple-profit-woocommerce", $wp_settings_util);

add_filter( 'woocommerce_get_sections_advanced', 'rudr_add_setting_section' );

function rudr_add_setting_section( $sections ) {

    $sections[ 'misha' ] = 'COGS & Profit';
    return $sections;

}

// add_action( 'woocommerce_settings_{tab}', ...
add_action( 'woocommerce_settings_advanced', 'rudr_section_content' );

function rudr_section_content() {
    if( empty( $_GET[ 'section' ] ) || 'misha' !== $_GET[ 'section' ] ) {
        return;
    }
    echo 'what is up?';
}


