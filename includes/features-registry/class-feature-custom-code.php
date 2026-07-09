<?php

/**
 * Header / Body / Footer custom-code injection.
 *
 * This is a script-insertion feature (raw HTML/JS output verbatim into the page).
 * WordPress.org does not accept script-insertion plugins from new authors, so this
 * file is stripped from the .org build via `.distignore` and its registration is
 * `file_exists()`-guarded in wp-arzo.php. It ships in the self-hosted / Pro build,
 * where the free core advertises it as a PRO-tier power feature.
 *
 * @package WP_Arzo
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Arzo_Feature_Custom_Code extends WP_Arzo_Feature
{
    public function id()
    {
        return 'custom_code';
    }
    public function title()
    {
        return 'Header / Body / Footer Code';
    }
    public function description()
    {
        return 'Insert custom code (analytics, verification tags, scripts) into the head, after <body>, or before </body>.';
    }
    public function group()
    {
        return 'developer';
    }
    public function icon()
    {
        return 'code';
    }
    public function settings_schema()
    {
        return array(
            array('key' => 'head', 'type' => 'code', 'label' => 'Inside <head>'),
            array('key' => 'body_open', 'type' => 'code', 'label' => 'After opening <body>'),
            array('key' => 'footer', 'type' => 'code', 'label' => 'Before closing </body>'),
        );
    }
    public function boot()
    {
        // Insert-Headers-and-Footers pattern: raw custom code entered by an admin
        // (manage_options) is output verbatim by design; escaping would defeat the feature.
        add_action('wp_head', function () {
            echo (string) $this->get_setting('head', ''); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }, 99);
        add_action('wp_body_open', function () {
            echo (string) $this->get_setting('body_open', ''); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        });
        add_action('wp_footer', function () {
            echo (string) $this->get_setting('footer', ''); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }, 99);
    }
}
