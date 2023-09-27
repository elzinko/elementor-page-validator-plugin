<?php
/**
 * Plugin Name: Elementor Page Validator Plugin
 * Description: This plugin enables all Elementor pages and blocks validation for a given website.
 * Version: 1.0
 * Author: Thomas Couderc
 */

define('WEBSITE_URL', 'https://yearbook:receptive@roomy-company.localsite.io');
define('SNAPSHOT_URL', 'https://webshot-elzinko.vercel.app/api/webshot?url='. WEBSITE_URL);

// Add menu in admin panel
add_action('admin_menu', 'add_menu_page_validator');

function add_menu_page_validator() {
    add_menu_page('Page validator', 'Page validator', 'manage_options', 'page_validator', 'show_page_validator_plugin');
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

    // Fetch all pages
    $args = array(
        'post_type' => 'page',
        'posts_per_page' => -1,
    );
    $query = new WP_Query($args);

    echo '<div class="wrap">';
    echo '<h1>Validation des éléments Elementor</h1>';
    echo '<form method="post">';
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th scope="col">ID</th><th scope="col">Valider</th><th scope="col">Fil d\'Ariane</th><th scope="col">Aperçu</th></tr></thead>';
    echo '<tbody>';

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $page_id = get_the_ID();
            $titre_page = get_the_title();

            // Display row for the page
            echo '<tr data-id="' . $page_id . '">';
            echo '<td>' . $page_id . '</td>';
            echo '<td><input type="checkbox" name="valider[]" value="' . $page_id . '"></td>';
            echo '<td>' . $titre_page . '</td>';
            echo '<td><img src="' . SNAPSHOT_URL . '&selectorId=' . $page_id . '" width="100"></td>';
            echo '</tr>';

            // Fetch Elementor data
            $raw_elementor_data = get_post_meta($page_id, '_elementor_data', true);
            $elementor_data = json_decode($raw_elementor_data, true);

            // Loop through the data to find sections
            if (is_array($elementor_data)) {
                foreach ($elementor_data as $element) {
                    if (isset($element['elType']) && 'section' === $element['elType']) {
                        $section_title = 'Section sans titre';  // Default title

                        // Check if the section contains elements
                        if (isset($element['elements']) && is_array($element['elements'])) {
                            $title = find_first_title($element['elements']);
                            if ($title) {
                                $section_title = $title;
                            }
                        }

                        // Display row for the section
                        echo '<tr data-id="' . $page_id . '-' . $element['id'] . '">';
                        echo '<td>' . $element['id'] . '</td>';
                        echo '<td><input type="checkbox" name="valider_section[]" value="' . $element['id'] . '"></td>';
                        echo '<td>' . $titre_page . ' > ' . $section_title . '</td>';
                        echo '<td><img src="' . SNAPSHOT_URL  . '&selectorId=' . $element['id'] . '" width="100"></td>';
                        echo '</tr>';
                    }
                }
            }

            // Display row for the section
            echo '<tr data-id="' . $page_id . '-' . $element['id'] . '">';
            echo '<td>' . $element['id'] . '</td>';
            echo '<td><input type="checkbox" name="valider_section[]" value="' . $element['id'] . '"></td>';
            echo '<td>' . $titre_page . ' > ' . $section_title . '</td>';
            echo '</tr>';
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