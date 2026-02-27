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
     $('form.cart').on('submit', function(){
        if(window.wcldDesigner){
            var json = window.wcldDesigner.getDesignJSON();
            if($('#label_design_json').length === 0){
                $('<input>').attr({
                    type: 'hidden',
                    id: 'label_design_json',
                    name: 'label_design_json',
                    value: json
                }).appendTo(this);
            } else {
                $('#label_design_json').val(json);
            }
        }
    });
});