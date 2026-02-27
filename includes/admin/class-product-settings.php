<?php
if ( ! defined('ABSPATH') ) exit;

class WCLD_Product_Settings {
    public function __construct() {
        add_action('woocommerce_product_options_general_product_data', [$this,'fields']);
        add_action('woocommerce_process_product_meta', [$this,'save_fields']);
        add_action('admin_footer', [$this,'media_script']);
    }

    public function fields(){
        echo '<div class="options_group">';
        // woocommerce_wp_checkbox(['id'=>'_enable_label_designer','label'=>'Enable Label Designer']);
        // woocommerce_wp_text_input(['id'=>'_label_width','label'=>'Width (mm)']);
        // woocommerce_wp_text_input(['id'=>'_label_height','label'=>'Height (mm)']);
        // woocommerce_wp_text_input(['id'=>'_label_bleed','label'=>'Bleed (mm)']);
        // woocommerce_wp_text_input(['id'=>'_label_bg_image','label'=>'Background Image','class'=>'wcld-bg-field']);
        // echo '<button class="button wcld-upload">Upload Image</button>';
        echo '</div>';
    }

    public function save_fields($post_id){
        update_post_meta($post_id,'_enable_label_designer',isset($_POST['_enable_label_designer'])?'yes':'no');
        update_post_meta($post_id,'_label_width',sanitize_text_field($_POST['_label_width']));
        update_post_meta($post_id,'_label_height',sanitize_text_field($_POST['_label_height']));
        update_post_meta($post_id,'_label_bleed',sanitize_text_field($_POST['_label_bleed']));
        update_post_meta($post_id,'_label_bg_image',sanitize_text_field($_POST['_label_bg_image']));
    }

    public function media_script(){ ?>
        <script>
        jQuery(function($){
            $('.wcld-upload').click(function(e){
                e.preventDefault();
                let frame = wp.media({title:'Select Image', multiple:false});
                frame.on('select', function(){
                    let url = frame.state().get('selection').first().toJSON().url;
                    $('.wcld-bg-field').val(url);
                });
                frame.open();
            });
        });
        </script>
    <?php }
}