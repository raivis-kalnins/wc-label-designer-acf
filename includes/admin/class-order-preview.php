<?php
class WCLD_Order_Preview {
    
    public function __construct() {
        // Display in admin order items
        add_action('woocommerce_after_order_itemmeta', array($this, 'display_design_in_order'), 10, 3);
        
        // Add modal for viewing design
        add_action('admin_footer', array($this, 'add_modal_template'));
        
        // AJAX for viewing design details
        add_action('wp_ajax_wcld_get_design_details', array($this, 'get_design_details'));
    }
    
    /**
     * Display design in order edit screen
     */
    public function display_design_in_order($item_id, $item, $product) {
        $design = $item->get_meta('_label_design');
        $image = $item->get_meta('_label_design_image');
        
        if (!$design && !$image) return;
        
        echo '<div class="wcld-order-design" style="margin-top:10px;padding:10px;background:#f9f9f9;border:1px solid #ddd;">';
        echo '<strong>Label Design:</strong><br>';
        
        if ($image) {
            echo '<img src="' . esc_url($image) . '" style="max-width:200px;max-height:200px;border:1px solid #ccc;margin:5px 0;">';
        }
        
        echo '<br><button type="button" class="button wcld-view-order-design" data-design=\'' . esc_attr($design) . '\' data-image=\'' . esc_attr($image) . '\'>View Full Design</button>';
        echo '</div>';
    }
    
    /**
     * Add modal template to admin footer
     */
    public function add_modal_template() {
        global $pagenow;
        if ($pagenow !== 'post.php' || get_post_type() !== 'shop_order') return;
        ?>
        <div id="wcld-modal" style="display:none;position:fixed;z-index:100000;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);">
            <div style="position:relative;width:80%;max-width:800px;margin:50px auto;background:#fff;padding:20px;border-radius:5px;">
                <span class="wcld-close" style="position:absolute;top:10px;right:20px;font-size:24px;cursor:pointer;">&times;</span>
                <h3>Label Design Preview</h3>
                <div id="wcld-modal-content"></div>
            </div>
        </div>
        <script>
        jQuery(function($) {
            $(document).on('click', '.wcld-view-order-design', function(e) {
                e.preventDefault();
                var design = $(this).data('design');
                var image = $(this).data('image');
                var content = '';
                
                if (image) {
                    content += '<img src="' + image + '" style="max-width:100%;">';
                }
                
                if (design) {
                    try {
                        var data = JSON.parse(design);
                        content += '<pre style="background:#f5f5f5;padding:10px;margin-top:10px;overflow:auto;">' + JSON.stringify(data, null, 2) + '</pre>';
                    } catch(e) {}
                }
                
                $('#wcld-modal-content').html(content);
                $('#wcld-modal').show();
            });
            
            $('.wcld-close, #wcld-modal').on('click', function(e) {
                if (e.target === this) $('#wcld-modal').hide();
            });
        });
        </script>
        <?php
    }
    
    /**
     * AJAX handler for design details
     */
    public function get_design_details() {
        check_ajax_referer('wcld_nonce', 'nonce');
        
        $design = isset($_POST['design']) ? sanitize_text_field($_POST['design']) : '';
        if (!$design) {
            wp_send_json_error('No design data');
        }
        
        wp_send_json_success(array(
            'html' => self::get_design_preview($design)
        ));
    }
}