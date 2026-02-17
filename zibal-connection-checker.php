<?php
/*
Plugin Name: Zibal Advanced Connection Checker
Plugin URI: https://zibal.ir/
Description: Professional diagnostics for Zibal connectivity
Version: 1.0
Author: Abolfazl Abdollahi
Author URI: https://zibal.ir/
Text Domain: zibal-connection-checker
*/

if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . 'includes/admin-page.php';
require_once plugin_dir_path(__FILE__) . 'includes/connection-tests.php';
require_once plugin_dir_path(__FILE__) . 'includes/merchant-tests.php';

add_action('admin_enqueue_scripts', function($hook) {
    if($hook === 'toplevel_page_zibal-check'){
        wp_enqueue_style('zibal-admin-css', plugin_dir_url(__FILE__) . 'assets/admin.css', [], '1.0.1');
        wp_enqueue_script('jquery');
    }
});
