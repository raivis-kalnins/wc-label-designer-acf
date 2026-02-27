<?php
if ( ! defined('ABSPATH') ) exit;

class WCLD_Admin_Settings {
    public function __construct() {
        add_action('admin_menu', [$this,'menu_page']);
    }

    public function menu_page() {
        //add_menu_page('Label Designer','Label Designer','manage_options','wcld-settings',[$this,'page_html']);
    }

    public function page_html() {
        $settings = get_option('wcld_global_settings');
        ?>
        <div class="wrap"><h2>WP Option Settings</h2></div>
        <?php
    }
}