<?php
/**
 * Plugin Name: WC Label Designer Pro
 * Description: WooCommerce product label designer with PDF export
 * Version: 1.0.6
 * Author: Raivis Kalnins (Fixed by AI)
 * Requires WooCommerce: 7.0
 */

if (!defined('ABSPATH')) exit;

define('WCLD_VERSION', '1.0.6');
define('WCLD_PATH', plugin_dir_path(__FILE__));
define('WCLD_URL', plugin_dir_url(__FILE__));

add_action('plugins_loaded', 'wcld_init', 20);

function wcld_init() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>WC Label Designer requires WooCommerce.</p></div>';
        });
        return;
    }
    
    WCLD_Helpers::init();
    WCLD_Frontend_Designer::init();
    WCLD_Ajax_Handlers::init();
    
    if (is_admin()) {
        WCLD_Admin_Settings::init();
        WCLD_Product_Settings::init();
        WCLD_Order_Preview::init();
    }
    
    add_filter('woocommerce_add_cart_item_data', 'wcld_add_cart_item_data', 10, 3);
    add_filter('woocommerce_get_cart_item_from_session', 'wcld_get_cart_item_from_session', 10, 2);
    add_filter('woocommerce_get_item_data', 'wcld_display_cart_item_data', 10, 2);
    add_action('woocommerce_checkout_create_order_line_item', 'wcld_add_order_item_meta', 10, 4);
    add_action('woocommerce_before_add_to_cart_button', 'wcld_add_hidden_inputs');
}

function wcld_add_cart_item_data($cart_item_data, $product_id, $variation_id) {
    // DEBUG: Log what we receive
    error_log('WCLD: POST data = ' . print_r($_POST, true));
    
    if (!empty($_POST['label_design_json'])) {
        $cart_item_data['label_design'] = sanitize_text_field($_POST['label_design_json']);
        error_log('WCLD: Design JSON saved');
    }
    if (!empty($_POST['label_design_image'])) {
        $cart_item_data['label_design_image'] = sanitize_text_field($_POST['label_design_image']);
        error_log('WCLD: Design image saved: ' . $_POST['label_design_image']);
    }
    if (!empty($cart_item_data['label_design'])) {
        $cart_item_data['unique_key'] = md5(microtime() . rand());
        error_log('WCLD: Unique key generated');
    }
    return $cart_item_data;
}

function wcld_get_cart_item_from_session($cart_item, $values) {
    if (!empty($values['label_design'])) {
        $cart_item['label_design'] = $values['label_design'];
    }
    if (!empty($values['label_design_image'])) {
        $cart_item['label_design_image'] = $values['label_design_image'];
    }
    return $cart_item;
}

function wcld_display_cart_item_data($item_data, $cart_item) {
    if (!empty($cart_item['label_design'])) {
        $design = json_decode($cart_item['label_design'], true);
        $image_url = $cart_item['label_design_image'] ?? '';
        
        // Build summary text
        $summary_parts = array();
        if (!empty($design['text'])) {
            $summary_parts[] = 'Text: ' . esc_html($design['text']);
        }
        if (!empty($design['qr'])) {
            $summary_parts[] = 'QR: ' . esc_html($design['qr']);
        }
        if (!empty($design['barcode'])) {
            $summary_parts[] = 'Barcode: ' . esc_html($design['barcode']);
        }
        
        $summary = !empty($summary_parts) ? implode(' | ', $summary_parts) : 'Custom Label Design';
        
        // CRITICAL FIX: Create clickable link to view design
        $display_html = '<div style="font-size:12px;background:#f0f7ff;padding:10px;border-radius:3px;border:1px solid #0073aa;">';
        
        if ($image_url) {
            $display_html .= '<a href="' . esc_url($image_url) . '" target="_blank" style="font-weight:bold;color:#0073aa;text-decoration:none;">';
            $display_html .= '🎨 ' . $summary;
            $display_html .= '</a>';
            $display_html .= '<br><img src="' . esc_url($image_url) . '" style="max-width:150px;max-height:150px;border:1px solid #ddd;margin-top:8px;cursor:pointer;" onclick="window.open(\'' . esc_url($image_url) . '\', \'_blank\');">';
            $display_html .= '<br><a href="' . esc_url($image_url) . '" target="_blank" class="button" style="margin-top:8px;font-size:11px;padding:5px 10px;background:#0073aa;color:#fff;text-decoration:none;display:inline-block;">📄 View Full Size / Print</a>';
        } else {
            $display_html .= $summary;
        }
        
        $display_html .= '</div>';
        
        $item_data[] = array(
            'key'   => __('Label Design', 'wcld'),
            'value' => $display_html
        );
    }
    return $item_data;
}

