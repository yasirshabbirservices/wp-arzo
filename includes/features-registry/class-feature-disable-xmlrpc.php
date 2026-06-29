<?php

/**
 * Feature: Disable XML-RPC — closes a common attack/abuse surface.
 *
 * @package WP_Arzo
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Arzo_Feature_Disable_XMLRPC extends WP_Arzo_Feature
{
    public function id()
    {
        return 'disable_xmlrpc';
    }

    public function title()
    {
        return 'Disable XML-RPC';
    }

    public function description()
    {
        return 'Disable the XML-RPC endpoint (xmlrpc.php) and its pingback methods.';
    }

    public function group()
    {
        return 'security';
    }

    public function icon()
    {
        return 'shield';
    }

    public function boot()
    {
        add_filter('xmlrpc_enabled', '__return_false');

        // Strip pingback headers and methods.
        add_filter('wp_headers', function ($headers) {
            unset($headers['X-Pingback']);
            return $headers;
        });
        add_filter('xmlrpc_methods', function ($methods) {
            unset($methods['pingback.ping'], $methods['pingback.extensions.getPingbacks']);
            return $methods;
        });
    }
}
