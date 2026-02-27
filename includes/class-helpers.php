<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCLD_Helpers {
    public function __construct() {}

    public static function is_enabled_for_product( $product_id ) {
        if ( ! $product_id ) return false;

        $product_enabled = get_field( 'wcld_enable', $product_id );
        if ( $product_enabled ) return true;

        $bulk_products = get_field( 'wcld_bulk_products', 'option' ) ?: [];
        if ( in_array( $product_id, $bulk_products ) ) return true;

        $enabled_categories = get_field( 'wcld_categories', 'option' ) ?: [];
        if ( $enabled_categories ) {
            $product_cats = wp_get_post_terms( $product_id, 'product_cat', ['fields'=>'ids'] );
            if ( array_intersect( $product_cats, $enabled_categories ) ) return true;
        }

        return false;
    }
}