function wcld_add_order_item_meta($item, $cart_item_key, $values, $order) {
    if (!empty($values['label_design'])) {
        $item->add_meta_data('_label_design', $values['label_design']);
        $item->add_meta_data('_label_design_image', $values['label_design_image'] ?? '');
    }
}

function wcld_add_hidden_inputs() {
    // CRITICAL FIX: Ensure fields are properly namespaced and will be submitted
    echo '<input type="hidden" name="label_design_json" id="label_design_json" value="">';
    echo '<input type="hidden" name="label_design_image" id="label_design_image" value="">';
    echo '<div id="wcld-preview-container" style="margin:10px 0;display:none;"></div>';
}

class WCLD_Helpers {
    public static function init() {}
    
    public static function is_enabled_for_product($product_id) {
        if (!function_exists('get_field')) {
            return get_post_meta($product_id, '_wcld_enable', true) === '1';
        }
        $enabled = get_field('wcld_enable', $product_id);
        return ($enabled === true || $enabled === '1' || $enabled === 1);
    }
}

class WCLD_Frontend_Designer {
    public static function init() {
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
        add_action('woocommerce_before_add_to_cart_button', array(__CLASS__, 'output_designer'));
    }
    
    public static function enqueue_assets() {
        if (!is_product()) return;
        
        global $product;
        if (!$product || !WCLD_Helpers::is_enabled_for_product($product->get_id())) return;
        
        wp_enqueue_script('jquery-ui-draggable');
        wp_enqueue_script('jquery-ui-resizable');
        
        wp_enqueue_script('html2canvas-cdn', 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js', array(), '1.4.1', true);
        wp_enqueue_script('qrcode-cdn', 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js', array(), '1.0.0', true);
        wp_enqueue_script('barcode-cdn', 'https://cdnjs.cloudflare.com/ajax/libs/jsbarcode/3.11.3/JsBarcode.all.min.js', array(), '3.11.3', true);
        
        wp_add_inline_script('html2canvas-cdn', self::get_designer_js(), 'after');
        wp_add_inline_style('woocommerce-layout', self::get_designer_css());
        
        $width = 150;
        $height = 100;
        $bleed = 5;
        $bg = '';
        
        if (function_exists('get_field')) {
            $width = get_field('wcld_width', $product->get_id()) ?: get_field('wcld_width', 'option') ?: 150;
            $height = get_field('wcld_height', $product->get_id()) ?: get_field('wcld_height', 'option') ?: 100;
            $bleed = get_field('wcld_bleed', $product->get_id()) ?: get_field('wcld_bleed', 'option') ?: 5;
            $bg = get_field('wcld_bg', $product->get_id()) ?: get_field('wcld_bg', 'option') ?: '';
        }
        
        wp_localize_script('html2canvas-cdn', 'wcldData', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'width' => (float) $width,
            'height' => (float) $height,
            'bleed' => (float) $bleed,
            'bg' => $bg,
            'nonce' => wp_create_nonce('wcld_nonce'),
            'product_id' => $product->get_id()
        ));
    }
    
    public static function output_designer() {
        global $product;
        if (!$product || !WCLD_Helpers::is_enabled_for_product($product->get_id())) return;
        
        echo self::get_designer_html();
    }
    
    private static function get_designer_html() {
        return '
        <div id="wcld-designer-wrapper" style="margin:20px 0;padding:20px;border:2px solid #0073aa;background:#f0f7ff;border-radius:5px;">
            <h3 style="margin-top:0;">Design Your Label</h3>
            <div style="display:flex;gap:20px;flex-wrap:wrap;">
                <div style="flex:1;min-width:300px;">
                    <div id="wcld-design-area" style="width:300px;height:200px;background:#fff;border:2px dashed #0073aa;position:relative;margin:10px 0;overflow:hidden;background-size:cover;background-position:center;">
                        <div id="text_layer" class="wcld-layer" style="position:absolute;left:20px;top:20px;padding:8px 12px;background:rgba(255,255,255,0.95);border:1px solid #ddd;cursor:move;font-family:Arial;font-size:16px;min-width:100px;z-index:10;box-shadow:0 2px 4px rgba(0,0,0,0.1);">Your Text Here</div>
                        <div id="qr_layer" style="position:absolute;right:20px;top:20px;background:#fff;padding:5px;z-index:10;"></div>
                        <div id="bc_layer" style="position:absolute;left:20px;bottom:20px;background:#fff;padding:5px;z-index:10;"></div>
                    </div>
                    <p style="font-size:11px;color:#666;margin-top:5px;">💡 Drag text to position. Enter text/QR/barcode below.</p>
                </div>
                <div style="flex:1;min-width:250px;">
                    <div style="margin-bottom:15px;">
                        <label style="display:block;font-weight:bold;margin-bottom:5px;">Label Text:</label>
                        <input type="text" id="wcld_text_input" class="wcld-text" data-id="text_layer" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:3px;" placeholder="Enter your text..." value="Your Text Here">
                    </div>
                    <div style="margin-bottom:15px;">
                        <label style="display:block;font-weight:bold;margin-bottom:5px;">QR Code Content:</label>
                        <input type="text" id="wcld_qr" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:3px;" placeholder="https://example.com or any text">
                    </div>
                    <div style="margin-bottom:15px;">
                        <label style="display:block;font-weight:bold;margin-bottom:5px;">Barcode (Code128):</label>
                        <input type="text" id="wcld_bc" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:3px;" placeholder="123456789">
                    </div>
                    <button type="button" id="wcld-capture-btn" class="button alt" style="width:100%;padding:12px;background:#0073aa;color:#fff;font-weight:bold;border:none;cursor:pointer;border-radius:3px;">
                        📸 Capture Design & Add to Cart
                    </button>
                    <div id="wcld-status" style="margin-top:10px;font-size:12px;color:#666;display:none;padding:8px;background:#fff;border-radius:3px;"></div>
                </div>
            </div>
        </div>';
    }
    
    private static function get_designer_js() {
        return '
        (function($) {
            "use strict";
            
            $(document).ready(function() {
                var $area = $("#wcld-design-area");
                if (!$area.length) return;
                
                // Apply background image
                if (wcldData.bg && wcldData.bg.length > 0) {
                    $area.css({
                        "background-image": "url(" + wcldData.bg + ")",
                        "background-size": "cover",
                        "background-position": "center"
                    });
                }
                
                // Check libraries
                if (typeof html2canvas === "undefined") {
                    console.error("html2canvas not loaded");
                    $("#wcld-status").text("❌ Error: Designer library not loaded. Please refresh the page.").css({"color":"red","display":"block"}).show();
                    return;
                }
                
                // Initialize draggable
                try {
                    $("#text_layer").draggable({ 
                        containment: "#wcld-design-area",
                        stop: function() { updateDesignData(); }
                    });
                } catch(e) {
                    console.log("Draggable not available:", e);
                }
                
                // Text input sync
                $(document).on("input", "#wcld_text_input", function() {
                    var text = $(this).val() || "Your Text Here";
                    $("#text_layer").text(text);
                    updateDesignData();
                });
                
                // QR Code generation
                $("#wcld_qr").on("input change", function() {
                    var val = $(this).val().trim();
                    var $el = $("#qr_layer");
                    $el.empty();
                    if (val && typeof QRCode !== "undefined") {
                        try {
                            new QRCode($el[0], {
                                text: val,
                                width: 80,
                                height: 80,
                                colorDark: "#000000",
                                colorLight: "#ffffff",
                                correctLevel: QRCode.CorrectLevel.H
                            });
                            updateDesignData();
                        } catch(e) {
                            console.error("QR Error:", e);
                        }
                    }
                });
                
                // Barcode generation
                $("#wcld_bc").on("input change", function() {
                    var val = $(this).val().trim();
                    var $el = $("#bc_layer");
                    $el.empty();
                    if (val && typeof JsBarcode !== "undefined") {
                        try {
                            var svg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
                            $el.append(svg);
                            JsBarcode(svg, val, {
                                format: "CODE128",
                                width: 2,
                                height: 50,
                                displayValue: true,
                                fontSize: 14
                            });
                            updateDesignData();
                        } catch(e) {
                            console.error("Barcode Error:", e);
                            $el.html("<div style=\"color:red;font-size:12px;\">Invalid barcode</div>");
                        }
                    }
                });
                
                function updateDesignData() {
                    var designData = {
                        text: $("#wcld_text_input").val(),
                        qr: $("#wcld_qr").val(),
                        barcode: $("#wcld_bc").val(),
                        bg_image: wcldData.bg || "",
                        text_position: {
                            left: $("#text_layer").css("left"),
                            top: $("#text_layer").css("top")
                        },
                        timestamp: new Date().toISOString()
                    };
                    $("#label_design_json").val(JSON.stringify(designData));
                    console.log("Design data updated:", designData);
                }
                
                updateDesignData();
                
                // CRITICAL FIX: Capture button click with proper form submission
                $("#wcld-capture-btn").on("click", function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    var $btn = $(this);
                    var $status = $("#wcld-status");
                    var $form = $("form.cart");
                    
                    // Disable WooCommerce add to cart button to prevent double submission
                    var $wcButton = $form.find("button[type=submit], .single_add_to_cart_button");
                    $wcButton.prop("disabled", true);
                    $btn.prop("disabled", true).text("⏳ Processing...");
                    $status.text("📸 Capturing design...").css({"color":"#666","display":"block"}).show();
                    
                    if (typeof html2canvas === "undefined") {
                        alert("❌ Designer library not loaded. Please refresh the page.");
                        $btn.prop("disabled", false).text("📸 Capture Design & Add to Cart");
                        $wcButton.prop("disabled", false);
                        return false;
                    }
                    
                    // Capture the design area
                    html2canvas(document.getElementById("wcld-design-area"), {
                        scale: 2,
                        useCORS: true,
                        allowTaint: true,
                        backgroundColor: "#ffffff",
                        logging: false
                    }).then(function(canvas) {
                        $status.text("☁️ Uploading to server...");
                        var imageData = canvas.toDataURL("image/png");
                        
                        // AJAX upload
                        $.ajax({
                            url: wcldData.ajax_url,
                            type: "POST",
                            data: {
                                action: "wcld_save_design",
                                nonce: wcldData.nonce,
                                image: imageData,
                                product_id: wcldData.product_id
                            },
                            success: function(response) {
                                console.log("Server response:", response);
                                
                                if (response.success && response.data && response.data.url) {
                                    $status.text("✅ Design saved! Adding to cart...").css("color","green");
                                    
                                    // CRITICAL: Set hidden fields
                                    $("#label_design_image").val(response.data.url);
                                    updateDesignData();
                                    
                                    // Show preview
                                    $("#wcld-preview-container").html(
                                        "<p>Preview:</p><img src=\"" + response.data.url + "\" style=\"max-width:200px;border:1px solid #ddd;\">"
                                    ).show();
                                    
                                    // CRITICAL FIX: Re-enable button and manually trigger form submission
                                    // Use a small delay to ensure fields are set
                                    setTimeout(function() {
                                        $status.text("🛒 Submitting to cart...");
                                        $wcButton.prop("disabled", false);
                                        
                                        // Trigger the actual WooCommerce add to cart
                                        // Method 1: Trigger click on the actual WooCommerce button
                                        if ($wcButton.length && !$wcButton.is($btn)) {
                                            $wcButton.trigger("click");
                                        } else {
                                            // Method 2: Submit form directly
                                            $form.trigger("submit");
                                        }
                                    }, 500);
                                } else {
                                    var errorMsg = (response.data) ? response.data : "Unknown server error";
                                    $status.text("❌ Error: " + errorMsg).css("color","red");
                                    $btn.prop("disabled", false).text("📸 Capture Design & Add to Cart");
                                    $wcButton.prop("disabled", false);
                                    console.error("Server error:", response);
                                }
                            },
                            error: function(xhr, status, error) {
                                $status.text("❌ Server error: " + error).css("color","red");
                                $btn.prop("disabled", false).text("📸 Capture Design & Add to Cart");
                                $wcButton.prop("disabled", false);
                                console.error("AJAX error:", status, error);
                                console.error("Response:", xhr.responseText);
                            }
                        });
                    }).catch(function(err) {
                        console.error("Canvas error:", err);
                        $status.text("❌ Capture failed: " + err.message).css("color","red");
                        $btn.prop("disabled", false).text("📸 Capture Design & Add to Cart");
                        $wcButton.prop("disabled", false);
                    });
                    
                    return false;
                });
            });
        })(jQuery);';
    }
    
