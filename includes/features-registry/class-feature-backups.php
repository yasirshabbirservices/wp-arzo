<?php

/**
 * Feature: Automated Snapshots.
 *
 * When enabled, takes a database snapshot automatically *before* any feature is
 * toggled (and exposes retention + scope settings). Manual backups are always
 * available from the Backups admin page regardless of this feature.
 *
 * @package WP_Arzo
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Arzo_Feature_Backups extends WP_Arzo_Feature
{
    public function id()
    {
        return 'auto_snapshots';
    }

    public function title()
    {
        return 'Automated Snapshots';
    }

    public function description()
    {
        return 'Automatically back up the database before any feature is toggled. Restore anytime from the Backups page.';
    }

    public function group()
    {
        return 'backup';
    }

    public function icon()
    {
        return 'database';
    }

    public function settings_schema()
    {
        return array(
            array(
                'key'     => 'auto_scope',
                'type'    => 'select',
                'label'   => 'Auto-snapshot scope',
                'default' => 'options',
                'help'    => 'Feature toggles only change the options table, so "Options" is fast and sufficient. Choose "Full database" for a complete safety net.',
                'options' => array(
                    'options' => 'Options table only (recommended)',
                    'full_db' => 'Full database',
                ),
            ),
            array(
                'key'     => 'retention',
                'type'    => 'number',
                'label'   => 'Snapshots to keep',
                'default' => 10,
                'help'    => 'Older snapshots beyond this count are pruned automatically.',
            ),
        );
    }

    public function boot()
    {
        // Apply retention from settings.
        add_filter('wp_arzo_backup_retention', function () {
            return max(1, (int) $this->get_setting('retention', 10));
        });

        // Snapshot before a feature toggle is persisted.
        add_action('wp_arzo_before_feature_toggle', function ($id, $enabled, $feature) {
            if (!class_exists('WP_Arzo_Backup_Manager')) {
                return;
            }
            $scope = $this->get_setting('auto_scope', 'options');
            $label = ($enabled ? 'Before enabling: ' : 'Before disabling: ') . $feature->title();
            WP_Arzo_Backup_Manager::instance()->create($scope, $label, 'feature_toggle');
        }, 10, 3);
    }
}
