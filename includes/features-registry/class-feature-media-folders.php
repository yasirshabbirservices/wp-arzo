<?php

/**
 * Feature: Media Folders (free).
 *
 * Organise the media library into nestable folders using a private hierarchical
 * taxonomy on attachments. Adds a folder filter to the library list view, a
 * per-file folder selector on the attachment edit screen, and a bulk
 * "Move to folder" action. Purely additive — it never moves or deletes files,
 * only tags them, so it's safe to enable/disable at any time.
 *
 * @package WP_Arzo
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Arzo_Feature_Media_Folders extends WP_Arzo_Feature
{
    const TAX = 'wp_arzo_media_folder';

    public function id()
    {
        return 'media_folders';
    }
    public function title()
    {
        return 'Media Folders';
    }
    public function description()
    {
        return 'Organise the media library into nestable folders, with a library filter and per-file assignment.';
    }
    public function group()
    {
        return 'media';
    }
    public function icon()
    {
        return 'folder';
    }

    public function boot()
    {
        add_action('init', array($this, 'register_taxonomy'));
        add_action('restrict_manage_posts', array($this, 'library_filter'));
        add_filter('parse_query', array($this, 'apply_filter'));
        add_filter('attachment_fields_to_edit', array($this, 'attachment_field'), 10, 2);
        add_filter('attachment_fields_to_save', array($this, 'attachment_save'), 10, 2);
        add_filter('bulk_actions-upload', array($this, 'bulk_actions'));
        add_filter('handle_bulk_actions-upload', array($this, 'handle_bulk'), 10, 3);
    }

    public function register_taxonomy()
    {
        register_taxonomy(self::TAX, 'attachment', array(
            'labels' => array(
                'name'          => 'Media Folders',
                'singular_name' => 'Media Folder',
                'menu_name'     => 'Media Folders',
                'all_items'     => 'All Folders',
                'add_new_item'  => 'Add Folder',
            ),
            'public'            => false,
            'hierarchical'      => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'rewrite'           => false,
            'show_in_menu'      => false, // surfaced via the media screens, not a top menu
        ));
    }

    /** Folder dropdown on the media library list view. */
    public function library_filter($post_type = 'attachment')
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->base !== 'upload') {
            return;
        }
        $terms = get_terms(array('taxonomy' => self::TAX, 'hide_empty' => false));
        if (is_wp_error($terms)) {
            return;
        }
        $current = isset($_GET[self::TAX]) ? sanitize_text_field(wp_unslash($_GET[self::TAX])) : '';
        echo '<select name="' . esc_attr(self::TAX) . '"><option value="">All folders</option>';
        foreach ($terms as $t) {
            echo '<option value="' . esc_attr($t->slug) . '" ' . selected($current, $t->slug, false) . '>'
                . esc_html($t->name) . ' (' . (int) $t->count . ')</option>';
        }
        echo '</select>';
    }

    public function apply_filter($query)
    {
        if (!is_admin() || empty($_GET[self::TAX])) {
            return $query;
        }
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && $screen->base === 'upload' && !empty($query->query_vars)) {
            $query->query_vars[self::TAX] = sanitize_text_field(wp_unslash($_GET[self::TAX]));
        }
        return $query;
    }

    /** Folder selector on the attachment edit panel. */
    public function attachment_field($fields, $post)
    {
        $terms = get_terms(array('taxonomy' => self::TAX, 'hide_empty' => false));
        $assigned = wp_get_object_terms($post->ID, self::TAX, array('fields' => 'ids'));
        $assigned = is_wp_error($assigned) ? array() : $assigned;
        $html = '<select name="attachments[' . $post->ID . '][wp_arzo_folder]"><option value="">— None —</option>';
        if (!is_wp_error($terms)) {
            foreach ($terms as $t) {
                $sel = in_array($t->term_id, $assigned, true) ? ' selected' : '';
                $html .= '<option value="' . (int) $t->term_id . '"' . $sel . '>' . esc_html($t->name) . '</option>';
            }
        }
        $html .= '</select>';
        $fields['wp_arzo_folder'] = array('label' => 'Folder', 'input' => 'html', 'html' => $html);
        return $fields;
    }

    public function attachment_save($post, $attachment)
    {
        if (isset($attachment['wp_arzo_folder'])) {
            $term_id = (int) $attachment['wp_arzo_folder'];
            wp_set_object_terms($post['ID'], $term_id ? array($term_id) : array(), self::TAX, false);
        }
        return $post;
    }

    public function bulk_actions($actions)
    {
        $terms = get_terms(array('taxonomy' => self::TAX, 'hide_empty' => false));
        if (!is_wp_error($terms)) {
            foreach ($terms as $t) {
                $actions['wp_arzo_folder_' . $t->term_id] = 'Move to: ' . $t->name;
            }
        }
        return $actions;
    }

    public function handle_bulk($redirect, $action, $ids)
    {
        if (strpos($action, 'wp_arzo_folder_') !== 0) {
            return $redirect;
        }
        $term_id = (int) substr($action, strlen('wp_arzo_folder_'));
        if ($term_id && !empty($ids)) {
            foreach ((array) $ids as $id) {
                wp_set_object_terms((int) $id, array($term_id), self::TAX, false);
            }
        }
        return $redirect;
    }
}
