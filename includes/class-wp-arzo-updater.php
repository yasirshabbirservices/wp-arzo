<?php

/**
 * WP Arzo — GitHub release updater.
 *
 * Lets the free plugin update straight from its GitHub Releases (no wordpress.org).
 * It checks the latest release, and when its tag is newer than the installed version
 * it surfaces a normal WP plugin update (Plugins screen, update count, "auto-update"
 * toggle, and the View-details modal). The downloadable package is the `wp-arzo.zip`
 * asset built by `.github/workflows/release.yml`.
 *
 * @package WP_Arzo
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Arzo_Updater
{
    /** @var string Plugin basename, e.g. "wp-arzo/wp-arzo.php". */
    private $file;
    /** @var string Plugin folder slug, e.g. "wp-arzo". */
    private $slug;
    /** @var string Installed version (no leading "v"). */
    private $version;
    /** @var string "owner/repo". */
    private $repo;
    /** @var string Transient cache key for the GitHub response. */
    private $cache_key = 'wp_arzo_gh_release';

    public function __construct($file, $version, $repo)
    {
        $this->file    = $file;
        $this->slug    = dirname($file);
        $this->version = ltrim((string) $version, 'vV');
        $this->repo    = $repo;
    }

    /** Register the update hooks. */
    public static function boot($file, $version, $repo)
    {
        $self = new self($file, $version, $repo);
        add_filter('pre_set_site_transient_update_plugins', array($self, 'inject_update'));
        add_filter('plugins_api', array($self, 'plugin_info'), 20, 3);
        add_filter('upgrader_source_selection', array($self, 'rename_source'), 10, 4);
        add_action('upgrader_process_complete', array($self, 'flush_cache'), 10, 2);
    }

    /* ----------------------------------------------------- GitHub fetch */

    /** Latest release object (cached), or null. */
    private function remote_release()
    {
        $cached = get_transient($this->cache_key);
        if ($cached !== false) {
            return ($cached === 'none') ? null : $cached;
        }
        $res = wp_remote_get(
            'https://api.github.com/repos/' . $this->repo . '/releases/latest',
            array(
                'timeout' => 10,
                'headers' => array(
                    'Accept'     => 'application/vnd.github+json',
                    'User-Agent' => 'WP-Arzo-Updater',
                ),
            )
        );
        if (is_wp_error($res) || (int) wp_remote_retrieve_response_code($res) !== 200) {
            set_transient($this->cache_key, 'none', 2 * HOUR_IN_SECONDS);
            return null;
        }
        $data = json_decode(wp_remote_retrieve_body($res));
        if (!is_object($data) || empty($data->tag_name)) {
            set_transient($this->cache_key, 'none', 2 * HOUR_IN_SECONDS);
            return null;
        }
        set_transient($this->cache_key, $data, 6 * HOUR_IN_SECONDS);
        return $data;
    }

    /** Pick the distributable zip: a release .zip asset, else the source zipball. */
    public static function package_url($data)
    {
        if (!empty($data->assets) && is_array($data->assets)) {
            foreach ($data->assets as $asset) {
                if (isset($asset->name, $asset->browser_download_url) && substr($asset->name, -4) === '.zip') {
                    return $asset->browser_download_url;
                }
            }
        }
        return isset($data->zipball_url) ? $data->zipball_url : '';
    }

    /** Whether $tag (release) is newer than the installed version. */
    public static function is_newer($tag, $installed)
    {
        return version_compare(ltrim((string) $tag, 'vV'), ltrim((string) $installed, 'vV'), '>');
    }

    /* --------------------------------------------------------- WP hooks */

    public function inject_update($transient)
    {
        if (!is_object($transient)) {
            return $transient;
        }
        $data = $this->remote_release();
        if (!$data || !self::is_newer($data->tag_name, $this->version)) {
            return $transient;
        }
        $package = self::package_url($data);
        if (!$package) {
            return $transient;
        }
        if (!isset($transient->response) || !is_array($transient->response)) {
            $transient->response = array();
        }
        $transient->response[$this->file] = (object) array(
            'slug'        => $this->slug,
            'plugin'      => $this->file,
            'new_version' => ltrim((string) $data->tag_name, 'vV'),
            'url'         => 'https://github.com/' . $this->repo,
            'package'     => $package,
            'icons'       => array(),
            'tested'      => '',
            'requires_php' => '7.2',
        );
        return $transient;
    }

    public function plugin_info($result, $action, $args)
    {
        if ($action !== 'plugin_information' || empty($args->slug) || $args->slug !== $this->slug) {
            return $result;
        }
        $data = $this->remote_release();
        if (!$data) {
            return $result;
        }
        return (object) array(
            'name'          => 'WP Arzo',
            'slug'          => $this->slug,
            'version'       => ltrim((string) $data->tag_name, 'vV'),
            'author'        => '<a href="https://yasirshabbir.com" target="_blank" rel="noopener">Yasir Shabbir</a>',
            'homepage'      => 'https://github.com/' . $this->repo,
            'download_link' => self::package_url($data),
            'requires'      => '5.0',
            'requires_php'  => '7.2',
            'last_updated'  => isset($data->published_at) ? $data->published_at : '',
            'sections'      => array(
                'description' => 'WP Arzo — Maintenance &amp; Administration Suite.',
                'changelog'   => isset($data->body) && $data->body !== '' ? wpautop(esc_html($data->body)) : 'See the GitHub release notes.',
            ),
        );
    }

    /**
     * GitHub zips extract to a folder named after the repo/tag (or "wp-arzo" from our
     * workflow). Rename it to the installed plugin slug so the update replaces in place.
     */
    public function rename_source($source, $remote_source, $upgrader, $hook_extra = array())
    {
        if (empty($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->file) {
            return $source;
        }
        global $wp_filesystem;
        $desired = trailingslashit($remote_source) . $this->slug;
        if (trailingslashit($source) === trailingslashit($desired)) {
            return $source;
        }
        if ($wp_filesystem && $wp_filesystem->move($source, $desired, true)) {
            return trailingslashit($desired);
        }
        return $source;
    }

    public function flush_cache($upgrader, $options)
    {
        if (isset($options['action'], $options['type']) && $options['action'] === 'update' && $options['type'] === 'plugin') {
            delete_transient($this->cache_key);
        }
    }
}
