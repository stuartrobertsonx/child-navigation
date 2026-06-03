<?php
/*
Plugin Name: Child Navigation
Version:     1.2
Description: Adds shortcode to list page and child or sibling pages
Author:      Stuart Robertson
Requires at least: 5.8
Requires PHP: 7.4
License:     GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: child-navigation
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    http_response_code(403);
    exit;
}

function get_excluded_pages() {
    $excluded_pages = array();
    $pages = get_pages();

    foreach ($pages as $page) {
        if (get_post_meta($page->ID, '_exclude_from_child_list', true)) {
            $excluded_pages[] = $page->ID;
        }
    }

    return $excluded_pages;
}


function render_child_navigation() {
    global $post;

    if (!isset($post) || empty($post->ID)) {
        return '';
    }

    $output = '';

    $child_pages = get_pages(array(
        'child_of'    => $post->ID,
        'parent'      => $post->ID,
        'sort_column' => 'post_title',
        'sort_order'  => 'ASC',
        'exclude'     => get_excluded_pages()
    ));

    if ($child_pages) {

        $output .= '<div class="custom-page-list"><div class="custom-page-title">';
        $output .= esc_html(get_the_title($post->ID));
        $output .= '</div><ul>';

        foreach ($child_pages as $page) {

            $custom_title = get_post_meta($page->ID, '_custom_child_title', true);
            $title = $custom_title ? $custom_title : get_the_title($page->ID);
            
            $class = ($page->ID == $post->ID)
                ? 'child-page current-page'
                : 'child-page';

            $output .= '<li class="' . esc_attr($class) . '">';
            $output .= '<a href="' . esc_url(get_permalink($page->ID)) . '">';
            $output .= esc_html($title);
            $output .= '</a>';
            $output .= '</li>';
        }

        $output .= '</ul></div>';

    } else {

        $parent_id = wp_get_post_parent_id($post->ID);

        if ($parent_id) {

            $sibling_pages = get_pages(array(
                'child_of'    => $parent_id,
                'parent'      => $parent_id,
                'sort_column' => 'post_title',
                'sort_order'  => 'ASC',
                'exclude'     => get_excluded_pages()
            ));

            if ($sibling_pages) {

                $output .= '<div class="custom-page-list"><div class="custom-page-title">';
                $output .= '<a href="' . esc_url(get_permalink($parent_id)) . '">' . esc_html(get_the_title($parent_id)) . '</a>';
                $output .= '</div><ul>';

                foreach ($sibling_pages as $page) {

                    $custom_title = get_post_meta($page->ID, '_custom_child_title', true);
                    $title = $custom_title ? $custom_title : get_the_title($page->ID);

                    $class = ($page->ID == $post->ID)
                        ? 'child-page current-page'
                        : 'child-page';

                    $output .= '<li class="' . esc_attr($class) . '">';
                    $output .= '<a href="' . esc_url(get_permalink($page->ID)) . '">';
                    $output .= esc_html($title);
                    $output .= '</a>';
                    $output .= '</li>';
                }

                $output .= '</ul></div>';
            }
        }
    }

    return $output;
}

add_shortcode('current_page_and_children', 'render_child_navigation');


function add_custom_meta_boxes() {
    add_meta_box(
        'custom_meta_box',
        'Side nav settings',
        'display_custom_meta_box',
        'page',
        'side',
        'high'
    );
}
add_action('add_meta_boxes', 'add_custom_meta_boxes');


function display_custom_meta_box($post) {
    $exclude_value = get_post_meta($post->ID, '_exclude_from_child_list', true);
    $custom_title  = get_post_meta($post->ID, '_custom_child_title', true);

    wp_nonce_field('custom_meta_box_nonce', 'custom_meta_box_nonce');
    ?>

    <label>
        <input type="checkbox" name="exclude_page" value="1" <?php checked($exclude_value, '1'); ?> />
        Exclude page from navigation
    </label>

    <br><br>

    <label>Custom Title:</label>
    <input type="text" name="custom_title" value="<?php echo esc_attr($custom_title); ?>" style="width:100%;" />

    <?php
}


function save_custom_meta_box($post_id) {
    if (!isset($_POST['custom_meta_box_nonce']) ||
        !wp_verify_nonce($_POST['custom_meta_box_nonce'], 'custom_meta_box_nonce')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (isset($_POST['exclude_page'])) {
        update_post_meta($post_id, '_exclude_from_child_list', '1');
    } else {
        delete_post_meta($post_id, '_exclude_from_child_list');
    }

    if (isset($_POST['custom_title'])) {
        update_post_meta(
            $post_id,
            '_custom_child_title',
            sanitize_text_field($_POST['custom_title'])
        );
    }
}
add_action('save_post', 'save_custom_meta_box');


function recent_posts_shortcode($atts) {

    $atts = shortcode_atts(array(
        'category' => '',
        'tag' => '',
        'posts' => 5,
        'heading' => 'Latest updates',
        'show_date' => 'true',
        'show_thumbnail' => 'true',
    ), $atts);

    $query = new WP_Query(array(
        'posts_per_page' => intval($atts['posts']),
        'category_name' => $atts['category'],
        'tag' => $atts['tag']
    ));

    $output = '';

    if ($query->have_posts()) {

        $output .= '<div class="custom-page-title">';
        $output .= esc_html($atts['heading']);

        while ($query->have_posts()) {
            $query->the_post();

            $output .= '<div class="child-page">';

            if ($atts['show_thumbnail'] === 'true' && has_post_thumbnail()) {
                $output .= get_the_post_thumbnail(get_the_ID(), 'thumbnail');
            }

            $output .= '<a href="' . esc_url(get_permalink()) . '">';
            $output .= esc_html(get_the_title());
            $output .= '</a>';

            if ($atts['show_date'] === 'true') {
                $output .= '<br><small>' . esc_html(get_the_date()) . '</small>';
            }

            $output .= '</div>';
        }

        $output .= '</div>';
    }

    wp_reset_postdata();

    return $output;
}
add_shortcode('recent_posts', 'recent_posts_shortcode');


function list_child_pages_shortcode($atts) {
    global $post;

    if (!isset($post)) return '';

    $atts = shortcode_atts(array(
        'exclude' => ''
    ), $atts);

    $exclude_ids = array_map('intval', explode(',', $atts['exclude']));

    $query = new WP_Query(array(
        'post_type' => 'page',
        'post_parent' => $post->ID,
        'post__not_in' => $exclude_ids,
        'orderby' => 'menu_order',
        'order' => 'ASC',
    ));

    $output = '<ul class="child-pages-list">';

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();

            $output .= '<li class="child-page">';
            $output .= '<a href="' . esc_url(get_permalink()) . '">';
            $output .= esc_html(get_the_title());
            $output .= '</a>';
            $output .= '</li>';
        }
    } else {
            $output .= '<li>No child pages found.</li>';
    }

    $output .= '</ul>';

    wp_reset_postdata();

    return $output;
}
add_shortcode('child_pages', 'list_child_pages_shortcode');
