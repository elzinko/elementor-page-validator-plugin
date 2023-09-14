<?php

/**
 * Plugin Name: Elementor Page Validator Plugin
 * Description: This plugin enable all Elementor pages and blocks validation for a given website.
 * Version: 1.0
 * Author: Thomas Couderc
 */


define('EPVP_PLUGIN_DEBUG', false);
define('EPVP_PLUGIN_VERSION', '1.0.0');
define('EPVP_PLUGIN_NAME', 'elementor-page-validator-plugin');


/**
 * Register plugin activation hook.
 * Check if Elementor is active, if not, deactivate plugin.
 */

function elementor_page_validation_plugin_activation()
{
    if (!is_plugin_active('elementor/elementor.php')) {
        // Disable your plugin.
        deactivate_plugins(plugin_basename(__FILE__));

        // Create an error message to let the user know.
        wp_die(__('This plugin needs Elementor to work. Please install and activate Elementor, then try again.', 'media-info-tracker'));
    }
}
register_activation_hook(__FILE__, 'elementor_page_validation_plugin_activation');

// Add menu in admin panel
add_action('admin_menu', 'add_menu_page_validator');

function add_menu_page_validator() {
    add_menu_page('Page validator', 'Page validator', 'manage_options', 'page_validator', 'show_page_validator_plugin');
}

function show_page_validator_plugin() {
    echo '<h1>Here is ' . EPVP_PLUGIN_NAME . ' plugin version ' . EPVP_PLUGIN_VERSION . ' !! </h1>';
}