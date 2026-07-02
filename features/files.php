<?php
/**
 * File Manager Feature (Powered by elFinder)
 *
 * @package WP_Arzo
 * @version 6.1
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Define elFinder path constant if not defined
if (!defined('ELFINDER_PHP_ROOT_PATH')) {
    define('ELFINDER_PHP_ROOT_PATH', WP_ARZO_PLUGIN_DIR . 'assets/libs/elFinder/php');
}

/**
 * elFinder access-control callback.
 *
 * Hides and denies read/write on dot-starting files (e.g. .env, .git, .htpasswd),
 * which would otherwise be exposed because the connector roots at ABSPATH.
 * Returning null lets elFinder apply its default for non-dotfiles.
 *
 * @return bool|null
 */
if (!function_exists('wp_arzo_elfinder_access')) {
    function wp_arzo_elfinder_access($attr, $path, $data, $volume, $isDir, $relpath)
    {
        $basename = basename($path);
        // Deny dotfiles/dotdirs (but not the volume root itself, whose relpath is '/').
        if (strlen($relpath) > 1 && $basename !== '' && $basename[0] === '.') {
            return in_array($attr, ['read', 'write', 'locked'], true)
                ? ($attr === 'locked')   // locked = true, read/write = false (deny)
                : null;
        }
        return null; // use default access control for everything else
    }
}

// --- BACKEND CONNECTOR ---
if (isset($_GET['operation']) && $_GET['operation'] === 'elfinder_connector') {

    // Disable error reporting to prevent JSON corruption
    error_reporting(0);
    
    // Include elFinder autoload (using our modified function name)
    require_once ELFINDER_PHP_ROOT_PATH . '/autoload.php';

    // Documentation for connector options:
    // https://github.com/Studio-42/elFinder/wiki/Connector-configuration-options
    $opts = array(
        'debug' => false,
        'roots' => array(
            array(
                'driver'        => 'LocalFileSystem',           // driver for accessing file system (REQUIRED)
                'path'          => ABSPATH,                     // path to files (REQUIRED)
                'URL'           => site_url(),                  // URL to files (REQUIRED)
                'trashHash'     => 't1_Lw',                     // elFinder's hash of trash folder
                'winHashFix'    => DIRECTORY_SEPARATOR !== '/', // to make hash same to Linux one on windows too
                'uploadDeny'    => array('all'),                // All Mimetypes not allowed to upload
                'uploadAllow'   => array(
                    'image', 
                    'text/plain', 
                    'application/pdf', 
                    'application/zip', 
                    'application/x-zip-compressed',
                    'text/css', 
                    'text/html', 
                    'application/javascript', 
                    'text/javascript', 
                    'text/x-php', 
                    'application/x-php',
                    'application/json',
                    'text/xml',
                    'application/xml'
                ), 
                'uploadOrder'   => array('deny', 'allow'),      // allowed Mimetype `image` and `text/plain` only
                'accessControl' => 'wp_arzo_elfinder_access',   // hide & deny dot-starting files (.env, .git, ...)
                'alias'         => 'Home',
                'attributes' => array(
                    array(
                        'pattern' => '/.tmb/',
                        'read' => false,
                        'write' => false,
                        'hidden' => true,
                        'locked' => false
                    ),
                    array(
                        'pattern' => '/.quarantine/',
                        'read' => false,
                        'write' => false,
                        'hidden' => true,
                        'locked' => false
                    )
                )
            )
        )
    );

    // Run elFinder
    $connector = new elFinderConnector(new elFinder($opts));
    $connector->run();
    exit;
}

