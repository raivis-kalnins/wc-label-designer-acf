<?php
class WCLD_Frontend_Designer {
    
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('woocommerce_before_single_product', array($this, 'output_designer'));
    }
    
    public function enqueue_assets() {
        if (!is_product()) return;
        
        global $product;
        if (!$product instanceof WC_Product) return;
        
        if (!WCLD_Helpers::is_enabled_for_product($product->get_id())) return;
        
        // Enqueue jQuery UI
        wp_enqueue_script('jquery-ui-draggable');
        wp_enqueue_script('jquery-ui-resizable');
        
        // Enqueue libraries with proper dependencies
        wp_enqueue_script(
            'html2canvas', 
            WCLD_URL . 'includes/frontend/assets/html2canvas.min.js', 
            array(), 
            '1.4.1', 
            true 
        );
        
        wp_enqueue_script(
            'qrcode', 
            WCLD_URL . 'includes/frontend/assets/qrcode.min.js', 
            array(), 
            null, 
            true 
        );
        
        wp_enqueue_script(
            'barcode', 
            WCLD_URL . 'includes/frontend/assets/JsBarcode.all.min.js', 
            array(), 
            null, 
            true 
        );
        
        // Main designer script
        wp_enqueue_script(
            'wcld-designer', 
            WCLD_URL . 'includes/frontend/assets/designer.js', 
            array('jquery', 'html2canvas', 'qrcode', 'barcode'), 
            WCLD_VERSION, 
            true 
        );
        
        wp_enqueue_style(
            'wcld-designer-css', 
            WCLD_URL . 'includes/frontend/assets/designer.css',
            array(),
            WCLD_VERSION
        );
        
        // Get dimensions from ACF
        $width = get_field('wcld_width_override', $product->get_id()) 
            ?: get_field('wcld_width', 'option') 
            ?: 150;
            
        $height = get_field('wcld_height_override', $product->get_id()) 
            ?: get_field('wcld_height', 'option') 
            ?: 100;
            
        $bleed = get_field('wcld_bleed_override', $product->get_id()) 
            ?: get_field('wcld_bleed', 'option') 
            ?: 5;
            
        $bg = get_field('wcld_bg_override', $product->get_id()) 
            ?: get_field('wcld_bg', 'option') 
            ?: '';
        
        // Localize script with data
        wp_localize_script('wcld-designer', 'wcldData', array(
            'ajax_url'      => admin_url('admin-ajax.php'),
            'ajax'          => admin_url('admin-ajax.php'), // Legacy support
            'width'         => (float) $width,
            'height'        => (float) $height,
            'bleed'         => (float) $bleed,
            'bg'            => $bg,
            'sku'           => $product->get_sku(),
            'product_name'  => $product->get_name(),
            'product_id'    => $product->get_id(),
            'nonce'         => wp_create_nonce('wcld_nonce'),
            'strings'       => array(
                'save_error'    => __('Failed to save design', 'wcld'),
                'generating'    => __('Generating...', 'wcld'),
                'add_to_cart'   => __('Add to Cart', 'wcld')
            )
        ));
    }
    
    public function output_designer() {
        global $product;
        
        if (!$product instanceof WC_Product) return;
        if (!WCLD_Helpers::is_enabled_for_product($product->get_id())) return;
        
        include WCLD_PATH . 'includes/frontend/designer-template.php';
    }
}