    private static function get_designer_css() {
        return '
        #wcld-designer-wrapper { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        #wcld-design-area { transition: all 0.3s ease; box-shadow: inset 0 0 10px rgba(0,0,0,0.1); }
        .wcld-layer { user-select: none; }
        .wcld-layer:hover { border-color: #0073aa !important; box-shadow: 0 0 8px rgba(0,115,170,0.4); }
        #wcld-capture-btn:hover { background: #005a87 !important; }
        #wcld-capture-btn:disabled { opacity: 0.6; cursor: not-allowed; }
        #qr_layer img, #bc_layer svg { display: block; margin: 0 auto; }';
    }
}

class WCLD_Ajax_Handlers {
    public static function init() {
        add_action('wp_ajax_wcld_save_design', array(__CLASS__, 'save_design'));
        add_action('wp_ajax_nopriv_wcld_save_design', array(__CLASS__, 'save_design'));
    }
    
    public static function save_design() {
        // Log for debugging
        error_log('WCLD AJAX: save_design called');
        
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wcld_nonce')) {
            error_log('WCLD AJAX: Nonce verification failed');
            wp_send_json_error('Security check failed. Please refresh the page.');
        }
        
        if (empty($_POST['image'])) {
            error_log('WCLD AJAX: No image data received');
            wp_send_json_error('No image data received.');
        }
        
