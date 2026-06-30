<?php

/**
 * Feature: Image SEO.
 *
 * Two upload-time helpers (each an independent toggle):
 *  • Auto Alt text — pre-fill an empty Alt field with a readable version of the filename.
 *  • Hyphenate filenames — replace underscores with hyphens for SEO-friendlier URLs.
 *
 * @package WP_Arzo
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Arzo_Feature_Image_SEO extends WP_Arzo_Feature
{
    public function id()
    {
        return 'image_seo';
    }
    public function title()
    {
        return 'Image SEO';
    }
    public function description()
    {
        return 'Auto-fill empty image Alt text from the filename, and replace underscores with hyphens in uploaded filenames.';
    }
    public function group()
    {
        return 'media';
    }
    public function icon()
    {
        return 'image';
    }
    public function settings_schema()
    {
        return array(
            array('key' => 'alt_from_filename', 'type' => 'toggle', 'label' => 'Auto-fill empty Alt text from the filename', 'default' => 1),
            array('key' => 'hyphenate', 'type' => 'toggle', 'label' => 'Convert underscores to hyphens in filenames', 'default' => 1),
        );
    }

    public function boot()
    {
        if ($this->get_setting('hyphenate', 1)) {
            add_filter('sanitize_file_name', array($this, 'hyphenate'), 10, 1);
        }
        if ($this->get_setting('alt_from_filename', 1)) {
            add_action('add_attachment', array($this, 'fill_alt'));
        }
    }

    /** Replace underscores with hyphens (and collapse repeats) in an uploaded filename. */
    public function hyphenate($filename)
    {
        $ext = '';
        $dot = strrpos($filename, '.');
        if ($dot !== false) {
            $ext = substr($filename, $dot);
            $filename = substr($filename, 0, $dot);
        }
        $filename = preg_replace('/_+/', '-', $filename);
        $filename = preg_replace('/-+/', '-', $filename);
        return trim($filename, '-') . $ext;
    }

    /** Set the image Alt text from the filename when it's empty. */
    public function fill_alt($attachment_id)
    {
        if (!wp_attachment_is_image($attachment_id)) {
            return;
        }
        $existing = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        if ($existing !== '' && $existing !== false) {
            return;
        }
        update_post_meta($attachment_id, '_wp_attachment_image_alt', self::alt_from_filename($attachment_id));
    }

    /** Turn a filename into a readable Alt string ("my_cool-photo.jpg" → "My cool photo"). */
    public static function alt_from_filename($attachment_id)
    {
        $file = get_attached_file($attachment_id);
        $name = $file ? wp_basename($file) : get_the_title($attachment_id);
        $name = preg_replace('/\.[^.]+$/', '', (string) $name);   // drop extension
        $name = preg_replace('/[-_]+/', ' ', $name);              // separators → space
        $name = preg_replace('/\s+/', ' ', trim($name));
        return $name !== '' ? ucfirst($name) : '';
    }
}
