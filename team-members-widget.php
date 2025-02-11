<?php
namespace TMOC;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Debug log to check Elementor widget registration
add_action('elementor/widgets/register', function() {
    error_log('Elementor Widgets Loaded');
});

error_log('🚀 Loading the latest version of team-members-widget.php');

// Ensure Elementor is loaded before registering the widget
function check_elementor_loaded() {
    if (!did_action('elementor/loaded')) {
        error_log('Elementor not loaded yet');
        return;
    }
    error_log('Elementor is loaded, registering widget');
}
add_action('plugins_loaded', 'check_elementor_loaded');

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

// main widget class
class Team_Members_Org_Chart_Widget extends Widget_Base {
    public function get_name() {
        return 'team_members_org_chart';
    }

    public function get_title() {
        return __('Team Members Org Chart', 'plugin-name');
    }

    public function get_icon() {
        return 'eicon-person';
    }

    public function get_categories() {
        return ['general'];
    }

    protected function _register_controls() {
        $this->start_controls_section(
            'content_section',
            [
                'label' => __('Content', 'plugin-name'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'sort_order',
            [
                'label' => __('Sort Order', 'plugin-name'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'manual' => __('Manual', 'plugin-name'),
                    'date_desc' => __('Date Descending', 'plugin-name'),
                    'date_asc' => __('Date Ascending', 'plugin-name'),
                    'name' => __('Name', 'plugin-name'),
                    'title' => __('Title', 'plugin-name'),
                    'rank' => __('Rank', 'plugin-name'),
                ],
                'default' => isset($this->get_settings_for_display()['sort_order']) 
                    ? $this->get_settings_for_display()['sort_order'] 
                    : get_option('tmoc_sort_order', 'manual'),
            ]
        );

        $this->add_control(
            'columns',
            [
                'label' => __('Columns per Row', 'plugin-name'),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 6,
                'step' => 1,
                'default' => isset($this->get_settings_for_display()['columns']) 
                    ? $this->get_settings_for_display()['columns'] 
                    : get_option('tmoc_columns', 3),
            ]
        );

        $this->add_control(
            'card_style',
            [
                'label' => __('Card Style', 'plugin-name'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'style1' => __('Style 1', 'plugin-name'),
                    'style2' => __('Style 2', 'plugin-name'),
                ],
                'default' => isset($this->get_settings_for_display()['card_style']) 
                    ? $this->get_settings_for_display()['card_style'] 
                    : get_option('tmoc_card_style', 'style1'),

            ]
        );

        $this->add_control(
            'hover_style',
            [
                'label' => __('Hover Style', 'plugin-name'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'shadow' => __('Shadow', 'plugin-name'),
                    'grow' => __('Grow', 'plugin-name'),
                ],
                'default' => isset($this->get_settings_for_display()['hover_style']) 
                    ? $this->get_settings_for_display()['hover_style'] 
                    : get_option('tmoc_hover_style', 'shadow'),

            ]
        );

        $this->add_control(
            'focus_style',
            [
                'label' => __('Focus Style', 'plugin-name'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'modal' => __('Modal', 'plugin-name'),
                    'anchor' => __('Anchor Top and Grow', 'plugin-name'),
                ],
                'default' => isset($this->get_settings_for_display()['focus_style']) 
                    ? $this->get_settings_for_display()['focus_style'] 
                    : get_option('tmoc_focus_style', 'modal'),

            ]
        );

        $this->add_control(
            'show_focus_on_load',
            [
                'label' => __('Show Focus on Load', 'plugin-name'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'plugin-name'),
                'label_off' => __('No', 'plugin-name'),
                'return_value' => 'yes',
                'default' => isset($this->get_settings_for_display()['show_focus_on_load']) 
                    ? $this->get_settings_for_display()['show_focus_on_load'] 
                    : get_option('tmoc_show_focus_on_load', 'no'),
            ]
        );

        $this->add_control(
            'reset_to_global',
            [
                'label' => __('Reset to Global Defaults', 'plugin-name'),
                'type' => Controls_Manager::BUTTON,
                'button_type' => 'default',
                'event' => 'reset_to_global_defaults',
            ]
        );        

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $columns = $settings['columns'];
        $gap = $settings['gap']['size'] . $settings['gap']['unit'];
        echo '<div class="team-members-container" style="display: flex; flex-wrap: wrap; gap: ' . esc_attr($gap) . '; justify-content: center;">';

        // Ensure order is valid; if first post has order 0, reset all orders
        $first_post = get_posts([
            'post_type'      => 'team_member',
            'posts_per_page' => 1,
            'orderby'        => 'meta_value_num',
            'meta_key'       => '_tmoc_order',
            'order'          => 'ASC',
            'fields'         => 'ids',
        ]);
        
        if (!empty($first_post)) {
            $first_order = get_post_meta($first_post[0], '_tmoc_order', true);
            if ($first_order == 0 || empty($first_order)) {
                $all_posts = get_posts([
                    'post_type'      => 'team_member',
                    'posts_per_page' => -1,
                    'orderby'        => 'date',
                    'order'          => 'ASC',
                ]);
                $order = 1;
                foreach ($all_posts as $post) {
                    update_post_meta($post->ID, '_tmoc_order', $order);
                    $order++;
                }
            }
        }

        echo '<div class="team-members-container" style="display: flex; flex-wrap: wrap; gap: ' . esc_attr($gap) . '; justify-content: center;">';

        $args = array(
            'post_type'      => 'team_member',
            'posts_per_page' => -1,
            'orderby'        => 'meta_value_num',
            'meta_key'       => '_tmoc_order',
            'order'          => 'ASC',
        );
        $query = new \WP_Query($args);

        error_log("🔍 Querying team members: Found " . $query->found_posts . " members.");

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $id = get_the_ID();
                $title = get_the_title();
                $job_title = get_post_meta($id, '_tmoc_job_title', true);
                $bio = get_post_meta($id, '_tmoc_bio', true);
                $image = get_post_meta($id, '_tmoc_image', true);
                $object_fit = get_post_meta($id, '_tmoc_image_fit', true) ?: 'cover';
                $position_x = get_post_meta($id, '_tmoc_image_x', true) ?: '50%';
                $position_y = get_post_meta($id, '_tmoc_image_y', true) ?: '50%';

                echo '<div class="team-member" style="width: calc(100% / ' . esc_attr($columns) . ' - ' . esc_attr($gap) . '); text-align: center; border: 2px solid #ddd; padding: 10px;">';
                echo '<div style="width: 100px; height: 100px; border-radius: 50%; overflow: hidden; display: flex; align-items: center; justify-content: center; background-size: ' . esc_attr($object_fit) . '; background-position: ' . esc_attr($position_x) . ' ' . esc_attr($position_y) . '; background-image: url(' . esc_url($image) . ');"></div>';
                echo '<h3>' . esc_html($title) . '</h3>';
                echo '<p>' . esc_html($job_title) . '</p>';
                echo '</div>';
            }
            wp_reset_postdata();
        } else {
            echo '<p>No team members found.</p>';
        }

        echo '</div>';
    }
}

