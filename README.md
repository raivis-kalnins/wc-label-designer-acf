# WC Label Designer Pro

**WC Label Designer Pro** is a WooCommerce plugin to allow product label design on the frontend.  
Features:

- Drag & resize text layers
- QR code & barcode (Code128)
- Safe-zone overlay
- PDF & JSON export to cart
- Global and product-level settings (ACF)
- Admin order preview of design

## Installation

1. Upload the `wc-label-designer` folder to `wp-content/plugins/`.
2. Activate the plugin in WordPress admin.
3. Go to **Settings → Label Designer** to configure global defaults.
4. Enable designer per product or category.

## Requirements

- WordPress 6.x+
- WooCommerce 7.x+
- Advanced Custom Fields (ACF) Pro


wc-label-designer-pro/
├── wc-label-designer-pro.php           # Main plugin file, enqueues scripts, hooks frontend/backend.
├── includes/
│   ├── class-helpers.php
│   ├── admin/
│   │   ├── class-admin-settings.php    # Global settings (ACF options page / custom admin menu)
│   │   ├── class-product-settings.php  # Product-level settings (meta boxes / ACF)
│   │   └── class-order-preview.php     # Admin order item display
│   ├── frontend/
│   │   ├── class-designer.php
│   │   ├── designer-template.php
│   │   └── assets/
│   │       ├── designer.js             # Frontend designer JS
│   │       ├── designer.css            # Frontend designer CSS
│   │       ├── html2canvas.min.js      # Canvas capture library
│   │       ├── qrcode.min.js           # QR code generation
│   │       └── JsBarcode.all.min.js    # Barcode generation
│   └── ajax/
│       └── class-ajax-handlers.php     # AJAX endpoints (e.g., save PDF)
└── acf-json/                           # ACF JSON for WP Options page & Single Product
    ├── group_wcld_global.json
    └── group_wcld_product.json