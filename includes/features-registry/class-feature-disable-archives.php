<?php

/**
 * Feature: Disable Archives.
 *
 * Turn off category / tag / author / date archive pages on the front end (each is an
 * independent toggle). Disabled archives return a 404 — useful for thin-content SEO.
 *
 * @package WP_Arzo
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Arzo_Feature_Disable_Archives extends WP_Arzo_Feature
{
    public function id()
    {
        return 'disable_archives';
    }
    public function title()
    {
        return 'Disable Archives';
    }
    public function description()
    {
        return 'Turn off category, tag, author, and/or date archive pages on the front end (each is a 404).';
    }
    public function group()
    {
        return 'marketing';
    }
    public function icon()
    {
        return 'search';
    }
    public function settings_schema()
    {
        return array(
            array('key' => 'category', 'type' => 'toggle', 'label' => 'Disable category archives', 'default' => 0),
            array('key' => 'tag',      'type' => 'toggle', 'label' => 'Disable tag archives', 'default' => 0),
            array('key' => 'author',   'type' => 'toggle', 'label' => 'Disable author archives', 'default' => 0),
            array('key' => 'date',     'type' => 'toggle', 'label' => 'Disable date archives', 'default' => 0),
        );
    }

    public function boot()
    {
        add_action('template_redirect', array($this, 'maybe_block'));
    }

    public function maybe_block()
    {
        if (is_admin()) {
            return;
        }
        $hit = ($this->get_setting('category', 0) && is_category())
            || ($this->get_setting('tag', 0) && is_tag())
            || ($this->get_setting('author', 0) && is_author())
            || ($this->get_setting('date', 0) && is_date());

        if (!$hit) {
            return;
        }
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
        nocache_headers();
        $template = get_404_template();
        if ($template) {
            include $template;
        }
        exit;
    }
}