// Register Widget
function register_team_members_org_chart_widget($widgets_manager) {
    error_log('Attempting to register team members widget');
    if (!did_action('elementor/loaded') || !class_exists('\Elementor\Widget_Base')) {
        error_log('Elementor not loaded or class Widget_Base not found');
        return;
    }
    error_log('Registering team members widget successfully');
    $widgets_manager->register(new Team_Members_Org_Chart_Widget());
}
add_action('elementor/widgets/register', 'register_team_members_org_chart_widget');

// Save default settings on plugin activation
function tmoc_set_default_options() {
    if (get_option('tmoc_columns') === false) {
        add_option('tmoc_columns', 3);
    }
    if (get_option('tmoc_gap') === false) {
        add_option('tmoc_gap', 20);
    }

    // Ensure _tmoc_order is set for all team members
    $args = array(
        'post_type'      => 'team_member',
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'ASC',
    );
    $query = new \WP_Query($args);

    error_log ("🔍 Set default values on plugin activation.");
    
    $order = 1;
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();
            if (empty(get_post_meta($post_id, '_tmoc_order', true))) {
                update_post_meta($post_id, '_tmoc_order', $order);
                error_log("🔄 Assigned new order $order to post ID: $post_id");
                $order++;
            }
        }
    }
}
register_activation_hook(__FILE__, 'tmoc_set_default_options');









