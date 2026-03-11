(function($) {
    'use strict';
    
    $(document).ready(function() {
        var $area = $('#wcld-design-area');
        if (!$area.length) return;
        
        // Initialize wcldData with fallbacks
        window.wcldData = window.wcldData || {};
        wcldData.ajax_url = wcldData.ajax_url || wcldData.ajax || '/wp-admin/admin-ajax.php';
        wcldData.nonce = wcldData.nonce || '';
        wcldData.width = wcldData.width || 150;
        wcldData.height = wcldData.height || 100;
        wcldData.bleed = wcldData.bleed || 5;
        
        // Set background
        if (wcldData.bg) {
            $area.css('background-image', 'url(' + wcldData.bg + ')');
        }
        
        // Set dimensions
        $area.css({
            'width': wcldData.width + 'mm',
            'height': wcldData.height + 'mm',
            'position': 'relative',
            'overflow': 'hidden',
            'border': '1px solid #ccc'
        });
        
        // Initialize drag and resize
        $('.wcld-layer').draggable({
            containment: "#wcld-design-area",
            stop: function() { updateDesignJSON(); }
        }).resizable({
            containment: "#wcld-design-area",
            stop: function() { updateDesignJSON(); }
        });
        
        // Text input sync
        $(document).on('input', '.wcld-text', function() {
            var id = $(this).data('id');
            var $layer = $('#' + id);
            if ($layer.length) {
                $layer.text($(this).val());
                updateDesignJSON();
            }
        });
        
        // QR Code generation
        $('#wcld_qr').on('input change', function() {
            var val = $(this).val();
            var $el = $('#qr_layer');
            if (!$el.length) return;
            
            $el.empty();
            if (val && typeof QRCode !== 'undefined') {
                try {
                    new QRCode($el[0], {
                        text: val,
                        width: 80,
                        height: 80
                    });
                    updateDesignJSON();
                } catch(e) {
                    console.error('QR Code generation failed:', e);
                }
            }
        });
        
        // Barcode generation
        $('#wcld_bc').on('input change', function() {
            var val = $(this).val();
            var $el = $('#bc_layer');
            if (!$el.length) return;
            
            $el.empty();
            if (val && typeof JsBarcode !== 'undefined') {
                try {
                    var svg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
                    $el.append(svg);
                    JsBarcode(svg, val, {
                        format: "CODE128",
                        width: 2,
                        height: 40,
                        displayValue: true
                    });
                    updateDesignJSON();
                } catch(e) {
                    console.error('Barcode generation failed:', e);
                }
            }
        });
        
        // Capture design and add to cart
        window.captureAndAddToCart = function() {
            var $button = $('button.single_add_to_cart_button');
            var originalText = $button.text();
            
            $button.prop('disabled', true).text(wcldData.strings?.generating || 'Generating...');
            
            // Use html2canvas to capture design area
            if (typeof html2canvas === 'undefined') {
                console.error('html2canvas not loaded');
                $button.prop('disabled', false).text(originalText);
                return false;
            }
            
            html2canvas($area[0], {
                scale: 2,
                useCORS: true,
                allowTaint: true,
                backgroundColor: null
            }).then(function(canvas) {
                // Convert to base64
                var imageData = canvas.toDataURL('image/png');
                
                // Save to server first
                $.ajax({
                    url: wcldData.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'wcld_save_design',
                        nonce: wcldData.nonce,
                        image: imageData
                    },
                    success: function(response) {
                        if (response.success) {
                            // Set hidden inputs
                            $('#label_design_json').val(JSON.stringify(getDesignData()));
                            $('#label_design_image').val(response.data.url);
                            
                            // Show preview
                            $('#wcld-preview-container').html(
                                '<img src="' + response.data.url + '" style="max-width:200px;">'
                            ).show();
                            
                            // Submit form
                            $('form.cart').submit();
                        } else {
                            alert(response.data || 'Failed to save design');
                            $button.prop('disabled', false).text(originalText);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', error);
                        alert('Failed to save design. Please try again.');
                        $button.prop('disabled', false).text(originalText);
                    }
                });
            }).catch(function(err) {
                console.error('Canvas capture failed:', err);
                alert('Failed to capture design');
                $button.prop('disabled', false).text(originalText);
            });
            
            return false; // Prevent default form submit
        };
        
        // Override form submit to capture first
        $('form.cart').on('submit', function(e) {
            // If already has design data, allow normal submit
            if ($('#label_design_image').val()) {
                return true;
            }
            
            // Otherwise capture first
            e.preventDefault();
            captureAndAddToCart();
        });
        
        // Get design data as object
        function getDesignData() {
            var data = {
                width: wcldData.width,
                height: wcldData.height,
                bleed: wcldData.bleed,
                layers: [],
                timestamp: new Date().toISOString()
            };
            
            $('.wcld-layer').each(function() {
                var $layer = $(this);
                data.layers.push({
                    id: $layer.attr('id'),
                    type: $layer.data('type') || 'text',
                    content: $layer.text(),
                    left: parseInt($layer.css('left')) || 0,
                    top: parseInt($layer.css('top')) || 0,
                    width: $layer.width(),
                    height: $layer.height(),
                    fontSize: $layer.css('font-size'),
                    color: $layer.css('color')
                });
            });
            
            return data;
        }
        
        // Update JSON in hidden field
        function updateDesignJSON() {
            var json = JSON.stringify(getDesignData());
            $('#label_design_json').val(json);
            if (window.wcldDesigner) {
                window.wcldDesigner.designJSON = json;
            }
        }
        
        // Expose to global for backward compatibility
        window.wcldDesigner = {
            getDesignJSON: function() {
                return JSON.stringify(getDesignData());
            },
            designJSON: ''
        };
        
        // Initialize
        updateDesignJSON();
    });
    
})(jQuery);