        $upload_dir = wp_upload_dir();
        $design_dir = $upload_dir['basedir'] . '/wcld-designs';
        
        if (!file_exists($design_dir)) {
            if (!wp_mkdir_p($design_dir)) {
                error_log('WCLD AJAX: Failed to create directory');
                wp_send_json_error('Failed to create upload directory.');
            }
            
            $htaccess_content = "Options -Indexes\n";
            $htaccess_content .= "<FilesMatch '\\.(php|php\\.|php3|php4|phtml|pl|py|jsp|asp|sh|cgi)$'>\n";
            $htaccess_content .= "order allow,deny\n";
            $htaccess_content .= "deny from all\n";
            $htaccess_content .= "</FilesMatch>";
            file_put_contents($design_dir . '/.htaccess', $htaccess_content);
        }
        
        $img_data = $_POST['image'];
        
        if (strpos($img_data, 'data:image/png;base64,') === 0) {
            $img_data = substr($img_data, strlen('data:image/png;base64,'));
        } elseif (strpos($img_data, 'data:image/jpeg;base64,') === 0) {
            $img_data = substr($img_data, strlen('data:image/jpeg;base64,'));
        }
        
        $img_data = str_replace(' ', '+', $img_data);
        $decoded = base64_decode($img_data, true);
        
