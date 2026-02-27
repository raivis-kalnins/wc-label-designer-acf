<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $product;
if ( ! $product instanceof WC_Product ) return;
?>

<div class="wcld-fields">
    <label>Title</label>
    <input type="text" class="wcld-text" data-id="text1" placeholder="Title" value="Title">
    <label>Subtitle</label>
    <input type="text" class="wcld-text" data-id="text2" placeholder="Subtitle" value="Subtitle">
    <label>Footer</label>
    <input type="text" class="wcld-text" data-id="text3" placeholder="Footer" value="Footer">
    <label>QR URL</label>
    <input type="text" id="wcld_qr" placeholder="https://example.com" value="https://example.com">
    <label>Barcode (Code128)</label>
    <input type="text" id="wcld_bc" placeholder="1234567890" value="1234567890">
</div>

<div id="wcld-design-area" style="position:relative;border:1px solid #ccc;">
    <div class="wcld-layer" id="text1">Title</div>
    <div class="wcld-layer" id="text2">Subtitle</div>
    <div class="wcld-layer" id="text3">Footer</div>
    <div class="wcld-layer" id="qr_layer"></div>
    <div class="wcld-layer" id="bc_layer"></div>
    <div class="safe-zone"></div>
</div>

<!-- Save Design & Add to Cart -->
<button id="wcld-save-design-btn" data-product-id="<?php echo esc_attr($product->get_id()); ?>">
    Save Design & Add to Cart
</button>

<!-- Security Nonce -->
<?php wp_nonce_field( 'wcld_save_design', 'wcld_design_nonce' ); ?>

<!-- Hidden fields for JSON / PDF -->
<input type="hidden" id="design_json" name="design_json">
<input type="hidden" id="design_pdf" name="design_pdf">

<!-- JS Libraries -->
<script src="<?php echo WCLD_URL; ?>includes/frontend/assets/html2canvas.min.js"></script>
<script src="<?php echo WCLD_URL; ?>includes/frontend/assets/qrcode.min.js"></script>
<script src="<?php echo WCLD_URL; ?>includes/frontend/assets/JsBarcode.all.min.js"></script>
<script src="<?php echo WCLD_URL; ?>includes/frontend/assets/designer.js"></script>