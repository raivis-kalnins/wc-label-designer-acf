<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCLD_Designer {
    public function __construct() {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'woocommerce_before_add_to_cart_button', [ $this, 'output_designer' ] );
    }

    public function enqueue_assets() {
        if ( ! is_product() ) return;
        global $product;
        if ( ! $product instanceof WC_Product ) return;
        if ( ! WCLD_Helpers::is_enabled_for_product( $product->get_id() ) ) return;

        wp_enqueue_script( 'jquery-ui-draggable' );
        wp_enqueue_script( 'jquery-ui-resizable' );
        wp_enqueue_script( 'html2canvas', WCLD_URL . 'includes/frontend/assets/html2canvas.min.js', [], null, true );
        wp_enqueue_script( 'qrcode', WCLD_URL . 'includes/frontend/assets/qrcode.min.js', [], null, true );
        wp_enqueue_script( 'barcode', WCLD_URL . 'includes/frontend/assets/JsBarcode.all.min.js', [], null, true );
        wp_enqueue_script( 'wcld-designer', WCLD_URL . 'includes/frontend/assets/designer.js', [ 'jquery' ], null, true );
        wp_enqueue_style( 'wcld-designer-css', WCLD_URL . 'includes/frontend/assets/designer.css' );

        $width  = get_field( 'wcld_width_override', $product->get_id() ) ?: get_field( 'wcld_width', 'option' ) ?: 150;
        $height = get_field( 'wcld_height_override', $product->get_id() ) ?: get_field( 'wcld_height', 'option' ) ?: 100;
        $bleed  = get_field( 'wcld_bleed_override', $product->get_id() ) ?: get_field( 'wcld_bleed', 'option' ) ?: 5;
        $bg     = get_field( 'wcld_bg_override', $product->get_id() ) ?: get_field( 'wcld_bg', 'option' );

        wp_localize_script( 'wcld-designer', 'wcldData', [
            'ajax'         => admin_url( 'admin-ajax.php' ),
            'width'        => (float) $width,
            'height'       => (float) $height,
            'bleed'        => (float) $bleed,
            'bg'           => $bg,
            'sku'          => $product->get_sku(),
            'product_name' => $product->get_name(),
            'nonce'        => wp_create_nonce( 'wcld_nonce' ),
        ]);
    }

    public function output_designer() {
        global $product;
        if ( ! $product instanceof WC_Product ) return;
        if ( ! WCLD_Helpers::is_enabled_for_product( $product->get_id() ) ) return;

        include WCLD_PATH . 'includes/frontend/designer-template.php';
    }
}