        if (!$decoded) {
            error_log('WCLD AJAX: Base64 decode failed');
            wp_send_json_error('Failed to decode image data.');
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_buffer($finfo, $decoded);
        finfo_close($finfo);
        
        if (!in_array($mime_type, array('image/png', 'image/jpeg'))) {
            error_log('WCLD AJAX: Invalid mime type: ' . $mime_type);
            wp_send_json_error('Invalid image format: ' . $mime_type);
        }
        
        $filename = 'wcld-' . time() . '-' . wp_rand(1000, 9999) . '.png';
        $filepath = $design_dir . '/' . $filename;
        
        if (file_put_contents($filepath, $decoded) === false) {
            error_log('WCLD AJAX: Failed to save file');
            wp_send_json_error('Failed to save image file.');
        }
        
        chmod($filepath, 0644);
        
        $file_url = $upload_dir['baseurl'] . '/wcld-designs/' . $filename;
        
        error_log('WCLD AJAX: Success - file saved to ' . $file_url);
        
        wp_send_json_success(array(
            'url' => $file_url,
            'filename' => $filename,
            'size' => strlen($decoded)
        ));
    }
}

class WCLD_Admin_Settings {
    public static function init() {
        add_action('acf/init', array(__CLASS__, 'add_options_page'));
    }
    
