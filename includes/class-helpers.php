<?php
if (!defined('ABSPATH')) exit;

class WCLD_Helpers {
    
    /**
     * Check if designer is enabled for product
     */
    public static function is_enabled_for_product($product_id) {
        // Check if ACF function exists
        if (!function_exists('get_field')) {
            return false;
        }
        
        // Check product-level setting
        $enabled = get_field('wcld_enable', $product_id);
        if ($enabled === true || $enabled === '1' || $enabled === 1) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get design preview HTML
     */
    public static function get_design_preview($design_json, $image_url = '') {
        $html = '<div class="wcld-design-preview">';
        
        if ($image_url) {
            $html .= '<img src="' . esc_url($image_url) . '" alt="Label Design" style="max-width:150px;max-height:150px;">';
        }
        
        $html .= '<br><a href="#" class="button wcld-view-design-btn" data-design=\'' . esc_attr($design_json) . '\'>View Design Details</a>';
        $html .= '</div>';
        
        return $html;
    }
}