<?php
if ( ! defined('ABSPATH') ) exit;

class WCLD_Order_Preview {
    public function __construct() {
        add_action( 'woocommerce_after_order_itemmeta', [$this,'display_order_design'], 10, 3 );
    }

    public function display_order_design( $item_id, $item, $product ) {
        $design_json = wc_get_order_item_meta( $item_id, 'Design JSON', true );
        $design_pdf  = wc_get_order_item_meta( $item_id, 'Design PDF', true );
        if ( ! $design_json && ! $design_pdf ) return;

        echo '<div class="wcld-order-meta">';
        echo '<h4>' . esc_html__( 'Label Design', 'wcld' ) . '</h4>';

        if ( $design_pdf ) {
            echo '<p><a href="' . esc_url( $design_pdf ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Download PDF', 'wcld' ) . '</a></p>';
        }

        if ( $design_json ) {
            echo '<details><summary>' . esc_html__( 'View JSON', 'wcld' ) . '</summary>';
            echo '<pre style="max-height:200px;overflow:auto;">' . esc_html( $design_json ) . '</pre>';
            echo '</details>';
        }

        echo '</div>';
    }
}