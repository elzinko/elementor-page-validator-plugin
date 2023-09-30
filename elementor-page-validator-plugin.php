<?php
/**
 * Plugin Name: Elementor Page Validator Plugin
 * Description: This plugin enables all Elementor pages and blocks validation for a given website.
 * Version: 1.0
 * Author: Thomas Couderc
 */


// Initialize or get snapshot_api from WordPress options
if (!get_option('snapshot_api')) {
    add_option('snapshot_api', 'https://webshot-elzinko.vercel.app/api/webshot');
}
define ('SNAPSHOT_API', get_option('snapshot_api'));

define ('WEBSITE_URL', get_site_url());

// Add menu and submenu in admin panel
add_action('admin_menu', 'add_menu_and_submenu_page_validator');

// define MOCK_SNAPSHOT
if (!get_option('mock_snapshot')) {
    add_option('mock_snapshot', 'false');
}
define('MOCK_SNAPSHOT', get_option('mock_snapshot'));


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


function build_test_link() {
    
    $test_link = WEBSITE_URL;

    $use_auth = get_option('use_auth', 'false');
    if ($use_auth === 'true') {
        $username = get_option('snapshot_username', '');
        $password = get_option('snapshot_password', '');
        if (!empty($username) && !empty($password)) {
            $test_link = "https://{$username}:{$password}@" . parse_url($test_link, PHP_URL_HOST) . parse_url($test_link, PHP_URL_PATH);
        }
    }
    return $test_link;
    
}

function show_page_validator_settings() {
    // Handle form submission for settings
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['snapshot_api'])) {
            update_option('snapshot_api', sanitize_text_field($_POST['snapshot_api']));
        }
        if (isset($_POST['use_auth'])) {
            update_option('use_auth', 'true');
        } else {
            update_option('use_auth', 'false');
        }
        if (isset($_POST['username'])) {
            update_option('snapshot_username', sanitize_text_field($_POST['username']));
        }
        if (isset($_POST['password'])) {
            update_option('snapshot_password', sanitize_text_field($_POST['password']));
        }
        if (isset($_POST['mock_snapshot'])) {
            update_option('mock_snapshot', 'true');
        } else {
            update_option('mock_snapshot', 'false');
        }
        
    }

    // Fetch the snapshot_api and credentials from options
    $snapshot_api = get_option('snapshot_api', SNAPSHOT_API);
    $use_auth = get_option('use_auth', 'false');
    $username = get_option('snapshot_username', '');
    $password = get_option('snapshot_password', '');
    $mock_snapshot = get_option('mock_snapshot', 'false');

    echo '<div class="wrap">';
    echo '<h1>Settings</h1>';
    echo '<form method="post">';
    echo '<label for="snapshot_api">Snapshot API : </label>';
    echo '<input type="text" size="50" id="snapshot_api" name="snapshot_api" value="' . esc_attr($snapshot_api) . '"><br>';
    echo '<label for="website_url">Website url : </label>';
    echo '<input type="text" id="website_url" name="website_url" value="' . esc_attr(WEBSITE_URL) . '" size="50" readonly><br>';
    echo '<br>';
    echo '<input type="checkbox" id="use_auth" name="use_auth" ' . ($use_auth === 'true' ? 'checked' : '') . '>';
    echo '<label for="use_auth">Use Authentication</label><br>';
    echo '<br>';
    echo '<div id="authFields" style="display:none;">';
    echo '<label for="username">Username: </label>';
    echo '<input type="text" id="username" name="username" value="' . esc_attr($username) . '"><br>';
    echo '<label for="password">Password: </label>';
    echo '<input type="password" id="password" name="password" value="' . esc_attr($password) . '"><br>';
    echo '</div>';
    echo '<br>';
    echo '<div id="snapshotFields"">';
    echo '<input type="checkbox" id="mock_snapshot" name="mock_snapshot" ' . ($mock_snapshot === 'true' ? 'checked' : '') . '>';
    echo '<label for="mock_snapshot">Mock snapshot: </label></>';
    echo '</div>';
    echo '<br>';
    echo '<label id="testLinkLabel">Test Link : ' . build_test_link() . '</label>';
    echo '<br>';
    echo '<br>';
    echo '<input type="submit" value="Save">';
    echo '</form>';
    echo '</div>';

    echo '<script>
jQuery(document).ready(function($) {
    function toggleAuthFields() {
        if ($("#use_auth").prop("checked")) {
            $("#authFields").show();
        } else {
            $("#authFields").hide();
        }
    }

    // Initial state
    toggleAuthFields();

    // Toggle on change
    $("#use_auth").change(function() {
        toggleAuthFields();
    });

    function buildTestLink() {
        var websiteUrl = $("#website_url").val();
        var useAuth = $("#use_auth").prop("checked");
        var username = $("#username").val();
        var password = $("#password").val();
        // replace each character of password with * character
        password = password.replace(/./g, "*");
        
        if (useAuth && username && password) {
            var urlParts = websiteUrl.split("://");
            websiteUrl = urlParts[0] + "://" + username + ":" + password + "@" + urlParts[1];
        }
        
        return websiteUrl;
    }

    function updateTestLink() {
        $("#testLinkLabel").text("Test Link : " + buildTestLink());
    }

    // Initial state
    updateTestLink();

    // Update on change
    $("#username, #password, #use_auth").change(function() {
        updateTestLink();
    });
});
</script>';

}


function build_section_link($page_id, $css_id) {
    
    $section_link = get_permalink($page_id);

    if ($section_link === false) {
        return null;
    }

    $use_auth = get_option('use_auth', 'false');
    if ($use_auth === 'true') {
        $username = get_option('snapshot_username', '');
        $password = get_option('snapshot_password', '');
        if (!empty($username) && !empty($password)) {
            $section_link = "https://{$username}:{$password}@" . parse_url($section_link, PHP_URL_HOST) . parse_url($section_link, PHP_URL_PATH);
        }
    }
    if ($css_id) {
        $section_link .= '#' . $css_id;
    }
    return $section_link;
    
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
            $section_link = build_section_link($page_id, null);

            // Display row for the page
            echo '<tr data-id="' . $page_id . '">';
            echo '<td>' . $page_id . '</td>';
            echo '<td><input type="checkbox" name="valider[]" value="' . $page_id . '"></td>';
            echo '<td><a href="' . $section_link . '" target = "_ blank">' . $titre_page . '</a></td>';
            if (MOCK_SNAPSHOT === 'true') {
                if ($getSnapshot) {
                    echo '<td><img src="' . SNAPSHOT_API .'?url=' . WEBSITE_URL . '&fullpage" width="100"></td>';
                } else {
                    echo '<td><img src="https://picsum.photos/200" width="100"></td>';
                }
            } else {
                echo '<td><img src="' . SNAPSHOT_API .'?url=' . WEBSITE_URL . '&fullpage" width="100"></td>';
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
                            $section_link = build_section_link($page_id, $css_id);
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

                        if (MOCK_SNAPSHOT === 'true') {
                            if ($getSnapshot && $css_id) {
                                echo '<td><img src="' . SNAPSHOT_API .'?url=' . $section_link . '&selectorId=' . $css_id . '" width="100"></td>';
                                $getSnapshot = false;
                            } else {
                                echo '<td><img src="https://picsum.photos/200" width="100"></td>';
                            }
                        } else {
                            echo '<td><img src="' . SNAPSHOT_API .'?url=' . $section_link . '&selectorId=' . $css_id . '" width="100"></td>';
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