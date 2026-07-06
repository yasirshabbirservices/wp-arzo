<?php

/**
 * Feature: Code Snippets (free). When enabled, runs the active snippets managed
 * under WP Arzo → Snippets. Disabling this feature is a global kill switch.
 *
 * @package WP_Arzo
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Arzo_Feature_Snippets extends WP_Arzo_Feature
{
    public function id()
    {
        return 'code_snippets';
    }

    public function title()
    {
        return 'Code Snippets';
    }

    public function description()
    {
        return 'Run safe PHP / CSS / JS / HTML snippets (managed under WP Arzo → Snippets). A snippet that errors auto-disables.';
    }

    public function group()
    {
        return 'developer';
    }

    public function icon()
    {
        return 'code';
    }

    public function boot()
    {
        if (class_exists('WP_Arzo_Snippets')) {
            WP_Arzo_Snippets::instance()->boot();
        }
    }
}
