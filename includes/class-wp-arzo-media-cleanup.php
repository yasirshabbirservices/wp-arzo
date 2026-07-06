<?php

/**
 * WP Arzo Media Cleanup scanner.
 *
 * Scans attachments (in batches, for progress) and flags ones with no detectable
 * usage. "Used" is detected conservatively — featured image, site logo/icon, a
 * reference in any post_content (URL or wp-image-<id> class), or in postmeta
 * (ACF / page builders) — and the match is intentionally broad so we err toward
 * "used" and never flag a live file as unused.
 *
 * Detection can't be 100% (theme options, hard-coded CSS, external caches), so the
 * UI labels results "possibly unused" and deletion is always explicit + confirmed.
 *
 * @package WP_Arzo
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Arzo_Media_Cleanup
{
    /** @var WP_Arzo_Media_Cleanup|null */
    private static $instance = null;

    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /** Total number of attachments. */
    public function count_attachments()
    {
        $counts = wp_count_posts('attachment');
        return isset($counts->inherit) ? (int) $counts->inherit : 0;
    }

    /**
     * Scan a batch of attachments.
     *
     * @return array[] one row per attachment with usage info.
     */
    public function scan_batch($offset, $limit)
    {
        $ids = get_posts(array(
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => $limit,
            'offset'         => $offset,
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ));

        $rows = array();
        foreach ($ids as $id) {
            $file = get_attached_file($id);
            $size = ($file && file_exists($file)) ? (int) filesize($file) : 0;
            list($used, $reason) = $this->usage($id, $file);
            $rows[] = array(
                'id'       => $id,
                'title'    => get_the_title($id) !== '' ? get_the_title($id) : '(no title)',
                'url'      => wp_get_attachment_url($id),
                'thumb'    => $this->thumb($id),
                'filename' => $file ? basename($file) : '',
                'mime'     => get_post_mime_type($id),
                'size'     => $size,
                'date'     => get_the_date('Y-m-d', $id),
                'used'     => (bool) $used,
                'reason'   => $reason,
            );
        }
        return $rows;
    }

    private function thumb($id)
    {
        if (wp_attachment_is_image($id)) {
            $src = wp_get_attachment_image_url($id, 'thumbnail');
            if ($src) {
                return $src;
            }
        }
        return '';
    }

    /**
     * @return array{0:bool,1:string} [used, reason]
     */
    private function usage($id, $file)
    {
        global $wpdb;

        // Featured image for some post.
        $thumb_of = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND meta_value = %d LIMIT 1",
            $id
        ));
        if ($thumb_of) {
            return array(true, 'Featured image');
        }

        // Site logo / icon.
        if ((int) get_theme_mod('custom_logo') === (int) $id) {
            return array(true, 'Site logo');
        }
        if ((int) get_option('site_icon') === (int) $id) {
            return array(true, 'Site icon');
        }

        // Gutenberg adds a wp-image-<id> class regardless of size.
        $class_like = '%' . $wpdb->esc_like('wp-image-' . $id) . '%';
        $in_class = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE ID <> %d AND post_status NOT IN ('trash','auto-draft') AND post_content LIKE %s LIMIT 1",
            $id,
            $class_like
        ));
        if ($in_class) {
            return array(true, 'Used in content');
        }

        // Reference by filename slug (catches sized variants + builder/meta storage).
        $slug = $file ? pathinfo($file, PATHINFO_FILENAME) : '';
        if ($slug !== '') {
            $like = '%' . $wpdb->esc_like($slug) . '%';

            $in_content = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE ID <> %d AND post_status NOT IN ('trash','auto-draft') AND post_content LIKE %s LIMIT 1",
                $id,
                $like
            ));
            if ($in_content) {
                return array(true, 'Used in content');
            }

            $in_meta = $wpdb->get_var($wpdb->prepare(
                "SELECT meta_id FROM {$wpdb->postmeta}
                 WHERE post_id <> %d
                   AND meta_key NOT IN ('_wp_attached_file','_wp_attachment_metadata','_wp_attachment_backup_sizes','_edit_lock','_edit_last')
                   AND meta_value LIKE %s LIMIT 1",
                $id,
                $like
            ));
            if ($in_meta) {
                return array(true, 'Referenced in meta');
            }
        }

        return array(false, 'No references found');
    }

    /**
     * Permanently delete attachments by id (files + all sizes).
     *
     * @return int Number deleted.
     */
    public function delete($ids)
    {
        $deleted = 0;
        foreach ((array) $ids as $id) {
            $id = (int) $id;
            if ($id && get_post_type($id) === 'attachment') {
                if (wp_delete_attachment($id, true)) {
                    $deleted++;
                }
            }
        }
        return $deleted;
    }
}
