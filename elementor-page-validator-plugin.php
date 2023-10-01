<?php
/**
 * Plugin Name: Elementor Page Validator Plugin
 * Description: This plugin enables all Elementor pages and blocks validation for a given website.
 * Version: 1.0
 * Author: Thomas Couderc
 */


 // website url
define ('WEBSITE_URL', get_site_url());
error_log("WEBSITE_URL : " . WEBSITE_URL);

// snapshot api url
define ('SNAPSHOT_API_DEFAULT', 'https://webshot-elzinko.vercel.app/api/webshot');
// if (!get_option('snapshot_api')) {
//     update_option('snapshot_api', SNAPSHOT_API_DEFAULT);
// }
error_log("SNAPSHOT_API URL : " . get_option('snapshot_api', SNAPSHOT_API_DEFAULT));

// debug mode
define('DEBUG', true);
error_log("DEBUG MODE: " . DEBUG);

if (!get_option('mock_snapshot')) {
    update_option('mock_snapshot', 'true');  // Utilisez update_option
}
define('MOCK_SNAPSHOT', get_option('mock_snapshot') === 'true');
error_log("MOCK_SNAPSHOT MODE : " . var_export(MOCK_SNAPSHOT, true));  // Utilisez var_export pour le débogage


// default screenshot url
if (!get_option('default_screenshot_url')) {
    update_option('default_screenshot_url', 'https://placehold.co/400/png');
}
define('DEFAULT_SCREENSHOT_URL',  get_option('default_screenshot_url'));
error_log("DEFAULT_SCREENSHOT_URL : " . DEFAULT_SCREENSHOT_URL);

// delete_option('mock_snapshot');
if (!get_option('force_update')) {
    update_option('force_update', 'false');  // Utilisez update_option
}
define('FORCE_UPDATE', get_option('force_update') === 'true');
error_log("FORCE_UPDATE MODE : " . var_export(FORCE_UPDATE, true)); 

define('SCREENSHOT_TMP_IMAGE', plugins_url('elementor-page-validator-plugin/assets/images/screenshot_tmp.svg'));

// Add menu and submenu in admin panel
add_action('admin_menu', 'add_menu_and_submenu_page_validator');

function enqueue_screenshots_script() {
    wp_enqueue_script('screenshots', plugin_dir_url(__FILE__) . 'assets/js/screenshots.js', array('jquery'), '1.0', true);
}

add_action('admin_enqueue_scripts', 'enqueue_screenshots_script');


function add_menu_and_submenu_page_validator() {
    add_menu_page('Page validator', 'Page validator', 'manage_options', 'page_validator', 'show_page_validator_plugin');
    add_submenu_page('page_validator', 'Settings', 'Settings', 'manage_options', 'page_validator_settings', 'show_page_validator_settings');
}


function request_image($request) {
    $body = $request->get_body();
    $params = json_decode($body, true);
    $data_url = isset($params['data_url']) ? $params['data_url'] : null;

    if ($data_url) {
        $task_id = uniqid();
        
        // Lancez le téléchargement de l'image ici (peut-être de manière asynchrone)
        // Vous pouvez utiliser wp_schedule_single_event pour exécuter download_image de manière asynchrone
        wp_schedule_single_event(time(), 'download_image_event', array('task_id' => $task_id, 'data_url' => $data_url));
        
        // Mettez à jour le statut de la tâche
        update_option('image_status_' . $task_id, 'pending');

        return new WP_REST_Response(['task_id' => $task_id], 200);
    } else {
        return new WP_REST_Response(['error' => 'data_url is missing'], 400);
    }
}


// Ajoutez cette action pour gérer l'événement planifié
add_action('download_image_event', 'download_image', 10, 2);

function download_image($task_id, $data_url) {
    // Utilisez wp_remote_get pour télécharger l'image
    $response = wp_remote_get($data_url);
    if (is_wp_error($response)) {
        // Gérer l'erreur
        update_option('image_status_' . $task_id, 'failed');
    } else {
        $image_data = wp_remote_retrieve_body($response);
        $upload = wp_upload_bits('screenshot_' . $task_id . '.png', null, $image_data);
        if (!$upload['error']) {
            $file_path = $upload['file'];
            $file_name = basename($file_path);
            $file_type = wp_check_filetype($file_name, null);
            $attachment = array(
                'post_mime_type' => $file_type['type'],
                'post_title' => sanitize_file_name($file_name),
                'post_content' => '',
                'post_status' => 'inherit'
            );

            $attachment_id = wp_insert_attachment($attachment, $file_path);
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attachment_data = wp_generate_attachment_metadata($attachment_id, $file_path);
            wp_update_attachment_metadata($attachment_id, $attachment_data);

            // Mettez à jour le statut de la tâche
            update_option('image_status_' . $task_id, 'completed');
            update_option('image_attachment_id_' . $task_id, $attachment_id);
        } else {
            update_option('image_status_' . $task_id, 'failed');
        }
    }
}