    public static function add_options_page() {
        if (function_exists('acf_add_options_page')) {
            acf_add_options_page(array(
                'page_title' => 'Label Designer Settings',
                'menu_title' => 'Label Designer',
                'menu_slug' => 'wcld-settings',
                'capability' => 'manage_options',
                'icon_url' => 'dashicons-art',
                'position' => 30
            ));
        }
    }
}

class WCLD_Product_Settings {
    public static function init() {
        add_action('add_meta_boxes', array(__CLASS__, 'add_meta_box'));
        add_action('save_post_product', array(__CLASS__, 'save_meta'));
    }
    
    public static function add_meta_box() {
        add_meta_box(
            'wcld_product_settings', 
            'Label Designer', 
            array(__CLASS__, 'meta_box_html'), 
            'product', 
            'side',
            'default'
        );
    }
    
    public static function meta_box_html($post) {
        wp_nonce_field('wcld_save_meta', 'wcld_meta_nonce');
        $enabled = get_post_meta($post->ID, '_wcld_enable', true);
        ?>
        <p>
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                <input type="checkbox" name="wcld_enable" value="1" <?php checked($enabled, '1'); ?> style="width:auto;">
                <span>Enable Label Designer for this product</span>
            </label>
        </p>
        <p class="description" style="color:#666;font-size:12px;">
            Check this to show the label designer on the product page.
        </p>
        <?php
    }
    
    public static function save_meta($post_id) {
        if (!isset($_POST['wcld_meta_nonce']) || !wp_verify_nonce($_POST['wcld_meta_nonce'], 'wcld_save_meta')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_product', $post_id)) return;
        
        $enabled = isset($_POST['wcld_enable']) ? '1' : '';
        update_post_meta($post_id, '_wcld_enable', $enabled);
    }
}

class WCLD_Order_Preview {
    public static function init() {
        add_action('woocommerce_after_order_itemmeta', array(__CLASS__, 'display_design'), 10, 3);
    }
    
    public static function display_design($item_id, $item, $product) {
        $design = $item->get_meta('_label_design');
        $image = $item->get_meta('_label_design_image');
        
        if (!$design && !$image) return;
        
        $data = json_decode($design, true);
        
        echo '<div style="margin-top:10px;padding:10px;background:#f0f7ff;border:1px solid #0073aa;border-radius:3px;">';
        echo '<strong style="color:#0073aa;">🎨 Label Design:</strong><br>';
        
        if ($image) {
            echo '<a href="' . esc_url($image) . '" target="_blank" style="display:inline-block;margin:5px 0;">';
            echo '<img src="' . esc_url($image) . '" style="max-width:200px;max-height:200px;border:1px solid #ddd;background:#fff;">';
            echo '</a><br>';
            echo '<a href="' . esc_url($image) . '" target="_blank" class="button" style="font-size:12px;">📄 View / Download Design</a>';
        }
        
        if ($data) {
            echo '<details style="margin-top:8px;font-size:11px;">';
            echo '<summary style="cursor:pointer;color:#666;">Design Details</summary>';
            echo '<div style="background:#fff;padding:8px;margin-top:5px;border-radius:3px;">';
            if (!empty($data['text'])) echo '<div><strong>Text:</strong> ' . esc_html($data['text']) . '</div>';
            if (!empty($data['qr'])) echo '<div><strong>QR:</strong> ' . esc_html($data['qr']) . '</div>';
            if (!empty($data['barcode'])) echo '<div><strong>Barcode:</strong> ' . esc_html($data['barcode']) . '</div>';
            echo '</div>';
            echo '</details>';
        }
        
        echo '</div>';
    }
}

register_activation_hook(__FILE__, function() {
    $upload_dir = wp_upload_dir();
    $design_dir = $upload_dir['basedir'] . '/wcld-designs';
    
    if (!file_exists($design_dir)) {
        wp_mkdir_p($design_dir);
    }
    
    $htaccess_content = "Options -Indexes\n";
    $htaccess_content .= "<FilesMatch '\\.(php|php\\.|php3|php4|phtml|pl|py|jsp|asp|sh|cgi)$'>\n";
    $htaccess_content .= "order allow,deny\n";
    $htaccess_content .= "deny from all\n";
    $htaccess_content .= "</FilesMatch>";
    
    file_put_contents($design_dir . '/.htaccess', $htaccess_content);
});