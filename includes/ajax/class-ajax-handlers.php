<?php
class WCLD_Ajax_Handlers {
    
    public function __construct() {
        add_action('wp_ajax_wcld_save_design', array($this, 'save_design'));
        add_action('wp_ajax_nopriv_wcld_save_design', array($this, 'save_design'));
        add_action('wp_ajax_wcld_generate_pdf', array($this, 'generate_pdf'));
        add_action('wp_ajax_nopriv_wcld_generate_pdf', array($this, 'generate_pdf'));
    }
    
    /**
     * Save design image and return URL
     */
    public function save_design() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wcld_nonce')) {
            wp_send_json_error('Invalid security token');
        }
        
        if (empty($_POST['image'])) {
            wp_send_json_error('No image data received');
        }
        
        // Create upload directory
        $upload_dir = wp_upload_dir();
        $design_dir = $upload_dir['basedir'] . '/wcld-designs';
        
        if (!file_exists($design_dir)) {
            wp_mkdir_p($design_dir);
        }
        
        // Process base64 image
        $img_data = $_POST['image'];
        
        // Handle different base64 formats
        if (strpos($img_data, 'data:image/png;base64,') === 0) {
            $img_data = str_replace('data:image/png;base64,', '', $img_data);
        } elseif (strpos($img_data, 'data:image/jpeg;base64,') === 0) {
            $img_data = str_replace('data:image/jpeg;base64,', '', $img_data);
        }
        
        $img_data = str_replace(' ', '+', $img_data);
        $decoded = base64_decode($img_data);
        
        if (!$decoded) {
            wp_send_json_error('Failed to decode image');
        }
        
        // Generate unique filename
        $filename = 'design-' . time() . '-' . wp_rand(1000, 9999) . '.png';
        $filepath = $design_dir . '/' . $filename;
        
        // Save file
        if (file_put_contents($filepath, $decoded) === false) {
            wp_send_json_error('Failed to save image file');
        }
        
        // Return URL
        $file_url = $upload_dir['baseurl'] . '/wcld-designs/' . $filename;
        
        wp_send_json_success(array(
            'url' => $file_url,
            'filename' => $filename
        ));
    }
    
    /**
     * Generate PDF from design (optional - requires PDF library)
     */
    public function generate_pdf() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wcld_nonce')) {
            wp_send_json_error('Invalid security token');
        }
        
        if (empty($_POST['image'])) {
            wp_send_json_error('No image data');
        }
        
        // For now, just save as PNG and return URL (PDF generation requires additional library)
        // If you have TCPDF or FPDF installed, convert PNG to PDF here
        
        $this->save_design();
    }
}