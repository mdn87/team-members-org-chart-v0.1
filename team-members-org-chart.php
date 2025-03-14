<?php
/*
Plugin Name: Team Members Org Chart
Description: Adds a team members section and displays an interactive org chart.
Version: 0.1
Author: Matt N
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

error_log('--- TEAM MEMBERS ORG CHART PLUGIN LOADED ---');

if (!function_exists('add_action')) {
    error_log('❌ WordPress core functions are not loaded!');
    return;
} else {
    error_log('✅ WordPress core is loaded, proceeding...');
}

// Hook into Elementor's widget registration process.
add_action('elementor/widgets/register', 'tmoc_register_widget');

function tmoc_register_widget( $widgets_manager ) {
    // Include the widget file now—this hook runs only on Elementor pages.
    require_once( trailingslashit( plugin_dir_path(__FILE__) ) . 'team-members-widget.php' );
    clearstatcache();
    
    error_log('🚀 Registering Team Members Org Chart Widget');

    // Register our widget.
    if ( class_exists( 'TMOC\Team_Members_Org_Chart_Widget' ) ) {
        error_log('✅ Registering Team Members Org Chart Widget.');
        $widgets_manager->register( new \TMOC\Team_Members_Org_Chart_Widget() );
    } else {
        error_log('❌ TMOC\Team_Members_Org_Chart_Widget class not found.');
    }
}

function tmoc_enqueue_elementor_scripts() {
    // Ensure this only runs when Elementor's editor is active
    if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
        wp_enqueue_script('jquery'); // Ensure jQuery is loaded
        error_log("🚀 Enqueuing Elementor scripts... (team-members-org-chart.php)");
    } else {
        error_log("❌ Elementor scripts not enqueued. (team-members-org-chart.php)");
    }
}
add_action('elementor/editor/before_enqueue_scripts', 'tmoc_enqueue_elementor_scripts');


// Add settings page
function tmoc_add_settings_page() {
    add_menu_page(
        'Team Members Settings',
        'Team Members',
        'manage_options',
        'tmoc-settings',
        'tmoc_settings_page',
        'dashicons-admin-generic'
    );
}
add_action('admin_menu', 'tmoc_add_settings_page');

// Settings page callback
function tmoc_settings_page() {
    ?>
    <div class="wrap">
        <h1>Team Members Plugin Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('tmoc_settings_group');
            do_settings_sections('tmoc-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Register settings
function tmoc_register_settings() {
    register_setting('tmoc_settings_group', 'tmoc_sort_order');
    register_setting('tmoc_settings_group', 'tmoc_members_per_row');

    add_settings_section('tmoc_main_section', 'Main Settings', null, 'tmoc-settings');

    add_settings_field(
        'tmoc_sort_order',
        'Sort Order',
        'tmoc_sort_order_callback',
        'tmoc-settings',
        'tmoc_main_section'
    );

    add_settings_field(
        'tmoc_members_per_row',
        'Members Per Row',
        'tmoc_members_per_row_callback',
        'tmoc-settings',
        'tmoc_main_section'
    );
}
add_action('admin_init', 'tmoc_register_settings');

// Register Custom Post Type for Team Members
function tmoc_register_team_members_cpt() {
    $args = array(
        'public' => true,
        'label'  => 'Team Members',
        'menu_icon' => 'dashicons-groups',
        'supports' => array('title', 'editor', 'thumbnail', 'custom-fields'),
    );
    register_post_type('team_member', $args);
}
add_action('init', 'tmoc_register_team_members_cpt');

// Add Team Members Management Page
function tmoc_add_team_members_manager_page() {
    add_submenu_page(
        'tmoc-settings', // Parent menu slug (uses the main settings page as the parent)
        'Manage Team Members',
        'Manage Members',
        'manage_options',
        'tmoc-manage-members',
        'tmoc_manage_members_page'
    );
}
add_action('admin_menu', 'tmoc_add_team_members_manager_page');

// --------- Duplicate a team member --------- //
function tmoc_duplicate_team_member() {
    if (!isset($_GET['post']) || !isset($_GET['_wpnonce'])) {
        wp_die('Invalid request');
    }

    $post_id = absint($_GET['post']);

    if (!wp_verify_nonce($_GET['_wpnonce'], 'tmoc_duplicate_' . $post_id)) {
        wp_die('Nonce verification failed');
    }

    // Get the original post
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'team_member') {
        wp_die('Invalid post type');
    }

    // Prepare duplicate post data
    $new_post_data = array(
        'post_title'  => $post->post_title . ' (Copy)',
        'post_content'=> $post->post_content,
        'post_status' => 'draft', // Save as draft initially
        'post_type'   => 'team_member',
    );

    // Insert the duplicated post
    $new_post_id = wp_insert_post($new_post_data);

    if ($new_post_id) {
        // Copy post meta
        $meta = get_post_meta($post_id);
        foreach ($meta as $key => $values) {
            foreach ($values as $value) {
                update_post_meta($new_post_id, $key, $value);
            }
        }

        // Redirect back
        wp_redirect(admin_url('edit.php?post_type=team_member'));
        exit;
    } else {
        wp_die('Error duplicating post.');
    }
}
add_action('admin_action_tmoc_duplicate_member', 'tmoc_duplicate_team_member');

// Add duplicate link to admin post list
function tmoc_add_duplicate_link($actions, $post) {
    if ($post->post_type === 'team_member') {
        $duplicate_url = wp_nonce_url(
            admin_url('admin.php?action=tmoc_duplicate_member&post=' . $post->ID),
            'tmoc_duplicate_' . $post->ID
        );

        $actions['duplicate'] = '<a href="' . esc_url($duplicate_url) . '" title="Duplicate this item">Duplicate</a>';
    }
    return $actions;
}
add_filter('post_row_actions', 'tmoc_add_duplicate_link', 10, 2);
// --------- End - Duplicate a team member --------- //

// Set the initial order of team members to be chronological
function tmoc_set_initial_order() {
    $args = array(
        'post_type'      => 'team_member',
        'posts_per_page' => -1,
        'orderby'        => 'date', // Default to chronological order
        'order'          => 'ASC',
    );
    $query = new WP_Query($args);

    if ($query->have_posts()) {
        $order = 1;
        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();
            if (get_post_field('menu_order', $post_id) == 0) { 
                // Only update posts that have menu_order = 0
                wp_update_post(array('ID' => $post_id, 'menu_order' => $order));
                $order++;
            }
        }
        wp_reset_postdata();
    }
}
register_activation_hook(__FILE__, 'tmoc_set_initial_order');

// ----------------- TEAM MEMBER MANAGER PAGE SETTINGS ----------------- //

// Callback function to display the Team Members Manager Page
function tmoc_manage_members_page() {
    // Get current sort order from the request or default to ascending order
    $current_sort_order = isset($_GET['sort']) ? sanitize_text_field($_GET['sort']) : 'asc';
    ?>
    <div class="wrap">
        <h1>Manage Team Members</h1>

        <h2>CSV Import / Export</h2>
        <form method="post" enctype="multipart/form-data" action="<?php echo admin_url('admin-post.php'); ?>">
            <?php wp_nonce_field('tmoc_import_csv', 'tmoc_import_nonce'); ?>
            <input type="hidden" name="action" value="tmoc_import_csv">

            <h2>Import Team Members</h2>
            <p>Upload a CSV file in the correct format.</p>
            <input type="file" name="tmoc_csv_file" required>
            <select name="import_type">
                <option value="add">Add as New Members</option>
                <option value="overwrite">Overwrite All Members</option>
            </select>
            <p><input type="submit" class="button button-primary" value="Upload CSV"></p>
        </form>

        <h2>Download CSV Templates</h2>
        <a href="<?php echo admin_url('admin-post.php?action=tmoc_download_template'); ?>" class="button">Download Blank CSV</a>
        <a href="<?php echo admin_url('admin-post.php?action=tmoc_export_csv'); ?>" class="button button-primary">Export Members</a>

        <h2>Team Members</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Unique ID</th>
                    <th>Name</th>
                    <th>Job Title</th>
                    <th>Rank</th>
                    <th>Image</th>
                    <th>
                        Order
                        <button class="tmoc-sort" data-sort="asc"><span class="dashicons dashicons-arrow-up-alt2"></span></button>
                        <button class="tmoc-sort" data-sort="desc"><span class="dashicons dashicons-arrow-down-alt2"></span></button>
                    </th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php

                tmoc_render_members_table();

                ?>
            </tbody>
        </table>
    </div>
    <?php
}
// ----------------- CSV Import / Export ----------------- //
// Download blank template CSV
function tmoc_download_template() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Unauthorized access.', 'plugin-name'));
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=team-members-template.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Name', 'Job Title', 'Rank', 'Image URL', 'Bio', 'Order']);

    fclose($output);
    exit;
}
add_action('admin_post_tmoc_download_template', 'tmoc_download_template');


// Export team members to CSV
function tmoc_export_csv() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Unauthorized access.', 'plugin-name'));
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=team-members-export.csv');

    $output = fopen('php://output', 'w');

    // ✅ Standard header row (always 6 columns)
    fputcsv($output, ['Name', 'Job Title', 'Rank', 'Image URL', 'Bio', 'Order']);

    $args = array(
        'post_type'      => 'team_member',
        'posts_per_page' => -1,
        'orderby'        => 'meta_value_num',
        'meta_key'       => '_tmoc_order',
        'order'          => 'ASC',
    );
    $query = new WP_Query($args);

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $id = get_the_ID();
            $name = get_the_title();
            $job_title = get_post_meta($id, '_tmoc_job_title', true) ?: '';
            $rank = get_post_meta($id, '_tmoc_rank', true) ?: '';
            $image_url = get_post_meta($id, '_tmoc_image', true) ?: '';
            $bio = get_post_meta($id, '_tmoc_bio', true) ?: '';
            $order = get_post_meta($id, '_tmoc_order', true) ?: '';

            // ✅ Ensure all 6 columns are present
            fputcsv($output, [$name, $job_title, $rank, $image_url, $bio, $order]);
        }
        wp_reset_postdata();
    }

    fclose($output);
    exit;
}
add_action('admin_post_tmoc_export_csv', 'tmoc_export_csv');


// Import team members from CSV
function tmoc_import_csv() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Unauthorized access.', 'plugin-name'));
    }

    if (!isset($_FILES['tmoc_csv_file']) || $_FILES['tmoc_csv_file']['error'] !== UPLOAD_ERR_OK) {
        wp_die(__('Error uploading file.', 'plugin-name'));
    }

    $file = $_FILES['tmoc_csv_file']['tmp_name'];

    if (($handle = fopen($file, 'r')) !== FALSE) {
        $headers = fgetcsv($handle); // ✅ Read and discard the header row

        // ✅ Validate expected headers (must match export headers)
        $expected_headers = ['Name', 'Job Title', 'Rank', 'Image URL', 'Bio', 'Order'];
        if ($headers !== $expected_headers) {
            fclose($handle);
            wp_die(__('Invalid CSV format. Please use the provided template.', 'plugin-name'));
        }

        $row_count = 0;
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $row_count++;
            error_log("🔍 Processing row #$row_count: " . print_r($data, true));

            if (count($data) < 6) {
                error_log("⚠️ Skipping row #$row_count: Not enough columns.");
                continue;
            }

            $name       = sanitize_text_field($data[0]);
            $job_title  = sanitize_text_field($data[1]);
            $rank       = sanitize_text_field($data[2]);
            $image_url  = esc_url_raw($data[3]);
            $bio        = sanitize_textarea_field($data[4]);
            $order      = intval($data[5]);

            if (empty($name)) {
                error_log("⚠️ Skipping row #$row_count: No name provided.");
                continue;
            }

            $post_id = wp_insert_post([
                'post_title'   => $name,
                'post_type'    => 'team_member',
                'post_status'  => 'publish',
            ]);

            if ($post_id) {
                update_post_meta($post_id, '_tmoc_job_title', $job_title);
                update_post_meta($post_id, '_tmoc_rank', $rank);
                update_post_meta($post_id, '_tmoc_image', $image_url);
                update_post_meta($post_id, '_tmoc_bio', $bio);
                update_post_meta($post_id, '_tmoc_order', $order);
                error_log("✅ Successfully added: $name (ID: $post_id)");
            } else {
                error_log("❌ Failed to insert post for $name.");
            }
        }
        fclose($handle);
    }

    wp_redirect(admin_url('admin.php?page=tmoc-manage-members&import_success=true'));
    exit;
}
add_action('admin_post_tmoc_import_csv', 'tmoc_import_csv');


 // ----------------- END - CSV Import / Export ----------------- //

// ----------------- Sorting Members (w/ AJAX) ----------------- //
function tmoc_render_members_table($order = 'ASC') {
    $args = array(
        'post_type'      => 'team_member',
        'posts_per_page' => -1,
        'meta_key'       => '_tmoc_order',
        'orderby'        => 'meta_value_num',
        'order'          => $order,
    );

    $query = new WP_Query($args);

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $id = get_the_ID();
            $title = get_the_title();
            $rank = get_post_meta($id, '_tmoc_rank', true);
            $job_title = get_post_meta($id, '_tmoc_job_title', true);
            $image = get_post_meta($id, '_tmoc_image', true);
            $order = get_post_meta($id, '_tmoc_order', true);

            echo '<tr>';
            echo '<td style="text-align:center;">' . esc_html($id) . '</td>';
            echo '<td>' . esc_html($title) . '</td>';
            echo '<td>' . esc_html($job_title) . '</td>';
            echo '<td>' . esc_html($rank) . '</td>';
            echo '<td><img src="' . esc_url($image) . '" style="width:50px; height:50px; border-radius:50%;" /></td>';
            echo '<td style="text-align:center;"><strong>' . esc_html($order) . '</strong></td>';
            echo '<td>';
            echo '<button class="button tmoc-move-member" data-id="' . $id . '" data-direction="up"><span class="dashicons dashicons-arrow-up-alt2"></span></button>';
            echo '<button class="button tmoc-move-member" data-id="' . $id . '" data-direction="down"><span class="dashicons dashicons-arrow-down-alt2"></span></button>';
            echo '<a href="' . esc_url(get_edit_post_link($id)) . '" class="button button-primary">Edit</a>';
            echo '</td>';
            echo '</tr>';
        }
        wp_reset_postdata();
    } else {
        echo '<tr><td colspan="6">No team members found.</td></tr>';
    }
}

function tmoc_sort_members_ajax() {
    if (!isset($_POST['sort'])) {
        wp_send_json_error("Missing sort parameter.");
    }

    $sort_order = sanitize_text_field($_POST['sort']);
    $order = ($sort_order === 'desc') ? 'DESC' : 'ASC';

    error_log("🔄 Sorting members by Order: $order");

    ob_start();
    tmoc_render_members_table($order);
    $html = ob_get_clean();

    wp_send_json_success(['html' => $html]);
}
add_action('wp_ajax_tmoc_sort_members', 'tmoc_sort_members_ajax');


// ----------------- TEAM MEMBER CUSTOM POST SETTINGS ----------------- //
// Add Meta Box for additional fields
function tmoc_add_team_member_meta_box() {
    add_meta_box(
        'tmoc_team_member_details',
        'Team Member Details',
        'tmoc_team_member_meta_box_callback',
        'team_member',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'tmoc_add_team_member_meta_box');

// Meta Box Callback - on display show the form fields
function tmoc_team_member_meta_box_callback($post) {
    $id = $post->ID;
    $job_title = get_post_meta($post->ID, '_tmoc_job_title', true);
    $bio = get_post_meta($post->ID, '_tmoc_bio', true);
    $rank = get_post_meta($post->ID, '_tmoc_rank', true);
    $image = get_post_meta($post->ID, '_tmoc_image', true);
    $image_fit = get_post_meta($post->ID, '_tmoc_image_fit', true) ?: 'cover';
    $image_x = get_post_meta($post->ID, '_tmoc_image_x', true) ?: '0';
    $image_y = get_post_meta($post->ID, '_tmoc_image_y', true) ?: '0';
    $image_scale = get_post_meta($post->ID, '_tmoc_image_scale', true) ?: '1';

    echo '<label for="tmoc_id">Unique ID:</label>';
    echo '<input type="text" id="tmoc_id" name="tmoc_id" value="' . esc_attr($post->ID) . '" style="width:100%;" readonly />';

    echo '<label for="tmoc_job_title">Job Title:</label>';
    echo '<input type="text" id="tmoc_job_title" name="tmoc_job_title" value="' . esc_attr($job_title) . '" style="width:100%;" />';

    echo '<label for="tmoc_bio">Bio:</label>';
    echo '<textarea id="tmoc_bio" name="tmoc_bio" rows="4" style="width:100%;">' . esc_textarea($bio) . '</textarea>';

    echo '<label for="tmoc_rank">Org. Rank:</label>';
    echo '<input type="number" id="tmoc_rank" name="tmoc_rank" value="' . esc_attr($rank) . '" style="width:100%;" />';

    echo '<label for="tmoc_image">Profile Image:</label>';
    echo '<input type="text" id="tmoc_image" name="tmoc_image" value="' . esc_attr($image) . '" style="width:80%;" />';
    echo '<button type="button" class="button tmoc_upload_image_button">Select from Media Library</button>';

    echo '<label for="tmoc_image_fit">Image Fit:</label>';
    echo '<select id="tmoc_image_fit" name="tmoc_image_fit">
            <option value="cover" ' . selected($image_fit, 'cover', false) . '>Cover</option>
            <option value="contain" ' . selected($image_fit, 'contain', false) . '>Contain</option>
            <option value="fill" ' . selected($image_fit, 'fill', false) . '>Fill</option>
            <option value="none" ' . selected($image_fit, 'none', false) . '>None</option>
          </select>';

    // Image Position X (with step buttons)
    echo '<br />';
    echo '<label for="tmoc_image_x">Image Position X (px):</label>';
    echo '<div class="tmoc-flex-input">';
    echo '<button type="button" class="tmoc-step-btn" data-target="tmoc_image_x" data-step="-1">-</button>';
    echo '<input type="number" id="tmoc_image_x" name="tmoc_image_x" value="' . esc_attr($image_x) . '" step="1" />';
    echo '<button type="button" class="tmoc-step-btn" data-target="tmoc_image_x" data-step="1">+</button>';
    echo '</div>';

    // Image Position Y (with step buttons)
    echo '<label for="tmoc_image_y">Image Position Y (px):</label>';
    echo '<div class="tmoc-flex-input">';
    echo '<button type="button" class="tmoc-step-btn" data-target="tmoc_image_y" data-step="-1">-</button>';
    echo '<input type="number" id="tmoc_image_y" name="tmoc_image_y" value="' . esc_attr($image_y) . '" step="1" />';
    echo '<button type="button" class="tmoc-step-btn" data-target="tmoc_image_y" data-step="1">+</button>';
    echo '</div>';

    // Image Scale (with step buttons)
    echo '<label for="tmoc_image_scale">Image Scale:</label>';
    echo '<div class="tmoc-flex-input">';
    echo '<button type="button" class="tmoc-step-btn" data-target="tmoc_image_scale" data-step="-0.1">-</button>';
    echo '<input type="number" id="tmoc_image_scale" name="tmoc_image_scale" value="' . esc_attr($image_scale) . '" step="0.1" min="0.5" max="2" />';
    echo '<button type="button" class="tmoc-step-btn" data-target="tmoc_image_scale" data-step="0.1">+</button>';
    echo '</div>';

    // **Live Image Preview**
    echo '<div id="tmoc_image_preview" style="margin-top: 15px; width: 120px; height: 120px; border-radius: 50%; overflow: hidden; display: flex; align-items: center; justify-content: center; border: 2px solid #ddd; background-size: ' . esc_attr($image_fit) . '; background-position: ' . esc_attr($image_x) . 'px ' . esc_attr($image_y) . 'px; background-image: url(' . esc_url($image) . ');">';
    if (!empty($image)) {
        echo '<img id="tmoc_preview_img" src="' . esc_url($image) . '" style="width:100%; height:100%; object-fit: ' . esc_attr($image_fit) . '; transform: scale(' . esc_attr($image_scale) . '); object-position: ' . esc_attr($image_x) . 'px ' . esc_attr($image_y) . 'px;" />';
    }
    echo '</div>';
}

// Save the custom fields
function tmoc_save_team_member_meta($post_id) {
    if (array_key_exists('tmoc_job_title', $_POST)) {
        update_post_meta($post_id, '_tmoc_job_title', sanitize_text_field($_POST['tmoc_job_title']));
    }
    if (array_key_exists('tmoc_bio', $_POST)) {
        update_post_meta($post_id, '_tmoc_bio', sanitize_textarea_field($_POST['tmoc_bio']));
    }
    if (array_key_exists('tmoc_rank', $_POST)) {
        update_post_meta($post_id, '_tmoc_rank', intval($_POST['tmoc_rank']));
    }
    if (array_key_exists('tmoc_image', $_POST)) {
        update_post_meta($post_id, '_tmoc_image', sanitize_text_field($_POST['tmoc_image']));
    }
    if (array_key_exists('tmoc_image_fit', $_POST)) {
        update_post_meta($post_id, '_tmoc_image_fit', sanitize_text_field($_POST['tmoc_image_fit']));
    }
    if (array_key_exists('tmoc_image_x', $_POST)) {
        update_post_meta($post_id, '_tmoc_image_x', sanitize_text_field($_POST['tmoc_image_x']));
    }
    if (array_key_exists('tmoc_image_y', $_POST)) {
        update_post_meta($post_id, '_tmoc_image_y', sanitize_text_field($_POST['tmoc_image_y']));
    }
    if (array_key_exists('tmoc_image_scale', $_POST)) {
        update_post_meta($post_id, '_tmoc_image_scale', floatval($_POST['tmoc_image_scale']));
    }

    // Assign default order if not set
    if (get_post_meta($post_id, '_tmoc_order', true) === '') {
        $last_order = get_posts(array(
            'post_type'      => 'team_member',
            'posts_per_page' => 1,
            'orderby'        => 'meta_value_num',
            'meta_key'       => '_tmoc_order',
            'order'          => 'DESC',
            'fields'         => 'ids',
        ));
        $new_order = !empty($last_order) ? get_post_meta($last_order[0], '_tmoc_order', true) + 1 : 1;
        update_post_meta($post_id, '_tmoc_order', $new_order);
    }
}
add_action('save_post', 'tmoc_save_team_member_meta');


// Set default order for team members on plugin activation
function tmoc_set_default_order() {
    $args = array(
        'post_type'      => 'team_member',
        'posts_per_page' => -1,
        'orderby'        => 'date', // Default chronological order
        'order'          => 'ASC',
    );
    $query = new \WP_Query($args);

    if ($query->have_posts()) {
        $order = 1;
        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();
            $existing_order = get_post_meta($post_id, '_tmoc_order', true);

            // Only assign order if it's missing or zero
            if (empty($existing_order) || $existing_order == 0) {
                update_post_meta($post_id, '_tmoc_order', $order);
                error_log("🔄 Assigned order $order to post ID: $post_id");
                $order++;
            }
        }
        wp_reset_postdata();
    } else {
        error_log("⚠️ No team members found to assign order.");
    }
}

// Run this function when the plugin initializes
add_action('init', 'tmoc_set_default_order');

// Remove the editor for the "team_member" post type
function tmoc_hide_editor() {
    global $pagenow;
    
    // Only remove the editor for the "team_member" post type
    if (get_post_type() === 'team_member' && in_array($pagenow, ['post.php', 'post-new.php'])) {
        remove_post_type_support('team_member', 'editor');
        add_post_type_support('bio', 'editor');
        add_post_type_support('team_image', 'thumbnail');
        error_log('🌻 Customized team_member post type options');
    }
}
add_action('admin_head', 'tmoc_hide_editor');


// ----------------- ENQUEUE SCRIPTS ----------------- //
// Script for admin page live updates
// Load JS and localize AJAX URL
function tmoc_enqueue_admin_scripts($hook) {
    error_log("🟢 Admin scripts hook triggered on: " . $hook);
    wp_enqueue_script('jquery'); // Ensure jQuery loads first

    // Load on manage members page specifically
    if ($hook === 'team-member_page_tmoc-manage-members') {
        wp_enqueue_script('tmoc-admin-js', plugin_dir_url(__FILE__) . 'js/admin.js', array('jquery'), null, true);
        wp_localize_script('tmoc-admin-js', 'tmoc_ajax', array('ajax_url' => admin_url('admin-ajax.php')));
        error_log("🚀 Enqueued admin scripts on manage page.");
    }
    // Load admin.js if we're on any Team Members-related page
    if (strpos($hook, 'tmoc') !== false) {
        wp_enqueue_script('tmoc-admin-js', plugin_dir_url(__FILE__) . 'admin.js', array('jquery'), null, true);
        wp_localize_script('tmoc-admin-js', 'tmoc_ajax', array('ajax_url' => admin_url('admin-ajax.php')));

        error_log("🚀 Enqueued admin.js successfully!");
        wp_enqueue_script('tmoc-interactive-js', plugin_dir_url(__FILE__) . 'interactive.js', array('jquery'), null, true);
        wp_localize_script('tmoc-interactive-js', 'tmoc_ajax', array('ajax_url' => admin_url('admin-ajax.php')));

        error_log("🚀 Enqueued interactive.js successfully!");
    } else {
        error_log("❌ Skipping script enqueue, not on plugin page.");
    }
    if ('post.php' === $hook || 'post-new.php' === $hook) {
        wp_enqueue_media(); // Enables WordPress media uploader
        wp_enqueue_script('tmoc-admin-js', plugin_dir_url(__FILE__) . 'admin.js', array('jquery'), null, true);
    }
}
add_action('admin_enqueue_scripts', 'tmoc_enqueue_admin_scripts');


// Change team order (UP/DOWN) with AJAX request
function tmoc_reorder_members() {
    error_log("🟢 AJAX Request Received");

    if (!isset($_POST['post_id']) || !isset($_POST['direction'])) {
        error_log("❌ Missing Parameters");
        wp_send_json_error("Missing parameters.");
    }

    error_log("✅ Processing Order Change for Post ID: " . $_POST['post_id'] . " | Direction: " . $_POST['direction']);

    // Validate required parameters
    if (!isset($_POST['post_id']) || !isset($_POST['direction'])) {
        error_log("❌ Missing parameters in AJAX request.");
        wp_send_json_error("Missing parameters.");
    }

    $post_id = intval($_POST['post_id']);
    $direction = sanitize_text_field($_POST['direction']);
    $current_order = get_post_meta($post_id, '_tmoc_order', true);

    if ($current_order === '') {
        error_log("⚠️ Post ID: $post_id is missing `_tmoc_order`. Assigning default.");
        $current_order = intval(get_post_field('menu_order', $post_id));
        update_post_meta($post_id, '_tmoc_order', $current_order);
    }

    error_log("🔄 Reordering post ID: $post_id (current order: $current_order) in direction: $direction");

    // Find the adjacent member to swap places with
    $args = array(
        'post_type'      => 'team_member',
        'posts_per_page' => 1,
        'meta_key'       => '_tmoc_order',
        'orderby'        => 'meta_value_num',
        'order'          => ($direction === 'up') ? 'DESC' : 'ASC',
        'meta_query'     => array(
            array(
                'key'     => '_tmoc_order',
                'value'   => $current_order,
                'compare' => ($direction === 'up') ? '<' : '>',
                'type'    => 'NUMERIC',
            ),
        ),
    );

    $query = new WP_Query($args);
    error_log("🔍 Found " . $query->post_count . " posts for swapping.");

    if ($query->have_posts()) {
        $query->the_post();
        $swap_post_id = get_the_ID();
        $swap_order = get_post_meta($swap_post_id, '_tmoc_order', true);

        error_log("✅ Swapping post ID: $post_id (order: $current_order) with post ID: $swap_post_id (order: $swap_order)");

        // Swap the `_tmoc_order` meta field
        update_post_meta($post_id, '_tmoc_order', $swap_order);
        update_post_meta($swap_post_id, '_tmoc_order', $current_order);

        // Also update WordPress menu_order for proper sorting in the admin panel
        wp_update_post(array('ID' => $post_id, 'menu_order' => $swap_order));
        wp_update_post(array('ID' => $swap_post_id, 'menu_order' => $current_order));
    } else {
        error_log("⚠️ No adjacent post found to swap with.");
    }

    wp_reset_postdata();
    wp_send_json_success("Order updated.");
    error_log("✅ Order update completed.");
}
add_action('wp_ajax_tmoc_reorder_members', 'tmoc_reorder_members');

// Load live widget interaction
function tmoc_enqueue_interactive_scripts() {
    wp_enqueue_script('tmoc-interactive-js', plugin_dir_url(__FILE__) . 'interactive.js', array('jquery'), null, true);
    error_log("🚀 Enqueued interactive.js successfully!");
}
add_action('wp_enqueue_scripts', 'tmoc_enqueue_interactive_scripts');

// Load widget styles
function tmoc_enqueue_widget_styles() {
    // Always enqueue styles
    wp_enqueue_style('tmoc-widget-css', plugin_dir_url(__FILE__) . 'widget.css', array(), null);
    error_log("✅ Enqueued widget.css for Elementor editing and frontend (team-members-org-chart.php)");
}
add_action('elementor/frontend/after_enqueue_styles', 'tmoc_enqueue_widget_styles'); // For Elementor
add_action('wp_enqueue_scripts', 'tmoc_enqueue_widget_styles'); // For frontend

function tmoc_enqueue_gsap() {
    wp_enqueue_script('gsap', 'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js', array(), null, true);
    wp_enqueue_script('cssruleplugin', 'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/CSSRulePlugin.min.js', array(), null, true);
    error_log("✅ Enqueued GSAP for animation (team-members-widget.php)");
}
add_action('wp_enqueue_scripts', 'tmoc_enqueue_gsap');
