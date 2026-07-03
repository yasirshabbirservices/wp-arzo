<?php

/**
 * Free features: Google Analytics 4, Google Tag Manager, and Google Ads tags.
 *
 * Part of the Analytics pillar — these move the "insert the Google tag" job into
 * the free core (Site-Kit parity: tag insertion is free) and group them under
 * Analytics. They complement the built-in cookieless engine: use the built-in
 * reports for privacy-first first-party data, and/or forward to Google here.
 * (Pro adds GA4 Data-API reporting into the dashboard, Consent Mode, server-side
 * GTM — see analytics-plan.md P4.)
 *
 * @package WP_Arzo
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Shared: should the tag be suppressed for the current (admin) user? */
if (!function_exists('wp_arzo_google_skip_admin')) {
    function wp_arzo_google_skip_admin($feature)
    {
        return $feature->get_setting('exclude_admins', true) && is_user_logged_in() && current_user_can('manage_options');
    }
}

class WP_Arzo_Feature_GA4 extends WP_Arzo_Feature
{
    public function id()
    {
        return 'google_analytics_4';
    }
    public function title()
    {
        return 'Google Analytics 4';
    }
    public function description()
    {
        return 'Add the GA4 global site tag (gtag.js) with your Measurement ID — with IP anonymization and an option to exclude signed-in admins from your own stats.';
    }
    public function group()
    {
        return 'analytics';
    }
    public function tier()
    {
        return 'free';
    }
    public function icon()
    {
        return 'chart';
    }
    public function settings_schema()
    {
        return array(
            array('key' => 'measurement_id', 'type' => 'text', 'label' => 'Measurement ID', 'help' => 'Format: G-XXXXXXXXXX (Admin → Data Streams in GA4).', 'default' => ''),
            array('key' => 'anonymize_ip', 'type' => 'toggle', 'label' => 'Anonymize IP', 'help' => 'Send visitor IPs to Google in anonymized form.', 'default' => true),
            array('key' => 'exclude_admins', 'type' => 'toggle', 'label' => 'Exclude signed-in admins', 'help' => 'Don’t load GA4 for logged-in administrators (keeps your own visits out of the data).', 'default' => true),
        );
    }
    public function boot()
    {
        add_action('wp_head', array($this, 'print_tag'), 4);
    }
    public function print_tag()
    {
        if (wp_arzo_google_skip_admin($this)) {
            return;
        }
        $id = preg_replace('/[^A-Za-z0-9\-]/', '', (string) $this->get_setting('measurement_id', ''));
        if ($id === '') {
            return;
        }
        $anon = $this->get_setting('anonymize_ip', true) ? ", { 'anonymize_ip': true }" : '';
        ?>
        <!-- WP Arzo: Google Analytics 4 -->
        <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr($id); ?>"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag() { dataLayer.push(arguments); }
            gtag('js', new Date());
            gtag('config', '<?php echo esc_js($id); ?>'<?php echo $anon; ?>);
        </script>
        <!-- End Google Analytics 4 -->
        <?php
    }
}

class WP_Arzo_Feature_GTM extends WP_Arzo_Feature
{
    public function id()
    {
        return 'google_tag_manager';
    }
    public function title()
    {
        return 'Google Tag Manager';
    }
    public function description()
    {
        return 'Add the GTM container (head script + body noscript) by container ID — manage all your marketing tags from Google Tag Manager.';
    }
    public function group()
    {
        return 'analytics';
    }
    public function tier()
    {
        return 'free';
    }
    public function icon()
    {
        return 'code';
    }
    public function settings_schema()
    {
        return array(
            array('key' => 'container_id', 'type' => 'text', 'label' => 'Container ID', 'help' => 'Format: GTM-XXXXXXX.', 'default' => ''),
            array('key' => 'exclude_admins', 'type' => 'toggle', 'label' => 'Exclude signed-in admins', 'help' => 'Don’t load GTM for logged-in administrators.', 'default' => true),
        );
    }

    private function container_id()
    {
        return preg_replace('/[^A-Za-z0-9\-]/', '', (string) $this->get_setting('container_id', ''));
    }

    public function boot()
    {
        add_action('wp_head', array($this, 'print_head'), 1);
        add_action('wp_body_open', array($this, 'print_body'), 1);
    }

    public function print_head()
    {
        if (wp_arzo_google_skip_admin($this)) {
            return;
        }
        $id = $this->container_id();
        if ($id === '') {
            return;
        }
        ?>
        <!-- WP Arzo: Google Tag Manager -->
        <script>(function (w, d, s, l, i) {
                w[l] = w[l] || []; w[l].push({ 'gtm.start': new Date().getTime(), event: 'gtm.js' });
                var f = d.getElementsByTagName(s)[0], j = d.createElement(s), dl = l != 'dataLayer' ? '&l=' + l : '';
                j.async = true; j.src = 'https://www.googletagmanager.com/gtm.js?id=' + i + dl; f.parentNode.insertBefore(j, f);
            })(window, document, 'script', 'dataLayer', '<?php echo esc_js($id); ?>');</script>
        <!-- End Google Tag Manager -->
        <?php
    }

    public function print_body()
    {
        if (wp_arzo_google_skip_admin($this)) {
            return;
        }
        $id = $this->container_id();
        if ($id === '') {
            return;
        }
        ?>
        <!-- WP Arzo: Google Tag Manager (noscript) -->
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo esc_attr($id); ?>"
            height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
        <?php
    }
}

class WP_Arzo_Feature_Google_Ads extends WP_Arzo_Feature
{
    public function id()
    {
        return 'google_ads';
    }
    public function title()
    {
        return 'Google Ads Tag';
    }
    public function description()
    {
        return 'Add the Google Ads global site tag (gtag.js) for remarketing & conversion tracking, with an optional default conversion label.';
    }
    public function group()
    {
        return 'analytics';
    }
    public function tier()
    {
        return 'free';
    }
    public function icon()
    {
        return 'bolt';
    }
    public function settings_schema()
    {
        return array(
            array('key' => 'conversion_id', 'type' => 'text', 'label' => 'Conversion ID', 'help' => 'Format: AW-XXXXXXXXX.', 'default' => ''),
            array('key' => 'exclude_admins', 'type' => 'toggle', 'label' => 'Exclude signed-in admins', 'help' => 'Don’t load the Ads tag for logged-in administrators.', 'default' => true),
        );
    }
    public function boot()
    {
        add_action('wp_head', array($this, 'print_tag'), 5);
    }
    public function print_tag()
    {
        if (wp_arzo_google_skip_admin($this)) {
            return;
        }
        $id = preg_replace('/[^A-Za-z0-9\-]/', '', (string) $this->get_setting('conversion_id', ''));
        if ($id === '') {
            return;
        }
        ?>
        <!-- WP Arzo: Google Ads -->
        <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr($id); ?>"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag() { dataLayer.push(arguments); }
            gtag('js', new Date());
            gtag('config', '<?php echo esc_js($id); ?>');
        </script>
        <!-- End Google Ads -->
        <?php
    }
}
