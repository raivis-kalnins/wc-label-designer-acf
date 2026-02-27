<?php
/*
Plugin Name: WC Label Designer PRO
Description: Enterprise WooCommerce Label Designer with per-product, category, bulk selection, CMYK PDF, safe zones, barcode, QR code & ACF.
Version: 1.0
Author: WP Experts
Network: true
*/

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'WCLD_URL', plugin_dir_url( __FILE__ ) );
define( 'WCLD_PATH', plugin_dir_path( __FILE__ ) );

// -------------------------
// 1️⃣ Includes
// -------------------------
require_once WCLD_PATH . 'includes/class-helpers.php';

// Admin
require_once WCLD_PATH . 'includes/admin/class-admin-settings.php';
require_once WCLD_PATH . 'includes/admin/class-product-settings.php';
require_once WCLD_PATH . 'includes/admin/class-order-preview.php';

// Frontend
require_once WCLD_PATH . 'includes/frontend/class-designer.php';

// AJAX
require_once WCLD_PATH . 'includes/ajax/class-ajax-handlers.php';

// -------------------------
// 2️⃣ Initialize Plugin
// -------------------------
add_action( 'plugins_loaded', function() {
    new WCLD_Helpers();

    if ( is_admin() ) {
        new WCLD_Admin_Settings();
        new WCLD_Product_Settings();
        new WCLD_Order_Preview();
    } else {
        new WCLD_Designer();
    }

    new WCLD_Ajax_Handlers();
});

// -------------------------
// 3️⃣ Create Demo Product
// -------------------------
function wcld_create_demo_product() {
    if ( ! is_admin() ) return;

    $demo = get_page_by_title( 'WC Label Designer Demo', OBJECT, 'product' );
    if ( $demo ) return;

    $product_id = wp_insert_post([
        'post_title'   => 'WC Label Designer Demo',
        'post_content' => 'This is a demo product with label designer enabled.',
        'post_status'  => 'publish',
        'post_type'    => 'product',
    ]);
    if ( ! $product_id ) return;

    wp_set_object_terms( $product_id, 'simple', 'product_type' );

    // WooCommerce required meta
    update_post_meta( $product_id, '_regular_price', 19.99 );
    update_post_meta( $product_id, '_price', 19.99 );
    update_post_meta( $product_id, '_stock_status', 'instock' );
    update_post_meta( $product_id, '_manage_stock', 'no' );
    update_post_meta( $product_id, '_virtual', 'yes' );
    update_post_meta( $product_id, '_downloadable', 'no' );

    // ACF fields
    if ( function_exists('update_field') ) {
        update_field( 'wcld_enable', 1, $product_id );
        update_field( 'wcld_width_override', 200, $product_id );
        update_field( 'wcld_height_override', 150, $product_id );
        update_field( 'wcld_bleed_override', 5, $product_id );
    }
}
add_action( 'init', 'wcld_create_demo_product', 20 );

// -------------------------
// 4️⃣ Add / Update Cart Item
// -------------------------
function wcld_add_or_update_cart_item( $product_id, $json_data = '', $pdf_url = '', $quantity = 1 ) {
    if ( ! WC()->cart ) return;

    $found = false;
    foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
        if ( $cart_item['product_id'] == $product_id ) {
            WC()->cart->cart_contents[$cart_item_key]['wcld_design_json'] = $json_data;
            WC()->cart->cart_contents[$cart_item_key]['wcld_design_pdf']  = $pdf_url;
            $found = true;
            break;
        }
    }

    if ( ! $found ) {
        WC()->cart->add_to_cart( $product_id, $quantity, 0, [], [
            'wcld_design_json' => $json_data,
            'wcld_design_pdf'  => $pdf_url
        ]);
    }
}

// -------------------------
// 5️⃣ Copy Design Data to Order Items
// -------------------------
add_action('woocommerce_checkout_create_order_line_item', 'wcld_add_design_to_order', 10, 4);
function wcld_add_design_to_order( $item, $cart_item_key, $values, $order ) {
    if ( ! empty($values['wcld_design_json']) ) {
        $item->add_meta_data('Design JSON', $values['wcld_design_json'], true);
    }
    if ( ! empty($values['wcld_design_pdf']) ) {
        $item->add_meta_data('Design PDF', $values['wcld_design_pdf'], true);
    }
}

// -------------------------
// 6️⃣ Show Design in Cart / Mini-Cart
// -------------------------
add_filter( 'woocommerce_get_item_data', 'wcld_show_design_in_cart', 10, 2 );
function wcld_show_design_in_cart( $item_data, $cart_item ) {
    if ( ! empty($cart_item['wcld_design_pdf']) ) {
        $item_data[] = [
            'key'   => __('Label Design', 'wcld'),
            'value' => '<a href="'.esc_url($cart_item['wcld_design_pdf']).'" target="_blank">'.__('View PDF', 'wcld').'</a>'
        ];
    }
    return $item_data;
}

// -------------------------
// 7️⃣ AJAX Endpoint: Save Design & Add to Cart
// -------------------------
add_action( 'wp_ajax_wcld_save_design', 'wcld_ajax_save_design' );
add_action( 'wp_ajax_nopriv_wcld_save_design', 'wcld_ajax_save_design' );
function wcld_ajax_save_design() {
    if ( ! isset($_POST['product_id']) ) wp_send_json_error('No product ID');

    $product_id = intval($_POST['product_id']);
    $json_data  = sanitize_text_field($_POST['design_json'] ?? '');
    $pdf_url    = esc_url_raw($_POST['design_pdf'] ?? '');

    // Add/update cart
    wcld_add_or_update_cart_item( $product_id, $json_data, $pdf_url );

    wp_send_json_success('Design saved to cart');
}