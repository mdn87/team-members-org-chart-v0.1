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


// Callback function to display the Team Members Manager Page
function tmoc_manage_members_page() {
    ?>
    <div class="wrap">
        <h1>Manage Team Members</h1>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Job Title</th>
                    <th>Image</th>
                    <th>Order</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
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
                        $title = get_the_title();
                        $job_title = get_post_meta($id, '_tmoc_job_title', true);
                        $image = get_post_meta($id, '_tmoc_image', true);
                        $order = get_post_meta($id, '_tmoc_order', true);

                        echo '<tr>';
                        echo '<td>' . esc_html($title) . '</td>';
                        echo '<td>' . esc_html($job_title) . '</td>';
                        echo '<td><img src="' . esc_url($image) . '" style="width:50px; height:50px; border-radius:50%;" /></td>';
                        echo '<td style="text-align:center;"><strong>' . esc_html($order) . '</strong></td>';
                        echo '<td>';
                        echo '<button class="button tmoc-move-member" data-id="' . $id . '" data-direction="up">⬆</button>';
                        echo '<button class="button tmoc-move-member" data-id="' . $id . '" data-direction="down">⬇</button>';
                        echo '</td>';
                        echo '</tr>';
                    }
                    wp_reset_postdata();
                } else {
                    echo '<tr><td colspan="5">No team members found.</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
    <?php
}


// Callbacks for settings fields
function tmoc_sort_order_callback() {
    $sort_order = get_option('tmoc_sort_order', 'name');
    ?>
    <select name="tmoc_sort_order">
        <option value="name" <?php selected($sort_order, 'name'); ?>>Sort by Name</option>
        <option value="job_title" <?php selected($sort_order, 'job_title'); ?>>Sort by Job Title</option>
        <option value="manual" <?php selected($sort_order, 'manual'); ?>>Manual Order</option>
    </select>
    <?php
}

function tmoc_members_per_row_callback() {
    $members_per_row = get_option('tmoc_members_per_row', 3);
    ?>
    <input type="number" name="tmoc_members_per_row" value="<?php echo esc_attr($members_per_row); ?>" min="1" max="10" />
    <?php
}

// Add meta box for additional fields
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

// Meta box callback function
function tmoc_team_member_meta_box_callback($post) {
    $job_title = get_post_meta($post->ID, '_tmoc_job_title', true);
    $bio = get_post_meta($post->ID, '_tmoc_bio', true);
    $image = get_post_meta($post->ID, '_tmoc_image', true);
    $order = get_post_meta($post->ID, '_tmoc_order', true); // Fix this line

    echo '<label for="tmoc_job_title">Job Title:</label>';
    echo '<input type="text" id="tmoc_job_title" name="tmoc_job_title" value="' . esc_attr($job_title) . '" style="width:100%;" />';
    
    echo '<label for="tmoc_bio">Bio:</label>';
    echo '<textarea id="tmoc_bio" name="tmoc_bio" rows="4" style="width:100%;">' . esc_textarea($bio) . '</textarea>';
    
    echo '<label for="tmoc_image">Profile Image:</label>';
    echo '<input type="text" id="tmoc_image" name="tmoc_image" value="' . esc_attr($image) . '" style="width:80%;" />';
    echo '<button type="button" class="button tmoc_upload_image_button">Select from Media Library</button>';
    
    echo '<label for="tmoc_order">Display Order:</label>';
    echo '<input type="number" id="tmoc_order" name="tmoc_order" value="' . esc_attr($order) . '" style="width:100%;" />';
}


// Save the custom fields
function tmoc_save_team_member_meta($post_id) {
    if (array_key_exists('tmoc_job_title', $_POST)) {
        update_post_meta($post_id, '_tmoc_job_title', sanitize_text_field($_POST['tmoc_job_title']));
    }
    if (array_key_exists('tmoc_bio', $_POST)) {
        update_post_meta($post_id, '_tmoc_bio', sanitize_textarea_field($_POST['tmoc_bio']));
    }
    if (array_key_exists('tmoc_image', $_POST)) {
        update_post_meta($post_id, '_tmoc_image', sanitize_text_field($_POST['tmoc_image']));
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

// Script for admin page live updates
// Load JS and localize AJAX URL
function tmoc_enqueue_admin_scripts($hook) {
    error_log("🟢 Admin scripts hook triggered on: " . $hook);

    // Load admin.js if we're on any Team Members-related page
    if (strpos($hook, 'tmoc') !== false) {
        wp_enqueue_script('tmoc-admin-js', plugin_dir_url(__FILE__) . 'admin.js', array('jquery'), null, true);
        wp_localize_script('tmoc-admin-js', 'tmoc_ajax', array('ajax_url' => admin_url('admin-ajax.php')));

        error_log("🚀 Enqueued admin.js successfully!");
    } else {
        error_log("❌ Skipping script enqueue, not on plugin page.");
    }
}
add_action('admin_enqueue_scripts', 'tmoc_enqueue_admin_scripts');


// Change team order with AJAX request
function tmoc_reorder_members() {
    if (!isset($_POST['post_id']) || !isset($_POST['direction'])) {
        error_log("❌ Missing parameters in AJAX request.");
        wp_send_json_error("Missing parameters.");
    }

    $post_id = intval($_POST['post_id']);
    $direction = sanitize_text_field($_POST['direction']);
    $current_order = get_post_meta($post_id, '_tmoc_order', true);

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

        // Swap orders
        update_post_meta($post_id, '_tmoc_order', $swap_order);
        update_post_meta($swap_post_id, '_tmoc_order', $current_order);
    } else {
        error_log("⚠️ No adjacent post found to swap with.");
    }

    wp_send_json_success("Order updated.");
    error_log("✅ Order update completed.");
}
add_action('wp_ajax_tmoc_reorder_members', 'tmoc_reorder_members');



