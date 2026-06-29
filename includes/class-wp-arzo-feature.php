<?php

/**
 * Base class for a WP Arzo feature module.
 *
 * Every feature (free or pro) extends this. The registry discovers modules,
 * renders the dashboard toggle grid from their metadata, persists enable-state
 * and settings, and calls boot() only for ENABLED features.
 *
 * @package WP_Arzo
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class WP_Arzo_Feature
{
    /** Unique, stable id (snake_case), e.g. 'disable_comments'. */
    abstract public function id();

    /** Human title shown on the dashboard card. */
    abstract public function title();

    /** One-line description shown under the title. */
    public function description()
    {
        return '';
    }

    /** Group key (see WP_Arzo_Feature_Registry::groups()). */
    public function group()
    {
        return 'utilities';
    }

    /** 'free' | 'pro'. */
    public function tier()
    {
        return 'free';
    }

    /** Icon registry key (see wp_arzo_icon()). */
    public function icon()
    {
        return 'bolt';
    }

    /** Whether the feature is on by default (before the user toggles it). */
    public function default_enabled()
    {
        return false;
    }

    /**
     * Settings field definitions (schema-driven, rendered by the dashboard).
     *
     * Each field: [
     *   'key' => 'string', 'type' => 'text|number|textarea|select|toggle',
     *   'label' => '…', 'help' => '…', 'default' => mixed,
     *   'options' => ['value' => 'Label']   // for select
     * ]
     *
     * @return array
     */
    public function settings_schema()
    {
        return array();
    }

    /** Whether this feature exposes a settings screen. */
    public function has_settings()
    {
        return !empty($this->settings_schema());
    }

    /**
     * Register the feature's hooks. Called only when the feature is enabled.
     * Runs on plugins_loaded, so features can add_action('init'|'admin_init'|…).
     */
    public function boot()
    {
    }

    /** Is this feature currently enabled? */
    public function is_enabled()
    {
        return WP_Arzo_Feature_Registry::instance()->is_enabled($this->id());
    }

    /** Read one of this feature's saved settings (falling back to the schema default). */
    public function get_setting($key, $default = null)
    {
        $values = WP_Arzo_Feature_Registry::instance()->get_settings($this->id());
        if (array_key_exists($key, $values)) {
            return $values[$key];
        }
        foreach ($this->settings_schema() as $field) {
            if (isset($field['key']) && $field['key'] === $key && array_key_exists('default', $field)) {
                return $field['default'];
            }
        }
        return $default;
    }
}