function check_image($request) {
    $task_id = $request['task_id'];
    $status = get_option('image_status_' . $task_id, 'pending');

    // Vérifiez l'état du téléchargement ici
    if ($status === 'completed') {
        $attachment_id = get_option('image_attachment_id_' . $task_id);
        $image_url = wp_get_attachment_url($attachment_id);
        return new WP_REST_Response(['status' => 'completed', 'image_url' => $image_url], 200);
    } else {
        return new WP_REST_Response(['status' => $status], 200);
    }
}

add_action('rest_api_init', function () {
    register_rest_route('epvp/v1', '/request-image/', array(
        'methods' => 'POST',
        'callback' => 'request_image',
    ));
});


add_action('rest_api_init', function () {
    register_rest_route('epvp/v1', '/check-image/(?P<task_id>\w+)', array(
        'methods' => 'GET',
        'callback' => 'check_image',
    ));
});




function find_first_title($element) {
    if (isset($element['elements']) && is_array($element['elements'])) {
        $elements = $element['elements'];
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
            update_option('use_auth', $_POST['use_auth']);
        }
        if (isset($_POST['username'])) {
            update_option('snapshot_username', sanitize_text_field($_POST['username']));
        }
        if (isset($_POST['password'])) {
            update_option('snapshot_password', sanitize_text_field($_POST['password']));
        }
        if (isset($_POST['mock_snapshot'])) {
            $mock_snapshot_value = $_POST['mock_snapshot'] === 'on' ? 'true' : 'false';
            update_option('mock_snapshot', $mock_snapshot_value);
            if ($mock_snapshot_value === 'true') {
                update_option('force_update', 'false'); // Réinitialiser force_update à false
            }
        } else {
            update_option('mock_snapshot', 'false');
        }        
        if (isset($_POST['force_update'])) {
            update_option('force_update', $_POST['force_update'] === 'on' ? 'true' : 'false');
        } else {
            update_option('force_update', 'false');
        }

        if (isset($_POST['default_screenshot_url'])) {
            update_option('default_screenshot_url', sanitize_text_field($_POST['default_screenshot_url']));
        }
        
        
    }

    // Fetch the snapshot_api and credentials from options
    $snapshot_api = get_option('snapshot_api', SNAPSHOT_API_DEFAULT);
    $use_auth = get_option('use_auth', 'false');
    $username = get_option('snapshot_username', '');
    $password = get_option('snapshot_password', '');
    $mock_snapshot = get_option('mock_snapshot', 'false');
    $force_update = get_option('force_update', 'false');
    $default_screenshot_url = get_option('default_screenshot_url', DEFAULT_SCREENSHOT_URL);

    echo '<div class="wrap">';
    echo '<h1>Settings</h1>';
    echo '<form method="post">';
    echo '<label for="snapshot_api">Snapshot API : </label>';
    echo '<input type="text" size="50" id="snapshot_api" name="snapshot_api" value="' . esc_attr($snapshot_api) . '"><br><br>';
    echo '<label for="website_url">Website URL : </label>';
    echo '<input type="text" id="website_url" name="website_url" value="' . esc_attr(WEBSITE_URL) . '" size="50" readonly><br>';
    echo '<br>';
    echo '<input type="checkbox" id="use_auth" name="use_auth" ' . ($use_auth === 'true' ? 'checked' : '') . '>';
    echo '<label for="use_auth">Use Authentication</label><br>';
    echo '<br>';
    echo '<div id="authFields" style="display:none;">';
    echo '<label for="username">Username: </label>';
    echo '<input type="text" id="username" name="username" value="' . esc_attr($username) . '"><br><br>';
    echo '<label for="password">Password: </label>';
    echo '<input type="password" id="password" name="password" value="' . esc_attr($password) . '"><br><br>';
    echo '</div>';
    echo '<br>';
    echo '<div id="snapshotFields"">';
    echo '<input type="checkbox" id="mock_snapshot" name="mock_snapshot" ' . ($mock_snapshot === 'true' ? 'checked' : '') . '>';
    echo '<label for="mock_snapshot">Mock snapshot</label></>';
    echo '</div>';

    echo '<div id="snapshotUrlFields"">';
    echo '<label for="default_screenshot_url">Default snapchot URL : </label>';
    echo '<input type="text" size="50" id="default_screenshot_url" name="default_screenshot_url" value="' . esc_attr($default_screenshot_url) . '"><br><br>';
    echo '</div>';
    echo '<br>';
    echo '<div id="forceUpdate"">';
    echo '<input type="checkbox" id="force_update" name="force_update" ' . ($force_update === 'true' ? 'checked' : '') . '>';
    echo '<label for="force_update">Force Update</label></>';
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

    function toggleForceUpdate() {
        if ($("#mock_snapshot").prop("checked")) {
            $("#forceUpdate").hide();
            $("#force_update").prop("checked", false); // Réinitialiser force_update à false
        } else {
            $("#forceUpdate").show();
        }
    }

    // Initial state
    toggleForceUpdate();

    // Toggle on change
    $("#mock_snapshot").change(function() {
        toggleForceUpdate();
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

function build_screenshot_link($page_url, $css_id, $fullpage = false) {
    if ($page_url === false || empty($page_url)) {
        return null;
    }

    $screenshot_link = $page_url;
    
    $use_auth = get_option('use_auth', 'false');
    if ($use_auth === 'true') {
        $username = get_option('snapshot_username', '');
        $password = get_option('snapshot_password', '');
        if (!empty($username) && !empty($password)) {
            $screenshot_link = "https://{$username}:{$password}@" . parse_url($page_url, PHP_URL_HOST) . parse_url($page_url, PHP_URL_PATH);
        }
    }

    if ($css_id) {
        $screenshot_link .= '&selectorId=' . $css_id;
    }

    if ($fullpage) {
        $screenshot_link .= '&fullpage';
    }
    
    return $screenshot_link;
    
}

function buildScreenshotUrlFromMock($page_id, $section_id = null) {
    $default_screenshot_url = DEFAULT_SCREENSHOT_URL;
    if ($section_id) {
        error_log("Use fake section screenshot");
        return $default_screenshot_url . '?text=Section+' . $section_id;
    } else {
        error_log("Use fake page screenshot");
        return $default_screenshot_url . '?text=Page+' . $page_id;
    }
}

function build_api_call_url($page_id, $section_id = null) {
    $snapshot_api = get_option('snapshot_api', SNAPSHOT_API_DEFAULT);
    $page_url = get_permalink($page_id);
    $target_url = $section_id? build_screenshot_link($page_url, $section_id) : build_screenshot_link($page_url, null, true);
    $screenshot_url = $snapshot_api .'?url=' . $target_url;
    return $screenshot_url;
}

function buildMetaKey($page_id, $section_id = null) {
    return $section_id ? $page_id . '_' . $section_id : $page_id;
}

function buildScreenshotUrlFromAPI($page_id, $section_id = null) {
    // Clé de métadonnées pour stocker l'ID de l'attachement
    $meta_key = buildMetaKey($page_id, $section_id);

    // Vérifier si l'image existe déjà en base
    $existing_attachment_id = get_post_meta($page_id, $meta_key, true);

    // Si l'image existe déjà ou que nous sommes en mode forcé de rafrachissement, on régénère l'url de l'image
    if (!$existing_attachment_id || (FORCE_UPDATE)) {
        
        // $page_url = get_permalink($page_id);
        // $target_url = $section_id? build_screenshot_link($page_url, $section_id, true) : build_screenshot_link($page_url, $section_id);
        $screenshot_url = build_api_call_url($page_id, $section_id);

        return $screenshot_url;

        // $response = wp_remote_get($screenshot_url);
        // if (is_wp_error($response)) {
        //     error_log("Erreur lors du téléchargement de l'image : " . $response->get_error_message());
        //     return DEFAULT_SCREENSHOT_URL . '?text=NOT+FOUND';
        // } else {
        //     $image_data = wp_remote_retrieve_body($response);
        //     $upload = wp_upload_bits('screenshot.jpg', null, $image_data);
        //     if (!$upload['error']) {
        //         $file_path = $upload['file'];
        //         $file_name = basename($file_path);
        //         $file_type = wp_check_filetype($file_name, null);
        //         $attachment = array(
        //             'post_mime_type' => $file_type['type'],
        //             'post_title' => sanitize_file_name($file_name),
        //             'post_content' => '',
        //             'post_status' => 'inherit'
        //         );

        //         // Si l'attachement existe déjà et que nous sommes en mode FORCE_UPDATE, supprimer l'ancien attachement
                if ($existing_attachment_id) {
                    wp_delete_attachment($existing_attachment_id, true);
                }

        //         $screenshot_id = wp_insert_attachment($attachment, $file_path, $page_id);
        //         require_once(ABSPATH . 'wp-admin/includes/image.php');
        //         $attachment_data = wp_generate_attachment_metadata($screenshot_id, $file_path);
        //         wp_update_attachment_metadata($screenshot_id, $attachment_data);
        //         update_post_meta($page_id, $meta_key, $screenshot_id);
        //         error_log("Image téléchargée et attachée avec succès. ID de l'attachement : " . $screenshot_id);

        //         // Retourner l'URL de l'attachement
        //         return wp_get_attachment_url($screenshot_id);
        //     }
        //     error_log("Erreur lors de l'upload de l'image : " . $upload['error']);
        //     return DEFAULT_SCREENSHOT_URL;
        // }
    } else {
        // Si l'image existe déjà et que nous ne sommes pas en mode FORCE_UPDATE, retourner l'URL de l'attachement existant
        return wp_get_attachment_url($existing_attachment_id);
    }
}

function getElementId($element) {
    if (isset($element['settings']['_element_id'])) {
        return $element['settings']['_element_id'];
    }
    if (isset($element['settings']['_id'])) {
        return $element['settings']['_id'];
    }
    if (isset($element['id'])) {
        return $element['id'];
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
    echo '<thead>
        <tr>
            <th scope="col">ID</th>
            <th scope="col">CSS ID</th>
            <th scope="col">Valider</th>
            <th scope="col">Fil d\'Ariane</th>
            <th scope="col">Aperçu</th>
        </tr>
    </thead>';
    echo '<tbody>';

    $getSnapshot = true;

    if ($query->have_posts()) {
        while ($query->have_posts()) {

            $query->the_post();
            $page_id = get_the_ID();
            $titre_page = get_the_title();
            $section_link = build_section_link($page_id, null);

            $screenshot_page_id = buildMetaKey($page_id);
            error_log("get page screenshot : $screenshot_page_id)");
            $screenshot_page_url = MOCK_SNAPSHOT ? buildScreenshotUrlFromMock($page_id) : buildScreenshotUrlFromAPI($page_id);
            error_log("screenshot_page_url : " . $screenshot_page_url);

            // Display row for the page
            echo '<tr data-id="' . $page_id . '">';
            echo '<td>' . $page_id . '</td>';
            echo '<td>NaN</td>';
            echo '<td><input type="checkbox" name="valider[]" value="' . $page_id . '"></td>';
            echo '<td><a href="' . $section_link . '" target = "_ blank">' . $titre_page . '</a></td>';
            echo '<td><img id="screenshot-' . $screenshot_page_id .'" data-url="' . $screenshot_page_url . '" src="' . esc_url(SCREENSHOT_TMP_IMAGE) . '" width="100"></td>';
            // echo '<td><img id="screenshot-' . $screenshot_page_id .'"src="' . esc_url(SCREENSHOT_TMP_IMAGE) . '" width="100"></td>';
            echo '</tr>';


            // Fetch Elementor data
            $raw_elementor_data = get_post_meta($page_id, '_elementor_data', true);
            $elementor_data = json_decode($raw_elementor_data, true);

            // Loop through the data to find sections
            if (is_array($elementor_data)) {
                foreach ($elementor_data as $element) {
                    if (isset($element['elType']) && 'section' === $element['elType']) {
                        $id = $element['id'];
                        $css_id = getElementId($element)? getElementId($element) : 'x';  
                        $section_id = $css_id? $css_id : $id;
                        $section_link = $css_id? build_section_link($page_id, $css_id) : $section_link;
                        $firstTitle = find_first_title($element);
                        $section_title = $firstTitle ? $firstTitle : 'Section sans titre';

                        $screenshot_section_id = buildMetaKey($page_id, $element['id']);
                        error_log("screenshot_section_id : " . $screenshot_section_id);
                        $screenshot_section_url = MOCK_SNAPSHOT ? buildScreenshotUrlFromMock($page_id, $section_id) : buildScreenshotUrlFromAPI($page_id, $section_id);
                        error_log("screenshot_section_url : " . $screenshot_section_url);

                        // Display row for the section
                        echo '<tr data-id="' . $page_id . '-' . $element['id'] . '">';
                        echo '<td>' . $section_id . '</td>';
                        echo '<td>' . $css_id . '</td>';
                        echo '<td><input type="checkbox" name="valider_section[]" value="' . $element['id'] . '"></td>';
                        if ($css_id) {
                            echo '<td><a href="' . $section_link . '" target = "_ blank">' . $titre_page . ' > ' . $section_title . '</a></td>';
                        } else {
                            echo '<td>' . $titre_page . ' > ' . $section_title . '</td>';
                        }
                        echo '<td><img id="screenshot-' . $screenshot_section_id .'" data-url="' . $screenshot_section_url . '" src="' . esc_url(SCREENSHOT_TMP_IMAGE) . '" width="100"></td>';

                        // echo '<td><img id="screenshot-' . $screenshot_section_id .'" width="100"></td>';
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
}
?>