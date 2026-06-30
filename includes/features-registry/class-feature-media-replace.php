<?php

/**
 * Feature: Prevent Duplicate Uploads (free).
 *
 * When you upload a file that already exists in the Media Library — matched by
 * filename (and optionally size) — WP Arzo stops the upload before a duplicate is
 * created and tells you which existing item to use instead. It does NOT modify or
 * delete any files (the safe way to "replace instead of duplicate"); to swap the
 * contents of a specific item, edit that attachment in the Media Library.
 *
 * @package WP_Arzo
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Arzo_Feature_Media_Replace extends WP_Arzo_Feature
{
    public function id()
    {
        return 'media_replace';
    }
    public function title()
    {
        return 'Prevent Duplicate Uploads';
    }
    public function description()
    {
        return 'Skip uploading a file that already exists (match by name, or name + size) instead of creating a duplicate. No files are modified or deleted.';
    }
    public function group()
    {
        return 'media';
    }
    public function icon()
    {
        return 'copy';
    }
    public function settings_schema()
    {
        return array(
            array(
                'key'     => 'match_by',
                'type'    => 'select',
                'label'   => 'Treat as duplicate when…',
                'default' => 'name_size',
                'options' => array(
                    'name'      => 'The filename matches',
                    'name_size' => 'The filename AND size match (recommended)',
                ),
            ),
        );
    }

    public function boot()
    {
        add_filter('wp_handle_upload_prefilter', array($this, 'detect_duplicate'));
    }

    /** Reject an upload that duplicates an existing attachment. */
    public function detect_duplicate($file)
    {
        if (!empty($file['error'])) {
            return $file; // already failing for another reason
        }
        $name = isset($file['name']) ? sanitize_file_name($file['name']) : '';
        $size = isset($file['size']) ? (int) $file['size'] : 0;
        if ($name === '') {
            return $file;
        }
        $existing = self::find_existing($name, $size, $this->get_setting('match_by', 'name_size'));
        if ($existing) {
            $title = get_the_title($existing);
            $file['error'] = sprintf(
                'A file named “%s” (%s) is already in your Media Library%s. Upload skipped to avoid a duplicate — use the existing item, or edit it to replace its contents.',
                $name,
                function_exists('size_format') ? size_format($size) : ($size . ' bytes'),
                $title ? ' as “' . $title . '”' : ''
            );
        }
        return $file;
    }

    /**
     * Find an existing attachment that matches the uploaded file.
     *
     * @param string $basename Uploaded (sanitized) file name.
     * @param int    $size     Uploaded size in bytes.
     * @param string $mode     'name' | 'name_size'.
     * @return int Attachment ID, or 0 if none.
     */
    public static function find_existing($basename, $size, $mode)
    {
        $basename = strtolower($basename);
        $candidates = get_posts(array(
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => 20,
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'     => '_wp_attached_file',
                    'value'   => $basename,
                    'compare' => 'LIKE',
                ),
            ),
        ));
        foreach ($candidates as $id) {
            $file = get_attached_file($id);
            if (!$file) {
                continue;
            }
            if (strtolower(wp_basename($file)) !== $basename) {
                continue; // LIKE can over-match; require an exact basename
            }
            if ($mode === 'name_size') {
                $existing_size = @filesize($file);
                if ($existing_size === false || (int) $existing_size !== (int) $size) {
                    continue; // different size → treat as a genuine replacement, allow it
                }
            }
            return (int) $id;
        }
        return 0;
    }
}