// --- FRONTEND UI ---
?>
<div class="content" style="padding: 0; border: none; background: transparent;">
    <!-- Dependencies -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/themes/base/jquery-ui.min.css">
    <link rel="stylesheet" href="<?php echo WP_ARZO_PLUGIN_URL . 'assets/libs/elFinder/css/elfinder.min.css'; ?>">
    
    <!-- Theme (Material Gray to match WP Arzo) -->
    <link rel="stylesheet" href="<?php echo WP_ARZO_PLUGIN_URL . 'assets/themes/material/material-gray/material-gray.min.css'; ?>">
    <!-- WP Arzo Design Tokens -->
    <link rel="stylesheet" href="<?php echo WP_ARZO_PLUGIN_URL . 'assets/css/design-tokens.css'; ?>">

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/jquery-ui.min.js"></script>
    
    <!-- Define fm object for compatibility with Bit File Manager's modified elFinder -->
    <script>
        var fm = {
            ajaxURL: '<?php echo admin_url('admin-ajax.php?action=wp_arzo_standalone&tab=files&operation=elfinder_connector'); ?>',
            nonce: '<?php echo wp_create_nonce('wp_arzo_fm'); ?>',
            action: 'elfinder_connector',
            options: {
                themes: {
                    'material-gray' : '<?php echo WP_ARZO_PLUGIN_URL . 'assets/themes/material/material-gray/material-gray.min.css'; ?>'
                },
                theme: 'material-gray',
                lang: 'en'
            }
        };
    </script>
    
    <!-- elFinder JS -->
    <script src="<?php echo WP_ARZO_PLUGIN_URL . 'assets/libs/elFinder/js/elfinder.full.js'; ?>"></script>
    
    <!-- Editors Support -->
    <script src="<?php echo WP_ARZO_PLUGIN_URL . 'assets/libs/elFinder/js/extras/editors.default.js'; ?>"></script>

    <!-- elFinder Container -->
    <h1 style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);border:0;padding:0;margin:-1px;">File Manager</h1>
    <div id="elfinder" style="height: 100%;" aria-label="File manager"></div>

    <script type="text/javascript" charset="utf-8">
        $(document).ready(function() {
            // Calculate height to fit window
            var height = $(window).height() - 180; // Adjust for header/nav
            
            // Use window.fm properties if available, otherwise fallbacks
            var connectorUrl = (window.fm && window.fm.ajaxURL) ? window.fm.ajaxURL : '<?php echo admin_url('admin-ajax.php?action=wp_arzo_standalone&tab=files&operation=elfinder_connector'); ?>';
            
            $('#elfinder').elfinder({
                url : connectorUrl,
                lang : 'en',
                height: height,
                defaultView: 'list',
                themes : {
                    'material-gray' : '<?php echo WP_ARZO_PLUGIN_URL . 'assets/themes/material/material-gray/material-gray.min.css'; ?>'
                },
                // Enable Editors via CDN
                cdns : {
                    ace : 'https://cdnjs.cloudflare.com/ajax/libs/ace/1.4.12',
                    codemirror : 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13',
                    simplemde : 'https://cdnjs.cloudflare.com/ajax/libs/simplemde/1.11.2',
                    ckeditor : 'https://cdnjs.cloudflare.com/ajax/libs/ckeditor/4.22.1'
                },
                uiOptions : {
                    // toolbar configuration
                    toolbar : [
                        ['back', 'forward'],
                        ['reload'],
                        ['home', 'up'],
                        ['mkdir', 'mkfile', 'upload'],
                        ['open', 'download', 'getfile'],
                        ['info'],
                        ['quicklook'],
                        ['copy', 'cut', 'paste'],
                        ['rm'],
                        ['duplicate', 'rename', 'edit', 'resize'],
                        ['extract', 'archive'],
                        ['search'],
                        ['view', 'sort'],
                        ['help']
                    ],
                    // directories tree options
                    tree : {
                        openRootOnLoad : true,
                        syncTree : true
                    },
                    // navbar options
                    navbar : {
                        minWidth : 150,
                        maxWidth : 500
                    },
                    // current working directory options
                    cwd : {
                        oldSchool : false
                    }
                },
                contextmenu : {
                    navbar : ['open', '|', 'copy', 'cut', 'paste', 'duplicate', '|', 'rm', '|', 'info'],
                    cwd    : ['reload', 'back', '|', 'upload', 'mkdir', 'mkfile', 'paste', '|', 'info'],
                    files  : [
                        'getfile', '|','open', 'quicklook', '|', 'download', '|', 'copy', 'cut', 'paste', 'duplicate', '|',
                        'rm', '|', 'edit', 'rename', 'resize', '|', 'archive', 'extract', '|', 'info'
                    ]
                },
            });

            // Adjust height on resize
            $(window).resize(function() {
                var h = $(window).height() - 180;
                $('#elfinder').height(h);
                $('#elfinder').elfinder('instance').resize(); // Trigger resize
            });
        });
    </script>
    
    <style>
        /* Custom overrides to match WP Arzo perfectly (Dark/Green Theme) */
        /* Using Design Tokens for Consistency */

        /* General Background & Text */
        .elfinder-workzone {
            background-color: var(--arzo-bg-dark) !important;
        }
        .elfinder-cwd-view-list .elfinder-cwd-file .elfinder-cwd-filename {
            color: var(--arzo-text-strong) !important;
        }
        .elfinder-cwd table td {
            color: var(--arzo-text-strong) !important;
        }
        /* Hover on file row */
        .elfinder-cwd-view-list .elfinder-cwd-file:hover {
            background: var(--arzo-bg-hover) !important;
            color: var(--arzo-text-primary) !important;
        }
        .elfinder-statusbar {
            color: var(--arzo-text-secondary) !important;
            background: var(--arzo-bg-panel) !important;
            border-top: 1px solid var(--arzo-border) !important;
        }
        
        /* Navbar (Sidebar) */
        .elfinder-navbar {
            background: var(--arzo-bg-panel) !important;
            border-right: 1px solid var(--arzo-border) !important;
        }
        .elfinder-navbar-dir {
            color: var(--arzo-text-secondary) !important;
            cursor: pointer;
        }
        /* Navbar Hover */
        .elfinder-navbar-dir:hover {
            background: var(--arzo-bg-hover) !important;
            color: var(--arzo-text-primary) !important;
        }
        /* Navbar Selected/Active */
        .elfinder-navbar-dir.ui-selected, 
        .elfinder-navbar-dir.ui-state-active {
            background: var(--arzo-accent) !important;
            color: var(--arzo-text-on-accent) !important; /* High Contrast Text */
        }
        
        /* Active States & Accents (Main File View) */
        .ui-state-active, 
        .ui-widget-content .ui-state-active, 
        .ui-widget-header .ui-state-active,
        .elfinder-cwd-view-list .elfinder-cwd-file.ui-selected {
            border: 1px solid var(--arzo-accent) !important;
            background: var(--arzo-accent) !important;
            color: var(--arzo-text-on-accent) !important; /* High Contrast Text */
        }

        /* Ensure links/text inside selected items are also high contrast */
        .ui-state-active a,
        .ui-state-active span,
        .elfinder-cwd-view-list .elfinder-cwd-file.ui-selected .elfinder-cwd-filename {
            color: var(--arzo-text-on-accent) !important;
        }
        
        /* Toolbar */
        .elfinder-toolbar {
            background: var(--arzo-bg-panel) !important;
            border-bottom: 1px solid var(--arzo-border) !important;
        }
        .elfinder-button {
            border: 1px solid transparent !important;
        }
        .elfinder-button:hover, .elfinder-button.ui-state-hover {
            background: var(--arzo-bg-hover) !important;
            border-color: var(--arzo-border-strong) !important;
        }
        /* If toolbar buttons become active/pressed */
        .elfinder-button.ui-state-active {
            background: var(--arzo-accent) !important;
        }
        .elfinder-button.ui-state-active .elfinder-button-icon {
             /* Invert icon color if possible or rely on dark background contrast if icon is light. 
                If icon is SVG/font, set color. If image, filter might be needed. 
                Assuming icons are images based on previous context. */
             filter: brightness(0); /* Turn white icons black */
        }
        
        /* Dialogs (Info, Edit, etc) */
        .elfinder-dialog {
            background: var(--arzo-bg-panel) !important;
            color: var(--arzo-text-primary) !important;
            border: 1px solid var(--arzo-border) !important;
            box-shadow: var(--arzo-shadow) !important;
        }
        .elfinder-dialog-title {
            color: var(--arzo-text-primary) !important;
        }
        .elfinder-dialog-icon {
            opacity: 0.8;
        }
        .ui-dialog-buttonpane {
            background: var(--arzo-bg-panel) !important;
            border-top: 1px solid var(--arzo-border) !important;
        }
        
        /* Dialog Buttons */
        .elfinder-dialog .ui-button {
            background: var(--arzo-bg-hover) !important;
            color: var(--arzo-text-primary) !important;
            border: 1px solid var(--arzo-border) !important;
        }
        .elfinder-dialog .ui-button:hover {
            background: var(--arzo-accent) !important;
            color: var(--arzo-text-on-accent) !important;
        }
        
        /* Context Menu */
        .elfinder-contextmenu {
            background: var(--arzo-bg-panel) !important;
            border: 1px solid var(--arzo-border) !important;
            color: var(--arzo-text-primary) !important;
        }
        .elfinder-contextmenu-item:hover {
            background: var(--arzo-accent) !important;
            color: var(--arzo-text-on-accent) !important;
        }
        .elfinder-contextmenu .elfinder-contextmenu-item .elfinder-contextmenu-icon {
             /* Default icons might be grey/white. On hover they need to be dark. */
        }
        .elfinder-contextmenu-item:hover .elfinder-contextmenu-icon {
            filter: brightness(0);
        }
        
        /* Ace Editor Customization */
        #ace_settingsmenu {
            background: var(--arzo-bg-panel) !important;
            color: var(--arzo-text-primary) !important;
        }
        
        /* Input fields */
        .elfinder-dialog input, .elfinder-dialog select, .elfinder-dialog textarea {
            background: var(--arzo-bg-hover) !important;
            color: var(--arzo-text-primary) !important;
            border: 1px solid var(--arzo-border-strong) !important;
        }

        /* Filter Inputs in File List */
        .elfinder-cwd input, .elfinder-cwd select {
             color: var(--arzo-text-primary) !important;
        }
        
        /* Scrollbars */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: var(--arzo-bg-dark);
        }
        ::-webkit-scrollbar-thumb {
            background: var(--arzo-border-strong);
            border-radius: var(--arzo-radius);
        }
        ::-webkit-scrollbar-thumb:hover {
            background: var(--arzo-text-muted);
        }

        /* ============================================================
           Dark skin v2 — close the remaining light gaps (jQuery-UI base
           chrome leaks through the material theme) + softer, dashboard-
           consistent selection. All token-driven.
           ============================================================ */

        /* List-view COLUMN HEADER (Name / Permissions / Modified / Size / Kind)
           — was a light jQuery-UI/material bar. The material theme rules are highly
           specific, so scope to the #elfinder id and clear the gradient image. */
        #elfinder .elfinder-cwd-thead,
        #elfinder .elfinder-cwd-thead td,
        #elfinder .elfinder-cwd-view-list thead td,
        #elfinder td[class*="elfinder-cwd-view-th"] {
            background: var(--arzo-bg-elev) !important;
            background-image: none !important;
            color: var(--arzo-text-secondary) !important;
            border-color: var(--arzo-border) !important;
            font-weight: 600 !important;
            text-shadow: none !important;
        }
        #elfinder td[class*="elfinder-cwd-view-th"]:hover {
            background: var(--arzo-bg-hover) !important; color: var(--arzo-text-primary) !important;
        }
        #elfinder td[class*="elfinder-cwd-view-th"].ui-state-active {
            background: var(--arzo-bg-hover) !important; color: var(--arzo-accent) !important;
        }

        /* jQuery-UI bases that still render light (dialog chrome, headers, overlay, tooltips). */
        .elfinder .ui-widget-header { background: var(--arzo-bg-elev) !important; color: var(--arzo-text-strong) !important; border-color: var(--arzo-border) !important; }
        .elfinder-dialog .ui-dialog-titlebar,
        .std42-dialog.ui-dialog .ui-dialog-titlebar { background: var(--arzo-bg-elev) !important; color: var(--arzo-text-strong) !important; border-color: var(--arzo-border) !important; }
        .ui-widget-overlay { background: var(--arzo-bg-dark) !important; opacity: .6 !important; }
        body .ui-tooltip, body .ui-tooltip.ui-widget {
            background: var(--arzo-bg-elev) !important; color: var(--arzo-text-primary) !important;
            border: 1px solid var(--arzo-border-strong) !important; box-shadow: var(--arzo-shadow) !important;
        }

        /* Inputs inside the file list (inline rename / filter) + the search box
           → sunken dark, never black-on-white. */
        .elfinder-cwd input,
        .elfinder-cwd select,
        .elfinder-cwd textarea,
        .elfinder-toolbar input[type="text"],
        .elfinder-button-search input,
        .elfinder-navbar input {
            background: var(--arzo-bg-input) !important;
            color: var(--arzo-text-primary) !important;
            border: 1px solid var(--arzo-border-strong) !important;
        }

        /* Path / breadcrumb links → brand accent, never browser blue. */
        .elfinder-statusbar a, .elfinder-path a { color: var(--arzo-accent) !important; text-decoration: none !important; }
        .elfinder-navbar .elfinder-navbar-root { color: var(--arzo-text-strong) !important; }

        /* Softer selection — accent-soft + ring, matching the dashboard's active-tab
           look (was a loud fully-filled green). Navbar dirs + file rows. */
        .elfinder-navbar-dir.ui-selected,
        .elfinder-navbar-dir.ui-state-active {
            background: var(--arzo-accent-soft) !important;
            color: var(--arzo-accent) !important;
            box-shadow: inset 0 0 0 1px var(--arzo-accent-ring) !important;
        }
        .elfinder-cwd-view-list .elfinder-cwd-file.ui-selected,
        .elfinder-cwd-file.ui-selected {
            background: var(--arzo-accent-soft) !important;
            border-color: var(--arzo-accent-ring) !important;
        }
        .elfinder-cwd-file.ui-selected .elfinder-cwd-filename,
        .elfinder-cwd-file.ui-selected td { color: var(--arzo-text-strong) !important; }
    </style>
</div>
