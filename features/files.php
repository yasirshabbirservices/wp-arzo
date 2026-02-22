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
                'uploadAllow'   => array('image', 'text/plain', 'application/pdf', 'application/zip', 'application/x-zip-compressed'), // Mimetype `image` and `text/plain` allowed to upload
                'uploadOrder'   => array('deny', 'allow'),      // allowed Mimetype `image` and `text/plain` only
                'accessControl' => 'access',                    // disable and hide dot starting files (OPTIONAL)
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
    
    <script src="<?php echo WP_ARZO_PLUGIN_URL . 'assets/libs/elFinder/js/elfinder.full.js'; ?>"></script>

    <!-- elFinder Container -->
    <div id="elfinder" style="height: 100%;"></div>

    <script type="text/javascript" charset="utf-8">
        // Documentation for client options:
        // https://github.com/Studio-42/elFinder/wiki/Client-configuration-options
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
                        // expand current root on init
                        openRootOnLoad : true,
                        // auto load current dir parents
                        syncTree : true
                    },
                    // navbar options
                    navbar : {
                        minWidth : 150,
                        maxWidth : 500
                    },
                    // current working directory options
                    cwd : {
                        // display parent directory in listing as ".."
                        oldSchool : false
                    }
                },
                contextmenu : {
                    // navbarfolder menu
                    navbar : ['open', '|', 'copy', 'cut', 'paste', 'duplicate', '|', 'rm', '|', 'info'],
                    // current directory menu
                    cwd    : ['reload', 'back', '|', 'upload', 'mkdir', 'mkfile', 'paste', '|', 'info'],
                    // current directory file menu
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
        /* Custom overrides to match WP Arzo perfectly */
        .elfinder-cwd-view-list .elfinder-cwd-file .elfinder-cwd-filename {
            color: #e0e0e0;
        }
        .elfinder-cwd-view-list .elfinder-cwd-file:hover {
            background: #2a2a2a;
        }
        .elfinder-statusbar {
            color: #999;
        }
        /* Fix icon path if needed - usually handled by CSS but relative paths might be tricky */
    </style>
</div>
