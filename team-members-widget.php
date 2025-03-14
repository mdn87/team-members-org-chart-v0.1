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
    public function get_style_depends() {
        return ['tmoc-widget-css']; // This tells Elementor to enqueue 'widget.css'
    }   
    
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
                'label' => __('Instance specific settings', 'plugin-name'),
                'tab' => Controls_Manager::TAB_CONTENT,
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
                'default' => get_option('tmoc_columns', 4),
                'selectors' => [
                    '{{WRAPPER}} .team-members-container' => ' --columns: {{VALUE}};',
                ],
                'render_type' => 'template', // Forces Elementor to re-render the widget
            ]
        );
        
        $this->add_control(
            'gap',
            [
                'label' => __('Gap Between Members', 'plugin-name'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', '%'],
                'range' => [
                    'px' => ['min' => 0, 'max' => 50],
                ],
                'default' => [
                    'size' => get_option('tmoc_gap', 20), // Ensure a global default
                    'unit' => 'px',
                ],
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
                'default' => get_option('tmoc_show_focus_on_load', 'no'),
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

    // --------------- Render Widget for Display ----------------- //
    protected function render() {
        $settings = $this->get_settings_for_display();
        $columns = isset($settings['columns']) ? $settings['columns'] : 3;
        $gap = isset($settings['gap']['size']) ? $settings['gap']['size'] . $settings['gap']['unit'] : '20px';
        error_log("🔄 Rendering Widget: Columns = $columns | Gap = $gap");

        echo '<div class="team-members-container" data-columns="' . esc_attr($columns) . '" data-gap="' . esc_attr($gap) . '">';
        
        $args = array(
            'post_type'      => 'team_member',
            'posts_per_page' => -1,
            'orderby'        => 'meta_value_num',
            'meta_key'       => '_tmoc_order',
            'order'          => 'ASC',
        );
        $query = new \WP_Query($args);
    
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $id = get_the_ID();
                $title = get_the_title();
                $order = get_post_meta($id, '_tmoc_order', true) ?: 9999;
                $job_title = get_post_meta($id, '_tmoc_job_title', true);
                $bio = get_post_meta($id, '_tmoc_bio', true);
                $image = get_post_meta($id, '_tmoc_image', true);
                $object_fit = get_post_meta($id, '_tmoc_image_fit', true) ?: 'cover';
                $position_x = get_post_meta($id, '_tmoc_image_x', true) ?: '50%';
                $position_y = get_post_meta($id, '_tmoc_image_y', true) ?: '50%';

                error_log("✅ Post ID: $id | Order: " . get_post_meta($id, '_tmoc_order', true));
    
                /* --- hard coded css to override styles --- //
                echo '<div class="team-member" 
                    data-id="' . esc_attr($id) . '" 
                    style="flex: 0 1 calc(100% / ' . esc_attr($columns) . ' - ' . esc_attr($gap) . '); max-width: calc(100% / ' . esc_attr($columns) . ' - ' . esc_attr($gap) . ');">';
                    error_log("🛠 Rendering Member ID: " . $id);
                */
                echo '<div class="team-member" data-id="' . esc_attr($id) . '">';
                echo '<div class="team-member-image" style="width: 100px; height: 100px; border-radius: 50%; overflow: hidden; background-size: ' . esc_attr($object_fit) . '; background-position: ' . esc_attr($position_x) . ' ' . esc_attr($position_y) . '; background-image: url(' . esc_url($image) . ');"></div>';
                echo '<h3>' . esc_html($title) . '</h3>';
                echo '<p>' . esc_html($job_title) . '</p>';
                echo '<div class="team-member-bio" style="display: none; margin-top: 10px; font-size: 14px;">' . esc_html($bio) . '</div>';
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
    $widget = new Team_Members_Org_Chart_Widget();
    $widgets_manager->register($widget);
    
    // Ensure styles load
    $widget->enqueue_styles();
    error_log('Registering team members widget successfully');
}
add_action('elementor/widgets/widgets_registered', 'TMOC\register_team_members_org_chart_widget');

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

function tmoc_enqueue_widget_styles() {
    // Always enqueue styles
    wp_enqueue_style('tmoc-widget-css', plugin_dir_url(__FILE__) . 'widget.css', array(), null);
    error_log("✅ Enqueued widget.css for Elementor editing and frontend (team-members-widget.php)");
}
add_action('elementor/frontend/after_enqueue_styles', 'tmoc_enqueue_widget_styles'); // For Elementor
add_action('wp_enqueue_scripts', 'tmoc_enqueue_widget_styles'); // For frontend

function tmoc_enqueue_gsap() {
    wp_enqueue_script('gsap', 'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js', array(), null, true);
    wp_enqueue_script('cssruleplugin', 'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/CSSRulePlugin.min.js', array(), null, true);
    error_log("✅ Enqueued GSAP for animation (team-members-widget.php)");
}
add_action('wp_enqueue_scripts', 'tmoc_enqueue_gsap');









