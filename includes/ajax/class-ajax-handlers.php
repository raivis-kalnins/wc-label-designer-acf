<?php
if ( ! defined('ABSPATH') ) exit;

class WCLD_Ajax_Handlers {
    public function __construct() {
        add_action( 'wp_ajax_wcld_save_pdf', [$this,'save_pdf'] );
        add_action( 'wp_ajax_nopriv_wcld_save_pdf', [$this,'save_pdf'] );
    }

    public function save_pdf() {
        check_ajax_referer('wcld_nonce','nonce');
        if ( empty($_POST['data']) ) wp_send_json_error(['message'=>'No data'],400);

        // Save base64 PNG
        $upload = wp_upload_dir();
        $data = $_POST['data'];
        $data = str_replace('data:image/png;base64,','',$data);
        $data = base64_decode($data);
        $filename = 'wcld-design-'.time().'.png';
        $file = $upload['basedir'].'/'.$filename;
        file_put_contents($file,$data);

        $url = $upload['baseurl'].'/'.$filename;
        wp_send_json_success(['url'=>$url]);
    }
}