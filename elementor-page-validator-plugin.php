<?php
/**
 * Plugin Name: Elementor Page Validator Plugin
 * Description: This plugin enables all Elementor pages and blocks validation for a given website.
 * Version: 1.0
 * Author: Thomas Couderc
 */


// Initialize or get SNAPSHOT_URL from WordPress options
if (!get_option('snapshot_url')) {
    add_option('snapshot_url', 'https://webshot-elzinko.vercel.app/api/webshot');
}

define ('SNAPSHOT_URL', get_option('snapshot_url'));
// Automatically detect the website URL
define ('WEBSITE_URL', get_site_url());


// Add menu and submenu in admin panel
add_action('admin_menu', 'add_menu_and_submenu_page_validator');

function add_menu_and_submenu_page_validator() {
    add_menu_page('Page validator', 'Page validator', 'manage_options', 'page_validator', 'show_page_validator_plugin');
    add_submenu_page('page_validator', 'Settings', 'Settings', 'manage_options', 'page_validator_settings', 'show_page_validator_settings');
}

function find_first_title($elements) {
    foreach ($elements as $element) {
        if (isset($element['widgetType']) && ($element['widgetType'] === 'heading' || $element['widgetType'] === 'widget-menu-anchor')) {
            return isset($element['settings']['title']) ? $element['settings']['title'] : 'Sans titre';
        }
        
        if (isset($element['elements']) && is_array($element['elements'])) {
            $title = find_first_title($element['elements']);
            if ($title) {
                return $title;
            }
        }
    }
    return null;
}

function show_page_validator_settings() {
    
    // Handle form submission for settings
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['snapshot_url'])) {
            update_option('snapshot_url', sanitize_text_field($_POST['snapshot_url']));
        }
    }

    // Fetch the snapshot_url from options
    $snapshot_url = get_option('snapshot_url', SNAPSHOT_URL);

    echo '<div class="wrap">';
    echo '<h1>Settings</h1>';
    echo '<form method="post">';
    echo '<label for="snapshot_url">Snapshot URL: </label>';
    echo '<input type="text" id="snapshot_url" name="snapshot_url" value="' . esc_attr($snapshot_url) . '">';
    echo '<input type="submit" value="Save">';
    echo '</form>';
    echo '</div>';
}

function show_page_validator_plugin() {
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['valider'])) {
            foreach ($_POST['valider'] as $page_id) {
                update_post_meta($page_id, 'is_validated', 'true');
            }
        }

        if (isset($_POST['valider_section'])) {
            foreach ($_POST['valider_section'] as $section_id) {
                update_post_meta($section_id, 'is_validated', 'true');
            }
        }
    }

    // Fetch all Elementor-edited pages
    $args = array(
        'post_type' => 'page',
        'posts_per_page' => -1,
        'meta_key' => '_elementor_edit_mode',
        'meta_value' => 'builder'
    );
    $query = new WP_Query($args);

    echo '<div class="wrap">';
    echo '<h1>Validation des éléments Elementor</h1>';
    echo '<form method="post">';
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th scope="col">ID</th><th scope="col">Valider</th><th scope="col">Fil d\'Ariane</th><th scope="col">Aperçu</th></tr></thead>';
    echo '<tbody>';

    $getSnapshot = true;

    if ($query->have_posts()) {
        while ($query->have_posts()) {

            $query->the_post();
            $page_id = get_the_ID();
            $titre_page = get_the_title();
            // Create an anchor link to page
            $section_link = get_permalink($page_id);

            // Display row for the page
            echo '<tr data-id="' . $page_id . '">';
            echo '<td>' . $page_id . '</td>';
            echo '<td><input type="checkbox" name="valider[]" value="' . $page_id . '"></td>';
            echo '<td><a href="' . $section_link . '" target = "_ blank">' . $titre_page . '</a></td>';
            echo '<td><img src="' . SNAPSHOT_URL .'?url=' . $section_link . '&selectorId=' . $css_id . '" width="100"></td>';
            if ($getSnapshot) {
                echo '<td><img src="' . SNAPSHOT_URL .'?url=' . WEBSITE_URL . '&fullpage" width="100"></td>';
            } else {
                echo '<td><img src="https://picsum.photos/200" width="100"></td>';
            }
            echo '</tr>';


            // Fetch Elementor data
            $raw_elementor_data = get_post_meta($page_id, '_elementor_data', true);
            $elementor_data = json_decode($raw_elementor_data, true);

            // Loop through the data to find sections
            if (is_array($elementor_data)) {
                foreach ($elementor_data as $element) {
                    if (isset($element['elType']) && 'section' === $element['elType']) {
                        $section_title = 'Section sans titre';
                        // get css id of element
                        $css_id = isset($element['settings']['_element_id']) ? $element['settings']['_element_id'] : null;  
                        
                        // Create an anchor link to the section
                        if ($css_id) {
                            $section_link = get_permalink($page_id) . '#' . $css_id;
                        }

                        // Check if the section contains elements
                         if (isset($element['elements']) && is_array($element['elements'])) {
                            $title = find_first_title($element['elements']);
                            if ($title) {
                                $section_title = $title;
                            }
                        }

                        $css_name = $css_id? $css_id : 'x';

                        // Display row for the section
                        echo '<tr data-id="' . $page_id . '-' . $element['id'] . '">';
                        echo '<td>' . $css_name . '</td>';
                        echo '<td><input type="checkbox" name="valider_section[]" value="' . $element['id'] . '"></td>';
                        if ($css_id) {
                            echo '<td><a href="' . $section_link . '" target = "_ blank">' . $titre_page . ' > ' . $section_title . '</a></td>';
                        } else {
                            echo '<td>' . $titre_page . ' > ' . $section_title . '</td>';
                        }
                        if ($getSnapshot && $css_id) {
                            echo '<td><img src="' . SNAPSHOT_URL .'?url=' . $section_link . '&selectorId=' . $css_id . '" width="100"></td>';
                            $getSnapshot = false;
                        } else {
                            echo '<td><img src="https://picsum.photos/200" width="100"></td>';
                        }
                        echo '</tr>';
                    }
                }
            }
        }
    }

    echo '</tbody>';
    echo '</table>';
    echo '<input type="submit" value="Valider les éléments sélectionnés">';
    echo '</form>';
    echo '</div>';
    echo '<script>
    jQuery(document).ready(function($) {
        $("input[name=\'valider[]\']").change(function() {
            var parentChecked = $(this).prop("checked");
            var parentId = $(this).val();
            $("tr[data-id^=\'" + parentId + "-\']").find("input[type=\'checkbox\']").prop("checked", parentChecked);
        });
    });
    </script>';
}
?>