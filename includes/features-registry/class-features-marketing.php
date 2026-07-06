<?php

/**
 * Marketing/SEO features: Manage robots.txt, Manage ads.txt.
 *
 * @package WP_Arzo
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Arzo_Feature_Manage_Robots_Txt extends WP_Arzo_Feature
{
    public function id()
    {
        return 'manage_robots_txt';
    }
    public function title()
    {
        return 'Manage robots.txt';
    }
    public function description()
    {
        return 'Serve a custom virtual robots.txt (only applies if your site has no static one).';
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
            array(
                'key'     => 'content',
                'type'    => 'textarea',
                'label'   => 'robots.txt content',
                'default' => "User-agent: *\nDisallow:",
                'help'    => 'Leave blank to keep the WordPress default.',
            ),
        );
    }
    public function boot()
    {
        add_filter('robots_txt', function ($output, $public) {
            $content = trim((string) $this->get_setting('content', ''));
            return $content !== '' ? $content . "\n" : $output;
        }, 10, 2);
    }
}

class WP_Arzo_Feature_Manage_Ads_Txt extends WP_Arzo_Feature
{
    public function id()
    {
        return 'manage_ads_txt';
    }
    public function title()
    {
        return 'Manage ads.txt';
    }
    public function description()
    {
        return 'Serve a custom /ads.txt for advertising/authorized-seller declarations.';
    }
    public function group()
    {
        return 'marketing';
    }
    public function icon()
    {
        return 'file';
    }
    public function settings_schema()
    {
        return array(
            array(
                'key'     => 'content',
                'type'    => 'textarea',
                'label'   => 'ads.txt content',
                'default' => '',
                'help'    => 'One record per line, e.g. google.com, pub-0000000000000000, DIRECT, f08c47fec0942fa0',
            ),
        );
    }
    public function boot()
    {
        add_action('init', function () {
            $path = isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';
            if ($path === '/ads.txt') {
                $content = trim((string) $this->get_setting('content', ''));
                if ($content !== '') {
                    header('Content-Type: text/plain; charset=utf-8');
                    // ads.txt is a plain-text response of admin-supplied (manage_options) content; HTML escaping would corrupt it.
                    echo $content . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    exit;
                }
            }
        });
    }
}
