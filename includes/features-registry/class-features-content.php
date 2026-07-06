<?php

/**
 * Free content features: Page/Post Duplication, Missed Schedule Fix, SVG Upload.
 *
 * @package WP_Arzo
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Arzo_Feature_Duplicate_Posts extends WP_Arzo_Feature
{
    public function id()
    {
        return 'duplicate_posts';
    }
    public function title()
    {
        return 'Page & Post Duplication';
    }
    public function description()
    {
        return 'Add a one-click “Duplicate” link to posts, pages and custom post types.';
    }
    public function group()
    {
        return 'content';
    }
    public function icon()
    {
        return 'copy';
    }
    public function boot()
    {
        add_filter('post_row_actions', array($this, 'row_action'), 10, 2);
        add_filter('page_row_actions', array($this, 'row_action'), 10, 2);
        add_action('admin_action_wp_arzo_duplicate', array($this, 'duplicate'));
    }
    public function row_action($actions, $post)
    {
        if (current_user_can('edit_posts')) {
            $url = wp_nonce_url(
                admin_url('admin.php?action=wp_arzo_duplicate&post=' . $post->ID),
                'wp_arzo_duplicate_' . $post->ID
            );
            $actions['wp_arzo_duplicate'] = '<a href="' . esc_url($url) . '">' . esc_html__('Duplicate', 'arzo-administration-suite') . '</a>';
        }
        return $actions;
    }
    public function duplicate()
    {
        $id = isset($_GET['post']) ? (int) $_GET['post'] : 0;
        check_admin_referer('wp_arzo_duplicate_' . $id);
        if (!current_user_can('edit_posts')) {
            wp_die('You do not have permission to duplicate content.');
        }
        $post = get_post($id);
        if (!$post) {
            wp_die('Original item not found.');
        }

        $new_id = wp_insert_post(array(
            'post_title'     => $post->post_title . ' (copy)',
            'post_content'   => $post->post_content,
            'post_excerpt'   => $post->post_excerpt,
            'post_status'    => 'draft',
            'post_type'      => $post->post_type,
            'post_author'    => get_current_user_id(),
            'post_parent'    => $post->post_parent,
            'menu_order'     => $post->menu_order,
            'comment_status' => $post->comment_status,
            'ping_status'    => $post->ping_status,
        ));

        if (!is_wp_error($new_id) && $new_id) {
            foreach (get_object_taxonomies($post->post_type) as $tax) {
                $terms = wp_get_object_terms($id, $tax, array('fields' => 'slugs'));
                wp_set_object_terms($new_id, $terms, $tax, false);
            }
            foreach (get_post_meta($id) as $meta_key => $values) {
                if ($meta_key === '_wp_old_slug') {
                    continue;
                }
                foreach ($values as $value) {
                    add_post_meta($new_id, $meta_key, maybe_unserialize($value));
                }
            }
        }

        wp_safe_redirect(admin_url('edit.php?post_type=' . $post->post_type));
        exit;
    }
}

class WP_Arzo_Feature_Missed_Schedule extends WP_Arzo_Feature
{
    public function id()
    {
        return 'missed_schedule';
    }
    public function title()
    {
        return 'Missed Schedule Fix';
    }
    public function description()
    {
        return 'Automatically publish scheduled posts that WordPress missed (stuck on “Missed schedule”).';
    }
    public function group()
    {
        return 'content';
    }
    public function icon()
    {
        return 'clock';
    }
    public function boot()
    {
        add_action('wp_loaded', array($this, 'check'));
    }
    public function check()
    {
        // Throttle so we don't query on every request.
        if (get_transient('wp_arzo_missed_check')) {
            return;
        }
        set_transient('wp_arzo_missed_check', 1, 15 * MINUTE_IN_SECONDS);

        global $wpdb;
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'future' AND post_date_gmt <= %s LIMIT 25",
            gmdate('Y-m-d H:i:s')
        ));
        foreach ((array) $ids as $id) {
            wp_publish_post((int) $id);
        }
    }
}

class WP_Arzo_Feature_SVG_Upload extends WP_Arzo_Feature
{
    public function id()
    {
        return 'svg_upload';
    }
    public function title()
    {
        return 'SVG Upload';
    }
    public function description()
    {
        return 'Allow administrators to upload SVG files, with basic sanitization on upload.';
    }
    public function group()
    {
        return 'media';
    }
    public function icon()
    {
        return 'image';
    }
    public function boot()
    {
        add_filter('upload_mimes', array($this, 'mimes'));
        add_filter('wp_check_filetype_and_ext', array($this, 'check_filetype'), 10, 4);
        add_filter('wp_handle_upload_prefilter', array($this, 'sanitize_upload'));
    }
    public function mimes($mimes)
    {
        if (current_user_can('manage_options')) {
            $mimes['svg']  = 'image/svg+xml';
            $mimes['svgz'] = 'image/svg+xml';
        }
        return $mimes;
    }
    public function check_filetype($data, $file, $filename, $mimes)
    {
        if (substr(strtolower($filename), -4) === '.svg') {
            $data['ext']  = 'svg';
            $data['type'] = 'image/svg+xml';
        }
        return $data;
    }
    public function sanitize_upload($file)
    {
        if (!isset($file['type']) || $file['type'] !== 'image/svg+xml' || empty($file['tmp_name'])) {
            return $file;
        }
        $svg = file_get_contents($file['tmp_name']);
        $clean = $this->basic_sanitize_svg($svg);
        if ($clean === false) {
            $file['error'] = 'This SVG was rejected because it contains script or unsafe content.';
        } else {
            file_put_contents($file['tmp_name'], $clean);
        }
        return $file;
    }
    /**
     * Lightweight SVG sanitization (admins only). Rejects scripts; strips event
     * handlers and javascript: URLs. For untrusted multi-author sites, use a
     * dedicated SVG sanitizer library.
     *
     * @return string|false
     */
    private function basic_sanitize_svg($svg)
    {
        if (stripos($svg, '<script') !== false || stripos($svg, '<!ENTITY') !== false) {
            return false;
        }
        $svg = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $svg);
        $svg = preg_replace('/(href|xlink:href)\s*=\s*("|\')\s*javascript:[^"\']*("|\')/i', '', $svg);
        return $svg;
    }
}
