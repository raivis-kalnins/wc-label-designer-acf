jQuery(function($){
    var $area = $('#wcld-design-area');
    if(!$area.length) return;

    // Initialize wcldData if missing
    if(typeof wcldData === 'undefined') wcldData = {};
    wcldData.ajax = wcldData.ajax || wc_add_to_cart_params.ajax_url;
    wcldData.nonce = wcldData.nonce || '';

    // Set background
    if(wcldData.bg) $area.css('background-image','url('+wcldData.bg+')');

    // Drag & resize
    $('.wcld-layer').draggable({ containment: "#wcld-design-area" })
                     .resizable({ containment: "#wcld-design-area" });

    // Sync inputs
    $('.wcld-text').on('input',function(){ 
        var id=$(this).data('id'); 
        $('#'+id).text($(this).val()); 
    });

    // QR code
    $('#wcld_qr').on('input change', function(){
        var val = $(this).val();
        var el = document.getElementById('qr_layer');
        if(!el) return;
        el.innerHTML='';
        if(val) new QRCode(el,{text:val,width:80,height:80});
    });

    // Barcode
    $('#wcld_bc').on('input change', function(){
        var val = $(this).val();
        var el = document.getElementById('bc_layer');
        if(!el) return;
        el.innerHTML='';
        if(val){
            var svg=document.createElementNS("http://www.w3.org/2000/svg","svg");
            el.appendChild(svg);
            JsBarcode(svg,val,{format:"code128",width:2,height:40,displayValue:true});
        }
    });

    // Form submit
    $('form.cart').on('submit',function(e){
        var productId = $form.find('input[name="add-to-cart"]').val() || $('#wcld-save-design-btn').data('product-id');
        if(!productId) return alert('Product ID missing!');
        e.preventDefault();
        var $form=$(this);
        var productId = $form.find('input[name="add-to-cart"]').val();
        if(!productId) return alert('Product ID missing!');

        // Collect layers
        var layers=[];
        $('.wcld-layer').each(function(){
            var $l=$(this);
            layers.push({
                id:$l.attr('id'),
                text:$l.text(),
                html:$l.html(),
                css:{
                    top:$l.css('top'),
                    left:$l.css('left'),
                    width:$l.css('width'),
                    height:$l.css('height'),
                    fontSize:$l.css('font-size')
                }
            });
        });

        var designJson = JSON.stringify({
            width:$area.width(),
            height:$area.height(),
            layers:layers
        });

        $('#design_json').val(designJson);

        // html2canvas -> PNG
        html2canvas($area[0]).then(function(canvas){
            var pdfData = canvas.toDataURL('image/png');
            $('#design_pdf').val(pdfData);

            // AJAX call to save design & add to cart
            $.post(wcldData.ajax, {
                action:'wcld_save_design',
                product_id: productId,
                design_json: designJson,
                design_pdf: pdfData,
                _wpnonce: wcldData.nonce
            }, function(response){
                if(response.success){
                    $(document.body).trigger('wc_fragment_refresh'); // refresh mini-cart
                    $form.off('submit').submit(); // continue normal checkout
                } else {
                    alert('Failed to add design to cart.');
                    console.error(response);
                    $form.off('submit').submit();
                }
            }).fail(function(){
                $form.off('submit').submit();
            });

        }).catch(function(){
            $form.off('submit').submit();
        });
    });
});