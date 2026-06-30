<?php

/**
 * WP Arzo Backup Manager (v1 — database snapshots).
 *
 * Creates streaming, low-memory DB snapshots (JSONL, gzip when available),
 * lists them, restores them (taking a safety snapshot first), deletes them, and
 * prunes by retention. Snapshots live in a protected folder under uploads.
 *
 * Scopes:
 *   - 'options'  : just the {prefix}options table (fast; used by auto-snapshot).
 *   - 'full_db'  : every table with the current table prefix.
 *
 * This is the local engine; cloud remotes (Drive/Dropbox/S3/…) are layered on top
 * later (Pro). Restore is structure-preserving: it TRUNCATEs and re-inserts (and
 * only CREATEs a table if it is missing), which safely covers "undo a bad
 * toggle/setting" without dropping live schema.
 *
 * @package WP_Arzo
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Arzo_Backup_Manager
{
    /** @var WP_Arzo_Backup_Manager|null */
    private static $instance = null;

    /** Rows read/inserted per batch (keeps memory flat on large tables). */
    const BATCH = 1000;

    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /* ----------------------------------------------------------- Storage */

    /** Absolute path to the backups root (under uploads), ensured + protected. */
    public function base_dir()
    {
        $uploads = wp_upload_dir();
        $dir = trailingslashit($uploads['basedir']) . 'wp-arzo-backups';

        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }
        // Deny direct web access.
        $htaccess = $dir . '/.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents($htaccess, "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
        }
        $index = $dir . '/index.php';
        if (!file_exists($index)) {
            @file_put_contents($index, "<?php // Silence is golden.\n");
        }
        return $dir;
    }

    private function snapshot_dir($id)
    {
        return $this->base_dir() . '/' . $id;
    }

    private function gz_available()
    {
        return function_exists('gzopen');
    }

    /* ------------------------------------------------------------ Create */

    /**
     * Create a snapshot.
     *
     * @param string $scope   'options' | 'full_db'
     * @param string $label   Human label.
     * @param string $trigger 'manual' | 'feature_toggle' | 'pre_restore' | …
     * @return array|WP_Error  Manifest array on success.
     */
    public function create($scope = 'options', $label = '', $trigger = 'manual')
    {
        global $wpdb;

        $scope = in_array($scope, array('options', 'full_db'), true) ? $scope : 'options';
        $tables = $this->tables_for_scope($scope);
        if (empty($tables)) {
            return new WP_Error('wp_arzo_backup_no_tables', 'No tables found to back up.');
        }

        $id = 'snap-' . gmdate('Ymd-His') . '-' . wp_generate_password(6, false, false);
        $dir = $this->snapshot_dir($id);
        if (!wp_mkdir_p($dir)) {
            return new WP_Error('wp_arzo_backup_mkdir', 'Could not create the snapshot folder.');
        }

        $use_gz = $this->gz_available();
        $data_path = $dir . '/data.jsonl' . ($use_gz ? '.gz' : '');
        $fh = $use_gz ? gzopen($data_path, 'wb9') : fopen($data_path, 'wb');
        if (!$fh) {
            return new WP_Error('wp_arzo_backup_open', 'Could not open the snapshot file for writing.');
        }
        $write = function ($line) use ($fh, $use_gz) {
            $use_gz ? gzwrite($fh, $line . "\n") : fwrite($fh, $line . "\n");
        };

        $write(wp_json_encode(array('type' => 'meta', 'scope' => $scope, 'prefix' => $wpdb->prefix, 'format' => 1)));

        $row_total = 0;
        foreach ($tables as $table) {
            $create_row = $wpdb->get_row("SHOW CREATE TABLE `$table`", ARRAY_N);
            $create_sql = isset($create_row[1]) ? $create_row[1] : '';
            $write(wp_json_encode(array('type' => 'table', 'name' => $table, 'create' => $create_sql)));

            $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM `$table`");
            for ($offset = 0; $offset < $count; $offset += self::BATCH) {
                $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM `$table` LIMIT %d OFFSET %d", self::BATCH, $offset), ARRAY_A);
                if (!$rows) {
                    break;
                }
                foreach ($rows as $row) {
                    $write(wp_json_encode(array('type' => 'row', 'name' => $table, 'data' => $row)));
                    $row_total++;
                }
            }
        }

        $use_gz ? gzclose($fh) : fclose($fh);

        $manifest = array(
            'id'          => $id,
            'label'       => $label !== '' ? $label : ('Snapshot ' . gmdate('Y-m-d H:i:s') . ' UTC'),
            'scope'       => $scope,
            'trigger'     => $trigger,
            'created'     => time(),
            'created_gmt' => gmdate('Y-m-d H:i:s'),
            'tables'      => array_values($tables),
            'table_count' => count($tables),
            'row_total'   => $row_total,
            'bytes'       => (int) (file_exists($data_path) ? filesize($data_path) : 0),
            'gzip'        => $use_gz,
            'wp_version'  => get_bloginfo('version'),
            'db_prefix'   => $wpdb->prefix,
            'plugin_ver'  => defined('WP_ARZO_VERSION') ? WP_ARZO_VERSION : '',
        );
        file_put_contents($dir . '/manifest.json', wp_json_encode($manifest));

        $this->prune();

        /**
         * Fires after a snapshot is written. Off-site/remote destinations (Pro) hook
         * this to push the snapshot folder to FTP/S3/cloud storage.
         *
         * @param string $id       Snapshot id.
         * @param array  $manifest Snapshot manifest.
         * @param string $dir      Absolute path to the snapshot folder.
         */
        do_action('wp_arzo_after_snapshot_created', $id, $manifest, $dir);

        return $manifest;
    }

    private function tables_for_scope($scope)
    {
        global $wpdb;
        if ($scope === 'options') {
            return array($wpdb->options);
        }
        $like = $wpdb->esc_like($wpdb->prefix) . '%';
        $tables = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $like));
        return is_array($tables) ? $tables : array();
    }

    /* -------------------------------------------------------------- List */

    /** @return array[] manifests, newest first. */
    public function list_snapshots()
    {
        $base = $this->base_dir();
        $out = array();
        foreach ((array) glob($base . '/snap-*', GLOB_ONLYDIR) as $dir) {
            $mf = $dir . '/manifest.json';
            if (!file_exists($mf)) {
                continue;
            }
            $data = json_decode((string) file_get_contents($mf), true);
            if (is_array($data) && !empty($data['id'])) {
                $out[] = $data;
            }
        }
        usort($out, function ($a, $b) {
            return ($b['created'] ?? 0) <=> ($a['created'] ?? 0);
        });
        return $out;
    }

    public function get_snapshot($id)
    {
        $id = $this->sanitize_id($id);
        $mf = $this->snapshot_dir($id) . '/manifest.json';
        if (!$id || !file_exists($mf)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($mf), true);
        return is_array($data) ? $data : null;
    }

    /* ----------------------------------------------------------- Restore */

    /**
     * Restore a snapshot. Takes a safety snapshot of the same scope first.
     *
     * @return true|WP_Error
     */
    public function restore($id)
    {
        global $wpdb;

        $manifest = $this->get_snapshot($id);
        if (!$manifest) {
            return new WP_Error('wp_arzo_backup_missing', 'Snapshot not found.');
        }

        // Safety snapshot before we mutate anything.
        $this->create($manifest['scope'], 'Auto safety snapshot before restore', 'pre_restore');

        $dir = $this->snapshot_dir($manifest['id']);
        $use_gz = !empty($manifest['gzip']);
        $data_path = $dir . '/data.jsonl' . ($use_gz ? '.gz' : '');
        if (!file_exists($data_path)) {
            return new WP_Error('wp_arzo_backup_data', 'Snapshot data file is missing.');
        }

        $fh = $use_gz ? gzopen($data_path, 'rb') : fopen($data_path, 'rb');
        if (!$fh) {
            return new WP_Error('wp_arzo_backup_open', 'Could not open the snapshot data file.');
        }

        $prepared = array(); // tables we've TRUNCATEd/CREATEd this run
        $suppress = $wpdb->suppress_errors(true);

        while (($line = ($use_gz ? gzgets($fh) : fgets($fh))) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $entry = json_decode($line, true);
            if (!is_array($entry) || empty($entry['type'])) {
                continue;
            }

            if ($entry['type'] === 'table') {
                $table = $entry['name'];
                $exists = (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
                if (!$exists && !empty($entry['create'])) {
                    $wpdb->query($entry['create']);
                } elseif ($exists) {
                    $wpdb->query("TRUNCATE TABLE `$table`");
                }
                $prepared[$table] = true;
            } elseif ($entry['type'] === 'row' && isset($entry['name'], $entry['data']) && is_array($entry['data'])) {
                if (!isset($prepared[$entry['name']])) {
                    continue; // never insert into a table we didn't prepare
                }
                $wpdb->insert($entry['name'], $entry['data']);
            }
        }

        $use_gz ? gzclose($fh) : fclose($fh);
        $wpdb->suppress_errors($suppress);

        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }

        return true;
    }

    /* ------------------------------------------------------ Delete/prune */

    public function delete($id)
    {
        $id = $this->sanitize_id($id);
        if (!$id) {
            return false;
        }
        return $this->rrmdir($this->snapshot_dir($id));
    }

    /** Keep only the newest $keep snapshots. */
    public function prune($keep = null)
    {
        if ($keep === null) {
            $keep = (int) apply_filters('wp_arzo_backup_retention', 10);
        }
        $keep = max(1, (int) $keep);
        $all = $this->list_snapshots();
        if (count($all) <= $keep) {
            return;
        }
        foreach (array_slice($all, $keep) as $old) {
            $this->delete($old['id']);
        }
    }

    /* ----------------------------------------------------------- Helpers */

    private function sanitize_id($id)
    {
        // Snapshot ids are 'snap-YYYYMMDD-HHMMSS-xxxxxx'. Reject anything else
        // (no path traversal).
        return preg_match('/^snap-\d{8}-\d{6}-[A-Za-z0-9]{6}$/', (string) $id) ? $id : '';
    }

    private function rrmdir($dir)
    {
        if (!is_dir($dir)) {
            return false;
        }
        foreach (array_diff(scandir($dir), array('.', '..')) as $item) {
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        }
        return @rmdir($dir);
    }

    public function total_size()
    {
        $bytes = 0;
        foreach ($this->list_snapshots() as $s) {
            $bytes += isset($s['bytes']) ? (int) $s['bytes'] : 0;
        }
        return $bytes;
    }
}
