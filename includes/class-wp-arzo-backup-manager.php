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
     * @param string $scope      'options' | 'full_db'
     * @param string $label      Human label.
     * @param string $trigger    'manual' | 'feature_toggle' | 'pre_restore' | …
     * @param array  $components Optional file components: uploads|plugins|themes|config.
     * @return array|WP_Error  Manifest array on success.
     */
    public function create($scope = 'options', $label = '', $trigger = 'manual', $components = array())
    {
        global $wpdb;

        $scope = in_array($scope, array('options', 'full_db'), true) ? $scope : 'options';
        $components = array_values(array_intersect((array) $components, array('uploads', 'plugins', 'themes', 'config')));
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
        $fh = $use_gz ? gzopen($data_path, 'wb9') : fopen($data_path, 'wb'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- streaming JSONL backup I/O within the plugin's own uploads backup dir; WP_Filesystem has no streaming API.
        if (!$fh) {
            return new WP_Error('wp_arzo_backup_open', 'Could not open the snapshot file for writing.');
        }
        $write = function ($line) use ($fh, $use_gz) {
            $use_gz ? gzwrite($fh, $line . "\n") : fwrite($fh, $line . "\n"); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- streaming JSONL backup I/O within the plugin's own uploads backup dir; WP_Filesystem has no streaming API.
        };

        $write(wp_json_encode(array('type' => 'meta', 'scope' => $scope, 'prefix' => $wpdb->prefix, 'format' => 1)));

        // Diff support: per-table row counts + an option-name → value-hash map are
        // captured while streaming (cheap) and stored beside the dump.
        $row_total    = 0;
        $table_counts = array();
        $option_map   = array();
        foreach ($tables as $table) {
            $create_row = $wpdb->get_row("SHOW CREATE TABLE `$table`", ARRAY_N);
            $create_sql = isset($create_row[1]) ? $create_row[1] : '';
            $write(wp_json_encode(array('type' => 'table', 'name' => $table, 'create' => $create_sql)));

            $is_options = ($table === $wpdb->options);
            $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM `$table`");
            $table_counts[$table] = $count;
            for ($offset = 0; $offset < $count; $offset += self::BATCH) {
                $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM `$table` LIMIT %d OFFSET %d", self::BATCH, $offset), ARRAY_A);
                if (!$rows) {
                    break;
                }
                foreach ($rows as $row) {
                    $write(wp_json_encode(array('type' => 'row', 'name' => $table, 'data' => $row)));
                    $row_total++;
                    if ($is_options && isset($row['option_name']) && count($option_map) < 25000) {
                        $option_map[$row['option_name']] = md5((string) ($row['option_value'] ?? ''));
                    }
                }
            }
        }

        $use_gz ? gzclose($fh) : fclose($fh); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- streaming JSONL backup I/O within the plugin's own uploads backup dir; WP_Filesystem has no streaming API.

        file_put_contents($dir . '/db-summary.json', wp_json_encode(array(
            'format'  => 1,
            'tables'  => $table_counts,
            'options' => $option_map,
        )));

        // Bounded file snapshot (uploads / plugins / themes / config) — zip + manifest.
        $files_info = array('count' => 0, 'bytes' => 0, 'zip_bytes' => 0, 'skipped' => 0, 'error' => '');
        if (!empty($components)) {
            $files_info = $this->snapshot_files($dir, $components);
        }

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
            'bytes'       => (int) (file_exists($data_path) ? filesize($data_path) : 0) + (int) $files_info['zip_bytes'],
            'gzip'        => $use_gz,
            'components'  => $components,
            'file_count'  => (int) $files_info['count'],
            'file_bytes'  => (int) $files_info['bytes'],
            'files_skipped' => (int) $files_info['skipped'],
            'files_error' => (string) $files_info['error'],
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

    /* -------------------------------------------------------- File snapshots */

    /** Per-file cap (larger files are skipped and counted, never silently). */
    const FILE_MAX_BYTES = 104857600; // 100 MB
    /** Files above this use a size+mtime pseudo-hash instead of md5 (speed). */
    const HASH_MAX_BYTES = 33554432;  // 32 MB

    /** The directory roots for each snapshot component. */
    public function component_roots()
    {
        $uploads = wp_upload_dir();
        return array(
            'uploads' => trailingslashit($uploads['basedir']),
            'plugins' => trailingslashit(WP_PLUGIN_DIR),
            'themes'  => trailingslashit(get_theme_root()),
        );
    }

    /**
     * Zip the requested components into <dir>/files.zip and write a hash manifest
     * (files-manifest.jsonl[.gz]) used by diff(). Bounded: skips files > 100 MB and
     * symlinks (skips are COUNTED), excludes the backup dir itself, and reopens the
     * zip every 500 entries to stay under open-file limits.
     *
     * @return array{count:int,bytes:int,zip_bytes:int,skipped:int,error:string}
     */
    private function snapshot_files($dir, array $components)
    {
        $info = array('count' => 0, 'bytes' => 0, 'zip_bytes' => 0, 'skipped' => 0, 'error' => '');
        if (!class_exists('ZipArchive')) {
            $info['error'] = 'ZipArchive is not available on this server — file components were skipped.';
            return $info;
        }
        $zip_path = $dir . '/files.zip';
        $use_gz   = $this->gz_available();
        $mf_path  = $dir . '/files-manifest.jsonl' . ($use_gz ? '.gz' : '');
        $mf       = $use_gz ? gzopen($mf_path, 'wb9') : fopen($mf_path, 'wb'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- streaming JSONL backup I/O within the plugin's own uploads backup dir; WP_Filesystem has no streaming API.
        if (!$mf) {
            $info['error'] = 'Could not open the file manifest for writing.';
            return $info;
        }
        $mwrite = function ($line) use ($mf, $use_gz) {
            $use_gz ? gzwrite($mf, $line . "\n") : fwrite($mf, $line . "\n"); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- streaming JSONL backup I/O within the plugin's own uploads backup dir; WP_Filesystem has no streaming API.
        };

        $zip = new ZipArchive();
        if ($zip->open($zip_path, ZipArchive::CREATE) !== true) {
            $use_gz ? gzclose($mf) : fclose($mf); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- streaming JSONL backup I/O within the plugin's own uploads backup dir; WP_Filesystem has no streaming API.
            $info['error'] = 'Could not create files.zip.';
            return $info;
        }

        $exclude = str_replace('\\', '/', $this->base_dir());
        $roots   = $this->component_roots();
        $pending = 0;

        foreach ($components as $component) {
            if ($component === 'config') {
                foreach (array('wp-config.php', '.htaccess') as $file) {
                    $abs = ABSPATH . $file;
                    if (is_file($abs) && is_readable($abs)) {
                        $zip->addFile($abs, 'config/' . $file);
                        $size = (int) filesize($abs);
                        $mwrite(wp_json_encode(array('c' => 'config', 'p' => $file, 's' => $size, 'm' => (int) filemtime($abs), 'h' => md5_file($abs))));
                        $info['count']++;
                        $info['bytes'] += $size;
                        $pending++;
                    }
                }
                continue;
            }
            if (!isset($roots[$component]) || !is_dir($roots[$component])) {
                continue;
            }
            $root = $roots[$component];
            try {
                $it = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::LEAVES_ONLY,
                    RecursiveIteratorIterator::CATCH_GET_CHILD
                );
            } catch (Exception $e) {
                continue;
            }
            foreach ($it as $file) {
                /** @var SplFileInfo $file */
                $abs = str_replace('\\', '/', $file->getPathname());
                // Never snapshot our own backups (uploads contains this base dir).
                if (strpos($abs, $exclude) === 0) {
                    continue;
                }
                if ($file->isLink() || !$file->isFile() || !$file->isReadable()) {
                    continue;
                }
                $size = (int) $file->getSize();
                if ($size > self::FILE_MAX_BYTES) {
                    $info['skipped']++;
                    continue;
                }
                $rel   = ltrim(substr($abs, strlen(str_replace('\\', '/', $root))), '/');
                $mtime = (int) $file->getMTime();
                $hash  = ($size <= self::HASH_MAX_BYTES) ? (string) md5_file($abs) : ('z:' . $size . ':' . $mtime);
                $zip->addFile($file->getPathname(), $component . '/' . $rel);
                $mwrite(wp_json_encode(array('c' => $component, 'p' => $rel, 's' => $size, 'm' => $mtime, 'h' => $hash)));
                $info['count']++;
                $info['bytes'] += $size;
                $pending++;
                if ($pending >= 500) { // flush to disk, avoid open-handle limits
                    $zip->close();
                    $zip->open($zip_path, ZipArchive::CREATE);
                    $pending = 0;
                }
            }
        }

        $zip->close();
        $use_gz ? gzclose($mf) : fclose($mf); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- streaming JSONL backup I/O within the plugin's own uploads backup dir; WP_Filesystem has no streaming API.
        $info['zip_bytes'] = (int) (file_exists($zip_path) ? filesize($zip_path) : 0);
        return $info;
    }

    /* ---------------------------------------------------------------- Diff */

    /** Load a snapshot's db-summary.json (null when missing — pre-6.93 snapshot). */
    public function load_db_summary($id)
    {
        $path = $this->snapshot_dir($this->sanitize_id($id)) . '/db-summary.json';
        if (!is_file($path)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($path), true);
        return is_array($data) ? $data : null;
    }

    /** Load a snapshot's file manifest as ["component/relpath" => hash]. Null when absent. */
    public function load_files_map($id)
    {
        $dir = $this->snapshot_dir($this->sanitize_id($id));
        $gz  = $dir . '/files-manifest.jsonl.gz';
        $raw = $dir . '/files-manifest.jsonl';
        $path = is_file($gz) ? $gz : (is_file($raw) ? $raw : '');
        if ($path === '') {
            return null;
        }
        $fh = (substr($path, -3) === '.gz') ? gzopen($path, 'rb') : fopen($path, 'rb'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- streaming JSONL backup I/O within the plugin's own uploads backup dir; WP_Filesystem has no streaming API.
        if (!$fh) {
            return null;
        }
        $map  = array();
        $isgz = (substr($path, -3) === '.gz');
        while (!($isgz ? gzeof($fh) : feof($fh))) {
            $line = $isgz ? gzgets($fh) : fgets($fh);
            if ($line === false) {
                break;
            }
            $row = json_decode(trim($line), true);
            if (is_array($row) && isset($row['c'], $row['p'], $row['h'])) {
                $map[$row['c'] . '/' . $row['p']] = (string) $row['h'];
            }
        }
        $isgz ? gzclose($fh) : fclose($fh); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- streaming JSONL backup I/O within the plugin's own uploads backup dir; WP_Filesystem has no streaming API.
        return $map;
    }

    /**
     * Pure map diff: added / removed / changed keys between two ["key" => hash] maps.
     * Sample lists are capped (counts are exact).
     */
    public static function diff_maps(array $a, array $b, $sample_cap = 50)
    {
        $out = array(
            'added' => array(), 'removed' => array(), 'changed' => array(),
            'added_count' => 0, 'removed_count' => 0, 'changed_count' => 0,
        );
        foreach ($b as $k => $h) {
            if (!array_key_exists($k, $a)) {
                $out['added_count']++;
                if (count($out['added']) < $sample_cap) {
                    $out['added'][] = $k;
                }
            } elseif ($a[$k] !== $h) {
                $out['changed_count']++;
                if (count($out['changed']) < $sample_cap) {
                    $out['changed'][] = $k;
                }
            }
        }
        foreach ($a as $k => $h) {
            if (!array_key_exists($k, $b)) {
                $out['removed_count']++;
                if (count($out['removed']) < $sample_cap) {
                    $out['removed'][] = $k;
                }
            }
        }
        return $out;
    }

    /** Pure table-count diff: per-table row deltas + added/removed tables. */
    public static function diff_tables(array $a, array $b)
    {
        $rows = array();
        foreach ($b as $table => $count) {
            if (!array_key_exists($table, $a)) {
                $rows[] = array('table' => $table, 'change' => 'added', 'delta' => (int) $count);
            } elseif ((int) $a[$table] !== (int) $count) {
                $rows[] = array('table' => $table, 'change' => 'rows', 'delta' => (int) $count - (int) $a[$table]);
            }
        }
        foreach ($a as $table => $count) {
            if (!array_key_exists($table, $b)) {
                $rows[] = array('table' => $table, 'change' => 'removed', 'delta' => -(int) $count);
            }
        }
        return $rows;
    }

    /**
     * Compare two snapshots (A = older/base, B = newer). Returns a structured
     * summary of DB (tables + options) and file changes, with notes where a
     * snapshot predates diff support or lacks file components.
     *
     * @return array|WP_Error
     */
    public function diff($id_a, $id_b)
    {
        $a = $this->get_snapshot($id_a);
        $b = $this->get_snapshot($id_b);
        if (!$a || !$b) {
            return new WP_Error('wp_arzo_diff_missing', 'One of the snapshots no longer exists.');
        }
        $out = array(
            'a'  => array('id' => $a['id'], 'label' => $a['label'], 'created_gmt' => $a['created_gmt'] ?? ''),
            'b'  => array('id' => $b['id'], 'label' => $b['label'], 'created_gmt' => $b['created_gmt'] ?? ''),
            'db' => null,
            'files' => null,
            'notes' => array(),
        );

        $sum_a = $this->load_db_summary($id_a);
        $sum_b = $this->load_db_summary($id_b);
        if ($sum_a && $sum_b) {
            $out['db'] = array(
                'tables'  => self::diff_tables((array) ($sum_a['tables'] ?? array()), (array) ($sum_b['tables'] ?? array())),
                'options' => self::diff_maps((array) ($sum_a['options'] ?? array()), (array) ($sum_b['options'] ?? array())),
            );
        } else {
            $out['notes'][] = 'Database comparison unavailable — one of the snapshots was created before diff support (v6.93).';
        }

        $files_a = $this->load_files_map($id_a);
        $files_b = $this->load_files_map($id_b);
        if ($files_a !== null && $files_b !== null) {
            $out['files'] = self::diff_maps($files_a, $files_b);
        } elseif (!empty($a['components']) || !empty($b['components'])) {
            $out['notes'][] = 'File comparison needs file components on BOTH snapshots.';
        }

        return $out;
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

    /** Result of the file-restore half of the last restore() call (array|null). */
    public $last_files_result = null;

    /**
     * Restore a snapshot. Takes a safety snapshot of the same scope (and, when
     * restoring files, the same file components) first.
     *
     * @param string $id
     * @param bool   $include_files Also extract the snapshot's file components
     *                              (uploads/plugins/themes). Config files are NEVER
     *                              auto-restored. File-restore details land in
     *                              $this->last_files_result.
     * @return true|WP_Error
     */
    public function restore($id, $include_files = false)
    {
        global $wpdb;

        $manifest = $this->get_snapshot($id);
        if (!$manifest) {
            return new WP_Error('wp_arzo_backup_missing', 'Snapshot not found.');
        }

        // Safety snapshot before we mutate anything — same scope, and the same file
        // components when those are about to be overwritten too.
        $safety_components = $include_files ? array_values(array_diff((array) ($manifest['components'] ?? array()), array('config'))) : array();
        $this->create($manifest['scope'], 'Auto safety snapshot before restore', 'pre_restore', $safety_components);

        $dir = $this->snapshot_dir($manifest['id']);
        $use_gz = !empty($manifest['gzip']);
        $data_path = $dir . '/data.jsonl' . ($use_gz ? '.gz' : '');
        if (!file_exists($data_path)) {
            return new WP_Error('wp_arzo_backup_data', 'Snapshot data file is missing.');
        }

        $fh = $use_gz ? gzopen($data_path, 'rb') : fopen($data_path, 'rb'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- streaming JSONL backup I/O within the plugin's own uploads backup dir; WP_Filesystem has no streaming API.
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
                    $wpdb->query($entry['create']); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- runs a CREATE TABLE statement from a plugin-generated backup manifest during restore; DDL is not parameterizable.
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

        $use_gz ? gzclose($fh) : fclose($fh); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- streaming JSONL backup I/O within the plugin's own uploads backup dir; WP_Filesystem has no streaming API.
        $wpdb->suppress_errors($suppress);

        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }

        $this->last_files_result = null;
        if ($include_files) {
            $fr = $this->restore_files($id);
            $this->last_files_result = is_wp_error($fr) ? array('error' => $fr->get_error_message()) : $fr;
        }

        return true;
    }

    /**
     * Pure mapper for a files.zip entry name → component + relative path.
     * Returns null for anything unsafe or unknown: directory entries, unknown
     * component prefixes, absolute paths, drive letters, or any '.'/'..' segment.
     */
    public static function map_zip_entry($name)
    {
        $name = str_replace('\\', '/', (string) $name);
        if ($name === '' || substr($name, -1) === '/') {
            return null; // directory entry
        }
        if (!preg_match('#^(uploads|plugins|themes|config)/(.+)$#', $name, $m)) {
            return null;
        }
        $rel = $m[2];
        if (strpos($rel, ':') !== false || $rel[0] === '/') {
            return null;
        }
        foreach (explode('/', $rel) as $seg) {
            if ($seg === '' || $seg === '.' || $seg === '..') {
                return null;
            }
        }
        return array('component' => $m[1], 'rel' => $rel);
    }

    /**
     * Extract a snapshot's files.zip back over the live site (uploads / plugins /
     * themes). Non-destructive: files added since the snapshot are left in place.
     * Config entries are counted but NEVER written (a wrong wp-config bricks the
     * site — download the snapshot to apply those manually). Zip-slip safe:
     * every entry passes map_zip_entry() + a realpath containment check.
     *
     * @return array{restored:int,failed:int,config_skipped:int}|WP_Error
     */
    public function restore_files($id)
    {
        $manifest = $this->get_snapshot($id);
        if (!$manifest) {
            return new WP_Error('wp_arzo_backup_missing', 'Snapshot not found.');
        }
        $zip_path = $this->snapshot_dir($manifest['id']) . '/files.zip';
        if (!is_file($zip_path)) {
            return new WP_Error('wp_arzo_restore_nofiles', 'This snapshot has no file archive.');
        }
        if (!class_exists('ZipArchive')) {
            return new WP_Error('wp_arzo_restore_nozip', 'ZipArchive is not available on this server.');
        }
        $zip = new ZipArchive();
        if ($zip->open($zip_path) !== true) {
            return new WP_Error('wp_arzo_restore_zipopen', 'Could not open the snapshot file archive.');
        }

        $roots = $this->component_roots();
        $out   = array('restored' => 0, 'failed' => 0, 'config_skipped' => 0);

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            $map  = self::map_zip_entry($name);
            if (!$map) {
                continue;
            }
            if ($map['component'] === 'config') {
                $out['config_skipped']++;
                continue;
            }
            $root = isset($roots[$map['component']]) ? $roots[$map['component']] : '';
            if ($root === '' || !is_dir($root)) {
                $out['failed']++;
                continue;
            }
            $target = $root . $map['rel'];
            if (!wp_mkdir_p(dirname($target))) {
                $out['failed']++;
                continue;
            }
            // Belt-and-braces containment (segments already validated above).
            $real_dir  = realpath(dirname($target));
            $real_root = realpath($root);
            if (!$real_dir || !$real_root || strpos(str_replace('\\', '/', $real_dir) . '/', str_replace('\\', '/', $real_root) . '/') !== 0) {
                $out['failed']++;
                continue;
            }
            $in = $zip->getStream($name);
            if (!$in) {
                $out['failed']++;
                continue;
            }
            // Write beside, then swap — never leave a half-written live file.
            $tmp   = $target . '.wpa-tmp';
            $outfh = @fopen($tmp, 'wb'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- streaming JSONL backup I/O within the plugin's own uploads backup dir; WP_Filesystem has no streaming API.
            if (!$outfh) {
                fclose($in); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- streaming JSONL backup I/O within the plugin's own uploads backup dir; WP_Filesystem has no streaming API.
                $out['failed']++;
                continue;
            }
            stream_copy_to_stream($in, $outfh);
            fclose($in); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- streaming JSONL backup I/O within the plugin's own uploads backup dir; WP_Filesystem has no streaming API.
            fclose($outfh); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- streaming JSONL backup I/O within the plugin's own uploads backup dir; WP_Filesystem has no streaming API.
            if (!@rename($tmp, $target)) { // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- atomically renames a temp backup file to its final name within the plugin's uploads backup dir.
                // Windows can refuse rename-over-existing; fall back to copy.
                if (!@copy($tmp, $target)) {
                    wp_delete_file($tmp);
                    $out['failed']++;
                    continue;
                }
                wp_delete_file($tmp);
            }
            $out['restored']++;
        }

        $zip->close();
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        return $out;
    }

    /* ------------------------------------------------ Import (remote restore) */

    /**
     * Import a snapshot ZIP (as produced for off-site upload — a flat archive of
     * manifest.json + data.jsonl[.gz]) back into the local store, then restore it.
     *
     * This is the landing point for **remote restore**: the Pro destinations
     * (Drive / pCloud / FTP) download a remote backup to a temp file and hand it
     * here. The archive becomes a normal local snapshot (so the user also gets it
     * back locally) and is then restored through the usual safety-first path.
     *
     * @param string $zip_path Absolute path to a downloaded snapshot .zip.
     * @return true|WP_Error
     */
    public function import_and_restore($zip_path)
    {
        $id = $this->import_zip($zip_path);
        if (is_wp_error($id)) {
            return $id;
        }
        return $this->restore($id);
    }

    /**
     * Extract a snapshot ZIP into the local backups folder and return its id.
     *
     * The archive is trusted to be one of our own snapshots, but we still extract
     * ONLY our known files by basename — embedded paths are never honored, so a
     * tampered archive can't write outside the snapshot folder (no zip-slip).
     *
     * @param string $zip_path
     * @return string|WP_Error Snapshot id on success.
     */
    public function import_zip($zip_path)
    {
        if (!class_exists('ZipArchive')) {
            return new WP_Error('wp_arzo_backup_zip', 'ZipArchive is not available on this server.');
        }
        if (!is_file($zip_path)) {
            return new WP_Error('wp_arzo_backup_zip', 'The downloaded backup file could not be found.');
        }
        $zip = new ZipArchive();
        if ($zip->open($zip_path) !== true) {
            return new WP_Error('wp_arzo_backup_zip', 'Could not open the downloaded backup archive.');
        }

        // Learn the snapshot id from the manifest before writing anything to disk.
        $manifest_raw = $zip->getFromName('manifest.json');
        if ($manifest_raw === false) {
            $zip->close();
            return new WP_Error('wp_arzo_backup_zip', 'This archive is not a WP Arzo snapshot (no manifest.json).');
        }
        $manifest = json_decode((string) $manifest_raw, true);
        $id = (is_array($manifest) && !empty($manifest['id'])) ? $this->sanitize_id($manifest['id']) : '';
        if ($id === '') {
            $zip->close();
            return new WP_Error('wp_arzo_backup_zip', 'The snapshot manifest is missing or has an invalid id.');
        }

        $dir = $this->snapshot_dir($id);
        if (!wp_mkdir_p($dir)) {
            $zip->close();
            return new WP_Error('wp_arzo_backup_zip', 'Could not create the local snapshot folder.');
        }

        // Whitelisted flat entries only — never trust a path inside the archive.
        foreach (array('manifest.json', 'data.jsonl', 'data.jsonl.gz') as $name) {
            $contents = $zip->getFromName($name);
            if ($contents !== false) {
                file_put_contents($dir . '/' . $name, $contents);
            }
        }
        $zip->close();

        $has_data = file_exists($dir . '/data.jsonl') || file_exists($dir . '/data.jsonl.gz');
        if (!file_exists($dir . '/manifest.json') || !$has_data) {
            return new WP_Error('wp_arzo_backup_zip', 'The archive did not contain the expected snapshot files.');
        }
        return $id;
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
            is_dir($path) ? $this->rrmdir($path) : wp_delete_file($path);
        }
        return @rmdir($dir); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- removes a temporary extraction dir within the plugin's uploads backup dir.
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
